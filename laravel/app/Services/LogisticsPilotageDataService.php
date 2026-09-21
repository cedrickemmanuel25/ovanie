<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryIncident;
use App\Models\LogisticsPilotageNotification;
use App\Models\LogisticsPilotageSetting;
use App\Models\LogisticsTerritoryZone;
use App\Models\OrderItem;
use App\Models\ReturnModel;
use App\Models\Setting;
use App\Models\Shipment;
use App\Support\LogisticsOperationalDataScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class LogisticsPilotageDataService
{
    /** @var Collection<int, LogisticsPilotageSetting>|null */
    private ?Collection $settingsSnapshot = null;

    public function resolvePeriod(?string $fromInput, ?string $toInput): array
    {
        try {
            $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : now()->startOfMonth();
        } catch (Throwable) {
            $from = now()->startOfMonth();
        }

        try {
            $to = $toInput ? Carbon::parse($toInput)->endOfDay() : now()->endOfDay();
        } catch (Throwable) {
            $to = now()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }

    public function realZones(): Collection
    {
        if (! Schema::hasTable('logistics_territory_zones')) {
            return collect();
        }

        $query = LogisticsTerritoryZone::query()->where('is_active', true);

        // Les versions récentes du module Territoire marquent explicitement les
        // anciennes zones de démonstration. On reste compatible avec les bases
        // qui n'ont pas encore cette colonne.
        if (Schema::hasColumn('logistics_territory_zones', 'source')) {
            $query->where(function (Builder $builder) {
                $builder->whereNull('source')->orWhere('source', '!=', 'demo');
            });
        }

        if (Schema::hasTable('logistics_territory_zone_communes')
            && method_exists(LogisticsTerritoryZone::class, 'communes')) {
            $query->with('communes');
        }

        return $query
            ->orderBy('name')
            ->get()
            ->reject(fn (LogisticsTerritoryZone $zone) => $this->isDemoMeta($zone->meta))
            ->values();
    }

    public function report(Carbon $from, Carbon $to, ?string $territoryCode = null): array
    {
        $zone = $territoryCode ? $this->realZones()->firstWhere('code', $territoryCode) : null;
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $current = $this->reportPeriodData($from, $to, $zone);
        $previous = $this->reportPeriodData($previousFrom, $previousTo, $zone);

        $kpis = [
            $this->kpi('Livraisons effectuées', $current['deliveries']->count(), $previous['deliveries']->count(), 'truck', 'green'),
            $this->rateKpi('Taux de ponctualité', $current['punctuality_rate'], $previous['punctuality_rate'], 'clock', 'blue'),
            $this->moneyKpi('Frais de livraison facturés', $current['delivery_fees_total'], $previous['delivery_fees_total'], 'wallet', 'orange', true),
            $this->kpi('Incidents signalés', $current['incidents']->count(), $previous['incidents']->count(), 'warning', 'red', true),
            $this->kpi('Retours traités', $current['treated_returns']->count(), $previous['treated_returns']->count(), 'return', 'purple', true),
            $this->kpi('Livreurs mobilisés', $current['active_drivers'], $previous['active_drivers'], 'user', 'teal'),
        ];

        $deliveryEvolution = [];
        $punctualityEvolution = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $dayDeliveries = $current['deliveries']->filter(fn ($s) => optional($s->delivered_at)->toDateString() === $date);
            $dayIncidents = $current['incidents']->filter(fn ($i) => $this->incidentDate($i)?->toDateString() === $date);
            $dayPunctuality = $this->punctualityRate($dayDeliveries);
            $deliveryEvolution[] = [
                'date' => $cursor->copy(),
                'deliveries' => $dayDeliveries->count(),
                'incidents' => $dayIncidents->count(),
            ];
            $punctualityEvolution[] = [
                'date' => $cursor->copy(),
                'rate' => $dayPunctuality,
            ];
            $cursor->addDay();
        }

        $feeGroups = $current['fee_shipments']
            ->groupBy(fn ($s) => $this->providerLabel((string) ($s->provider_type ?: $s->internal_carrier_type ?: 'other')))
            ->map(fn (Collection $rows, string $label) => ['label' => $label, 'amount' => (float) $rows->sum('final_price')])
            ->filter(fn ($row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();
        $deliveryFeesTotal = (float) $feeGroups->sum('amount');
        $feeDistribution = $feeGroups->map(function ($row) use ($deliveryFeesTotal) {
            $row['percent'] = $deliveryFeesTotal > 0 ? round(($row['amount'] / $deliveryFeesTotal) * 100, 1) : 0;
            return $row;
        })->values();

        $driverPerformance = $this->driverPerformance($from, $to, $zone);
        $topZones = $this->topZones($current['deliveries'], $current['incidents']);

        $latestIncidents = $this->latestIncidents($from, $to, $zone);
        $latestReturns = $this->latestReturns($from, $to, $zone);

        $averageDuration = $this->averageDeliveryDurationMinutes($current['deliveries']);
        $averageDeliveryFee = $current['deliveries']->count() > 0
            ? $current['delivery_fees_total'] / max(1, $current['deliveries']->count())
            : 0;
        $returnRate = $current['deliveries']->count() > 0
            ? round(($current['returns_requested']->count() / $current['deliveries']->count()) * 100, 1)
            : 0;
        $averageDriverRating = $driverPerformance->filter(fn ($r) => is_numeric($r['rating']))->avg('rating');

        return [
            'from' => $from,
            'to' => $to,
            'territory' => $zone,
            'zones' => $this->realZones(),
            'kpis' => $kpis,
            'deliveryEvolution' => collect($deliveryEvolution),
            'punctualityEvolution' => collect($punctualityEvolution),
            'feeDistribution' => $feeDistribution,
            'deliveryFeesTotal' => $current['delivery_fees_total'],
            'driverPerformance' => $driverPerformance,
            'topZones' => $topZones,
            'latestIncidents' => $latestIncidents,
            'latestReturns' => $latestReturns,
            'keyIndicators' => collect([
                ['label' => 'Délai moyen de livraison', 'value' => $averageDuration !== null ? $this->formatMinutes($averageDuration) : '—'],
                ['label' => 'Note moyenne livreurs', 'value' => $averageDriverRating ? number_format((float) $averageDriverRating, 1, ',', ' ') . '/5' : '—'],
                ['label' => 'Frais moyens facturés par livraison', 'value' => $averageDeliveryFee > 0 ? number_format($averageDeliveryFee, 0, ',', ' ') . ' FCFA' : '0 FCFA'],
                ['label' => 'Taux de retours', 'value' => number_format($returnRate, 1, ',', ' ') . '%'],
            ]),
            'dataSourceLabel' => 'Données OVANIE',
        ];
    }

    protected function reportPeriodData(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): array
    {
        $deliveries = $this->realShipmentsQuery()
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$from, $to])
            ->with('order')
            ->get()
            ->filter(fn ($s) => $this->matchesZone($s->order, $s->delivery_address, $zone))
            ->values();

        $incidents = $this->realIncidentsBetween($from, $to, $zone);
        $returnsRequested = $this->realReturnsBetween($from, $to, $zone, false);
        $treatedReturns = $this->realReturnsBetween($from, $to, $zone, true);

        $activeDrivers = $this->realAssignmentsBetween($from, $to, $zone)
            ->pluck('driver_id')->filter()->unique()->count();

        return [
            'deliveries' => $deliveries,
            'fee_shipments' => $deliveries,
            'incidents' => $incidents,
            'returns_requested' => $returnsRequested,
            'treated_returns' => $treatedReturns,
            'punctuality_rate' => $this->punctualityRate($deliveries),
            'delivery_fees_total' => (float) $deliveries->sum('final_price'),
            'active_drivers' => $activeDrivers,
        ];
    }

    protected function realShipmentsQuery(): Builder
    {
        return LogisticsOperationalDataScope::shipments(Shipment::query());
    }

    protected function realAssignmentsBetween(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): Collection
    {
        if (! Schema::hasTable('delivery_assignments')) {
            return collect();
        }

        $query = LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
            ->with(['driver', 'order']);
        if (Schema::hasColumn('delivery_assignments', 'delivered_at')) {
            $query->whereBetween('delivered_at', [$from, $to]);
        } else {
            $query->whereBetween('updated_at', [$from, $to]);
        }
        return $query->get()
            ->filter(fn ($a) => $this->matchesZone($a->order, $a->delivery_address, $zone))
            ->values();
    }

    protected function realIncidentsBetween(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): Collection
    {
        if (! Schema::hasTable('delivery_incidents')) {
            return collect();
        }

        $query = DeliveryIncident::query()->with(['order', 'shipment']);
        if (method_exists(DeliveryIncident::class, 'scopeOperationalReal')) {
            $query->operationalReal();
        }

        if (Schema::hasColumn('delivery_incidents', 'occurred_at')) {
            $query->where(function (Builder $q) use ($from, $to) {
                $q->whereBetween('occurred_at', [$from, $to])
                    ->orWhere(function (Builder $x) use ($from, $to) {
                        $x->whereNull('occurred_at')->whereBetween('created_at', [$from, $to]);
                    });
            });
        } else {
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query->get()
            ->filter(fn ($i) => $this->matchesZone($i->order, $i->shipment?->delivery_address, $zone))
            ->values();
    }

    protected function realReturnsBetween(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone, bool $treated): Collection
    {
        if (! Schema::hasTable('returns')) {
            return collect();
        }

        $query = ReturnModel::query()->with('order');
        if (Schema::hasColumn('returns', 'order_reference')) {
            $query->where(fn (Builder $q) => $q->whereNull('order_reference')->orWhere('order_reference', 'not like', '%DEMO%'));
        }
        if (Schema::hasTable('orders')) {
            $query->whereDoesntHave('order', fn (Builder $q) => $q->where('order_number', 'like', '%DEMO%'));
        }

        if ($treated) {
            $treatedStatuses = ['closed', 'refunded', 'resolved', 'rejected'];
            $query->whereIn('status', $treatedStatuses);
            if (Schema::hasColumn('returns', 'resolved_at')) {
                $query->where(function (Builder $q) use ($from, $to) {
                    $q->whereBetween('resolved_at', [$from, $to])
                        ->orWhere(function (Builder $x) use ($from, $to) {
                            $x->whereNull('resolved_at')->whereBetween('updated_at', [$from, $to]);
                        });
                });
            } else {
                $query->whereBetween('updated_at', [$from, $to]);
            }
        } else {
            if (Schema::hasColumn('returns', 'request_date')) {
                $query->whereBetween('request_date', [$from->toDateString(), $to->toDateString()]);
            } else {
                $query->whereBetween('created_at', [$from, $to]);
            }
        }

        return $query->get()
            ->filter(fn ($r) => $this->matchesZone($r->order, $r->order?->delivery_address, $zone))
            ->values();
    }

    protected function punctualityRate(Collection $deliveries): ?float
    {
        $comparable = $deliveries->filter(fn ($s) => $s->estimated_delivery_at && $s->delivered_at);
        if ($comparable->isEmpty()) {
            return null;
        }
        $onTime = $comparable->filter(fn ($s) => $s->delivered_at->lte($s->estimated_delivery_at))->count();
        return round(($onTime / $comparable->count()) * 100, 1);
    }

    protected function averageDeliveryDurationMinutes(Collection $deliveries): ?int
    {
        $durations = $deliveries->map(function ($shipment) {
            if (! $shipment->delivered_at) {
                return null;
            }
            $start = $shipment->routed_at ?: $shipment->created_at;
            if (! $start) {
                return null;
            }
            return max(0, $start->diffInMinutes($shipment->delivered_at));
        })->filter(fn ($value) => $value !== null);

        return $durations->isEmpty() ? null : (int) round($durations->avg());
    }

    protected function driverPerformance(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): Collection
    {
        $assignments = $this->realAssignmentsBetween($from, $to, $zone)
            ->filter(fn ($a) => $a->driver_id && $a->driver)
            ->groupBy('driver_id');

        $orderIncidentCounts = collect();
        if (Schema::hasTable('delivery_incidents')) {
            $orderIds = $assignments->flatten()->pluck('order_id')->filter()->unique()->values();
            if ($orderIds->isNotEmpty()) {
                $iq = DeliveryIncident::query()->whereIn('order_id', $orderIds);
                if (method_exists(DeliveryIncident::class, 'scopeOperationalReal')) {
                    $iq->operationalReal();
                }
                $orderIncidentCounts = $iq->get()->groupBy('order_id')->map->count();
            }
        }

        return $assignments->map(function (Collection $rows) use ($orderIncidentCounts) {
            $driver = $rows->first()->driver;
            $comparable = $rows->filter(fn ($a) => $a->delivered_at && $a->estimated_delivery_at);
            $punctuality = null;
            if ($comparable->isNotEmpty()) {
                $onTime = $comparable->filter(fn ($a) => $a->delivered_at->lte($a->estimated_delivery_at))->count();
                $punctuality = round(($onTime / $comparable->count()) * 100, 1);
            }
            $incidents = $rows->sum(fn ($a) => (int) ($orderIncidentCounts[$a->order_id] ?? 0));
            return [
                'driver_id' => $driver->id,
                'name' => $driver->name,
                'deliveries' => $rows->count(),
                'punctuality' => $punctuality,
                'incidents' => $incidents,
                'rating' => $driver->rating ?: null,
            ];
        })->sortByDesc('deliveries')->take(5)->values();
    }

    protected function topZones(Collection $deliveries, Collection $incidents): Collection
    {
        $incidentCounts = $incidents->groupBy(fn ($i) => $this->zoneLabel($i->order, $i->shipment?->delivery_address))->map->count();

        return $deliveries
            ->groupBy(fn ($s) => $this->zoneLabel($s->order, $s->delivery_address))
            ->map(function (Collection $rows, string $label) use ($incidentCounts) {
                return [
                    'zone' => $label,
                    'deliveries' => $rows->count(),
                    'punctuality' => $this->punctualityRate($rows),
                    'incidents' => (int) ($incidentCounts[$label] ?? 0),
                ];
            })
            ->sortByDesc('deliveries')
            ->take(5)
            ->values();
    }

    protected function latestIncidents(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): Collection
    {
        if (! Schema::hasTable('delivery_incidents')) {
            return collect();
        }
        $query = DeliveryIncident::query()->with(['order', 'shipment'])->latest('id');
        if (method_exists(DeliveryIncident::class, 'scopeOperationalReal')) {
            $query->operationalReal();
        }
        if (Schema::hasColumn('delivery_incidents', 'occurred_at')) {
            $query->whereBetween('occurred_at', [$from, $to]);
        } else {
            $query->whereBetween('created_at', [$from, $to]);
        }
        return $query->take(50)->get()
            ->filter(fn ($i) => $this->matchesZone($i->order, $i->shipment?->delivery_address, $zone))
            ->take(5)
            ->map(fn ($i) => [
                'date' => $this->incidentDate($i) ?: $i->created_at,
                'type' => $this->humanize($i->incident_type ?: 'Incident'),
                'description' => $i->description ?: 'Incident logistique',
                'zone' => $this->zoneLabel($i->order, $i->shipment?->delivery_address),
                'status' => $this->statusLabel($i->status),
            ])->values();
    }

    protected function latestReturns(Carbon $from, Carbon $to, ?LogisticsTerritoryZone $zone): Collection
    {
        if (! Schema::hasTable('returns')) {
            return collect();
        }
        $query = ReturnModel::query()->with('order')->latest('id');
        if (Schema::hasColumn('returns', 'order_reference')) {
            $query->where(fn (Builder $q) => $q->whereNull('order_reference')->orWhere('order_reference', 'not like', '%DEMO%'));
        }
        if (Schema::hasColumn('returns', 'request_date')) {
            $query->whereBetween('request_date', [$from, $to]);
        } else {
            $query->whereBetween('created_at', [$from, $to]);
        }
        return $query->take(50)->get()
            ->filter(fn ($r) => ! $r->order || ! str_contains((string) $r->order->order_number, 'DEMO'))
            ->filter(fn ($r) => $this->matchesZone($r->order, $r->order?->delivery_address, $zone))
            ->take(5)
            ->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->request_date ?: $r->created_at,
                'reason' => $r->reason ?: 'Retour produit',
                'zone' => $this->zoneLabel($r->order, $r->order?->delivery_address),
                'status' => $this->returnStatusLabel($r),
            ])->values();
    }

    public function syncRealNotifications(bool $force = false): void
    {
        if (! Schema::hasTable('logistics_pilotage_notifications')) {
            return;
        }

        $cacheKey = 'ovanie:logistics:pilotage:notifications:last-sync';
        if (! $force && Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, 45);

        // Une seule lecture/initialisation des paramètres par requête.
        // L'ancienne version appelait ensureSettings() puis settingValues(),
        // ce qui relançait toute l'initialisation et doublait les requêtes SQL.
        $settings = $this->ensureSettings()->pluck('value', 'setting_key')->all();

        if (($settings['automatic_notifications'] ?? '1') !== '1') {
            // La génération est suspendue sans marquer artificiellement les
            // incidents existants comme résolus. À la réactivation, la
            // synchronisation suivante remettra l'état à jour.
            return;
        }

        $gpsThreshold = max(1, (int) $this->numberFromSetting($settings['loss_alert'] ?? '10'));
        $delayThreshold = max(1, (int) ($settings['critical_delay'] ?? 30));
        $unassignedThreshold = max(1, (int) ($settings['unassigned_alert'] ?? 15));

        $activeIds = [];

        if (Schema::hasTable('delivery_assignments') && ($settings['realtime'] ?? '1') === '1') {
            $assignments = LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
                ->with(['driver', 'order'])
                ->whereIn('status', ['assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])
                ->get();

            foreach ($assignments as $assignment) {
                $reference = $assignment->mission_number ?: ('MIS-' . $assignment->id);
                $lastSeen = $assignment->gps_last_seen_at ?: $assignment->driver?->last_seen_at;
                $ageMinutes = $lastSeen ? $lastSeen->diffInMinutes(now()) : $assignment->updated_at?->diffInMinutes(now());
                if ($ageMinutes !== null && $ageMinutes >= $gpsThreshold && ($assignment->gps_status ?? '') !== 'disabled') {
                    $activeIds[] = $this->upsertNotification([
                        'type' => 'gps',
                        'title' => 'Signal GPS à vérifier',
                        'message' => $lastSeen
                            ? "Aucune position depuis {$ageMinutes} minute(s)."
                            : 'Aucune position GPS récente disponible.',
                        'reference' => $reference,
                        'location' => $assignment->order?->delivery_commune ?: $assignment->delivery_address,
                        'occurred_at' => $lastSeen ?: $assignment->updated_at ?: now(),
                        'priority' => $ageMinutes >= ($gpsThreshold * 2) ? 'critical' : 'high',
                        'source' => 'delivery_assignment',
                        'source_id' => $assignment->id,
                        'url' => $assignment->order_item_id ? route('logistics.tracking.mission', $assignment->order_item_id, false) : route('logistics.tracking', [], false),
                    ]);
                }
            }
        }

        if (Schema::hasTable('shipments') && ($settings['delay_alert'] ?? '1') === '1') {
            $lateShipments = $this->realShipmentsQuery()
                ->with('order')
                ->whereNotIn('status', ['delivered', 'completed', 'cancelled', 'returned'])
                ->whereNotNull('estimated_delivery_at')
                ->where('estimated_delivery_at', '<', now())
                ->get();
            foreach ($lateShipments as $shipment) {
                $lateMinutes = max(1, $shipment->estimated_delivery_at->diffInMinutes(now()));
                if ($lateMinutes < $delayThreshold) {
                    continue;
                }
                $activeIds[] = $this->upsertNotification([
                    'type' => 'delay',
                    'title' => 'Livraison en retard',
                    'message' => "Échéance dépassée de {$lateMinutes} minute(s).",
                    'reference' => $shipment->tracking_number ?: ('SHP-' . $shipment->id),
                    'location' => $shipment->order?->delivery_commune ?: $shipment->delivery_address,
                    'occurred_at' => $shipment->estimated_delivery_at,
                    'priority' => (($settings['late_30'] ?? '1') === '1' && $lateMinutes >= ($delayThreshold * 2)) ? 'critical' : 'high',
                    'source' => 'shipment',
                    'source_id' => $shipment->id,
                    'url' => $shipment->order_item_id ? route('logistics.shipments.details', $shipment->order_item_id, false) : route('logistics.shipments', [], false),
                ]);
            }
        }

        if (Schema::hasTable('delivery_incidents') && ($settings['incident_notification'] ?? '1') === '1') {
            $incidents = DeliveryIncident::query()->with(['order', 'shipment'])->whereNotIn('status', ['resolved', 'closed'])->latest('id');
            if (method_exists(DeliveryIncident::class, 'scopeOperationalReal')) {
                $incidents->operationalReal();
            }
            foreach ($incidents->get() as $incident) {
                $severity = strtolower((string) ($incident->severity ?: 'medium'));
                $priority = in_array($severity, ['critical', 'critique'], true)
                    ? (($settings['critical_incident_rule'] ?? '1') === '1' ? 'critical' : 'high')
                    : (in_array($severity, ['high', 'haute', 'important'], true) ? 'high' : 'medium');
                $activeIds[] = $this->upsertNotification([
                    'type' => 'incident',
                    'title' => $this->humanize($incident->incident_type ?: 'Incident déclaré'),
                    'message' => $incident->description ?: 'Un incident logistique nécessite une vérification.',
                    'reference' => 'INC-' . str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT),
                    'location' => $this->zoneLabel($incident->order, $incident->shipment?->delivery_address),
                    'occurred_at' => $this->incidentDate($incident) ?: $incident->created_at ?: now(),
                    'priority' => $priority,
                    'source' => 'delivery_incident',
                    'source_id' => $incident->id,
                    'url' => route('logistics.incidents.show', $incident->id, false),
                ]);
            }
        }

        if (Schema::hasTable('returns') && ($settings['return_notification'] ?? '1') === '1') {
            $returns = ReturnModel::query()->with('order')
                ->whereIn('status', ['pending', 'accepted'])
                ->latest('id')->get()
                ->filter(fn ($r) => ! str_contains((string) $r->order_reference, 'DEMO') && ! str_contains((string) ($r->order?->order_number), 'DEMO'));
            foreach ($returns as $return) {
                $accepted = $return->status === 'accepted';
                $activeIds[] = $this->upsertNotification([
                    'type' => 'return',
                    'title' => $accepted ? 'Retour à organiser' : 'Demande de retour en attente',
                    'message' => $return->reason ?: 'Un dossier de retour nécessite une action.',
                    'reference' => 'RET-' . str_pad((string) $return->id, 5, '0', STR_PAD_LEFT),
                    'location' => $this->zoneLabel($return->order, $return->order?->delivery_address),
                    'occurred_at' => $return->updated_at ?: $return->created_at ?: now(),
                    'priority' => ($accepted && ($settings['urgent_return'] ?? '1') === '1') ? 'high' : 'medium',
                    'source' => 'return',
                    'source_id' => $return->id,
                    'url' => route('logistics.returns.details', $return->id, false),
                ]);
            }
        }

        if (Schema::hasTable('order_items') && ($settings['unassigned_mission'] ?? '1') === '1') {
            $readyItems = OrderItem::query()->with('order')
                ->where('delivery_status', 'ready_for_pickup')
                ->whereHas('order', fn (Builder $q) => LogisticsOperationalDataScope::orders($q))
                ->whereDoesntHave('deliveryAssignments', fn (Builder $q) => LogisticsOperationalDataScope::assignments($q)
                    ->whereIn('status', ['planned', 'assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived']))
                ->get();
            foreach ($readyItems as $item) {
                $readyAt = $item->seller_ready_for_pickup_at ?: $item->updated_at;
                $age = $readyAt ? $readyAt->diffInMinutes(now()) : 0;
                if ($age < $unassignedThreshold) {
                    continue;
                }
                $activeIds[] = $this->upsertNotification([
                    'type' => 'mission',
                    'title' => 'Mission non affectée',
                    'message' => "Colis prêt depuis {$age} minute(s), sans livreur affecté.",
                    'reference' => ($item->order?->order_number ?: 'CMD') . '-' . $item->id,
                    'location' => $item->order?->delivery_commune ?: $item->order?->delivery_address,
                    'occurred_at' => $readyAt ?: now(),
                    'priority' => $age >= ($unassignedThreshold * 2) ? 'high' : 'medium',
                    'source' => 'order_item',
                    'source_id' => $item->id,
                    'url' => route('logistics.shipments.details', $item->id, false),
                ]);
            }
        }

        $stale = LogisticsPilotageNotification::query()->where('status', 'active_real');
        if ($activeIds === []) {
            $stale->update(['status' => 'resolved_real']);
        } else {
            $stale->whereNotIn('id', array_values(array_unique($activeIds)))->update(['status' => 'resolved_real']);
        }
    }

    protected function upsertNotification(array $data): int
    {
        $notification = LogisticsPilotageNotification::query()->firstOrNew([
            'type' => $data['type'],
            'reference' => $data['reference'],
        ]);

        $wasActive = $notification->exists && $notification->status === 'active_real';
        $notification->fill([
            'title' => $data['title'],
            'message' => $data['message'] ?? null,
            'location' => $data['location'] ?: null,
            'occurred_at' => $data['occurred_at'] ?: now(),
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'active_real',
            'meta' => [
                'source' => $data['source'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'url' => $data['url'] ?? null,
                'generated_from_ovanie' => true,
            ],
        ]);
        if (! $wasActive) {
            $notification->is_read = false;
        }
        $notification->save();

        return (int) $notification->id;
    }

    public function notificationCounts(): array
    {
        $this->syncRealNotifications();
        if (! Schema::hasTable('logistics_pilotage_notifications')) {
            return ['gps' => 0, 'delay' => 0, 'incident' => 0, 'return' => 0, 'mission' => 0, 'unread' => 0];
        }
        $base = LogisticsPilotageNotification::query()->where('status', 'active_real');
        return [
            'gps' => (clone $base)->where('type', 'gps')->count(),
            'delay' => (clone $base)->where('type', 'delay')->count(),
            'incident' => (clone $base)->where('type', 'incident')->count(),
            'return' => (clone $base)->where('type', 'return')->count(),
            'mission' => (clone $base)->where('type', 'mission')->count(),
            'unread' => (clone $base)->where('is_read', false)->count(),
        ];
    }

    public function unreadNotificationCount(): int
    {
        try {
            // IMPORTANT : ce compteur est affiché dans la barre de navigation de
            // toutes les pages Logistique. Il doit donc rester une lecture très
            // légère et ne jamais lancer syncRealNotifications() pendant le rendu.
            // La synchronisation complète reste exécutée sur la page Notifications
            // (LogisticsPilotageController::notifications) et peut aussi être lancée
            // par un job/cron dédié.
            if (! Schema::hasTable('logistics_pilotage_notifications')) {
                return 0;
            }

            return (int) LogisticsPilotageNotification::query()
                ->where('status', 'active_real')
                ->where('is_read', false)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function ensureSettings(): Collection
    {
        // Cache uniquement en mémoire pendant la requête HTTP. Cela évite de
        // rejouer 15+ firstOrCreate lorsqu'un même écran demande plusieurs fois
        // les paramètres (notifications + valeurs + navigation).
        if ($this->settingsSnapshot !== null) {
            return $this->settingsSnapshot;
        }

        if (! Schema::hasTable('logistics_pilotage_settings')) {
            return $this->settingsSnapshot = collect();
        }

        $contactEmail = 'contact@ovanie.ci';
        if (Schema::hasTable('settings')) {
            try {
                $contactEmail = (string) Setting::getValue('contactEmail', $contactEmail);
            } catch (Throwable) {
                // La page Pilotage doit rester accessible même si les réglages
                // généraux n'ont pas encore été initialisés.
            }
        }
        if (! filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $contactEmail = 'contact@ovanie.ci';
        }
        $phone = trim((string) config('support_ai.support_phone', '01 61 78 18 18')) ?: '01 61 78 18 18';
        $definitions = [
            ['general', 'platform_name', 'Nom de la plateforme', 'OVANIE Logistics', 'text', 'Identité affichée dans l’espace logistique'],
            ['general', 'contact_email', 'E-mail de contact', $contactEmail, 'email', 'Contact opérationnel OVANIE Logistics'],
            ['general', 'phone', 'Téléphone', $phone, 'text', 'Numéro de contact logistique'],
            ['operation', 'automatic_notifications', 'Notifications opérationnelles automatiques', '1', 'boolean', 'Générer les alertes à partir des missions, retards, incidents et retours réels'],
            ['delays', 'unassigned_alert', 'Mission non affectée', '15', 'number', 'Seuil en minutes avant alerte'],
            ['delays', 'critical_delay', 'Retard critique', '30', 'number', 'Seuil en minutes utilisé pour détecter les retards'],
            ['gps', 'realtime', 'Surveillance GPS des missions actives', '1', 'boolean', 'Détecter les missions dont le signal GPS n’est plus récent'],
            ['gps', 'loss_alert', 'Perte de signal GPS', '10', 'number', 'Seuil en minutes avant alerte GPS'],
            ['alerts', 'delay_alert', 'Alerte de retard', '1', 'boolean', 'Créer une notification lorsqu’une livraison dépasse son échéance'],
            ['alerts', 'incident_notification', 'Alerte incident', '1', 'boolean', 'Créer une notification pour les incidents non résolus'],
            ['alerts', 'return_notification', 'Alerte retour', '1', 'boolean', 'Créer une notification pour les retours nécessitant une action'],
            ['alerts', 'unassigned_mission', 'Alerte mission non affectée', '1', 'boolean', 'Créer une notification lorsqu’un colis prêt reste sans livreur'],
            ['rules', 'tour_optimization', 'Optimisation des tournées par défaut', '1', 'boolean', 'Activer par défaut l’optimisation routière lors de la création d’une tournée'],
            ['escalation', 'late_30', 'Escalader les retards importants', '1', 'boolean', 'Passer en priorité critique lorsque le retard atteint deux fois le seuil configuré'],
            ['escalation', 'critical_incident_rule', 'Escalader les incidents critiques', '1', 'boolean', 'Conserver la priorité critique pour les incidents déclarés critiques'],
            ['escalation', 'urgent_return', 'Prioriser les retours acceptés', '1', 'boolean', 'Passer en priorité haute les retours acceptés à organiser'],
        ];

        foreach ($definitions as $position => [$group, $key, $label, $value, $type, $description]) {
            $setting = LogisticsPilotageSetting::query()->firstOrCreate(
                ['setting_key' => $key],
                [
                    'group_key' => $group,
                    'label' => $label,
                    'value' => (string) $value,
                    'type' => $type,
                    'description' => $description,
                    'position' => $position,
                    'meta' => [],
                ]
            );
            $updates = [];
            if ($setting->group_key !== $group || $setting->label !== $label || $setting->type !== $type || $setting->description !== $description || (int) $setting->position !== $position) {
                $updates = [
                    'group_key' => $group,
                    'label' => $label,
                    'type' => $type,
                    'description' => $description,
                    'position' => $position,
                ];
            }
            if ($type === 'number') {
                $normalizedNumber = max(1, $this->numberFromSetting((string) $setting->value));
                if ((string) $setting->value !== (string) $normalizedNumber) {
                    $updates['value'] = (string) $normalizedNumber;
                }
            }
            if ($updates !== []) {
                $setting->forceFill($updates)->save();
            }
        }

        // Nettoyage des valeurs issues de l’ancien seeder de démonstration.
        LogisticsPilotageSetting::where('setting_key', 'contact_email')->where('value', 'contact@ovanie-logistics.com')->update(['value' => $contactEmail]);
        LogisticsPilotageSetting::where('setting_key', 'phone')->where('value', '+225 27 22 44 55 66')->update(['value' => $phone]);

        $supportedKeys = collect($definitions)->pluck(1)->values()->all();

        return $this->settingsSnapshot = LogisticsPilotageSetting::query()
            ->whereIn('setting_key', $supportedKeys)
            ->orderBy('position')
            ->get();
    }

    public function settingEnabled(string $key, bool $default = false): bool
    {
        $values = $this->settingValues();
        if (! array_key_exists($key, $values)) {
            return $default;
        }

        return (string) $values[$key] === '1';
    }

    public function settingValues(): array
    {
        return $this->ensureSettings()->pluck('value', 'setting_key')->all();
    }

    public function saveSettings(array $input): void
    {
        $settings = $this->ensureSettings()->keyBy('setting_key');
        foreach ($settings as $key => $setting) {
            if ($setting->type === 'boolean') {
                $value = array_key_exists($key, $input) ? '1' : '0';
            } else {
                $raw = $input[$key] ?? $setting->value;
                $value = trim(is_array($raw) ? implode(',', $raw) : (string) $raw);
                if ($setting->type === 'number') {
                    $value = (string) max(1, (float) $value);
                }
                if ($setting->type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $value = (string) $setting->value;
                }
                $value = Str::limit($value, 1000, '');
            }
            $setting->update(['value' => $value]);
        }
        $this->settingsSnapshot = null;
        Cache::forget('ovanie:logistics:pilotage:notifications:last-sync');
    }

    protected function matchesZone($order, ?string $fallbackAddress, ?LogisticsTerritoryZone $zone): bool
    {
        if (! $zone) {
            return true;
        }

        $haystack = $this->normalize(collect([
            $order?->delivery_commune,
            $order?->delivery_zone,
            $order?->delivery_quartier,
            $order?->delivery_city,
            $order?->delivery_address,
            $fallbackAddress,
        ])->filter()->implode(' '));

        $communes = collect($zone->covered_communes ?: []);
        if (method_exists($zone, 'communes')) {
            try {
                $relatedCommunes = $zone->relationLoaded('communes')
                    ? $zone->getRelation('communes')
                    : (Schema::hasTable('logistics_territory_zone_communes') ? $zone->communes()->get() : collect());
                $communes = $communes->merge($relatedCommunes->pluck('name'));
            } catch (Throwable) {
                // Compatibilité avec les anciennes installations du module Territoire.
            }
        }

        $needles = $communes
            ->push($zone->name)
            ->push($zone->code)
            ->filter()
            ->map(fn ($value) => $this->normalize((string) $value))
            ->filter();

        return $needles->contains(fn ($needle) => $needle !== '' && str_contains($haystack, $needle));
    }

    protected function zoneLabel($order, ?string $fallbackAddress): string
    {
        return trim((string) ($order?->delivery_commune ?: $order?->delivery_zone ?: $order?->delivery_city ?: $fallbackAddress ?: 'Non renseignée'));
    }

    protected function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replace(['-', '_', ',', ';', '/'], ' ')->squish()->toString();
    }

    protected function isDemoMeta($meta): bool
    {
        $meta = is_array($meta) ? $meta : [];
        return (bool) ($meta['demo'] ?? false) || in_array(($meta['source'] ?? null), ['demo', 'seeder'], true);
    }

    protected function incidentDate($incident): ?Carbon
    {
        return $incident->occurred_at ?: $incident->created_at;
    }

    protected function providerLabel(string $provider): string
    {
        return match (strtolower($provider)) {
            'ovanie', 'internal', 'ovanie_logistics' => 'OVANIE Logistics',
            'partner', 'carrier', 'external' => 'Partenaires',
            'seller', 'vendor' => 'Logistique vendeur',
            'pickup' => 'Retrait',
            default => 'Autres',
        };
    }

    protected function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'open', 'pending' => 'Ouvert',
            'in_progress', 'processing' => 'En cours',
            'resolved' => 'Résolu',
            'closed' => 'Clôturé',
            default => $this->humanize($status ?: 'En cours'),
        };
    }

    protected function returnStatusLabel(ReturnModel $return): string
    {
        return match ((string) $return->status) {
            'pending' => 'En attente',
            'accepted' => 'Accepté',
            'rejected' => 'Rejeté',
            'refunded' => 'Remboursé',
            'resolved', 'closed' => 'Traité',
            'cancelled' => 'Annulé',
            default => $this->humanize((string) $return->status),
        };
    }

    protected function humanize(string $value): string
    {
        return Str::of($value)->replace(['_', '-'], ' ')->squish()->ucfirst()->toString();
    }

    protected function kpi(string $label, int|float $current, int|float $previous, string $icon, string $tone, bool $lowerIsBetter = false): array
    {
        $change = $this->percentChange((float) $current, (float) $previous);
        return [
            'label' => $label,
            'value' => number_format((float) $current, 0, ',', ' '),
            'change' => $change,
            'change_label' => $this->formatChange($change),
            'positive' => $lowerIsBetter ? $change <= 0 : $change >= 0,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    protected function rateKpi(string $label, ?float $current, ?float $previous, string $icon, string $tone): array
    {
        $currentValue = $current ?? 0;
        $previousValue = $previous ?? 0;
        $change = round($currentValue - $previousValue, 1);
        return [
            'label' => $label,
            'value' => $current === null ? '—' : number_format($current, 1, ',', ' ') . '%',
            'change' => $change,
            'change_label' => ($change >= 0 ? '+ ' : '- ') . number_format(abs($change), 1, ',', ' ') . ' pt',
            'positive' => $change >= 0,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    protected function moneyKpi(string $label, float $current, float $previous, string $icon, string $tone, bool $higherIsBetter = false): array
    {
        $change = $this->percentChange($current, $previous);
        return [
            'label' => $label,
            'value' => number_format($current, 0, ',', ' ') . ' FCFA',
            'change' => $change,
            'change_label' => $this->formatChange($change),
            'positive' => $higherIsBetter ? $change >= 0 : $change <= 0,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    protected function percentChange(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    protected function formatChange(float $change): string
    {
        return ($change >= 0 ? '+ ' : '- ') . number_format(abs($change), 1, ',', ' ') . '%';
    }

    protected function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        return $rest ? "{$hours}h {$rest}min" : "{$hours}h";
    }

    protected function numberFromSetting(string $value): float
    {
        if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)/', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }
        return 0;
    }
}
