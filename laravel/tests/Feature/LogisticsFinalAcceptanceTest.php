<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LogisticsFinalAcceptanceTest extends TestCase
{
    public function test_all_logistics_controller_actions_exist(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'logistique')) {
                continue;
            }

            $controller = $route->getAction('controller');
            if (! is_string($controller) || ! str_contains($controller, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $controller, 2);

            $this->assertTrue(
                class_exists($class),
                "Contrôleur introuvable pour {$route->uri()} : {$class}"
            );
            $this->assertTrue(
                method_exists($class, $method),
                "Méthode introuvable pour {$route->uri()} : {$class}@{$method}"
            );
        }
    }

    public function test_expected_logistics_modules_are_routable(): void
    {
        $requiredRoutes = [
            'logistics.dashboard',
            'logistics.shipments',
            'logistics.assignments.index',
            'logistics.tours.index',
            'logistics.tracking',
            'logistics.drivers',
            'logistics.fleet',
            'logistics.incidents.index',
            'logistics.returns',
            'logistics.handoffs.index',
            'logistics.zones',
            'logistics.ovanie-pricing.index',
            'logistics.partners',
            'logistics.control.reports',
            'logistics.control.notifications',
            'logistics.control.settings',
        ];

        foreach ($requiredRoutes as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Route logistique manquante : {$name}");
        }
    }

    public function test_removed_or_stale_logistics_routes_are_not_exposed(): void
    {
        foreach ([
            'logistics.returns.store',
            'logistics.shipments.confirm',
            'logistics.shipments.problem',
        ] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "Route obsolète encore exposée : {$name}");
        }

        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($uris->contains('logistique/driver-live'), 'L’ancien outil driver-live ne doit plus être exposé.');
    }
}
