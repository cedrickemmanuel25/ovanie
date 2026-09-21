<?php

declare(strict_types=1);

use App\Services\OvanieReferenceDataService;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "ERREUR — vendor/autoload.php introuvable. Lancez d'abord composer install.\n");
    exit(1);
}

require $autoload;

if (! is_file($bootstrap)) {
    fwrite(STDERR, "ERREUR — bootstrap/app.php introuvable.\n");
    exit(1);
}

$app = require $bootstrap;
$app->make(Kernel::class)->bootstrap();

$errors = [];
$warnings = [];

if (! is_file($root . '/config/ovanie_contract.php')) {
    $errors[] = 'config/ovanie_contract.php est absent : appliquez/validez d’abord l’étape 2.';
}

if (! is_file($root . '/config/ovanie_reference_data.php')) {
    $errors[] = 'config/ovanie_reference_data.php est absent.';
}

/** @var OvanieReferenceDataService $references */
$references = $app->make(OvanieReferenceDataService::class);

$requiredP0 = [
    'product_states',
    'commercial_sale_types',
    'selling_modes',
    'product_units',
    'seller_types',
    'address_types',
    'delivery_zones',
    'mobile_money_operators',
    'identity_types',
    'vehicle_types',
    'payment_methods',
    'logistics_types',
];

$registry = (array) config('ovanie_reference_data.contract_references', []);
$contractEnums = (array) config('ovanie_contract.enums', []);

foreach ($registry as $alias => $enum) {
    if (! array_key_exists((string) $enum, $contractEnums)) {
        $errors[] = "{$alias} pointe vers l'enum absent {$enum}.";
    }
}

foreach ($requiredP0 as $alias) {
    if (! array_key_exists($alias, $registry)) {
        $errors[] = "Référentiel P0 absent : {$alias}.";
    }
}

$expectedCounts = [
    'product_units' => 15,
    'seller_types' => 4,
    'address_types' => 5,
    'vehicle_types' => 5,
];

foreach ($expectedCounts as $alias => $minimum) {
    try {
        $actual = count($references->codes($alias));
        if ($actual < $minimum) {
            $errors[] = "{$alias} contient {$actual} valeur(s), minimum attendu {$minimum}.";
        }
    } catch (Throwable $e) {
        $errors[] = "Impossible de résoudre {$alias} : {$e->getMessage()}";
    }
}

$wiredFiles = [
    'App Vendeur meta' => 'app/Http/Controllers/Api/Vendor/VendorMobileController.php',
    'Commercial boutique meta' => 'app/Http/Controllers/Api/Commercial/CommercialMobileShopController.php',
    'Commercial produit meta' => 'app/Http/Controllers/Api/Commercial/CommercialMobileProductController.php',
    'Client adresses' => 'app/Http/Controllers/Api/AddressController.php',
    'Territoire livraison' => 'app/Http/Controllers/Api/DeliveryTerritoryController.php',
    'Checkout paiement' => 'app/Services/CheckoutPaymentOptionsService.php',
];

$wiredCount = 0;
foreach ($wiredFiles as $label => $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    if ($contents === '' || ! str_contains($contents, 'OvanieReferenceDataService')) {
        $errors[] = "{$label} n'est pas branché sur OvanieReferenceDataService.";
    } else {
        $wiredCount++;
    }
}

$categoryCount = null;
$communeCount = null;
try {
    $categoryCount = count($references->categoriesFlat());
    $communeCount = count($references->communes());
} catch (Throwable $e) {
    $errors[] = 'Lecture base impossible pour catégories/communes : ' . $e->getMessage();
}

if ($errors !== []) {
    fwrite(STDERR, "ECHEC — Étape 3 non validée\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

if ($categoryCount === 0) {
    $warnings[] = 'Aucune catégorie active dans la base courante.';
}
if ($communeCount === 0) {
    $warnings[] = 'Aucune commune active dans la base courante.';
}

echo "OK — Référentiels Laravel OVANIE centralisés\n";
echo 'Contrat : ' . $references->contractVersion() . "\n";
echo 'Registre : ' . $references->registryVersion() . "\n";
echo 'Référentiels contractuels : ' . count($registry) . "\n";
echo 'Référentiels P0 : ' . count($requiredP0) . '/' . count($requiredP0) . "\n";
echo 'Contrôleurs/services branchés : ' . $wiredCount . '/' . count($wiredFiles) . "\n";
echo 'Catégories actives : ' . (string) $categoryCount . "\n";
echo 'Communes actives : ' . (string) $communeCount . "\n";
echo "API unifiée : non exposée (prévue à l'étape 4)\n";

foreach ($warnings as $warning) {
    echo "AVERTISSEMENT — {$warning}\n";
}
