<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Carte de couverture des zones de livraison : contours réels des communes
 * (OpenStreetMap, mis en cache) dessinés sur une image Mapbox, avec la
 * position des étiquettes de communes exprimée en fractions de l'image.
 */
class CommuneCoverageMapService
{
    private const WIDTH = 640;
    private const HEIGHT = 360;
    private const PADDING = 56;
    private const MAX_ZOOM = 13.2;

    /** Vue d'ensemble d'Abidjan quand aucune commune n'est à afficher. */
    private const DEFAULT_VIEW = [-4.0083, 5.3364, 10.1];

    /**
     * @param array<int, array{name: string, active: bool}> $communes
     * @return array{url: ?string, labels: array<int, array{name: string, x: float, y: float, active: bool}>}
     */
    public function map(array $communes): array
    {
        $token = (string) config('geo.mapbox.public_token');
        $shapes = [];
        foreach ($communes as $commune) {
            $rings = $this->boundary($commune['name']);
            if ($rings) {
                $shapes[] = ['name' => $commune['name'], 'active' => (bool) $commune['active'], 'rings' => $rings];
            }
        }

        [$lng, $lat, $zoom] = $this->viewFor($shapes);
        $labels = [];
        foreach ($shapes as $shape) {
            [$cLng, $cLat] = $this->centroid($shape['rings'][0]);
            [$x, $y] = $this->project($cLng, $cLat, $lng, $lat, $zoom);
            if ($x > 0.02 && $x < 0.98 && $y > 0.04 && $y < 0.96) {
                $labels[] = ['name' => $shape['name'], 'x' => round($x, 4), 'y' => round($y, 4), 'active' => $shape['active']];
            }
        }

        if ($token === '') {
            return ['url' => null, 'labels' => $labels];
        }

        $overlays = [];
        foreach ($shapes as $shape) {
            [$stroke, $fill, $opacity] = $shape['active'] ? ['ff6a00', 'ff8a3d', '0.35'] : ['7c8798', '9aa3b2', '0.4'];
            foreach ($shape['rings'] as $ring) {
                $overlays[] = sprintf('path-2.5+%s-0.95+%s-%s(%s)', $stroke, $fill, $opacity, rawurlencode($this->encodePolyline($ring)));
            }
        }

        $url = 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/'
            . ($overlays ? implode(',', $overlays) . '/' : '')
            . sprintf('%.5f,%.5f,%.2f', $lng, $lat, $zoom)
            . '/' . self::WIDTH . 'x' . self::HEIGHT . '@2x?'
            . http_build_query(['language' => config('geo.mapbox.language', 'fr'), 'access_token' => $token]);

        // Au-delà de la limite Mapbox, on retombe sur la carte sans contours.
        if (strlen($url) > 8000) {
            $url = 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/'
                . sprintf('%.5f,%.5f,%.2f', $lng, $lat, $zoom)
                . '/' . self::WIDTH . 'x' . self::HEIGHT . '@2x?'
                . http_build_query(['language' => config('geo.mapbox.language', 'fr'), 'access_token' => $token]);
        }

        return ['url' => $url, 'labels' => $labels];
    }

    /**
     * Contour administratif d'une commune d'Abidjan : liste d'anneaux extérieurs
     * [[lng, lat], ...]. Résultat conservé en cache ; un échec n'est retenu qu'une heure.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public function boundary(string $name): array
    {
        $key = 'commune_boundary:v1:' . Str::slug($name);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $rings = [];
        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => (string) config('geo.nominatim_user_agent', 'OVANIE/1.0')])
                ->get(rtrim((string) config('geo.nominatim_base_url', 'https://nominatim.openstreetmap.org'), '/') . '/search', [
                    'q' => $name . ", Abidjan, Côte d'Ivoire",
                    'format' => 'jsonv2',
                    'polygon_geojson' => 1,
                    'polygon_threshold' => 0.0015,
                    'limit' => 5,
                ]);
            foreach ((array) $response->json() as $result) {
                $geo = $result['geojson'] ?? null;
                if (($result['category'] ?? '') !== 'boundary' || ! is_array($geo)) {
                    continue;
                }
                $polygons = match ($geo['type'] ?? '') {
                    'Polygon' => [$geo['coordinates']],
                    'MultiPolygon' => $geo['coordinates'],
                    default => [],
                };
                foreach ($polygons as $polygon) {
                    $outer = $polygon[0] ?? [];
                    if (count($outer) >= 4) {
                        $rings[] = array_map(fn ($point) => [round((float) $point[0], 5), round((float) $point[1], 5)], $outer);
                    }
                }
                if ($rings) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Contour de commune indisponible.', ['commune' => $name, 'error' => $e->getMessage()]);
        }

        $rings ? Cache::forever($key, $rings) : Cache::put($key, [], now()->addHour());

        return $rings;
    }

    /** Centre et zoom qui contiennent toutes les communes, marge comprise. */
    private function viewFor(array $shapes): array
    {
        $points = [];
        foreach ($shapes as $shape) {
            foreach ($shape['rings'] as $ring) {
                array_push($points, ...$ring);
            }
        }
        if (! $points) {
            return self::DEFAULT_VIEW;
        }

        $xs = array_map(fn ($p) => $this->mercatorX($p[0]), $points);
        $ys = array_map(fn ($p) => $this->mercatorY($p[1]), $points);
        $dx = max(max($xs) - min($xs), 1e-9);
        $dy = max(max($ys) - min($ys), 1e-9);
        // À zoom 0, le monde mesure 512 px.
        $zoom = min(
            log((self::WIDTH - 2 * self::PADDING) / ($dx * 512), 2),
            log((self::HEIGHT - 2 * self::PADDING) / ($dy * 512), 2),
            self::MAX_ZOOM,
        );
        $cx = (max($xs) + min($xs)) / 2;
        $cy = (max($ys) + min($ys)) / 2;

        return [$this->inverseX($cx), $this->inverseY($cy), round($zoom, 2)];
    }

    /** Position d'un point dans l'image, en fractions (0 à 1) de sa largeur et de sa hauteur. */
    private function project(float $lng, float $lat, float $centerLng, float $centerLat, float $zoom): array
    {
        $world = 512 * (2 ** $zoom);
        $x = ($this->mercatorX($lng) - $this->mercatorX($centerLng)) * $world / self::WIDTH + 0.5;
        $y = ($this->mercatorY($lat) - $this->mercatorY($centerLat)) * $world / self::HEIGHT + 0.5;

        return [$x, $y];
    }

    private function centroid(array $ring): array
    {
        $area = $cx = $cy = 0.0;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $cross = $ring[$j][0] * $ring[$i][1] - $ring[$i][0] * $ring[$j][1];
            $area += $cross;
            $cx += ($ring[$j][0] + $ring[$i][0]) * $cross;
            $cy += ($ring[$j][1] + $ring[$i][1]) * $cross;
        }
        if (abs($area) < 1e-12) {
            return $ring[0];
        }

        return [$cx / (3 * $area), $cy / (3 * $area)];
    }

    private function mercatorX(float $lng): float
    {
        return ($lng + 180) / 360;
    }

    private function mercatorY(float $lat): float
    {
        $rad = deg2rad($lat);

        return (1 - log(tan($rad) + 1 / cos($rad)) / M_PI) / 2;
    }

    private function inverseX(float $x): float
    {
        return $x * 360 - 180;
    }

    private function inverseY(float $y): float
    {
        return rad2deg(atan(sinh(M_PI * (1 - 2 * $y))));
    }

    /** Polyline encodée (précision 5), format attendu par les tracés Mapbox. */
    private function encodePolyline(array $ring): string
    {
        $out = '';
        $prevLat = $prevLng = 0;
        foreach ($ring as [$lng, $lat]) {
            $lat5 = (int) round($lat * 1e5);
            $lng5 = (int) round($lng * 1e5);
            $out .= $this->encodeValue($lat5 - $prevLat) . $this->encodeValue($lng5 - $prevLng);
            [$prevLat, $prevLng] = [$lat5, $lng5];
        }

        return $out;
    }

    private function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : $value << 1;
        $chunk = '';
        while ($value >= 0x20) {
            $chunk .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $chunk . chr($value + 63);
    }
}
