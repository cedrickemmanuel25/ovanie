<?php

namespace Tests\Unit\Support;

use App\Services\SupportAi\SupportAiOrchestrator;
use ReflectionMethod;
use Tests\TestCase;

class SupportAiOrchestratorRoutingTest extends TestCase
{
    public function test_continuation_resumes_with_the_active_specialist(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'initialAgentRole');
        $orchestrator = app(SupportAiOrchestrator::class);

        $role = $method->invoke($orchestrator, [
            'continuation' => true,
            'structured' => ['agent_role' => 'technical'],
        ]);

        $this->assertSame('technical', $role);
    }

    public function test_new_topic_always_starts_with_the_general_agent(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'initialAgentRole');
        $orchestrator = app(SupportAiOrchestrator::class);

        $role = $method->invoke($orchestrator, [
            'continuation' => false,
            'structured' => ['agent_role' => 'technical'],
        ]);

        $this->assertSame('general', $role);
    }

    public function test_welcome_is_never_repeated_after_a_visible_reply(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'shouldUseDeterministicWelcome');
        $orchestrator = app(SupportAiOrchestrator::class);

        $this->assertTrue($method->invoke($orchestrator, 'salut', false));
        $this->assertFalse($method->invoke($orchestrator, 'salut', true));
        $this->assertFalse($method->invoke($orchestrator, 'oui', true));
        $this->assertFalse($method->invoke($orchestrator, 'et ensuite', true));
    }

    public function test_repeated_opening_is_removed_without_losing_the_answer(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'removeRepeatedOpening');
        $orchestrator = app(SupportAiOrchestrator::class);

        $answer = $method->invoke(
            $orchestrator,
            "Bonjour 👋\n\nPasser une commande sur OVANIE se fait depuis votre panier.",
        );

        $this->assertSame(
            'Passer une commande sur OVANIE se fait depuis votre panier.',
            $answer,
        );

        $this->assertSame(
            'Je vous écoute. Quel point souhaitez-vous poursuivre ?',
            $method->invoke(
                $orchestrator,
                'Bienvenue sur le Support OVANIE 👋 Je suis Miss N’Nan. Dites-moi simplement ce dont vous avez besoin, et je vous aiderai.',
            ),
        );
    }

    public function test_email_is_extracted_from_a_whatsapp_message_for_account_verification(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'extractEmail');
        $orchestrator = app(SupportAiOrchestrator::class);

        $this->assertSame('konan.yves@gmail.com', $method->invoke($orchestrator, 'Je réessaie : Konan.Yves@gmail.com'));
        $this->assertNull($method->invoke($orchestrator, 'je ne connais pas mon adresse'));
    }

    public function test_phone_is_extracted_when_client_uses_the_fallback_option(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'extractPhoneHint');
        $orchestrator = app(SupportAiOrchestrator::class);

        $this->assertSame('07 08 09 10 11', $method->invoke($orchestrator, 'Essayez avec le 07 08 09 10 11'));
        $this->assertNull($method->invoke($orchestrator, 'ma commande 1234'));
    }

    public function test_unverified_shop_creation_claim_is_replaced_by_a_verified_safe_answer(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'enforceVerifiedStateClaims');
        $orchestrator = app(SupportAiOrchestrator::class);

        $answer = $method->invoke(
            $orchestrator,
            'Maintenant que votre boutique est créée, connectez-vous avec vos identifiants de vendeur.',
            ['requester' => ['matched_account' => false], 'shop' => null],
        );

        $this->assertSame(
            'Je ne trouve pas encore de boutique associée à votre compte. Voulez-vous que je vous guide pour la créer sur le site ou l’application OVANIE ?',
            $answer,
        );
        $this->assertStringNotContainsString('boutique est créée', $answer);
        $this->assertStringNotContainsString('vos identifiants', $answer);
    }

    public function test_seller_first_answer_immediately_explains_the_real_channel(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'enforceSellerChannelDisclosure');
        $orchestrator = app(SupportAiOrchestrator::class);

        $answer = $method->invoke(
            $orchestrator,
            'Le parcours comporte cinq blocs. Avez-vous déjà un compte OVANIE, ou souhaitez-vous en créer un maintenant ?',
            'Je veux vendre mes produits sur votre site',
            ['requester' => ['matched_account' => false]],
        );

        $this->assertStringContainsString('ici sur WhatsApp', $answer);
        $this->assertStringContainsString('site ou l’application OVANIE', $answer);
        $this->assertStringNotContainsString('Avez-vous déjà un compte OVANIE, ou', $answer);
    }

    public function test_second_fallback_proposes_a_different_actionable_option(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'fallbackBody');
        $orchestrator = app(SupportAiOrchestrator::class);

        $first = $method->invoke($orchestrator, 0);
        $second = $method->invoke($orchestrator, 1);

        $this->assertNotSame($first, $second);
        $this->assertStringContainsString('numéro de téléphone', $second);
        $this->assertStringContainsString('support humain', $second);
    }

    public function test_catalog_failure_never_claims_an_account_verification_problem(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'fallbackBody');
        $answer = $method->invoke(
            app(SupportAiOrchestrator::class),
            0,
            'Vous avez du marbre importé d’Italie ?',
            ['requester' => ['matched_account' => false]],
        );

        $this->assertStringContainsString('catalogue OVANIE', $answer);
        $this->assertStringContainsString('aucune vérification de compte', $answer);
        $this->assertStringNotContainsString('informations de votre compte', $answer);
    }

    public function test_verified_catalog_product_cannot_be_denied_when_adding_to_cart(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'enforceCatalogConsistency');
        $answer = $method->invoke(
            app(SupportAiOrchestrator::class),
            "Je n'ai pas pu localiser le gravier 15/25.",
            'Oui ajoutez-le au panier',
            ['catalog' => ['products' => [[
                'name' => 'Gravier 15/25',
                'effective_price' => 6500,
                'stock' => 100,
            ]]]],
        );

        $this->assertStringContainsString('Gravier 15/25', $answer);
        $this->assertStringContainsString('6 500 FCFA', $answer);
        $this->assertStringContainsString('Je ne peux pas modifier votre panier directement depuis WhatsApp', $answer);
        $this->assertStringNotContainsString("n'ai pas pu localiser", $answer);
    }

    public function test_order_fallback_reports_the_loaded_result_instead_of_a_generic_failure(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'fallbackBody');
        $context = [
            'requester' => ['matched_account' => true],
            'data_status' => ['orders' => ['loaded' => true]],
            'orders' => [],
            'conversation' => ['memory' => ['structured' => ['known_references' => ['CMD-4521']]]],
        ];

        $answer = $method->invoke(app(SupportAiOrchestrator::class), 0, 'CMD-4521, je viens de vous le donner', $context);

        $this->assertStringContainsString('compte OVANIE a bien été identifié', $answer);
        $this->assertStringContainsString('aucune commande CMD-4521', $answer);
        $this->assertStringNotContainsString('difficulté pour charger', $answer);
    }

    public function test_blocking_identity_question_is_resumed_after_secondary_delivery_answer(): void
    {
        $method = new ReflectionMethod(SupportAiOrchestrator::class, 'resumeBlockingPendingQuestion');
        $orchestrator = app(SupportAiOrchestrator::class);
        $identityQuestion = 'Pouvez-vous me donner l’adresse e-mail ou le numéro de téléphone associé à votre compte OVANIE ?';
        $memory = [
            'reply_to_pending_question' => true,
            'structured' => [
                'pending_questions' => [
                    ['body' => $identityQuestion, 'blocking' => true],
                    ['body' => 'Dans quelle ville souhaitez-vous être livré ?', 'blocking' => false],
                ],
            ],
        ];

        $answer = $method->invoke(
            $orchestrator,
            "Yamoussoukro est une localité à vérifier dans les données de couverture.\n\nQuel quartier souhaitez-vous indiquer ?",
            $memory,
        );

        $this->assertStringContainsString('Pour reprendre votre demande en cours', $answer);
        $this->assertStringContainsString($identityQuestion, $answer);
        $this->assertStringNotContainsString('Quel quartier souhaitez-vous indiquer ?', $answer);
    }
}
