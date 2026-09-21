<?php

namespace Tests\Unit\Support;

use App\Services\SupportAi\SupportConversationMemoryService;
use ReflectionMethod;
use Tests\TestCase;

class SupportConversationMemoryServiceTest extends TestCase
{
    public function test_normal_problem_wording_does_not_reset_the_active_topic(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'looksLikeExplicitNewRequest');
        $service = app(SupportConversationMemoryService::class);

        $this->assertFalse($method->invoke($service, 'j ai un probleme avec le paiement'));
        $this->assertFalse($method->invoke($service, 'je voudrais savoir ou est ma commande'));
        $this->assertFalse($method->invoke($service, 'maintenant j ai le numero de reference'));
    }

    public function test_only_an_explicit_topic_change_resets_the_active_topic(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'looksLikeExplicitNewRequest');
        $service = app(SupportConversationMemoryService::class);

        $this->assertTrue($method->invoke($service, 'autre question'));
        $this->assertTrue($method->invoke($service, 'changeons de sujet'));
        $this->assertTrue($method->invoke($service, 'passons a autre chose'));
        $this->assertTrue($method->invoke($service, 'oubliez le ciment je veux du gravier'));
    }

    public function test_memory_configuration_keeps_a_bounded_history_for_one_day(): void
    {
        $this->assertSame(12, config('support_ai.conversation.recent_messages'));
        $this->assertSame(1440, config('support_ai.conversation.memory_ttl_minutes'));
    }

    public function test_product_search_and_order_remain_in_the_same_purchase_journey(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'sameBusinessJourney');
        $service = app(SupportConversationMemoryService::class);

        $this->assertTrue($method->invoke(
            $service,
            ['topic_key' => 'catalog', 'active_intent' => 'product_search'],
            'order',
            'product',
            'comment passer une commande',
        ));
    }

    public function test_short_product_name_answers_the_pending_question_instead_of_starting_catalog_search(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'isReplyToPendingQuestion');
        $service = app(SupportConversationMemoryService::class);
        $memory = ['pending_question' => 'Avez-vous déjà acheté quelque chose sur OVANIE par le passé ?'];

        $this->assertTrue($method->invoke($service, 'oui gravier', $memory));
        $this->assertTrue($method->invoke($service, "c'est vide", $memory));
        $this->assertFalse($method->invoke($service, 'je cherche du gravier disponible', $memory));
        $this->assertFalse($method->invoke($service, 'je veux acheter du gravier', $memory));
        $this->assertFalse($method->invoke($service, 'Je vais prendre 15 tôles bac couleur', $memory));
    }

    public function test_whatsapp_assistance_entry_message_starts_a_new_support_request(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'isExplicitNewSupportRequest');
        $service = app(SupportConversationMemoryService::class);

        $this->assertTrue($method->invoke($service, "Bonjour OVANIE, j’ai besoin d’assistance."));
        $this->assertTrue($method->invoke($service, "Bonsoir, j'ai un autre problème"));
        $this->assertTrue($method->invoke($service, 'Je souhaite recommencer'));
        $this->assertFalse($method->invoke($service, 'Bonjour'));
        $this->assertFalse($method->invoke($service, 'Bonjour, oui la commande 1234'));
    }

    public function test_decision_is_mapped_to_a_stable_business_journey(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'journeyFromDecision');
        $service = app(SupportConversationMemoryService::class);

        $this->assertSame('purchase', $method->invoke($service, ['intent' => 'product_search', 'topic_key' => 'catalog']));
        $this->assertSame('purchase', $method->invoke($service, ['intent' => 'order_tracking', 'topic_key' => 'commande']));
        $this->assertSame('seller', $method->invoke($service, ['intent' => 'shop_setup', 'topic_key' => 'boutique']));
        $this->assertSame('business', $method->invoke($service, ['intent' => 'business_quote', 'topic_key' => 'devis']));
    }

    public function test_secondary_question_does_not_answer_or_erase_the_pending_identity_question(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'isReplyToPendingQuestion');
        $service = app(SupportConversationMemoryService::class);
        $memory = ['pending_question' => 'Pouvez-vous donner l’adresse e-mail associée au compte ?'];

        $this->assertFalse($method->invoke($service, "Est-ce que vous livrez aussi en dehors d'Abidjan ?", $memory));
        $this->assertTrue($method->invoke($service, 'konan.yves@gmail.com', $memory));
    }

    public function test_order_word_has_priority_over_product_name(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'topicFamilyFromText');
        $service = app(SupportConversationMemoryService::class);

        $this->assertSame('order', $method->invoke($service, 'pour ma commande de gravier vous avez trouve'));
        $this->assertSame('product', $method->invoke($service, 'je cherche du gravier'));
    }

    public function test_previous_order_segment_is_restored_instead_of_opening_catalog(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'findResumableSegment');
        $service = app(SupportConversationMemoryService::class);
        $orderSegment = [
            'segment_id' => 'order-segment',
            'active_intent' => 'missing_order',
            'topic_key' => 'commande_non_livree',
            'journey' => 'after_sales',
            'known_references' => ['CMD-9999'],
            'pending_question' => 'Quel est l’e-mail associé au compte ?',
        ];
        $active = [
            'segment_id' => 'seller-segment',
            'active_intent' => 'shop_setup',
            'topic_key' => 'boutique',
            'journey' => 'seller',
            'suspended_segments' => [$orderSegment],
        ];

        $result = $method->invoke($service, 'Au fait, pour ma commande de gravier, vous avez trouvé ?', $active);

        $this->assertIsArray($result);
        $this->assertSame('order-segment', $result['segment']['segment_id']);
        $this->assertSame('Quel est l’e-mail associé au compte ?', $result['segment']['pending_question']);
    }

    public function test_active_journey_cannot_drift_without_a_new_topic(): void
    {
        $method = new ReflectionMethod(SupportConversationMemoryService::class, 'stableJourney');
        $service = app(SupportConversationMemoryService::class);
        $existing = ['journey' => 'after_sales'];
        $purchaseDecision = ['intent' => 'product_search', 'topic_key' => 'catalog'];

        $this->assertSame('after_sales', $method->invoke($service, $existing, $purchaseDecision, false));
        $this->assertSame('purchase', $method->invoke($service, $existing, $purchaseDecision, true));
    }
}
