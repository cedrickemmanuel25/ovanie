<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminCategoryCatalogueLayoutTest extends TestCase
{
    public function test_category_and_subcategory_creation_are_visually_separated(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminCategoryController.php'));
        $categoryView = file_get_contents(resource_path('views/admin/categories/create.blade.php'));
        $subcategoryView = file_get_contents(resource_path('views/admin/categories/create-subcategory.blade.php'));
        $form = file_get_contents(resource_path('views/admin/categories/partials/form.blade.php'));

        $this->assertStringContainsString('admin.categories.create-subcategory', $controller);
        $this->assertStringContainsString('value="category"', $categoryView);
        $this->assertStringContainsString('value="subcategory"', $subcategoryView);
        $this->assertStringNotContainsString('name="category_type" value="subcategory"', $categoryView);
        $this->assertStringContainsString('Catégorie principale de rattachement', $form);
        $this->assertStringNotContainsString('category-type-grid', $form);
    }

    public function test_header_actions_are_kept_on_one_line_on_desktop(): void
    {
        $categories = file_get_contents(resource_path('views/admin/categories/index.blade.php'));
        $references = file_get_contents(resource_path('views/admin/master-products/index.blade.php'));
        $categoryCss = file_get_contents(public_path('admin/css/admin_categories.css'));
        $catalogueCss = file_get_contents(public_path('admin/css/admin_catalogue.css'));

        $this->assertStringContainsString('category-header-actions-inline', $categories);
        $this->assertStringContainsString('reference-hero-actions-inline', $references);
        $this->assertStringContainsString('flex-wrap:nowrap', $categoryCss);
        $this->assertStringContainsString('flex-wrap:nowrap', $catalogueCss);
    }
}
