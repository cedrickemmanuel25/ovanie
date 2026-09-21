<?php

namespace App\Http\Controllers;

use App\Models\VendorPayout;
use App\Services\VendorFinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class VendorPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor']);
    }

    /**
     * Mes ventes : lecture financière de la part de la boutique.
     * Cette page n'est pas l'échéancier des reversements.
     */
    public function index(Request $request, VendorFinanceService $finance)
    {
        $user = Auth::user();
        $shop = $user?->shop;

        if (! $shop) {
            return redirect()->route('open-shop')
                ->with('error', 'Vous devez créer une boutique avant d’accéder à vos ventes.');
        }

        $finance->syncShop($shop);

        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));
        $method = trim((string) $request->query('method', ''));
        $dateFrom = $this->validDate((string) $request->query('from', ''));
        $dateTo = $this->validDate((string) $request->query('to', ''));

        $query = $finance->baseQuery($shop, (int) $user->id)
            ->with(['order.client'])
            ->latest();

        if ($status !== '' && in_array($status, $this->allowedStatuses(), true)) {
            $query->where('status', $status);
        }

        if ($method !== '' && in_array($method, $this->allowedPaymentMethods(), true)) {
            $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('payment_method', $method));
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('payout_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', '%' . $search . '%')
                            ->orWhereHas('client', function ($clientQuery) use ($search) {
                                $clientQuery->where('name', 'like', '%' . $search . '%')
                                    ->orWhere('email', 'like', '%' . $search . '%');
                            });
                    });
            });
        }

        $payouts = $query->paginate(15)->withQueryString();
        $summary = $finance->summary($shop, (int) $user->id);

        // payout_amount (net vendeur), pas total_amount (prix payé par le
        // client, commission OVANIE incluse) : le graphique doit refléter ce
        // que le vendeur touche réellement, pas le chiffre d'affaires brut.
        $thirtyDayOperations = $finance->baseQuery($shop, (int) $user->id)
            ->with('order:id,payment_method')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->get(['id', 'order_id', 'payout_amount', 'status', 'created_at', 'paid_at']);

        $salesByDate = $thirtyDayOperations
            ->groupBy(fn ($payout) => optional($payout->created_at)->format('Y-m-d'))
            ->map(fn ($rows) => (float) $rows->sum('payout_amount'));

        $chartData = collect(range(29, 0))
            ->map(function (int $daysAgo) use ($salesByDate) {
                $date = now()->subDays($daysAgo);

                return [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('d/m'),
                    'amount' => (float) ($salesByDate[$date->format('Y-m-d')] ?? 0),
                ];
            })
            ->values();

        $dominantPaymentMethod = $thirtyDayOperations
            ->pluck('order.payment_method')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $lastPaymentAt = $finance->baseQuery($shop, (int) $user->id)
            ->where('status', VendorPayout::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->max('paid_at');

        $view = View::exists('vendor.payments') ? 'vendor.payments' : 'daniel.payments';

        return view($view, compact(
            'shop',
            'payouts',
            'summary',
            'status',
            'search',
            'method',
            'dateFrom',
            'dateTo',
            'chartData',
            'dominantPaymentMethod',
            'lastPaymentAt',
        ));
    }

    public function show(VendorPayout $payment)
    {
        $this->authorizeVendorPayout($payment);

        abort_unless($payment->order_id, 404, 'Commande associée introuvable.');

        return redirect()->route('vendor.orders.show', $payment->order_id);
    }

    /**
     * Le vendeur ne peut jamais déclarer un reversement payé.
     * Cette action crée seulement une demande de suivi administratif.
     */
    public function markCompleted(Request $request, VendorPayout $payment)
    {
        $this->authorizeVendorPayout($payment);

        $validated = $request->validate([
            'vendor_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($payment->isPaid()) {
            return back()->with('info', 'Ce reversement est déjà marqué comme payé.');
        }

        $meta = $payment->meta ?? [];
        $meta['vendor_followup_requested_at'] = now()->toDateTimeString();
        $meta['vendor_followup_requested_by'] = Auth::id();
        $meta['vendor_followup_note'] = $validated['vendor_note'] ?? null;

        $payment->update([
            'meta' => $meta,
            'vendor_followup_requested_at' => now(),
            'vendor_note' => $validated['vendor_note'] ?? $payment->vendor_note,
        ]);

        return back()->with('success', 'Votre demande de suivi a été envoyée à l’administration OVANIE.');
    }

    private function authorizeVendorPayout(VendorPayout $payout): void
    {
        $user = Auth::user();
        $shop = $user?->shop;

        abort_unless($shop, 403, 'Boutique introuvable.');
        abort_unless(
            (int) $payout->shop_id === (int) $shop->id
                && (int) $payout->vendor_id === (int) $user->id,
            403,
            'Vous n’avez pas accès à cette opération.'
        );
    }

    private function validDate(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function allowedPaymentMethods(): array
    {
        return [
            'paydunya',
            'bank_transfer',
            'cash_on_delivery',
        ];
    }

    private function allowedStatuses(): array
    {
        return [
            VendorPayout::STATUS_WAITING_PAYMENT,
            VendorPayout::STATUS_WAITING_RECEPTION,
            VendorPayout::STATUS_BLOCKED,
            VendorPayout::STATUS_PENDING,
            VendorPayout::STATUS_APPROVED,
            VendorPayout::STATUS_PROCESSING,
            VendorPayout::STATUS_PAID,
            VendorPayout::STATUS_FAILED,
        ];
    }
}
