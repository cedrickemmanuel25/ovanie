<?php

namespace App\Services\Geo;

use App\Models\Order;
use App\Models\Shipment;

class DeliveryCoordinateService
{
    /**
     * Sources qui correspondent à une position réellement enregistrée pour la commande.
     * Le géocodage n'est opérationnel que lorsqu'il a été explicitement autorisé.
     */
    private const OPERATIONAL_SOURCES = [
        'browser',
        'browser_gps',
        'browser_live',
        'map_pin_confirmed',
        'manual_map',
        'address_book',
        'mobile_api',
        'mobile_gps',
        'mobile_live_gps',
        'mobile_map_pin',
        'logistics_verified',
    ];

    /** @return array{latitude:?float,longitude:?float,source:?string,verified:bool} */
    public function forOrder(?Order $order): array
    {
        if (! $order) {
            return $this->empty();
        }

        $source = strtolower(trim((string) ($order->delivery_geo_source ?? '')));

        if ($this->isPlaceholderSource($source)) {
            return $this->empty($source ?: null);
        }

        // Les colonnes explicites delivery_latitude / delivery_longitude sont la
        // source principale du checkout actuel. Elles ne doivent jamais être
        // remplacées par les anciens centres approximatifs de commune.
        if ($this->valid($order->delivery_latitude, $order->delivery_longitude)) {
            $verified = $this->sourceIsOperational($source) || $source === '';

            if ($source === 'geocoding') {
                $verified = (bool) config('geo.allow_operational_geocoding', false);
            }

            return [
                'latitude' => (float) $order->delivery_latitude,
                'longitude' => (float) $order->delivery_longitude,
                'source' => $source ?: null,
                'verified' => $verified,
            ];
        }

        // Les anciennes colonnes delivery_lat / delivery_lng ont été utilisées
        // par une migration pour injecter des centres de commune. On ne les
        // accepte donc plus sans provenance opérationnelle explicite.
        if ($this->valid($order->delivery_lat, $order->delivery_lng)
            && ($this->sourceIsOperational($source)
                || ($source === 'geocoding' && (bool) config('geo.allow_operational_geocoding', false)))) {
            return [
                'latitude' => (float) $order->delivery_lat,
                'longitude' => (float) $order->delivery_lng,
                'source' => $source,
                'verified' => true,
            ];
        }

        return $this->empty($source ?: null);
    }

    /** @return array{latitude:?float,longitude:?float,source:?string,verified:bool} */
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing('order');

        // La commande reste la source de vérité de la destination. Cela empêche
        // une ancienne expédition contenant un centre de commune approximatif de
        // réintroduire une fausse position sur les cartes.
        if ($shipment->order) {
            return $this->forOrder($shipment->order);
        }

        // Compatibilité prudente pour une expédition orpheline réellement
        // géolocalisée. Ce cas ne doit normalement pas se produire.
        if ($this->valid($shipment->delivery_latitude, $shipment->delivery_longitude)) {
            return [
                'latitude' => (float) $shipment->delivery_latitude,
                'longitude' => (float) $shipment->delivery_longitude,
                'source' => 'shipment',
                'verified' => false,
            ];
        }

        return $this->empty();
    }

    public function valid($latitude, $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude === 0.0 && $longitude === 0.0) {
            return false;
        }

        $bounds = config('geo.country_bounds', []);
        $minLat = (float) ($bounds['min_lat'] ?? -90);
        $maxLat = (float) ($bounds['max_lat'] ?? 90);
        $minLng = (float) ($bounds['min_lng'] ?? -180);
        $maxLng = (float) ($bounds['max_lng'] ?? 180);

        return $latitude >= $minLat && $latitude <= $maxLat
            && $longitude >= $minLng && $longitude <= $maxLng;
    }

    public function sourceIsOperational(?string $source): bool
    {
        $source = strtolower(trim((string) $source));

        return in_array($source, self::OPERATIONAL_SOURCES, true);
    }

    private function isPlaceholderSource(string $source): bool
    {
        if ($source === '') {
            return false;
        }

        foreach (['demo', 'seed', 'placeholder', 'commune_default', 'commune-centre', 'legacy_commune'] as $fragment) {
            if (str_contains($source, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{latitude:null,longitude:null,source:?string,verified:false} */
    private function empty(?string $source = null): array
    {
        return [
            'latitude' => null,
            'longitude' => null,
            'source' => $source,
            'verified' => false,
        ];
    }
}
