<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;

class MtnSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        if (! config('sms.enabled')) {
            return false;
        }

        $res = Http::post(config('services.mtn_sms.url'), [
            'username' => config('services.mtn_sms.username'),
            'password' => config('services.mtn_sms.password'),
            'to' => $phone,
            'message' => $message,
            'from' => 'IMOO'
        ]);

        return $res->ok();
    }
}
