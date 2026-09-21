<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\HomepageService::class);
$reflector = new ReflectionClass($service);
$method = $reflector->getMethod('buildPublicPayload');
$method->setAccessible(true);

echo "Testing buildPublicPayload()...\n";
$t0 = microtime(true);
try {
    $payload = $method->invoke($service);
    echo "buildPublicPayload() completed in " . round((microtime(true) - $t0) * 1000, 2) . " ms\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
