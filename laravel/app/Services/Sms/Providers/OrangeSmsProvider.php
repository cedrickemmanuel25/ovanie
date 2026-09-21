<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;

class OrangeSmsProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): bool
    {
        if (! config('sms.enabled')) {
            return false;
        }

        // Auth
        $tokenRes = Http::asForm()->post(
            'https://api.orange.com/oauth/v3/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.orange_sms.client_id'),
                'client_secret' => config('services.orange_sms.client_secret'),
            ]
        );

        if (!$tokenRes->ok()) return false;

        $token = $tokenRes['access_token'];

        // Send
        $res = Http::withToken($token)->post(
            config('services.orange_sms.url'),
            [
                'outboundSMSMessageRequest' => [
                    'address' => "tel:$phone",
                    'senderAddress' => config('services.orange_sms.sender'),
                    'outboundSMSTextMessage' => [
                        'message' => $message
                    ]
                ]
            ]
        );

        return $res->ok();
    }
}
