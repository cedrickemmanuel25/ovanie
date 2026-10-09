<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\DriverPayout;
use App\Services\DriverPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Écran « Mes gains » de l'application livreur : gains versés après chaque
 * livraison, espèces à déposer et compte Mobile Money de réception.
 */
class DriverEarningsController extends Controller
{
    public function index(Request $request, DriverPayoutService $payouts): JsonResponse
    {
        $driver = $this->driver($request);
        $rows = DriverPayout::query()->where('driver_id', $driver->id)->latest('id')->limit(100)->get();
        $monthStart = now()->startOfMonth();

        return response()->json([
            'summary' => [
                'today' => (float) $rows->where('created_at', '>=', now()->startOfDay())->sum('net_amount'),
                'month' => (float) $rows->where('created_at', '>=', $monthStart)->sum('net_amount'),
                'paid_total' => (float) $rows->where('status', DriverPayout::STATUS_PAID)->sum('transfer_amount'),
                'awaiting_transfer' => (float) $rows->whereIn('status', [
                    DriverPayout::STATUS_PENDING, DriverPayout::STATUS_PROCESSING,
                    DriverPayout::STATUS_FAILED, DriverPayout::STATUS_BLOCKED,
                ])->sum('transfer_amount'),
                'cash_due' => $payouts->cashDue($driver),
                'cash_limit' => (float) config('driver_payouts.cash_limit', 50000),
                'cash_blocked' => $payouts->cashLimitReached($driver),
                'missions' => $rows->count(),
            ],
            'account' => $payouts->account($driver),
            'payouts' => $rows->map(fn (DriverPayout $payout) => [
                'reference' => $payout->reference,
                'mission_number' => $payout->mission_number,
                'gross_amount' => $payout->gross_amount,
                'commission_percent' => $payout->commission_percent,
                'net_amount' => $payout->net_amount,
                'cash_collected' => $payout->cash_collected,
                'cash_due' => $payout->cash_settled_at ? 0.0 : $payout->cash_due,
                'transfer_amount' => $payout->transfer_amount,
                'status' => $payout->status,
                'status_label' => $payout->statusLabel(),
                'created_at' => $payout->created_at?->toIso8601String(),
                'paid_at' => $payout->paid_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function updateAccount(Request $request, DriverPayoutService $payouts): JsonResponse
    {
        $driver = $this->driver($request);
        $validated = $request->validate([
            'operator' => ['required', 'in:wave,orange,mtn,moov'],
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'operator.required' => 'Choisissez votre opérateur Mobile Money.',
            'phone.required' => 'Renseignez votre numéro Mobile Money.',
        ]);

        try {
            $payouts->saveAccount($driver, $validated['operator'], $validated['phone']);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        // Les gains en attente partent dès que le compte est renseigné.
        $payouts->retryForDriver($driver->fresh());

        return response()->json([
            'message' => 'Compte Mobile Money enregistré. Vos gains y seront versés après chaque livraison.',
            'account' => $payouts->account($driver->fresh()),
        ]);
    }

    private function driver(Request $request): DeliveryDriver
    {
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        return $driver;
    }
}
