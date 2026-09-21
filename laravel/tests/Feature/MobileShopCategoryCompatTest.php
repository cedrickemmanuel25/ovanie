<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileShopCategoryCompatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The vendor mobile app (ovanie_vendor Flutter) still submits a single
     * 'main_category' field to POST /open-shop(-v2), which internally calls
     * ShopController::store() — the same endpoint the web open-shop form
     * uses, now validating 'categories' (array) instead. Without the
     * backward-compat shim in VendorMobileController::openShop(), every
     * mobile shop creation would fail validation on the missing 'categories'
     * field as soon as the multi-category change shipped.
     */
    public function test_mobile_open_shop_with_only_main_category_does_not_fail_on_categories_field(): void
    {
        $response = $this->postJson('/api/mobile/v1/vendor/open-shop-v2', [
            'main_category' => 'materiaux-gros-oeuvres',
        ]);

        $errors = $response->json('errors') ?? [];

        $this->assertArrayNotHasKey('categories', $errors);
        $this->assertArrayNotHasKey('categories.0', $errors);
    }
}
