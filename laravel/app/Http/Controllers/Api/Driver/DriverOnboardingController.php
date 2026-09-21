<?php

namespace App\Http\Controllers\Api\Driver;

/**
 * API MOBILE — Application Flutter "OVANIE Livreur" (contrat figé).
 *
 * -----------------------------------------------------------------------
 * GET /api/driver/me   (auth:sanctum)
 * 200 OK :
 *   {
 *     "id": 1, "first_name": "Adama", "last_name": "Koné", "name": "Adama Koné",
 *     "phone": "+2250700000000", "email": null,
 *     "onboarding_status": "pending_review",
 *     "is_active": false, "must_change_password": false,
 *     "rating": 4.8, "status": "Disponible",
 *     "submitted_at": "2026-09-01T10:00:00+00:00",
 *     "reviewed_at": null,
 *     "rejection_reason": null,
 *     "avatar_url": "https://.../storage/drivers/avatars/xxx.jpg",
 *     "profile": {
 *       "birth_date": "1994-05-12", "identity_type": "CNI", "identity_number": "...",
 *       "vehicle": "moto", "plate": "AB-1234-CI", "vehicle_year": 2020, "capacity": "80kg",
 *       "work_hours": "08:00-18:00", "availability_days": ["lundi", "mardi"],
 *       "zones": ["Cocody", "Marcory"], "zone_ids": [3, 7],
 *       "vehicle_photos": ["drivers/onboarding/1/vehicle-0.jpg"],
 *       "documents": {"Pièce d’identité": {"status": "À vérifier"}}
 *     }
 *   }
 *
 * -----------------------------------------------------------------------
 * POST /api/driver/onboarding/submit   (auth:sanctum, multipart/form-data)
 * Champs acceptés (tous facultatifs à l'exception de ceux marqués *) :
 *   - photo                 : fichier image (photo de profil)
 *   - birth_date             : date (YYYY-MM-DD)
 *   - identity_type          : string (ex. "CNI", "Passeport", "Permis")
 *   - identity_number        : string
 *   - identity_document      : fichier (photo/scan de la pièce d'identité)
 *   - vehicle *              : string, une valeur parmi moto,tricycle,pickup,camion_3t,camion_10t
 *   - plate *                : string (immatriculation)
 *   - vehicle_year           : entier
 *   - capacity               : string (ex. "80kg")
 *   - vehicle_registration_document : fichier (carte grise)
 *   - vehicle_photos[]       : fichiers image (jusqu'à 6 photos du véhicule)
 *   - work_hours             : string (ex. "08:00-18:00")
 *   - availability_days[] *  : tableau parmi lundi..dimanche
 *   - zone_ids[] *           : tableau d'identifiants de communes (abidjan_communes.id)
 *
 * Effet : enregistre tout dans `profile` (JSON) + fichiers sur le disque public
 * (drivers/onboarding/{driver_id}/...), passe onboarding_status="pending_review"
 * et submitted_at=now(). Le dossier apparaît alors dans "Dossiers à vérifier"
 * côté back-office Logistique (voir LogisticsDirectoryController::driverReview).
 *
 * 200 OK : {"ok": true, "onboarding_status": "pending_review"}
 * 422    : erreurs de validation Laravel standard ({"message":..., "errors": {...}})
 *
 * -----------------------------------------------------------------------
 * GET /api/driver/territory/communes   (auth:sanctum)
 * Liste des communes réellement ouvertes à l'inscription : la commune doit
 * être active (abidjan_communes.is_active=true) ET rattachée à au moins une
 * zone Territoire elle-même active (logistics_territory_zones.is_active=true).
 * Remplace toute liste codée en dur côté app mobile : c'est la Logistique
 * (page Territoire) qui décide seule des communes ouvertes.
 * 200 OK : {"communes": [{"id": 1, "name": "Cocody"}, ...]} triées par nom.
 */
use App\Models\AbidjanCommune;
use App\Models\DeliveryDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverOnboardingController
{
    public function communes(): JsonResponse
    {
        $communes = AbidjanCommune::query()
            ->availableForOnboarding()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'communes' => $communes->map(fn (AbidjanCommune $commune) => [
                'id' => $commune->id,
                'name' => $commune->name,
            ])->values()->all(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var DeliveryDriver $driver */
        $driver = $request->user();

        return response()->json([
            'id' => $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'email' => $driver->email,
            'onboarding_status' => $driver->onboarding_status,
            'is_active' => (bool) $driver->is_active,
            'must_change_password' => (bool) $driver->must_change_password,
            'rating' => $driver->rating,
            'status' => $driver->status,
            'is_online' => $driver->is_online,
            'last_gps_seen_at' => $driver->lastGpsSeenAt()?->toIso8601String(),
            'presence_heartbeat_seconds' => (int) config('delivery.driver_presence_heartbeat_seconds', 45),
            'presence_offline_after_seconds' => (int) config('delivery.driver_presence_online_seconds', 120),
            'vehicle' => $driver->vehicle,
            'submitted_at' => $driver->submitted_at?->toIso8601String(),
            'reviewed_at' => $driver->reviewed_at?->toIso8601String(),
            'rejection_reason' => $driver->rejection_reason,
            'avatar_url' => $driver->avatar ? \Illuminate\Support\Facades\Storage::disk('public')->url($driver->avatar) : null,
            'profile' => $driver->profile ?? [],
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        /** @var DeliveryDriver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'birth_date' => ['nullable', 'date'],
            'identity_type' => ['nullable', 'string', 'max:60'],
            'identity_number' => ['nullable', 'string', 'max:60'],
            'identity_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'vehicle' => ['required', 'in:moto,tricycle,pickup,camion_3t,camion_10t'],
            'plate' => ['required', 'string', 'max:40'],
            'vehicle_color' => ['nullable', 'string', 'max:60'],
            'vehicle_color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'vehicle_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'capacity' => ['nullable', 'string', 'max:60'],
            'vehicle_registration_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'vehicle_photos' => ['nullable', 'array', 'max:6'],
            'vehicle_photos.*' => ['file', 'image', 'max:5120'],
            'vehicle_photo' => ['nullable', 'image', 'max:5120'],
            'plate_photo' => ['nullable', 'image', 'max:5120'],
            'supporting_documents' => ['nullable', 'array', 'max:10'],
            'supporting_documents.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'work_hours' => ['nullable', 'string', 'max:60'],
            'availability_days' => ['required', 'array', 'min:1', 'max:7'],
            'availability_days.*' => ['required', 'string', 'distinct', 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche'],
            'zone_ids' => ['required', 'array', 'min:1', 'max:50'],
            'zone_ids.*' => [
                'required',
                'integer',
                'distinct',
                function ($attribute, $value, $fail) {
                    if (! AbidjanCommune::isAvailableForOnboarding((int) $value)) {
                        $fail('Cette commune n’est plus disponible pour l’inscription.');
                    }
                },
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $communes = AbidjanCommune::query()
            ->whereIn('id', $data['zone_ids'])
            ->get(['id', 'name']);

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $basePath = 'drivers/onboarding/'.$driver->id;

        $profile = $driver->profile ?? [];
        $profile['birth_date'] = $data['birth_date'] ?? ($profile['birth_date'] ?? null);
        $profile['identity_type'] = $data['identity_type'] ?? ($profile['identity_type'] ?? null);
        $profile['identity_number'] = $data['identity_number'] ?? ($profile['identity_number'] ?? null);
        $profile['vehicle_year'] = $data['vehicle_year'] ?? ($profile['vehicle_year'] ?? null);
        $profile['capacity'] = $data['capacity'] ?? ($profile['capacity'] ?? null);
        $profile['plate'] = $data['plate'];
        if (filled($data['vehicle_color'] ?? null)) {
            $profile['vehicle_color'] = trim((string) $data['vehicle_color']);
            $profile['vehicle_color_source'] = 'vehicle_photo_auto';
        }
        if (filled($data['vehicle_color_hex'] ?? null)) {
            $profile['vehicle_color_hex'] = strtoupper(trim((string) $data['vehicle_color_hex']));
            $profile['vehicle_color_source'] = 'vehicle_photo_auto';
        }
        $profile['work_hours'] = $data['work_hours'] ?? ($profile['work_hours'] ?? null);
        $profile['availability_days'] = $data['availability_days'];
        $profile['zones'] = $communes->pluck('name')->values()->all();
        $profile['zone_ids'] = $communes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if ($request->hasFile('vehicle_photos')) {
            $photos = [];
            foreach ($request->file('vehicle_photos') as $index => $file) {
                $photos[] = $file->storeAs($basePath, 'vehicle-'.now()->timestamp.'-'.$index.'.'.$file->extension(), 'public');
            }
            $profile['vehicle_photos'] = $photos;
        }

        foreach (['vehicle_photo', 'plate_photo'] as $field) {
            if ($request->hasFile($field)) {
                $profile[$field] = $request->file($field)->store($basePath, 'public');
            }
        }
        if ($request->hasFile('supporting_documents')) {
            $profile['supporting_documents'] = [];
            foreach ($request->file('supporting_documents') as $file) {
                $profile['supporting_documents'][] = $file->store($basePath, 'public');
            }
        }

        $documents = $profile['documents'] ?? [];
        if ($request->hasFile('identity_document')) {
            $documents['Pièce d’identité'] = [
                'path' => $request->file('identity_document')->storeAs($basePath, 'identity.'.$request->file('identity_document')->extension(), 'public'),
                'status' => 'À vérifier',
            ];
        }
        if ($request->hasFile('vehicle_registration_document')) {
            $documents['Carte grise'] = [
                'path' => $request->file('vehicle_registration_document')->storeAs($basePath, 'registration.'.$request->file('vehicle_registration_document')->extension(), 'public'),
                'status' => 'À vérifier',
            ];
        }
        if ($documents) {
            $profile['documents'] = $documents;
        }

        $driverAttributes = [
            'vehicle' => $data['vehicle'],
            'profile' => $profile,
            'onboarding_status' => DeliveryDriver::ONBOARDING_PENDING_REVIEW,
            'submitted_at' => now(),
        ];

        if ($communes->isNotEmpty()) {
            $driverAttributes['zone'] = $communes->first()->name;
            $driverAttributes['commune_id'] = $communes->first()->id;
        }

        if ($request->hasFile('photo')) {
            $driverAttributes['avatar'] = $request->file('photo')->store('drivers/avatars', 'public');
        }

        $driver->update($driverAttributes);

        return response()->json([
            'ok' => true,
            'onboarding_status' => $driver->onboarding_status,
        ]);
    }
}
