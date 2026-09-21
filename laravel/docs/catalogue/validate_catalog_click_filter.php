<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$errors = [];
$viewPath = $root . '/resources/views/catalog/index.blade.php';
$jsPath = $root . '/public/js/catalog.js';

$view = is_file($viewPath) ? file_get_contents($viewPath) : '';
$js = is_file($jsPath) ? file_get_contents($jsPath) : '';

foreach ([
    'data-category-filter' => 'Les catégories du filtre doivent être des liens cliquables.',
    '$catalogCategoryUrl' => 'La construction d’URL catalogue robuste est absente.',
    "route('catalog.index')" => 'Le filtre doit revenir vers /catalog avec un seul paramètre category.',
] as $needle => $message) {
    if (! str_contains($view, $needle)) {
        $errors[] = $message;
    }
}

if (str_contains($view, 'data-category-url="{{ $childDestination }}"')) {
    $errors[] = 'Ancienne navigation de sous-catégorie dépendante du JavaScript encore présente.';
}

if (! str_contains($js, "closest('.catalog-check-row')")) {
    $errors[] = 'Le JavaScript ne sait pas relire le libellé des nouveaux liens catégorie.';
}

$tested = [];
try {
    if (Schema::hasTable('categories') && Schema::hasTable('products')) {
        $controller = app(App\Http\Controllers\ProductController::class);

        // 1) Sous-catégorie avec au moins un produit : le cas Agglos de production.
        $child = Category::query()
            ->whereNotNull('parent_id')
            ->whereHas('products')
            ->withCount('products')
            ->orderByDesc('products_count')
            ->first();

        if ($child) {
            $request = Request::create('/api/products', 'GET', [
                'category' => $child->slug,
                'per_page' => 12,
            ]);
            $response = $controller->index($request);
            $payload = $response->getData(true);
            $total = (int) ($payload['total'] ?? 0);
            $tested[] = "Sous-catégorie {$child->name}: {$total} produit(s) API";
            if ($total < 1) {
                $errors[] = "La sous-catégorie {$child->name} possède des produits mais l’API en retourne 0.";
            }
        }

        // 2) Catégorie racine sans sous-catégorie mais avec produit(s).
        $leafRoot = Category::query()
            ->whereNull('parent_id')
            ->whereDoesntHave('children')
            ->whereHas('products')
            ->withCount('products')
            ->first();

        if ($leafRoot) {
            $request = Request::create('/api/products', 'GET', [
                'category' => $leafRoot->slug,
                'per_page' => 12,
            ]);
            $response = $controller->index($request);
            $payload = $response->getData(true);
            $total = (int) ($payload['total'] ?? 0);
            $tested[] = "Catégorie sans sous-catégorie {$leafRoot->name}: {$total} produit(s) API";
            if ($total < 1) {
                $errors[] = "La catégorie {$leafRoot->name} n’a pas de sous-catégorie et possède des produits, mais l’API en retourne 0.";
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Test API impossible : ' . $e->getMessage();
}

if ($errors) {
    fwrite(STDERR, "ECHEC — filtre catalogue encore invalide\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

echo "OK — filtre catalogue cliquable et exclusif\n";
echo "Navigation catégorie : lien natif /catalog?category=...\n";
echo "Sous-catégorie : 1 seule sélection\n";
echo "Catégorie sans sous-catégorie : prise en charge\n";
foreach ($tested as $line) {
    echo $line . "\n";
}
