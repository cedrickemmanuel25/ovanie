<?php
function search_all_files($dir, $pattern) {
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        $ext = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
        if (!in_array($ext, ['php', 'js', 'css'])) continue;
        
        // Skip vendors, node_modules, storage, etc.
        $path = $file->getPathname();
        if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false || strpos($path, 'storage') !== false) {
            continue;
        }
        
        $content = file_get_contents($path);
        if (stripos($content, $pattern) !== false) {
            echo "Match in: " . $path . "\n";
        }
    }
}

search_all_files('app', 'Category');
search_all_files('resources/views', 'Category');
search_all_files('database/seeders', 'Category');
