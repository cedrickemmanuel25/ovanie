<?php

namespace App\Services;

use App\Services\WhatsApp\WhatsAppCloudApiService;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public static function send(string $phone, string $message): bool
    {
        /** @var WhatsAppCloudApiService $service */
        $service = app(WhatsAppCloudApiService::class);

        if (! $service->isConfigured()) {
            Log::warning('Message WhatsApp non envoyé : Cloud API non configurée.', [
                'phone_suffix' => substr(preg_replace('/\D+/', '', $phone), -4),
            ]);

            return false;
        }

        $service->sendTextMessage($phone, $message);

        return true;
    }
}
