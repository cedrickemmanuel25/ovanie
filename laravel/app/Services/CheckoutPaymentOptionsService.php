<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Validation\ValidationException;

/**
 * Source de vérité unique des modes de paiement du checkout OVANIE.
 *
 * Web et API mobile doivent consulter ce service au lieu de reconstruire
 * chacun leurs propres règles. Les informations retournées sont destinées
 * à l'interface client ; aucune information interne vendeur n'est exposée.
 */
class CheckoutPaymentOptionsService
{
    public const ONLINE_MIN_XOF = 200;
    public const ONLINE_LIMIT_XOF = 3_000_000;

    public function __construct(private readonly OvanieReferenceDataService $references)
    {
    }

    public function forCart(?Cart $cart, float $total = 0): array
    {
        // `shops.direct_payment` est un ancien indicateur conservé uniquement
        // pour compatibilité. Il ne doit plus désactiver PayDunya.
        $requiresCashOnDelivery = false;

        $paydunyaEnabled = (bool) config('paydunya.enabled');
        $onlineMeetsMinimum = $total <= 0 || $total >= self::ONLINE_MIN_XOF;
        $onlineWithinLimit = $total <= 0 || $total <= self::ONLINE_LIMIT_XOF;
        $onlineAllowed = $paydunyaEnabled
            && $onlineMeetsMinimum
            && $onlineWithinLimit
            && ! $requiresCashOnDelivery;

        $onlineReason = null;
        if (! $paydunyaEnabled) {
            $onlineReason = 'Le paiement en ligne est temporairement indisponible.';
        } elseif (! $onlineMeetsMinimum) {
            // Le moyen reste désactivé sous le minimum du prestataire, sans
            // afficher cette contrainte technique au client.
            $onlineReason = null;
        } elseif (! $onlineWithinLimit) {
            $onlineReason = 'Le paiement en ligne est limité à 3 000 000 FCFA pour cette commande.';
        } elseif ($requiresCashOnDelivery) {
            $onlineReason = 'Cette commande doit être réglée à la livraison.';
        }

        return [
            'cash_on_delivery_required' => $requiresCashOnDelivery,
            'online_min_xof' => self::ONLINE_MIN_XOF,
            'online_limit_xof' => self::ONLINE_LIMIT_XOF,
            'methods' => [
                [
                    'code' => 'paydunya',
                    'label' => 'Paiement en ligne',
                    'description' => 'Wave, Orange Money, MTN MoMo, Moov Money ou carte bancaire',
                    'enabled' => $onlineAllowed,
                    'reason' => $onlineReason,
                    'operators' => $this->references->checkoutOperators(),
                ],
                [
                    'code' => 'cash_on_delivery',
                    'label' => 'Paiement à la livraison',
                    'description' => 'Réglez le montant de la commande lors de la livraison.',
                    'enabled' => true,
                    'required' => $requiresCashOnDelivery,
                    'reason' => $requiresCashOnDelivery
                        ? 'Ce mode est requis pour cette commande.'
                        : null,
                    'operators' => [],
                ],
                [
                    'code' => 'bank_transfer',
                    'label' => 'Virement bancaire',
                    'description' => 'Effectuez un virement puis déposez votre preuve de paiement.',
                    'enabled' => ! $requiresCashOnDelivery,
                    'required' => false,
                    'reason' => $requiresCashOnDelivery
                        ? 'Cette commande doit être réglée à la livraison.'
                        : null,
                    'operators' => [],
                ],
            ],
        ];
    }

    public function assertAllowed(?Cart $cart, string $method, float $total = 0): void
    {
        $options = $this->forCart($cart, $total);
        $selected = collect($options['methods'])->firstWhere('code', $method);

        if (! $selected) {
            throw ValidationException::withMessages([
                'payment_method' => 'Ce mode de paiement n’est pas pris en charge.',
            ]);
        }

        if (! ($selected['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'payment_method' => $selected['reason'] ?: 'Ce mode de paiement n’est pas disponible pour cette commande.',
            ]);
        }

        if (($options['cash_on_delivery_required'] ?? false) && $method !== 'cash_on_delivery') {
            throw ValidationException::withMessages([
                'payment_method' => 'Cette commande doit être réglée à la livraison.',
            ]);
        }
    }
}
