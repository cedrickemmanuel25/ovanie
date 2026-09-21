<?php

declare(strict_types=1);

$workspace = dirname(__DIR__, 4);

$mobileRootCandidates = [
    $workspace . DIRECTORY_SEPARATOR . 'OVANIE-MOBILE',
    $workspace . DIRECTORY_SEPARATOR . 'ovanie-mobile',
];
$mobileRoot = null;
foreach ($mobileRootCandidates as $candidate) {
    if (is_dir($candidate)) {
        $mobileRoot = $candidate;
        break;
    }
}

$errors = [];
if ($mobileRoot === null) {
    $errors[] = 'Dossier OVANIE-MOBILE/ovanie-mobile introuvable à côté de laravel.';
}

$apps = [
    'client' => 'ovanie_app',
    'vendor' => 'ovanie_vendor',
    'driver' => 'ovanie_livreur',
    'commercial' => 'ovanie_commercial',
];

$requiredFiles = [
    'client' => [
        'lib/core/reference_data/reference_data_store.dart',
        'lib/features/checkout/presentation/checkout_screen.dart',
        'lib/features/addresses/presentation/address_form_screen.dart',
        'lib/features/account/presentation/add_payment_method_screen.dart',
        'lib/features/payments/presentation/resume_payment_screen.dart',
    ],
    'vendor' => [
        'lib/core/reference_data/reference_data_store.dart',
        'lib/data/vendor_repository.dart',
        'lib/features/products/product_form_screen.dart',
        'lib/features/onboarding/shop_onboarding_screen.dart',
    ],
    'driver' => [
        'lib/core/reference_data/reference_data_store.dart',
        'lib/features/onboarding/onboarding_data.dart',
        'lib/features/onboarding/vehicle_categories.dart',
    ],
    'commercial' => [
        'lib/features/shops/data/shops_service.dart',
        'lib/features/products/data/products_service.dart',
        'lib/features/shops/models/shop_data.dart',
        'lib/features/shops/presentation/open_shop_screen.dart',
    ],
];

$checks = [
    'client' => [
        'lib/features/checkout/presentation/checkout_screen.dart' => [
            'must' => ['OvanieReferenceDataStore.instance.communeNames'],
            'must_not' => ["abidjan_localities.dart", 'kAbidjanCommunes', 'kAbidjanQuartiersByCommune', 'Compatibilité avec un ancien backend'],
        ],
        'lib/features/addresses/presentation/address_form_screen.dart' => [
            'must_not' => ["AddressTypeOption(code: 'home'", "AddressTypeOption(code: 'office'"],
        ],
        'lib/features/account/presentation/add_payment_method_screen.dart' => [
            'must_not' => ["PaymentOperatorOption(key: 'wave'", "PaymentOperatorOption(key: 'orange'"],
        ],
        'lib/features/payments/presentation/resume_payment_screen.dart' => [
            'must_not' => ["('wave', 'Wave')", "('orange', 'Orange Money')"],
        ],
    ],
    'vendor' => [
        'lib/data/vendor_repository.dart' => [
            'must' => ["'reference_source': 'reference-data'"],
            'must_not' => ["'/mobile/v1/vendor/meta'", "'legacy_fallback'"],
        ],
        'lib/features/products/product_form_screen.dart' => [
            'must_not' => ["'new': 'Neuf'", "'standard': 'Vente à l’unité'", "'materiau': 'Matériau de construction'"],
        ],
        'lib/features/onboarding/shop_onboarding_screen.dart' => [
            'must_not' => ["'particulier': 'Particulier'", "'abidjan': 'Abidjan'", "'cni': 'CNI'", "'orange': 'Orange Money'"],
        ],
    ],
    'driver' => [
        'lib/features/onboarding/onboarding_data.dart' => [
            'must_not' => ["'Cocody'", "'Lundi'", "'Camion 10T'"],
        ],
        'lib/features/onboarding/vehicle_categories.dart' => [
            'must_not' => ['kVehicleTypeToBackendEnum', 'kWeekDayToBackendValue'],
        ],
    ],
    'commercial' => [
        'lib/features/shops/data/shops_service.dart' => [
            'must_not' => ["/mobile/v1/commercial/shops/meta"],
        ],
        'lib/features/products/data/products_service.dart' => [
            'must_not' => ["/mobile/v1/commercial/products/meta"],
        ],
        'lib/features/shops/models/shop_data.dart' => [
            'must_not' => ["const ['Abidjan']"],
        ],
        'lib/features/shops/presentation/open_shop_screen.dart' => [
            'must' => ['itemHeight: null'],
            'must_not' => ["'abidjan': 'Abidjan'", "'cni': 'CNI'", "'post_delivery': 'Paiement après livraison (72h)'"],
        ],
    ],
];

$foundApps = 0;
$foundFiles = 0;
$totalFiles = 0;

if ($mobileRoot !== null) {
    foreach ($apps as $appKey => $dirName) {
        $appRoot = $mobileRoot . DIRECTORY_SEPARATOR . $dirName;
        if (!is_dir($appRoot)) {
            $errors[] = "Application introuvable : {$dirName}";
            continue;
        }
        $foundApps++;

        foreach ($requiredFiles[$appKey] as $relative) {
            $totalFiles++;
            $path = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($path)) {
                $errors[] = "Fichier absent : {$dirName}/{$relative}";
                continue;
            }
            $foundFiles++;
        }

        foreach ($checks[$appKey] ?? [] as $relative => $rules) {
            $path = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($path)) {
                continue;
            }
            $content = file_get_contents($path) ?: '';
            foreach ($rules['must'] ?? [] as $needle) {
                if (!str_contains($content, $needle)) {
                    $errors[] = "Marqueur attendu absent dans {$dirName}/{$relative} : {$needle}";
                }
            }
            foreach ($rules['must_not'] ?? [] as $needle) {
                if (str_contains($content, $needle)) {
                    $errors[] = "Fallback local encore présent dans {$dirName}/{$relative} : {$needle}";
                }
            }
        }
    }

    $obsolete = [
        $mobileRoot . DIRECTORY_SEPARATOR . 'ovanie_app' . DIRECTORY_SEPARATOR . 'lib/features/checkout/data/abidjan_localities.dart',
        $mobileRoot . DIRECTORY_SEPARATOR . 'ovanie_app' . DIRECTORY_SEPARATOR . 'lib/features/categories/data/official_categories.dart',
    ];
    foreach ($obsolete as $file) {
        if (is_file($file)) {
            $errors[] = 'Fichier local obsolète encore présent : ' . str_replace($workspace . DIRECTORY_SEPARATOR, '', $file);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ECHEC — Étape 7 non validée\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

echo "OK — Étape 7 : fallbacks métier locaux retirés\n";
echo "Applications vérifiées : {$foundApps}/4\n";
echo "Fichiers ciblés : {$foundFiles}/{$totalFiles}\n";
echo "Source métier : Laravel /api/mobile/v1/reference-data\n";
echo "Listes locales Client obsolètes : supprimées 2/2\n";
echo "Legacy meta fallbacks Vendeur/Commercial : 0\n";
echo "Correctif dropdown Commercial : conservé\n";
