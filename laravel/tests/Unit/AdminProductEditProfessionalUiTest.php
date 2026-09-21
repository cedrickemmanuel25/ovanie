<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminProductEditProfessionalUiTest extends TestCase
{
    public function test_edit_form_is_server_rendered_and_complete(): void
    {
        $view = file_get_contents(resource_path('views/admin/products/edit.blade.php'));

        $this->assertStringContainsString("route('admin.products.update', \$product)", $view);
        $this->assertStringContainsString("@method('PUT')", $view);
        $this->assertStringContainsString('Données logistiques', $view);
        $this->assertStringContainsString('weight_kg', $view);
        $this->assertStringContainsString('length_cm', $view);
        $this->assertStringContainsString('width_cm', $view);
        $this->assertStringContainsString('height_cm', $view);
        $this->assertStringContainsString('remove_images[]', $view);
        $this->assertStringContainsString('Enregistrer les modifications', $view);
        $this->assertStringNotContainsString('product_edit.js', $view);
    }

    public function test_update_preserves_images_unless_the_admin_removes_them(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminProductController.php'));

        $this->assertStringContainsString("'remove_images' => ['nullable', 'array']", $controller);
        $this->assertStringContainsString('ProductImageNormalizer', $controller);
        $this->assertStringContainsString("route('admin.products.edit', \$product)", $controller);
        $this->assertStringNotContainsString("si aucune image envoyée → tout supprimer", $controller);
    }
}
