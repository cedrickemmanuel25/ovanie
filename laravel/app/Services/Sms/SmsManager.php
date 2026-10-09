<?php

namespace App\Services\Sms;

use App\Services\Sms\Drivers\AllMySmsDriver;

class SmsManager
{
    public static function send($phone, $message)
    {
        if (! config('sms.enabled')) {
            return false;
        }

        $phone = self::formatPhone((string) $phone);
        if (! $phone) {
            return false;
        }

        return match (config('sms.default')) {
            'allmysms' => (new AllMySmsDriver())->send($phone, $message),
            default => throw new \Exception('Driver SMS non supporté'),
        };
    }

    /**
     * Préserve un numéro déjà international (+221, +225, +33, …).
     * Le préfixe +225 n'est appliqué qu'à un numéro réellement local.
     */
    protected static function formatPhone(string $phone): ?string
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }

        if (str_starts_with($raw, '00') && strlen($digits) > 2) {
            return '+' . substr($digits, 2);
        }

        // Les numéros déjà stockés avec l'indicatif CI sans « + » restent inchangés.
        if (str_starts_with($digits, '225') && strlen($digits) >= 11) {
            return '+' . $digits;
        }

        return '+225' . $digits;
    }
}
