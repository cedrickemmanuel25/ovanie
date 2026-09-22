<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CompleteDemoSeeder
 *
 * Peuple TOUS les espaces de la plateforme OVANIE avec des données réalistes.
 * Utilise DB::table()->insert() pour contourner les contraintes $fillable.
 *
 * IDENTIFIANTS DE DÉMONSTRATION :
 * ─────────────────────────────────────────────────────
 * URL staff  : /administration/login
 * URL client : /login
 *
 * 👑 ADMIN      : admin@ovanie.com           / Admin@2024!
 *                 directrice@ovanie.com      / Admin@2024!
 * 💼 COMMERCIAL : commercial1@ovanie.com     / Commercial@2024!
 *                 commercial2@ovanie.com     / Commercial@2024!
 *                 commercial3@ovanie.com     / Commercial@2024!
 * 🚚 LOGISTIQUE : logistique1@ovanie.com     / Logistique@2024!
 *                 logistique2@ovanie.com     / Logistique@2024!
 *                 logistique3@ovanie.com     / Logistique@2024!
 * 🎧 SUPPORT    : support1@ovanie.com        / Support@2024!
 *                 support2@ovanie.com        / Support@2024!
 *                 support3@ovanie.com        / Support@2024!
 * 🏪 VENDEUR    : vendeur1@ovanie.com        / Vendeur@2024!
 *                 vendeur2@ovanie.com        / Vendeur@2024!
 *                 vendeur3@ovanie.com        / Vendeur@2024!
 *                 vendeur4@ovanie.com        / Vendeur@2024!
 * 👥 CLIENT     : client1@ovanie.com         / Client@2024!
 *                 ...jusqu'à client10@ovanie.com
 */
class CompleteDemoSeeder extends Seeder
{
    private array $communes = [
        'Cocody'      => ['lat' => 5.3582, 'lng' => -3.9806],
        'Yopougon'    => ['lat' => 5.3344, 'lng' => -4.0620],
        'Marcory'     => ['lat' => 5.3168, 'lng' => -3.9922],
        'Abobo'       => ['lat' => 5.4167, 'lng' => -4.0167],
        'Treichville' => ['lat' => 5.3014, 'lng' => -4.0108],
        'Plateau'     => ['lat' => 5.3244, 'lng' => -4.0208],
        'Koumassi'    => ['lat' => 5.2936, 'lng' => -3.9634],
        'Adjamé'      => ['lat' => 5.3575, 'lng' => -4.0194],
        'Attécoubé'   => ['lat' => 5.3575, 'lng' => -4.0500],
        'Port-Bouët'  => ['lat' => 5.2551, 'lng' => -3.9301],
    ];

    private string $now;

    public function run(): void
    {
        $this->now = now()->toDateTimeString();
        $this->command?->info('🚀 Démarrage du seeding complet OVANIE...');

        DB::statement('PRAGMA foreign_keys = OFF;');
        $this->truncateTables();
        DB::statement('PRAGMA foreign_keys = ON;');

        $this->command?->info('👑 Création des administrateurs...');
        $admins = $this->seedAdmins();

        $this->command?->info('💼 Création de l\'équipe commerciale...');
        $commercials = $this->seedCommercials();

        $this->command?->info('🚚 Création de l\'équipe logistique...');
        $logistics = $this->seedLogistics();

        $this->command?->info('🎧 Création de l\'équipe support...');
        $supportAgents = $this->seedSupport();

        $this->command?->info('🏪 Création des vendeurs et boutiques...');
        $vendors = $this->seedVendors($commercials[0]);

        $this->command?->info('👥 Création des clients...');
        $clients = $this->seedClients($commercials[0]);

        $this->command?->info('📦 Création des commandes et livraisons...');
        $this->seedOrders($clients, $vendors);

        $this->command?->info('↩️  Création des retours et litiges...');
        $this->seedReturnsAndDisputes($clients);

        $this->command?->info('📊 Création du pipeline commercial...');
        $this->seedCommercialPipeline($commercials);

        $this->command?->info('🎫 Création des tickets support...');
        $this->seedSupportData($supportAgents, $clients);

        $this->command?->info('🏍️  Création des livreurs...');
        $this->seedDrivers();

        $this->command?->info('⚙️  Paramètres et promotions...');
        $this->seedSettings();
        $this->seedPromotions();

        $this->command?->info('');
        $this->printCredentials();
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function truncateTables(): void
    {
        $tables = [
            'admin_logs', 'internal_login_logs',
            'support_ai_audit_logs', 'support_agent_handoffs',
            'support_callback_requests', 'support_call_events', 'support_calls',
            'support_conversation_messages', 'support_conversations',
            'support_ticket_messages', 'support_tickets',
            'support_knowledge_articles',
            'commercial_activities', 'commercial_leads',
            'delivery_proofs', 'delivery_incidents', 'delivery_assignments',
            'delivery_routes', 'delivery_route_stops', 'delivery_route_caches',
            'shipment_status_histories', 'shipments',
            'disputes', 'returns',
            'commissions', 'vendor_payout_adjustments', 'vendor_payouts',
            'reviews', 'negotiations',
            'order_status_histories', 'order_items', 'orders',
            'cart_items', 'carts',
            'payment_proofs', 'payments',
            'addresses',
            'product_images', 'product_delivery_zones', 'products',
            'staff_profiles',
            'shops',
            'banners', 'promotions',
            'newsletter_subscribers',
            'users',
            'settings',
        ];

        foreach ($tables as $table) {
            try {
                DB::table($table)->delete();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function insertUser(array $data): int
    {
        $base = [
            'name'              => $data['first_name'].' '.$data['last_name'],
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
        $cols = DB::select("PRAGMA table_info(\"$table\")");
        $cache[$table] = array_column($cols, 'name');
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

    // ─────────────────────────────────────────────────────────────────────────
    // 1. ADMINS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedAdmins(): array
    {
        $ids = [];
        foreach ([
            ['first_name' => 'Christophe', 'last_name' => 'ADMIN',      'email' => 'admin@ovanie.com',       'role' => 'admin'],
            ['first_name' => 'Sophie',      'last_name' => 'DIRECTRICE', 'email' => 'directrice@ovanie.com',  'role' => 'admin'],
        ] as $d) {
            $ids[] = $this->insertUser(array_merge($d, [
                'password'     => Hash::make('Admin@2024!'),
                'is_admin'     => 1,
                'status'       => 'active',
                'phone'        => '+2250700'.rand(100000, 999999),
                'account_type' => 'professionnel',
            ]));
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. COMMERCIAUX
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCommercials(): array
    {
        $ids = [];
        foreach ([
            ['first_name' => 'Konan',  'last_name' => 'BENIE',    'email' => 'commercial1@ovanie.com'],
            ['first_name' => 'Aya',    'last_name' => 'KOFFI',    'email' => 'commercial2@ovanie.com'],
            ['first_name' => 'Lamine', 'last_name' => 'COULIBALY','email' => 'commercial3@ovanie.com'],
        ] as $d) {
            $ids[] = $this->insertUser(array_merge($d, [
                'password'     => Hash::make('Commercial@2024!'),
                'role'         => 'commercial',
                'status'       => 'active',
                'phone'        => '+2250501'.rand(100000, 999999),
                'account_type' => 'professionnel',
            ]));
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. LOGISTIQUE
    // ─────────────────────────────────────────────────────────────────────────
    private function seedLogistics(): array
    {
        $ids = [];
        foreach ([
            ['first_name' => 'Moussa', 'last_name' => 'DIALLO',   'email' => 'logistique1@ovanie.com'],
            ['first_name' => 'Amenan', 'last_name' => 'KOUAKOU',  'email' => 'logistique2@ovanie.com'],
            ['first_name' => 'Boris',  'last_name' => 'NGUESSAN', 'email' => 'logistique3@ovanie.com'],
        ] as $d) {
            $ids[] = $this->insertUser(array_merge($d, [
                'password'     => Hash::make('Logistique@2024!'),
                'role'         => 'logistique',
                'status'       => 'active',
                'phone'        => '+2250701'.rand(100000, 999999),
                'account_type' => 'professionnel',
            ]));
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. SUPPORT
    // ─────────────────────────────────────────────────────────────────────────
    private function seedSupport(): array
    {
        $ids = [];
        foreach ([
            ['first_name' => 'Nana',   'last_name' => 'DIABATE', 'email' => 'support1@ovanie.com'],
            ['first_name' => 'Ismaël', 'last_name' => 'TOURE',   'email' => 'support2@ovanie.com'],
            ['first_name' => 'Fatou',  'last_name' => 'CAMARA',  'email' => 'support3@ovanie.com'],
        ] as $d) {
            $ids[] = $this->insertUser(array_merge($d, [
                'password'     => Hash::make('Support@2024!'),
                'role'         => 'support',
                'status'       => 'active',
                'phone'        => '+2250101'.rand(100000, 999999),
                'account_type' => 'professionnel',
            ]));
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. VENDEURS + BOUTIQUES + PRODUITS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedVendors(int $commercialId): array
    {
        $cat = DB::table('categories')->first();
        $categoryId = $cat?->id;

        $vendorDefs = [
            [
                'user' => ['first_name' => 'Koffi',      'last_name' => 'ACKAH',     'email' => 'vendeur1@ovanie.com', 'phone' => '+2250701001001'],
                'shop' => ['name' => 'CKBAT PLUS BTP',        'commune' => 'Yopougon', 'description' => 'Spécialiste des matériaux de construction : ciment, fer, carrelage, peinture.'],
                'products' => [
                    ['name' => 'Ciment CEM II 50 kg',             'price' => 4800,   'weight' => 50,   'unit' => 'sac',     'stock' => 500],
                    ['name' => 'Fer à béton HA12 12m',             'price' => 6500,   'weight' => 10,   'unit' => 'barre',   'stock' => 300],
                    ['name' => 'Parpaings creux 15cm (palette)',    'price' => 28000,  'weight' => 900,  'unit' => 'palette', 'stock' => 80],
                    ['name' => 'Peinture murale blanche 20L',       'price' => 24500,  'weight' => 25,   'unit' => 'bidon',   'stock' => 120],
                    ['name' => 'Carrelage sol 60x60 cm (boîte)',    'price' => 9200,   'weight' => 25,   'unit' => 'boîte',   'stock' => 200],
                    ['name' => 'Sable de rivière (tonne)',          'price' => 35000,  'weight' => 1000, 'unit' => 'tonne',   'stock' => 50],
                    ['name' => 'Gravier 10/20 (tonne)',             'price' => 40000,  'weight' => 1000, 'unit' => 'tonne',   'stock' => 40],
                    ['name' => 'Tube PVC pression Ø100 - 6m',      'price' => 8700,   'weight' => 8,    'unit' => 'tube',    'stock' => 150],
                ],
            ],
            [
                'user' => ['first_name' => 'Diane',     'last_name' => 'BOGA',       'email' => 'vendeur2@ovanie.com', 'phone' => '+2250702002002'],
                'shop' => ['name' => 'SOFITEL MATERIAUX',     'commune' => 'Cocody',   'description' => 'Partenaire de confiance pour matériaux de finition et décoration intérieure.'],
                'products' => [
                    ['name' => 'Carrelage mural 30x60 cm',    'price' => 7800,  'weight' => 18, 'unit' => 'boîte', 'stock' => 180],
                    ['name' => 'Plâtre de construction 40kg', 'price' => 6200,  'weight' => 40, 'unit' => 'sac',   'stock' => 220],
                    ['name' => 'Peinture façade 20L',         'price' => 32000, 'weight' => 25, 'unit' => 'bidon', 'stock' => 90],
                    ['name' => 'Dalle béton 40x40 cm (lot)',  'price' => 12500, 'weight' => 50, 'unit' => 'lot',   'stock' => 100],
                    ['name' => 'Enduit de rebouchage 5kg',    'price' => 4200,  'weight' => 5,  'unit' => 'pot',   'stock' => 300],
                ],
            ],
            [
                'user' => ['first_name' => 'Ibrahim',   'last_name' => 'KONE',       'email' => 'vendeur3@ovanie.com', 'phone' => '+2250703003003'],
                'shop' => ['name' => 'KONE ELECTRO & PLOMBERIE','commune' => 'Marcory','description' => 'Matériel électrique et plomberie professionnels pour chantiers résidentiels et industriels.'],
                'products' => [
                    ['name' => 'Câble électrique 2.5mm² (100m)',   'price' => 45000,  'weight' => 12,  'unit' => 'rouleau', 'stock' => 60],
                    ['name' => 'Disjoncteur bipolaire 32A',         'price' => 8500,   'weight' => 0.5, 'unit' => 'pièce',   'stock' => 200],
                    ['name' => 'Tableau électrique 12 modules',     'price' => 18000,  'weight' => 3,   'unit' => 'tableau', 'stock' => 45],
                    ['name' => 'Robinet mitigeur lavabo chromé',    'price' => 22500,  'weight' => 1.2, 'unit' => 'pièce',   'stock' => 80],
                    ['name' => 'Tuyau cuivre 15mm (3m)',            'price' => 5800,   'weight' => 1,   'unit' => 'tube',    'stock' => 120],
                    ['name' => 'Pompe submersible 0.75kW',          'price' => 185000, 'weight' => 15,  'unit' => 'pièce',   'stock' => 12],
                ],
            ],
            [
                'user' => ['first_name' => 'Marie-Josée','last_name' => 'ASSEMIAN',  'email' => 'vendeur4@ovanie.com', 'phone' => '+2250704004004'],
                'shop' => ['name' => 'DECO & BOIS PREMIUM', 'commune' => 'Cocody',   'description' => 'Spécialiste du bois et décoration intérieure : parquet, portes, placards et cuisines sur mesure.'],
                'products' => [
                    ['name' => 'Parquet stratifié chêne (m²)',     'price' => 14500, 'weight' => 8,  'unit' => 'm²',   'stock' => 250],
                    ['name' => 'Porte intérieure pleine 70x205 cm','price' => 68000, 'weight' => 28, 'unit' => 'pièce','stock' => 30],
                    ['name' => 'Plan de travail cuisine 3m',        'price' => 95000, 'weight' => 40, 'unit' => 'pièce','stock' => 15],
                    ['name' => 'Lambris PVC blanc (m²)',            'price' => 6500,  'weight' => 4,  'unit' => 'm²',   'stock' => 400],
                ],
            ],
        ];

        $vendors = [];

        foreach ($vendorDefs as $vd) {
            $coords  = $this->communes[$vd['shop']['commune']];
            $userId  = $this->insertUser([
                'first_name'               => $vd['user']['first_name'],
                'last_name'                => $vd['user']['last_name'],
                'email'                    => $vd['user']['email'],
                'password'                 => Hash::make('Vendeur@2024!'),
                'role'                     => 'client',
                'status'                   => 'active',
                'phone'                    => $vd['user']['phone'],
                'account_type'             => 'professionnel',
                'created_by_commercial_id' => $commercialId,
            ]);

            $shopId = $this->insert('shops', [
                'user_id'                  => $userId,
                'name'                     => $vd['shop']['name'],
                'slug'                     => Str::slug($vd['shop']['name']),
                'seller_type'              => 'particulier',
                'identity_type'            => 'cni',
                'identity_number'          => 'CI-'.rand(10000000, 99999999),
                'description'              => $vd['shop']['description'],
                'status'                   => 'approved',
                'is_active'                => 1,
                'logistics_status'         => 'ready',
                'logistics_type'           => 'ovanie',
                'district'                 => 'Abidjan',
                'address'                  => 'Boulevard Principal, Rue 14, '.$vd['shop']['commune'],
                'geo_status'               => 'verified',
                'commune'                  => $vd['shop']['commune'],
                'city'                     => 'Abidjan',
                'latitude'                 => $coords['lat'],
                'longitude'                => $coords['lng'],
                'whatsapp'                 => $vd['user']['phone'],
                'business_email'           => $vd['user']['email'],
                'payment_mode'             => 'post_delivery',
                'approved_at'              => now()->subDays(rand(30, 180))->toDateTimeString(),
                'created_by_commercial_id' => $commercialId,
                'managed_by_commercial_id' => $commercialId,
            ]);

            $productIds = [];
            foreach ($vd['products'] as $pd) {
                $pid = $this->insert('products', [
                    'shop_id'                  => $shopId,
                    'vendor_id'                => $userId,
                    'category_id'              => $categoryId,
                    'name'                     => $pd['name'],
                    'slug'                     => Str::slug($pd['name']).'-'.Str::lower(Str::random(4)),
                    'description'              => $pd['name'].' — Produit de qualité professionnelle disponible à Abidjan.',
                    'price'                    => $pd['price'],
                    'weight'                   => $pd['weight'],
                    'unit'                     => $pd['unit'],
                    'stock'                    => $pd['stock'],
                    'status'                   => 'approved',
                    'is_active'                => 1,
                    'views'                    => rand(50, 800),
                    'sales'                    => rand(5, 150),
                    'created_by_commercial_id' => $commercialId,
                ]);

                $this->insert('product_images', [
                    'product_id' => $pid,
                    'path'       => 'demo/product-placeholder.jpg',
                    'is_primary' => 1,
                    'sort_order' => 0,
                ]);

                $productIds[] = $pid;
            }

            // Payout vendeur (historique)
            $this->insert('vendor_payouts', [
                'vendor_id'         => $userId,
                'shop_id'           => $shopId,
                'product_amount'    => rand(300000, 2500000),
                'payout_amount'     => rand(280000, 2300000),
                'total_amount'      => rand(300000, 2500000),
                'commission_amount' => rand(15000, 125000),
                'status'            => 'paid',
                'payment_method'    => 'wave',
                'phone'             => $vd['user']['phone'],
                'payout_reference'  => 'PAYOUT-'.strtoupper(Str::random(8)),
                'payout_period_key' => now()->subMonth()->format('Y-m'),
                'paid_at'           => now()->subDays(rand(1, 30))->toDateTimeString(),
                'approved_at'       => now()->subDays(rand(5, 35))->toDateTimeString(),
            ]);

            $this->insert('vendor_payouts', [
                'vendor_id'         => $userId,
                'shop_id'           => $shopId,
                'product_amount'    => rand(80000, 900000),
                'payout_amount'     => rand(75000, 855000),
                'total_amount'      => rand(80000, 900000),
                'commission_amount' => rand(4000, 45000),
                'status'            => 'pending',
                'payout_period_key' => now()->format('Y-m'),
            ]);

            $vendors[] = ['user_id' => $userId, 'shop_id' => $shopId, 'product_ids' => $productIds, 'phone' => $vd['user']['phone']];
        }

        // Boutique en attente (test workflow approbation)
        $pendingUserId = $this->insertUser([
            'first_name'  => 'Patrick',
            'last_name'   => 'ZAMA',
            'email'       => 'vendeur_pending@ovanie.com',
            'password'    => Hash::make('Vendeur@2024!'),
            'role'        => 'client',
            'status'      => 'active',
            'phone'       => '+2250705005005',
            'account_type'=> 'professionnel',
        ]);
        $this->insert('shops', [
            'user_id'       => $pendingUserId,
            'name'          => 'ZAMA CONSTRUCTIONS',
            'slug'          => 'zama-constructions',
            'seller_type'   => 'particulier',
            'identity_type' => 'cni',
            'identity_number' => 'CI-99887766',
            'description'   => 'Matériaux de construction haut de gamme pour grands projets.',
            'status'        => 'pending',
            'is_active'     => 0,
            'commune'       => 'Plateau',
            'city'          => 'Abidjan',
            'whatsapp'      => '+2250705005005',
            'business_email'=> 'vendeur_pending@ovanie.com',
        ]);

        return $vendors;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. CLIENTS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedClients(int $commercialId): array
    {
        $clientDefs = [
            ['first_name' => 'Franck',     'last_name' => 'ETTIEN',   'email' => 'client1@ovanie.com',  'phone' => '+2250501010101', 'commune' => 'Cocody',      'account_type' => 'particulier'],
            ['first_name' => 'Ramatou',    'last_name' => 'BAMBA',    'email' => 'client2@ovanie.com',  'phone' => '+2250502020202', 'commune' => 'Yopougon',    'account_type' => 'particulier'],
            ['first_name' => 'Serge',      'last_name' => 'GNANGNAN', 'email' => 'client3@ovanie.com',  'phone' => '+2250503030303', 'commune' => 'Marcory',     'account_type' => 'professionnel'],
            ['first_name' => 'Adjoua',     'last_name' => 'ASSOUAN',  'email' => 'client4@ovanie.com',  'phone' => '+2250504040404', 'commune' => 'Abobo',       'account_type' => 'particulier'],
            ['first_name' => 'Aristide',   'last_name' => 'HABA',     'email' => 'client5@ovanie.com',  'phone' => '+2250505050505', 'commune' => 'Treichville', 'account_type' => 'professionnel'],
            ['first_name' => 'Edwige',     'last_name' => 'KOUAME',   'email' => 'client6@ovanie.com',  'phone' => '+2250506060606', 'commune' => 'Koumassi',    'account_type' => 'particulier'],
            ['first_name' => 'Thierry',    'last_name' => 'YAO',      'email' => 'client7@ovanie.com',  'phone' => '+2250507070707', 'commune' => 'Plateau',     'account_type' => 'professionnel'],
            ['first_name' => 'Christelle', 'last_name' => 'AKPATA',   'email' => 'client8@ovanie.com',  'phone' => '+2250508080808', 'commune' => 'Adjamé',      'account_type' => 'particulier'],
            ['first_name' => 'Seydou',     'last_name' => 'TRAORE',   'email' => 'client9@ovanie.com',  'phone' => '+2250509090909', 'commune' => 'Cocody',      'account_type' => 'particulier'],
            ['first_name' => 'Véronique',  'last_name' => 'GBEKE',    'email' => 'client10@ovanie.com', 'phone' => '+2250510101010', 'commune' => 'Yopougon',    'account_type' => 'professionnel'],
        ];

        $clients = [];

        foreach ($clientDefs as $i => $cd) {
            $coords = $this->communes[$cd['commune']];
            $userId = $this->insertUser([
                'first_name'               => $cd['first_name'],
                'last_name'                => $cd['last_name'],
                'email'                    => $cd['email'],
                'password'                 => Hash::make('Client@2024!'),
                'role'                     => 'client',
                'status'                   => 'active',
                'phone'                    => $cd['phone'],
                'account_type'             => $cd['account_type'],
                'city'                     => 'Abidjan',
                'created_by_commercial_id' => $i < 3 ? $commercialId : null,
            ]);

            // Adresse principale
            $this->insert('addresses', [
                'user_id'        => $userId,
                'type'           => 'home',
                'label'          => 'Domicile',
                'address'        => 'Quartier Résidentiel, Rue 12, '.$cd['commune'],
                'recipient_name' => $cd['first_name'].' '.$cd['last_name'],
                'phone'          => $cd['phone'],
                'commune'        => $cd['commune'],
                'city'           => 'Abidjan',
                'latitude'       => $coords['lat'] + (rand(-10, 10) / 1000),
                'longitude'      => $coords['lng'] + (rand(-10, 10) / 1000),
                'is_default'     => 1,
            ]);

            $clients[] = [
                'id'      => $userId,
                'name'    => $cd['first_name'].' '.$cd['last_name'],
                'phone'   => $cd['phone'],
                'email'   => $cd['email'],
                'commune' => $cd['commune'],
            ];
        }

        // Panier pour le premier client
        $cartId = $this->insert('carts', ['user_id' => $clients[0]['id']]);
        $productIds = DB::table('products')->where('status', 'approved')->take(2)->pluck('id');
        foreach ($productIds as $pid) {
            $price = DB::table('products')->where('id', $pid)->value('price');
            $this->insert('cart_items', [
                'cart_id'    => $cartId,
                'product_id' => $pid,
                'quantity'   => rand(1, 3),
                'price'      => $price,
            ]);
        }

        return $clients;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. COMMANDES + PAIEMENTS + LIVRAISONS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedOrders(array $clients, array $vendors): void
    {
        $communes  = array_keys($this->communes);
        $invoiceN  = 1000;

        // Scénarios variés
        $scenarios = [
            ['status' => 'delivered',   'payment_status' => 'paid',    'days' => -45],
            ['status' => 'delivered',   'payment_status' => 'paid',    'days' => -30],
            ['status' => 'delivered',   'payment_status' => 'paid',    'days' => -15],
            ['status' => 'delivered',   'payment_status' => 'paid',    'days' => -7],
            ['status' => 'delivered',   'payment_status' => 'paid',    'days' => -3],
            ['status' => 'shipped',     'payment_status' => 'paid',    'days' => -2],
            ['status' => 'shipped',     'payment_status' => 'paid',    'days' => -1],
            ['status' => 'confirmed',   'payment_status' => 'paid',    'days' => 0],
            ['status' => 'confirmed',   'payment_status' => 'paid',    'days' => 0],
            ['status' => 'pending',     'payment_status' => 'pending', 'days' => 0],
            ['status' => 'pending',     'payment_status' => 'pending', 'days' => 0],
            ['status' => 'cancelled',   'payment_status' => 'failed',  'days' => -5],
        ];

        $allProductIds = DB::table('products')->where('status', 'approved')->pluck('id')->toArray();
        if (empty($allProductIds)) return;

        foreach ($scenarios as $sc) {
            $client  = $clients[array_rand($clients)];
            $vendor  = $vendors[array_rand($vendors)];
            $commune = $communes[array_rand($communes)];
            $coords  = $this->communes[$commune];
            $invoiceN++;

            $shopProductIds = DB::table('products')
                ->where('shop_id', $vendor['shop_id'])
                ->where('status', 'approved')
                ->pluck('id')->toArray();
            if (empty($shopProductIds)) $shopProductIds = $allProductIds;

            $selectedPids = array_slice(
                $shopProductIds,
                0,
                min(rand(1, 3), count($shopProductIds))
            );

            $subtotal    = 0;
            $deliveryFee = rand(1500, 8000);
            $createdAt   = now()->addDays($sc['days'])->toDateTimeString();

            foreach ($selectedPids as $pid) {
                $price = DB::table('products')->where('id', $pid)->value('price');
                $qty   = rand(1, 5);
                $subtotal += $price * $qty;
            }

            $orderId = $this->insert('orders', [
                'order_number'    => 'ORD-'.strtoupper(Str::random(8)),
                'client_id'       => $client['id'],
                'customer_name'   => $client['name'],
                'vendor_id'       => $vendor['user_id'],
                'shop_id'         => $vendor['shop_id'],
                'product_id'      => !empty($selectedPids) ? $selectedPids[0] : null,
                'status'          => $sc['status'],
                'subtotal'        => $subtotal,
                'delivery_fee'    => $deliveryFee,
                'total_amount'    => $subtotal + $deliveryFee,
                'payment_status'  => $sc['payment_status'],
                'payment_method'  => ['wave', 'orange_money', 'cod'][rand(0, 2)],
                'invoice_number'  => 'OV-'.$invoiceN,
                'delivery_commune'=> $commune,
                'delivery_city'   => 'Abidjan',
                'delivery_lat'    => $coords['lat'],
                'delivery_lng'    => $coords['lng'],
                'delivery_recipient_name'  => $client['name'],
                'delivery_recipient_phone' => $client['phone'],
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            foreach ($selectedPids as $pid) {
                $price = DB::table('products')->where('id', $pid)->value('price');
                $qty   = rand(1, 5);
                $this->insert('order_items', [
                    'order_id'   => $orderId,
                    'product_id' => $pid,
                    'shop_id'    => $vendor['shop_id'],
                    'quantity'   => $qty,
                    'price'      => $price,
                    'subtotal'   => $price * $qty,
                    'vendor_status' => in_array($sc['status'], ['confirmed', 'shipped', 'delivered']) ? 'confirmed' : 'pending',
                    'delivery_status' => $sc['status'] === 'delivered' ? 'delivered' : ($sc['status'] === 'shipped' ? 'in_transit' : 'pending'),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            // Paiement
            if ($sc['payment_status'] === 'paid') {
                $this->insert('payments', [
                    'order_id'  => $orderId,
                    'client_id' => $client['id'],
                    'amount'    => $subtotal + $deliveryFee,
                    'currency'  => 'XOF',
                    'method'    => ['wave', 'orange_money', 'cod'][rand(0, 2)],
                    'status'    => 'completed',
                    'reference' => 'TXN-'.strtoupper(Str::random(10)),
                    'paid_at'   => now()->addDays($sc['days'])->addMinutes(30)->toDateTimeString(),
                    'created_at'=> $createdAt,
                    'updated_at'=> $createdAt,
                ]);
            }

            // Expédition
            if (in_array($sc['status'], ['shipped', 'delivered', 'confirmed'])) {
                $shipStatus = match($sc['status']) {
                    'delivered' => 'delivered',
                    'shipped'   => 'in_transit',
                    default     => 'pending',
                };
                $shopCoords = ['lat' => ($coords['lat'] + 0.01), 'lng' => ($coords['lng'] + 0.01)];
                $shipId = $this->insert('shipments', [
                    'order_id'         => $orderId,
                    'status'           => $shipStatus,
                    'tracking_number'  => 'SHP-'.strtoupper(Str::random(8)),
                    'pickup_commune'   => DB::table('shops')->where('id', $vendor['shop_id'])->value('commune') ?? 'Abidjan',
                    'delivery_commune' => $commune,
                    'pickup_lat'       => $shopCoords['lat'],
                    'pickup_lng'       => $shopCoords['lng'],
                    'delivery_lat'     => $coords['lat'],
                    'delivery_lng'     => $coords['lng'],
                    'estimated_delivery_at' => now()->addDays($sc['days'] + 3)->toDateTimeString(),
                    'delivered_at'     => $shipStatus === 'delivered' ? now()->addDays($sc['days'] + 2)->toDateTimeString() : null,
                    'created_at'       => $createdAt,
                    'updated_at'       => $createdAt,
                ]);
                $this->insert('shipment_status_histories', [
                    'shipment_id' => $shipId,
                    'status'      => $shipStatus,
                    'created_at'  => $createdAt,
                    'updated_at'  => $createdAt,
                ]);
            }

            // Avis client
            if ($sc['status'] === 'delivered' && rand(0, 1)) {
                $comments = [
                    'Très bonne qualité, livraison rapide !',
                    'Produit conforme. Service impeccable.',
                    'Satisfait, je recommande !',
                    'Livraison dans les délais. Emballage soigné.',
                ];
                $pid = !empty($selectedPids) ? $selectedPids[0] : null;
                $this->insert('reviews', [
                    'user_id'    => $client['id'],
                    'product_id' => $pid,
                    'rating'     => rand(3, 5),
                    'comment'    => $comments[array_rand($comments)],
                    'created_at' => now()->addDays($sc['days'] + 2)->toDateTimeString(),
                    'updated_at' => now()->addDays($sc['days'] + 2)->toDateTimeString(),
                ]);
            }
        }

        // 20 commandes supplémentaires pour remplir les tableaux
        for ($i = 0; $i < 20; $i++) {
            $client   = $clients[array_rand($clients)];
            $vendor   = $vendors[array_rand($vendors)];
            $commune  = $communes[array_rand($communes)];
            $coords   = $this->communes[$commune];
            $pid      = $allProductIds[array_rand($allProductIds)];
            $price    = DB::table('products')->where('id', $pid)->value('price');
            $qty      = rand(1, 10);
            $daysAgo  = rand(0, 90);
            $status   = $daysAgo > 30 ? 'delivered' : (['pending', 'confirmed', 'shipped', 'delivered'][rand(0, 3)]);
            $createdAt= now()->subDays($daysAgo)->toDateTimeString();
            $invoiceN++;

            $orderId = $this->insert('orders', [
                'order_number'    => 'ORD-'.strtoupper(Str::random(8)),
                'client_id'       => $client['id'],
                'customer_name'   => $client['name'],
                'vendor_id'       => $vendor['user_id'],
                'shop_id'         => $vendor['shop_id'],
                'product_id'      => $pid,
                'status'          => $status,
                'subtotal'        => $price * $qty,
                'delivery_fee'    => rand(2000, 6000),
                'total_amount'    => $price * $qty + rand(2000, 6000),
                'payment_status'  => $status === 'pending' ? 'pending' : 'paid',
                'payment_method'  => 'wave',
                'invoice_number'  => 'OV-'.$invoiceN,
                'delivery_commune'=> $commune,
                'delivery_city'   => 'Abidjan',
                'delivery_lat'    => $coords['lat'],
                'delivery_lng'    => $coords['lng'],
                'delivery_recipient_name'  => $client['name'],
                'delivery_recipient_phone' => $client['phone'],
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            $this->insert('order_items', [
                'order_id'   => $orderId,
                'product_id' => $pid,
                'shop_id'    => $vendor['shop_id'],
                'quantity'   => $qty,
                'price'      => $price,
                'subtotal'   => $price * $qty,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($status !== 'pending') {
                $this->insert('payments', [
                    'order_id'  => $orderId,
                    'client_id' => $client['id'],
                    'amount'    => $price * $qty + rand(2000, 6000),
                    'currency'  => 'XOF',
                    'method'    => 'wave',
                    'status'    => 'completed',
                    'reference' => 'TXN-'.strtoupper(Str::random(10)),
                    'paid_at'   => now()->subDays($daysAgo)->addMinutes(30)->toDateTimeString(),
                    'created_at'=> $createdAt,
                    'updated_at'=> $createdAt,
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. RETOURS & LITIGES
    // ─────────────────────────────────────────────────────────────────────────
    private function seedReturnsAndDisputes(array $clients): void
    {
        $deliveredOrders = DB::table('orders')
            ->where('status', 'delivered')
            ->take(6)->get();

        $reasons = [
            'Produit défectueux à la réception',
            'Produit non conforme à la description',
            'Quantité incorrecte reçue',
            'Produit endommagé pendant le transport',
        ];

        foreach ($deliveredOrders->take(3) as $order) {
            $this->insert('returns', [
                'order_id'        => $order->id,
                'order_reference' => $order->order_number,
                'product_name'    => 'Produit Matériaux BTP',
                'client_id'       => $order->client_id,
                'shop_id'         => $order->shop_id,
                'reason'          => $reasons[array_rand($reasons)],
                'return_type'     => 'product',
                'status'          => ['pending', 'accepted', 'rejected'][rand(0, 2)],
                'request_date'    => now()->subDays(rand(1, 7))->toDateString(),
            ]);
        }

        $disputeReasons = [
            'Vendeur ne répond plus après la commande',
            'Produit reçu endommagé, vendeur refuse le remboursement',
            'Non-livraison après 15 jours',
            'Paiement effectué mais commande annulée sans remboursement',
        ];

        foreach ($deliveredOrders->take(2) as $order) {
            $this->insert('disputes', [
                'order_id'        => $order->id,
                'order_reference' => $order->order_number,
                'client_id'       => $order->client_id,
                'client_name'     => $order->customer_name ?? 'Client',
                'shop_id'         => $order->shop_id,
                'reason'          => $disputeReasons[array_rand($disputeReasons)],
                'status'          => ['open', 'in_review', 'resolved'][rand(0, 2)],
            ]);
        }

        // Incidents de livraison
        $shipments    = DB::table('shipments')->take(3)->get();
        $incidentTypes= ['adresse_incorrecte', 'client_absent', 'colis_endommage', 'retard'];
        foreach ($shipments as $shipment) {
            $this->insert('delivery_incidents', [
                'shipment_id' => $shipment->id,
                'type'        => $incidentTypes[array_rand($incidentTypes)],
                'description' => 'Client introuvable à l\'adresse indiquée. Tentative de recontact en cours.',
                'status'      => ['open', 'in_progress', 'resolved'][rand(0, 2)],
                'reported_by' => 'driver',
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. PIPELINE COMMERCIAL
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCommercialPipeline(array $commercialIds): void
    {
        $leadsData = [
            ['company_name' => 'MAISON TRAORÉ SARL',          'contact_name' => 'Moussa Traoré',    'phone' => '+2250700111222', 'email' => 'traoré@maisonsarl.ci',    'status' => 'in_progress', 'lead_type' => 'vendeur',  'estimated_value' => 2500000],
            ['company_name' => 'Résidence Les Palmiers',       'contact_name' => 'Jocelyne Aké',     'phone' => '+2250700222333', 'email' => 'ake@palmiers.ci',          'status' => 'in_progress', 'lead_type' => 'client',   'estimated_value' => 850000],
            ['company_name' => 'SOCIÉTÉ BTP ABIDJAN',          'contact_name' => 'Gervais Yoboué',   'phone' => '+2250700333444', 'email' => 'yoboue@btp-abj.ci',       'status' => 'in_progress', 'lead_type' => 'business', 'estimated_value' => 15000000],
            ['company_name' => 'Kouakou Frères Construction',  'contact_name' => 'Ange Kouakou',     'phone' => '+2250700444555', 'email' => 'ange@kouakoufr.ci',        'status' => 'won',         'lead_type' => 'vendeur',  'estimated_value' => 5000000],
            ['company_name' => 'ORANGE IMMOBILIER CI',         'contact_name' => 'Patricia Orange',  'phone' => '+2250700555666', 'email' => 'p.orange@orangeimmo.ci',  'status' => 'new',         'lead_type' => 'business', 'estimated_value' => 30000000],
            ['company_name' => 'Chantier Villa Boni',          'contact_name' => 'Théodore Boni',    'phone' => '+2250700666777', 'email' => 'boni@villaboni.ci',        'status' => 'in_progress', 'lead_type' => 'client',   'estimated_value' => 1200000],
            ['company_name' => 'BATIPLUS GROUP',               'contact_name' => 'Cécile Ahizi',     'phone' => '+2250700777888', 'email' => 'ahizi@batiplus.ci',        'status' => 'lost',        'lead_type' => 'vendeur',  'estimated_value' => 8000000],
            ['company_name' => 'École Primaire Moderne Abobo', 'contact_name' => 'Directeur Sanogo', 'phone' => '+2250700888999', 'email' => 'sanogo@epm-abobo.ci',      'status' => 'in_progress', 'lead_type' => 'business', 'estimated_value' => 4500000],
            ['company_name' => 'M. Soro Klétigui',             'contact_name' => 'Klétigui Soro',   'phone' => '+2250700999000', 'email' => 'k.soro@gmail.com',         'status' => 'won',         'lead_type' => 'client',   'estimated_value' => 350000],
            ['company_name' => 'LOGICIM ABIDJAN',              'contact_name' => 'Narcisse Lokossou','phone' => '+2250701000111', 'email' => 'lokossou@logicim.ci',      'status' => 'in_progress', 'lead_type' => 'vendeur',  'estimated_value' => 12000000],
            ['company_name' => 'Clinique Saint-Luc',           'contact_name' => 'Dr. Agba Rosine',  'phone' => '+2250701111222', 'email' => 'rosine@stluc-clinique.ci', 'status' => 'in_progress', 'lead_type' => 'business', 'estimated_value' => 7000000],
            ['company_name' => 'M. Dago Emmanuel',             'contact_name' => 'Emmanuel Dago',    'phone' => '+2250701222333', 'email' => 'dago@gmail.com',           'status' => 'new',         'lead_type' => 'client',   'estimated_value' => 95000],
        ];

        $activityNotes = [
            'Prise de contact initiale par téléphone. Client intéressé, demande de catalogue.',
            'Réunion en présentiel. Présentation des offres OVANIE Pro.',
            'Envoi de la proposition commerciale. En attente de retour.',
            'Relance téléphonique — client en réunion, rappel planifié.',
            'Devis validé ! Commande en cours de traitement.',
            'Client demande une remise supplémentaire sur commande groupée.',
            'Visite de chantier. Évaluation des besoins en matériaux.',
            'Signature du contrat cadre. Onboarding prévu la semaine prochaine.',
            'Prospect non joignable. 3ème tentative de contact.',
        ];

        foreach ($leadsData as $ld) {
            $commercialId = $commercialIds[array_rand($commercialIds)];
            $createdAt    = now()->subDays(rand(1, 120))->toDateTimeString();

            $leadId = $this->insert('commercial_leads', [
                'reference'       => 'COM-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'assigned_to'     => $commercialId,
                'created_by'      => $commercialId,
                'company_name'    => $ld['company_name'],
                'contact_name'    => $ld['contact_name'],
                'title'           => $ld['company_name'].' — Demande '.$ld['lead_type'],
                'phone'           => $ld['phone'],
                'email'           => $ld['email'],
                'status'          => $ld['status'],
                'lead_type'       => $ld['lead_type'],
                'estimated_value' => $ld['estimated_value'],
                'source'          => ['appel_entrant', 'referral', 'terrain', 'web', 'whatsapp'][rand(0, 4)],
                'need_summary'    => 'Prospect identifié lors d\'une campagne terrain. Potentiel élevé.',
                'city'            => array_keys($this->communes)[rand(0, 9)],
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);

            for ($a = 0; $a < rand(1, 4); $a++) {
                $actDate = now()->subDays(rand(0, 100))->toDateTimeString();
                $this->insert('commercial_activities', [
                    'commercial_lead_id' => $leadId,
                    'author_id'          => $commercialId,
                    'type'               => ['call', 'email', 'meeting', 'visit', 'whatsapp'][rand(0, 4)],
                    'description'        => $activityNotes[array_rand($activityNotes)],
                    'outcome'            => ['positive', 'neutral', 'negative'][rand(0, 2)],
                    'happened_at'        => $actDate,
                    'created_at'         => $actDate,
                    'updated_at'         => $actDate,
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. SUPPORT
    // ─────────────────────────────────────────────────────────────────────────
    private function seedSupportData(array $supportIds, array $clients): void
    {
        $agent   = $supportIds[0];
        $orders  = DB::table('orders')->take(10)->get();

        $subjects = [
            'Commande non reçue après 10 jours',
            'Produit reçu endommagé - demande remboursement',
            'Erreur de facturation sur ma commande',
            'Changement d\'adresse de livraison impossible',
            'Comment annuler ma commande ?',
            'Vendeur ne répond plus à mes messages',
            'Paiement débité mais commande non confirmée',
            'Délai de livraison anormalement long',
            'Produit non conforme à l\'annonce',
            'Remboursement reçu incorrect',
            'Problème de connexion à mon compte',
            'Je souhaite modifier mon abonnement',
        ];

        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses   = ['open', 'in_progress', 'resolved', 'closed'];

        foreach ($subjects as $i => $subject) {
            $client    = $clients[array_rand($clients)];
            $createdAt = now()->subDays(rand(0, 30))->toDateTimeString();
            $status    = $statuses[rand(0, 3)];

            $order = $orders->isNotEmpty() ? $orders->random() : null;

            $ticketId = $this->insert('support_tickets', [
                'reference'         => 'SUP-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'subject'           => $subject,
                'description'       => 'Description détaillée du ticket : '.$subject.'. Demande enregistrée par le client.',
                'status'            => $status,
                'priority'          => $priorities[rand(0, 3)],
                'requester_user_id' => $client['id'],
                'requester_name'    => $client['name'],
                'requester_email'   => $client['email'],
                'requester_phone'   => $client['phone'],
                'created_by'        => $client['id'],
                'assigned_to'       => $agent,
                'order_id'          => $order?->id,
                'channel'           => ['chat', 'email', 'phone', 'whatsapp'][rand(0, 3)],
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
            ]);

            // Messages du ticket
            $messages = [
                ['sender' => 'client', 'body' => 'Bonjour, j\'ai un problème avec ma commande. Pouvez-vous m\'aider ?'],
                ['sender' => 'staff',  'body' => 'Bonjour, je comprends votre situation. Je vais vérifier votre dossier immédiatement.'],
                ['sender' => 'client', 'body' => 'Merci, j\'attends votre retour.'],
                ['sender' => 'staff',  'body' => 'Votre dossier est traité. Vous recevrez une réponse définitive sous 24h.'],
            ];

            foreach ($messages as $j => $msg) {
                $senderId = $msg['sender'] === 'client' ? $client['id'] : $agent;
                $this->insert('support_ticket_messages', [
                    'support_ticket_id' => $ticketId,
                    'author_id'         => $senderId,
                    'author_type'       => $msg['sender'] === 'client' ? 'client' : 'staff',
                    'body'              => $msg['body'],
                    'created_at'        => now()->subDays(rand(0, 30))->addHours($j * 2)->toDateTimeString(),
                    'updated_at'        => now()->subDays(rand(0, 30))->addHours($j * 2)->toDateTimeString(),
                ]);
            }
        }

        // Conversations IA
        $topics = [
            'Suivi de commande OV-1025',
            'Demande de remboursement',
            'Comment passer une commande ?',
            'Ma boutique n\'est pas visible',
            'Délai de livraison pour Abobo',
            'Prix du ciment en gros',
        ];

        foreach ($topics as $topic) {
            $client    = $clients[array_rand($clients)];
            $createdAt = now()->subHours(rand(1, 72))->toDateTimeString();

            $convId = $this->insert('support_conversations', [
                'public_token'      => (string) Str::uuid(),
                'requester_user_id' => $client['id'],
                'requester_name'    => $client['name'],
                'requester_phone'   => $client['phone'],
                'assigned_to'       => $agent,
                'channel'           => ['chat', 'whatsapp'][rand(0, 1)],
                'status'            => ['active', 'resolved', 'pending_human'][rand(0, 2)],
                'subject'           => $topic,
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
                'last_message_at'   => $createdAt,
            ]);

            $this->insert('support_conversation_messages', [
                'support_conversation_id' => $convId,
                'sender_type'             => 'user',
                'sender_user_id'          => $client['id'],
                'body'                    => 'Bonjour, '.$topic.'. Pouvez-vous m\'aider ?',
                'created_at'              => $createdAt,
                'updated_at'              => $createdAt,
            ]);

            $this->insert('support_conversation_messages', [
                'support_conversation_id' => $convId,
                'sender_type'             => 'ai',
                'body'                    => 'Bonjour ! Je suis N\'Nan, votre assistante OVANIE. Je vais vous aider immédiatement. Pouvez-vous préciser votre numéro de commande ?',
                'created_at'              => now()->subHours(rand(1, 72))->addMinutes(1)->toDateTimeString(),
                'updated_at'              => now()->subHours(rand(1, 72))->addMinutes(1)->toDateTimeString(),
            ]);
        }

        // Base de connaissances
        $kbArticles = [
            ['title' => 'Comment suivre ma commande ?',    'category' => 'commandes',  'content' => 'Connectez-vous à votre espace client et cliquez sur "Mes commandes". Vous verrez le statut en temps réel et le nom du livreur assigné.'],
            ['title' => 'Politique de retour OVANIE',      'category' => 'retours',    'content' => 'Vous disposez de 7 jours après réception pour déclarer un retour. Le produit doit être dans son état d\'origine.'],
            ['title' => 'Modes de paiement acceptés',      'category' => 'paiements',  'content' => 'OVANIE accepte : Wave, Orange Money (via PayDunya), et le paiement à la livraison selon la zone.'],
            ['title' => 'Délais de livraison Abidjan',     'category' => 'livraisons', 'content' => 'Dans Abidjan : 24-48h selon la commune. Zones proches : même jour possible. Hors Abidjan : 3-7 jours ouvrables.'],
            ['title' => 'Comment ouvrir une boutique ?',   'category' => 'vendeurs',   'content' => 'Cliquez sur "Ouvrir ma boutique", complétez le formulaire et uploadez vos documents. Validation sous 48-72h.'],
            ['title' => 'Résolution des litiges',          'category' => 'litiges',    'content' => 'En cas de litige, ouvrez un ticket via "Retour/Réclamation". Notre équipe intervient sous 48h.'],
        ];

        foreach ($kbArticles as $article) {
            $pubDate = now()->subDays(rand(1, 60))->toDateTimeString();
            $this->insert('support_knowledge_articles', [
                'title'        => $article['title'],
                'slug'         => Str::slug($article['title']),
                'category'     => $article['category'],
                'content'      => $article['content'],
                'status'       => 'published',
                'created_by'   => $agent,
                'approved_by'  => $agent,
                'published_at' => $pubDate,
                'created_at'   => $pubDate,
                'updated_at'   => $pubDate,
            ]);
        }

        // Appels support
        for ($i = 0; $i < 8; $i++) {
            $client    = $clients[array_rand($clients)];
            $startedAt = now()->subHours(rand(0, 72));
            $this->insert('support_calls', [
                'reference'         => 'CALL-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'from_number'       => $client['phone'],
                'to_number'         => '+2250161781818',
                'direction'         => rand(0, 1) ? 'inbound' : 'outbound',
                'status'            => ['completed', 'missed', 'in_progress'][rand(0, 2)],
                'duration_seconds'  => rand(30, 600),
                'handled_by'        => $agent,
                'requester_user_id' => $client['id'],
                'provider'          => 'demo',
                'started_at'        => $startedAt->toDateTimeString(),
                'ended_at'          => $startedAt->copy()->addSeconds(rand(60, 600))->toDateTimeString(),
                'summary'           => 'Appel concernant une commande en cours.',
                'created_at'        => $startedAt->toDateTimeString(),
                'updated_at'        => $startedAt->toDateTimeString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. LIVREURS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedDrivers(): void
    {
        $driversData = [
            ['name' => 'Kouamé Yao',    'phone' => '0701123456', 'vehicle' => 'Camion 3T (ABJ-1234-AB)'],
            ['name' => 'Traoré Brice',  'phone' => '0545090123', 'vehicle' => 'Moto Tokyo (5567-GJ01)'],
            ['name' => 'Adama Koné',    'phone' => '0777665544', 'vehicle' => 'Fourgonnette Citroën (9801-HH01)'],
            ['name' => 'Bionnou Mensah','phone' => '0123456789', 'vehicle' => 'Camion 5T (2244-CK01)'],
            ['name' => 'Diallo Seydou', 'phone' => '0756789012', 'vehicle' => 'Moto Yamaha (3301-AB01)'],
            ['name' => 'Koné Awa',      'phone' => '0701234567', 'vehicle' => 'Fourgonnette Ford (7812-CE01)'],
        ];

        $communes = array_keys($this->communes);

        foreach ($driversData as $dd) {
            $commune = $communes[array_rand($communes)];
            $coords  = $this->communes[$commune];
            $this->insert('delivery_drivers', [
                'name'      => $dd['name'],
                'phone'     => $dd['phone'],
                'vehicle'   => $dd['vehicle'],
                'zone'      => $commune,
                'status'    => ['available', 'busy', 'offline'][rand(0, 2)],
                'latitude'  => $coords['lat'] + (rand(-5, 5) / 1000),
                'longitude' => $coords['lng'] + (rand(-5, 5) / 1000),
                'is_active' => 1,
                'is_online' => rand(0, 1),
                'password'  => Hash::make('Livreur@2024!'),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. PARAMÈTRES
    // ─────────────────────────────────────────────────────────────────────────
    private function seedSettings(): void
    {
        $settings = [
            'site_name'              => 'OVANIE',
            'site_tagline'           => 'Le marché en ligne des matériaux de construction en Côte d\'Ivoire',
            'contact_email'          => 'contact@ovanie.com',
            'contact_phone'          => '01 61 78 18 18',
            'whatsapp_number'        => '+2250161781818',
            'commission_rate'        => '5',
            'min_order_amount'       => '5000',
            'currency'               => 'XOF',
            'country_code'           => 'CI',
            'timezone'               => 'Africa/Abidjan',
        ];

        foreach ($settings as $key => $value) {
            try {
                DB::table('settings')->updateOrInsert(['key' => $key], [
                    'key'        => $key,
                    'value'      => $value,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            } catch (\Throwable) {}
        }

        // Newsletter
        foreach (['newsletter1@gmail.com', 'newsletter2@gmail.com', 'pro@construction-ci.com'] as $email) {
            $this->insert('newsletter_subscribers', ['email' => $email]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. PROMOTIONS & BANNIÈRES
    // ─────────────────────────────────────────────────────────────────────────
    private function seedPromotions(): void
    {
        $this->insert('promotions', [
            'code'       => 'CIMENT10',
            'type'       => 'percent',
            'value'      => 10,
            'starts_at'  => now()->subDays(2)->toDateTimeString(),
            'ends_at'    => now()->addDays(5)->toDateTimeString(),
            'is_active'  => 1,
        ]);

        $this->insert('promotions', [
            'code'       => 'BIENVENUE5000',
            'type'       => 'fixed',
            'value'      => 5000,
            'starts_at'  => now()->subDays(30)->toDateTimeString(),
            'ends_at'    => now()->addDays(60)->toDateTimeString(),
            'is_active'  => 1,
        ]);

        $this->insert('banners', [
            'title'     => 'Matériaux BTP - Livraison Express Abidjan',
            'image'     => 'demo/banner-placeholder.jpg',
            'link'      => '/catalog',
            'zone'      => 'home_top',
            'is_active' => 1,
            'position'  => 1,
            'starts_at' => now()->subDays(7)->toDateTimeString(),
            'ends_at'   => now()->addDays(30)->toDateTimeString(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AFFICHAGE DES IDENTIFIANTS
    // ─────────────────────────────────────────────────────────────────────────
    private function printCredentials(): void
    {
        $lines = [
            '',
            '╔══════════════════════════════════════════════════════════════╗',
            '║          IDENTIFIANTS DE DÉMONSTRATION OVANIE                ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  URL STAFF   : /administration/login                         ║',
            '║  URL CLIENT  : /login                                        ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  👑 ADMIN                                                     ║',
            '║     admin@ovanie.com          / Admin@2024!                  ║',
            '║     directrice@ovanie.com     / Admin@2024!                  ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  💼 COMMERCIAL                                                ║',
            '║     commercial1@ovanie.com    / Commercial@2024!             ║',
            '║     commercial2@ovanie.com    / Commercial@2024!             ║',
            '║     commercial3@ovanie.com    / Commercial@2024!             ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  🚚 LOGISTIQUE                                                ║',
            '║     logistique1@ovanie.com    / Logistique@2024!             ║',
            '║     logistique2@ovanie.com    / Logistique@2024!             ║',
            '║     logistique3@ovanie.com    / Logistique@2024!             ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  🎧 SUPPORT                                                   ║',
            '║     support1@ovanie.com       / Support@2024!                ║',
            '║     support2@ovanie.com       / Support@2024!                ║',
            '║     support3@ovanie.com       / Support@2024!                ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  🏪 VENDEUR                                                   ║',
            '║     vendeur1@ovanie.com       / Vendeur@2024!  (CKBAT BTP)   ║',
            '║     vendeur2@ovanie.com       / Vendeur@2024!  (SOFITEL)     ║',
            '║     vendeur3@ovanie.com       / Vendeur@2024!  (KONE ELECTRO)║',
            '║     vendeur4@ovanie.com       / Vendeur@2024!  (DECO BOIS)   ║',
            '║     vendeur_pending@ovanie.com/ Vendeur@2024!  (En attente)  ║',
            '╠══════════════════════════════════════════════════════════════╣',
            '║  👥 CLIENT                                                    ║',
            '║     client1@ovanie.com        / Client@2024!                 ║',
            '║     client2  ...  client10    / Client@2024!                 ║',
            '╚══════════════════════════════════════════════════════════════╝',
        ];

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }
}
