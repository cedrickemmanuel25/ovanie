<?php

namespace Tests\Unit\Support;

use App\Services\WhatsApp\MetaWebhookSignatureVerifier;
use Tests\TestCase;

class MetaWebhookSignatureVerifierTest extends TestCase
{
    public function test_it_accepts_a_valid_meta_signature(): void
    {
        config()->set('whatsapp.app_secret', 'secret-test');

        $body = '{"object":"whatsapp_business_account"}';
        $signature = 'sha256='.hash_hmac('sha256', $body, 'secret-test');

        $this->assertTrue(app(MetaWebhookSignatureVerifier::class)->verify($body, $signature));
    }

    public function test_it_rejects_an_invalid_meta_signature(): void
    {
        config()->set('whatsapp.app_secret', 'secret-test');

        $this->assertFalse(app(MetaWebhookSignatureVerifier::class)->verify('{}', 'sha256=invalid'));
    }
}
