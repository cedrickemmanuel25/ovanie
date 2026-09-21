<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SupportAiCenterArchitectureTest extends TestCase
{
    public function test_support_ai_console_routes_use_the_internal_support_guard(): void
    {
        foreach ([
            'support.ai-agents.index',
            'support.conversations.index',
            'support.calls.index',
            'support.handoffs.index',
            'support.callbacks.index',
            'support.knowledge.index',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");
            $middleware = collect($route->gatherMiddleware());
            $this->assertTrue($middleware->contains('auth:admin'));
            $this->assertTrue($middleware->contains('internal'));
            $this->assertTrue($middleware->contains('staff:support'));
        }
    }

    public function test_public_chat_and_telephony_webhook_routes_are_throttled(): void
    {
        foreach ([
            'api.support.chat.start',
            'api.support.chat.message',
            'webhooks.support.telephony',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} absente.");
            $this->assertTrue(collect($route->gatherMiddleware())->contains(
                fn ($middleware) => str_starts_with((string) $middleware, 'throttle:')
            ));
        }
    }

    public function test_support_ai_permissions_are_declared_for_the_support_role(): void
    {
        $permissions = config('staff.role_permissions.support', []);

        foreach ([
            'support.ai.agents.read',
            'support.ai.conversations.read',
            'support.ai.conversations.respond',
            'support.ai.calls.read',
            'support.ai.calls.initiate',
            'support.ai.calls.transfer',
            'support.ai.handoffs.manage',
            'support.ai.callbacks.manage',
            'support.ai.knowledge.manage',
        ] as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }
}
