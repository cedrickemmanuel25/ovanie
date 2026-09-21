<?php
$content = file_get_contents('app/Http/Controllers/VendorProductController.php');
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (preg_match('/(flash|bf|type|promo)/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
