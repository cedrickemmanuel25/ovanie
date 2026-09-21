<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$registry = app(App\Services\Geo\AbidjanLocalityRegistry::class);

// Check recognitionFormsCount method
$methods = get_class_methods($registry);
echo "Methods:\n";
foreach ($methods as $m) {
    echo "  $m\n";
}
echo "\n";

// Check resolve() keys
$resolved = $registry->resolve(
    ['Cité Les Lauriers Nouvelle Appellation', 'Cocody', 'Abidjan'],
    'Cocody',
    'Cité Les Lauriers Nouvelle Appellation'
);
echo "Resolve keys:\n";
foreach (array_keys($resolved) as $k) {
    echo "  $k => " . json_encode($resolved[$k]) . "\n";
}
echo "\n";

// Check Allabra in Cocody
$results = $registry->localitiesForCommune('Cocody', 'allabra', 10);
echo "Allabra search result:\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "\n";

// Check total localities count
$total = 0;
$forms = 0;
foreach ($registry->communes() as $c) {
    $quarters = $registry->localitiesForCommune($c['name'], null, 500);
    $total += count($quarters);
    foreach ($quarters as $q) {
        $forms += 1 + count($q['aliases'] ?? []);
    }
}
echo "Total localities: $total\n";
echo "Total recognition forms: $forms\n";
