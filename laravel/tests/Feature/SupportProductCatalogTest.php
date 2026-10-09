<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Le Support Center ajoute et modifie les produits comme l'administration, sans jamais supprimer. */
class SupportProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_can_list_create_and_edit_products_without_delete_controls(): void
    {
        $support = User::factory()->create(['role' => 'support', 'status' => 'active']);
        $product = $this->product();

        $index = $this->actingAs($support, 'admin')->get(route('support.products.index'));
        $index->assertOk();
        $index->assertSee('Support Center');
        $index->assertSee('support/css/catalogue-scope.css', false);
        $index->assertDontSee('OVANIE Support');
        $index->assertSee(route('support.products.edit', $product), false);
        $index->assertDontSee(route('admin.products.destroy', $product), false);
        $index->assertDontSee('Supprimer définitivement ce produit');

        $this->actingAs($support, 'admin')->get(route('support.products.create'))->assertOk()
            ->assertSee(route('support.products.store'), false);

        $edit = $this->actingAs($support, 'admin')->get(route('support.products.edit', $product));
        $edit->assertOk();
        $edit->assertSee(route('support.products.update', $product), false);
        $edit->assertDontSee('Supprimer le produit');
        $edit->assertDontSee('id="deleteProductForm"', false);

        $this->actingAs($support, 'admin')->get(route('support.products.show', $product))->assertOk();
    }

    public function test_catalogue_page_still_loads_when_the_public_folder_lacks_the_stylesheet(): void
    {
        // Sur cPanel, public_path() pointe vers public_html : une CSS non copiée ne doit pas provoquer d'erreur 500.
        $this->app->usePublicPath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ovanie-public-vide-' . uniqid());
        $support = User::factory()->create(['role' => 'support', 'status' => 'active']);
        $this->product();

        $this->actingAs($support, 'admin')->get(route('support.products.index'))->assertOk();
    }

    public function test_support_cannot_delete_a_product(): void
    {
        $support = User::factory()->create(['role' => 'support', 'status' => 'active']);
        $product = $this->product();

        $this->actingAs($support, 'admin')->delete(route('admin.products.destroy', $product));
        $this->actingAs($support, 'admin')->delete(url('/support/produits/' . $product->getRouteKey()));

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_space_still_offers_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $product = $this->product();

        $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Supprimer le produit');
    }

    private function product(): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique support', 'slug' => 'boutique-support-' . uniqid(),
            'seller_type' => 'particulier', 'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-SUP-' . uniqid(),
            'status' => 'approved', 'is_active' => true, 'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);
        $category = Category::create(['name' => 'Ciment', 'slug' => 'ciment-' . uniqid(), 'status' => 'actif']);

        return Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Ciment CPJ 45', 'slug' => Str::slug('Ciment CPJ 45') . '-' . uniqid(),
            'description' => 'Sac de 50 kg', 'price' => 5000, 'stock' => 10, 'status' => 'approved', 'is_active' => true,
        ]);
    }
}
