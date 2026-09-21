<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\LogisticsTerritoryZone;
use App\Models\Shipment;
use App\Support\LogisticsOperationalDataScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LogisticsTerritoryMetricsService
{
    public function forZones(Collection $zones): array
    {
        $result = [];
        foreach ($zones as $zone) {
            $result[$zone->id] = $this->forZone($zone);
        }

        return $result;
    }

    public function forZone(LogisticsTerritoryZone $zone): array
    {
        $communes = $this->communeNames($zone);
        $communeIds = $zone->relationLoaded('communes')
            ? $zone->communes->pluck('id')->map(fn ($id) => (int) $id)->values()
            : collect();

        $now = now();
        $weekStart = $now->copy()->subDays(7);
        $previousWeekStart = $now->copy()->subDays(14);

        $activity7d = $this->shipmentQuery($communes)->where('created_at', '>=', $weekStart)->count();
        $previous7d = $this->shipmentQuery($communes)
            ->whereBetween('created_at', [$previousWeekStart, $weekStart])
            ->count();
        $growth = $this->percentChange($activity7d, $previous7d);

        $today = $this->shipmentQuery($communes)->whereDate('created_at', $now->toDateString())->count();
        $yesterday = $this->shipmentQuery($communes)->whereDate('created_at', $now->copy()->subDay()->toDateString())->count();

        $waiting = $this->assignmentQuery($communes)
            ->whereIn('status', ['planned', 'assigned'])
            ->count();
        $inProgress = $this->assignmentQuery($communes)
            ->whereIn('status', ['accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])
            ->count();

        $drivers = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->with('commune:id,name')
            ->withCount([
                'activeAssignments as operational_active_assignments_count' => fn (Builder $assignment) => LogisticsOperationalDataScope::assignments($assignment),
            ])
            ->orderBy('name')
            ->get()
            ->filter(function (DeliveryDriver $driver) use ($communes, $communeIds) {
                if ($communes->isEmpty() && $communeIds->isEmpty()) {
                    return false;
                }

                if ($communeIds->isNotEmpty() && array_intersect($driver->interventionZoneIds(), $communeIds->all())) {
                    return true;
                }

                return $communes->contains(fn ($commune) => $driver->coversZone((string) $commune));
            })
            ->values();
        $driversTotal = $drivers->count();
        $driversAvailable = $drivers->filter(fn (DeliveryDriver $driver) => $this->driverAvailable($driver))->count();
        $load = $driversTotal > 0 ? min(100, (int) round(($inProgress / max(1, $driversTotal)) * 100)) : 0;

        $recentDelivered = $this->shipmentQuery($communes)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $now->copy()->subDays(30))
            ->get(['delivered_at', 'estimated_delivery_at', 'distance_km']);

        $eligibleSla = $recentDelivered->filter(fn (Shipment $shipment) => $shipment->estimated_delivery_at !== null);
        $sla = $eligibleSla->isNotEmpty()
            ? (int) round($eligibleSla->filter(fn (Shipment $shipment) => $shipment->delivered_at->lte($shipment->estimated_delivery_at))->count() * 100 / $eligibleSla->count())
            : 0;
        $averageDistance = round((float) ($recentDelivered->whereNotNull('distance_km')->avg('distance_km') ?? 0), 1);

        return [
            'activity_7d' => $activity7d,
            'activity_previous_7d' => $previous7d,
            'activity_growth_percent' => $growth,
            'deliveries_today' => $today,
            'deliveries_yesterday' => $yesterday,
            'deliveries_growth' => $this->percentChange($today, $yesterday),
            'waiting_missions' => $waiting,
            'missions_in_progress' => $inProgress,
            'current_load_percent' => $load,
            'drivers_available' => $driversAvailable,
            'drivers_total' => $driversTotal,
            'average_distance_km' => $averageDistance,
            'sla_percent' => $sla,
            'delivery_density' => $this->density($activity7d),
            'drivers' => $drivers->map(fn (DeliveryDriver $driver) => [
                'id' => $driver->id,
                'initials' => $driver->initials,
                'name' => $driver->name,
                'vehicle' => $driver->vehicle ?: 'Non renseigné',
                'status' => $driver->status ?: ($driver->is_online ? 'Disponible' : 'Hors ligne'),
                'availability' => $this->availabilityLabel($driver),
                'commune' => $driver->interventionZonesLabel(2),
                'available' => $this->driverAvailable($driver),
            ])->values()->all(),
        ];
    }

    /**
     * Détail des livreurs par commune de la zone (et non plus seulement agrégé
     * sur toute la zone) : total, disponibles, en mission (en ligne mais pas
     * disponible) et hors ligne. Se base sur les livreurs actifs dont la
     * commune fait partie de `interventionZoneIds()`.
     *
     * @return array<int, array{commune: \App\Models\AbidjanCommune, drivers_total: int, drivers_available: int, drivers_in_mission: int, drivers_offline: int}>
     */
    public function communeBreakdown(LogisticsTerritoryZone $zone): array
    {
        $zone->loadMissing('communes:id,code,name,slug,latitude,longitude');
        if ($zone->communes->isEmpty()) {
            return [];
        }

        $drivers = LogisticsOperationalDataScope::drivers(DeliveryDriver::query())
            ->where('is_active', true)
            ->withCount([
                'activeAssignments as operational_active_assignments_count' => fn (Builder $assignment) => LogisticsOperationalDataScope::assignments($assignment),
            ])
            ->get();

        return $zone->communes->map(function ($commune) use ($drivers) {
            $communeId = (int) $commune->id;
            $matching = $drivers->filter(
                fn (DeliveryDriver $driver) => in_array($communeId, $driver->interventionZoneIds(), true)
            )->values();

            $available = $matching->filter(fn (DeliveryDriver $driver) => $this->driverAvailable($driver));
            $inMission = $matching->filter(
                fn (DeliveryDriver $driver) => $driver->is_online && ! $this->driverAvailable($driver)
            );
            $offline = $matching->filter(fn (DeliveryDriver $driver) => ! $driver->is_online);

            return [
                'commune' => $commune,
                'drivers_total' => $matching->count(),
                'drivers_available' => $available->count(),
                'drivers_in_mission' => $inMission->count(),
                'drivers_offline' => $offline->count(),
            ];
        })->values()->all();
    }

    public function communeNames(LogisticsTerritoryZone $zone): Collection
    {
        if ($zone->relationLoaded('communes') && $zone->communes->isNotEmpty()) {
            return $zone->communes->pluck('name')->filter()->unique()->values();
        }

        return collect($zone->covered_communes ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->values();
    }

    private function shipmentQuery(Collection $communes): Builder
    {
        $query = LogisticsOperationalDataScope::shipments(Shipment::query())
            ->whereHas('order', function (Builder $order) use ($communes) {
                if ($communes->isNotEmpty()) {
                    $order->whereIn('delivery_commune', $communes->all());
                } else {
                    $order->whereRaw('1 = 0');
                }
            });

        return $query;
    }

    private function assignmentQuery(Collection $communes): Builder
    {
        return LogisticsOperationalDataScope::assignments(DeliveryAssignment::query())
            ->whereHas('order', function (Builder $order) use ($communes) {
                if ($communes->isNotEmpty()) {
                    $order->whereIn('delivery_commune', $communes->all());
                } else {
                    $order->whereRaw('1 = 0');
                }
            });
    }

    private function driverAvailable(DeliveryDriver $driver): bool
    {
        if (! $driver->is_online || (int) ($driver->operational_active_assignments_count ?? 0) > 0) {
            return false;
        }

        $status = mb_strtolower(trim((string) $driver->status));
        foreach (['livraison', 'mission', 'occup', 'indisponible', 'hors ligne', 'suspend'] as $busy) {
            if (str_contains($status, $busy)) {
                return false;
            }
        }

        return true;
    }

    private function availabilityLabel(DeliveryDriver $driver): string
    {
        if ($this->driverAvailable($driver)) {
            return 'Maintenant';
        }
        if ($driver->last_seen_at instanceof CarbonInterface) {
            return 'Vu '.$driver->last_seen_at->diffForHumans();
        }

        return 'Non disponible';
    }

    private function percentChange(int $current, int $previous): int
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function density(int $activity): string
    {
        return match (true) {
            $activity >= 100 => 'Élevée',
            $activity >= 30 => 'Moyenne',
            default => 'Faible',
        };
    }
}
