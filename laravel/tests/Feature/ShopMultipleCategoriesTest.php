<?php

namespace Tests\Feature;

use App\Http\Controllers\ShopController;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopMultipleCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function makeShop(): Shop
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        return Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'latitude' => 5.35, 'longitude' => -4.01,
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_shop_can_be_linked_to_several_categories(): void
    {
        $shop = $this->makeShop();
        $cat1 = Category::query()->create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-' . uniqid(), 'status' => 'actif']);
        $cat2 = Category::query()->create(['name' => 'Outillage', 'slug' => 'outillage-' . uniqid(), 'status' => 'actif']);

        $shop->categories()->sync([$cat1->id, $cat2->id]);

        $this->assertCount(2, $shop->fresh()->categories);
        $this->assertEqualsCanonicalizing(
            [$cat1->id, $cat2->id],
            $shop->fresh()->categories->pluck('id')->all()
        );
    }

    public function test_sync_shop_categories_preserves_selection_order_and_sets_main_category_from_first(): void
    {
        $shop = $this->makeShop();
        $catA = Category::query()->create(['name' => 'A', 'slug' => 'cat-a-' . uniqid(), 'status' => 'actif']);
        $catB = Category::query()->create(['name' => 'B', 'slug' => 'cat-b-' . uniqid(), 'status' => 'actif']);
        $catC = Category::query()->create(['name' => 'C', 'slug' => 'cat-c-' . uniqid(), 'status' => 'actif']);

        // L'ordre choisi par le vendeur (B, C, A) doit être respecté, pas l'ordre SQL.
        $slugsInVendorOrder = [$catB->slug, $catC->slug, $catA->slug];

        $method = new \ReflectionMethod(ShopController::class, 'syncShopCategories');
        $method->setAccessible(true);
        $method->invoke(app(ShopController::class), $shop, $slugsInVendorOrder);

        $this->assertSame(
            [$catB->id, $catC->id, $catA->id],
            $shop->fresh()->categories->pluck('id')->all()
        );
    }

    public function test_shop_category_pivot_ignores_unknown_slugs_without_error(): void
    {
        $shop = $this->makeShop();
        $cat = Category::query()->create(['name' => 'Réel', 'slug' => 'reel-' . uniqid(), 'status' => 'actif']);

        $method = new \ReflectionMethod(ShopController::class, 'syncShopCategories');
        $method->setAccessible(true);
        $method->invoke(app(ShopController::class), $shop, [$cat->slug, 'slug-qui-nexiste-pas']);

        $this->assertCount(1, $shop->fresh()->categories);
        $this->assertSame($cat->id, $shop->fresh()->categories->first()->id);
    }
}
