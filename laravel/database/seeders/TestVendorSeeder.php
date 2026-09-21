<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductDeliveryZone;
use App\Models\Category;
use App\Models\AbidjanCommune;
use App\Models\SellerDeliveryProfile;
use App\Models\SellerDeliveryZone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestVendorSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'testvendeur01@gmail.com';
        $phone = '07 04 74 97 85';
        $rawPhone = '0704749785';
        $intlPhone = '+2250704749785';
        $password = '12345678';

        $this->command?->info("==================================================");
        $this->command?->info("CONFIGURATION DU VENDEUR DE TEST (Cocody Riviera 2)");
        $this->command?->info("==================================================");

        // 1. Créer ou mettre à jour l'utilisateur vendeur
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Vendeur',
                'last_name' => 'Test',
                'name' => 'Vendeur Test Riviera 2',
                'phone' => $phone,
                'whatsapp_phone' => $intlPhone,
                'whatsapp_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'role' => 'vendor',
                'status' => 'active',
                'city' => 'Abidjan',
                'account_type' => 'pro',
            ]
        );

        $this->command?->info("✓ Utilisateur créé/mis à jour : {$user->email} (ID: {$user->id})");

        // 2. Créer ou mettre à jour le modèle Vendor (legacy / compatibilité)
        if (class_exists(Vendor::class) && DB::getSchemaBuilder()->hasTable('vendors')) {
            Vendor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'shop_name' => 'Boutique Test Riviera 2',
                    'contact_email' => $email,
                    'description' => 'Boutique de test avec logistique propre à Cocody Riviera 2',
                    'is_active' => true,
                    'status' => 'approved',
                ]
            );
            $this->command?->info("✓ Modèle Vendor synchronisé");
        }

        // 3. Créer ou mettre à jour la boutique vendeur
        $cocodyCommune = null;
        if (class_exists(AbidjanCommune::class) && DB::getSchemaBuilder()->hasTable('abidjan_communes')) {
            $cocodyCommune = AbidjanCommune::where('slug', 'cocody')->orWhere('name', 'like', '%Cocody%')->first();
        }

        $shop = Shop::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Boutique Test Riviera 2',
                'slug' => 'boutique-test-riviera-2',
                'description' => 'Boutique de test pour validation des paiements Web & Mobile avec logistique vendeur propre (Cocody Riviera 2).',
                'seller_type' => 'entreprise',
                'company_name' => 'Test Vendeur Riviera 2 SARL',
                'city' => 'Abidjan',
                'commune' => 'Cocody',
                'commune_id' => $cocodyCommune?->id ?? 6,
                'district' => 'Riviera 2',
                'landmark' => 'Cocody Riviera 2, Abidjan',
                'address' => 'Cocody Riviera 2, Carrefour Sainte Famille',
                'identity_type' => 'cni',
                'identity_number' => 'CI-0704749785',
                'identity_country' => 'CI',
                'business_email' => $email,
                'whatsapp' => $intlPhone,
                'latitude' => 5.3470000,
                'longitude' => -3.9780000,
                'geo_status' => Shop::GEO_STATUS_VERIFIED,
                'geo_verified_at' => now(),
                'direct_payment' => true,
                'logistics_type' => 'seller',
                'payment_mode' => 'direct',
                'mm_operator' => 'orange',
                'mm_number' => $rawPhone,
                'mm_holder' => 'Vendeur Test',
                'status' => Shop::STATUS_APPROVED,
                'kyc_status' => Shop::KYC_VERIFIED,
                'logistics_status' => Shop::LOGISTICS_READY,
                'is_active' => true,
                'approved_at' => now(),
            ]
        );

        $this->command?->info("✓ Boutique créée/mise à jour : {$shop->name} (ID: {$shop->id})");

        // 4. Profil Logistique Vendeur (propre logistique)
        $vehicles = ['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t'];

        $profile = SellerDeliveryProfile::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'is_enabled' => true,
                'default_delay' => '24h',
                'max_weight_kg' => 10000.0,
                'max_volume_m3' => 100.0,
                'vehicle_types' => $vehicles,
                'capacity_description' => 'Flotte propre disponible pour livraison rapide dans tout Abidjan (Cocody Riviera 2).',
                'conditions' => 'Livraison directe par le vendeur à 1 FCFA pour les tests de paiement.',
                'status' => 'approved',
                'validated_at' => now(),
            ]
        );

        // 5. Zones de livraison pour TOUTES les communes d'Abidjan à 1 FCFA
        $communes = collect();
        if (class_exists(AbidjanCommune::class) && DB::getSchemaBuilder()->hasTable('abidjan_communes')) {
            $communes = AbidjanCommune::all();
        }

        if ($communes->isEmpty()) {
            $communeNames = [
                'Abobo', 'Adjamé', 'Attécoubé', 'Cocody', 'Koumassi',
                'Marcory', 'Plateau', 'Port-Bouët', 'Treichville', 'Yopougon',
                'Bingerville', 'Songon', 'Anyama'
            ];
            $communes = collect($communeNames)->map(fn ($name, $i) => (object)['id' => $i + 1, 'name' => $name]);
        }

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
                    'delivery_price' => 1.0, // 1 FCFA par commune
                    'estimated_delay' => '24h',
                    'max_weight_kg' => 10000.0,
                    'max_volume_m3' => 100.0,
                    'is_active' => true,
                ]);
                $zoneCount++;
            }
        }

        $this->command?->info("✓ {$zoneCount} zones de livraison configurées à 1 FCFA par commune.");

        // Valider la logistique de la boutique
        if (class_exists(\App\Services\SellerLogisticsValidator::class)) {
            app(\App\Services\SellerLogisticsValidator::class)->synchronizeShopStatus($shop);
        }

        // 6. Attribuer les produits de la base de données à cette boutique avec prix = 25 FCFA
        $productCount = Product::count();

        if ($productCount === 0) {
            $this->command?->info("Aucun produit trouvé dans la BDD. Création de produits de test...");
            $category = Category::first();
            if (! $category) {
                $category = Category::create([
                    'name' => 'Matériaux & Quincaillerie',
                    'slug' => 'materiaux-quincaillerie',
                    'is_active' => true,
                ]);
            }

            $sampleProducts = [
                ['name' => 'Ciment CPJ 42.5 - Sac de 50kg', 'price' => 25.0],
                ['name' => 'Fer à béton Haute Adhérence FeE500 Diamètre 10mm', 'price' => 25.0],
                ['name' => 'Peinture Acrylique Extérieure Blanc 20L', 'price' => 25.0],
                ['name' => 'Carrelage Grès Cérame 60x60 - Carton 1.44m2', 'price' => 25.0],
                ['name' => 'Tuyau PVC Pression Diamètre 110mm - Barre 4m', 'price' => 25.0],
            ];

            foreach ($sampleProducts as $sp) {
                Product::create([
                    'shop_id' => $shop->id,
                    'category_id' => $category->id,
                    'sku' => 'TEST-' . strtoupper(Str::random(6)),
                    'name' => $sp['name'],
                    'slug' => Str::slug($sp['name']) . '-' . Str::random(5),
                    'description' => 'Produit de test pour vérification du paiement à 25 FCFA.',
                    'short_description' => 'Produit de test Ovanie (25 FCFA)',
                    'price' => 25.0,
                    'promo_price' => null,
                    'weight' => 1,
                    'weight_kg' => 1,
                    'volume_m3' => 0.01,
                    'stock' => 999,
                    'availability_status' => 'in_stock',
                    'status' => 'approved',
                    'is_active' => true,
                    'delivery_mode' => 'seller',
                    'seller_delivery_delay' => '24h',
                    'pickup_commune' => 'Cocody',
                    'pickup_address' => 'Cocody Riviera 2, Carrefour Sainte Famille',
                    'product_state' => 'neuf',
                ]);
            }
            $productCount = Product::where('shop_id', $shop->id)->count();
        } else {
            // Mettre à jour tous les produits existants pour cette boutique à 25 FCFA
            Product::query()->update([
                'shop_id' => $shop->id,
                'price' => 25.0, // 25 FCFA
                'promo_price' => null,
                'stock' => 999,
                'availability_status' => 'in_stock',
                'status' => 'approved',
                'is_active' => true,
                'delivery_mode' => 'seller',
                'seller_delivery_delay' => '24h',
                'pickup_commune' => 'Cocody',
                'pickup_address' => 'Cocody Riviera 2, Carrefour Sainte Famille',
            ]);
        }

        $this->command?->info("✓ {$productCount} produits configurés à 25 FCFA et rattachés à la boutique.");
        $this->command?->newLine();
        $this->command?->info("==================================================");
        $this->command?->info("RÉCAPITULATIF VENDEUR TEST :");
        $this->command?->info("  • Email       : {$email}");
        $this->command?->info("  • Téléphone   : {$phone}");
        $this->command?->info("  • Mot de passe: {$password}");
        $this->command?->info("  • Boutique    : {$shop->name}");
        $this->command?->info("  • Localisation: Cocody Riviera 2");
        $this->command?->info("  • Logistique  : Propre vendeur (logistics_type = seller)");
        $this->command?->info("  • Prix produit: 25 FCFA");
        $this->command?->info("  • Livraison   : 1 FCFA par commune");
        $this->command?->info("==================================================");
    }
}
