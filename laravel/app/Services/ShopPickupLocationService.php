<?php

namespace App\Services;

use App\Models\Shop;
use App\Services\Geo\GeocodingService;
use App\Services\Geo\ShopLocationResolver;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopPickupLocationService
{
    /**
     * Seuil, plus strict que MAX_GPS_ACCURACY_METERS, en-dessous duquel une
     * capture "preview_only" (position peu précise, ex. géolocalisation IP
     * d'un PC sans GPS) est jugée trop imprécise pour prétendre identifier
     * une adresse spécifique.
     */
    private const LOW_CONFIDENCE_ACCURACY_METERS = 500;

    public function resolve(Shop $shop, array $input): array
    {
        $data = Validator::make($input, [
            'preview_only' => ['sometimes', 'boolean'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geo_source' => ['sometimes', 'in:browser_gps,manual_map'],
            'map_position_confirmed' => ['required_if:geo_source,manual_map', 'accepted_if:geo_source,manual_map'],
            'geo_accuracy' => [\Illuminate\Validation\Rule::requiredIf(($input['geo_source'] ?? 'browser_gps') === 'browser_gps'), 'nullable', 'numeric', 'gt:0', 'max:'.(! empty($input['preview_only']) ? 40075000 : ShopLogisticsSettingsService::MAX_GPS_ACCURACY_METERS)],
            'geo_captured_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(15)->toIso8601String(),
                'before_or_equal:'.now()->addSeconds(10)->toIso8601String()],
        ])->validate();
        $data['geo_source'] = $data['geo_source'] ?? 'browser_gps';
        $data['geo_accuracy'] = $data['geo_source'] === 'manual_map' ? null : $data['geo_accuracy'];
        // Resolve ONLY the new device coordinates. Never use the opening address
        // as a query or recycle a previous reverse-geocoding response.
        $geo = app(GeocodingService::class)->reverse((float) $data['latitude'], (float) $data['longitude'], false);
        if (! $geo || ($geo['address_quality'] ?? null) === 'coordinates_only') {
            throw ValidationException::withMessages(['location' =>
                'Position reçue, mais son adresse ne peut pas être identifiée. Réessayez : aucune ancienne adresse ne sera validée à sa place.']);
        }
        $location = app(ShopLocationResolver::class)->toFormPayload($geo);
        if (blank($location['commune'] ?? null) || blank($location['address'] ?? null)) {
            throw ValidationException::withMessages(['location' =>
                'La commune et l’adresse de cette position n’ont pas pu être identifiées. Réessayez avant de valider.']);
        }
        $receipt = $data + [
            'shop_id' => $shop->id, 'expires_at' => now()->addMinutes(15)->timestamp,
            'commune' => trim($location['commune']), 'district' => trim($location['district'] ?? ''),
            'city' => $location['city'] ?? null, 'region' => $location['region'] ?? null,
        ];

        $filledFieldsCount = collect([$location['address'] ?? null, $location['commune'] ?? null, $location['district'] ?? null, $location['landmark'] ?? null])
            ->filter(fn ($value) => filled($value))
            ->count();
        $lowConfidence = is_numeric($data['geo_accuracy'] ?? null)
            && (float) $data['geo_accuracy'] > self::LOW_CONFIDENCE_ACCURACY_METERS
            && $filledFieldsCount < 2;

        return [
            'address' => $location['address'], 'commune' => $receipt['commune'],
            'district' => $receipt['district'], 'landmark' => $location['landmark'] ?? '',
            'display_name' => $location['display_name'] ?? $location['address'],
            'latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude'],
            'location_token' => ! empty($data['preview_only']) ? null : Crypt::encryptString(json_encode($receipt, JSON_THROW_ON_ERROR)),
            'low_confidence' => $lowConfidence,
        ];
    }

    public function verify(Shop $shop, array $input): array
    {
        try {
            $receipt = json_decode(Crypt::decryptString((string) ($input['location_token'] ?? '')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->invalid();
        }
        if (! is_array($receipt) || (int) ($receipt['shop_id'] ?? 0) !== (int) $shop->id
            || (int) ($receipt['expires_at'] ?? 0) < now()->timestamp) $this->invalid();
        if (($input['geo_source'] ?? '') !== ($receipt['geo_source'] ?? 'browser_gps')) $this->invalid();
        foreach (['latitude', 'longitude'] as $field) {
            if (! isset($receipt[$field], $input[$field]) || abs((float) $receipt[$field] - (float) $input[$field]) > 0.0000001) $this->invalid();
        }
        if ($receipt['geo_source'] === 'browser_gps' && (! isset($input['geo_accuracy'])
            || abs((float) $receipt['geo_accuracy'] - (float) $input['geo_accuracy']) > 0.0000001)) $this->invalid();
        if (($input['geo_captured_at'] ?? null) !== $receipt['geo_captured_at']) $this->invalid();
        foreach (['commune', 'district'] as $field) {
            if (filled($receipt[$field]) && $this->normalize($receipt[$field]) !== $this->normalize((string) ($input[$field] ?? ''))) {
                throw ValidationException::withMessages([$field =>
                    'Cette information ne correspond pas à la position détectée. Localisez à nouveau la boutique.']);
            }
        }
        return $receipt;
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['location' => 'Localisez la boutique et vérifiez son adresse détectée avant de continuer.']);
    }
}
