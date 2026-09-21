<?php

/**
 * Script de déploiement du Vendeur Test pour tests de paiement en ligne (Mobile & Web)
 * 
 * Exécution sur le serveur :
 * php deploy_test_vendor.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\AbidjanCommune;
use App\Models\SellerDeliveryProfile;
use App\Models\SellerDeliveryZone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "=== DÉPLOIEMENT DU COMPTE VENDEUR DE TEST ===\n";

$email = 'testvendeur01@gmail.com';
$phone = '07 00 00 15 15';
$password = '12345678';

// 1. Utilisateur Vendeur
$user = User::updateOrCreate(
    ['email' => $email],
    [
        'first_name' => 'Vendeur',
        'last_name' => 'Test',
        'name' => 'Vendeur Test Cocody',
        'phone' => $phone,
        'whatsapp_phone' => '+2250700001515',
        'whatsapp_verified_at' => now(),
        'email_verified_at' => now(),
        'password' => Hash::make($password),
        'role' => 'vendor',
        'status' => 'active',
        'city' => 'Abidjan',
        'account_type' => 'pro',
    ]
);
echo "[OK] Utilisateur créé/mis à jour : {$user->email} (ID: {$user->id})\n";

// 2. Boutique Vendeur (Cocody Angré - Propre logistique)
$cocodyCommune = AbidjanCommune::where('slug', 'cocody')->orWhere('name', 'like', '%Cocody%')->first();

$shop = Shop::updateOrCreate(
    ['user_id' => $user->id],
    [
        'name' => 'Boutique Test Vendeur Angré',
        'slug' => 'boutique-test-vendeur-angre',
        'description' => 'Boutique de test pour validation des paiements Web & Mobile avec logistique vendeur propre.',
        'seller_type' => 'entreprise',
        'company_name' => 'Test Vendeur SARL',
        'city' => 'Abidjan',
        'commune' => 'Cocody',
        'commune_id' => $cocodyCommune?->id ?? 6,
        'district' => 'Angré',
        'landmark' => 'Cocody Angré, Boulevard Latrille, 8ème Tranche',
        'address' => 'Cocody Angré, Carrefour Duncan',
        'identity_type' => 'cni',
        'identity_number' => 'CI-0700001515',
        'identity_country' => 'CI',
        'business_email' => $email,
        'whatsapp' => '+2250700001515',
        'latitude' => 5.3582000,
        'longitude' => -3.9806000,
        'geo_status' => Shop::GEO_STATUS_VERIFIED,
        'geo_verified_at' => now(),
        'direct_payment' => true,
        'logistics_type' => 'seller',
        'payment_mode' => 'direct',
        'mm_operator' => 'orange',
        'mm_number' => '0700001515',
        'mm_holder' => 'Vendeur Test',
        'status' => Shop::STATUS_APPROVED,
        'kyc_status' => Shop::KYC_VERIFIED,
        'logistics_status' => Shop::LOGISTICS_READY,
        'is_active' => true,
        'approved_at' => now(),
    ]
);
echo "[OK] Boutique créée/mise à jour : {$shop->name} (ID: {$shop->id})\n";

// 3. Profil Logistique Vendeur
$vehicles = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

$profile = SellerDeliveryProfile::updateOrCreate(
    ['shop_id' => $shop->id],
    [
        'is_enabled' => true,
        'default_delay' => '24h',
        'max_weight_kg' => 10000.0,
        'max_volume_m3' => 100.0,
        'vehicle_types' => $vehicles,
        'capacity_description' => 'Flotte propre disponible pour livraison rapide dans tout Abidjan.',
        'conditions' => 'Livraison directe par le vendeur à 1 FCFA pour les tests de paiement.',
        'status' => 'approved',
        'validated_at' => now(),
    ]
);

// 4. Tarifs de livraison par commune (1 FCFA pour chaque commune)
$communes = AbidjanCommune::all();
SellerDeliveryZone::where('seller_delivery_profile_id', $profile->id)->delete();

$zoneCount = 0;
foreach ($communes as $commune) {
    foreach ($vehicles as $vehicle) {
        SellerDeliveryZone::create([
            'shop_id' => $shop->id,
            'seller_delivery_profile_id' => $profile->id,
            'city' => 'Abidjan',
            'commune' => $commune->name,
            'commune_id' => $commune->id,
            'district' => 'Toute la commune',
            'coverage_type' => 'all',
            'vehicle_code' => $vehicle,
            'delivery_price' => 1.0, // 1 FCFA
            'estimated_delay' => '24h',
            'max_weight_kg' => 10000.0,
            'max_volume_m3' => 100.0,
            'is_active' => true,
        ]);
        $zoneCount++;
    }
}
echo "[OK] {$zoneCount} zones de livraison configurées à 1 FCFA.\n";

app(\App\Services\SellerLogisticsValidator::class)->synchronizeShopStatus($shop);

// 5. Attribuer des produits de test à 2 FCFA
$sourceProducts = Product::with('images')
    ->where('shop_id', '!=', $shop->id)
    ->where('is_active', true)
    ->take(15)
    ->get();

if ($sourceProducts->isEmpty()) {
    $sourceProducts = Product::with('images')->take(15)->get();
}

$productCount = 0;
foreach ($sourceProducts as $source) {
    $testSlug = Str::slug($source->name) . '-test-vendeur-' . $source->id;

    $product = Product::updateOrCreate(
        [
            'shop_id' => $shop->id,
            'slug' => $testSlug,
        ],
        [
            'category_id' => $source->category_id,
            'sku' => 'TEST-' . strtoupper(Str::random(6)),
            'name' => '[TEST 2F] ' . $source->name,
            'description' => $source->description ?: 'Produit de test pour vérification du processus de commande et paiement.',
            'short_description' => $source->short_description ?: 'Produit de test Ovanie',
            'technical_details' => $source->technical_details,
            'product_attributes' => $source->product_attributes,
            'price' => 2.0, // 2 FCFA
            'promo_price' => null,
            'weight' => $source->weight ?: 1,
            'weight_kg' => $source->weight_kg ?: 1,
            'volume_m3' => $source->volume_m3 ?: 0.01,
            'stock' => 999,
            'availability_status' => 'in_stock',
            'status' => 'approved',
            'is_active' => true,
            'delivery_mode' => 'seller',
            'seller_delivery_delay' => '24h',
            'pickup_commune' => 'Cocody',
            'pickup_address' => 'Cocody Angré, Carrefour Duncan',
            'product_state' => 'neuf',
        ]
    );

    if ($source->images->isNotEmpty()) {
        ProductImage::where('product_id', $product->id)->delete();
        foreach ($source->images as $img) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $img->path,
                'original_path' => $img->original_path,
                'card_path' => $img->card_path,
                'thumb_path' => $img->thumb_path,
                'original_width' => $img->original_width,
                'original_height' => $img->original_height,
                'is_main' => $img->is_main,
                'is_primary' => $img->is_primary,
                'sort_order' => $img->sort_order,
            ]);
        }
    }

    $productCount++;
}

echo "[OK] {$productCount} produits de test configurés à 2 FCFA.\n";
echo "=== INITIALISATION DU VENDEUR TERMINÉE AVEC SUCCÈS ===\n";
