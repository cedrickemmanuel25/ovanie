<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Support\Facades\DB;
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
    ) {
    }

    public function eligibleDrivers(OrderItem $item)
    {
        $requiredCode = $this->requiredVehicleCode($item);

        return LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->orderBy('name')
            ->get()
            ->filter(fn (DeliveryDriver $driver) => $this->isDriverAvailable($driver) && $this->driverCanHandle($driver, $requiredCode))
            ->values();
    }

    public function assign(
        OrderItem $item,
        DeliveryDriver $driver,
        array $data,
        ?User $actor = null
    ): DeliveryAssignment {
        return DB::transaction(function () use ($item, $driver, $data, $actor) {
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
