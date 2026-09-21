<?php

namespace App\Support;

final class LogisticsTerritoryMapPresets
{
    /**
     * GeoJSON coordinates are stored as [longitude, latitude].
     * These operational polygons are intentionally editable from the Territoire UI.
     */
    public static function all(): array
    {
        return [
            'Z-001' => self::zone('Abidjan Centre', '#13b989', '#078d6b', [5.3495, -4.0084], [
                [-4.0520, 5.3330], [-4.0360, 5.3105], [-3.9960, 5.3060], [-3.9650, 5.3220],
                [-3.9570, 5.3515], [-3.9730, 5.3810], [-4.0110, 5.3910], [-4.0440, 5.3710],
                [-4.0520, 5.3330],
            ]),
            'Z-002' => self::zone('Abidjan Nord', '#5ca8ff', '#1477ef', [5.4300, -4.0080], [
                [-4.0680, 5.3850], [-4.0480, 5.4520], [-4.0100, 5.4900], [-3.9570, 5.4720],
                [-3.9380, 5.4150], [-3.9650, 5.3810], [-4.0110, 5.3910], [-4.0680, 5.3850],
            ]),
            'Z-003' => self::zone('Abidjan Sud', '#aa91ff', '#7357e8', [5.2820, -4.0050], [
                [-4.0820, 5.3070], [-4.0520, 5.3330], [-4.0360, 5.3105], [-3.9960, 5.3060],
                [-3.9650, 5.3220], [-3.9300, 5.3000], [-3.9480, 5.2550], [-4.0220, 5.2380],
                [-4.0820, 5.2670], [-4.0820, 5.3070],
            ]),
            'Z-004' => self::zone('Bingerville', '#ffb05e', '#ef8b1d', [5.3600, -3.8950], [
                [-3.9570, 5.3515], [-3.9380, 5.4150], [-3.8810, 5.4270], [-3.8250, 5.3910],
                [-3.8310, 5.3260], [-3.9000, 5.3060], [-3.9650, 5.3220], [-3.9570, 5.3515],
            ]),
            'Z-005' => self::zone('Anyama', '#7db7ff', '#1e73e8', [5.5150, -4.0150], [
                [-4.0850, 5.4700], [-4.0750, 5.5480], [-4.0200, 5.5900], [-3.9560, 5.5660],
                [-3.9450, 5.4930], [-3.9570, 5.4720], [-4.0100, 5.4900], [-4.0850, 5.4700],
            ]),
            'Z-006' => self::zone('Yopougon', '#ff7f79', '#ff3d3d', [5.3430, -4.0900], [
                [-4.1720, 5.3020], [-4.1590, 5.3810], [-4.1060, 5.4140], [-4.0680, 5.3850],
                [-4.0520, 5.3330], [-4.0820, 5.3070], [-4.1120, 5.2730], [-4.1720, 5.3020],
            ]),
            'Z-007' => self::zone('Port-Bouët', '#a99cff', '#6d58e8', [5.2520, -3.9000], [
                [-3.9480, 5.2550], [-3.9300, 5.3000], [-3.9000, 5.3060], [-3.8310, 5.3260],
                [-3.7850, 5.2830], [-3.8120, 5.2220], [-3.8870, 5.2130], [-3.9480, 5.2550],
            ]),
            'Z-008' => self::box('San Pedro', '#66b8e8', '#2b86ba', [4.7485, -6.6363], .055, .045),
            'Z-009' => self::box('Yamoussoukro', '#9aa8bb', '#64748b', [6.8276, -5.2893], .060, .050),
            'Z-010' => self::box('Bouaké', '#6dc9a7', '#289c76', [7.6906, -5.0300], .060, .050),
            'Z-011' => self::box('Daloa', '#7db7ff', '#2f79c9', [6.8774, -6.4502], .055, .050),
            'Z-012' => self::box('Korhogo', '#9aa8bb', '#64748b', [9.4580, -5.6296], .060, .050),
        ];
    }

    public static function for(string $code, ?string $name = null): array
    {
        $preset = self::all()[$code] ?? null;
        if ($preset) {
            return $preset;
        }

        return self::box($name ?: $code, '#7db7ff', '#2f79c9', [5.359952, -4.008256], .035, .028);
    }

    public static function fromGeoJson(string $name, array $geometry, string $fill = '#13b989', string $stroke = '#078d6b'): ?array
    {
        if (($geometry['type'] ?? null) !== 'Polygon' || ! is_array($geometry['coordinates'][0] ?? null)) {
            return null;
        }

        $ring = array_values(array_filter($geometry['coordinates'][0], static function ($point) {
            return is_array($point) && count($point) >= 2 && is_numeric($point[0]) && is_numeric($point[1]);
        }));

        if (count($ring) < 3) {
            return null;
        }

        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        $lngs = array_column($ring, 0);
        $lats = array_column($ring, 1);

        return self::zone($name, $fill, $stroke, [array_sum($lats) / count($lats), array_sum($lngs) / count($lngs)], $ring);
    }

    private static function zone(string $label, string $fill, string $stroke, array $center, array $ring): array
    {
        return [
            'label' => $label,
            'center' => ['lat' => (float) $center[0], 'lng' => (float) $center[1]],
            'fill' => $fill,
            'stroke' => $stroke,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$ring],
            ],
        ];
    }

    private static function box(string $label, string $fill, string $stroke, array $center, float $lngRadius, float $latRadius): array
    {
        [$lat, $lng] = $center;
        return self::zone($label, $fill, $stroke, $center, [
            [$lng - $lngRadius, $lat - $latRadius],
            [$lng + $lngRadius, $lat - $latRadius],
            [$lng + $lngRadius, $lat + $latRadius],
            [$lng - $lngRadius, $lat + $latRadius],
            [$lng - $lngRadius, $lat - $latRadius],
        ]);
    }
}
