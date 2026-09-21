<?php

$workspace = dirname(__DIR__, 4);
$errors = [];

$configs = [
    'App Client' => $workspace . '/OVANIE-MOBILE/ovanie_app/lib/core/config/app_config.dart',
    'App Vendeur' => $workspace . '/OVANIE-MOBILE/ovanie_vendor/lib/core/config/app_config.dart',
    'App Livreur' => $workspace . '/OVANIE-MOBILE/ovanie_livreur/lib/core/config/api_config.dart',
    'App Commercial' => $workspace . '/OVANIE-MOBILE/ovanie_commercial/lib/core/api/api_config.dart',
];

$validated = 0;
foreach ($configs as $label => $file) {
    if (!is_file($file)) {
        $errors[] = "$label : fichier introuvable : $file";
        continue;
    }
    $content = file_get_contents($file) ?: '';
    foreach (['OVANIE_ENV', 'OVANIE_API_BASE', 'https://test.ovanie.com/api', 'https://www.ovanie.com/api', '10.0.2.2:8000/api'] as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "$label : configuration absente : $needle";
        }
    }
    if (!array_filter($errors, fn ($error) => str_starts_with($error, "$label :"))) {
        $validated++;
    }
}

$scripts = [
    'start-laravel.ps1',
    'run-app.ps1',
    'build-apk.ps1',
    'test-local-api.ps1',
    'show-config.ps1',
    'common.ps1',
];
foreach ($scripts as $script) {
    if (!is_file($workspace . '/LOCAL-DEV/' . $script)) {
        $errors[] = "Script manquant : LOCAL-DEV/$script";
    }
}

if ($errors) {
    echo "ECHEC — Configuration locale OVANIE non validée\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    exit(1);
}

echo "OK — Environnement LOCAL OVANIE configuré\n";
echo "Applications : {$validated}/4\n";
echo "Local téléphone physique : IP LAN du PC injectée par PowerShell\n";
echo "Local Android Emulator : http://10.0.2.2:8000/api\n";
echo "Test : https://test.ovanie.com/api\n";
echo "Production : https://www.ovanie.com/api\n";
echo "Retour test/production : sans modification du code Dart\n";
