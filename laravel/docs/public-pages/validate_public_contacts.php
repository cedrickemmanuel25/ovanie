<?php

$root = dirname(__DIR__, 2);

$targets = [
    $root.'/resources/views/public',
    $root.'/resources/views/contact.blade.php',
    $root.'/resources/views/layouts/_footer.blade.php',
    $root.'/resources/views/layouts/_navbar.blade.php',
    $root.'/resources/views/layouts/app.blade.php',
    $root.'/resources/views/open-shop.blade.php',
    $root.'/resources/views/products/show.blade.php',
    $root.'/resources/views/catalog/index.blade.php',
    $root.'/resources/views/errors/500.blade.php',
    $root.'/resources/views/gift-cards',
];

$files = [];
foreach ($targets as $target) {
    if (is_dir($target)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
    } elseif (is_file($target)) {
        $files[] = $target;
    }
}
$files = array_values(array_unique($files));

$forbidden = [
    '01 61 78 18 18',
    '01 61 78 01 01',
    '01 61 71 00 00',
    'contact@ovanie.ci',
    'support@ovanie.ci',
    '+10 000',
    'clients nous font confiance',
];

$errors = [];
foreach ($files as $file) {
    $content = file_get_contents($file) ?: '';
    foreach ($forbidden as $needle) {
        if (str_contains($content, $needle)) {
            $errors[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file)." contient encore : {$needle}";
        }
    }
}

$configFile = $root.'/config/public_contact.php';
if (! is_file($configFile)) {
    $errors[] = 'config/public_contact.php est absent.';
} else {
    $configContent = file_get_contents($configFile) ?: '';
    foreach ([
        "'01 61 78 00 00'",
        "'+2250161780000'",
        "'contact@ovanie.com'",
        "'2250161781818'",
        "'Discutez avec un conseiller'",
        "'Bonjour OVANIE, j’ai besoin d’assistance.'",
    ] as $needle) {
        if (! str_contains($configContent, $needle)) {
            $errors[] = "Valeur officielle absente de config/public_contact.php : {$needle}";
        }
    }
}

if ($errors) {
    echo "ECHEC — Coordonnées publiques OVANIE non uniformisées\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK — Pages publiques OVANIE uniformisées\n";
echo "Téléphone : 01 61 78 00 00\n";
echo "Lien téléphone : tel:+2250161780000\n";
echo "E-mail : contact@ovanie.com\n";
echo "WhatsApp : Discutez avec un conseiller\n";
echo "WhatsApp URL : https://wa.me/2250161781818?text=Bonjour%20OVANIE%2C%20j%E2%80%99ai%20besoin%20d%E2%80%99assistance.\n";
echo "Anciennes coordonnées publiques : 0 occurrence\n";
echo "Statistiques non vérifiées +10 000 : supprimées\n";
echo "Fichiers publics contrôlés : ".count($files)."\n";
