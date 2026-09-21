<?php

namespace App\Services;

use App\Models\ClientPaymentMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentMethodResolver
{
    private const PAYDUNYA_CHANNELS = [
        'orange' => 'orange-money-ci',
        'mtn' => 'mtn-ci',
        'wave' => 'wave-ci',
        'moov' => 'moov-ci',
    ];

    public function resolve(User $user, ?int $methodId, string $checkoutMethod): ?ClientPaymentMethod
    {
        if (! $methodId) {
            return null;
        }

        $method = $user->paymentMethods()->whereKey($methodId)->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'client_payment_method_id' => 'Le moyen de paiement sélectionné ne vous appartient pas ou n’existe plus.',
            ]);
        }

        if (! in_array($checkoutMethod, ['paydunya', 'cash_on_delivery'], true)) {
            throw ValidationException::withMessages([
                'client_payment_method_id' => 'Le moyen de paiement enregistré ne peut pas être utilisé avec ce mode de règlement.',
            ]);
        }

        if (! in_array($method->operator, ClientPaymentMethod::OPERATORS, true)) {
            throw ValidationException::withMessages([
                'client_payment_method_id' => 'Le moyen de paiement enregistré n’est pas pris en charge.',
            ]);
        }

        return $method;
    }

    public function paydunyaChannel(?ClientPaymentMethod $method): ?string
    {
        if (! $method) {
            return null;
        }

        return self::PAYDUNYA_CHANNELS[$method->operator] ?? null;
    }

    public function paydunyaChannelForOperator(?string $operator): ?string
    {
        $operator = strtolower(trim((string) $operator));

        return self::PAYDUNYA_CHANNELS[$operator] ?? null;
    }
}
