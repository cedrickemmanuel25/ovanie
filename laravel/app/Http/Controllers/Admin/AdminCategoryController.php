<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\MarketplacePerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $roots = Category::query()
            ->roots()
            ->withCount(['products', 'children'])
            ->with([
                'children' => fn ($query) => $query
                    ->withCount(['products', 'masterProducts'])
                    ->ordered(),
            ])
            ->ordered()
            ->get();

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        $categories = $roots
            ->map(function (Category $root) use ($search, $status, $type): Category {
                $children = $root->children
                    ->filter(fn (Category $child) => $this->matchesFilters($child, $search, $status))
                    ->values();

                if ($type === 'principales') {
                    $children = collect();
                }

                $root->setRelation('children', $children);

                return $root;
            })
            ->filter(function (Category $root) use ($search, $status, $type): bool {
                if ($type === 'sous-categories') {
                    return $root->children->isNotEmpty();
                }

                if ($type === 'principales') {
                    return $this->matchesFilters($root, $search, $status);
                }

                return $this->matchesFilters($root, $search, $status)
                    || $root->children->isNotEmpty();
            })
            ->values();

        $stats = [
            'total' => Category::query()->count(),
            'roots' => Category::query()->roots()->count(),
            'children' => Category::query()->subcategories()->count(),
            'active' => Category::query()->active()->count(),
            'assigned_products' => Product::query()->whereNotNull('category_id')->count(),
            'without_category' => Product::query()->whereNull('category_id')->count(),
        ];

        return view('admin.categories.index', compact(
            'categories',
            'stats',
            'search',
            'status',
            'type',
        ));
    }

    public function create(Request $request): View
    {
        $requestedType = (string) $request->query('type', 'category');
        $isSubcategory = $requestedType === 'subcategory' || $request->filled('parent_id');

        $category = new Category([
            'parent_id' => $isSubcategory ? ($request->integer('parent_id') ?: null) : null,
            'status' => 'actif',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $categoryType = $isSubcategory ? 'subcategory' : 'category';
        $parents = $this->parentOptions();

        return view(
            $isSubcategory ? 'admin.categories.create-subcategory' : 'admin.categories.create',
            compact('category', 'categoryType', 'parents')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $parent] = $this->validatedData($request);
        $imagePath = $this->storeImage($request);

        $category = DB::transaction(function () use ($data, $parent, $imagePath): Category {
            $category = Category::create($this->payload($data, $parent, $imagePath));
            $this->flushCategoryCaches();

            return $category;
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('success', $category->parent_id
                ? 'La sous-catégorie a été créée et est disponible dans les formulaires produits.'
                : 'La catégorie principale a été créée et est disponible sur les pages concernées.');
    }

    public function edit(Category $category): View
    {
        $categoryType = $category->parent_id ? 'subcategory' : 'category';
        $parents = $this->parentOptions($category);

        return view('admin.categories.edit', compact('category', 'categoryType', 'parents'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        [$data, $parent] = $this->validatedData($request, $category);

        if ($parent && $category->children()->exists()) {
            throw ValidationException::withMessages([
                'category_type' => 'Cette catégorie contient des sous-catégories. Déplacez-les avant de la convertir en sous-catégorie.',
            ]);
        }

        $previousImagePath = $category->image_path;
        $imagePath = $this->storeImage($request) ?? ($request->boolean('remove_image') ? null : $category->image_path);

        DB::transaction(function () use ($category, $data, $parent, $imagePath): void {
            $category->update($this->payload($data, $parent, $imagePath));

            if ($category->parent_id === null && $category->status === 'inactif') {
                $category->children()->update([
                    'status' => 'inactif',
                    'is_active' => false,
                ]);
            }

            $this->flushCategoryCaches();
        });

        if ($previousImagePath && $previousImagePath !== $imagePath && Storage::disk('public')->exists($previousImagePath)) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'La catégorie a été mise à jour sur toutes les pages qui utilisent le catalogue.');
    }

    public function updateStatus(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:actif,inactif'],
        ]);

        DB::transaction(function () use ($category, $data): void {
            $category->update([
                'status' => $data['status'],
                'is_active' => $data['status'] === 'actif',
            ]);

            if ($category->parent_id === null && $data['status'] === 'inactif') {
                $category->children()->update([
                    'status' => 'inactif',
                    'is_active' => false,
                ]);
            }

            $this->flushCategoryCaches();
        });

        return back()->with('success', $data['status'] === 'actif'
            ? 'La catégorie est maintenant active.'
            : 'La catégorie est maintenant inactive.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $productsCount = $category->products()->count();
        $masterProductsCount = $category->masterProducts()->count();
        $childrenCount = $category->children()->count();

        if ($productsCount > 0 || $masterProductsCount > 0 || $childrenCount > 0) {
            return back()->with('error', implode(' ', array_filter([
                $childrenCount > 0 ? "Cette catégorie contient {$childrenCount} sous-catégorie(s)." : null,
                $productsCount > 0 ? "Elle est utilisée par {$productsCount} produit(s)." : null,
                $masterProductsCount > 0 ? "Elle est utilisée par {$masterProductsCount} référence(s) technique(s)." : null,
                'Déplacez d’abord ces éléments ou rendez la catégorie inactive.',
            ])));
        }

        $imagePath = $category->image_path;
        $category->delete();
        $this->flushCategoryCaches();

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'La catégorie a été supprimée.');
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'category_type' => ['required', 'in:category,subcategory'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1200'],
            'icon' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', 'in:actif,inactif'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'parent_id.exists' => 'La catégorie principale sélectionnée est introuvable.',
            'category_type.required' => 'Choisissez catégorie principale ou sous-catégorie.',
        ]);

        $parent = null;

        if ($data['category_type'] === 'subcategory') {
            if (empty($data['parent_id'])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Choisissez la catégorie principale de cette sous-catégorie.',
                ]);
            }

            $parent = Category::query()->findOrFail((int) $data['parent_id']);

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une sous-catégorie doit être rattachée directement à une catégorie principale.',
                ]);
            }

            if ($category && $parent->is($category)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une catégorie ne peut pas être son propre parent.',
                ]);
            }
        }

        $duplicate = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
            ->when($parent, fn ($query) => $query->where('parent_id', $parent->id))
            ->when(!$parent, fn ($query) => $query->whereNull('parent_id'))
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Une catégorie portant ce nom existe déjà à ce niveau.',
            ]);
        }

        return [$data, $parent];
    }

    private function payload(array $data, ?Category $parent, ?string $imagePath = null): array
    {
        return [
            'parent_id' => $parent?->id,
            'level' => $parent ? 2 : 1,
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'icon' => filled($data['icon'] ?? null) ? trim($data['icon']) : null,
            'image_path' => $imagePath,
            'status' => $data['status'],
            'is_active' => $data['status'] === 'actif',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('categories', 'public');
    }

    private function parentOptions(?Category $excluded = null)
    {
        return Category::query()
            ->roots()
            ->when($excluded, fn ($query) => $query->whereKeyNot($excluded->id))
            ->ordered()
            ->get();
    }

    private function matchesFilters(Category $category, string $search, string $status): bool
    {
        if ($status !== '' && $category->status !== $status) {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', [
            $category->name,
            $category->slug,
            $category->description,
        ]));

        return str_contains($haystack, mb_strtolower($search));
    }

    private function flushCategoryCaches(): void
    {
        app(MarketplacePerformanceService::class)->flushMarketplaceCache();
        Cache::forget('homepage.public.' . config('homepage.cache.version', 'v1'));
    }
}
