<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SupportAiRealIntegrationArchitectureTest extends TestCase
{
    public function test_real_twilio_webhooks_are_registered_and_throttled(): void
    {
        foreach ([
            'webhooks.support.twilio.voice',
            'webhooks.support.twilio.gather',
            'webhooks.support.twilio.status',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} absente.");
            $this->assertContains('POST', $route->methods());
            $this->assertTrue(collect($route->gatherMiddleware())->contains(
                fn ($middleware) => str_starts_with((string) $middleware, 'throttle:')
            ));
        }
    }

    public function test_commercial_and_logistics_receive_dedicated_support_queues(): void
    {
        $expectations = [
            'commercial.handoffs.index' => ['auth:admin', 'internal', 'staff:commercial'],
            'logistics.handoffs.index' => ['auth:admin', 'internal', 'staff:logistique'],
        ];

        foreach ($expectations as $name => $requiredMiddleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");

            $middleware = collect($route->gatherMiddleware());
            foreach ($requiredMiddleware as $required) {
                $this->assertTrue($middleware->contains($required), "Middleware {$required} absent de {$name}.");
            }
        }
    }

    public function test_integration_migration_uses_relations_instead_of_copied_business_data(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_07_15_230000_integrate_support_ai_with_core_workflows.php'));

        foreach ([
            "delivery_incident_id",
            "support_conversation_id",
            "support_ticket_id",
            "commercial_lead_id",
            "target_department",
            "idempotency_key",
            "record_hash",
            "previous_hash",
        ] as $column) {
            $this->assertStringContainsString($column, $migration);
        }

        $this->assertStringContainsString('support_ai_audit_logs_no_update', $migration);
        $this->assertStringContainsString('support_ai_audit_logs_no_delete', $migration);
    }

    public function test_only_published_approved_and_non_expired_knowledge_is_available_to_ai(): void
    {
        $model = file_get_contents(app_path('Models/SupportKnowledgeArticle.php'));
        $resolver = file_get_contents(app_path('Services/SupportAi/SupportContextResolver.php'));

        $this->assertStringContainsString("where('status', 'published')", $model);
        $this->assertStringContainsString("whereNotNull('approved_by')", $model);
        $this->assertStringContainsString("orWhere('expires_at', '>', now())", $model);
        $this->assertStringContainsString('availableToAi()', $resolver);
    }

    public function test_generic_telephony_webhook_rejects_unconfigured_or_unsigned_events(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Webhooks/SupportTelephonyWebhookController.php'));
        $status = file_get_contents(app_path('Services/SupportAi/SupportServiceStatusService.php'));

        $this->assertStringContainsString("provider') === 'webhook'", $controller);
        $this->assertStringContainsString('telephonyConfigured()', $controller);
        $this->assertStringContainsString('if ($secret === \'\')', $controller);
        $this->assertStringContainsString('return false;', $controller);
        $this->assertStringContainsString('$configured && $publiclyReachable', $status);
    }

    public function test_support_ai_seeders_do_not_create_fake_operational_records(): void
    {
        $seeder = file_get_contents(database_path('seeders/SupportAiCenterSeeder.php'));

        $this->assertStringContainsString('SupportAiAgent::query()->updateOrCreate', preg_replace('/\s+/', '', $seeder));
        $this->assertStringNotContainsString('SupportConversation::create', $seeder);
        $this->assertStringNotContainsString('SupportCall::create', $seeder);
        $this->assertStringNotContainsString('SupportTicket::create', $seeder);
    }

    public function test_audit_model_and_database_both_block_mutation(): void
    {
        $model = file_get_contents(app_path('Models/SupportAiAuditLog.php'));
        $migration = file_get_contents(database_path('migrations/2026_07_15_230000_integrate_support_ai_with_core_workflows.php'));

        $this->assertStringContainsString('static::updating', $model);
        $this->assertStringContainsString('static::deleting', $model);
        $this->assertStringContainsString('calculateRecordHash', $model);
        $this->assertStringContainsString('CREATE TRIGGER support_ai_audit_logs_no_update', $migration);
        $this->assertStringContainsString('CREATE TRIGGER support_ai_audit_logs_no_delete', $migration);
    }
}
