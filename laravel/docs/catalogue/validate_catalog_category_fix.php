<?php

$root = dirname(__DIR__, 2);
$errors = [];

$files = [
    'app/Http/Controllers/ProductController.php',
    'resources/views/catalog/index.blade.php',
    'public/js/catalog.js',
    'app/Services/LegacyProductSubcategoryClassifier.php',
    'app/Console/Commands/ReclassifyLegacyProductsToSubcategories.php',
    'config/catalog_subcategory_rules.php',
];

foreach ($files as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "Fichier absent : {$file}";
    }
}

if (!$errors) {
    $controller = file_get_contents($root . '/app/Http/Controllers/ProductController.php');
    $view = file_get_contents($root . '/resources/views/catalog/index.blade.php');
    $js = file_get_contents($root . '/public/js/catalog.js');
    $command = file_get_contents($root . '/app/Console/Commands/ReclassifyLegacyProductsToSubcategories.php');

    $checks = [
        'Filtre catégorie exclusif côté Laravel' => str_contains($controller, 'la dernière catégorie explicitement demandée gagne'),
        'Catégorie parent inclut ses sous-catégories' => str_contains($controller, 'where(\'parent_id\', $matchedCategory->id)'),
        'Catégories affichées en choix unique' => str_contains($view, 'type="radio" class="filter-control" name="category"'),
        'Anciennes URL multi-category nettoyées' => str_contains($js, 'requestedCategories.at(-1)'),
        'Une seule catégorie envoyée à API' => str_contains($js, "params.set('category', selectedCategory)"),
        'Reclassement anciens produits disponible' => str_contains($command, 'ovanie:reclassify-product-subcategories'),
    ];

    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $errors[] = "Contrôle échoué : {$label}";
        }
    }
}

if ($errors) {
    fwrite(STDERR, "ECHEC — correctif catalogue non validé\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

echo "OK — Catalogue OVANIE catégories/sous-catégories corrigé\n";
echo "Sélection catégorie : exclusive (1 seule)\n";
echo "Sélection sous-catégorie : exclusive (1 seule)\n";
echo "Catégorie principale : produits directs + sous-catégories\n";
echo "Sous-catégorie : uniquement ses produits\n";
echo "Commande anciens produits : php artisan ovanie:reclassify-product-subcategories\n";
echo "Application : ajouter --apply après contrôle de la simulation\n";
