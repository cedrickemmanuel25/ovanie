<?php
function search_all_files($dir, $pattern) {
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        if (!in_array(pathinfo($file->getPathname(), PATHINFO_EXTENSION), ['css', 'php'])) continue;
        
        $content = file_get_contents($file->getPathname());
        if (stripos($content, $pattern) !== false) {
            echo "Match in: " . $file->getPathname() . "\n";
        }
    }
}
search_all_files('public/css', 'catalog-page-numbers');
search_all_files('resources/views', 'catalog-page-numbers');
