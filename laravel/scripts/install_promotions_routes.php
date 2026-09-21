<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$webRoutes = $projectRoot . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$routeInclude = "        require base_path('routes/admin_promotions.php');";
$oldRoute = "        Route::get('promotions', fn() => view('admin.promotions.index'))->name('promotions.index');";

if (! is_file($webRoutes)) {
    fwrite(STDERR, "Fichier introuvable : {$webRoutes}\n");
    exit(1);
}

$content = file_get_contents($webRoutes);
if ($content === false) {
    fwrite(STDERR, "Impossible de lire routes/web.php\n");
    exit(1);
}

if (str_contains($content, "routes/admin_promotions.php")) {
    echo "Les routes Promotions sont déjà installées.\n";
    exit(0);
}

if (! str_contains($content, $oldRoute)) {
    fwrite(STDERR, "La route Promotions d'origine n'a pas été trouvée. Ajoutez manuellement cette ligne dans le groupe admin :\n{$routeInclude}\n");
    exit(2);
}

$backup = $webRoutes . '.before-promotions-v9-3.bak';
if (! copy($webRoutes, $backup)) {
    fwrite(STDERR, "Impossible de créer la sauvegarde {$backup}\n");
    exit(1);
}

$content = str_replace($oldRoute, $routeInclude, $content, $count);
if ($count !== 1 || file_put_contents($webRoutes, $content) === false) {
    fwrite(STDERR, "La mise à jour des routes a échoué. La sauvegarde est disponible dans {$backup}\n");
    exit(1);
}

echo "Routes Promotions installées avec succès.\nSauvegarde : {$backup}\n";
