<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPromotionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Promotion::query()
            ->withCount(['products', 'orders']);

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $this->applyStatusFilter($query, (string) $request->input('status'));

        match ($request->input('sort', 'newest')) {
            'oldest' => $query->oldest(),
            'start_asc' => $query->orderBy('starts_at'),
            'end_asc' => $query->orderBy('ends_at'),
            'value_desc' => $query->orderByDesc('value'),
            default => $query->latest(),
        };

        $promotions = $query->paginate(12)->withQueryString();
        $now = now();

        $summary = [
            'total' => Promotion::count(),
            'active' => Promotion::query()
                ->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->count(),
            'upcoming' => Promotion::query()
                ->where('is_active', true)
                ->where('starts_at', '>', $now)
                ->count(),
            'expired' => Promotion::query()
                ->where('ends_at', '<', $now)
                ->count(),
            'linked_products' => DB::table('promotion_product')->distinct()->count('product_id'),
        ];

        return view('admin.promotions.index', compact('promotions', 'summary'));
    }

    public function create(): View
    {
        return view('admin.promotions.create', [
            'promotion' => new Promotion([
                'type' => 'percent',
                'is_active' => true,
                'starts_at' => now()->startOfHour(),
                'ends_at' => now()->addDays(7)->endOfHour(),
            ]),
            'products' => $this->productOptions(),
            'selectedProducts' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        DB::transaction(function () use ($data, $productIds): void {
            $promotion = Promotion::create($data);
            $promotion->products()->sync($productIds);
        });

        $this->forgetPromotionCaches();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'La promotion a été créée et enregistrée dans la base de données.');
    }

    public function edit(Promotion $promotion): View
    {
        $promotion->load('products:id');

        return view('admin.promotions.edit', [
            'promotion' => $promotion,
            'products' => $this->productOptions(),
            'selectedProducts' => $promotion->products->pluck('id'),
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $data = $this->validatedData($request, $promotion);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        DB::transaction(function () use ($promotion, $data, $productIds): void {
            $promotion->update($data);
            $promotion->products()->sync($productIds);
        });

        $this->forgetPromotionCaches();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'La promotion a été mise à jour.');
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);
        $this->forgetPromotionCaches();

        return back()->with(
            'success',
            $promotion->is_active ? 'La promotion est maintenant active.' : 'La promotion a été désactivée.'
        );
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        if ($promotion->orders()->exists()) {
            return back()->with(
                'error',
                'Cette promotion a déjà été utilisée dans une commande. Désactivez-la au lieu de la supprimer.'
            );
        }

        DB::transaction(function () use ($promotion): void {
            $promotion->products()->detach();
            $promotion->users()->detach();
            $promotion->delete();
        });

        $this->forgetPromotionCaches();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'La promotion a été supprimée.');
    }

    private function validatedData(Request $request, ?Promotion $promotion = null): array
    {
        $request->merge([
            'code' => $this->normaliseCode((string) $request->input('code')),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('promotions', 'code')->ignore($promotion?->id),
            ],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ], [
            'code.required' => 'Le nom ou code de la promotion est obligatoire.',
            'code.unique' => 'Ce code de promotion existe déjà.',
            'value.required' => 'La valeur de la remise est obligatoire.',
            'starts_at.required' => 'La date de début est obligatoire.',
            'ends_at.required' => 'La date de fin est obligatoire.',
            'ends_at.after' => 'La date de fin doit être postérieure à la date de début.',
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'Une remise en pourcentage ne peut pas dépasser 100 %.',
            ]);
        }

        return $data;
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $now = now();

        match ($status) {
            'active' => $query
                ->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now),
            'upcoming' => $query
                ->where('is_active', true)
                ->where('starts_at', '>', $now),
            'expired' => $query->where('ends_at', '<', $now),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    private function productOptions()
    {
        return Product::query()
            ->whereNull('archived_at')
            ->with(['shop:id,name', 'category:id,name'])
            ->orderBy('name')
            ->get();
    }

    private function normaliseCode(string $value): string
    {
        $value = Str::upper(Str::ascii(trim($value)));
        $value = preg_replace('/[^A-Z0-9_-]+/', '-', $value) ?? '';

        return trim(preg_replace('/-+/', '-', $value) ?? '', '-_');
    }

    private function forgetPromotionCaches(): void
    {
        Cache::forget('ovanie:promotions:active');
        Cache::forget('homepage.public.v1');
    }
}
