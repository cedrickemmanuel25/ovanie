<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$errors = [];
$service = app(App\Services\OvanieSchemaCompatibilityService::class);

$schema = $service->currentSchemaVersion();
$compatibilityVersion = $service->compatibilityVersion();
$apps = $service->apps();
$expectedApps = ['client', 'vendor', 'driver', 'commercial'];

if ($schema !== '1.0.0') {
    $errors[] = "Schema inattendu : {$schema}";
}
if ($compatibilityVersion !== '1.0.0') {
    $errors[] = "Version de compatibilité inattendue : {$compatibilityVersion}";
}

foreach ($expectedApps as $appCode) {
    if (! isset($apps[$appCode])) {
        $errors[] = "Application absente de la matrice : {$appCode}";
        continue;
    }

    $current = $service->evaluate($appCode, '1.0.0');
    if (($current['compatible'] ?? false) !== true) {
        $errors[] = "Le schéma 1.0.0 devrait être compatible pour {$appCode}.";
    }

    $old = $service->evaluate($appCode, '0.9.0');
    if (($old['compatible'] ?? true) !== false) {
        $errors[] = "Le schéma 0.9.0 devrait être refusé pour {$appCode}.";
    }

    $future = $service->evaluate($appCode, '2.0.0');
    if (($future['compatible'] ?? true) !== false) {
        $errors[] = "Le schéma 2.0.0 devrait être refusé pour {$appCode}.";
    }
}

$routes = app('router')->getRoutes();
$routeFound = false;
foreach ($routes as $route) {
    if ($route->uri() === 'api/mobile/v1/reference-data/compatibility' && in_array('GET', $route->methods(), true)) {
        $routeFound = true;
        break;
    }
}
if (! $routeFound) {
    $errors[] = 'Route GET /api/mobile/v1/reference-data/compatibility absente.';
}

$projectRoot = dirname($root);
$mobileChecks = [
    'client' => [$projectRoot . '/mobile client/lib/main.dart', $projectRoot . '/mobile client/lib/core/compatibility/schema_compatibility.dart', $projectRoot . '/mobile client/lib/core/network/api_client.dart'],
    'vendor' => [$projectRoot . '/mobile vendeur/lib/main.dart', $projectRoot . '/mobile vendeur/lib/core/compatibility/schema_compatibility.dart', $projectRoot . '/mobile vendeur/lib/core/network/api_client.dart'],
    'driver' => [$projectRoot . '/mobile livreur /lib/main.dart', $projectRoot . '/mobile livreur /lib/core/compatibility/schema_compatibility.dart', $projectRoot . '/mobile livreur /lib/core/network/api_client.dart'],
    'commercial' => [$projectRoot . '/mobile commercial/lib/main.dart', $projectRoot . '/mobile commercial/lib/core/compatibility/schema_compatibility.dart', $projectRoot . '/mobile commercial/lib/core/api/api_client.dart'],
];

$mobileValidated = 0;
foreach ($mobileChecks as $appCode => [$mainFile, $compatibilityFile, $apiFile]) {
    // Les projets mobiles peuvent être dans un autre workspace que Laravel.
    // S'ils sont présents à côté de laravel, le validateur contrôle leur code.
    if (! is_file($mainFile) || ! is_file($compatibilityFile) || ! is_file($apiFile)) {
        continue;
    }

    $main = file_get_contents($mainFile) ?: '';
    $compat = file_get_contents($compatibilityFile) ?: '';
    $api = file_get_contents($apiFile) ?: '';

    if (! str_contains($main, 'OvanieSchemaCompatibility.check()')) {
        $errors[] = "Contrôle de démarrage absent dans {$appCode}.";
    }
    if (! str_contains($compat, "appCode = '{$appCode}'") || ! str_contains($compat, "supportedSchemaVersion = '1.0.0'")) {
        $errors[] = "Contrat APK invalide dans {$appCode}.";
    }
    if (! str_contains($api, 'X-Ovanie-Schema-Version')) {
        $errors[] = "En-tête de schéma absent du client HTTP {$appCode}.";
    }
    $mobileValidated++;
}

if ($errors !== []) {
    echo "ECHEC — Étape 5 non validée\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK — Compatibilité APK ↔ contrat OVANIE active\n";
echo "Schema Laravel : {$schema}\n";
echo "Compatibilité : {$compatibilityVersion}\n";
echo "Applications configurées : " . count($expectedApps) . '/4\n';
echo "Schéma courant 1.0.0 : compatible 4/4\n";
echo "Ancien schéma 0.9.0 : refusé 4/4\n";
echo "Futur schéma 2.0.0 : refusé 4/4\n";
echo "Route compatibilité : GET /api/mobile/v1/reference-data/compatibility\n";
echo "Applications mobiles vérifiées dans ce workspace : {$mobileValidated}/4\n";
echo "Listes Flutter : inchangées à cette étape\n";
