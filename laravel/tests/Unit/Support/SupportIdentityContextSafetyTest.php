<?php

namespace Tests\Unit\Support;

use App\Models\SupportConversation;
use App\Services\SupportAi\SupportContextAuthorizationService;
use App\Services\SupportAi\SupportContextResolver;
use ReflectionMethod;
use Tests\TestCase;

class SupportIdentityContextSafetyTest extends TestCase
{
    public function test_uncertain_identity_is_detected(): void
    {
        $method = new ReflectionMethod(SupportContextResolver::class, 'identityNeedsConfirmation');
        $resolver = app(SupportContextResolver::class);

        $this->assertTrue($method->invoke($resolver, 'Je suis nouveau sur la plateforme'));
        $this->assertTrue($method->invoke($resolver, "Je ne sais pas si j'ai un compte"));
    }

    public function test_unconfirmed_identity_cannot_expose_shop_data(): void
    {
        $conversation = new SupportConversation();
        $conversation->forceFill([
            'requester_user_id' => 42,
            'requester_match_method' => 'phone',
            'channel' => 'whatsapp',
        ]);

        $safe = app(SupportContextAuthorizationService::class)->authorize($conversation, [
            'requester' => [
                'matched_account' => false,
                'account_match_requires_confirmation' => true,
                'display_name' => 'Profil WhatsApp',
                'has_shop' => false,
            ],
            'shop' => ['_owner_user_id' => 42, 'name' => 'Boutique privée'],
        ]);

        $this->assertNull($safe['shop']);
    }
}
