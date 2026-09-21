<?php

namespace App\Http\Controllers;

use App\Models\VendorPayout;
use App\Services\VendorFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;

class VendorPayoutController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor']);
    }

    /**
     * Page reversements vendeur détaillée.
     */
    public function index(Request $request, VendorFinanceService $finance)
    {
        $user = Auth::user();
        $shop = $user?->shop;

        if (! $shop) {
            return redirect()
                ->route('open-shop')
                ->with('error', 'Vous devez créer une boutique avant d’accéder aux reversements vendeur.');
        }

        $finance->syncShop($shop);

        $status = $request->query('status');
        $search = trim((string) $request->query('q'));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $baseQuery = VendorPayout::query()
            ->with(['order.client', 'shop', 'vendor'])
            ->where('shop_id', $shop->id)
            ->where('vendor_id', $user->id);

        if ($status && in_array($status, $this->allowedStatuses(), true)) {
            $baseQuery->where('status', $status);
        }

        if ($dateFrom) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $baseQuery->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('payout_reference', 'like', '%' . $search . '%')
                    ->orWhere('batch_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', '%' . $search . '%')
                            ->orWhere('id', $search);
                    });
            });
        }

        $payouts = (clone $baseQuery)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = $finance->summary($shop, (int) $user->id);

        $paymentMethod = [
            'operator' => $shop->mm_operator ?: 'Non défini',
            'number' => $shop->mm_number ?: 'Non défini',
            'holder' => $shop->mm_holder ?: 'Non défini',
            'direct_payment' => (bool) $shop->direct_payment,
        ];

        $statuses = $this->statusFilters();
        $view = View::exists('vendor.payouts.index') ? 'vendor.payouts.index' : 'vendor.payments';

        return view($view, compact(
            'shop',
            'payouts',
            'summary',
            'paymentMethod',
            'statuses',
            'status',
            'search',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Détail d'un reversement vendeur.
     */
    public function show(VendorPayout $payout)
    {
        $this->authorizeVendorPayout($payout);

        $payout->load([
            'order.client',
            'order.items.product.shop',
            'order.payments',
            'shop',
            'vendor',
        ]);

        $shopItems = $payout->order?->items
            ? $payout->order->items->filter(function ($item) use ($payout) {
                $itemShopId = $item->shop_id ?: $item->product?->shop_id;
                return (int) $itemShopId === (int) $payout->shop_id;
            })
            : collect();

        return view('vendor.payouts.show', compact('payout', 'shopItems'));
    }

    /**
     * Export CSV des reversements du vendeur connecté.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        $shop = $user?->shop;

        abort_unless($shop, 403, 'Boutique introuvable.');

        $payouts = VendorPayout::with(['order'])
            ->where('shop_id', $shop->id)
            ->where('vendor_id', $user->id)
            ->latest()
            ->get();

        $filename = 'reversements-' . now()->format('Ymd-His') . '.csv';

        return Response::streamDownload(function () use ($payouts) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Commande',
                'Vente boutique',
                'Commission OVANIE',
                'Net vendeur',
                'Methode',
                'Telephone',
                'Reference',
                'Statut',
            ], ';');

            foreach ($payouts as $payout) {
                fputcsv($handle, [
                    optional($payout->created_at)->format('d/m/Y H:i'),
                    $payout->order?->order_number ?: ('OVN-' . $payout->order_id),
                    (float) $payout->total_amount,
                    (float) $payout->commission_amount,
                    (float) $payout->payout_amount,
                    $payout->payment_method,
                    $payout->phone,
                    $payout->payout_reference,
                    $payout->status_label,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Demande de suivi envoyée par le vendeur à l'admin.
     */
    public function requestFollowUp(Request $request, VendorPayout $payout)
    {
        $this->authorizeVendorPayout($payout);

        $validated = $request->validate([
            'vendor_note' => ['nullable', 'string', 'max:500'],
        ]);

        $meta = $payout->meta ?? [];
        $meta['vendor_followup_requested_at'] = now()->toDateTimeString();
        $meta['vendor_followup_requested_by'] = Auth::id();
        $meta['vendor_followup_note'] = $validated['vendor_note'] ?? null;

        $payout->update([
            'meta' => $meta,
            'vendor_followup_requested_at' => now(),
            'vendor_note' => $validated['vendor_note'] ?? $payout->vendor_note,
        ]);

        return back()->with('success', 'Votre demande de suivi a été envoyée à l’administration OVANIE.');
    }

    private function authorizeVendorPayout(VendorPayout $payout): void
    {
        $user = Auth::user();
        $shop = $user?->shop;

        abort_unless($shop, 403, 'Vous devez avoir une boutique pour accéder à ce reversement.');

        abort_unless(
            (int) $payout->shop_id === (int) $shop->id && (int) $payout->vendor_id === (int) $user->id,
            403,
            'Vous n’avez pas accès à ce reversement.'
        );
    }

    private function allowedStatuses(): array
    {
        return array_keys($this->statusFilters());
    }

    private function statusFilters(): array
    {
        return [
            VendorPayout::STATUS_WAITING_PAYMENT => 'Attente paiement client',
            VendorPayout::STATUS_WAITING_RECEPTION => 'Attente réception client',
            VendorPayout::STATUS_BLOCKED => 'Bloqué',
            VendorPayout::STATUS_PENDING => 'Programmé',
            VendorPayout::STATUS_APPROVED => 'Prêt à payer',
            VendorPayout::STATUS_PROCESSING => 'En traitement',
            VendorPayout::STATUS_PAID => 'Payé',
            VendorPayout::STATUS_FAILED => 'Échec',
            VendorPayout::STATUS_CANCELLED => 'Annulé',
        ];
    }
}
