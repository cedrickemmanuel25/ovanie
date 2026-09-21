<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use App\Notifications\Delivery\DriverAssignedNotification;
use App\Notifications\Delivery\DriverOnTheWayNotification;
use App\Notifications\Delivery\OrderReadyForPickupNotification;
use App\Notifications\Delivery\ShipmentDeliveredNotification;
use App\Notifications\Delivery\ShipmentPickedUpNotification;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public const PROVIDER_OVANIE = 'ovanie';
    public const PROVIDER_SELLER = 'seller';
    public const PROVIDER_PICKUP = 'pickup';
    public const PROVIDER_PARTNER = 'partner';

    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_PREPARING = 'preparing';
    public const DELIVERY_READY_FOR_PICKUP = 'ready_for_pickup';
    public const DELIVERY_ASSIGNED = 'assigned';
    public const DELIVERY_PICKED_UP = 'picked_up';
    public const DELIVERY_IN_TRANSIT = 'in_transit';
    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_LATE = 'late';
    public const DELIVERY_FAILED_STATUS = 'failed';
    public const DELIVERY_RETURNED = 'returned';
    public const DELIVERY_CANCELLED = 'cancelled';
    public const DELIVERY_FAILED = 'delivery_failed';
    public const DELIVERY_NOT_REQUIRED = 'not_required';

    /**
     * Statut d'affichage vendeur, unique source de vérité utilisée à la fois
     * par l'espace web (VendorOrderController) et par l'app mobile
     * (VendorMobileController). Avant ce partage, les deux gardaient chacun
     * leur propre copie de cette logique, et elles avaient légèrement divergé
     * (listes de statuts "expédiée" différentes, vérification "annulée" tenant
     * compte ou non de delivery_status) : web et mobile pouvaient donc
     * afficher un statut différent pour la même commande.
     *
     * Accepte aussi bien des `OrderItem` Eloquent (espace web) que des lignes
     * `stdClass` issues d'une requête `DB::table('order_items')` brute (liste
     * mobile, qui évite de charger le modèle complet pour rester rapide) :
     * les deux exposent `vendor_status`/`delivery_status` de la même façon,
     * donc aucun type strict n'est imposé sur les éléments.
     *
     * @param  iterable<object{vendor_status: ?string, delivery_status: ?string}>  $items
     */
    public function resolveVendorDisplayStatus($items): string
    {
        $items = collect($items);

        if ($items->isEmpty()) {
            return 'pending';
        }

        if ($items->every(fn ($item) =>
            $item->vendor_status === 'cancelled'
            || $item->delivery_status === self::DELIVERY_CANCELLED
        )) {
            return 'cancelled';
        }

        if ($items->every(fn ($item) =>
            $item->delivery_status === self::DELIVERY_DELIVERED
        )) {
            return 'delivered';
        }

        if ($items->contains(fn ($item) => in_array($item->delivery_status, [
            self::DELIVERY_ASSIGNED,
            self::DELIVERY_PICKED_UP,
            self::DELIVERY_IN_TRANSIT,
            self::DELIVERY_LATE,
            self::DELIVERY_FAILED,
            self::DELIVERY_DELIVERED,
        ], true))) {
            return 'shipped';
        }

        if ($items->every(fn ($item) => ($item->vendor_status ?: 'pending') === 'ready')) {
            return 'ready';
        }

        if ($items->contains(fn ($item) => in_array($item->vendor_status, ['accepted', 'preparing'], true))) {
            return 'preparing';
        }

        return 'pending';
    }

    public function initializeOrderItem(OrderItem $item, string $provider, array $options = []): OrderItem
    {
        $provider = $this->normalizeProvider($provider);
        $status = match ($provider) {
            self::PROVIDER_OVANIE => self::DELIVERY_PENDING,
            self::PROVIDER_SELLER => self::DELIVERY_PREPARING,
            self::PROVIDER_PICKUP => self::DELIVERY_NOT_REQUIRED,
            self::PROVIDER_PARTNER => self::DELIVERY_PENDING,
            default => self::DELIVERY_PENDING,
        };

        $oldStatus = $item->delivery_status;

        $item->forceFill(array_filter([
            'delivery_provider' => $provider,
            'delivery_mode' => $provider,
            'delivery_status' => $status,
            'delivery_delay' => $options['delivery_delay'] ?? null,
            'delivery_price' => $options['delivery_price'] ?? 0,
            'delivery_service_id' => $options['delivery_service_id'] ?? null,
            'delivery_zone_id' => $options['delivery_zone_id'] ?? null,
            'vendor_delivery_status' => $this->legacyVendorDeliveryStatus($status),
            'vendor_delivery_updated_at' => now(),
            'reception_status' => 'waiting',
            'payout_status' => 'not_ready',
        ], fn ($value) => $value !== null))->save();

        $this->recordHistory($item->order, $item, 'delivery', $oldStatus, $status, [
            'actor_type' => 'system',
            'label' => 'Mode de livraison initialisé',
            'message' => $this->providerLabel($provider) . ' assigné au checkout.',
            'metadata' => $options,
        ]);

        return $item->refresh();
    }

    public function markVendorReadyForOvanie(OrderItem $item, ?User $actor = null, ?string $note = null): OrderItem
    {
        if ($item->delivery_provider !== self::PROVIDER_OVANIE) {
            return $item;
        }

        return $this->setDeliveryStatus(
            item: $item,
            status: self::DELIVERY_READY_FOR_PICKUP,
            actor: $actor,
            actorType: 'vendor',
            note: $note ?: 'Le vendeur a marqué le colis prêt pour enlèvement OVANIE.'
        );
    }

    public function setDeliveryStatus(OrderItem $item, string $status, ?User $actor = null, string $actorType = 'system', ?string $note = null, array $extra = []): OrderItem
    {
        $suppressNotifications = (bool) ($extra['suppress_notifications'] ?? false);
        unset($extra['suppress_notifications']);

        $oldStatus = $item->delivery_status;
        $this->assertDeliveryTransition($item, $status, $actorType, $extra);
        $persistedExtra = collect($extra)
            ->filter(fn ($value, string $column) => Schema::hasColumn('order_items', $column))
            ->all();

        // Un double clic, une actualisation ou un webhook rejoué ne doit jamais
        // recréer l'historique ni renvoyer une notification pour le même état.
        if ($oldStatus === $status) {
            $sameStatusData = array_filter($persistedExtra, fn ($value) => $value !== null);
            if ($note !== null) {
                $sameStatusData['vendor_delivery_note'] = $note;
            }
            if ($sameStatusData !== []) {
                $item->forceFill($sameStatusData)->save();
            }

            return $item->refresh();
        }

        $legacy = $this->legacyVendorDeliveryStatus($status);

        $data = [
            'delivery_status' => $status,
            'vendor_delivery_status' => $legacy,
            'vendor_delivery_note' => $note ?? $item->vendor_delivery_note,
            'vendor_delivery_updated_at' => now(),
        ];

        if ($status === self::DELIVERY_READY_FOR_PICKUP) {
            $data['seller_ready_for_pickup_at'] = $item->seller_ready_for_pickup_at ?: now();
            $data['vendor_status'] = 'ready';
            $data['vendor_prepared_at'] = $item->vendor_prepared_at ?: now();
        }

        if ($status === self::DELIVERY_ASSIGNED) {
            $data['logistics_assigned_at'] = $item->logistics_assigned_at ?: now();
        }

        if ($status === self::DELIVERY_PICKED_UP) {
            $data['picked_up_at'] = $item->picked_up_at ?: now();
            $data['vendor_status'] = 'shipped';
            $data['vendor_shipped_at'] = $item->vendor_shipped_at ?: now();
        }

        if ($status === self::DELIVERY_IN_TRANSIT) {
            $data['vendor_status'] = 'shipped';
            $data['vendor_shipped_at'] = $item->vendor_shipped_at ?: now();
        }

        if ($status === self::DELIVERY_DELIVERED) {
            $data['delivery_completed_at'] = $item->delivery_completed_at ?: now();
            $data['vendor_status'] = 'delivered';
            $data['vendor_delivered_at'] = $item->vendor_delivered_at ?: now();
            $data['reception_status'] = $item->reception_status ?: 'waiting';
            $data['payout_status'] = 'waiting_reception';
        }

        if ($status === self::DELIVERY_FAILED) {
            $data['delivery_failed_at'] = $item->delivery_failed_at ?: now();
            $data['delivery_failure_reason'] = $note;
        }

        $item->forceFill(array_merge($data, $persistedExtra))->save();

        $this->recordHistory($item->order, $item, 'delivery', $oldStatus, $status, [
            'actor_type' => $actorType,
            'user_id' => $actor?->id,
            'label' => $this->deliveryStatusLabel($status),
            'message' => $note,
            'metadata' => $extra,
        ]);

        $this->syncShipmentFromItem($item, $status, $actor, $note, $extra);

        if ($status === self::DELIVERY_READY_FOR_PICKUP) {
            $item->order?->forceFill([
                'seller_ready_for_pickup_at' => $item->order?->seller_ready_for_pickup_at ?: now(),
            ])->save();

            if (! $suppressNotifications) {
                $this->notifyAdmins('Colis pret pour collecte', 'Une expedition OVANIE attend une assignation.', [
                    'category' => 'deliveries',
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'url' => route('logistics.shipments'),
                ]);

                app(DeliveryNotificationService::class)->notifyLogistics(new OrderReadyForPickupNotification($item->refresh()));
            }
        }

        if ($status === self::DELIVERY_ASSIGNED) {
            $item->order?->forceFill([
                'driver_assigned_at' => $item->order?->driver_assigned_at ?: now(),
            ])->save();

            if (! $suppressNotifications) {
                if ($this->isVendorVisible($item)) {
                    $this->notify($item->product?->shop?->user, 'Livreur assigne', 'OVANIE a assigne un livreur a votre colis.', [
                        'category' => 'deliveries',
                        'order_id' => $item->order_id,
                        'order_item_id' => $item->id,
                        'url' => route('vendor.orders.show', $item->order_id),
                    ]);
                }

                app(DeliveryNotificationService::class)->notifyDriverAssigned($item->refresh(), new DriverAssignedNotification($item->refresh()));
            }
        }

        if ($status === self::DELIVERY_PICKED_UP) {
            $item->order?->forceFill([
                'picked_up_at' => $item->order?->picked_up_at ?: now(),
            ])->save();

            if (! $suppressNotifications) {
                app(DeliveryNotificationService::class)->notifyGroup(
                    $item->refresh(),
                    'picked_up',
                    'Colis récupéré',
                    'Les articles de cette livraison ont été récupérés et sont en cours d’acheminement.'
                );
            }
        }

        if ($status === self::DELIVERY_IN_TRANSIT) {
            $item->order?->forceFill([
                'in_transit_at' => $item->order?->in_transit_at ?: now(),
            ])->save();

            if (! $suppressNotifications) {
                app(DeliveryNotificationService::class)->notifyGroup(
                    $item->refresh(),
                    'in_transit',
                    'Livreur en route',
                    'Votre livraison est en route. Consultez votre espace client pour suivre son avancement.',
                    'Votre livraison est en route. Consultez votre espace client OVANIE.'
                );
            }
        }

        if ($status === self::DELIVERY_DELIVERED) {
            $item->order?->forceFill([
                'delivered_at' => $item->order?->delivered_at ?: now(),
            ])->save();

            if (! $suppressNotifications) {
                app(DeliveryNotificationService::class)->notifyGroup(
                    $item->refresh(),
                    'delivered',
                    'Livraison effectuée',
                    'Cette livraison a été remise. Vérifiez les articles reçus puis confirmez la réception depuis votre espace client.'
                );
            }
        }

        if ($item->order) {
            app(OrderDeliveryStatusAggregator::class)->sync($item->order);
            $item->order->refreshGlobalStatusFromItems();
        }

        return $item->refresh();
    }

    public function confirmClientReception(OrderItem $item, ?User $actor = null, ?string $note = null): OrderItem
    {
        $oldStatus = $item->reception_status;
        $order = $item->order;
        $payoutStatus = $this->isItemPayoutEligibleAfterReception($item) ? 'ready' : 'waiting_payment';

        $item->forceFill(array_filter([
            'reception_status' => 'confirmed',
            'reception_confirmed_at' => now(),
            'payout_status' => $payoutStatus,
            'payout_ready_at' => $payoutStatus === 'ready' ? now() : null,
        ], fn ($value) => $value !== null))->save();

        $this->recordHistory($order, $item, 'reception', $oldStatus, 'confirmed', [
            'actor_type' => 'client',
            'user_id' => $actor?->id,
            'label' => 'Réception confirmée',
            'message' => $note ?: 'Le client a confirmé la réception de cette ligne de commande.',
        ]);

        $this->markPayoutsReadyWhenEligible($order);
        $order?->refreshGlobalStatusFromItems();

        if ($order) {
            app(LoyaltyService::class)->awardForCompletedOrder($order->fresh(['items']));
        }

        if ($this->isVendorVisible($item)) {
            $this->notify($item->product?->shop?->user, 'Reception client confirmee', 'La ligne de commande peut etre analysee pour reversement.', [
                'category' => 'payouts',
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'url' => route('vendor.payouts.index'),
            ]);
        }

        return $item->refresh();
    }

    public function assignDriver(OrderItem $item, array $data, ?User $actor = null): DeliveryAssignment
    {
        $this->assertDeliveryTransition($item, self::DELIVERY_ASSIGNED, 'logistics');

        $assignment = DeliveryAssignment::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'driver_id' => $data['driver_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'assigned_by' => $actor?->id,
            'status' => 'assigned',
            'pickup_address' => $data['pickup_address'] ?? $this->pickupAddress($item),
            'delivery_address' => $data['delivery_address'] ?? $this->deliveryAddress($item),
            'pickup_scheduled_at' => $data['pickup_scheduled_at'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);

        $this->setDeliveryStatus($item, self::DELIVERY_ASSIGNED, $actor, 'logistics', 'Livraison assignée à un livreur OVANIE.', [
            'suppress_notifications' => (bool) ($data['suppress_notifications'] ?? false),
        ]);

        return $assignment;
    }

    public function createDeliveryProof(OrderItem $item, array $data, ?User $actor = null): DeliveryProof
    {
        $hasReceiver = filled($data['receiver_name'] ?? null) && filled($data['receiver_phone'] ?? null);
        $hasStrongProof = filled($data['proof_photo'] ?? null)
            || filled($data['signature_path'] ?? null)
            || filled($data['otp_code'] ?? null);

        if (! $hasReceiver || ! $hasStrongProof) {
            throw new \InvalidArgumentException('Veuillez ajouter une preuve de livraison valide : photo, signature ou code OTP.');
        }

        $actorType = $item->is_ovanie_delivery ? 'logistics' : 'vendor';
        $this->assertDeliveryTransition($item, self::DELIVERY_DELIVERED, $actorType, [
            'delivery_proof_id' => 'pending',
        ]);

        $proof = DeliveryProof::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'delivery_provider' => $item->delivery_provider ?: self::PROVIDER_OVANIE,
            'driver_id' => $data['driver_id'] ?? null,
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_phone' => $data['receiver_phone'] ?? null,
            'proof_photo' => $data['proof_photo'] ?? null,
            'signature_path' => $data['signature_path'] ?? null,
            'delivery_note' => $data['delivery_note'] ?? null,
            'delivered_at' => $data['delivered_at'] ?? now(),
            'meta' => $data['meta'] ?? [],
        ]);

        $this->setDeliveryStatus($item, self::DELIVERY_DELIVERED, $actor, $actorType, $data['delivery_note'] ?? 'Preuve de livraison enregistrée.', [
            // Utilisé pour autoriser la transition et tracer la preuve ; ce champ
            // est filtré avant la mise à jour SQL de order_items.
            'delivery_proof_id' => $proof->id,
            'suppress_notifications' => (bool) ($data['suppress_notifications'] ?? false),
        ]);

        return $proof;
    }

    public function recordHistory(?Order $order, ?OrderItem $item, string $statusType, ?string $oldStatus, ?string $newStatus, array $options = []): ?OrderStatusHistory
    {
        if (! Schema::hasTable('order_status_histories')) {
            return null;
        }

        return OrderStatusHistory::create([
            'order_id' => $order?->id ?? $item?->order_id,
            'order_item_id' => $item?->id,
            'user_id' => $options['user_id'] ?? null,
            'actor_type' => $options['actor_type'] ?? 'system',
            'status_type' => $statusType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'label' => $options['label'] ?? null,
            'message' => $options['message'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    public function notify(?User $user, string $title, string $message, array $data = []): void
    {
        if (! $user) {
            return;
        }

        $category = (string) ($data['category'] ?? 'orders');
        $dispatcher = app(OvanieNotificationDispatcher::class);
        $canonicalCategory = $dispatcher->canonicalCategory($category);

        // Les notifications de livraison client sont envoyées par
        // DeliveryNotificationService, qui applique déjà les préférences par canal.
        if ($user->role === 'client' && $canonicalCategory === 'deliveries') {
            return;
        }

        $channels = $user->role === 'client'
            ? ['in_app', 'email', 'sms']
            : ['in_app'];

        $dispatcher->send(
            $user,
            $category,
            $title,
            $message,
            $data,
            $channels
        );
    }

    public function notifyAdmins(string $title, string $message, array $data = []): void
    {
        User::query()
            ->where(function ($query) {
                $query->where('is_admin', true)
                    ->orWhere('role', 'admin');
            })
            ->get()
            ->each(fn (User $admin) => $this->notify($admin, $title, $message, $data + ['category' => 'admin']));
    }

    public function isVendorVisible(OrderItem $item): bool
    {
        return $item->vendor_visible_at !== null;
    }

    public function markPayoutsReadyWhenEligible(?Order $order): void
    {
        if (! $order || ! Schema::hasTable('vendor_payouts')) {
            return;
        }

        $order->loadMissing('items');

        // Le statut technique des lignes reste utile pour les écrans vendeur,
        // mais la date réelle du reversement est désormais calculée uniquement
        // par VendorPayoutScheduleService (72 h ou créneau hebdomadaire).
        foreach ($order->items as $item) {
            $eligible = $this->isItemPayoutEligible($item);

            $item->forceFill([
                'payout_status' => $eligible ? 'ready' : 'blocked',
                'payout_ready_at' => $eligible
                    ? ($item->payout_ready_at ?: now())
                    : null,
            ])->saveQuietly();
        }

        app(VendorPayoutScheduleService::class)->syncOrder($order->fresh());
    }

    public function isItemPayoutEligible(OrderItem $item): bool
    {
        return $item->delivery_status === self::DELIVERY_DELIVERED
            && $item->reception_status === 'confirmed'
            && $this->isOrderPaymentEligible($item->order)
            && ! $this->hasActiveReturn($item);
    }

    private function isItemPayoutEligibleAfterReception(OrderItem $item): bool
    {
        return $item->delivery_status === self::DELIVERY_DELIVERED
            && $this->isOrderPaymentEligible($item->order)
            && ! $this->hasActiveReturn($item);
    }

    private function isOrderPaymentEligible(?Order $order): bool
    {
        if (! $order) {
            return false;
        }

        return in_array($order->payment_status, ['paid', 'escrow_held'], true);
    }

    private function hasActiveReturn(OrderItem $item): bool
    {
        if (! Schema::hasTable('returns')) {
            return false;
        }

        return ReturnModel::where('order_item_id', $item->id)
            ->whereNotIn('status', ['rejected', 'closed', 'refunded', 'resolved', 'cancelled'])
            ->exists();
    }

    public function providerLabel(?string $provider): string
    {
        return match ($provider) {
            self::PROVIDER_OVANIE => 'OVANIE Logistics',
            self::PROVIDER_SELLER => 'Livraison prise en charge',
            self::PROVIDER_PICKUP => 'Retrait géré par OVANIE',
            self::PROVIDER_PARTNER => 'OVANIE Logistics',
            default => 'Mode de livraison à définir',
        };
    }

    public function deliveryStatusLabel(?string $status): string
    {
        return match ($status) {
            self::DELIVERY_PENDING => 'En attente',
            self::DELIVERY_PREPARING => 'Préparation de la commande',
            self::DELIVERY_READY_FOR_PICKUP => 'Prêt pour enlèvement OVANIE',
            self::DELIVERY_ASSIGNED => 'Assigné à un livreur',
            self::DELIVERY_PICKED_UP => 'Récupéré par OVANIE',
            self::DELIVERY_IN_TRANSIT => 'En livraison',
            self::DELIVERY_DELIVERED => 'Livré',
            self::DELIVERY_FAILED => 'Incident livraison',
            self::DELIVERY_NOT_REQUIRED => 'Retrait / pas de livraison',
            default => 'Statut non défini',
        };
    }

    private function assertDeliveryTransition(OrderItem $item, string $status, string $actorType, array $extra = []): void
    {
        $current = $item->delivery_status;

        if ($current === $status) {
            return;
        }

        if (in_array($actorType, ['admin', 'system'], true)) {
            return;
        }

        $provider = $item->delivery_provider;
        $allowed = false;

        if (in_array($provider, [self::PROVIDER_OVANIE, self::PROVIDER_PARTNER], true)) {
            if ($actorType === 'vendor') {
                $allowed = in_array($current, [self::DELIVERY_PENDING, self::DELIVERY_PREPARING], true)
                    && $status === self::DELIVERY_READY_FOR_PICKUP;
            }

            if ($actorType === 'logistics') {
                $allowed = match ($status) {
                    self::DELIVERY_ASSIGNED => $current === self::DELIVERY_READY_FOR_PICKUP,
                    self::DELIVERY_PICKED_UP => $current === self::DELIVERY_ASSIGNED,
                    self::DELIVERY_IN_TRANSIT => $current === self::DELIVERY_PICKED_UP,
                    self::DELIVERY_DELIVERED => $current === self::DELIVERY_IN_TRANSIT
                        && filled($extra['delivery_proof_id'] ?? null),
                    self::DELIVERY_FAILED => in_array($current, [
                        self::DELIVERY_READY_FOR_PICKUP,
                        self::DELIVERY_ASSIGNED,
                        self::DELIVERY_PICKED_UP,
                        self::DELIVERY_IN_TRANSIT,
                    ], true),
                    default => false,
                };
            }
        } elseif ($provider === self::PROVIDER_SELLER && in_array($actorType, ['vendor', 'seller_driver'], true)) {
            $allowed = match ($status) {
                self::DELIVERY_IN_TRANSIT => in_array($current, [self::DELIVERY_PENDING, self::DELIVERY_PREPARING], true),
                self::DELIVERY_DELIVERED => $current === self::DELIVERY_IN_TRANSIT
                    && (filled($extra['delivery_otp_verified_at'] ?? null) || filled($extra['delivery_proof_id'] ?? null)),
                self::DELIVERY_FAILED => in_array($current, [self::DELIVERY_PREPARING, self::DELIVERY_IN_TRANSIT], true),
                default => false,
            };
        }

        if (! $allowed) {
            throw ValidationException::withMessages([
                'delivery_status' => sprintf(
                    'Transition de livraison non autorisée : %s → %s.',
                    $this->deliveryStatusLabel($current),
                    $this->deliveryStatusLabel($status)
                ),
            ]);
        }
    }

    private function syncShipmentFromItem(OrderItem $item, string $status, ?User $actor = null, ?string $note = null, array $meta = []): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        $shipment = null;

        if ($item->shipment_id) {
            $shipment = Shipment::find($item->shipment_id);
        }

        if (! $shipment) {
            $shipment = Shipment::where('order_item_id', $item->id)->latest()->first();
        }

        if (! $shipment) {
            if ($status !== self::DELIVERY_READY_FOR_PICKUP || $item->delivery_provider !== self::PROVIDER_OVANIE) {
                return;
            }

            $destinationCoordinates = app(\App\Services\Geo\DeliveryCoordinateService::class)
                ->forOrder($item->order);

            $shipment = Shipment::create([
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'shop_id' => $item->shop_id,
                'delivery_service_id' => $item->delivery_service_id,
                'provider_type' => self::PROVIDER_OVANIE,
                'tracking_number' => 'TRK-' . strtoupper(Str::random(10)),
                'status' => $status,
                'estimated_delivery_at' => now()->addDay(),
                'final_price' => $item->delivery_price ?? 0,
                'pickup_address' => $this->pickupAddress($item),
                'delivery_address' => $this->deliveryAddress($item),
                'pickup_latitude' => $item->product?->shop?->latitude,
                'pickup_longitude' => $item->product?->shop?->longitude,
                'delivery_latitude' => $destinationCoordinates['latitude'],
                'delivery_longitude' => $destinationCoordinates['longitude'],
                'meta' => ['source' => 'workflow_ready_for_pickup'],
            ]);

            $item->forceFill(['shipment_id' => $shipment->id])->save();
        }

        $shipmentStatus = $this->aggregateShipmentStatus($shipment, $status);
        $oldShipmentStatus = $shipment->status;

        $payload = [
            'status' => $shipmentStatus,
            'updated_at' => now(),
        ];

        if ($shipmentStatus === self::DELIVERY_DELIVERED) {
            $payload['delivered_at'] = now();
        }

        $shipment->forceFill($payload)->save();

        if ($oldShipmentStatus !== $shipmentStatus && Schema::hasTable('shipment_status_histories')) {
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'status' => $shipmentStatus,
                'label' => $this->deliveryStatusLabel($shipmentStatus),
                'note' => $note,
                'created_by' => $actor?->id,
                'meta' => array_merge($meta, [
                    'source_order_item_id' => $item->id,
                    'requested_item_status' => $status,
                ]),
            ]);
        }
    }

    private function aggregateShipmentStatus(Shipment $shipment, string $fallbackStatus): string
    {
        $statuses = OrderItem::query()
            ->where('shipment_id', $shipment->id)
            ->pluck('delivery_status')
            ->filter()
            ->values();

        if ($statuses->isEmpty()) {
            return $fallbackStatus;
        }

        if ($statuses->count() === 1) {
            return (string) $statuses->first();
        }

        if ($statuses->contains(self::DELIVERY_FAILED)) {
            return self::DELIVERY_FAILED;
        }

        if ($statuses->every(fn ($value) => $value === self::DELIVERY_DELIVERED)) {
            return self::DELIVERY_DELIVERED;
        }

        $rank = [
            self::DELIVERY_PENDING => 0,
            self::DELIVERY_PREPARING => 0,
            self::DELIVERY_READY_FOR_PICKUP => 1,
            self::DELIVERY_ASSIGNED => 2,
            self::DELIVERY_PICKED_UP => 3,
            self::DELIVERY_IN_TRANSIT => 4,
            self::DELIVERY_DELIVERED => 5,
        ];

        $minimumRank = $statuses->map(fn ($value) => $rank[$value] ?? 0)->min();
        $maximumRank = $statuses->map(fn ($value) => $rank[$value] ?? 0)->max();

        if ($minimumRank >= 4) {
            return self::DELIVERY_IN_TRANSIT;
        }

        if ($minimumRank >= 3) {
            return self::DELIVERY_PICKED_UP;
        }

        if ($minimumRank >= 2) {
            return self::DELIVERY_ASSIGNED;
        }

        if ($maximumRank >= 1) {
            return self::DELIVERY_READY_FOR_PICKUP;
        }

        return self::DELIVERY_PENDING;
    }

    private function normalizeProvider(?string $provider): string
    {
        return in_array($provider, [self::PROVIDER_OVANIE, self::PROVIDER_SELLER, self::PROVIDER_PICKUP, self::PROVIDER_PARTNER], true)
            ? $provider
            : self::PROVIDER_OVANIE;
    }

    private function legacyVendorDeliveryStatus(string $status): string
    {
        return match ($status) {
            self::DELIVERY_IN_TRANSIT, self::DELIVERY_PICKED_UP => 'in_delivery',
            self::DELIVERY_DELIVERED => 'delivered',
            default => 'pending',
        };
    }

    private function pickupAddress(OrderItem $item): ?string
    {
        $shop = $item->product?->shop ?: $item->shop;
        return collect([$shop?->city, $shop?->commune, $shop?->address, $shop?->landmark])->filter()->join(', ') ?: null;
    }

    private function deliveryAddress(OrderItem $item): ?string
    {
        $order = $item->order;
        return collect([$order?->delivery_city, $order?->delivery_commune, $order?->delivery_quartier, $order?->address])->filter()->join(', ') ?: null;
    }
}
