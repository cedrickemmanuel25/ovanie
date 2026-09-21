<?php

namespace App\Services;

use App\Models\Shop;

class CommercialShopLocationService
{
    public const READY_ACCURACY_METERS = 100.0;

    /**
     * Construit les champs GPS/logistiques à enregistrer lors de la création
     * d'une boutique par un commercial.
     *
     * @return array<string, mixed>
     */
    public function creationPayload(array $data): array
    {
        $logisticsType = (string) ($data['logistics_type'] ?? 'ovanie');
        $locationMode = (string) ($data['location_mode'] ?? 'later');

        if ($logisticsType !== 'ovanie' || $locationMode !== 'gps_now') {
            return $this->pendingPayload();
        }

        return $this->resolvedPayload(
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['geo_accuracy']) && $data['geo_accuracy'] !== '' ? (float) $data['geo_accuracy'] : null,
            (string) ($data['geo_source'] ?? 'browser_gps'),
        );
    }

    /**
     * Met à jour la position d'une boutique OVANIE Logistics.
     */
    public function updateShop(Shop $shop, array $data): Shop
    {
        $payload = $this->resolvedPayload(
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['geo_accuracy']) && $data['geo_accuracy'] !== '' ? (float) $data['geo_accuracy'] : null,
            (string) ($data['geo_source'] ?? 'browser_gps'),
        );

        $shop->fill([
            'commune' => trim((string) $data['commune']),
            'district' => trim((string) $data['district']),
            'landmark' => filled($data['landmark'] ?? null) ? trim((string) $data['landmark']) : null,
            'address' => filled($data['address'] ?? null)
                ? trim((string) $data['address'])
                : collect([$data['district'] ?? null, $data['commune'] ?? null, $shop->city])->filter()->implode(', '),
            ...$payload,
        ])->save();

        return $shop->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(Shop $shop): array
    {
        $missing = [];

        if (! filled($shop->commune)) {
            $missing[] = 'commune';
        }
        if (! filled($shop->district)) {
            $missing[] = 'quartier';
        }
        if (! filled($shop->address)) {
            $missing[] = 'adresse';
        }

        if ($shop->usesOvanieLogistics()) {
            if (! is_numeric($shop->latitude) || ! is_numeric($shop->longitude)) {
                $missing[] = 'position GPS';
            }
            if (! in_array($shop->geo_status, [Shop::GEO_STATUS_RELIABLE, Shop::GEO_STATUS_VERIFIED], true)) {
                $missing[] = 'confirmation GPS';
            }
        } elseif (! $shop->hasCompleteSellerLogistics()) {
            $missing[] = 'configuration logistique vendeur';
        }


        return [
            'location_ready' => $shop->usesSellerLogistics()
                || (
                    is_numeric($shop->latitude)
                    && is_numeric($shop->longitude)
                    && in_array($shop->geo_status, [Shop::GEO_STATUS_RELIABLE, Shop::GEO_STATUS_VERIFIED], true)
                ),
            'logistics_ready' => $shop->logistics_status === Shop::LOGISTICS_READY,
            'publication_ready' => $shop->canPublishProducts(),
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedPayload(float $latitude, float $longitude, ?float $accuracy, string $source): array
    {
        $source = in_array($source, ['browser_gps', 'manual_map'], true) ? $source : 'browser_gps';
        $manual = $source === 'manual_map';
        $reliable = $manual || $accuracy === null || $accuracy <= self::READY_ACCURACY_METERS;

        if ($manual) {
            $precision = 'manual';
            $score = 85;
        } elseif ($accuracy === null) {
            $precision = 'unknown';
            $score = 70;
        } elseif ($accuracy <= 20) {
            $precision = 'exact';
            $score = 95;
        } elseif ($accuracy <= 50) {
            $precision = 'good';
            $score = 85;
        } elseif ($accuracy <= self::READY_ACCURACY_METERS) {
            $precision = 'acceptable';
            $score = 70;
        } else {
            $precision = 'low';
            $score = 40;
        }

        return [
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'geo_accuracy' => $accuracy !== null ? round($accuracy, 2) : null,
            'geo_source' => $source,
            'geo_precision' => $precision,
            'geo_precision_score' => $score,
            'geo_status' => $reliable
                ? Shop::GEO_STATUS_RELIABLE
                : Shop::GEO_STATUS_REVIEW_RECOMMENDED,
            'geo_verified_at' => $reliable ? now() : null,
            'logistics_status' => $reliable
                ? Shop::LOGISTICS_READY
                : Shop::LOGISTICS_INCOMPLETE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingPayload(): array
    {
        return [
            'latitude' => null,
            'longitude' => null,
            'geo_accuracy' => null,
            'geo_source' => null,
            'geo_precision' => null,
            'geo_precision_score' => null,
            'geo_status' => Shop::GEO_STATUS_VERIFICATION_REQUIRED,
            'geo_verified_at' => null,
            'logistics_status' => Shop::LOGISTICS_INCOMPLETE,
        ];
    }
}
