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

        // format numéro CI
        $phone = self::formatPhone($phone);

        $driver = config('sms.default');

        switch ($driver) {
            case 'allmysms':
                return (new AllMySmsDriver())->send($phone, $message);

            default:
                throw new \Exception("Driver SMS non supporté");
        }
    }

    protected static function formatPhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '225')) {
            return '+' . $phone;
        }

        return '+225' . ltrim($phone, '0');
    }
}
