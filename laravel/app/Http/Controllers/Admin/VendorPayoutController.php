<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\VendorPayout;
use App\Services\VendorPayoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorPayoutController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'status' => ['nullable', 'in:' . implode(',', $this->allowedStatuses())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:newest,oldest,amount_desc,amount_asc,due_first'],
        ]);

        $query = VendorPayout::query()->with(['vendor', 'shop', 'order']);
        $this->applyFilters($query, $filters);

        $summary = [
            'total_amount' => (float) (clone $query)->sum('payout_amount'),
            'ready_amount' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_APPROVED)
                ->sum('payout_amount'),
            'processing_amount' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_PROCESSING)
                ->sum('payout_amount'),
            'paid_amount' => (float) (clone $query)
                ->where('status', VendorPayout::STATUS_PAID)
                ->sum('payout_amount'),
            'records_count' => (int) (clone $query)->count(),
            'ready_count' => (int) (clone $query)
                ->where('status', VendorPayout::STATUS_APPROVED)
                ->count(),
            'blocked_count' => (int) (clone $query)
                ->whereIn('status', [
                    VendorPayout::STATUS_BLOCKED,
                    VendorPayout::STATUS_WAITING_PAYMENT,
                    VendorPayout::STATUS_WAITING_RECEPTION,
                ])
                ->count(),
        ];

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at'),
            'amount_desc' => $query->orderByDesc('payout_amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('payout_amount')->orderByDesc('id'),
            'due_first' => $query
                ->orderByRaw('CASE WHEN scheduled_for IS NULL THEN 1 ELSE 0 END')
                ->orderBy('scheduled_for')
                ->orderByDesc('id'),
            default => $query->latest('created_at'),
        };

        $payouts = $query->paginate(12)->withQueryString();

        $shops = Shop::query()
            ->whereIn('id', VendorPayout::query()->whereNotNull('shop_id')->select('shop_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $isLive = strtolower((string) config('paydunya.mode', 'test')) === 'live';
        $executionMode = (string) config(
            'vendor_payouts.execution_mode',
            $isLive ? 'manual' : 'simulation'
        );

        return view('admin.payouts.index', compact(
            'payouts',
            'shops',
            'summary',
            'filters',
            'isLive',
            'executionMode'
        ));
    }

    public function approve(VendorPayout $payout, VendorPayoutService $service)
    {
        if ($payout->status !== VendorPayout::STATUS_PENDING) {
            return back()->with('error', 'Seul un reversement programmé peut être approuvé.');
        }

        if ($payout->scheduled_for && $payout->scheduled_for->isFuture()) {
            return back()->with('error', 'Ce reversement n’est pas encore arrivé à échéance.');
        }

        $service->approve($payout);

        return back()->with('success', 'Reversement approuvé et prêt pour traitement.');
    }

    public function markProcessing(Request $request, VendorPayout $payout, VendorPayoutService $service)
    {
        if ($payout->status !== VendorPayout::STATUS_APPROVED) {
            return back()->with('error', 'Le reversement doit être approuvé avant de passer en traitement.');
        }

        $validated = $request->validate([
            'batch_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $service->markProcessing(
            $payout,
            $validated['batch_reference'] ?? ('LOT-' . now()->format('YmdHis'))
        );

        return back()->with('success', 'Le reversement est maintenant en traitement.');
    }

    public function markPaid(Request $request, VendorPayout $payout, VendorPayoutService $service)
    {
        if ($payout->status === VendorPayout::STATUS_PAID) {
            return back()->with('error', 'Ce reversement est déjà marqué comme payé.');
        }

        if ($payout->status === VendorPayout::STATUS_CANCELLED) {
            return back()->with('error', 'Un reversement annulé ne peut pas être payé.');
        }

        $isLive = strtolower((string) config('paydunya.mode', 'test')) === 'live';
        $executionMode = (string) config('vendor_payouts.execution_mode', $isLive ? 'manual' : 'simulation');

        if ($isLive && $executionMode === 'paydunya') {
            return back()->with(
                'error',
                'Le mode PayDunya automatique est actif : seul le statut confirmé par PayDunya peut marquer ce reversement comme payé.'
            );
        }

        if (! in_array($payout->status, [VendorPayout::STATUS_APPROVED, VendorPayout::STATUS_PROCESSING], true)) {
            return back()->with('error', 'Le reversement doit être approuvé ou en traitement avant d’être confirmé payé.');
        }

        $referenceRules = ['nullable', 'string', 'max:120'];
        if ($isLive && config('vendor_payouts.require_reference_in_live', true)) {
            $referenceRules[0] = 'required';
        }

        $validated = $request->validate([
            'payout_reference' => $referenceRules,
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $reference = trim((string) ($validated['payout_reference'] ?? ''));

        if (! $isLive) {
            $reference = $reference !== ''
                ? $reference
                : 'TEST-VERSEMENT-' . now()->format('YmdHis') . '-' . $payout->id;
        }

        $meta = $payout->meta ?? [];
        $meta['payment_environment'] = $isLive ? 'production' : 'test';
        $meta['payout_execution_mode'] = $executionMode;
        $meta['simulation'] = ! $isLive;
        $meta['confirmed_by_admin_at'] = now()->toDateTimeString();

        $payout->forceFill([
            'admin_note' => $validated['admin_note'] ?? $payout->admin_note,
            'meta' => $meta,
        ])->save();

        $service->markPaid($payout, $reference);

        return back()->with(
            'success',
            $isLive
                ? 'Reversement confirmé avec sa référence réelle.'
                : 'Reversement simulé avec succès. Aucun argent réel n’a été transféré.'
        );
    }

    public function markFailed(Request $request, VendorPayout $payout, VendorPayoutService $service)
    {
        if (in_array($payout->status, [VendorPayout::STATUS_PAID, VendorPayout::STATUS_CANCELLED], true)) {
            return back()->with('error', 'Ce reversement ne peut plus être marqué en échec.');
        }

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ], [
            'admin_note.required' => 'Indiquez la raison de l’échec du reversement.',
        ]);

        $payout->forceFill(['admin_note' => $validated['admin_note']])->save();
        $service->fail($payout, $validated['admin_note']);

        return back()->with('success', 'Le reversement a été marqué en échec.');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery
                    ->where('payout_reference', 'like', "%{$search}%")
                    ->orWhere('batch_reference', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('order', function (Builder $orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('shop', function (Builder $shopQuery) use ($search) {
                        $shopQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('vendor', function (Builder $vendorQuery) use ($search) {
                        $vendorQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', (int) $filters['shop_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    private function allowedStatuses(): array
    {
        return [
            VendorPayout::STATUS_BLOCKED,
            VendorPayout::STATUS_WAITING_PAYMENT,
            VendorPayout::STATUS_WAITING_RECEPTION,
            VendorPayout::STATUS_PENDING,
            VendorPayout::STATUS_APPROVED,
            VendorPayout::STATUS_PROCESSING,
            VendorPayout::STATUS_PAID,
            VendorPayout::STATUS_FAILED,
            VendorPayout::STATUS_CANCELLED,
        ];
    }
}
