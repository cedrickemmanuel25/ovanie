<?php

namespace Tests\Unit\Support;

use App\Models\SupportConversation;
use App\Services\SupportAi\SupportContextAuthorizationService;
use App\Services\SupportAi\SupportContextResolver;
use App\Services\SupportAi\Providers\ClaudeSupportAiProvider;
use ReflectionMethod;
use Tests\TestCase;

class SupportCatalogContextSafetyTest extends TestCase
{
    public function test_authorized_catalog_keeps_only_backend_categories(): void
    {
        $conversation = new SupportConversation();
        $conversation->forceFill([
            'channel' => 'whatsapp',
            'requester_match_method' => 'unmatched',
        ]);

        $safe = app(SupportContextAuthorizationService::class)->authorize($conversation, [
            'catalog' => [
                'query' => 'produit',
                'terms' => [],
                'categories' => ['Matériaux gros œuvre', 'Énergie solaire'],
                'products' => [],
            ],
        ]);

        $this->assertSame(
            ['Matériaux gros œuvre', 'Énergie solaire'],
            $safe['catalog']['categories'],
        );
    }

    public function test_provider_forbids_generic_catalog_examples(): void
    {
        $provider = file_get_contents(app_path('Services/SupportAi/Providers/ClaudeSupportAiProvider.php'));

        $this->assertStringContainsString('catalog.categories', $provider);
        $this->assertStringContainsString("N'inventez jamais de catégories génériques", $provider);
        $this->assertStringContainsString("La présence d'une catégorie ne prouve JAMAIS", $provider);
        $this->assertStringContainsString('Si catalog.products est vide', $provider);
        $this->assertStringContainsString('conversation.reply_to_pending_question=true', $provider);
        $this->assertStringContainsString("pas une demande de catalogue, de devis ou de proforma", $provider);
        $this->assertStringContainsString('CONTRAT DE PERTINENCE ET DE VÉRITÉ', $provider);
    }

    public function test_laravel_rejects_an_unsolicited_business_or_quote_route(): void
    {
        $method = new ReflectionMethod(ClaudeSupportAiProvider::class, 'parseEnvelope');
        $provider = app(ClaudeSupportAiProvider::class);
        $raw = json_encode([
            'intent' => 'quote',
            'agent_role' => 'business',
            'priority' => 'normal',
            'topic_key' => 'devis',
            'new_topic' => true,
            'facts' => [],
            'missing_fields' => [],
            'action' => 'handoff',
            'data_needs' => [],
            'handoff_target' => 'business',
            'body' => '',
            'confidence' => 90,
        ], JSON_THROW_ON_ERROR);

        $parsed = $method->invoke($provider, $raw, 'general', 'je ne vois pas mes commandes', []);

        $this->assertSame('general', $parsed['agent_role']);
        $this->assertSame('clarify', $parsed['action']);
        $this->assertNull($parsed['handoff_target']);
        $this->assertStringNotContainsString('devis', mb_strtolower($parsed['body']));
    }

    public function test_explicit_quote_request_can_reach_the_business_agent(): void
    {
        $method = new ReflectionMethod(ClaudeSupportAiProvider::class, 'parseEnvelope');
        $provider = app(ClaudeSupportAiProvider::class);
        $raw = json_encode([
            'intent' => 'quote',
            'agent_role' => 'business',
            'priority' => 'normal',
            'topic_key' => 'devis',
            'new_topic' => true,
            'facts' => [],
            'missing_fields' => [],
            'action' => 'handoff',
            'data_needs' => [],
            'handoff_target' => 'business',
            'handoff_summary' => 'Le client demande un devis.',
            'body' => '',
            'confidence' => 90,
        ], JSON_THROW_ON_ERROR);

        $parsed = $method->invoke($provider, $raw, 'general', 'je souhaite un devis pour 50 tonnes de gravier', []);

        $this->assertSame('business', $parsed['agent_role']);
        $this->assertSame('handoff', $parsed['action']);
        $this->assertSame('business', $parsed['handoff_target']);
    }

    public function test_product_clarification_reply_keeps_catalog_access(): void
    {
        $memory = [
            'structured' => [
                'active_intent' => 'product_search',
                'pending_question' => 'Quelle marque ou quel type de ciment recherchez-vous ?',
                'pending_questions' => [[
                    'body' => 'Quelle marque ou quel type de ciment recherchez-vous ?',
                    'intent' => 'product_search',
                ]],
            ],
        ];

        $resolverMethod = new ReflectionMethod(SupportContextResolver::class, 'isCatalogClarificationReply');
        $this->assertTrue($resolverMethod->invoke(
            app(SupportContextResolver::class),
            'Peu importe la marque, le ciment classique ira très bien',
            $memory,
        ));

        $providerMethod = new ReflectionMethod(ClaudeSupportAiProvider::class, 'isCatalogClarificationReply');
        $this->assertTrue($providerMethod->invoke(
            app(ClaudeSupportAiProvider::class),
            'Ciment classique',
            ['conversation' => ['memory' => $memory]],
        ));
    }
}
