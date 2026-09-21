<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\LegacyProductSubcategoryClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReclassifyLegacyProductsToSubcategories extends Command
{
    protected $signature = 'ovanie:reclassify-product-subcategories
        {--apply : Appliquer réellement les reclassements sûrs}
        {--parent= : Limiter à un slug de catégorie principale}
        {--limit=0 : Limiter le nombre de produits analysés}
        {--show-all : Afficher aussi les produits sans proposition sûre}';

    protected $description = 'Classe les anciens produits encore rattachés aux catégories principales dans leurs sous-catégories correspondantes.';

    public function handle(LegacyProductSubcategoryClassifier $classifier): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            $this->error('Tables products/categories introuvables.');
            return self::FAILURE;
        }

        if (! Schema::hasColumn('categories', 'parent_id')) {
            $this->error('La hiérarchie parent_id des catégories est absente.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $parentSlug = trim((string) $this->option('parent'));
        $limit = max(0, (int) $this->option('limit'));
        $showAll = (bool) $this->option('show-all');

        $parents = Category::query()
            ->whereNull('parent_id')
            ->when($parentSlug !== '', fn ($query) => $query->where('slug', $parentSlug))
            ->with(['children' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->filter(fn (Category $category) => $category->children->isNotEmpty())
            ->values();

        if ($parents->isEmpty()) {
            $this->warn('Aucune catégorie principale avec sous-catégories trouvée.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($apply
            ? 'MODE APPLICATION — seuls les classements sûrs seront enregistrés.'
            : 'MODE SIMULATION — aucune donnée ne sera modifiée.');
        $this->newLine();

        $analysed = 0;
        $confident = 0;
        $updated = 0;
        $ambiguous = 0;
        $rows = [];

        foreach ($parents as $parent) {
            $query = Product::query()
                ->where('category_id', $parent->id)
                ->orderBy('id');

            if ($limit > 0) {
                $remaining = $limit - $analysed;
                if ($remaining <= 0) {
                    break;
                }
                $query->limit($remaining);
            }

            foreach ($query->get() as $product) {
                $analysed++;
                $suggestion = $classifier->suggest($product, $parent->children);
                $best = $suggestion['best_category'];

                if ($suggestion['confident'] && $suggestion['category']) {
                    $confident++;
                    $target = $suggestion['category'];

                    if ($apply) {
                        DB::transaction(function () use ($product, $target): void {
                            Product::query()
                                ->whereKey($product->id)
                                ->where('category_id', $product->category_id)
                                ->update(['category_id' => $target->id]);
                        });
                        $updated++;
                    }

                    $rows[] = [
                        $product->id,
                        mb_strimwidth((string) $product->name, 0, 42, '…'),
                        $parent->name,
                        $target->name,
                        $suggestion['score'],
                        $suggestion['margin'],
                        $apply ? 'RECLASSE' : 'PROPOSE',
                    ];
                } else {
                    $ambiguous++;
                    if ($showAll) {
                        $rows[] = [
                            $product->id,
                            mb_strimwidth((string) $product->name, 0, 42, '…'),
                            $parent->name,
                            $best?->name ?? '—',
                            $suggestion['score'],
                            $suggestion['margin'],
                            'A VERIFIER',
                        ];
                    }
                }
            }
        }

        if ($rows !== []) {
            $this->table(
                ['ID', 'Produit', 'Catégorie actuelle', 'Sous-catégorie', 'Score', 'Écart', 'Action'],
                array_slice($rows, 0, 200)
            );

            if (count($rows) > 200) {
                $this->line('Affichage limité aux 200 premières lignes.');
            }
        }

        $this->newLine();
        $this->line('Produits analysés : ' . $analysed);
        $this->line('Classements sûrs : ' . $confident);
        $this->line('À vérifier manuellement : ' . $ambiguous);
        if ($apply) {
            $this->line('Produits réellement reclassés : ' . $updated);
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Aucune donnée modifiée. Si les propositions sont correctes, relancez avec --apply.');
        }

        return self::SUCCESS;
    }
}
