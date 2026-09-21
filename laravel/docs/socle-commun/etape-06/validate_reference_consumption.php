<?php

declare(strict_types=1);

$laravelRoot = dirname(__DIR__, 3);
$workspaceRoot = dirname($laravelRoot);
$errors = [];
$warnings = [];

function firstExistingDir(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function requireMarkers(string $file, array $markers, array &$errors, string $label): void
{
    if (! is_file($file)) {
        $errors[] = "Fichier absent pour {$label} : {$file}";
        return;
    }

    $content = file_get_contents($file) ?: '';
    foreach ($markers as $marker) {
        if (! str_contains($content, $marker)) {
            $errors[] = "Marqueur absent dans {$label} : {$marker}";
        }
    }
}

// Laravel : l'étape 4 doit être présente, car elle est la source consommée ici.
requireMarkers(
    $laravelRoot . '/routes/api.php',
    ['mobile/v1/reference-data'],
    $errors,
    'routes Laravel'
);
requireMarkers(
    $laravelRoot . '/app/Services/OvanieReferenceDataService.php',
    ['function apiPayload', "product_categories", "checkout_operators"],
    $errors,
    'OvanieReferenceDataService'
);

$mobileRootCandidates = [
    $workspaceRoot . '/ovanie-mobile',
    $workspaceRoot,
];

$mobileRoot = null;
foreach ($mobileRootCandidates as $candidate) {
    if (is_dir($candidate)) {
        $mobileRoot = $candidate;
        break;
    }
}

$projects = [
    'Client' => [
        'dirs' => [
            $workspaceRoot . '/ovanie-mobile/ovanie_app',
            $workspaceRoot . '/mobile client',
        ],
        'checks' => [
            'lib/main.dart' => ['OvanieReferenceDataStore.instance.warmUp()'],
            'lib/core/reference_data/reference_data_store.dart' => ['/mobile/v1/reference-data'],
            'lib/features/checkout/presentation/checkout_screen.dart' => ['OvanieReferenceDataStore', 'quartersByCommuneName', 'checkout_operators'],
            'lib/features/addresses/presentation/address_form_screen.dart' => ['address_types', "'other'"],
            'lib/features/payments/presentation/resume_payment_screen.dart' => ['checkout_operators'],
        ],
    ],
    'Vendeur' => [
        'dirs' => [
            $workspaceRoot . '/ovanie-mobile/ovanie_vendor',
            $workspaceRoot . '/mobile vendeur',
        ],
        'checks' => [
            'lib/main.dart' => ['OvanieReferenceDataStore.instance.warmUp()'],
            'lib/core/reference_data/reference_data_store.dart' => ['/mobile/v1/reference-data'],
            'lib/data/vendor_repository.dart' => ['product_categories', 'seller_types', 'reference_source'],
            'lib/features/products/product_form_screen.dart' => ['État du produit', 'product_states', 'selling_modes', "'selling_mode'", 'Mode de vente'],
            'lib/features/onboarding/shop_onboarding_screen.dart' => ['seller_types', 'delivery_zones', 'identity_types'],
            'lib/features/menu/profile_screens.dart' => ['shop_categories'],
        ],
    ],
    'Livreur' => [
        'dirs' => [
            $workspaceRoot . '/ovanie-mobile/ovanie_livreur',
            $workspaceRoot . '/mobile livreur ',
            $workspaceRoot . '/mobile livreur',
        ],
        'checks' => [
            'lib/main.dart' => ['OvanieReferenceDataStore.instance.warmUp()'],
            'lib/core/reference_data/reference_data_store.dart' => ['/mobile/v1/reference-data'],
            'lib/features/onboarding/onboarding_data.dart' => ['vehicle_types', 'week_days', 'communeNames'],
            'lib/features/onboarding/vehicle_categories.dart' => ['OvanieReferenceDataStore', 'vehicle_types', 'week_days'],
        ],
    ],
    'Commercial' => [
        'dirs' => [
            $workspaceRoot . '/ovanie-mobile/ovanie_commercial',
            $workspaceRoot . '/mobile commercial',
        ],
        'checks' => [
            'lib/features/shops/data/shops_service.dart' => ['/mobile/v1/reference-data', 'seller_types', 'delivery_zones'],
            'lib/features/products/data/products_service.dart' => ['/mobile/v1/reference-data', 'product_categories'],
            'lib/features/shops/models/shop_data.dart' => ['sellerTypes'],
            'lib/features/shops/presentation/open_shop_screen.dart' => ['_sellerTypeOptions', '_deliveryZoneOptions', '_identityTypeOptions'],
        ],
    ],
];

$validated = [];
$notFound = [];
foreach ($projects as $label => $project) {
    $dir = firstExistingDir($project['dirs']);
    if ($dir === null) {
        $notFound[] = $label;
        continue;
    }

    foreach ($project['checks'] as $relative => $markers) {
        requireMarkers($dir . '/' . $relative, $markers, $errors, "App {$label} / {$relative}");
    }
    $validated[] = $label;
}

if ($errors !== []) {
    echo "ECHEC — Étape 6 non validée\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    if ($notFound !== []) {
        echo '- Applications non trouvées dans ce workspace : ' . implode(', ', $notFound) . "\n";
    }
    exit(1);
}

echo "OK — Étape 6 : applications branchées sur les référentiels Laravel\n";
echo "Laravel reference-data : OK\n";
foreach ($validated as $label) {
    echo "App {$label} : OK\n";
}
if ($notFound !== []) {
    echo 'Applications non inspectées (workspace différent) : ' . implode(', ', $notFound) . "\n";
}
echo "Applications inspectées : " . count($validated) . "/4\n";
echo "Mode : Laravel prioritaire, fallback local conservé\n";
echo "Suppression des fallbacks : non (après validation fonctionnelle)\n";
