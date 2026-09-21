<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\GlobalLegacyProductCategoryClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReclassifyLegacyProductsGlobally extends Command
{
    protected $signature = 'ovanie:reclassify-products-global
        {--apply : Appliquer réellement les propositions sûres}
        {--limit=0 : Limiter le nombre de produits analysés}
        {--product= : Analyser uniquement un ID produit}
        {--show-all : Afficher également les produits ambigus}
        {--show-reasons : Afficher les raisons de la proposition après le tableau}';

    protected $description = 'Reclasse les anciens produits en corrigeant simultanément la catégorie principale et la sous-catégorie.';

    public function handle(GlobalLegacyProductCategoryClassifier $classifier): int
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
        $showAll = (bool) $this->option('show-all');
        $showReasons = (bool) $this->option('show-reasons');
        $limit = max(0, (int) $this->option('limit'));
        $productId = trim((string) $this->option('product'));

        $roots = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->withCount(['children' => fn ($query) => $query->active()])
            ->get()
            ->filter(fn (Category $root) => (int) $root->children_count > 0)
            ->values();

        $rootIds = $roots->pluck('id')->map(fn ($id) => (int) $id)->all();

        $leafCategories = Category::query()
            ->active()
            ->whereNotNull('parent_id')
            ->whereIn('parent_id', $rootIds)
            ->with('parent')
            ->ordered()
            ->get()
            ->filter(fn (Category $leaf) => $leaf->parent !== null)
            ->values();

        if ($rootIds === [] || $leafCategories->isEmpty()) {
            $this->warn('Aucune hiérarchie catégorie principale / sous-catégorie exploitable.');
            return self::SUCCESS;
        }

        $query = Product::query()
            ->with('category')
            // On cible uniquement les produits hérités encore rangés directement
            // dans une catégorie principale. Les produits déjà en sous-catégorie
            // ne sont pas touchés par cette commande.
            ->whereIn('category_id', $rootIds)
            ->orderBy('id');

        if ($productId !== '') {
            $query->whereKey((int) $productId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get();

        $this->newLine();
        $this->info($apply
            ? 'MODE APPLICATION — catégorie principale + sous-catégorie seront corrigées uniquement pour les propositions sûres.'
            : 'MODE SIMULATION — aucune donnée ne sera modifiée.');
        $this->newLine();

        $analysed = 0;
        $confident = 0;
        $ambiguous = 0;
        $updated = 0;
        $rows = [];
        $reasonRows = [];

        foreach ($products as $product) {
            $analysed++;
            $current = $product->category;
            $suggestion = $classifier->suggest($product, $leafCategories);
            $bestChild = $suggestion['best_category'];
            $bestParent = $suggestion['best_parent'];

            if ($suggestion['confident'] && $suggestion['category'] && $suggestion['parent']) {
                $confident++;
                $target = $suggestion['category'];
                $targetParent = $suggestion['parent'];

                if ($apply) {
                    $changed = DB::transaction(function () use ($product, $target): int {
                        return Product::query()
                            ->whereKey($product->id)
                            ->where('category_id', $product->category_id)
                            ->update(['category_id' => $target->id]);
                    });

                    if ($changed > 0) {
                        $updated++;
                    }
                }

                $rows[] = [
                    $product->id,
                    mb_strimwidth((string) $product->name, 0, 34, '…'),
                    mb_strimwidth((string) ($current?->name ?? '—'), 0, 22, '…'),
                    mb_strimwidth((string) $targetParent->name, 0, 22, '…'),
                    mb_strimwidth((string) $target->name, 0, 25, '…'),
                    $suggestion['score'],
                    $suggestion['margin'],
                    $apply ? 'RECLASSE' : 'PROPOSE',
                ];
            } else {
                $ambiguous++;

                if ($showAll) {
                    $rows[] = [
                        $product->id,
                        mb_strimwidth((string) $product->name, 0, 34, '…'),
                        mb_strimwidth((string) ($current?->name ?? '—'), 0, 22, '…'),
                        mb_strimwidth((string) ($bestParent?->name ?? '—'), 0, 22, '…'),
                        mb_strimwidth((string) ($bestChild?->name ?? '—'), 0, 25, '…'),
                        $suggestion['score'],
                        $suggestion['margin'],
                        'A VERIFIER',
                    ];
                }
            }

            if ($showReasons && ($suggestion['confident'] || $showAll)) {
                $reasonRows[] = [
                    'id' => $product->id,
                    'product' => (string) $product->name,
                    'reasons' => $suggestion['reasons'],
                ];
            }
        }

        if ($rows !== []) {
            $this->table(
                ['ID', 'Produit', 'Cat. actuelle', 'Cat. proposée', 'Sous-catégorie', 'Score', 'Écart', 'Action'],
                array_slice($rows, 0, 250)
            );

            if (count($rows) > 250) {
                $this->line('Affichage limité aux 250 premières lignes. Utilisez --product=ID pour examiner un produit précis.');
            }
        }

        if ($showReasons && $reasonRows !== []) {
            $this->newLine();
            $this->info('Raisons des propositions :');
            foreach (array_slice($reasonRows, 0, 80) as $row) {
                $this->line('#'.$row['id'].' '.$row['product']);
                foreach ($row['reasons'] as $reason) {
                    $this->line('  - '.$reason);
                }
            }
        }

        $this->newLine();
        $this->line('Produits hérités analysés : '.$analysed);
        $this->line('Propositions sûres : '.$confident);
        $this->line('À vérifier manuellement : '.$ambiguous);
        if ($apply) {
            $this->line('Produits réellement reclassés : '.$updated);
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Aucune donnée modifiée. Contrôlez les propositions avant toute utilisation de --apply.');
        }

        return self::SUCCESS;
    }
}
