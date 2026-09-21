<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public static function send(string $phone, string $message)
    {
        try {
            if (! config('services.africastalking.username') || ! config('services.africastalking.key')) {
                Log::info('SMS non envoye: aucun fournisseur configure', [
                    'phone' => $phone,
                    'message' => $message,
                ]);

                return false;
            }

            $AT = new AfricasTalking(
                config('services.africastalking.username'),
                config('services.africastalking.key')
            );

            $sms = $AT->sms();

            return $sms->send([
                'to' => self::formatPhone($phone),
                'message' => $message,
                'from' => config('services.africastalking.sender'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur SMS Africa’s Talking', [
                'phone' => $phone,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '225')) {
            return '+' . $phone;
        }

        return '+225' . ltrim($phone, '0');
    }
}
