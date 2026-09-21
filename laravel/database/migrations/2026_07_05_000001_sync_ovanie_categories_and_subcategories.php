<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Catégories principales validées par OVANIE et leurs sous-catégories.
     * La carte cadeau reste sans sous-catégorie.
     */
    private array $catalog = [
        'Matériaux gros œuvres' => [
            'Ciment',
            'Fer à béton',
            'Gravier',
            'Sable',
            'Briques',
            'Blocs béton',
            'Agglos',
            'Hourdis',
            'Treillis soudés',
            'Chaux',
            'Béton prêt à l’emploi',
            'Étanchéité gros œuvre',
        ],
        'Matériaux écologique' => [
            'Briques écologiques',
            'Blocs de terre comprimée',
            'Peintures écologiques',
            'Enduits naturels',
            'Isolants écologiques',
            'Bois traités écologiques',
            'Revêtements recyclés',
            'Matériaux recyclés',
            'Solutions de construction durable',
            'Produits basse consommation',
        ],
        'Outillage & équipement' => [
            'Outillage à main',
            'Outillage électroportatif',
            'Échelles & escabeaux',
            'Équipements de chantier',
            'Équipements de protection (EPI)',
            'Machines de chantier',
            'Mesure & traçage',
            'Coupe & perçage',
            'Soudure',
            'Nettoyage chantier',
            'Levage & manutention',
            'Quincaillerie',
        ],
        'Matériaux de finition' => [
            'Carrelage',
            'Faïence',
            'Peinture',
            'Enduits & plâtre',
            'Revêtements muraux',
            'Revêtements de sol',
            'Faux plafonds',
            'Portes intérieures',
            'Fenêtres',
            'Sanitaires de finition',
            'Robinetterie',
            'Décoration intérieure',
        ],
        'Energie solaire' => [
            'Panneaux solaires',
            'Batteries solaires',
            'Onduleurs solaires',
            'Régulateurs',
            'Kits solaires',
            'Lampadaires solaires',
            'Projecteurs solaires',
            'Pompes solaires',
            'Accessoires de fixation solaire',
            'Câbles solaires',
            'Coffrets de protection solaire',
        ],
        'Électricité & plomberie' => [
            'Câbles & fils électriques',
            'Disjoncteurs',
            'Tableaux électriques',
            'Prises & interrupteurs',
            'Luminaires',
            'Gaines & conduits',
            'Protection électrique',
            'Accessoires électriques',
            'Tuyaux',
            'Raccords',
            'Vannes',
            'Robinets',
            'Éviers & lavabos',
            'WC & sanitaires',
            'Pompes à eau',
            'Réservoirs',
            'Accessoires plomberie',
        ],
        'Nos reconditionnée' => [
            'Groupes électrogènes reconditionnés',
            'Outillage reconditionné',
            'Matériel électrique reconditionné',
            'Équipements solaires reconditionnés',
            'Pompes reconditionnées',
            'Machines de chantier reconditionnées',
            'Accessoires reconditionnés',
        ],
        'Carte cadeau OVANIE' => [],
    ];

    private array $legacySlugs = [
        'materiaux-gros-oeuvres' => ['materiaux-gros-oeuvre', 'gros-oeuvre-maconnerie', 'materiaux-gros-oeuvres'],
        'materiaux-ecologique' => ['materiaux-ecologiques', 'materiaux-ecologique'],
        'outillage-equipement' => ['outillage', 'materiel-outillage', 'outillage-equipement', 'equipement-de-chantier'],
        'materiaux-de-finition' => ['materiaux-finition', 'finition-deco', 'materiaux-de-finition'],
        'energie-solaire' => ['energie-solaire-domotique', 'energie-solaire'],
        'electricite-plomberie' => ['electricite-plomberie'],
        'nos-reconditionnee' => ['nos-reconditionnes', 'nos-reconditionnee'],
        'carte-cadeau-ovanie' => ['carte-cadeau-ovanie'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            return;
        }

        DB::transaction(function (): void {
            $sortOrder = 10;

            foreach ($this->catalog as $parentName => $children) {
                $parentSlug = Str::slug($parentName);
                $parentId = $this->upsertParent(
                    $parentName,
                    $parentSlug,
                    $this->legacySlugs[$parentSlug] ?? [$parentSlug],
                    $sortOrder
                );

                $childSort = 1;
                foreach ($children as $childName) {
                    $this->upsertChild($parentId, $childName, $childSort);
                    $childSort++;
                }

                $sortOrder += 10;
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('categories') || !Schema::hasColumn('categories', 'slug')) {
            return;
        }

        $childSlugs = collect($this->catalog)
            ->flatten()
            ->map(fn (string $name) => Str::slug($name))
            ->values()
            ->all();

        if ($childSlugs !== []) {
            DB::table('categories')->whereIn('slug', $childSlugs)->delete();
        }
    }

    private function upsertParent(string $name, string $slug, array $legacySlugs, int $sortOrder): int
    {
        $query = DB::table('categories');

        if (Schema::hasColumn('categories', 'slug')) {
            $query->whereIn('slug', array_values(array_unique(array_merge([$slug], $legacySlugs))));
        } elseif (Schema::hasColumn('categories', 'name')) {
            $query->where('name', $name);
        }

        $matches = $query->orderBy('id')->get();
        $canonical = $matches->firstWhere('slug', $slug) ?? $matches->first();

        if (!$canonical) {
            $id = DB::table('categories')->insertGetId($this->categoryPayload($name, $slug, null, 1, $sortOrder));
        } else {
            $id = (int) $canonical->id;
            DB::table('categories')->where('id', $id)->update($this->categoryPayload($name, $slug, null, 1, $sortOrder, false));
        }

        foreach ($matches as $duplicate) {
            if ((int) $duplicate->id === $id) {
                continue;
            }

            $duplicateId = (int) $duplicate->id;

            if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
                DB::table('products')->where('category_id', $duplicateId)->update(['category_id' => $id]);
            }

            if (Schema::hasColumn('categories', 'parent_id')) {
                DB::table('categories')->where('parent_id', $duplicateId)->update(['parent_id' => $id]);
            }

            DB::table('categories')->where('id', $duplicateId)->delete();
        }

        return $id;
    }

    private function upsertChild(int $parentId, string $name, int $sortOrder): void
    {
        $slug = Str::slug($name);
        $query = DB::table('categories');

        if (Schema::hasColumn('categories', 'slug')) {
            $query->where('slug', $slug);
        } else {
            $query->where('name', $name);
        }

        $existing = $query->first();
        $payload = $this->categoryPayload($name, $slug, $parentId, 2, $sortOrder, !$existing);

        if ($existing) {
            DB::table('categories')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('categories')->insert($payload);
        }
    }

    private function categoryPayload(
        string $name,
        string $slug,
        ?int $parentId,
        int $level,
        int $sortOrder,
        bool $withCreatedAt = true
    ): array {
        $payload = [];

        if (Schema::hasColumn('categories', 'name')) {
            $payload['name'] = $name;
        }
        if (Schema::hasColumn('categories', 'nom')) {
            $payload['nom'] = $name;
        }
        if (Schema::hasColumn('categories', 'title')) {
            $payload['title'] = $name;
        }
        if (Schema::hasColumn('categories', 'slug')) {
            $payload['slug'] = $slug;
        }
        if (Schema::hasColumn('categories', 'parent_id')) {
            $payload['parent_id'] = $parentId;
        }
        if (Schema::hasColumn('categories', 'level')) {
            $payload['level'] = $level;
        }
        if (Schema::hasColumn('categories', 'sort_order')) {
            $payload['sort_order'] = $sortOrder;
        }
        if (Schema::hasColumn('categories', 'is_active')) {
            $payload['is_active'] = true;
        }
        if (Schema::hasColumn('categories', 'status')) {
            $payload['status'] = 'actif';
        }
        if (Schema::hasColumn('categories', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        if ($withCreatedAt && Schema::hasColumn('categories', 'created_at')) {
            $payload['created_at'] = now();
        }

        return $payload;
    }
};
