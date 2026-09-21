<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\DeliveryDriver;
use App\Models\LogisticsFleetVehicle;
use App\Models\OrderItem;
use App\Models\ReturnModel;
use App\Services\VehicleAppearanceService;
use App\ViewModels\LogisticsDirectoryData as Data;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LogisticsDirectoryController extends Controller
{
    public function drivers(Request $request)
    {
        $all = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->with('currentLocation')
            ->withCount([
                'assignments' => fn ($query) => LogisticsOperationalDataScope::assignments($query),
                'activeAssignments' => fn ($query) => LogisticsOperationalDataScope::assignments($query),
            ])
            ->orderBy('id')
            ->get();

        $fleetVehicles = Schema::hasTable('logistics_fleet_vehicles')
            ? LogisticsOperationalDataScope::fleetVehicles(LogisticsFleetVehicle::query())
                ->orderBy('id')
                ->get()
            : collect();

        // Rattache le véhicule réel de la flotte au livreur. Le driver_id stocké dans
        // meta est prioritaire; les correspondances nom/immatriculation sont des secours
        // pour les données historiques déjà présentes dans le projet.
        foreach ($all as $driver) {
            $plate = mb_strtolower(trim((string) data_get($driver->profile, 'plate', '')));
            $name = mb_strtolower(trim((string) $driver->name));
            $fleet = $fleetVehicles->first(function ($vehicle) use ($driver, $plate, $name) {
                $metaDriverId = (int) data_get($vehicle->meta, 'driver_id', 0);
                if ($metaDriverId > 0 && $metaDriverId === (int) $driver->id) {
                    return true;
                }
                if ($name !== '' && mb_strtolower(trim((string) $vehicle->driver_name)) === $name) {
                    return true;
                }
                return $plate !== '' && mb_strtolower(trim((string) $vehicle->registration)) === $plate;
            });

            $driver->setAttribute('fleet_vehicle_type', $fleet?->vehicle_type);
            $driver->setAttribute('fleet_vehicle_registration', $fleet?->registration);
            $driver->setAttribute('fleet_vehicle_label', $fleet
                ? trim($fleet->vehicle_type.' '.($fleet->brand ?: '').' '.($fleet->model ?: '')).' ('.$fleet->registration.')'
                : $driver->vehicle);
        }

        $filtered = $all->filter(function ($d) use ($request) {
            $vehicleText = trim(($d->fleet_vehicle_label ?? '').' '.$d->vehicle);
            $zonesText = implode(' ', $d->interventionZones());

            return (! $request->q || str_contains(mb_strtolower($d->name.' '.$zonesText.' '.$vehicleText), mb_strtolower($request->q)))
                && (! $request->status || Data::driverStatus($d)[0] === $request->status)
                && (! $request->zone || $d->coversZone((string) $request->zone))
                && (! $request->vehicle || str_contains(mb_strtolower($vehicleText), mb_strtolower($request->vehicle)))
                && (! $request->onboarding || $d->onboarding_status === $request->onboarding);
        });

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($filtered) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['Livreur', 'Téléphone', 'Véhicule', 'Zones d’intervention', 'Statut']);
                foreach ($filtered as $d) {
                    fputcsv($f, [$d->name, $d->phone, $d->fleet_vehicle_label ?: $d->vehicle, implode(', ', $d->interventionZones()), Data::driverStatus($d)[0]]);
                }
                fclose($f);
            }, 'livreurs.csv');
        }

        $fleetInUse = $fleetVehicles
            ->filter(fn ($vehicle) => $vehicle->status !== 'out_of_service')
            ->groupBy('vehicle_type')
            ->map->count()
            ->sortDesc();

        $driversToWatch = $all
            ->filter(fn ($driver) => $driver && (int) ($driver->active_assignments_count ?? 0) > 0)
            ->sortBy(fn ($driver) => $driver->currentLocation?->recorded_at?->getTimestamp() ?? 0)
            ->take(3)
            ->values();

        $per = max(1, min(100, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));

        $onboardingCounts = $all->countBy(fn ($d) => $d->onboarding_status ?: DeliveryDriver::ONBOARDING_ACTIVE);

        return view('logistics.drivers', [
            'drivers' => new LengthAwarePaginator(
                $filtered->forPage($page, $per)->values(),
                $filtered->count(),
                $per,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ),
            'allDrivers' => $all,
            'zones' => AbidjanCommune::where('is_active', true)->orderBy('name')->get(),
            'fleetInUse' => $fleetInUse,
            'fleetVehiclesTotal' => $fleetVehicles->count(),
            'driversToWatch' => $driversToWatch,
            'onboardingCounts' => $onboardingCounts,
        ]);
    }

    public function driver(?DeliveryDriver $driver = null)
    {
        $driver ??= LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->orderBy('id')->first();
        if (! $driver) {
            return redirect()->route('logistics.drivers')->with('info', 'Ajoutez un livreur pour consulter sa fiche.');
        }

        abort_unless(
            LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->whereKey($driver->getKey())->exists(),
            404
        );

        $assignmentScope = fn ($query) => LogisticsOperationalDataScope::assignments($query);
        $driver->load([
            'currentLocation',
            'assignments' => $assignmentScope,
            'assignments.orderItem.order',
            'assignments.orderItem.product.shop',
        ])->loadCount([
            'assignments' => $assignmentScope,
            'activeAssignments' => $assignmentScope,
        ]);

        $zoneIds = $driver->interventionZoneIds();
        $zoneCommunesById = AbidjanCommune::query()
            ->where('is_active', true)
            ->whereIn('id', $zoneIds)
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->keyBy('id');

        // Conserve exactement l'ordre des communes choisi dans le modal.
        $interventionZoneCommunes = collect($zoneIds)
            ->map(fn ($id) => $zoneCommunesById->get((int) $id))
            ->filter()
            ->values();

        $interventionZonePoints = $interventionZoneCommunes
            ->filter(fn ($commune) => is_numeric($commune->latitude) && is_numeric($commune->longitude))
            ->map(fn ($commune) => [
                'lat' => (float) $commune->latitude,
                'lng' => (float) $commune->longitude,
                'label' => (string) $commune->name,
                'color' => '#009c60',
                // Sur une couverture multi-communes, le nom apparaît au survol
                // afin de garder la carte lisible même avec 10+ marqueurs.
                'permanent' => false,
            ])
            ->values()
            ->all();

        return view('logistics.driver-profile', [
            'driver' => $driver,
            'missions' => $driver->assignments->sortByDesc('created_at')->take(5),
            'zones' => AbidjanCommune::where('is_active', true)->orderBy('name')->get(),
            'interventionZoneCommunes' => $interventionZoneCommunes,
            'interventionZonePoints' => $interventionZonePoints,
        ]);
    }

    /**
     * OVANIE ne recrute pas ses propres livreurs : la Logistique se contente
     * d'inviter un livreur partenaire (nom, prénom, téléphone). C'est lui qui
     * complète ensuite son dossier (véhicule, zones, pièces justificatives...)
     * depuis l'application mobile "OVANIE Livreur".
     */
    public function storeDriver(Request $request)
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:120',
            'first_name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:30', Rule::unique('delivery_drivers', 'phone')],
        ], [
            'last_name.required' => 'Renseignez le nom du livreur.',
            'first_name.required' => 'Renseignez le prénom du livreur.',
            'phone.required' => 'Renseignez le numéro de téléphone du livreur.',
            'phone.unique' => 'Ce numéro de téléphone est déjà associé à un livreur.',
        ]);

        $driver = DeliveryDriver::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'status' => 'Indisponible',
            // Le compte n'est activé qu'une fois le dossier soumis puis validé.
            'is_active' => false,
            'is_online' => false,
            'onboarding_status' => DeliveryDriver::ONBOARDING_INVITED,
            'profile' => [],
        ]);

        return redirect()->route('logistics.drivers.show', $driver)
            ->with('success', 'Livreur invité. Il pourra compléter son dossier depuis l’application mobile OVANIE Livreur avec ce numéro de téléphone.');
    }

    public function updateDriver(Request $request, DeliveryDriver $driver)
    {
        if ($request->has('toggle_active')) {
            abort_unless(in_array($driver->onboarding_status, [DeliveryDriver::ONBOARDING_ACTIVE, DeliveryDriver::ONBOARDING_SUSPENDED]), 422, 'Validez d’abord le dossier d’inscription.');
            $driver->update(['is_active' => ! $driver->is_active]);

            return back()->with('success', 'État du compte mis à jour.');
        }
        if ($request->has('availability')) {
            $request->validate(['availability' => 'required|in:Disponible,Indisponible']);
            $driver->update(['status' => $request->availability]);

            return back()->with('success', 'Disponibilité mise à jour.');
        }
        if ($request->has('note')) {
            $request->validate(['note' => 'required|string|max:1000']);
            $profile = $driver->profile ?? [];
            $profile['notes'][] = ['text' => $request->note, 'author' => $request->user('admin')?->name ?? 'Responsable Logistique', 'at' => now()->toIso8601String()];
            $driver->update(['profile' => $profile]);

            return back()->with('success', 'Note ajoutée.');
        }
        $data = $this->validateDriver($request, $driver);
        $data['profile'] = array_replace($driver->profile ?? [], $data['profile'] ?? []);
        $driver->update($data);

        return back()->with('success', 'Fiche livreur mise à jour.');
    }

    private function validateDriver(Request $request, ?DeliveryDriver $driver = null): array
    {
        $request->validate([
            'documents' => 'nullable|array',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Cette méthode ne sert désormais qu'à l'édition (updateDriver) : la création
        // se limite à nom/prénom/téléphone (voir storeDriver). Un livreur invité qui
        // n'a pas encore complété son dossier depuis l'app mobile peut ne pas avoir
        // de véhicule/zones/plaque : ces champs restent donc facultatifs à l'édition
        // tant qu'ils n'ont pas déjà été renseignés (par la Logistique ou le livreur).
        $hasExistingZones = ! empty($driver?->interventionZoneIds());
        $zoneRule = $hasExistingZones || $request->filled('zone_ids') ? 'required' : 'nullable';

        $data = $request->validate([
            'name' => 'required|string|max:190',
            'phone' => ['required', 'string', 'max:30', Rule::unique('delivery_drivers', 'phone')->ignore($driver?->id)],
            'email' => ['nullable', 'email', Rule::unique('delivery_drivers', 'email')->ignore($driver?->id)],
            'zone_ids' => $zoneRule.'|array|min:1|max:50',
            'zone_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('abidjan_communes', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'vehicle' => ($driver?->vehicle ? 'nullable' : 'required').'|in:moto,tricycle,pickup,camion_3t,camion_10t',
            'plate' => (data_get($driver?->profile, 'plate') ? 'nullable' : 'required').'|string|max:40',
            'availability_days' => (data_get($driver?->profile, 'availability_days') ? 'nullable' : 'required').'|array|min:1|max:7',
            'availability_days.*' => 'required|string|distinct|in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'zone_ids.required' => 'Sélectionnez au moins une zone d’intervention.',
            'zone_ids.min' => 'Sélectionnez au moins une zone d’intervention.',
            'zone_ids.*.exists' => 'Une zone sélectionnée n’est plus disponible.',
        ]);

        $hasZoneSubmission = array_key_exists('zone_ids', $data) && $data['zone_ids'] !== null;
        $profile = [];

        if ($hasZoneSubmission) {
            $submittedZoneIds = collect($data['zone_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $communesById = AbidjanCommune::query()
                ->where('is_active', true)
                ->whereIn('id', $submittedZoneIds)
                ->get(['id', 'name'])
                ->keyBy('id');

            $selectedCommunes = $submittedZoneIds
                ->map(fn ($id) => $communesById->get($id))
                ->filter()
                ->values();

            if ($selectedCommunes->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'zone_ids' => 'Sélectionnez au moins une zone d’intervention.',
                ]);
            }

            $primaryCommune = $selectedCommunes->first();
            $data['zone'] = $primaryCommune->name;
            $data['commune_id'] = $primaryCommune->id;

            $profile['zones'] = $selectedCommunes->pluck('name')->values()->all();
            $profile['zone_ids'] = $selectedCommunes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }
        unset($data['zone_ids']);

        foreach (['plate', 'availability_days'] as $key) {
            if (array_key_exists($key, $data)) {
                $profile[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('drivers/avatars', 'public');
        }

        $stored = $driver?->profile['documents'] ?? [];
        foreach ([
            'identity' => 'Pièce d’identité',
            'registration' => 'Carte grise',
            'license' => 'Permis de conduire',
            'insurance' => 'Assurance',
        ] as $key => $label) {
            if ($request->hasFile('documents.'.$key)) {
                $stored[$label] = [
                    'path' => $request->file('documents.'.$key)->store('private-documents/drivers', 'local'),
                    'status' => 'À vérifier',
                ];
            }
        }


        if ($stored) {
            $profile['documents'] = $stored;
        }

        $data['profile'] = $profile;

        return $data;
    }

    public function document(DeliveryDriver $driver, string $document)
    {
        $label = ['identity' => 'Pièce d’identité', 'registration' => 'Carte grise', 'license' => 'Permis de conduire', 'insurance' => 'Assurance'][$document] ?? null;
        $path = $label ? ($driver->profile['documents'][$label]['path'] ?? null) : null;
        if (preg_match('/^supporting-(\d+)$/', $document, $matches)) {
            $path = data_get($driver->profile, 'supporting_documents.'.(int) $matches[1]);
        }
        abort_unless($path && ! str_contains($path, '..'), 404);
        // Mobile uploads and back-office uploads use different storage disks.
        $diskName = match (true) {
            str_starts_with($path, 'private-documents/drivers/') => 'local',
            str_starts_with($path, 'drivers/onboarding/'.$driver->id.'/') => 'public',
            default => null,
        };
        abort_unless($diskName, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function access(Request $request, DeliveryDriver $driver)
    {
        abort(410, 'La connexion se fait par code OTP SMS depuis l’application livreur. Validez le dossier pour activer le compte.');
    }

    public function destroyDriver(DeliveryDriver $driver)
    {
        $hasActiveMission = $driver->assignments()
            ->whereNotIn('status', ['delivered', 'cancelled', 'failed'])
            ->exists();

        abort_if($hasActiveMission, 422, 'Ce livreur a une mission en cours : elle doit être terminée ou réassignée avant suppression.');

        if ($driver->assignments()->exists()) {
            abort(422, 'Ce livreur a un historique de missions et ne peut pas être supprimé. Désactivez son compte à la place.');
        }

        DB::transaction(function () use ($driver) {
            if ($driver->avatar) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($driver->avatar);
            }

            if (Schema::hasTable('driver_locations')) {
                DB::table('driver_locations')->where('driver_id', $driver->id)->delete();
            }

            if (Schema::hasTable('logistics_fleet_vehicles')) {
                LogisticsFleetVehicle::whereJsonContains('meta->driver_id', (int) $driver->id)->get()
                    ->each(function (LogisticsFleetVehicle $vehicle) use ($driver) {
                        if (data_get($vehicle->meta, 'source') === 'driver_profile') {
                            // Fiche auto-créée depuis le profil du livreur (pas un vrai véhicule
                            // de flotte enregistré par la logistique) : elle n'a plus de raison
                            // d'exister sans le compte livreur.
                            $vehicle->delete();
                        } else {
                            // Vrai véhicule de flotte : on le garde, on retire juste le livreur.
                            $vehicle->update([
                                'driver_name' => null,
                                'driver_phone' => null,
                                'meta' => collect($vehicle->meta ?? [])
                                    ->except(['driver_id'])
                                    ->all(),
                            ]);
                        }
                    });
            }

            $driver->delete();
        });

        return redirect()->route('logistics.drivers')->with('success', 'Compte livreur supprimé.');
    }

    /**
     * Fiche de revue d'un dossier livreur soumis depuis l'app mobile OVANIE Livreur.
     */
    public function driverReview(DeliveryDriver $driver)
    {
        abort_unless(
            LogisticsOperationalDataScope::drivers(DeliveryDriver::query())->whereKey($driver->getKey())->exists(),
            404
        );

        return view('logistics.driver-review', [
            'driver' => $driver,
            'zones' => AbidjanCommune::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Valide le dossier d'un livreur partenaire : il devient actif et peut
     * désormais recevoir des missions.
     */
    public function approveDriver(Request $request, DeliveryDriver $driver)
    {
        $driver->update([
            'onboarding_status' => DeliveryDriver::ONBOARDING_ACTIVE,
            'is_active' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user('admin')?->id,
            'rejection_reason' => null,
        ]);

        // Analyse une seule fois la vraie photo du véhicule avant la création /
        // synchronisation de la fiche Flotte. Aucune couleur fictive n'est inventée.
        app(VehicleAppearanceService::class)->forDriver($driver);
        $driver->refresh();
        $this->syncFleetVehicleFromDriverProfile($driver);

        return redirect()->route('logistics.drivers.show', $driver)->with('success', 'Dossier validé. Le livreur est désormais actif.');
    }

    /**
     * Alimente automatiquement la page Flotte à partir du véhicule déclaré par le
     * livreur dans son profil (app mobile), afin d'éviter une resaisie manuelle
     * redondante véhicule/immatriculation à chaque validation de dossier.
     */
    private function syncFleetVehicleFromDriverProfile(DeliveryDriver $driver): void
    {
        if (! Schema::hasTable('logistics_fleet_vehicles')) {
            return;
        }

        $plate = trim((string) data_get($driver->profile, 'plate', ''));
        if ($plate === '' || ! $driver->vehicle) {
            return;
        }

        $existing = LogisticsFleetVehicle::query()
            ->where(function ($query) use ($driver, $plate) {
                $query->where('registration', $plate)
                    ->orWhereJsonContains('meta->driver_id', (int) $driver->id);
            })
            ->first();

        $specification = (new LogisticsFleetVehicle(['vehicle_type' => $driver->vehicle]))->vehicleSpecification();

        $attributes = [
            'registration' => $plate,
            'vehicle_type' => $driver->vehicle,
            'capacity_kg' => $specification['max_weight_kg'] ?? 0,
            'volume_m3' => $specification['max_volume_m3'] ?? null,
            'year' => data_get($driver->profile, 'vehicle_year'),
            'zone' => $driver->zone,
            'driver_name' => $driver->name,
            'driver_phone' => $driver->phone,
            'meta' => array_replace(
                $existing?->meta ?? [],
                [
                    'driver_id' => (int) $driver->id,
                    'source' => 'driver_profile',
                    'vehicle_color' => data_get($driver->profile, 'vehicle_color'),
                    'vehicle_color_hex' => data_get($driver->profile, 'vehicle_color_hex'),
                    'vehicle_color_source' => data_get($driver->profile, 'vehicle_color_source'),
                ],
                $driver->vehiclePhotoPath() ? ['photo_path' => $driver->vehiclePhotoPath()] : []
            ),
        ];

        if ($existing) {
            $existing->update($attributes);
            $vehicle = $existing->fresh();
        } else {
            $vehicle = LogisticsFleetVehicle::create($attributes + [
                'code' => 'LIV-'.$driver->id.'-'.Str::upper(Str::random(4)),
            ]);
        }

        $profile = is_array($driver->profile) ? $driver->profile : [];
        $profile['fleet_vehicle_id'] = $vehicle->id;
        $profile['plate'] = $vehicle->registration;
        $driver->forceFill(['profile' => $profile])->saveQuietly();
    }

    /**
     * Refuse le dossier d'un livreur partenaire avec un motif.
     */
    public function rejectDriver(Request $request, DeliveryDriver $driver)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [
            'rejection_reason.required' => 'Indiquez le motif du refus.',
        ]);

        $driver->update([
            'onboarding_status' => DeliveryDriver::ONBOARDING_REJECTED,
            'is_active' => false,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user('admin')?->id,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return redirect()->route('logistics.drivers')->with('success', 'Dossier refusé. Le livreur a été notifié du motif.');
    }

    /**
     * Demande une correction sur le dossier d'un livreur partenaire : contrairement
     * à un refus définitif (rejectDriver), le dossier repasse à l'état "invited"
     * (comme un livreur qui n'a pas encore soumis son dossier) afin qu'il puisse
     * le corriger et le resoumettre depuis l'app mobile OVANIE Livreur. Le motif
     * de correction est conservé dans `rejection_reason` (même champ que le refus,
     * mais l'état "invited" distingue clairement une simple demande de correction
     * d'un refus définitif du dossier).
     */
    public function requestCorrection(Request $request, DeliveryDriver $driver)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [
            'rejection_reason.required' => 'Indiquez ce que le livreur doit corriger.',
        ]);

        $driver->update([
            'onboarding_status' => DeliveryDriver::ONBOARDING_INVITED,
            'is_active' => false,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user('admin')?->id,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return redirect()->route('logistics.drivers')->with('success', 'Correction demandée. Le livreur pourra mettre à jour son dossier depuis l’application mobile.');
    }

    public function returns(Request $request)
    {
        $all = ReturnModel::with(['client', 'order.client', 'product', 'orderItem.product'])->latest('id')->get()
            ->reject(fn (ReturnModel $return) => $this->isDemonstrationReturn($return))
            ->values();
        $filtered = $all->filter(fn ($r) => (! $request->q || str_contains(mb_strtolower($r->order_reference.' '.$r->product_name.' '.$r->client?->name.' '.Data::ref($r, 'RET')), mb_strtolower($request->q))) && (! $request->status || Data::returnStatus($r)[2] === $request->status) && (! $request->reason || $r->reason === $request->reason) && (! $request->date || $r->request_date?->format('Y-m-d') === $request->date));
        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($filtered) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['Retour', 'Commande', 'Produit', 'Motif', 'Statut']);
                foreach ($filtered as $r) {
                    fputcsv($f, [Data::ref($r, 'RET'), $r->order_reference, $r->product_name, $r->reason, Data::returnStatus($r)[0]]);
                }fclose($f);
            }, 'retours.csv');
        }
        $per = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        return view('logistics.returns', ['returns' => new LengthAwarePaginator($filtered->forPage($page, $per)->values(), $filtered->count(), $per, $page, ['path' => $request->url(), 'query' => $request->query()]), 'allReturns' => $all]);
    }

    public function planReturn(Request $request, ReturnModel $return)
    {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Action ignorée : ce dossier de démonstration n’est plus utilisé.');
        }
        abort_unless($return->return_type === 'return' && ! in_array($return->status, ['refunded', 'rejected', 'closed']), 422, 'Ce retour ne peut pas être planifié.');
        $data = $request->validate(['driver_id' => 'required|exists:delivery_drivers,id', 'date' => 'required|date|after_or_equal:today', 'slot' => 'required|string|max:60', 'destination' => 'required|string|max:255', 'address' => 'required|string|max:255', 'note' => 'nullable|string|max:500', 'urgent' => 'nullable|boolean']);
        $driver = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->findOrFail($data['driver_id']);
        DB::transaction(function () use ($return, $data, $driver) {
            $locked = ReturnModel::lockForUpdate()->findOrFail($return->id);
            $meta = is_array($locked->meta) ? $locked->meta : [];
            $meta['collection'] = $data;

            if (data_get($meta, 'vendor_decision.type') === 'accept' && data_get($meta, 'vendor_decision.published_at')) {
                data_set($meta, 'vendor_decision.delivery_details', [
                    'address' => $data['address'],
                    'date' => $data['date'],
                    'slot' => $data['slot'],
                    'driver_name' => $driver->name,
                    'driver_phone' => $driver->phone,
                    'vehicle' => $driver->vehicle,
                    'destination' => $data['destination'],
                ]);
            }

            $locked->update(['meta' => $meta, 'status' => 'accepted', 'accepted_at' => $locked->accepted_at ?? now(), 'logistics_status' => 'return_pickup_planned']);
        });

        return back()->with('success', 'Collecte planifiée et livreur affecté.');
    }

    public function noteReturn(Request $request, ReturnModel $return)
    {
        if ($this->isDemonstrationReturn($return)) {
            return redirect()->route('logistics.returns')->with('info', 'Action ignorée : ce dossier de démonstration n’est plus utilisé.');
        }
        $request->validate(['note' => 'required|string|max:1000']);
        $meta = $return->meta ?? [];
        $meta['notes'][] = ['text' => $request->note, 'author' => $request->user('admin')?->name ?? 'Responsable Logistique', 'at' => now()->toIso8601String()];
        $return->update(['meta' => $meta]);

        return back()->with('success','Note ajoutée.');
    }

    private function isDemonstrationReturn(ReturnModel $return): bool
    {
        return data_get($return->meta, 'demo_seeder') === 'logistics-returns'
            || str_starts_with((string) $return->order_reference, 'CMD-DEMO-RETURN-');
    }
}
