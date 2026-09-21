<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminPromotionsAndPayoutsProfessionalUiTest extends TestCase
{
    public function test_payout_amounts_are_displayed_in_a_compact_financial_strip(): void
    {
        $css = file_get_contents(base_path('public/admin/css/admin_payouts.css'));
        $view = file_get_contents(base_path('resources/views/admin/payouts/index.blade.php'));

        $this->assertStringContainsString('.pay-financial-grid', $css);
        $this->assertStringContainsString('white-space: nowrap', $css);
        $this->assertStringContainsString('Montant brut', $view);
        $this->assertStringContainsString('Commission OVANIE', $view);
        $this->assertStringContainsString('Net à verser', $view);
    }

    public function test_promotions_are_loaded_and_saved_from_the_database(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Admin/AdminPromotionController.php'));
        $routes = file_get_contents(base_path('routes/admin_promotions.php'));

        $this->assertStringContainsString('Promotion::create($data)', $controller);
        $this->assertStringContainsString("products()->sync", $controller);
        $this->assertStringContainsString("promotions.create", $routes);
        $this->assertStringContainsString("promotions.update", $routes);
        $this->assertStringContainsString("promotions.destroy", $routes);
    }

    public function test_promotions_page_has_professional_filters_and_real_empty_state(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/promotions/index.blade.php'));

        $this->assertStringContainsString('Gestion des promotions', $view);
        $this->assertStringContainsString('Retrouver une promotion', $view);
        $this->assertStringContainsString('Aucune promotion enregistrée.', $view);
        $this->assertStringNotContainsString('Black Friday</td>', $view);
        $this->assertStringNotContainsString('Vente Flash Décembre</td>', $view);
    }

    public function test_creation_and_editing_use_real_laravel_forms(): void
    {
        $create = file_get_contents(base_path('resources/views/admin/promotions/create.blade.php'));
        $edit = file_get_contents(base_path('resources/views/admin/promotions/edit.blade.php'));
        $form = file_get_contents(base_path('resources/views/admin/promotions/partials/form.blade.php'));

        $this->assertStringContainsString("route('admin.promotions.store')", $create);
        $this->assertStringContainsString("route('admin.promotions.update', $promotion)", $edit);
        $this->assertStringContainsString('name="product_ids[]"', $form);
        $this->assertStringContainsString('Période de diffusion', $form);
    }
}
