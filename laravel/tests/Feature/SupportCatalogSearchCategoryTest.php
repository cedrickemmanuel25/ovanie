<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\SupportAi\SupportCatalogSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug rapporté par l'utilisateur : N'Nan affirmait qu'un produit "n'existe
 * pas sur la plateforme" alors qu'il était bien présent en base. Cause :
 * la recherche catalogue de l'IA (SupportCatalogSearchService) ne
 * cherchait que dans le nom/la marque/la description du produit lui-même,
 * jamais dans le nom de sa catégorie. Une question formulée par catégorie
 * ("EPI", "équipements de protection"...) ne remontait donc aucun produit
 * si ces mots n'apparaissaient pas littéralement dans son nom.
 */
class SupportCatalogSearchCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $productName, string $categoryName, ?string $parentCategoryName = null): Product
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create([
            'user_id' => $vendor->id, 'name' => 'Boutique catalogue IA',
            'slug' => 'boutique-catalogue-ia-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'district' => 'Abidjan',
            'address' => 'Cocody', 'latitude' => 5.35, 'longitude' => -4.01,
            'identity_type' => 'cni', 'identity_number' => 'CI-CATIA-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
            'logistics_type' => 'ovanie', 'logistics_status' => 'ready',
        ]);

        $parent = $parentCategoryName ? Category::create([
            'name' => $parentCategoryName, 'slug' => \Illuminate\Support\Str::slug($parentCategoryName) . '-' . uniqid(), 'status' => 'actif',
        ]) : null;

        $category = Category::create([
            'name' => $categoryName, 'slug' => \Illuminate\Support\Str::slug($categoryName) . '-' . uniqid(),
            'parent_id' => $parent?->id, 'status' => 'actif',
        ]);

        return Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => $productName, 'slug' => \Illuminate\Support\Str::slug($productName) . '-' . uniqid(),
            'description' => 'x', 'price' => 10000, 'stock' => 10,
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_a_query_by_category_name_finds_a_product_whose_own_name_never_mentions_it(): void
    {
        $product = $this->makeProduct('Casque de chantier réglable', 'Équipements de protection');

        $result = app(SupportCatalogSearchService::class)->search('avez-vous des équipements de protection');

        $this->assertContains($product->name, array_column($result['products'], 'name'));
    }

    public function test_a_query_by_parent_category_name_also_finds_the_product(): void
    {
        $product = $this->makeProduct('Gilet haute visibilité', 'EPI chantier', 'Sécurité');

        $result = app(SupportCatalogSearchService::class)->search('je cherche dans la catégorie sécurité');

        $this->assertContains($product->name, array_column($result['products'], 'name'));
    }

    public function test_a_query_matching_only_the_product_name_still_works(): void
    {
        $product = $this->makeProduct('Ciment CPJ 42.5', 'Matériaux gros œuvre');

        $result = app(SupportCatalogSearchService::class)->search('je cherche du ciment');

        $this->assertContains($product->name, array_column($result['products'], 'name'));
    }
}
