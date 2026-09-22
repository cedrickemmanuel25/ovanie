<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le formulaire "Ajouter un produit" (vendeur web) rendait plusieurs champs
 * obligatoires côté interface alors que le serveur ne les exigeait pas
 * (marque, type de produit, état du produit, description courte, usage
 * recommandé, détails techniques) : un vendeur peu à l'aise avec ce
 * vocabulaire technique se retrouvait bloqué pour rien. Ce test garantit que
 * la page se rend toujours correctement et que ces champs ne bloquent plus
 * la progression, sans avoir supprimé les données déjà saisies par les
 * vendeurs qui les remplissent.
 */
class VendorProductWizardSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendor(): User
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        Shop::query()->create([
            'user_id' => $vendor->id, 'name' => 'Boutique wizard',
            'slug' => 'boutique-wizard-' . uniqid(), 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'commune' => 'Cocody', 'address' => 'Adresse interne',
            'district' => 'Abidjan', 'landmark' => 'Pharmacie du carrefour',
            'latitude' => 5.35, 'longitude' => -4.01, 'geo_status' => 'verified',
            'logistics_status' => 'ready', 'logistics_type' => 'ovanie',
            'identity_type' => 'cni', 'identity_number' => 'CI-' . uniqid(),
            'status' => 'approved', 'is_active' => true,
        ]);

        return $vendor;
    }

    public function test_the_create_product_page_still_renders_correctly(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($vendor)
            ->get(route('vendor.add_product', ['type' => 'single']))
            ->assertOk();
    }

    public function test_non_essential_fields_no_longer_block_step_progression(): void
    {
        $vendor = $this->makeVendor();

        $response = $this->actingAs($vendor)->get(route('vendor.add_product', ['type' => 'single']));
        $response->assertOk();

        $html = $response->getContent();

        foreach (['pwBrand"', 'pwProductType"', 'pwState"', 'pwShortDescription"', 'pwUsageArea"'] as $needle) {
            $this->assertStringContainsString($needle, $html, "Le champ {$needle} doit rester dans le formulaire.");
        }

        // Ces champs ne doivent plus porter data-required-step : le serveur
        // ne les exige pas (VendorProductController::validateProductRequest()),
        // donc l'interface ne doit plus bloquer le vendeur dessus.
        foreach (['pwBrand', 'pwProductType', 'pwState', 'pwShortDescription', 'pwUsageArea', 'pwTechnicalDetails'] as $fieldId) {
            if (! preg_match('/id="' . $fieldId . '"([^>]*)>/', $html, $matches)) {
                $this->fail("Champ {$fieldId} introuvable dans le HTML rendu.");
            }

            $this->assertStringNotContainsString(
                'data-required-step',
                $matches[1],
                "Le champ {$fieldId} ne doit plus être obligatoire côté interface."
            );
        }

        // Le doublon "Marque" en lecture seule (étape 3) a été retiré.
        $this->assertStringNotContainsString('pwBrandReadonly', $html);
    }

    /**
     * VendorProductController::validateProductRequest() n'a pas été modifié
     * par cette simplification (ces champs étaient déjà `nullable` côté
     * serveur) : ce test fige ce contrat pour que personne ne les rende
     * obligatoires côté serveur sans mettre à jour le formulaire en
     * conséquence.
     */
    public function test_the_simplified_fields_remain_optional_at_the_validation_layer(): void
    {
        $controller = new \App\Http\Controllers\VendorProductController();
        $method = new \ReflectionMethod($controller, 'validateProductRequest');
        $method->setAccessible(true);

        $request = \Illuminate\Http\Request::create('/vendeur/products', 'POST', [
            'name' => 'Sac de ciment 50kg',
            'category_id' => 1,
            'sale_type' => 'Vente normale',
            'price' => 5000,
            'stock' => 10,
            'description' => 'Ciment de qualité pour tous travaux.',
            'unit' => 'sac',
            'min_order_quantity' => 1,
            'weight_kg' => 50,
            'length_cm' => 60,
            'width_cm' => 40,
            'height_cm' => 10,
            'fragile' => '0',
            'requires_unloading' => '0',
            // Intentionnellement omis : brand, material_grade, product_state,
            // short_description, usage_area, technical_details.
        ]);
        $request->setUserResolver(fn () => \App\Models\User::factory()->make(['role' => 'vendor']));

        try {
            $method->invoke($controller, $request, true);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $failed = array_keys($exception->errors());
            foreach (['brand', 'material_grade', 'product_state', 'short_description', 'usage_area', 'technical_details'] as $field) {
                $this->assertNotContains($field, $failed, "{$field} ne doit pas être obligatoire côté serveur.");
            }

            return;
        }

        $this->assertTrue(true);
    }
}
