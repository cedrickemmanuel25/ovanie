<?php

namespace App\Services;

class ClientPaymentPresentationService
{
    public function methodLabel(?string $method): string
    {
        return match ($method) {
            'paydunya' => 'Paiement en ligne sécurisé',
            'bank_transfer' => 'Virement bancaire',
            'cash_on_delivery', 'pay_on_delivery' => 'Paiement à la livraison',
            'mobile_money' => 'Mobile Money',
            'wave' => 'Wave',
            'orange_money', 'orange' => 'Orange Money',
            'mtn_money', 'mtn' => 'MTN MoMo',
            'moov_money', 'moov' => 'Moov Money',
            default => filled($method) ? ucfirst(str_replace('_', ' ', (string) $method)) : 'Non défini',
        };
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid', 'escrow_held', 'released_to_vendor' => 'Paiement confirmé',
            'commission_paid' => 'Frais initiaux confirmés · solde à régler à la livraison',
            'partial' => 'Partiellement payé',
            'processing' => 'Paiement en traitement',
            'failed' => 'Paiement échoué',
            'cancelled' => 'Paiement annulé',
            'refunded' => 'Remboursé',
            default => 'En attente de paiement',
        };
    }
}
