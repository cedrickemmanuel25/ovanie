<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminCategoryManagementUiTest extends TestCase
{
    public function test_category_management_is_server_rendered_and_supports_subcategories(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Http/Controllers/Admin/AdminCategoryController.php');
        $model = file_get_contents($root . '/app/Models/Category.php');
        $index = file_get_contents($root . '/resources/views/admin/categories/index.blade.php');
        $form = file_get_contents($root . '/resources/views/admin/categories/partials/form.blade.php');
        $routes = file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('DB::transaction', $controller);
        self::assertStringContainsString("'parent_id'", $controller);
        self::assertStringContainsString('flushCategoryCaches', $controller);
        self::assertStringContainsString('function children()', $model);
        self::assertStringContainsString('Ajouter une sous-catégorie', $index);
        self::assertStringContainsString('name="category_type"', $form);
        self::assertStringContainsString('AdminCategoryController::class', $routes);
        self::assertStringNotContainsString('Rempli dynamiquement via JS', $index);
    }
}
