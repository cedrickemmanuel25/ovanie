<?php

namespace Tests\Unit;

use Tests\TestCase;

class HomepagePerformanceRegressionTest extends TestCase
{
    public function test_public_homepage_payload_is_cached_without_user_personalization(): void
    {
        $service = file_get_contents(app_path('Services/HomepageService.php'));

        $this->assertStringContainsString('Cache::remember(', $service);
        $this->assertStringContainsString('private function buildPublicPayload(): array', $service);
        $this->assertStringContainsString('$payload[\'cartCount\'] = $this->cartCount($user);', $service);
        $this->assertStringContainsString('$payload[\'favoriteProductIds\'] = $this->favoriteProductIds', $service);
    }

    public function test_homepage_uses_optimized_webp_assets(): void
    {
        $view = file_get_contents(resource_path('views/public/home.blade.php'));
        $assets = [
            'home-hero.webp',
            'home-assistant.webp',
            'home-gros-oeuvre.webp',
            'home-finition.webp',
            'home-outillage.webp',
            'home-equipement.webp',
            'home-energie.webp',
            'home-plomberie.webp',
            'home-carte-cadeau.webp',
            'home-reconditionnes.webp',
        ];

        foreach ($assets as $asset) {
            $path = public_path('storage/logos/' . $asset);
            $this->assertStringContainsString($asset, $view);
            $this->assertFileExists($path);
            $this->assertLessThan(200_000, filesize($path), $asset . ' doit rester inférieur à 200 Ko.');
        }
    }

    public function test_public_layout_defers_non_critical_scripts(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/guest.blade.php'));

        $this->assertMatchesRegularExpression('/js\/main\.js[^>]+defer/', $layout);
        $this->assertMatchesRegularExpression('/js\/category-drawer\.js[^>]+defer/', $layout);
        $this->assertMatchesRegularExpression('/unpkg\.com\/lucide@latest[^>]+defer/', $layout);
    }
}
