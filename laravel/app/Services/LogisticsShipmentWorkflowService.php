<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\FirebasePushService;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogisticsShipmentWorkflowService
{
    private const VEHICLE_RANK = [
        'moto' => 1,
        'tricycle' => 2,
        'pickup' => 3,
        'camion_3t' => 4,
        'camion_10t' => 5,
    ];

    private const TRANSITIONS = [
        OrderWorkflowService::DELIVERY_ASSIGNED => [
            OrderWorkflowService::DELIVERY_PICKED_UP,
        ],
        OrderWorkflowService::DELIVERY_PICKED_UP => [
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
        ],
    ];

    public function __construct(
        private readonly OrderWorkflowService $workflow,
        private readonly OvanieNotificationDispatcher $notifications,
        private readonly FirebasePushService $driverPush,
        private readonly OvanieShipmentConsolidationService $consolidation,
    ) {
    }

    public function eligibleDrivers(OrderItem $item)
    {
        return $this->eligibleDriversForVehicleCode($this->requiredVehicleCode($item));
    }

    private function eligibleDriversForVehicleCode(string $requiredCode)
    {
        return LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->orderBy('name')
            ->get()
            ->filter(fn (DeliveryDriver $driver) => $this->isDriverAvailable($driver) && $this->driverCanHandle($driver, $requiredCode))
            ->values();
    }

    /**
     * Marque le colis prêt pour enlèvement OVANIE puis, si ce passage vient
     * réellement de se produire (pas un rejeu / double clic), diffuse la
     * course à tous les livreurs éligibles — voir broadcastToEligibleDrivers().
     * Point d'entrée unique appelé par VendorOrderController::markShipped()
     * (web + délégation mobile) et VendorMobileController::updateOrderPreparation().
     */
    public function markReadyAndBroadcast(OrderItem $item, ?User $actor = null, ?string $note = null): OrderItem
    {
        $wasReady = $item->delivery_status === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP;
        $updated = $this->workflow->markVendorReadyForOvanie($item, $actor, $note);

        if (! $wasReady && $updated->delivery_status === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP) {
            // La mission a normalement déjà été proposée dès la libération de
            // la commande au vendeur (correction 01). On garde cet appel comme
            // filet de sécurité si aucun livreur n'avait été disponible à ce
            // moment-là : broadcastToEligibleDrivers() est idempotente.
            $this->broadcastToEligibleDrivers($updated);

            // Important : "Prête" ne déclenche pas le départ du livreur.
            // On informe seulement le livreur qui a déjà réservé la mission.
            // La collecte reste bloquée dans DriverMissionService tant que
            // tous les points vendeurs de la mission ne sont pas prêts.
            $itemId = (int) $updated->id;
            DB::afterCommit(function () use ($itemId): void {
                $this->notifyReservedDriverAboutVendorReadiness($itemId);
            });
        }

        return $updated;
    }

    /**
     * Crée une offre (DeliveryAssignment status="offered") pour chaque livreur
     * éligible dès que la commande est libérée au vendeur, sans attendre que
     * le colis soit prêt. Le vendeur prépare en parallèle. Le premier livreur
     * qui accepte réserve la mission ; la collecte reste impossible tant que
     * tous les points vendeur ne sont pas prêts.
     *
     * La méthode reste aussi utilisée au passage "Prête" comme filet de
     * sécurité : si une mission est déjà réservée, aucune nouvelle offre n'est
     * créée ; si aucun livreur n'avait été trouvé auparavant, une nouvelle
     * tentative de diffusion peut avoir lieu.
     */
    public function broadcastToEligibleDrivers(OrderItem $item): int
    {
        $drivers = DB::transaction(function () use ($item) {
            $seedItem = OrderItem::query()
                ->with(['shipment', 'product.shop'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOvanie($seedItem);

            $group = $this->consolidation->groupForItem($seedItem);
            $groupItemIds = collect($group['items'] ?? [])
                ->pluck('id')
                ->filter()
                ->unique()
                ->values();

            if ($groupItemIds->isEmpty()) {
                $groupItemIds = collect([$seedItem->id]);
            }

            // On verrouille toute la mission consolidée afin de créer une
            // offre complète et indivisible. Cela évite qu'un livreur reçoive
            // seulement une partie des produits d'une même mission.
            $lockedItems = OrderItem::query()
                ->with(['shipment', 'product.shop'])
                ->whereIn('id', $groupItemIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lockedItems as $lockedItem) {
                $this->assertOvanie($lockedItem);
                if (! in_array($lockedItem->delivery_status, [
                    OrderWorkflowService::DELIVERY_PENDING,
                    OrderWorkflowService::DELIVERY_PREPARING,
                    OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                ], true)) {
                    return collect();
                }
            }

            $missionNumber = (string) ($group['mission_number'] ?? sprintf('OVL-%05d-01', (int) $seedItem->order_id));

            // Idempotence mission complète : une mission déjà proposée,
            // réservée ou démarrée ne doit pas être rediffusée.
            $hasLiveOrReservedAssignment = DeliveryAssignment::query()
                ->whereIn('order_item_id', $groupItemIds->all())
                ->whereIn('status', [
                    'offered', 'accepted', 'assigned', 'collecting',
                    'picked_up', 'in_transit', 'arrived', 'delivered',
                ])
                ->exists();

            if ($hasLiveOrReservedAssignment) {
                return collect();
            }

            $requiredCode = (string) ($group['vehicle_code'] ?? $this->requiredVehicleCode($seedItem));
            $eligible = $this->eligibleDriversForVehicleCode($requiredCode);

            if ($eligible->isEmpty()) {
                Log::info('OVANIE Logistics : aucun livreur éligible pour diffuser la mission.', [
                    'mission_number' => $missionNumber,
                    'order_id' => $seedItem->order_id,
                    'required_vehicle_code' => $requiredCode,
                ]);
                return collect();
            }

            foreach ($eligible as $driver) {
                foreach ($lockedItems as $lockedItem) {
                    $pricing = $this->priceForItem($lockedItem);

                    DeliveryAssignment::create([
                        'mission_number' => $missionNumber,
                        'order_id' => $lockedItem->order_id,
                        'order_item_id' => $lockedItem->id,
                        'driver_id' => $driver->id,
                        'status' => 'offered',
                        'pickup_address' => $lockedItem->shipment?->pickup_address,
                        'delivery_address' => $lockedItem->shipment?->delivery_address,
                        'price_amount' => $pricing['gross'],
                        'driver_commission_percent' => $pricing['commission_percent'],
                        'driver_net_amount' => $pricing['net'],
                        'meta' => array_filter([
                            'mission_number' => $missionNumber,
                            'required_vehicle_code' => $requiredCode,
                            'offer_phase' => collect($lockedItems)->every(
                                fn (OrderItem $line) => $line->delivery_status === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP
                            ) ? 'ready_for_pickup' : 'vendor_preparing',
                            'vendor_preparation_required' => collect($lockedItems)->contains(
                                fn (OrderItem $line) => $line->delivery_status !== OrderWorkflowService::DELIVERY_READY_FOR_PICKUP
                            ),
                        ]),
                    ]);
                }
            }

            // Un seul push par mission et par livreur, avec le gain total de
            // la mission (somme des lignes), pas le prix d'un seul produit.
            DB::afterCommit(function () use ($eligible, $missionNumber) {
                foreach ($eligible as $driver) {
                    $missionNet = DeliveryAssignment::query()
                        ->where('mission_number', $missionNumber)
                        ->where('driver_id', $driver->id)
                        ->where('status', 'offered')
                        ->get()
                        ->sortByDesc('id')
                        ->unique('order_item_id')
                        ->sum(fn (DeliveryAssignment $assignment) => (float) ($assignment->driver_net_amount ?? 0));
                    $netLabel = number_format((float) $missionNet, 0, ',', ' ') . ' FCFA';

                    $this->driverPush->sendToDriver(
                        $driver,
                        'Nouvelle mission à réserver',
                        "Une livraison future vous est proposée pour {$netLabel}. La préparation vendeur peut être en cours : réservez la mission maintenant, puis attendez le signal de collecte.",
                        [
                            'category' => 'mission_offered',
                            'mission_number' => $missionNumber,
                        ]
                    );
                }
            });

            return $eligible;
        });

        return $drivers->count();
    }


    /**
     * Informe le livreur qui a déjà réservé la mission qu'un point vendeur est
     * désormais prêt. Un seul message est envoyé par boutique et par mission,
     * même si la boutique possède plusieurs lignes de commande.
     *
     * Quand le dernier point vendeur devient prêt, le message indique
     * explicitement que la collecte peut commencer. Cette méthode ne change
     * jamais le statut physique de la mission : elle ne fait qu'informer.
     */
    private function notifyReservedDriverAboutVendorReadiness(int $orderItemId): void
    {
        $payload = DB::transaction(function () use ($orderItemId) {
            $item = OrderItem::query()
                ->with(['product.shop', 'order'])
                ->whereKey($orderItemId)
                ->lockForUpdate()
                ->first();

            if (! $item || $item->delivery_provider !== OrderWorkflowService::PROVIDER_OVANIE) {
                return null;
            }

            $group = $this->consolidation->groupForItem($item);
            $groupItems = collect($group['items'] ?? []);
            $itemIds = $groupItems->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

            if ($itemIds->isEmpty()) {
                return null;
            }

            $missionNumber = (string) ($group['mission_number'] ?? sprintf('OVL-%05d-01', (int) $item->order_id));
            $shop = $item->product?->shop;
            $shopId = $shop?->id;

            if (! $shopId) {
                return null;
            }

            $pickupStops = collect($group['pickup_stops'] ?? []);
            $stop = $pickupStops->first(function (array $candidate) use ($shopId): bool {
                return (string) ($candidate['id'] ?? '') === (string) $shopId;
            });

            // On notifie le livreur uniquement quand TOUTES les lignes de ce
            // vendeur sont prêtes. Ainsi 5 articles d'une même boutique ne
            // génèrent pas 5 notifications.
            if (! is_array($stop) || ! (bool) ($stop['ready'] ?? false)) {
                return null;
            }

            $winnerAssignments = DeliveryAssignment::query()
                ->whereIn('order_item_id', $itemIds->all())
                ->whereIn('status', ['accepted', 'assigned'])
                ->with('driver')
                ->orderByDesc('accepted_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $winner = $winnerAssignments->first();
            if (! $winner?->driver) {
                // Aucun livreur n'a encore réservé : il recevra l'état "prêt"
                // directement dans la mission lorsqu'il l'acceptera.
                return null;
            }

            $alreadyNotified = $winnerAssignments->contains(function (DeliveryAssignment $assignment) use ($shopId): bool {
                $ids = collect(data_get($assignment->meta, 'vendor_ready_notified_shop_ids', []))
                    ->map(fn ($id) => (string) $id);

                return $ids->contains((string) $shopId);
            });

            if ($alreadyNotified) {
                return null;
            }

            foreach ($winnerAssignments as $assignment) {
                $meta = is_array($assignment->meta) ? $assignment->meta : [];
                $notifiedShopIds = collect($meta['vendor_ready_notified_shop_ids'] ?? [])
                    ->push($shopId)
                    ->filter()
                    ->unique(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                $meta['vendor_ready_notified_shop_ids'] = $notifiedShopIds;
                $meta['last_vendor_ready_at'] = now()->toIso8601String();
                $assignment->forceFill(['meta' => $meta])->save();
            }

            $readyPickupCount = (int) ($group['ready_pickup_count'] ?? 0);
            $pickupCount = max(1, (int) ($group['pickup_count'] ?? $pickupStops->count()));
            $allReady = $readyPickupCount >= $pickupCount;
            $shopName = trim((string) ($shop->name ?? 'Un point de collecte'));

            return [
                'driver' => $winner->driver,
                'mission_number' => $missionNumber,
                'order_id' => (int) $item->order_id,
                'shop_id' => (int) $shopId,
                'shop_name' => $shopName !== '' ? $shopName : 'Un point de collecte',
                'ready_pickup_count' => $readyPickupCount,
                'pickup_count' => $pickupCount,
                'all_ready' => $allReady,
            ];
        });

        if (! is_array($payload) || ! ($payload['driver'] ?? null) instanceof DeliveryDriver) {
            return;
        }

        /** @var DeliveryDriver $driver */
        $driver = $payload['driver'];
        $missionNumber = (string) $payload['mission_number'];
        $shopName = (string) $payload['shop_name'];
        $readyPickupCount = (int) $payload['ready_pickup_count'];
        $pickupCount = (int) $payload['pickup_count'];
        $allReady = (bool) $payload['all_ready'];

        $title = $allReady
            ? 'Mission prête pour collecte'
            : 'Point vendeur prêt';

        $message = $allReady
            ? "Tous les points vendeurs de la mission {$missionNumber} sont prêts. Vous pouvez maintenant démarrer les collectes depuis l’application."
            : "{$shopName} a terminé la préparation de la mission {$missionNumber}. {$readyPickupCount}/{$pickupCount} point(s) sont prêts. Attendez le signal final avant de démarrer.";

        // Notification visible dans l'accueil/centre de notifications de l'app
        // livreur, même si Firebase n'est pas configuré.
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'ovanie.driver.notification',
                'notifiable_type' => DeliveryDriver::class,
                'notifiable_id' => $driver->id,
                'data' => json_encode([
                    'title' => $title,
                    'message' => $message,
                    'category' => $allReady ? 'mission_ready_for_pickup' : 'mission_vendor_ready',
                    'mission_number' => $missionNumber,
                    'order_id' => $payload['order_id'],
                    'shop_id' => $payload['shop_id'],
                    'ready_pickup_count' => $readyPickupCount,
                    'pickup_count' => $pickupCount,
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->driverPush->sendToDriver(
            $driver,
            $title,
            $message,
            [
                'category' => $allReady ? 'mission_ready_for_pickup' : 'mission_vendor_ready',
                'mission_number' => $missionNumber,
                'order_id' => $payload['order_id'],
                'shop_id' => $payload['shop_id'],
                'ready_pickup_count' => $readyPickupCount,
                'pickup_count' => $pickupCount,
            ]
        );

        Log::info('OVANIE Logistics : livreur réservé informé de la préparation vendeur.', [
            'mission_number' => $missionNumber,
            'driver_id' => $driver->id,
            'shop_id' => $payload['shop_id'],
            'ready_pickup_count' => $readyPickupCount,
            'pickup_count' => $pickupCount,
            'all_ready' => $allReady,
        ]);
    }

    /**
     * Attache le prix (et le montant net livreur) à une affectation créée par
     * le flux d'attribution manuelle de la Logistique
     * (LogisticsController::assignShipment -> OrderWorkflowService::assignDriver,
     * le seul chemin réellement appelé par l'interface staff aujourd'hui —
     * contrairement à self::assign() ci-dessus, qui n'a aucun appelant), puis
     * notifie le livreur par push avec le montant. $suppressNotification
     * reprend le même indicateur que le reste de la boucle d'affectation
     * consolidée (un seul push pour une mission multi-colis).
     */
    public function attachPricingAndNotify(DeliveryAssignment $assignment, OrderItem $item, bool $suppressNotification = false): void
    {
        $pricing = $this->priceForItem($item);

        $assignment->forceFill([
            'price_amount' => $pricing['gross'],
            'driver_commission_percent' => $pricing['commission_percent'],
            'driver_net_amount' => $pricing['net'],
        ])->save();

        if ($suppressNotification || ! $assignment->driver_id) {
            return;
        }

        $driver = $assignment->driver ?: DeliveryDriver::find($assignment->driver_id);
        if (! $driver) {
            return;
        }

        $netLabel = number_format((float) $pricing['net'], 0, ',', ' ') . ' FCFA';

        DB::afterCommit(function () use ($driver, $assignment, $netLabel) {
            $this->driverPush->sendToDriver(
                $driver,
                'Nouvelle mission',
                "Une livraison vous a été attribuée pour {$netLabel}. Ouvrez l'app pour l'accepter.",
                [
                    'category' => 'mission_assigned',
                    'mission_number' => $assignment->resolved_mission_number,
                ]
            );
        });
    }

    /**
     * Prix de la course pour un colis donné : le montant de livraison déjà
     * facturé au client sur cette ligne (OrderItem::delivery_price, calculé
     * au checkout par OvanieDeliveryPriceCalculator), duquel on retient la
     * commission OVANIE pour obtenir le montant net que touche le livreur.
     */
    private function priceForItem(OrderItem $item): array
    {
        $gross = round((float) $item->delivery_price, 0);
        $commissionPercent = (float) Setting::getValue('logistics_driver_commission_percent', 15);
        $net = round($gross * (1 - $commissionPercent / 100), 0);

        return [
            'gross' => $gross,
            'commission_percent' => $commissionPercent,
            'net' => $net,
        ];
    }

    public function assign(
        OrderItem $item,
        DeliveryDriver $driver,
        array $data,
        ?User $actor = null
    ): DeliveryAssignment {
        $assignment = DB::transaction(function () use ($item, $driver, $data, $actor) {
            $lockedItem = OrderItem::query()
                ->with(['shipment', 'product.shop', 'order.client'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOvanie($lockedItem);

            if (! in_array($lockedItem->delivery_status, [
                OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                OrderWorkflowService::DELIVERY_ASSIGNED,
                OrderWorkflowService::DELIVERY_FAILED,
            ], true)) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Le livreur ne peut être affecté qu’à une expédition prête, déjà affectée ou à réaffecter après incident.',
                ]);
            }

            $lockedDriver = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDriver->is_active || $this->isUnavailable($lockedDriver)) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Ce livreur n’est pas disponible pour une nouvelle mission.',
                ]);
            }

            $requiredCode = $this->requiredVehicleCode($lockedItem);
            if (! $this->driverCanHandle($lockedDriver, $requiredCode)) {
                throw ValidationException::withMessages([
                    'driver_id' => sprintf(
                        'Véhicule incompatible : cette expédition exige au minimum %s.',
                        $lockedItem->shipment?->vehicle_label ?: $lockedItem->logistics_vehicle_label ?: $requiredCode
                    ),
                ]);
            }

            DeliveryAssignment::query()
                ->where('order_item_id', $lockedItem->id)
                ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
                ->update(['status' => 'reassigned']);

            $pricing = $this->priceForItem($lockedItem);

            $assignment = DeliveryAssignment::create([
                'order_id' => $lockedItem->order_id,
                'order_item_id' => $lockedItem->id,
                'driver_id' => $lockedDriver->id,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'assigned_by' => $actor?->id,
                'status' => 'assigned',
                'pickup_address' => $data['pickup_address'] ?? $lockedItem->shipment?->pickup_address,
                'delivery_address' => $data['delivery_address'] ?? $lockedItem->shipment?->delivery_address,
                'pickup_scheduled_at' => $data['pickup_scheduled_at'] ?? null,
                'price_amount' => $pricing['gross'],
                'driver_commission_percent' => $pricing['commission_percent'],
                'driver_net_amount' => $pricing['net'],
                'meta' => array_filter([
                    'required_vehicle_code' => $requiredCode,
                    'driver_vehicle' => $lockedDriver->vehicle,
                ]),
            ]);

            $this->workflow->setDeliveryStatus(
                $lockedItem,
                OrderWorkflowService::DELIVERY_ASSIGNED,
                $actor,
                'logistics',
                'Expédition affectée à ' . $lockedDriver->name . '.',
                [
                    'driver_name' => $lockedDriver->name,
                    'driver_phone' => $lockedDriver->phone,
                    'vehicle_plate' => $data['vehicle_plate'] ?? $lockedItem->vehicle_plate,
                ]
            );

            return $assignment->fresh(['driver']);
        });

        // Hors transaction : l'attribution reste valide même si la notification
        // push échoue, si Firebase n'est pas configuré (voir
        // FirebasePushService::configured()), ou si l'appel réseau est lent. Le
        // livreur retrouve de toute façon la mission au prochain rafraîchissement
        // de la liste.
        $this->driverPush->sendToDriver(
            $assignment->driver,
            'Nouvelle mission',
            'Une nouvelle livraison vous a été attribuée. Ouvrez l’app pour l’accepter.',
            [
                'category' => 'mission_assigned',
                'mission_number' => $assignment->resolved_mission_number,
            ]
        );

        return $assignment;
    }

    public function advance(OrderItem $item, string $targetStatus, ?User $actor = null, ?string $note = null): OrderItem
    {
        return DB::transaction(function () use ($item, $targetStatus, $actor, $note) {
            $lockedItem = OrderItem::query()
                ->with(['shipment', 'order.client', 'latestDeliveryAssignment'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOvanie($lockedItem);

            if ($targetStatus === OrderWorkflowService::DELIVERY_DELIVERED) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'La livraison finale doit être confirmée par le formulaire de preuve de livraison.',
                ]);
            }

            $allowed = self::TRANSITIONS[$lockedItem->delivery_status] ?? [];
            if (! in_array($targetStatus, $allowed, true)) {
                throw ValidationException::withMessages([
                    'delivery_status' => "Transition logistique non autorisée : {$lockedItem->delivery_status} → {$targetStatus}.",
                ]);
            }

            if (! $lockedItem->latestDeliveryAssignment?->driver_id) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'Aucun livreur réel n’est affecté à cette expédition.',
                ]);
            }

            $this->workflow->setDeliveryStatus(
                $lockedItem,
                $targetStatus,
                $actor,
                'logistics',
                $note ?: $this->defaultTransitionNote($targetStatus)
            );

            $assignment = $lockedItem->latestDeliveryAssignment;
            if ($assignment) {
                if ($targetStatus === OrderWorkflowService::DELIVERY_PICKED_UP) {
                    $assignment->forceFill([
                        'status' => 'picked_up',
                        'picked_up_at' => $assignment->picked_up_at ?: now(),
                    ])->save();
                }

                if ($targetStatus === OrderWorkflowService::DELIVERY_IN_TRANSIT) {
                    $assignment->forceFill(['status' => 'in_transit'])->save();
                }
            }

            if ($targetStatus === OrderWorkflowService::DELIVERY_IN_TRANSIT) {
                $this->ensureAndSendOtp($lockedItem->refresh());
            }

            return $lockedItem->refresh();
        });
    }

    public function confirmDelivery(OrderItem $item, array $data, ?User $actor = null)
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $lockedItem = OrderItem::query()
                ->with(['shipment', 'order.client', 'latestDeliveryAssignment'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOvanie($lockedItem);

            if ($lockedItem->delivery_status !== OrderWorkflowService::DELIVERY_IN_TRANSIT) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'La livraison doit être en cours avant de pouvoir être confirmée.',
                ]);
            }

            if ($lockedItem->delivery_completed_at !== null) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'Cette livraison est déjà confirmée.',
                ]);
            }

            $expectedOtp = trim((string) $lockedItem->delivery_otp_code);
            $providedOtp = trim((string) ($data['otp_code'] ?? ''));
            $hasPhoto = filled($data['proof_photo'] ?? null);
            $otpVerified = false;

            if ($expectedOtp !== '') {
                if ($providedOtp === '' || ! hash_equals($expectedOtp, $providedOtp)) {
                    throw ValidationException::withMessages([
                        'otp_code' => 'Le code OTP saisi est incorrect.',
                    ]);
                }
                $otpVerified = true;
            } elseif (! $hasPhoto && blank($data['signature_path'] ?? null)) {
                throw ValidationException::withMessages([
                    'proof_photo' => 'Aucun OTP n’est disponible pour cette expédition : une photo ou une signature de réception est obligatoire.',
                ]);
            }

            if ($otpVerified) {
                $lockedItem->forceFill(['delivery_otp_verified_at' => now()])->save();
            }

            $proof = $this->workflow->createDeliveryProof($lockedItem->refresh(), [
                'driver_id' => $lockedItem->latestDeliveryAssignment?->driver_id,
                'receiver_name' => $data['receiver_name'],
                'receiver_phone' => $data['receiver_phone'],
                'proof_photo' => $data['proof_photo'] ?? null,
                'signature_path' => $data['signature_path'] ?? null,
                'delivery_note' => $data['delivery_note'] ?? null,
                'meta' => [
                    'otp_verified' => $otpVerified,
                    'verified_at' => $otpVerified ? now()->toIso8601String() : null,
                ],
            ], $actor);

            $assignment = $lockedItem->latestDeliveryAssignment;
            if ($assignment) {
                $assignment->forceFill([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ])->save();
            }

            return $proof;
        });
    }

    public function requiredVehicleCode(OrderItem $item): string
    {
        $item->loadMissing('shipment');

        return (string) ($item->shipment?->vehicle_code
            ?: $item->logistics_vehicle_code
            ?: 'moto');
    }

    public function driverCanHandle(DeliveryDriver $driver, string $requiredCode): bool
    {
        $driverCode = $this->normalizeVehicleCode($driver->vehicle);
        $requiredCode = $this->normalizeVehicleCode($requiredCode);

        if (! isset(self::VEHICLE_RANK[$driverCode], self::VEHICLE_RANK[$requiredCode])) {
            return false;
        }

        return self::VEHICLE_RANK[$driverCode] >= self::VEHICLE_RANK[$requiredCode];
    }

    /**
     * Les affectations d'une mission utilisent le véhicule calculé pour cette
     * charge, et non un véhicule simplement plus grand. Exemple : une mission
     * Moto ne propose que des livreurs disposant réellement d'une Moto.
     */
    public function driverMatchesRequiredVehicle(DeliveryDriver $driver, string $requiredCode): bool
    {
        $driverCode = $this->normalizeVehicleCode($driver->vehicle);
        $requiredCode = $this->normalizeVehicleCode($requiredCode);

        return $driverCode !== '' && $requiredCode !== '' && $driverCode === $requiredCode;
    }

    public function driverVehicleCode(DeliveryDriver $driver): string
    {
        return $this->normalizeVehicleCode($driver->vehicle);
    }


    public function isDriverAvailable(DeliveryDriver $driver): bool
    {
        // OVANIE ne recrute pas ses propres livreurs : un livreur partenaire ne peut
        // recevoir de mission qu'une fois son dossier validé par la Logistique
        // (onboarding_status = active), même si le compte est déjà is_active.
        return (bool) $driver->is_active
            && $driver->onboarding_status === DeliveryDriver::ONBOARDING_ACTIVE
            && ! $this->isUnavailable($driver);
    }

    private function ensureAndSendOtp(OrderItem $item): void
    {
        if (filled($item->delivery_otp_code)) {
            return;
        }

        $otp = (string) random_int(100000, 999999);
        $item->forceFill(['delivery_otp_code' => $otp])->save();

        $client = $item->order?->client;
        $this->notifications->send(
            $client,
            'deliveries',
            'Code de confirmation de livraison',
            "Votre code OVANIE de confirmation de livraison est {$otp}. Communiquez-le uniquement après réception réelle de votre commande.",
            [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'url' => route('client.orders.show', $item->order_id),
            ],
            ['in_app', 'email', 'sms']
        );
    }

    private function assertOvanie(OrderItem $item): void
    {
        if ($item->delivery_provider !== OrderWorkflowService::PROVIDER_OVANIE) {
            throw ValidationException::withMessages([
                'delivery_provider' => 'Cette expédition n’est pas prise en charge par OVANIE Logistics.',
            ]);
        }
    }

    private function isUnavailable(DeliveryDriver $driver): bool
    {
        $status = mb_strtolower(trim((string) $driver->status));

        return in_array($status, [
            'indisponible',
            'en conge',
            'en congé',
            'suspendu',
            'inactive',
            'inactif',
        ], true);
    }

    private function normalizeVehicleCode(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['-', ' ', 'é', 'è'], ['_', '_', 'e', 'e'], $value);

        return match (true) {
            str_contains($value, '10t') => 'camion_10t',
            str_contains($value, '3t') => 'camion_3t',
            str_contains($value, 'pickup'), str_contains($value, 'pick_up') => 'pickup',
            str_contains($value, 'tricycle') => 'tricycle',
            str_contains($value, 'moto') => 'moto',
            isset(self::VEHICLE_RANK[$value]) => $value,
            default => '',
        };
    }

    private function defaultTransitionNote(string $status): string
    {
        return match ($status) {
            OrderWorkflowService::DELIVERY_PICKED_UP => 'Colis enlevé au point de vente.',
            OrderWorkflowService::DELIVERY_IN_TRANSIT => 'Livreur en route vers le client.',
            default => 'Statut logistique mis à jour.',
        };
    }
}
