<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\Geo\AbidjanLocalityRegistry;
use App\Services\Geo\GeocodingService;
use App\Services\OvanieReferenceDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function __construct(private readonly OvanieReferenceDataService $references)
    {
    }

    public function index(Request $request)
    {
        $query = Address::query();
        $user = $request->user();

        if ($user && ($user->is_admin || $user->role === 'admin') && $request->has('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        } elseif ($user) {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->orderByDesc('is_default')->latest()->get()
                ->map(fn (Address $address) => $this->serializeAddress($address))
                ->values(),
            'types' => $this->addressTypes(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $address = Address::findOrFail($id);
        $this->authorizeAddress($request, $address);

        return response()->json(['data' => $this->serializeAddress($address)]);
    }

    public function store(Request $request, GeocodingService $geocoding, AbidjanLocalityRegistry $registry)
    {
        $validated = $this->normalizeLocation($this->validateAddress($request, true), $geocoding, $registry);
        $validated['user_id'] = $this->resolvedUserId($request, $validated['user_id'] ?? null);
        $validated['recipient_name'] = ($validated['recipient_name'] ?? null) ?: $request->user()->name;

        $address = DB::transaction(function () use ($validated) {
            if (! empty($validated['is_default'])) {
                Address::where('user_id', $validated['user_id'])->update(['is_default' => false]);
            }

            $hasAddress = Address::where('user_id', $validated['user_id'])->exists();
            if (! $hasAddress) {
                $validated['is_default'] = true;
            }

            return Address::create($validated);
        }, 3);

        return response()->json(['data' => $this->serializeAddress($address)], 201);
    }

    public function update(Request $request, $id, GeocodingService $geocoding, AbidjanLocalityRegistry $registry)
    {
        $address = Address::findOrFail($id);
        $this->authorizeAddress($request, $address);
        $validated = $this->validateAddress($request, false);
        if ($request->hasAny(['city', 'commune', 'quartier', 'country', 'address', 'latitude', 'longitude'])) {
            $validated = $this->normalizeLocation(
                array_merge($address->only(['city', 'commune', 'quartier', 'country', 'address', 'latitude', 'longitude']), $validated),
                $geocoding,
                $registry
            );
        }

        DB::transaction(function () use ($address, $validated) {
            if (! empty($validated['is_default'])) {
                Address::where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);

            if (! Address::where('user_id', $address->user_id)->where('is_default', true)->exists()) {
                $address->forceFill(['is_default' => true])->save();
            }
        }, 3);

        return response()->json(['data' => $this->serializeAddress($address->fresh())]);
    }

    public function destroy(Request $request, $id)
    {
        $address = Address::findOrFail($id);
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($address) {
            $userId = $address->user_id;
            $wasDefault = (bool) $address->is_default;
            $address->delete();

            if ($wasDefault) {
                Address::where('user_id', $userId)
                    ->latest()
                    ->first()
                    ?->forceFill(['is_default' => true])
                    ->save();
            }
        }, 3);

        return response()->json(['message' => 'Adresse supprimée avec succès.']);
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($address) {
            Address::where('user_id', $address->user_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();
        }, 3);

        return response()->json([
            'message' => 'Adresse principale mise à jour.',
            'data' => $this->serializeAddress($address->fresh()),
        ]);
    }

    private function addressTypes(): array
    {
        return $this->references->options('address_types');
    }

    private function serializeAddress(Address $address): array
    {
        $typeLabels = collect($this->addressTypes())->pluck('label', 'code');
        $type = (string) ($address->type ?: 'home');
        $location = app(AbidjanLocalityRegistry::class)->resolve(
            [$address->commune, $address->quartier, $address->address],
            $address->commune,
            $address->quartier
        );
        $localityType = (string) ($location['locality_type'] ?? '');
        $parentQuarter = (string) ($location['parent_quarter'] ?? '');

        return [
            'id' => (int) $address->id,
            'type' => $type,
            'type_label' => $typeLabels[$type] ?? 'Adresse',
            'label' => (string) $address->label,
            'recipient_name' => (string) ($address->recipient_name ?? ''),
            'city' => (string) ($address->city ?? ''),
            'commune' => (string) ($address->commune ?? ''),
            'quartier' => (string) ($address->quartier ?? ''),
            'quartier_principal' => $parentQuarter !== ''
                ? $parentQuarter
                : ($localityType === 'quartier' ? (string) ($address->quartier ?? '') : ''),
            'sous_quartier' => $localityType === 'sous_quartier'
                ? (string) ($address->quartier ?? '')
                : '',
            'locality_type' => $localityType,
            'locality_type_label' => (string) ($location['locality_type_label'] ?? ''),
            'country' => (string) ($address->country ?? "Côte d'Ivoire"),
            'address' => (string) $address->address,
            'phone' => (string) $address->phone,
            'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
            'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
            'is_default' => (bool) $address->is_default,
            'updated_at' => optional($address->updated_at)->toIso8601String(),
        ];
    }

    private function validateAddress(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => [$required, Rule::in($this->references->codes('address_types'))],
            'label' => [$required, 'string', 'max:100'],
            'recipient_name' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:150'],
            'city' => [$required, 'string', 'max:100'],
            'commune' => [$required, 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:100'],
            'address' => [$required, 'string', 'max:500'],
            'phone' => [$required, 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizeLocation(array $data, GeocodingService $geocoding, AbidjanLocalityRegistry $registry): array
    {
        try {
            if (isset($data['latitude'], $data['longitude'])) {
                $geo = $geocoding->reverse((float) $data['latitude'], (float) $data['longitude']);
            } else {
                $geo = $geocoding->searchBestMatch(
                    $data['address'] ?? null,
                    $data['commune'] ?? $data['city'] ?? null,
                    $data['quartier'] ?? null,
                    $data['country'] ?? "Cote d'Ivoire"
                );
            }

            if ($geo) {
                $data['latitude'] = $geo['latitude'] ?? $data['latitude'] ?? null;
                $data['longitude'] = $geo['longitude'] ?? $data['longitude'] ?? null;
                $resolved = (array) ($geo['resolved_location'] ?? []);

                if (($resolved['is_abidjan'] ?? false) === true) {
                    $data['city'] = 'Abidjan';
                    $data['commune'] = $resolved['commune']
                        ?: $registry->canonicalCommune($data['commune'] ?? null)
                        ?: ($data['commune'] ?? 'Abidjan');
                    $data['quartier'] = $resolved['quartier'] ?? $data['quartier'] ?? null;
                }
            } else {
                $data['commune'] = $registry->canonicalCommune($data['commune'] ?? null) ?: ($data['commune'] ?? null);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $data['commune'] = $registry->canonicalCommune($data['commune'] ?? null) ?: ($data['commune'] ?? null);
        }

        return $data;
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        $user = $request->user();

        abort_unless(
            $user && (($user->is_admin || $user->role === 'admin') || (int) $address->user_id === (int) $user->id),
            403
        );
    }

    private function resolvedUserId(Request $request, ?int $requestedUserId): int
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (($user->is_admin || $user->role === 'admin') && $requestedUserId) {
            return $requestedUserId;
        }

        return $user->id;
    }
}
