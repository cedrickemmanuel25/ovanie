<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$registry = app(App\Services\Geo\AbidjanLocalityRegistry::class);

// Look for Abobo localities containing PK
$abobo = $registry->localitiesForCommune('Abobo', null, 500);
echo "Abobo localities containing 'PK':\n";
foreach ($abobo as $q) {
    if (stripos($q['name'], 'pk') !== false || stripos($q['name'], 'PK') !== false) {
        echo "  " . json_encode($q, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

echo "\nAll Abobo localities:\n";
foreach ($abobo as $q) {
    echo "  " . json_encode(['name' => $q['name'], 'type' => $q['type'], 'aliases' => $q['aliases']], JSON_UNESCAPED_UNICODE) . "\n";
}
