<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\Geo\AbidjanLocationResolver;
use App\Services\Geo\AbidjanLocalityRegistry;

class CheckoutAddressResolver
{
    public function __construct(
        private readonly AbidjanLocationResolver $locations,
        private readonly AbidjanLocalityRegistry $registry
    ) {}

    public function applyToRequest(Request $request): void
    {
        if ($request->input('delivery_destination_type') === 'pickup') {
            return;
        }

        $addressId = (int) $request->input('saved_address_id', 0);
        if ($addressId <= 0) {
            $this->normalizeRequestZone($request);
            return;
        }

        $address = $this->ownedAddress($request->user(), $addressId);
        $snapshot = $this->snapshot($address);

        $recipientName = $request->input('full_name') ?: $snapshot['recipient_name'];
        $recipientPhone = $request->input('whatsapp_phone') ?: $snapshot['phone'];

        $request->merge([
            'full_name' => $recipientName,
            'phone' => $request->input('phone') ?: $recipientPhone,
            'delivery_recipient_name' => $recipientName,
            'delivery_recipient_phone' => $recipientPhone,
            'whatsapp_phone' => $recipientPhone,
            'address' => $snapshot['address'],
            'delivery_zone' => $snapshot['delivery_zone'],
            'delivery_commune' => $snapshot['delivery_commune'],
            'delivery_quartier' => $snapshot['delivery_quartier'],
            'delivery_locality_type' => $snapshot['delivery_locality_type'] ?? null,
            'delivery_city' => $snapshot['delivery_city'],
            'delivery_latitude' => $snapshot['delivery_latitude'],
            'delivery_longitude' => $snapshot['delivery_longitude'],
            'delivery_geo_source' => 'address_book',
        ]);
    }

    public function snapshot(Address $address): array
    {
        $isAbidjan = $this->isAbidjan($address->city, $address->commune, $address->quartier, $address->address);
        $commune = $this->resolveAbidjanCommune(
            $address->commune ?: ($isAbidjan ? $address->city : null),
            $address->quartier,
            $address->address
        );

        $locality = $this->registry->resolve(
            [$address->commune, $address->quartier, $address->address, $address->city],
            $commune ?: $address->commune,
            $address->quartier
        );

        return [
            'saved_address_id' => (int) $address->id,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'address' => $address->address,
            'delivery_zone' => $isAbidjan ? 'abidjan' : 'interieur',
            // La commune parente alimente la grille tarifaire.
            'delivery_commune' => ($locality['commune'] ?? null) ?: $commune,
            // La localité conserve le niveau le plus fin reconnu.
            'delivery_quartier' => ($locality['quarter'] ?? null) ?: $address->quartier,
            'delivery_locality_type' => $locality['locality_type'] ?? null,
            'delivery_city' => $isAbidjan ? null : $address->city,
            'delivery_latitude' => is_numeric($address->latitude) ? (float) $address->latitude : null,
            'delivery_longitude' => is_numeric($address->longitude) ? (float) $address->longitude : null,
        ];
    }

    public function ownedAddress(User $user, int $addressId): Address
    {
        $address = $user->addresses()->whereKey($addressId)->first();

        if (! $address) {
            throw ValidationException::withMessages([
                'saved_address_id' => 'L’adresse sélectionnée ne vous appartient pas ou n’existe plus.',
            ]);
        }

        return $address;
    }

    public function isAbidjan(?string ...$parts): bool
    {
        return $this->locations->isAbidjan(
            collect($parts)->filter(fn ($value) => filled($value))->values()->all()
        );
    }

    private function normalizeRequestZone(Request $request): void
    {
        $parts = collect([
            $request->input('delivery_city'),
            $request->input('delivery_commune'),
            $request->input('delivery_quartier'),
            $request->input('address'),
        ])->filter(fn ($value) => filled($value))->values()->all();

        $resolved = $this->registry->resolve(
            $parts,
            $request->input('delivery_commune'),
            $request->input('delivery_quartier')
        );

        $isAbidjan = (bool) ($resolved['is_abidjan'] ?? false) || $this->isAbidjan(...$parts);
        $commune = $isAbidjan
            ? (($resolved['commune'] ?? null) ?: $this->resolveAbidjanCommune(
                $request->input('delivery_commune'),
                $request->input('delivery_quartier'),
                $request->input('address')
            ))
            : $request->input('delivery_commune');

        // delivery_quartier transporte volontairement la localité la plus fine
        // connue : quartier, sous-quartier, cité, village ou carrefour. Le tarif
        // reste rattaché à la commune parente.
        $quartier = ($resolved['quarter'] ?? null) ?: $request->input('delivery_quartier');

        $request->merge([
            'delivery_zone' => $isAbidjan ? 'abidjan' : 'interieur',
            'delivery_commune' => $commune,
            'delivery_quartier' => $quartier,
            'delivery_locality_type' => $resolved['locality_type'] ?? $request->input('delivery_locality_type'),
            'delivery_city' => $isAbidjan ? null : $request->input('delivery_city'),
        ]);
    }

    private function resolveAbidjanCommune(?string $current, ?string ...$locationParts): ?string
    {
        $detected = $this->locations->detectCommune(
            collect([$current, ...$locationParts])->filter()->values()->all()
        );

        if ($detected) {
            return $detected;
        }

        return $this->locations->isGenericAbidjan($current) ? 'Abidjan' : null;
    }

}
