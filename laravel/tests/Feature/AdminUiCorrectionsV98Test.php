<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminUiCorrectionsV98Test extends TestCase
{
    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function test_dashboard_no_longer_renders_test_or_debug_notices(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/admin/dashboard.blade.php'));

        $this->assertStringNotContainsString('<strong>Environnement de test</strong>', $view);
        $this->assertStringNotContainsString('Les paiements et reversements affichés peuvent être simulés.', $view);
        $this->assertStringNotContainsString('<strong>Mode diagnostic actif</strong>', $view);
        $this->assertStringNotContainsString('APP_DEBUG doit être désactivé avant la mise en production.', $view);
    }

    public function test_all_requested_form_actions_are_integrated_in_the_document_flow(): void
    {
        $checks = [
            'public/admin/css/admin_product_edit.css' => '.product-form-actions',
            'public/admin/css/admin_catalogue.css' => '.catalogue-form-actions',
            'public/admin/css/admin_categories.css' => '.category-form-footer',
            'public/admin/css/admin_clients.css' => '.ac-form-footer',
            'public/admin/css/admin_promotions.css' => '.promo-save-bar',
            'public/admin/css/admin_settings.css' => '.settings-actions',
        ];

        foreach ($checks as $file => $selector) {
            $css = file_get_contents($this->projectPath($file));
            $this->assertStringContainsString($selector, $css, $file);
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($selector, '/') . '\s*\{[^}]*position:\s*static/s',
                $css,
                $file
            );
        }
    }

    public function test_admin_product_preview_preserves_json_and_renders_the_public_sheet(): void
    {
        $controller = file_get_contents($this->projectPath('app/Http/Controllers/Admin/AdminProductController.php'));
        $view = file_get_contents($this->projectPath('resources/views/admin/products/edit.blade.php'));

        $this->assertStringContainsString('ProductCalculatorService $calculatorService', $controller);
        $this->assertStringContainsString("return view('products.show'", $controller);
        $this->assertStringContainsString('$request->expectsJson()', $controller);
        $this->assertStringContainsString("url('/admin/products/' . \$product->getRouteKey())", $view);
        $this->assertStringNotContainsString("url('/produit/'", $view);
    }

    public function test_settings_page_uses_svg_icons_instead_of_temporary_emoji(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/admin/settings.blade.php'));

        foreach (['🏪', '📞', '📍', '⚠️', '✅', '⚙️', '💎', '📢', '🏦', '🖼️', '💾'] as $emoji) {
            $this->assertStringNotContainsString($emoji, $view);
        }

        $this->assertStringContainsString('$settingsIcons = [', $view);
        $this->assertStringContainsString("\$settingsIcons['save']", $view);
    }

    public function test_password_reset_button_is_wrapped_in_its_own_responsive_action_area(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/admin/staff/edit.blade.php'));
        $css = file_get_contents($this->projectPath('public/admin/css/admin_staff.css'));

        $this->assertStringContainsString('class="staff-inline-actions"', $view);
        $this->assertStringContainsString('.staff-inline-actions', $css);
        $this->assertStringContainsString('grid-column: 1 / -1', $css);
    }
}
