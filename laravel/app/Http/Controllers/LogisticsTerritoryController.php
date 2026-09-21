<?php

namespace App\Http\Controllers;

use App\Models\AbidjanCommune;
use App\Models\LogisticsTerritoryZone;
use App\Models\User;
use App\Services\LogisticsTerritoryMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LogisticsTerritoryController extends Controller
{
    public function __construct(private readonly LogisticsTerritoryMetricsService $metrics)
    {
    }

    public function index(Request $request)
    {
        $query = $this->realZonesQuery()->with('communes:id,code,name,slug,latitude,longitude')->orderBy('code');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('region', 'like', $term)
                    ->orWhere('covered_communes', 'like', $term);

                if (Schema::hasTable('logistics_territory_zone_communes')) {
                    $builder->orWhereHas('communes', fn (Builder $commune) => $commune->where('name', 'like', $term));
                }
            });
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }
        if ($request->filled('delay')) {
            $query->where('average_delay_hours', (int) $request->input('delay'));
        }
        if ($request->filled('region')) {
            $query->where('region', $request->string('region')->toString());
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 12)));
        $zones = $query->paginate($perPage)->withQueryString();
        $all = $this->realZonesQuery()
            ->with('communes:id,code,name,slug,latitude,longitude')
            ->orderBy('code')
            ->get();

        $metricsByZone = $this->metrics->forZones($all);
        $featured = $request->filled('selected')
            ? $all->firstWhere('code', $request->string('selected')->toString())
            : $zones->first();
        $featuredMetrics = $featured ? ($metricsByZone[$featured->id] ?? $this->metrics->forZone($featured)) : [];

        $active = $all->where('is_active', true);
        $activity = $active->sum(fn (LogisticsTerritoryZone $zone) => (int) data_get($metricsByZone, $zone->id.'.activity_7d', 0));
        $previousActivity = $active->sum(fn (LogisticsTerritoryZone $zone) => (int) data_get($metricsByZone, $zone->id.'.activity_previous_7d', 0));
        $activityGrowth = $previousActivity > 0
            ? (int) round((($activity - $previousActivity) / $previousActivity) * 100)
            : ($activity > 0 ? 100 : 0);

        $communeIds = $active->flatMap(fn (LogisticsTerritoryZone $zone) => $zone->communes->pluck('id'))->unique();
        $stats = [
            'active' => $active->count(),
            'active_delta' => '+'.$active->filter(fn ($zone) => $zone->created_at?->isCurrentMonth())->count().' ce mois',
            'communes' => $communeIds->count(),
            'communes_delta' => $this->newCommuneLinksThisMonth().' ce mois',
            'avg_delay' => (int) round($active->avg('average_delay_hours') ?: 0),
            'avg_delay_delta' => 'configuration actuelle',
            'activity' => $activity,
            'activity_delta' => ($activityGrowth > 0 ? '+' : '').$activityGrowth.' %',
        ];

        $regions = $all->pluck('region')->filter()->unique()->sort()->values();
        if ($regions->isEmpty()) {
            $regions = collect(['District autonome d’Abidjan']);
        }
        $delays = $all->pluck('average_delay_hours')->filter()->unique()->sort()->values();
        if ($delays->isEmpty()) {
            $delays = collect([12, 24, 48, 72]);
        }
        $communes = $this->availableCommunes();
        $responsibles = $this->responsibles();
        $mapZones = $this->mapZones($all, $metricsByZone);

        return view('logistics.zones', compact(
            'zones', 'stats', 'featured', 'featuredMetrics', 'regions', 'delays',
            'mapZones', 'metricsByZone', 'communes', 'responsibles'
        ));
    }

    public function show(LogisticsTerritoryZone $zone)
    {
        $this->abortIfDemo($zone);
        $zone->load('communes:id,code,name,slug,latitude,longitude');

        $all = $this->realZonesQuery()
            ->with('communes:id,code,name,slug,latitude,longitude')
            ->orderBy('code')
            ->get();
        $metricsByZone = $this->metrics->forZones($all);
        $zoneMetrics = $metricsByZone[$zone->id] ?? $this->metrics->forZone($zone);
        $communeBreakdown = $this->metrics->communeBreakdown($zone);
        $mapZones = $this->mapZones($all, $metricsByZone);
        $selectedMapZone = collect($mapZones)->firstWhere('code', $zone->code) ?? $this->mapZone($zone, $zoneMetrics);
        $communes = $this->availableCommunes();
        $responsibles = $this->responsibles();
        $regions = $all->pluck('region')->filter()->unique()->sort()->values();
        if (! $regions->contains($zone->region)) {
            $regions->push($zone->region);
            $regions = $regions->filter()->unique()->sort()->values();
        }

        return view('logistics.zones-show', compact(
            'zone', 'mapZones', 'selectedMapZone', 'zoneMetrics', 'communeBreakdown', 'communes', 'responsibles', 'regions'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $communes = $this->selectedCommunes($data['commune_ids']);
        $code = $this->nextCode();

        $zone = DB::transaction(function () use ($data, $communes, $code) {
            $zone = LogisticsTerritoryZone::create($this->payload($data, $communes) + [
                'code' => $code,
                'source' => 'manual',
                'activity_7d' => 0,
                'activity_growth_percent' => 0,
                'current_load_percent' => 0,
                'drivers_available' => 0,
                'drivers_total' => 0,
                'sla_percent' => 0,
                'drivers_snapshot' => null,
                'activity_snapshot' => null,
                'map_variant' => 'custom',
                'meta' => ['source' => 'territory_module'],
            ]);
            $zone->communes()->sync($communes->pluck('id')->all());

            return $zone;
        });

        return redirect()->route('logistics.zones.show', $zone)->with('success', 'Zone de livraison enregistrée.');
    }

    public function update(Request $request, LogisticsTerritoryZone $zone)
    {
        $this->abortIfDemo($zone);
        $data = $this->validated($request);
        $communes = $this->selectedCommunes($data['commune_ids']);

        DB::transaction(function () use ($zone, $data, $communes) {
            $zone->update($this->payload($data, $communes));
            $zone->communes()->sync($communes->pluck('id')->all());
        });

        return back()->with('success', 'Zone de livraison mise à jour.');
    }

    public function toggle(LogisticsTerritoryZone $zone)
    {
        $this->abortIfDemo($zone);
        $zone->update(['is_active' => ! $zone->is_active]);
        return back()->with('success', 'Statut de la zone mis à jour.');
    }

    public function destroy(LogisticsTerritoryZone $zone)
    {
        $this->abortIfDemo($zone);
        $zone->delete();
        return redirect()->route('logistics.zones')->with('success', 'Zone supprimée.');
    }

    public function export()
    {
        $rows = $this->realZonesQuery()->with('communes:id,name')->orderBy('code')->get();
        $csv = "Code;Nom;Région;Communes;Statut;Délai moyen\n";
        foreach ($rows as $zone) {
            $csv .= implode(';', [
                $zone->code,
                str_replace(';', ',', $zone->name),
                str_replace(';', ',', $zone->region),
                str_replace(';', ',', $zone->communes->pluck('name')->implode(', ')),
                $zone->is_active ? 'Active' : 'Inactive',
                $zone->average_delay_hours.'h',
            ])."\n";
        }

        return Response::make("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="zones-livraison-ovanie.csv"',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'average_delay_hours' => ['required', 'integer', Rule::in([12, 24, 48, 72])],
            'responsible_name' => ['nullable', 'string', 'max:120'],
            'coverage_start_time' => ['nullable', 'date_format:H:i'],
            'coverage_end_time' => ['nullable', 'date_format:H:i'],
            'operational_note' => ['nullable', 'string', 'max:500'],
            'commune_ids' => ['required', 'array', 'min:1', 'max:30'],
            'commune_ids.*' => ['integer', 'distinct', Rule::exists('abidjan_communes', 'id')->where('is_active', true)],
            'allow_express' => ['nullable', 'boolean'],
            'prioritize_missions' => ['nullable', 'boolean'],
            'auto_apply_new_missions' => ['nullable', 'boolean'],
            'show_in_filters' => ['nullable', 'boolean'],
            'coverage_geojson' => ['nullable', 'json', 'max:50000'],
        ]);
    }

    private function payload(array $data, Collection $communes): array
    {
        $geometry = $this->decodeGeometry($data['coverage_geojson'] ?? null);
        $center = $this->geometryCenter($geometry);

        return [
            'name' => trim($data['name']),
            'region' => trim($data['region']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'average_delay_hours' => (int) $data['average_delay_hours'],
            'responsible_name' => filled($data['responsible_name'] ?? null) ? trim($data['responsible_name']) : null,
            'coverage_start_time' => $data['coverage_start_time'] ?? '06:00',
            'coverage_end_time' => $data['coverage_end_time'] ?? '22:00',
            'operational_note' => filled($data['operational_note'] ?? null) ? trim($data['operational_note']) : null,
            'covered_communes' => $communes->pluck('name')->values()->all(),
            'communes_count' => $communes->count(),
            'allow_express' => (bool) ($data['allow_express'] ?? false),
            'prioritize_missions' => (bool) ($data['prioritize_missions'] ?? false),
            'auto_apply_new_missions' => (bool) ($data['auto_apply_new_missions'] ?? false),
            'show_in_filters' => (bool) ($data['show_in_filters'] ?? false),
            'coverage_geojson' => $geometry,
            'center_latitude' => $center['lat'] ?? null,
            'center_longitude' => $center['lng'] ?? null,
            'source' => 'manual',
            'map_variant' => 'custom',
        ];
    }

    private function mapZones(Collection $zones, array $metricsByZone): array
    {
        return $zones->map(fn (LogisticsTerritoryZone $zone) => $this->mapZone(
            $zone,
            $metricsByZone[$zone->id] ?? []
        ))->values()->all();
    }

    private function mapZone(LogisticsTerritoryZone $zone, array $metrics): array
    {
        $geometry = is_array($zone->coverage_geojson) ? $zone->coverage_geojson : null;
        // Sans polygone enregistré, la carte se base sur les limites de la commune
        // sélectionnée et n'affiche aucun point central approximatif.
        $center = ['lat' => null, 'lng' => null];

        $breakdownByCommuneId = collect($this->metrics->communeBreakdown($zone))
            ->keyBy(fn (array $row) => $row['commune']->id);

        return [
            'code' => $zone->code,
            'name' => $zone->name,
            'region' => $zone->region,
            'is_active' => (bool) $zone->is_active,
            'activity_7d' => (int) ($metrics['activity_7d'] ?? 0),
            'average_delay_hours' => (int) $zone->average_delay_hours,
            'fill' => '#36c99a',
            'stroke' => '#078d6b',
            'center' => $center,
            'geometry' => $geometry,
            'communes' => $zone->communes->map(function (AbidjanCommune $commune) use ($breakdownByCommuneId) {
                $breakdown = $breakdownByCommuneId->get($commune->id);

                return [
                    'id' => $commune->id,
                    'code' => $commune->code,
                    'name' => $commune->name,
                    'lat' => null,
                    'lng' => null,
                    'stats' => [
                        'drivers_total' => (int) ($breakdown['drivers_total'] ?? 0),
                        'drivers_available' => (int) ($breakdown['drivers_available'] ?? 0),
                        'drivers_in_mission' => (int) ($breakdown['drivers_in_mission'] ?? 0),
                        'drivers_offline' => (int) ($breakdown['drivers_offline'] ?? 0),
                    ],
                ];
            })->values()->all(),
            'url' => route('logistics.zones.show', $zone),
        ];
    }

    private function realZonesQuery(): Builder
    {
        $query = LogisticsTerritoryZone::query();
        if (Schema::hasColumn('logistics_territory_zones', 'source')) {
            $query->where('source', '!=', 'demo');
        }

        return $query;
    }

    private function availableCommunes(): Collection
    {
        if (! Schema::hasTable('abidjan_communes')) {
            return collect();
        }

        return AbidjanCommune::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'slug', 'latitude', 'longitude']);
    }

    private function responsibles(): Collection
    {
        return User::query()
            ->whereIn('role', ['logistique', 'logistics', 'admin'])
            ->where(function (Builder $query) {
                $query->whereNull('email')->orWhere('email', 'not like', '%@demo.ovanie.invalid');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function selectedCommunes(array $ids): Collection
    {
        return AbidjanCommune::query()
            ->where('is_active', true)
            ->whereIn('id', array_map('intval', $ids))
            ->orderBy('name')
            ->get();
    }

    private function nextCode(): string
    {
        // Les anciens jeux de démonstration restent masqués mais leurs codes sont
        // encore uniques en base. On tient donc compte de TOUS les codes pour ne
        // jamais provoquer une collision lors de la création d'une vraie zone.
        $max = LogisticsTerritoryZone::query()
            ->pluck('code')
            ->map(fn ($code) => (int) preg_replace('/\D+/', '', (string) $code))
            ->max() ?? 0;

        return 'Z-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function decodeGeometry(?string $value): ?array
    {
        if (! filled($value)) {
            return null;
        }

        $geometry = json_decode((string) $value, true);
        if (($geometry['type'] ?? null) !== 'Polygon' || ! is_array($geometry['coordinates'][0] ?? null)) {
            return null;
        }

        $ring = array_values(array_filter($geometry['coordinates'][0], fn ($point) =>
            is_array($point)
            && count($point) >= 2
            && is_numeric($point[0])
            && is_numeric($point[1])
            && $this->isAbidjanPoint((float) $point[1], (float) $point[0])
        ));
        if (count($ring) < 3) {
            return null;
        }
        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }

    private function geometryCenter(?array $geometry): ?array
    {
        $ring = $geometry['coordinates'][0] ?? [];
        if (count($ring) < 3) {
            return null;
        }

        $lngs = array_map(fn ($point) => (float) $point[0], $ring);
        $lats = array_map(fn ($point) => (float) $point[1], $ring);

        return ['lat' => array_sum($lats) / count($lats), 'lng' => array_sum($lngs) / count($lngs)];
    }


    private function isAbidjanPoint(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return $lat >= 5.10 && $lat <= 5.65
            && $lng >= -4.45 && $lng <= -3.65;
    }

    private function newCommuneLinksThisMonth(): string
    {
        if (! Schema::hasTable('logistics_territory_zone_communes')) {
            return '+0';
        }

        $count = DB::table('logistics_territory_zone_communes')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return '+'.$count;
    }

    private function abortIfDemo(LogisticsTerritoryZone $zone): void
    {
        if (Schema::hasColumn('logistics_territory_zones', 'source') && $zone->source === 'demo') {
            abort(404);
        }
    }
}
