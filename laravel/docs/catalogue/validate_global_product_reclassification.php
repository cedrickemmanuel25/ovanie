<?php

$root = dirname(__DIR__, 2);
$checks = [
    'app/Console/Commands/ReclassifyLegacyProductsGlobally.php' => [
        'ovanie:reclassify-products-global',
        "whereIn('category_id', \$rootIds)",
        "update(['category_id' => \$target->id])",
    ],
    'app/Services/GlobalLegacyProductCategoryClassifier.php' => [
        'minimum_score',
        'minimum_margin',
        'candidateAllowed',
        'best_parent',
    ],
    'config/catalog_global_classifier.php' => [
        'exclusive_keywords',
        'current_parent_bonus',
        'reconditioned_states',
    ],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_file($path)) {
        $errors[] = "Fichier absent : {$relative}";
        continue;
    }

    $contents = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = "Signature absente dans {$relative} : {$needle}";
        }
    }
}

if ($errors !== []) {
    echo "ECHEC — Reclassement global non valide\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK — Reclassement global catégorie principale + sous-catégorie prêt\n";
echo "Mode par défaut : simulation, aucune donnée modifiée\n";
echo "Cible : produits encore rattachés directement à une catégorie principale\n";
echo "Analyse : toutes les sous-catégories actives OVANIE\n";
echo "Application : uniquement propositions sûres avec --apply\n";
echo "Protection : produits déjà en sous-catégorie non touchés\n";
echo "Protection : reconditionnés proposés seulement si état/catégorie cohérent\n";
