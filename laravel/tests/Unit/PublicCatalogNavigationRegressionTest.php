<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicCatalogNavigationRegressionTest extends TestCase
{
    public function test_public_navigation_uses_category_pages_and_registration_choice(): void
    {
        $home = file_get_contents(resource_path('views/public/home.blade.php'));
        $catalog = file_get_contents(resource_path('views/catalog/index.blade.php'));
        $navbar = file_get_contents(resource_path('views/layouts/_navbar.blade.php'));
        $script = file_get_contents(public_path('js/catalog.js'));

        $this->assertStringContainsString("route('categories.show',", $home);
        $this->assertStringContainsString('data-category-url="{{ $categoryDestination }}"', $catalog);
        $this->assertStringContainsString('control.dataset.categoryUrl', $script);
        $this->assertStringContainsString("Route::has('register') ? route('register')", $navbar);
        $this->assertStringNotContainsString("Route::has('register.client') ? route('register.client')", $navbar);
    }

    public function test_contact_details_and_gift_card_links_are_public(): void
    {
        $navbar = file_get_contents(resource_path('views/layouts/_navbar.blade.php'));
        $footer = file_get_contents(resource_path('views/layouts/_footer.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ProductController.php'));

        $this->assertStringContainsString('01 61 78 00 00', $navbar);
        $this->assertStringContainsString('FEH KESSÉ, Abidjan', $footer);
        $this->assertStringContainsString('01 61 78 00 00', $footer);
        $this->assertStringContainsString("'url' => route('gift-cards.show',", $controller);
    }
}
