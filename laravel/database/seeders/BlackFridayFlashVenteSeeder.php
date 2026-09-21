<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * BlackFridayFlashVenteSeeder
 *
 * 1. Ajoute des produits "black friday" et "vente flash" à tous les vendeurs existants.
 * 2. Crée le vendeur testvendeur11@gmail.com (07 00 00 00 15) avec sa propre logistique,
 *    boutique à Cocody Rivera 2, profil et zones de livraison complets.
 * 3. Attribue des produits (copies des produits des autres vendeurs) au nouveau vendeur.
 * 4. Associe de vraies images existantes à tous les produits.
 * 5. S'assure que TOUS les vendeurs ont des produits actifs dans les deux sections.
 * 6. Vide le cache pour mise à jour immédiate sur Web et Mobile.
 *
 * Identifiants du nouveau vendeur :
 *   Email        : testvendeur11@gmail.com
 *   Tél          : +2250700000015
 *   Mot de passe : Vendeur@2024!
 */
class BlackFridayFlashVenteSeeder extends Seeder
{
    private string $now;

    // ─── Communes d'Abidjan avec tarifs livraison vendeur ───────────────────
    private array $tariffs = [
        ['commune' => 'Cocody',      'price' => 3000, 'delay' => '24h'],
        ['commune' => 'Marcory',     'price' => 2000, 'delay' => '24h'],
        ['commune' => 'Plateau',     'price' => 2000, 'delay' => '24h'],
        ['commune' => 'Treichville', 'price' => 2000, 'delay' => '24h'],
        ['commune' => 'Adjamé',      'price' => 2500, 'delay' => '24h'],
        ['commune' => 'Koumassi',    'price' => 2500, 'delay' => '24h'],
        ['commune' => 'Yopougon',    'price' => 3500, 'delay' => '24h-48h'],
        ['commune' => 'Abobo',       'price' => 3500, 'delay' => '24h-48h'],
        ['commune' => 'Port-Bouët',  'price' => 3000, 'delay' => '24h'],
        ['commune' => 'Attécoubé',   'price' => 3000, 'delay' => '24h'],
        ['commune' => 'Bingerville', 'price' => 4000, 'delay' => '48h'],
        ['commune' => 'Songon',      'price' => 5500, 'delay' => '48h'],
        ['commune' => 'Anyama',      'price' => 5000, 'delay' => '48h'],
    ];

    // ─── Produits Black Friday à ajouter à chaque vendeur ───────────────────
    private array $blackFridayProducts = [
        ['name' => 'Lot Ciment Black Friday 10 sacs',          'price' => 42000, 'promo_price' => 35000, 'weight' => 500,  'unit' => 'lot',    'stock' => 50,  'description' => 'Offre spéciale Black Friday : 10 sacs de ciment CEM II à prix réduit. Qualité garantie.'],
        ['name' => 'Pack Carrelage BF 60x60 (5 boîtes)',       'price' => 46000, 'promo_price' => 38000, 'weight' => 125,  'unit' => 'pack',   'stock' => 30,  'description' => 'Black Friday : 5 boîtes de carrelage sol 60x60 cm, finition mate anti-dérapante.'],
        ['name' => 'Fer à béton HA12 Black Friday (10 barres)', 'price' => 63000, 'promo_price' => 52000, 'weight' => 100, 'unit' => 'lot',    'stock' => 40,  'description' => 'Lot Black Friday de 10 barres de fer HA12 – idéal pour dalles et fondations.'],
        ['name' => 'Peinture Murale BF 4x20L',                 'price' => 95000, 'promo_price' => 78000, 'weight' => 100,  'unit' => 'pack',   'stock' => 20,  'description' => 'Black Friday : pack 4 bidons de peinture murale blanche 20L, lessivable.'],
    ];

    // ─── Produits Vente Flash à ajouter à chaque vendeur ────────────────────
    private array $venteFlashProducts = [
        ['name' => 'Ciment CEM I 42.5 – Flash 24h',           'price' => 5200,  'promo_price' => 3900,  'weight' => 50,   'unit' => 'sac',    'stock' => 100, 'description' => 'Vente Flash 24h : sac de ciment CEM I 42.5 haute résistance – stock limité !'],
        ['name' => 'Robinet Chromé Flash Deal',                'price' => 25000, 'promo_price' => 17500, 'weight' => 1.2,  'unit' => 'pièce',  'stock' => 40,  'description' => 'Flash Deal : robinet mitigeur lavabo chromé de qualité professionnelle.'],
        ['name' => 'Lambris PVC Flash – 50 m²',               'price' => 31000, 'promo_price' => 22000, 'weight' => 200,  'unit' => 'pack',   'stock' => 20,  'description' => 'Vente Flash : 50 m² de lambris PVC blanc, pose facile sur tout support.'],
        ['name' => 'Tableau Électrique 12 mod. Flash',        'price' => 19500, 'promo_price' => 13900, 'weight' => 3,    'unit' => 'pièce',  'stock' => 35,  'description' => 'Flash sale : tableau électrique 12 modules avec protection différentielle incluse.'],
    ];

    public function run(): void
    {
        $this->now = now()->toDateTimeString();

        $this->command?->info('');
        $this->command?->info('🎉 BlackFridayFlashVenteSeeder – démarrage...');

        $commercialId = DB::table('users')->where('role', 'commercial')->value('id');
        $categoryId   = DB::table('categories')->value('id');

        // Récupérer les templates d'images réelles existantes
        $templateImages = $this->loadTemplateImages();

        // ── 1. Crée / Met à jour le vendeur testvendeur11 ────────────────────
        $this->command?->info('👤 Configuration du vendeur testvendeur11@gmail.com...');

        $existingUser = DB::table('users')->where('email', 'testvendeur11@gmail.com')->first();
        if ($existingUser) {
            $newUserId = $existingUser->id;
        } else {
            $newUserId = $this->insertUser([
                'first_name'               => 'Test',
                'last_name'                => 'VENDEUR11',
                'email'                    => 'testvendeur11@gmail.com',
                'password'                 => Hash::make('Vendeur@2024!'),
                'role'                     => 'client',
                'status'                   => 'active',
                'phone'                    => '+2250700000015',
                'account_type'             => 'professionnel',
                'created_by_commercial_id' => $commercialId,
            ]);
        }

        $existingShop = DB::table('shops')->where('user_id', $newUserId)->first();
        if ($existingShop) {
            $newShopId = $existingShop->id;
            DB::table('shops')->where('id', $newShopId)->update([
                'status'           => 'approved',
                'is_active'        => 1,
                'logistics_status' => 'ready',
                'logistics_type'   => 'seller',
                'address'          => 'Rivera 2, Cocody, Abidjan',
                'district'         => 'Abidjan',
                'commune'          => 'Cocody',
                'city'             => 'Abidjan',
                'latitude'         => 5.3582,
                'longitude'        => -3.9806,
                'geo_status'       => 'verified',
                'updated_at'       => $this->now,
            ]);
        } else {
            $newShopId = $this->insert('shops', [
                'user_id'                  => $newUserId,
                'name'                     => 'TESTVENDEUR11 MATÉRIAUX',
                'slug'                     => 'testvendeur11-materiaux-'.Str::lower(Str::random(4)),
                'seller_type'              => 'particulier',
                'identity_type'            => 'cni',
                'identity_number'          => 'CI-'.rand(10000000, 99999999),
                'description'              => 'Boutique de matériaux de construction – Cocody Rivera 2. Livraison assurée par notre propre équipe logistique.',
                'status'                   => 'approved',
                'is_active'                => 1,
                'logistics_status'         => 'ready',
                'logistics_type'           => 'seller',
                'district'                 => 'Abidjan',
                'address'                  => 'Rivera 2, Cocody, Abidjan',
                'landmark'                 => 'Rivera 2',
                'geo_status'               => 'verified',
                'commune'                  => 'Cocody',
                'city'                     => 'Abidjan',
                'latitude'                 => 5.3582,
                'longitude'                => -3.9806,
                'whatsapp'                 => '+2250700000015',
                'business_email'           => 'testvendeur11@gmail.com',
                'payment_mode'             => 'post_delivery',
                'approved_at'              => $this->now,
                'created_by_commercial_id' => $commercialId,
                'managed_by_commercial_id' => $commercialId,
            ]);
        }

        // ── 2. Configure le profil et les zones de livraison vendeur ──────────
        $this->command?->info('🚚 Configuration de la logistique propre pour TESTVENDEUR11...');

        $profile = DB::table('seller_delivery_profiles')->where('shop_id', $newShopId)->first();
        if (! $profile) {
            $profileId = $this->insert('seller_delivery_profiles', [
                'shop_id'       => $newShopId,
                'is_enabled'    => 1,
                'status'        => 'ready',
                'default_delay' => '24h',
                'max_weight_kg' => 10000.0,
                'max_volume_m3' => 50.0,
                'conditions'    => 'Livraison directe sur chantier ou domicile. Déchargement inclus pour les petits colis.',
                'vehicle_types' => json_encode(['moto', 'tricycle', 'pickup', 'camion_3t', 'camion_10t']),
            ]);
        } else {
            $profileId = $profile->id;
            DB::table('seller_delivery_profiles')->where('id', $profileId)->update([
                'is_enabled'    => 1,
                'status'        => 'ready',
                'default_delay' => '24h',
                'max_weight_kg' => 10000.0,
                'max_volume_m3' => 50.0,
                'conditions'    => 'Livraison directe sur chantier ou domicile. Déchargement inclus pour les petits colis.',
                'updated_at'    => $this->now,
            ]);
        }

        foreach ($this->tariffs as $t) {
            $zoneExists = DB::table('seller_delivery_zones')
                ->where('shop_id', $newShopId)
                ->where('commune', $t['commune'])
                ->exists();

            if (! $zoneExists) {
                $this->insert('seller_delivery_zones', [
                    'shop_id'                    => $newShopId,
                    'seller_delivery_profile_id' => $profileId,
                    'city'                       => 'Abidjan',
                    'commune'                    => $t['commune'],
                    'delivery_price'             => $t['price'],
                    'estimated_delay'            => $t['delay'],
                    'max_weight_kg'              => 5000.0,
                    'max_volume_m3'              => 20.0,
                    'is_active'                  => 1,
                ]);
            }
        }

        // ── 3. Attribution des produits des autres vendeurs au nouveau vendeur ──
        $this->command?->info('🔄 Attribution des produits existants au nouveau vendeur...');

        $existingProducts = DB::table('products')
            ->where('status', 'approved')
            ->where('is_active', 1)
            ->where('shop_id', '!=', $newShopId)
            ->get();

        $alreadyCopiedNames = DB::table('products')
            ->where('shop_id', $newShopId)
            ->pluck('name')
            ->toArray();

        $addedCount = 0;
        foreach ($existingProducts as $product) {
            if (in_array($product->name, $alreadyCopiedNames, true)) {
                continue;
            }

            $newPid = $this->insert('products', [
                'shop_id'                  => $newShopId,
                'vendor_id'                => $newUserId,
                'category_id'              => $product->category_id ?? $categoryId,
                'name'                     => $product->name,
                'slug'                     => Str::slug($product->name).'-tv11-'.Str::lower(Str::random(4)),
                'description'              => $product->description ?? $product->name.' — Disponible chez TestVendeur11, Cocody Rivera 2.',
                'price'                    => $product->price,
                'promo_price'              => $product->promo_price ?? null,
                'weight'                   => $product->weight ?? 0,
                'unit'                     => $product->unit ?? 'pièce',
                'stock'                    => max(10, (int)($product->stock ?? 10)),
                'status'                   => 'approved',
                'is_active'                => 1,
                'views'                    => rand(10, 200),
                'sales'                    => rand(1, 30),
                'sale_type'                => $product->sale_type ?? null,
                'created_by_commercial_id' => $commercialId,
            ]);

            // Copie de la vraie image du produit source
            $sourceImg = DB::table('product_images')->where('product_id', $product->id)->first();
            $this->assignProductImage($newPid, $product->name, $templateImages, $sourceImg);

            $addedCount++;
        }

        // ── 4. Ajoute / Rafraîchit les produits BF et VF pour TOUS les vendeurs ─
        $this->command?->info('📦 Ajout des offres Black Friday & Vente Flash à toutes les boutiques...');

        $allShops = DB::table('shops')
            ->where('status', 'approved')
            ->where('is_active', 1)
            ->get();

        $flashStart = now()->toDateTimeString();
        $flashEnd   = now()->addDays(30)->toDateTimeString();
        $bfStart    = now()->toDateTimeString();
        $bfEnd      = now()->addDays(60)->toDateTimeString();

        foreach ($allShops as $shop) {
            DB::table('products')
                ->where('shop_id', $shop->id)
                ->where('sale_type', 'vente flash')
                ->update([
                    'flash_start_at' => $flashStart,
                    'flash_end'      => $flashEnd,
                    'status'         => 'approved',
                    'is_active'      => 1,
                ]);

            DB::table('products')
                ->where('shop_id', $shop->id)
                ->where('sale_type', 'black friday')
                ->update([
                    'bf_start'  => $bfStart,
                    'bf_end'    => $bfEnd,
                    'status'    => 'approved',
                    'is_active' => 1,
                ]);

            // BF
            foreach ($this->blackFridayProducts as $pd) {
                $exists = DB::table('products')
                    ->where('shop_id', $shop->id)
                    ->where('sale_type', 'black friday')
                    ->where('name', $pd['name'])
                    ->first();

                if (! $exists) {
                    $pid = $this->insert('products', [
                        'shop_id'                  => $shop->id,
                        'vendor_id'                => $shop->user_id,
                        'category_id'              => $categoryId,
                        'name'                     => $pd['name'],
                        'slug'                     => Str::slug($pd['name']).'-bf-'.Str::lower(Str::random(4)),
                        'description'              => $pd['description'],
                        'price'                    => $pd['price'],
                        'promo_price'              => $pd['promo_price'],
                        'weight'                   => $pd['weight'],
                        'unit'                     => $pd['unit'],
                        'stock'                    => $pd['stock'],
                        'status'                   => 'approved',
                        'is_active'                => 1,
                        'sale_type'                => 'black friday',
                        'bf_start'                 => $bfStart,
                        'bf_end'                   => $bfEnd,
                        'views'                    => rand(100, 1500),
                        'sales'                    => rand(10, 200),
                        'created_by_commercial_id' => $commercialId,
                    ]);

                    $this->assignProductImage($pid, $pd['name'], $templateImages);
                } else {
                    $this->ensureProductHasValidImage($exists->id, $pd['name'], $templateImages);
                }
            }

            // VF
            foreach ($this->venteFlashProducts as $pd) {
                $exists = DB::table('products')
                    ->where('shop_id', $shop->id)
                    ->where('sale_type', 'vente flash')
                    ->where('name', $pd['name'])
                    ->first();

                if (! $exists) {
                    $pid = $this->insert('products', [
                        'shop_id'                  => $shop->id,
                        'vendor_id'                => $shop->user_id,
                        'category_id'              => $categoryId,
                        'name'                     => $pd['name'],
                        'slug'                     => Str::slug($pd['name']).'-vf-'.Str::lower(Str::random(4)),
                        'description'              => $pd['description'],
                        'price'                    => $pd['price'],
                        'promo_price'              => $pd['promo_price'],
                        'weight'                   => $pd['weight'],
                        'unit'                     => $pd['unit'],
                        'stock'                    => $pd['stock'],
                        'status'                   => 'approved',
                        'is_active'                => 1,
                        'sale_type'                => 'vente flash',
                        'flash_start_at'           => $flashStart,
                        'flash_end'                => $flashEnd,
                        'views'                    => rand(200, 2000),
                        'sales'                    => rand(20, 300),
                        'created_by_commercial_id' => $commercialId,
                    ]);

                    $this->assignProductImage($pid, $pd['name'], $templateImages);
                } else {
                    $this->ensureProductHasValidImage($exists->id, $pd['name'], $templateImages);
                }
            }
        }

        // ── 5. Vider le cache pour application immédiate ──────────────────────
        Cache::flush();
        $this->command?->info('🧹 Cache vidé avec succès.');
        $this->command?->info('🖼️ Toutes les images de produits sont désormais valides et visibles.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTION DES IMAGES
    // ─────────────────────────────────────────────────────────────────────────
    private function loadTemplateImages(): array
    {
        $templateImages = [];
        $originalProducts = DB::table('products')->whereBetween('id', [2, 22])->get();

        foreach ($originalProducts as $op) {
            $img = DB::table('product_images')->where('product_id', $op->id)->first();
            if ($img && $img->path && file_exists(public_path('storage/' . ltrim($img->path, '/')))) {
                $templateImages[$op->name] = (array) $img;
            }
        }
        return $templateImages;
    }

    private function findMatchingTemplate(string $name, array $templateImages): ?array
    {
        $lower = mb_strtolower($name);

        if (str_contains($lower, 'ciment')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'ciment')) return $img;
        }
        if (str_contains($lower, 'carrelage')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'carrelage')) return $img;
        }
        if (str_contains($lower, 'peinture')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'peinture')) return $img;
        }
        if (str_contains($lower, 'robinet') || str_contains($lower, 'mitigeur')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'robinet')) return $img;
        }
        if (str_contains($lower, 'tableau') || str_contains($lower, 'disjoncteur') || str_contains($lower, 'electrique') || str_contains($lower, 'cable') || str_contains($lower, 'prise')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'tableau') || str_contains(mb_strtolower($k), 'disjoncteur') || str_contains(mb_strtolower($k), 'prise')) return $img;
        }
        if (str_contains($lower, 'fer') || str_contains($lower, 'brique') || str_contains($lower, 'gravier') || str_contains($lower, 'sable')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'brique') || str_contains(mb_strtolower($k), 'gravier') || str_contains(mb_strtolower($k), 'sable')) return $img;
        }
        if (str_contains($lower, 'tuyau') || str_contains($lower, 'pvc') || str_contains($lower, 'lambris')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'tuyau')) return $img;
        }
        if (str_contains($lower, 'solaire') || str_contains($lower, 'batterie') || str_contains($lower, 'panneau') || str_contains($lower, 'projecteur')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'solaire') || str_contains(mb_strtolower($k), 'panneau')) return $img;
        }
        if (str_contains($lower, 'brouette') || str_contains($lower, 'pelle')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'brouette') || str_contains(mb_strtolower($k), 'pelle')) return $img;
        }
        if (str_contains($lower, 'casque') || str_contains($lower, 'gilet') || str_contains($lower, 'chaussure')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'casque') || str_contains(mb_strtolower($k), 'gilet') || str_contains(mb_strtolower($k), 'chaussure')) return $img;
        }
        if (str_contains($lower, 'wc') || str_contains($lower, 'lavabo')) {
            foreach ($templateImages as $k => $img) if (str_contains(mb_strtolower($k), 'wc') || str_contains(mb_strtolower($k), 'lavabo')) return $img;
        }

        return reset($templateImages) ?: null;
    }

    private function assignProductImage(int $productId, string $name, array $templateImages, ?object $sourceImg = null): void
    {
        if ($sourceImg && !empty($sourceImg->path) && file_exists(public_path('storage/' . ltrim($sourceImg->path, '/')))) {
            $imgData = (array) $sourceImg;
        } else {
            $imgData = $this->findMatchingTemplate($name, $templateImages);
        }

        if ($imgData) {
            unset($imgData['id'], $imgData['created_at'], $imgData['updated_at']);
            $imgData['product_id'] = $productId;
            $imgData['is_primary'] = 1;
            $imgData['sort_order'] = 0;

            $this->insert('product_images', $imgData);
        }
    }

    private function ensureProductHasValidImage(int $productId, string $name, array $templateImages): void
    {
        $img = DB::table('product_images')->where('product_id', $productId)->first();
        $needsFix = false;

        if (! $img || ! $img->path) {
            $needsFix = true;
        } else {
            $fullPath = public_path('storage/' . ltrim($img->path, '/'));
            if (! file_exists($fullPath) || is_dir($fullPath) || str_contains($img->path, 'placeholder')) {
                $needsFix = true;
            }
        }

        if ($needsFix) {
            $tpl = $this->findMatchingTemplate($name, $templateImages);
            if ($tpl) {
                unset($tpl['id'], $tpl['created_at'], $tpl['updated_at']);
                $tpl['product_id'] = $productId;
                $tpl['is_primary'] = 1;
                $tpl['sort_order'] = 0;

                if ($img) {
                    DB::table('product_images')->where('id', $img->id)->update($tpl);
                } else {
                    DB::table('product_images')->insert($tpl);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function insertUser(array $data): int
    {
        $base = [
            'name'              => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
            'email_verified_at' => $this->now,
            'remember_token'    => Str::random(10),
            'created_at'        => $this->now,
            'updated_at'        => $this->now,
        ];
        DB::table('users')->insert(array_merge($base, $data));
        return DB::getPdo()->lastInsertId();
    }

    private function cols(string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $cols = DB::select("PRAGMA table_info(\"$table\")");
            $cache[$table] = array_column($cols, 'name');
        } else {
            $dbName = DB::getDatabaseName();
            $cols = DB::select(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$dbName, $table]
            );
            $cache[$table] = array_column($cols, 'COLUMN_NAME');
        }

        return $cache[$table];
    }

    private function safe(string $table, array $data): array
    {
        $cols = $this->cols($table);
        return array_filter($data, fn($k) => in_array($k, $cols, true), ARRAY_FILTER_USE_KEY);
    }

    private function insert(string $table, array $data): int
    {
        $row = $this->safe($table, array_merge(['created_at' => $this->now, 'updated_at' => $this->now], $data));
        DB::table($table)->insert($row);
        return DB::getPdo()->lastInsertId();
    }
}
