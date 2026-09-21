<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * The 8 official OVANIE categories with their subcategories.
     * Running this seeder clears the categories table and re-inserts
     * everything with stable IDs so product FK references stay valid.
     */
    public function run(): void
    {
        if (!Schema::hasTable('categories')) {
            return;
        }

        // 8 top-level categories with fixed IDs 1–8.
        $parents = [
            ['id' => 1, 'name' => 'Matériaux gros œuvre',   'slug' => 'materiaux-gros-oeuvre',  'sort' => 10],
            ['id' => 2, 'name' => 'Matériaux écologiques',  'slug' => 'materiaux-ecologiques',   'sort' => 20],
            ['id' => 3, 'name' => 'Outillage & Équipement', 'slug' => 'outillage-equipement',    'sort' => 30],
            ['id' => 4, 'name' => 'Matériaux de finition',  'slug' => 'materiaux-de-finition',   'sort' => 40],
            ['id' => 5, 'name' => 'Énergie solaire',         'slug' => 'energie-solaire',         'sort' => 50],
            ['id' => 6, 'name' => 'Électricité & Plomberie','slug' => 'electricite-plomberie',   'sort' => 60],
            ['id' => 7, 'name' => 'Nos reconditionnés',     'slug' => 'nos-reconditionnes',      'sort' => 70],
            ['id' => 8, 'name' => 'Carte cadeau Ovanie',    'slug' => 'carte-cadeau-ovanie',     'sort' => 80],
        ];

        // Subcategories keyed by parent ID (1–8).
        $children = [
            1 => [
                'Ciment', 'Fer à béton', 'Gravier', 'Sable', 'Briques', 'Blocs béton',
                'Agglos', 'Hourdis', 'Treillis soudés', 'Chaux', 'Béton prêt à l\'emploi',
                'Étanchéité gros œuvre',
            ],
            2 => [
                'Briques écologiques', 'Blocs de terre comprimée', 'Peintures écologiques',
                'Enduits naturels', 'Isolants écologiques', 'Bois traités écologiques',
                'Revêtements recyclés', 'Matériaux recyclés',
                'Solutions de construction durable', 'Produits basse consommation',
            ],
            3 => [
                'Outillage à main', 'Outillage électroportatif', 'Échelles & escabeaux',
                'Équipements de chantier', 'Équipements de protection (EPI)',
                'Machines de chantier', 'Mesure & traçage', 'Coupe & perçage', 'Soudure',
                'Nettoyage chantier', 'Levage & manutention', 'Quincaillerie',
            ],
            4 => [
                'Carrelage', 'Faïence', 'Peinture', 'Enduits & plâtre', 'Revêtements muraux',
                'Revêtements de sol', 'Faux plafonds', 'Portes intérieures', 'Fenêtres',
                'Sanitaires de finition', 'Robinetterie', 'Décoration intérieure',
            ],
            5 => [
                'Panneaux solaires', 'Batteries solaires', 'Onduleurs solaires', 'Régulateurs',
                'Kits solaires', 'Lampadaires solaires', 'Projecteurs solaires', 'Pompes solaires',
                'Accessoires de fixation solaire', 'Câbles solaires', 'Coffrets de protection solaire',
            ],
            6 => [
                'Câbles & fils électriques', 'Disjoncteurs', 'Tableaux électriques',
                'Prises & interrupteurs', 'Luminaires', 'Gaines & conduits',
                'Protection électrique', 'Accessoires électriques', 'Tuyaux', 'Raccords',
                'Vannes', 'Robinets', 'Éviers & lavabos', 'WC & sanitaires', 'Pompes à eau',
                'Réservoirs', 'Accessoires plomberie',
            ],
            7 => [
                'Groupes électrogènes reconditionnés', 'Outillage reconditionné',
                'Matériel électrique reconditionné', 'Équipements solaires reconditionnés',
                'Pompes reconditionnées', 'Machines de chantier reconditionnées',
                'Accessoires reconditionnés',
            ],
            // ID 8 = Carte cadeau Ovanie → no subcategories
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('categories')->truncate();

        $now = now();

        // Insert parent categories with fixed IDs.
        foreach ($parents as $cat) {
            $payload = ['id' => $cat['id'], 'parent_id' => null];
            if (Schema::hasColumn('categories', 'name'))       $payload['name']       = $cat['name'];
            if (Schema::hasColumn('categories', 'slug'))       $payload['slug']       = $cat['slug'];
            if (Schema::hasColumn('categories', 'sort_order')) $payload['sort_order'] = $cat['sort'];
            if (Schema::hasColumn('categories', 'status'))     $payload['status']     = 'actif';
            if (Schema::hasColumn('categories', 'is_active'))  $payload['is_active']  = true;
            if (Schema::hasColumn('categories', 'created_at')) $payload['created_at'] = $now;
            if (Schema::hasColumn('categories', 'updated_at')) $payload['updated_at'] = $now;
            DB::table('categories')->insert($payload);
        }

        // Insert subcategories (auto-increment IDs, starting at 9).
        foreach ($children as $parentId => $names) {
            foreach (array_values($names) as $sort => $name) {
                $payload = ['parent_id' => $parentId];
                if (Schema::hasColumn('categories', 'name'))       $payload['name']       = $name;
                if (Schema::hasColumn('categories', 'slug'))       $payload['slug']       = Str::slug($name);
                if (Schema::hasColumn('categories', 'sort_order')) $payload['sort_order'] = ($sort + 1) * 10;
                if (Schema::hasColumn('categories', 'status'))     $payload['status']     = 'actif';
                if (Schema::hasColumn('categories', 'is_active'))  $payload['is_active']  = true;
                if (Schema::hasColumn('categories', 'created_at')) $payload['created_at'] = $now;
                if (Schema::hasColumn('categories', 'updated_at')) $payload['updated_at'] = $now;
                DB::table('categories')->insert($payload);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
