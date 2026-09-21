<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\Carrier;
use App\Models\CarrierVehicle;
use App\Models\DeliveryService;
use App\Models\DeliveryServiceZone;
use App\Models\LogisticsPartner;
use App\Models\LogisticsTerritoryZone;
use App\Models\Shipment;
use App\Services\LogisticsPartnerService;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogisticsPartnerController extends Controller
{
    public function __construct(private readonly LogisticsPartnerService $partners)
    {
    }

    public function index(Request $request): View
    {
        $query = $this->externalCarriersQuery()
            ->with([
                'partnerProfile',
                'vehicles',
                'rateCards',
                'deliveryServices.zones',
            ])
            ->orderBy('name');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhereHas('vehicles', fn (Builder $vehicle) => $vehicle
                        ->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%"))
                    ->orWhereHas('partnerProfile', fn (Builder $profile) => $profile
                        ->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%"));
            });
        }

        $type = (string) $request->input('type', 'all');
        if ($type === 'specialized') {
            $query->whereHas('partnerProfile', fn (Builder $profile) => $profile->where('partner_type', 'specialized'));
        } elseif ($type === 'partner') {
            $query->where(function (Builder $builder) {
                $builder->whereDoesntHave('partnerProfile')
                    ->orWhereHas('partnerProfile', fn (Builder $profile) => $profile->where('partner_type', '!=', 'specialized'));
            });
        }

        $status = (string) $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true)->whereNotIn('status', ['suspended', 'inactive', 'draft']);
        } elseif ($status === 'suspended') {
            $query->where(function (Builder $builder) {
                $builder->where('is_active', false)->orWhereIn('status', ['suspended', 'inactive']);
            });
        } elseif ($status === 'draft') {
            $query->where('status', 'draft');
        }

        $coverage = trim((string) $request->input('coverage', ''));
        if ($coverage !== '') {
            $query->where(function (Builder $builder) use ($coverage) {
                $builder->whereHas('deliveryServices.zones', function (Builder $zone) use ($coverage) {
                    $zone->where('commune', $coverage)
                        ->orWhere('city', $coverage)
                        ->orWhere('delivery_zone', $coverage);
                })->orWhereHas('rateCards', fn (Builder $rate) => $rate->where('zone', $coverage))
                    ->orWhereHas('partnerProfile', fn (Builder $profile) => $profile->whereJsonContains('coverage', $coverage));
            });
        }

        $carriers = $query->paginate(15)->withQueryString();
        $rows = collect($carriers->items())
            ->mapWithKeys(fn (Carrier $carrier) => [$carrier->id => $this->partners->carrierRow($carrier)]);

        $allCarriers = $this->externalCarriersQuery()
            ->with(['partnerProfile', 'vehicles', 'rateCards', 'deliveryServices.zones'])
            ->orderBy('name')
            ->get();

        $allRows = $allCarriers->map(fn (Carrier $carrier) => $this->partners->carrierRow($carrier));
        $activeCount = $allRows->where('is_active', true)->count();
        $specializedCount = $allRows->where('type', 'specialized')->count();
        $activeVehicles = $allRows->sum('vehicle_count');
        $missionCount = $allRows->sum('mission_count');

        $watchPartners = $allRows
            ->filter(fn (array $row) => $row['delays_count'] > 0 || ($row['sla_percent'] !== null && $row['sla_percent'] < 90))
            ->sortBy(fn (array $row) => $row['sla_percent'] ?? 999)
            ->take(4)
            ->values();

        $coverageOptions = $this->partners->coverageOptions();
        $vehicleDistribution = $this->partners->globalVehicleDistribution();
        $communes = AbidjanCommune::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $territoryZones = LogisticsTerritoryZone::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']);

        return view('logistics.partners.index', [
            'partners' => $carriers,
            'rows' => $rows,
            'summary' => [
                'total' => $allRows->count(),
                'active' => $activeCount,
                'specialized' => $specializedCount,
                'vehicles' => $activeVehicles,
                'missions' => $missionCount,
            ],
            'vehicleDistribution' => $vehicleDistribution,
            'watchPartners' => $watchPartners,
            'coverageOptions' => $coverageOptions,
            'communes' => $communes,
            'territoryZones' => $territoryZones,
        ]);
    }

    public function show(string $partner): View
    {
        $carrier = $this->resolveCarrier($partner);
        $carrier->load(['partnerProfile', 'vehicles', 'rateCards', 'deliveryServices.zones']);

        $row = $this->partners->carrierRow($carrier);
        $fleet = $this->partners->fleetDistribution($carrier);
        $missions = $this->partners->missionHistory($carrier);
        $availableMissions = $this->partners->availableMissions($carrier);

        return view('logistics.partners.show', compact('carrier', 'row', 'fleet', 'missions', 'availableMissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('carriers', 'name')],
            'partner_type' => ['required', Rule::in(['partner', 'specialized'])],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'main_zone' => ['required', 'string', 'max:160'],
            'coverage' => ['required', 'array', 'min:1'],
            'coverage.*' => ['required', 'string', 'max:160'],
            'vehicle_types' => ['nullable', 'array'],
            'vehicle_types.*' => ['string', 'max:80'],
            'average_delay_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'integrated_at' => ['nullable', 'date'],
            'contract_reference' => ['nullable', 'string', 'max:100'],
            'sla_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority_level' => ['required', Rule::in(['normal', 'high', 'critical'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'contract_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $saveMode = (string) $request->input('save_mode', 'active');
        $isActive = $saveMode !== 'draft' && $request->boolean('is_active', true);

        $carrier = DB::transaction(function () use ($request, $data, $isActive, $saveMode) {
            $carrier = Carrier::create([
                'name' => $data['name'],
                'code' => $this->uniqueCarrierCode($data['name']),
                'phone' => $data['phone'],
                'city' => $data['main_zone'],
                'status' => $saveMode === 'draft' ? 'draft' : ($isActive ? 'active' : 'suspended'),
                'rating' => 0,
                'average_delay_hours' => $data['average_delay_hours'],
                'is_active' => $isActive,
            ]);

            $logoPath = $request->file('logo')?->store('logistics/partners/logos', 'public');
            $contractPath = $request->file('contract_file')?->store('logistics/partners/contracts', 'local');

            $profileData = [
                // Sans la migration carrier_id, on relie le profil au transporteur
                // par le même code métier. Cela évite toute erreur SQL et garde
                // le module utilisable immédiatement.
                'code' => Schema::hasColumn('logistics_partners', 'carrier_id')
                    ? 'LP-' . str_pad((string) $carrier->id, 5, '0', STR_PAD_LEFT)
                    : $carrier->code,
                'name' => $carrier->name,
                'partner_type' => $data['partner_type'],
                'contact_name' => $data['contact_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'main_zone' => $data['main_zone'],
                'coverage' => array_values(array_unique($data['coverage'])),
                'vehicle_count' => 0,
                'mission_count' => 0,
                'sla_percent' => (float) ($data['sla_percent'] ?? 0),
                'acceptance_percent' => 0,
                'successful_deliveries' => 0,
                'delays_count' => 0,
                'status' => $carrier->status,
                'contract_reference' => $data['contract_reference'] ?? null,
                'integrated_at' => $data['integrated_at'] ?? null,
                'priority_level' => $data['priority_level'],
                'can_receive_missions' => $request->boolean('can_receive_missions'),
                'auto_assignment_visible' => $request->boolean('auto_assignment_visible'),
                'supports_special_loads' => $request->boolean('supports_special_loads') || $data['partner_type'] === 'specialized',
                'intercommunal_delivery' => $request->boolean('intercommunal_delivery'),
                'description' => $data['description'] ?? null,
                'observations' => $data['observations'] ?? null,
                'vehicle_types' => $data['vehicle_types'] ?? [],
                'fleet_distribution' => [],
                'vehicles' => [],
                'mission_history' => [],
                'internal_notes' => [],
                'available_missions' => [],
                'meta' => ['source' => 'logistics_partners_module'],
            ];

            if (Schema::hasColumn('logistics_partners', 'carrier_id')) {
                $profileData['carrier_id'] = $carrier->id;
            }
            if (Schema::hasColumn('logistics_partners', 'logo_path')) {
                $profileData['logo_path'] = $logoPath;
            }
            if (Schema::hasColumn('logistics_partners', 'contract_path')) {
                $profileData['contract_path'] = $contractPath;
            }

            LogisticsPartner::create($profileData);

            $service = DeliveryService::create([
                'carrier_id' => $carrier->id,
                'provider_type' => DeliveryService::PROVIDER_PARTNER,
                'code' => 'partner-' . Str::slug($carrier->code ?: $carrier->name),
                'name' => 'Livraison ' . $carrier->name,
                'description' => $data['description'] ?? 'Service de livraison partenaire.',
                'estimated_hours' => $data['average_delay_hours'],
                'min_estimated_hours' => null,
                'max_estimated_hours' => $data['average_delay_hours'],
                'available_days' => [1, 2, 3, 4, 5, 6],
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'sort_order' => 100,
                'is_active' => $isActive,
                'meta' => [
                    'quote_required' => true,
                    'managed_by' => 'logistics_partners',
                    'vehicle_types' => $data['vehicle_types'] ?? [],
                ],
            ]);

            foreach (array_values(array_unique($data['coverage'])) as $communeName) {
                DeliveryServiceZone::create([
                    'delivery_service_id' => $service->id,
                    'country' => "Côte d'Ivoire",
                    'region' => "District autonome d'Abidjan",
                    'delivery_zone' => 'abidjan',
                    'city' => 'Abidjan',
                    'commune' => $communeName,
                    'district' => null,
                    'is_active' => $isActive,
                ]);
            }

            return $carrier;
        });

        return redirect()
            ->route('logistics.partners.show', $carrier->code ?: $carrier->id)
            ->with('success', $saveMode === 'draft' ? 'Partenaire enregistré en brouillon.' : 'Partenaire créé avec succès.');
    }

    public function storeVehicle(Request $request, string $partner): RedirectResponse
    {
        $carrier = $this->resolveCarrier($partner);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'plate_number' => ['nullable', 'string', 'max:50', Rule::unique('carrier_vehicles', 'plate_number')],
            'capacity_kg' => ['required', 'numeric', 'min:1', 'max:100000'],
            'volume_m3' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        CarrierVehicle::create([
            'carrier_id' => $carrier->id,
            'type' => $data['type'],
            'plate_number' => $data['plate_number'] ?: null,
            'capacity_ton' => round(((float) $data['capacity_kg']) / 1000, 3),
            'volume_m3' => (float) ($data['volume_m3'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Véhicule partenaire enregistré.');
    }

    public function assignMissions(Request $request, string $partner): RedirectResponse
    {
        $carrier = $this->resolveCarrier($partner);

        abort_unless($this->partners->isActive($carrier), 422, 'Ce partenaire n’est pas actif.');

        $data = $request->validate([
            'missions' => ['required', 'array', 'min:1'],
            'missions.*' => ['integer', 'exists:shipments,id'],
        ]);

        $coverage = $this->partners->coverage($carrier);
        $assigned = 0;

        DB::transaction(function () use ($data, $carrier, $coverage, &$assigned) {
            $shipments = LogisticsOperationalDataScope::shipments(Shipment::query())
                ->with('order')
                ->whereIn('id', $data['missions'])
                ->whereNull('carrier_id')
                ->whereIn('status', ['pending', 'ready_for_pickup'])
                ->lockForUpdate()
                ->get();

            foreach ($shipments as $shipment) {
                $location = (string) ($shipment->order?->delivery_commune
                    ?: $shipment->order?->delivery_city
                    ?: $shipment->delivery_address
                    ?: '');

                if (! $this->partners->coverageMatches($coverage, $location)) {
                    continue;
                }

                $service = $carrier->deliveryServices()->where('is_active', true)->orderBy('sort_order')->first();
                $meta = (array) ($shipment->meta ?? []);
                $meta['partner_assignment'] = [
                    'carrier_id' => $carrier->id,
                    'carrier_name' => $carrier->name,
                    'assigned_at' => now()->toIso8601String(),
                    'source' => 'logistics_partners',
                ];

                $shipment->forceFill([
                    'carrier_id' => $carrier->id,
                    'delivery_service_id' => $service?->id ?: $shipment->delivery_service_id,
                    'provider_type' => 'partner',
                    'internal_carrier_type' => 'partner',
                    'internal_carrier_name' => $carrier->name,
                    'meta' => $meta,
                ])->save();
                $assigned++;
            }
        });

        if ($assigned === 0) {
            return back()->with('error', 'Aucune mission compatible n’a pu être attribuée. Vérifiez la couverture ou l’état des missions.');
        }

        return back()->with('success', $assigned . ' mission(s) réelle(s) attribuée(s) à ' . $carrier->name . '.');
    }

    public function toggleStatus(string $partner): RedirectResponse
    {
        $carrier = $this->resolveCarrier($partner);
        $activate = ! $this->partners->isActive($carrier);

        DB::transaction(function () use ($carrier, $activate) {
            $carrier->forceFill([
                'is_active' => $activate,
                'status' => $activate ? 'active' : 'suspended',
            ])->save();

            $carrier->deliveryServices()->update(['is_active' => $activate]);
            $carrier->partnerProfile?->forceFill(['status' => $activate ? 'active' : 'suspended'])->save();
        });

        return back()->with('success', $activate ? 'Partenaire réactivé.' : 'Partenaire suspendu.');
    }

    public function addNote(Request $request, string $partner): RedirectResponse
    {
        $carrier = $this->resolveCarrier($partner);
        $profile = $carrier->partnerProfile;

        if (! $profile) {
            return back()->with('error', 'Le profil opérationnel de ce partenaire n’est pas encore configuré.');
        }

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $notes = collect($profile->internal_notes ?? []);
        $user = auth('admin')->user() ?: auth()->user();
        $notes->prepend([
            'author' => $user?->name ?: 'Équipe logistique',
            'date' => now()->format('d/m/Y H:i'),
            'text' => $data['note'],
        ]);
        $profile->forceFill(['internal_notes' => $notes->take(50)->values()->all()])->save();

        return back()->with('success', 'Note interne ajoutée.');
    }

    public function downloadContract(string $partner)
    {
        $carrier = $this->resolveCarrier($partner);
        $path = $carrier->partnerProfile?->contract_path;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $name = 'contrat-' . Str::slug($carrier->name) . '.pdf';
        return Storage::disk('local')->download($path, $name);
    }

    public function export(): StreamedResponse
    {
        $carriers = $this->externalCarriersQuery()
            ->with(['partnerProfile', 'vehicles', 'rateCards', 'deliveryServices.zones'])
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($carriers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Partenaire', 'Type', 'Téléphone', 'Ville', 'Couverture', 'Véhicules actifs', 'Missions', 'SLA 30 jours', 'Statut'], ';');

            foreach ($carriers as $carrier) {
                $row = $this->partners->carrierRow($carrier);
                fputcsv($out, [
                    $row['code'],
                    $carrier->name,
                    $row['type_label'],
                    $carrier->phone,
                    $carrier->city,
                    $row['coverage']->implode(', '),
                    $row['vehicle_count'],
                    $row['mission_count'],
                    $row['sla_percent'] === null ? '' : $row['sla_percent'] . '%',
                    $row['status_label'],
                ], ';');
            }
            fclose($out);
        }, 'ovanie-partenaires-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function externalCarriersQuery(): Builder
    {
        return Carrier::query()
            ->where(function (Builder $query) {
                $query->whereNull('code')->orWhere('code', '!=', 'ovanie-logistics');
            })
            ->whereDoesntHave('deliveryServices', fn (Builder $service) => $service->where('provider_type', DeliveryService::PROVIDER_OVANIE));
    }

    private function resolveCarrier(string $value): Carrier
    {
        $carrier = $this->externalCarriersQuery()
            ->where(function (Builder $query) use ($value) {
                $query->where('code', $value);
                if (ctype_digit($value)) {
                    $query->orWhere('id', (int) $value);
                }
            })
            ->first();

        if ($carrier) {
            return $carrier;
        }

        if (Schema::hasColumn('logistics_partners', 'carrier_id')) {
            $profile = LogisticsPartner::query()
                ->where('code', $value)
                ->whereNotNull('carrier_id')
                ->first();

            if ($profile) {
                return $this->externalCarriersQuery()->findOrFail($profile->carrier_id);
            }
        } else {
            // Ancien schéma : le profil et le transporteur partagent le même code.
            $profile = LogisticsPartner::query()->where('code', $value)->first();
            if ($profile) {
                $legacyCarrier = $this->externalCarriersQuery()->where('code', $profile->code)->first();
                if ($legacyCarrier) {
                    return $legacyCarrier;
                }
            }
        }

        abort(404);
    }

    private function uniqueCarrierCode(string $name): string
    {
        $root = 'PTR-' . Str::upper(Str::substr(Str::slug($name, ''), 0, 8));
        if ($root === 'PTR-') {
            $root = 'PTR-PARTNER';
        }

        $code = $root;
        $suffix = 2;
        while (Carrier::where('code', $code)->exists()) {
            $code = $root . '-' . $suffix++;
        }

        return $code;
    }
}
