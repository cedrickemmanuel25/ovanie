<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DriverPayout;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paie le livreur OVANIE juste après chaque livraison confirmée par le code du
 * client. Paiement à la livraison : le livreur garde son net sur les espèces
 * encaissées et doit le reste à OVANIE (soldé par la Logistique).
 */
class DriverPayoutService
{
    public function __construct(
        private readonly ClientDeliveryGroupService $groups,
        private readonly PayDunyaDisbursementClient $paydunya,
        private readonly FirebasePushService $driverPush,
    ) {
    }

    /**
     * Groupes de livraison payés à la livraison et non réglés pour ces lignes :
     * [order_id, key, total], une seule fois par groupe.
     *
     * @return array<int, array{order_id: int, key: string, total: float}>
     */
    public function cashGroups(Collection $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $order = $item->order;
            if (! $order instanceof Order) {
                continue;
            }
            $group = $this->groups->findForItem($order, (int) $item->id);
            if (! $group || empty($group['is_cash_on_delivery']) || ! empty($group['is_paid'])) {
                continue;
            }
            $groups[$order->id . ':' . $group['key']] = [
                'order_id' => (int) $order->id,
                'key' => (string) $group['key'],
                'total' => round((float) ($group['total'] ?? 0), 2),
            ];
        }

        return array_values($groups);
    }

    /** Montant exact à encaisser auprès du client (paiement à la livraison). */
    public function cashToCollect(Collection $items): float
    {
        return round((float) collect($this->cashGroups($items))->sum('total'), 2);
    }

    /** Crée (une seule fois) le gain de la mission livrée puis lance le virement. */
    public function recordDeliveredMission(DeliveryDriver $driver, string $missionNumber, Collection $assignments, float $cashCollected, array $cashGroups = []): DriverPayout
    {
        $payout = DB::transaction(function () use ($driver, $missionNumber, $assignments, $cashCollected, $cashGroups) {
            $existing = DriverPayout::query()
                ->where('driver_id', $driver->id)
                ->where('mission_number', $missionNumber)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $latest = $assignments->unique('order_item_id');
            $net = round((float) $latest->sum(fn (DeliveryAssignment $a) => (float) ($a->driver_net_amount ?? 0)), 2);
            $gross = round((float) $latest->sum(fn (DeliveryAssignment $a) => (float) ($a->price_amount ?? 0)), 2);
            $kept = min($net, $cashCollected);
            $account = $this->account($driver);

            return DriverPayout::create([
                'driver_id' => $driver->id,
                'mission_number' => $missionNumber,
                'order_id' => $latest->first()?->order_id,
                'reference' => 'LIV-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'gross_amount' => $gross,
                'commission_percent' => (float) ($latest->first()?->driver_commission_percent ?? 0),
                'net_amount' => $net,
                'cash_collected' => $cashCollected,
                'cash_due' => round(max(0, $cashCollected - $net), 2),
                'transfer_amount' => round(max(0, $net - $kept), 2),
                'status' => $net - $kept > 0 ? DriverPayout::STATUS_PENDING : DriverPayout::STATUS_KEPT_IN_CASH,
                'operator' => $account['operator'],
                'phone' => $account['phone'],
                'paid_at' => $net - $kept > 0 ? null : now(),
                'meta' => $cashGroups ? ['cash_groups' => $cashGroups] : null,
            ]);
        });

        return $payout->wasRecentlyCreated ? $this->execute($payout) : $payout;
    }

    /** Lance ou reprend le virement Mobile Money d'un gain. */
    public function execute(DriverPayout $payout): DriverPayout
    {
        $payout->refresh();
        if (! in_array($payout->status, [DriverPayout::STATUS_PENDING, DriverPayout::STATUS_FAILED, DriverPayout::STATUS_BLOCKED, DriverPayout::STATUS_PROCESSING], true)) {
            return $payout;
        }

        $mode = (string) config('driver_payouts.execution_mode', 'simulation');
        if ($mode === 'manual') {
            return $payout;
        }

        $account = $this->account($payout->driver);
        if (! $account['phone'] || ! $this->paydunya->supportsOperator($account['operator'])) {
            $payout->forceFill(['status' => DriverPayout::STATUS_BLOCKED, 'failure_reason' => 'Compte Mobile Money à renseigner.'])->save();

            return $payout;
        }
        $payout->forceFill(['operator' => $account['operator'], 'phone' => $account['phone']])->save();

        if ($mode === 'simulation') {
            return $this->markPaid($payout, ['simulation' => true]);
        }

        try {
            $meta = $payout->meta ?? [];
            $token = (string) data_get($meta, 'paydunya.token', '');
            if ($token === '') {
                $token = $this->paydunya->createInvoice($account['phone'], $payout->transfer_amount, $account['operator']);
                $meta['paydunya'] = ['token' => $token, 'created_at' => now()->toIso8601String()];
                $payout->forceFill(['status' => DriverPayout::STATUS_PROCESSING, 'meta' => $meta])->save();
                $status = $this->paydunya->submit($token, $payout->reference);
            } else {
                $status = $this->paydunya->check($token);
            }
        } catch (\Throwable $e) {
            report($e);
            // Un jeton déjà créé est réconcilié au prochain passage : jamais de double virement.
            $payout->forceFill([
                'status' => filled(data_get($payout->meta, 'paydunya.token')) ? DriverPayout::STATUS_PROCESSING : DriverPayout::STATUS_FAILED,
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            return $payout;
        }

        return match ($status) {
            'success' => $this->markPaid($payout),
            'failed' => tap($payout)->forceFill(['status' => DriverPayout::STATUS_FAILED, 'failure_reason' => 'Virement refusé par l’opérateur.', 'meta' => array_merge($payout->meta ?? [], ['paydunya' => []])])->save(),
            default => tap($payout)->forceFill(['status' => DriverPayout::STATUS_PROCESSING])->save(),
        };
    }

    /** Relance tous les virements en attente d'un livreur (ex. compte ajouté). */
    public function retryForDriver(DeliveryDriver $driver): void
    {
        DriverPayout::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [DriverPayout::STATUS_BLOCKED, DriverPayout::STATUS_FAILED, DriverPayout::STATUS_PENDING])
            ->get()
            ->each(fn (DriverPayout $payout) => $this->execute($payout));
    }

    /** Espèces encore dues à OVANIE par le livreur. */
    public function cashDue(DeliveryDriver $driver): float
    {
        return round((float) DriverPayout::query()
            ->where('driver_id', $driver->id)
            ->whereNull('cash_settled_at')
            ->sum('cash_due'), 2);
    }

    public function cashLimitReached(DeliveryDriver $driver): bool
    {
        return $this->cashDue($driver) > (float) config('driver_payouts.cash_limit', 50000);
    }

    /**
     * La Logistique a reçu les espèces du livreur : la dette est soldée et le
     * paiement des commandes concernées est enregistré (le vendeur peut être reversé).
     */
    public function settleCash(DeliveryDriver $driver, ?int $staffId = null): float
    {
        $amount = $this->cashDue($driver);
        $payouts = DriverPayout::query()
            ->where('driver_id', $driver->id)
            ->whereNull('cash_settled_at')
            ->where('cash_collected', '>', 0)
            ->get();

        foreach ($payouts as $payout) {
            foreach ((array) data_get($payout->meta, 'cash_groups', []) as $group) {
                $order = Order::find($group['order_id'] ?? 0);
                if (! $order) {
                    continue;
                }
                try {
                    app(DeliveryGroupCollectionService::class)->collect($order, (string) $group['key'], [
                        'actor_type' => 'logistics',
                        'user_id' => $staffId,
                        'operator' => 'ovanie_driver_cash',
                        'driver_id' => $driver->id,
                        'mission_number' => $payout->mission_number,
                    ]);
                    $order->items()->pluck('shop_id')->filter()->unique()->each(
                        fn ($shopId) => app(VendorOrderReleaseService::class)->releaseForShop($order->fresh(), (int) $shopId)
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            $payout->forceFill(['cash_settled_at' => now()])->save();
        }

        return $amount;
    }

    /** @return array{operator: ?string, phone: ?string} */
    public function account(?DeliveryDriver $driver): array
    {
        $payout = (array) data_get($driver?->profile, 'payout', []);

        return [
            'operator' => filled($payout['operator'] ?? null) ? (string) $payout['operator'] : null,
            'phone' => filled($payout['phone'] ?? null) ? (string) $payout['phone'] : null,
        ];
    }

    public function saveAccount(DeliveryDriver $driver, string $operator, string $phone): void
    {
        $profile = is_array($driver->profile) ? $driver->profile : [];
        $profile['payout'] = ['operator' => $operator, 'phone' => $this->paydunya->normalizePhone($phone)];
        $driver->forceFill(['profile' => $profile])->save();
    }

    private function markPaid(DriverPayout $payout, array $meta = []): DriverPayout
    {
        $payout->forceFill([
            'status' => DriverPayout::STATUS_PAID,
            'paid_at' => now(),
            'failure_reason' => null,
            'meta' => array_merge($payout->meta ?? [], $meta),
        ])->save();

        $amount = number_format($payout->transfer_amount, 0, ',', ' ');
        try {
            $this->driverPush->sendToDriver(
                $payout->driver,
                'Gain versé',
                "{$amount} F ont été versés sur votre Mobile Money pour la mission {$payout->mission_number}.",
                ['category' => 'driver_payout', 'mission_number' => $payout->mission_number]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $payout;
    }
}
