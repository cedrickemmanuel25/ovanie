<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Validation\ValidationException;

class VendorOrderTransitionService
{
    private const PREPARATION_TRANSITIONS = [
        'pending' => ['accepted'],
        'accepted' => ['preparing'],
        'preparing' => ['ready'],
        'ready' => [],
        'shipped' => [],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function currentStatus(OrderItem $item): string
    {
        return $item->vendor_status ?: 'pending';
    }

    public function allowedPreparationTransitions(OrderItem $item): array
    {
        return self::PREPARATION_TRANSITIONS[$this->currentStatus($item)] ?? [];
    }

    public function assertPreparationTransition(OrderItem $item, string $targetStatus): void
    {
        $current = $this->currentStatus($item);

        if (! in_array($targetStatus, $this->allowedPreparationTransitions($item), true)) {
            throw ValidationException::withMessages([
                'vendor_status' => "Transition impossible : {$current} → {$targetStatus}.",
            ]);
        }
    }

    public function assertCanMarkReadyForOvanie(OrderItem $item): void
    {
        if ($item->delivery_provider !== OrderWorkflowService::PROVIDER_OVANIE) {
            throw ValidationException::withMessages([
                'delivery_provider' => 'Cette ligne n’est pas prise en charge par OVANIE Logistics.',
            ]);
        }

        if ($this->currentStatus($item) !== 'ready') {
            throw ValidationException::withMessages([
                'vendor_status' => 'La commande doit d’abord être acceptée, préparée puis marquée prête.',
            ]);
        }
    }

    public function assertCanStartSellerDelivery(OrderItem $item): void
    {
        if ($item->delivery_provider !== OrderWorkflowService::PROVIDER_SELLER) {
            throw ValidationException::withMessages([
                'delivery_provider' => 'Cette ligne ne relève pas de la logistique vendeur.',
            ]);
        }

        if ($this->currentStatus($item) !== 'ready') {
            throw ValidationException::withMessages([
                'vendor_status' => 'La commande doit être préparée et marquée prête avant le départ en livraison.',
            ]);
        }
    }

    public function assertCanUpdateSellerDelivery(OrderItem $item): void
    {
        if ($item->delivery_provider !== OrderWorkflowService::PROVIDER_SELLER) {
            throw ValidationException::withMessages([
                'delivery_provider' => 'OVANIE Logistics gère cette livraison. Le vendeur ne peut pas modifier son statut logistique.',
            ]);
        }

        if (! in_array($item->delivery_status, [
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
            OrderWorkflowService::DELIVERY_FAILED,
        ], true)) {
            throw ValidationException::withMessages([
                'vendor_delivery_status' => 'La livraison vendeur doit être démarrée avant toute mise à jour de suivi.',
            ]);
        }
    }

    public function assertCanVerifyOtp(OrderItem $item): void
    {
        if ($item->delivery_provider !== OrderWorkflowService::PROVIDER_SELLER) {
            throw ValidationException::withMessages([
                'delivery_otp_code' => 'La validation OTP vendeur n’est pas disponible pour une livraison OVANIE Logistics.',
            ]);
        }

        if ($item->delivery_status !== OrderWorkflowService::DELIVERY_IN_TRANSIT) {
            throw ValidationException::withMessages([
                'delivery_otp_code' => 'Le code OTP ne peut être validé que lorsque la livraison est réellement en cours.',
            ]);
        }

        if ($item->delivery_otp_verified_at !== null) {
            throw ValidationException::withMessages([
                'delivery_otp_code' => 'Cette livraison a déjà été confirmée par OTP.',
            ]);
        }
    }

    public function nextAction(OrderItem $item): ?string
    {
        return match ($this->currentStatus($item)) {
            'pending' => 'accepted',
            'accepted' => 'preparing',
            'preparing' => 'ready',
            default => null,
        };
    }
}
