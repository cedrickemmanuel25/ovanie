<?php

namespace App\Services;

use App\Models\DeliveryDriver;
use App\Models\LogisticsFleetVehicle;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Source unique pour l'apparence du véhicule livreur.
 *
 * Règles OVANIE :
 * - le véhicule déclaré par le livreur à l'inscription est la source prioritaire ;
 * - la Flotte ne sert que de miroir/fallback lorsqu'elle est explicitement liée à ce livreur ;
 * - la photo réellement fournie par le livreur est utilisée dès qu'elle existe ;
 * - la couleur est détectée automatiquement à partir de cette photo et mémorisée
 *   dans profile + meta de la Flotte pour ne pas recalculer à chaque affichage ;
 * - aucun véhicule/couleur fictif n'est créé lorsque la donnée réelle manque.
 */
class VehicleAppearanceService
{
    /** @var array<int, LogisticsFleetVehicle|null> */
    private static array $fleetByDriver = [];

    private static ?bool $fleetTableExists = null;

    public function forDriver(
        ?DeliveryDriver $driver,
        bool $persistDetection = true,
        bool $detectIfMissing = true
    ): array
    {
        if (! $driver) {
            return $this->emptyAppearance();
        }

        $profile = is_array($driver->profile) ? $driver->profile : [];
        $fleet = $this->fleetVehicleFor($driver);

        // Source de vérité : le véhicule que CE livreur a déclaré dans son
        // onboarding. Une fiche Flotte ne doit jamais remplacer le type ou la
        // plaque d'un autre livreur par simple ressemblance de nom/plaque.
        $typeRaw = trim((string) (
            $driver->vehicle
            ?: data_get($profile, 'vehicle')
            ?: $fleet?->vehicle_type
        ));
        $typeCode = $this->typeCode($typeRaw);
        $typeLabel = $this->typeLabel($typeRaw);

        $profilePlate = trim((string) data_get($profile, 'plate', ''));
        $plate = $profilePlate !== ''
            ? $profilePlate
            : trim((string) ($fleet?->registration ?: ''));

        // La photo du dossier du livreur est prioritaire. La photo Flotte n'est
        // utilisée qu'en fallback si cette Flotte est strictement rattachée au
        // même livreur par fleetVehicleFor().
        $driverPhotoPath = $driver->vehiclePhotoPath();
        $fleetPhotoPath = trim((string) data_get($fleet?->meta, 'photo_path', ''));
        $photoPath = $driverPhotoPath ?: ($fleetPhotoPath !== '' ? $fleetPhotoPath : null);
        $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;
        $isRealPhoto = filled($photoPath);

        $color = trim((string) (
            data_get($profile, 'vehicle_color')
            ?: data_get($profile, 'color')
            ?: data_get($fleet?->meta, 'vehicle_color')
        ));
        $colorHex = trim((string) (
            data_get($profile, 'vehicle_color_hex')
            ?: data_get($fleet?->meta, 'vehicle_color_hex')
        ));
        $colorSource = trim((string) (
            data_get($profile, 'vehicle_color_source')
            ?: data_get($fleet?->meta, 'vehicle_color_source')
        ));

        if ($detectIfMissing && $photoPath && ($color === '' || $colorHex === '')) {
            $detected = $this->detectColorFromPublicPath($photoPath);
            if ($detected) {
                $color = $detected['label'];
                $colorHex = $detected['hex'];
                $colorSource = 'vehicle_photo_auto';

                if ($persistDetection) {
                    $this->persistDetectedColor($driver, $fleet, $detected);
                }
            }
        }

        if ($color !== '' && $colorHex === '') {
            $colorHex = $this->hexFromColorLabel($color) ?? '';
        }

        $fallbackUrl = '/images/logistics/vehicle-assets/'.$this->fallbackFilename($typeCode);

        return [
            'fleet_id' => $fleet?->id,
            'fleet_code' => $fleet?->code,
            'type_raw' => $typeRaw ?: null,
            'type_code' => $typeCode,
            'type_label' => $typeLabel,
            'plate' => $plate !== '' ? $plate : null,
            'brand' => filled($fleet?->brand) ? $fleet->brand : data_get($profile, 'vehicle_brand'),
            'model' => filled($fleet?->model) ? $fleet->model : data_get($profile, 'vehicle_model'),
            'photo_path' => $photoPath,
            'photo_url' => $photoUrl,
            'real_photo_url' => $photoUrl,
            'reference_photo_url' => $fallbackUrl,
            'photo_is_real' => $isRealPhoto,
            'color' => $color !== '' ? $color : null,
            'color_hex' => $colorHex !== '' ? $colorHex : null,
            'color_source' => $colorSource !== '' ? $colorSource : null,
        ];
    }

    public function fleetVehicleFor(DeliveryDriver $driver): ?LogisticsFleetVehicle
    {
        $driverId = (int) $driver->id;
        if ($driverId > 0 && array_key_exists($driverId, self::$fleetByDriver)) {
            return self::$fleetByDriver[$driverId];
        }

        self::$fleetTableExists ??= Schema::hasTable('logistics_fleet_vehicles');
        if (! self::$fleetTableExists) {
            return null;
        }

        $profile = is_array($driver->profile) ? $driver->profile : [];
        $fleetId = (int) data_get($profile, 'fleet_vehicle_id', 0);

        // 1) ID explicitement mémorisé dans le profil, mais uniquement si la
        // fiche Flotte confirme qu'elle appartient réellement à ce livreur.
        if ($fleetId > 0) {
            $candidate = LogisticsFleetVehicle::query()->find($fleetId);
            if ($candidate && $this->fleetBelongsToDriver($candidate, $driver)) {
                if ($driverId > 0) {
                    self::$fleetByDriver[$driverId] = $candidate;
                }
                return $candidate;
            }
        }

        // 2) Relation explicite meta.driver_id. On ne fait plus de recherche
        // par nom ou immatriculation : ces champs ne sont pas des identifiants
        // fiables et pouvaient faire apparaître le véhicule d'un autre livreur.
        $vehicle = LogisticsFleetVehicle::query()
            ->whereJsonContains('meta->driver_id', $driverId)
            ->orderByDesc('id')
            ->first();

        if ($driverId > 0) {
            self::$fleetByDriver[$driverId] = $vehicle;
        }

        return $vehicle;
    }

    private function fleetBelongsToDriver(LogisticsFleetVehicle $vehicle, DeliveryDriver $driver): bool
    {
        return (int) data_get($vehicle->meta, 'driver_id', 0) === (int) $driver->id;
    }

    /**
     * Détecte la couleur dominante du véhicule à partir de sa vraie photo.
     * Le calcul privilégie les pixels chromatiques et évite les arrière-plans
     * très blancs/noirs. Pour les véhicules blanc/gris/noir, un fallback
     * achromatique est appliqué.
     */
    public function detectColorFromPublicPath(string $path): ?array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $binary = $disk->get($path);
            $image = @imagecreatefromstring($binary);
            if (! $image) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 2 || $height < 2) {
                imagedestroy($image);
                return null;
            }

            $stepX = max(1, (int) floor($width / 56));
            $stepY = max(1, (int) floor($height / 56));
            $hueBins = array_fill(0, 12, ['weight' => 0.0, 'r' => 0.0, 'g' => 0.0, 'b' => 0.0]);
            $gray = ['weight' => 0.0, 'r' => 0.0, 'g' => 0.0, 'b' => 0.0];
            $chromaticWeight = 0.0;
            $grayWeight = 0.0;

            // On privilégie la zone centrale : elle contient généralement le véhicule
            // plutôt que le ciel/sol des bords de la photo.
            $minX = (int) floor($width * 0.08);
            $maxX = (int) ceil($width * 0.92);
            $minY = (int) floor($height * 0.08);
            $maxY = (int) ceil($height * 0.92);

            for ($y = $minY; $y < $maxY; $y += $stepY) {
                for ($x = $minX; $x < $maxX; $x += $stepX) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    [$h, $s, $v] = $this->rgbToHsv($r, $g, $b);

                    // Pixels quasi transparents/non pertinents, sur/sous exposés.
                    if ($v < 0.06 || $v > 0.98) {
                        continue;
                    }

                    if ($s >= 0.20) {
                        $bin = ((int) floor($h / 30)) % 12;
                        $weight = max(0.02, $s * (0.55 + min($v, 0.85)));
                        $hueBins[$bin]['weight'] += $weight;
                        $hueBins[$bin]['r'] += $r * $weight;
                        $hueBins[$bin]['g'] += $g * $weight;
                        $hueBins[$bin]['b'] += $b * $weight;
                        $chromaticWeight += $weight;
                    } else {
                        $weight = 0.35 + (1 - $s);
                        $gray['weight'] += $weight;
                        $gray['r'] += $r * $weight;
                        $gray['g'] += $g * $weight;
                        $gray['b'] += $b * $weight;
                        $grayWeight += $weight;
                    }
                }
            }

            imagedestroy($image);

            $winning = null;
            foreach ($hueBins as $index => $bin) {
                if (! $winning || $bin['weight'] > $winning['weight']) {
                    $winning = $bin + ['index' => $index];
                }
            }

            // Une quantité suffisante de couleur saturée indique une carrosserie colorée.
            if ($winning && $winning['weight'] > 0 && $chromaticWeight >= max(6.0, $grayWeight * 0.10)) {
                $r = (int) round($winning['r'] / $winning['weight']);
                $g = (int) round($winning['g'] / $winning['weight']);
                $b = (int) round($winning['b'] / $winning['weight']);
                [$h, $s, $v] = $this->rgbToHsv($r, $g, $b);

                return [
                    'label' => $this->chromaticLabel($h, $v),
                    'hex' => sprintf('#%02X%02X%02X', $r, $g, $b),
                    'rgb' => [$r, $g, $b],
                    'confidence' => round(min(0.98, 0.55 + min(0.40, $s * 0.35)), 2),
                ];
            }

            if ($gray['weight'] > 0) {
                $r = (int) round($gray['r'] / $gray['weight']);
                $g = (int) round($gray['g'] / $gray['weight']);
                $b = (int) round($gray['b'] / $gray['weight']);
                $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                $label = $luma < 0.22 ? 'Noir' : ($luma < 0.48 ? 'Gris foncé' : ($luma < 0.78 ? 'Gris / argent' : 'Blanc'));

                return [
                    'label' => $label,
                    'hex' => sprintf('#%02X%02X%02X', $r, $g, $b),
                    'rgb' => [$r, $g, $b],
                    'confidence' => 0.62,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    public function persistDetectedColor(DeliveryDriver $driver, ?LogisticsFleetVehicle $fleet, array $detected): void
    {
        $profile = is_array($driver->profile) ? $driver->profile : [];
        $profile['vehicle_color'] = $detected['label'];
        $profile['vehicle_color_hex'] = $detected['hex'];
        $profile['vehicle_color_source'] = 'vehicle_photo_auto';
        $profile['vehicle_color_detected_at'] = now()->toIso8601String();
        $driver->forceFill(['profile' => $profile])->saveQuietly();

        if ($fleet) {
            $meta = is_array($fleet->meta) ? $fleet->meta : [];
            $meta['vehicle_color'] = $detected['label'];
            $meta['vehicle_color_hex'] = $detected['hex'];
            $meta['vehicle_color_source'] = 'vehicle_photo_auto';
            $meta['vehicle_color_detected_at'] = now()->toIso8601String();
            $fleet->forceFill(['meta' => $meta])->saveQuietly();
        }
    }

    public function typeCode(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['-', '_'], ' ', $value);

        return match (true) {
            str_contains($value, 'tricycle') => 'tricycle',
            str_contains($value, 'moto'), str_contains($value, 'scooter') => 'moto',
            str_contains($value, 'pickup'), str_contains($value, 'pick up') => 'pickup',
            str_contains($value, '10t'), str_contains($value, '10 t') => 'camion_10t',
            str_contains($value, '3t'), str_contains($value, '3 t') => 'camion_3t',
            default => 'vehicle',
        };
    }

    public function typeLabel(?string $value): string
    {
        return match ($this->typeCode($value)) {
            'moto' => 'Moto',
            'tricycle' => 'Tricycle',
            'pickup' => 'Pickup',
            'camion_3t' => 'Camion 3T',
            'camion_10t' => 'Camion 10T',
            default => trim((string) $value) ?: 'Véhicule',
        };
    }

    private function fallbackFilename(string $typeCode): string
    {
        return match ($typeCode) {
            'moto' => 'moto.png',
            'tricycle' => 'tricycle.png',
            'pickup' => 'pickup.png',
            'camion_3t' => 'camion-3t.png',
            'camion_10t' => 'camion-10t.png',
            default => 'pickup.png',
        };
    }

    private function rgbToHsv(int $r, int $g, int $b): array
    {
        $rf = $r / 255;
        $gf = $g / 255;
        $bf = $b / 255;
        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $delta = $max - $min;
        $h = 0.0;

        if ($delta > 0) {
            if ($max === $rf) {
                $h = 60 * fmod((($gf - $bf) / $delta), 6);
            } elseif ($max === $gf) {
                $h = 60 * ((($bf - $rf) / $delta) + 2);
            } else {
                $h = 60 * ((($rf - $gf) / $delta) + 4);
            }
        }
        if ($h < 0) {
            $h += 360;
        }

        $s = $max <= 0 ? 0.0 : $delta / $max;
        return [$h, $s, $max];
    }

    private function chromaticLabel(float $hue, float $value): string
    {
        $base = match (true) {
            $hue < 15 || $hue >= 345 => 'Rouge',
            $hue < 38 => 'Orange',
            $hue < 68 => 'Jaune',
            $hue < 165 => 'Vert',
            $hue < 195 => 'Turquoise',
            $hue < 255 => 'Bleu',
            $hue < 290 => 'Violet',
            $hue < 345 => 'Rose / bordeaux',
            default => 'Couleur détectée',
        };

        if ($value < 0.34 && ! str_contains($base, 'bordeaux')) {
            return $base.' foncé';
        }
        if ($value > 0.82 && in_array($base, ['Bleu', 'Vert', 'Turquoise'], true)) {
            return $base.' clair';
        }
        return $base;
    }

    private function hexFromColorLabel(string $label): ?string
    {
        $value = mb_strtolower(trim($label));

        return match (true) {
            str_contains($value, 'argent'), str_contains($value, 'gris clair') => '#A7ADB3',
            str_contains($value, 'gris fonce'), str_contains($value, 'gris foncé') => '#5E646B',
            $value === 'gris' => '#80868D',
            str_contains($value, 'noir') => '#202428',
            str_contains($value, 'blanc') => '#E9EDF0',
            str_contains($value, 'rouge') => '#D9342B',
            str_contains($value, 'orange') => '#F28B24',
            str_contains($value, 'jaune') => '#E3B316',
            str_contains($value, 'vert') => '#159447',
            str_contains($value, 'turquoise') => '#1B9C9A',
            str_contains($value, 'bleu') => '#2D63B7',
            str_contains($value, 'violet') => '#7250A8',
            str_contains($value, 'rose'), str_contains($value, 'bordeaux') => '#A53757',
            default => null,
        };
    }

    private function emptyAppearance(): array
    {
        return [
            'fleet_id' => null,
            'fleet_code' => null,
            'type_raw' => null,
            'type_code' => 'vehicle',
            'type_label' => 'Véhicule',
            'plate' => null,
            'brand' => null,
            'model' => null,
            'photo_path' => null,
            'photo_url' => null,
            'real_photo_url' => null,
            'photo_is_real' => false,
            'reference_photo_url' => null,
            'color' => null,
            'color_hex' => null,
            'color_source' => null,
        ];
    }
}
