<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VendorPayoutSettingsService
{
    public function save(Shop $shop, array $input): Shop
    {
        $data = Validator::make($input, [
            'payment_mode' => ['required', Rule::in([Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY])],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'business_email' => ['nullable', 'email', 'max:255'],
        ], [
            'payment_mode.required' => 'Choisissez votre calendrier de reversement.',
            'mm_operator.required' => 'Choisissez l’opérateur de paiement.',
            'mm_number.required' => 'Le numéro Mobile Money est obligatoire.',
            'mm_number.regex' => 'Le numéro Mobile Money est invalide.',
            'mm_holder.required' => 'Le nom du titulaire est obligatoire.',
        ])->validate();

        return DB::transaction(function () use ($shop, $data) {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $firstExplicitConfiguration = ! $shop->hasConfiguredPayout();

            $shop->forceFill([
                'payment_mode' => $data['payment_mode'],
                'direct_payment' => false,
                'mm_operator' => $data['mm_operator'],
                'mm_number' => $this->cleanPhone($data['mm_number']),
                'mm_holder' => trim($data['mm_holder']),
                'whatsapp' => $this->cleanPhone($data['whatsapp'] ?? $shop->whatsapp),
                'business_email' => $data['business_email'] ?? $shop->business_email,
                'payout_configured_at' => now(),
                // Une boutique historique commence réellement son onboarding payout
                // le jour où elle confirme elle-même sa configuration.
                'payout_onboarding_started_at' => $firstExplicitConfiguration
                    ? now()
                    : ($shop->payout_onboarding_started_at ?: now()),
            ])->save();

            return $shop->refresh();
        });
    }

    private function cleanPhone(?string $value): ?string
    {
        if (! filled($value)) return null;
        $value = preg_replace('/[^0-9+]/', '', (string) $value);
        return $value !== '' ? $value : null;
    }
}
