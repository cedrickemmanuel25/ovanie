<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorProductController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\PayDunyaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Paydunya\Checkout\CheckoutInvoice;
use Tests\TestCase;

class VendorMobileBoostTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendorWithActiveProduct(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'landmark' => 'Pharmacie du carrefour', 'latitude' => 5.35, 'longitude' => -4.01, 'geo_status' => 'verified',
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat-' . uniqid(), 'status' => 'actif']);
        $product = Product::query()->create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Produit boostable', 'slug' => 'produit-boostable-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 5,
            'status' => 'actif', 'is_active' => true,
        ]);

        return [$vendor, $product];
    }

    public function test_boost_packages_endpoint_returns_the_same_packages_as_the_web_modal(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique test',
            'slug' => 'boutique-test-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'landmark' => 'Pharmacie du carrefour', 'latitude' => 5.35, 'longitude' => -4.01, 'geo_status' => 'verified',
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')->getJson('/api/mobile/v1/vendor/boost/packages');

        $response->assertOk();
        $packages = $response->json('packages');
        $this->assertCount(count(VendorProductController::boostPackagesList()), $packages);
        $this->assertSame('semaine_classic', $packages[0]['key']);
        $this->assertSame(1400, $packages[0]['price']);
    }

    public function test_pay_boost_creates_a_paydunya_invoice_and_returns_its_url(): void
    {
        [$vendor, $product] = $this->makeVendorWithActiveProduct();

        $invoice = Mockery::mock(CheckoutInvoice::class);
        $invoice->token = 'BOOST-' . uniqid();
        $invoice->shouldReceive('create')->once()->andReturnTrue();
        $invoice->shouldReceive('getInvoiceUrl')->once()->andReturn('https://pay.example.test/boost-invoice');

        $this->mock(PayDunyaService::class, function ($mock) use ($invoice): void {
            $mock->shouldReceive('createBoostInvoice')->once()->andReturn($invoice);
        });

        $response = $this->actingAs($vendor, 'sanctum')->postJson(
            "/api/mobile/v1/vendor/products/{$product->id}/boost/pay",
            ['boost_package' => 'semaine_classic', 'phone' => '0700000000']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('redirect_url', 'https://pay.example.test/boost-invoice');
        $this->assertSame('pending', $product->fresh()->boost_payment_status);
    }

    public function test_pay_boost_is_refused_for_a_product_belonging_to_another_shop(): void
    {
        [, $product] = $this->makeVendorWithActiveProduct();
        $otherVendor = User::factory()->create(['role' => 'vendor']);
        Shop::query()->create([
            'user_id' => $otherVendor->id, 'name' => 'Autre boutique',
            'slug' => 'autre-boutique-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'landmark' => 'Pharmacie du carrefour', 'latitude' => 5.35, 'longitude' => -4.01, 'geo_status' => 'verified',
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);

        $response = $this->actingAs($otherVendor, 'sanctum')->postJson(
            "/api/mobile/v1/vendor/products/{$product->id}/boost/pay",
            ['boost_package' => 'semaine_classic']
        );

        $response->assertForbidden();
    }
}
