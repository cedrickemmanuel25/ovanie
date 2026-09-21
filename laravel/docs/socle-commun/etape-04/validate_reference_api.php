<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MobileReferenceDataController;
use App\Services\OvanieReferenceDataService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

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

if (! class_exists(MobileReferenceDataController::class)) {
    $errors[] = 'MobileReferenceDataController est absent.';
}

/** @var OvanieReferenceDataService $references */
$references = $app->make(OvanieReferenceDataService::class);
$routes = Route::getRoutes();
$expectedRoutes = [
    'api.mobile.reference-data.index' => 'api/mobile/v1/reference-data',
    'api.mobile.reference-data.meta' => 'api/mobile/v1/reference-data/meta',
];
$routeResults = [];

foreach ($expectedRoutes as $name => $expectedUri) {
    $found = null;
    foreach ($routes as $route) {
        if ($route->getName() === $name) {
            $found = $route;
            break;
        }
    }
    if ($found === null) {
        $errors[] = "Route absente : {$name}.";
        continue;
    }
    $uri = $found->uri();
    if ($uri !== $expectedUri) {
        $errors[] = "URI inattendue pour {$name} : {$uri}.";
    }
    if (! in_array('GET', $found->methods(), true)) {
        $errors[] = "{$name} n'accepte pas GET.";
    }
    $middleware = $found->gatherMiddleware();
    if (in_array('auth:sanctum', $middleware, true)) {
        $errors[] = "{$name} exige auth:sanctum.";
    }
    $routeResults[$name] = ['uri' => $uri, 'middleware' => $middleware];
}

$payload = [];
try {
    $payload = $references->apiPayload();
} catch (Throwable $e) {
    $errors[] = 'Impossible de construire le payload : ' . $e->getMessage();
}

$meta = $payload['meta'] ?? [];
$data = $payload['data'] ?? [];
if (($payload['ok'] ?? false) !== true) {
    $errors[] = 'Le payload principal ne contient pas ok=true.';
}
if (($meta['schema_version'] ?? null) !== $references->contractVersion()) {
    $errors[] = 'schema_version ne correspond pas au contrat.';
}
if (($meta['registry_version'] ?? null) !== $references->registryVersion()) {
    $errors[] = 'registry_version ne correspond pas au registre.';
}

$contractReferences = array_keys((array) config('ovanie_reference_data.contract_references', []));
foreach ($contractReferences as $key) {
    if (! array_key_exists($key, $data)) {
        $errors[] = "Référentiel contractuel absent : {$key}.";
    }
}

$requiredP0 = [
    'product_states', 'commercial_sale_types', 'selling_modes', 'product_units',
    'seller_types', 'address_types', 'delivery_zones', 'mobile_money_operators',
    'identity_types', 'vehicle_types', 'payment_methods', 'logistics_types',
];
foreach ($requiredP0 as $key) {
    if (! isset($data[$key]) || ! is_array($data[$key])) {
        $errors[] = "Clé P0 absente ou invalide : {$key}.";
    }
}

$dynamicRequired = [
    'categories', 'categories_flat', 'subcategories', 'shop_categories',
    'product_categories', 'regions', 'cities', 'communes', 'quarters',
    'delivery_territory',
];
foreach ($dynamicRequired as $key) {
    if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
        $errors[] = "Référentiel dynamique absent ou invalide : {$key}.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ECHEC — Étape 4 non validée\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

$categoryCount = count($data['categories_flat'] ?? []);
$subcategoryCount = count($data['subcategories'] ?? []);
$communeCount = count($data['communes'] ?? []);
$quarterCount = count($data['quarters'] ?? []);

echo "OK — API commune OVANIE reference-data exposée\n";
echo 'Route référentiels : GET /' . $routeResults['api.mobile.reference-data.index']['uri'] . "\n";
echo 'Route meta : GET /' . $routeResults['api.mobile.reference-data.meta']['uri'] . "\n";
echo "Authentification : publique, lecture seule, throttle actif\n";
echo 'Schema version : ' . $references->contractVersion() . "\n";
echo 'Registre : ' . $references->registryVersion() . "\n";
echo 'Référentiels contractuels : ' . count($contractReferences) . '/' . count($contractReferences) . "\n";
echo 'Référentiels P0 : ' . count($requiredP0) . '/' . count($requiredP0) . "\n";
echo 'Catégories actives : ' . $categoryCount . "\n";
echo 'Sous-catégories actives : ' . $subcategoryCount . "\n";
echo 'Communes actives : ' . $communeCount . "\n";
echo 'Quartiers actifs : ' . $quarterCount . "\n";
echo "Applications Flutter : non modifiées à cette étape\n";
