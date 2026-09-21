<?php
$file = 'resources/views/vendor/products/partials/ovanie-product-wizard.blade.php';
if (!file_exists($file)) {
    // Check main folder
    $file = 'resources/views/vendor/products/ovanie-product-wizard.blade.php';
}
if (!file_exists($file)) {
    die("File not found\n");
}
$content = file_get_contents($file);
$lines = explode("\n", $content);

$queries = [
    'Commission estimée',
    'Prix client estimé',
    'Net vendeur',
    'Glissez-déposez',
    'flash',
    'black',
    'friday',
    'promo',
    'vente'
];

foreach ($queries as $query) {
    echo "--- Search results for: '$query' ---\n";
    foreach ($lines as $i => $line) {
        if (stripos($line, $query) !== false) {
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
