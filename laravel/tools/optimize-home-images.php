<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/public/storage/logos';
$images = [
    'hero img.png' => ['home-hero.webp', 1600],
    'ia ovanie.png' => ['home-assistant.webp', 720],
    'gros œuvre & maçonnerie.png' => ['home-gros-oeuvre.webp', 720],
    'finition & déco.png' => ['home-finition.webp', 720],
    'matériel outillage.png' => ['home-outillage.webp', 720],
    'equipement de chantier.png' => ['home-equipement.webp', 720],
    'énergie & autonomie.png' => ['home-energie.webp', 720],
    'Électricité & Plomberie.png' => ['home-plomberie.webp', 720],
    'Carte Cadeau OVANIE.png' => ['home-carte-cadeau.webp', 720],
    'Nos reconditionnés.png' => ['home-reconditionnes.webp', 720],
];

foreach ($images as $sourceName => [$targetName, $maxWidth]) {
    $sourcePath = $root . '/' . $sourceName;
    $targetPath = $root . '/' . $targetName;

    if (! is_file($sourcePath)) {
        fwrite(STDERR, "Image absente : {$sourceName}\n");
        continue;
    }

    $source = imagecreatefrompng($sourcePath);
    $width = imagesx($source);
    $height = imagesy($source);
    $targetWidth = min($width, $maxWidth);
    $targetHeight = (int) round($height * ($targetWidth / $width));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);

    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    if (! imagewebp($target, $targetPath, 80)) {
        throw new RuntimeException("Échec de création : {$targetName}");
    }

    imagedestroy($source);
    imagedestroy($target);
    echo "{$sourceName} -> {$targetName}\n";
}
