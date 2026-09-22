<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminProductController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\PublicProductVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug rapporté par l'utilisateur : la suppression d'un produit depuis
 * l'espace admin ne se répercutait ni sur l'accueil ni sur la base de
 * données.
 *
 * Cause : order_items.product_id porte une contrainte restrictOnDelete.
 * Dès qu'une commande contient plusieurs produits différents, seul l'un
 * d'eux est référencé par orders.product_id (cascadeOnDelete) ; les autres
 * ne sont référencés QUE via order_items, sans chemin de cascade. Supprimer
 * un tel produit levait donc une QueryException non interceptée dans
 * AdminProductController::destroy() : la requête échouait et le produit
 * restait bien présent en base (et donc sur l'accueil).
 */
class AdminProductDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeShopAndVendor(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique admin-delete',
            'slug' => 'boutique-admin-delete-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-ADMDEL-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);

        return [$vendor, $shop];
    }

    private function makeProduct($vendor, $shop, string $name): Product
    {
        $category = Category::create(['name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'status' => 'actif']);

        return Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_a_never_ordered_product_is_hard_deleted(): void
    {
        [$vendor, $shop] = $this->makeShopAndVendor();
        $product = $this->makeProduct($vendor, $shop, 'Produit jamais commandé');

        (new AdminProductController())->destroy($product);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_a_product_only_referenced_through_order_items_is_archived_instead_of_crashing(): void
    {
        [$vendor, $shop] = $this->makeShopAndVendor();
        $client = User::factory()->create(['role' => 'client']);

        // Produit A porté par orders.product_id (cascadeOnDelete), produit B
        // uniquement présent dans order_items (restrictOnDelete) : le cas
        // réel d'une commande multi-articles.
        $productA = $this->makeProduct($vendor, $shop, 'Produit A multi-article');
        $productB = $this->makeProduct($vendor, $shop, 'Produit B multi-article');

        $order = Order::create([
            'order_number' => 'CMD-' . uniqid(),
            'client_id' => $client->id,
            'product_id' => $productA->id,
            'vendor_id' => $vendor->id,
            'status' => 'completed',
            'total_amount' => 15000,
        ]);

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $productA->id, 'shop_id' => $vendor->id,
            'quantity' => 1, 'price' => 10000, 'subtotal' => 10000,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $productB->id, 'shop_id' => $vendor->id,
            'quantity' => 1, 'price' => 5000, 'subtotal' => 5000,
        ]);

        // Ne doit jamais lever de QueryException.
        (new AdminProductController())->destroy($productB);

        // Toujours en base (l'historique de commande est préservé)...
        $this->assertDatabaseHas('products', ['id' => $productB->id]);
        $productB->refresh();
        $this->assertNotNull($productB->archived_at);
        $this->assertFalse((bool) $productB->is_active);

        // ...mais absent de toute vitrine publique (accueil/catalogue).
        $visible = (new PublicProductVisibilityService())
            ->query()
            ->whereKey($productB->id)
            ->exists();
        $this->assertFalse($visible);

        // La commande et sa ligne d'origine ne sont jamais perdues.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $productB->id]);
    }
}
