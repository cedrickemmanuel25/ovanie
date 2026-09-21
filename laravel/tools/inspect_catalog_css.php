<?php
$content = file_get_contents('public/css/catalog.css');
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (preg_match('/(pagination|page-nav|page-numbers|page-number|page-ellipsis)/i', $line)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
