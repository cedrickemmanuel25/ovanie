<?php

namespace App\Services\Geo;

use App\Services\LogisticsTerritoryCoverageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryPointGuard
{
    private const LIVE_GPS_SOURCES = [
        'browser_live',
        'browser_gps',
        'mobile_live_gps',
        'mobile_gps',
    ];

    private const CONFIRMED_PIN_SOURCES = [
        'map_pin_confirmed',
        'mobile_map_pin',
        'manual_map',
    ];

    public function __construct(private readonly LogisticsTerritoryCoverageService $territoryCoverage)
    {
    }

    /**
     * Valide le point GPS puis la couverture opérationnelle réellement configurée
     * dans Territoire. La même règle est utilisée par le checkout Web et mobile.
     */
    public function validateOperationalPoint(Request $request): void
    {
        if ($request->input('delivery_destination_type') === 'pickup') {
            return;
        }

        $source = strtolower(trim((string) $request->input('delivery_geo_source')));
        $latitude = $request->input('delivery_latitude');
        $longitude = $request->input('delivery_longitude');

        $hasCoordinates = is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180
            && ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);

        if (in_array($source, self::CONFIRMED_PIN_SOURCES, true)) {
            if (! $hasCoordinates) {
                throw ValidationException::withMessages([
                    'delivery_latitude' => 'Le point de livraison cartographique doit être confirmé avant de continuer.',
                ]);
            }

            $this->territoryCoverage->assertRequestCovered($request);
            return;
        }

        if (in_array($source, self::LIVE_GPS_SOURCES, true)) {
            if (! $hasCoordinates) {
                throw ValidationException::withMessages([
                    'delivery_latitude' => 'La position GPS de livraison est manquante.',
                ]);
            }

            $accuracy = $request->input('delivery_geo_accuracy');
            if (! is_numeric($accuracy) || (float) $accuracy <= 0 || (float) $accuracy > 15) {
                throw ValidationException::withMessages([
                    'delivery_geo_accuracy' => 'La position GPS automatique est encore trop imprécise. Gardez la localisation précise activée et relancez « Ma position » avant de confirmer.',
                ]);
            }
        }

        $this->territoryCoverage->assertRequestCovered($request);
    }
}
