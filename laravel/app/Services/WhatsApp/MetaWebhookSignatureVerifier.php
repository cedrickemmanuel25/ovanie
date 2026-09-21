<?php

namespace App\Services\WhatsApp;

class MetaWebhookSignatureVerifier
{
    public function verify(string $rawBody, ?string $signature): bool
    {
        $appSecret = trim((string) config('whatsapp.app_secret', ''));
        $signature = trim((string) $signature);

        if ($appSecret === '' || $signature === '') {
            return false;
        }

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
