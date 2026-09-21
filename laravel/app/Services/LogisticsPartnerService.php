<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\CarrierVehicle;
use App\Models\DeliveryServiceZone;
use App\Models\LogisticsPartner;
use App\Models\Shipment;
use App\Support\LogisticsOperationalDataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LogisticsPartnerService
{
    public function carrierRow(Carrier $carrier): array
    {
        $metrics = $this->metrics($carrier);
        $coverage = $this->coverage($carrier);
        $profile = $carrier->partnerProfile;

        return [
            'carrier' => $carrier,
            'code' => $carrier->code ?: 'CAR-' . str_pad((string) $carrier->id, 5, '0', STR_PAD_LEFT),
            'type' => $profile?->partner_type === 'specialized' ? 'specialized' : 'partner',
            'type_label' => $profile?->partner_type === 'specialized' ? 'Spécialisé BTP' : 'Partenaire',
            'coverage' => $coverage,
            'coverage_label' => $coverage->take(3)->implode(', '),
            'vehicle_count' => $metrics['vehicle_count'],
            'mission_count' => $metrics['mission_count'],
            'sla_percent' => $metrics['sla_percent'],
            'delays_count' => $metrics['delays_count'],
            'active_missions' => $metrics['active_missions'],
            'successful_deliveries' => $metrics['successful_deliveries'],
            'is_active' => $this->isActive($carrier),
            'status_label' => $this->isActive($carrier) ? 'Actif' : ($carrier->status === 'draft' ? 'Brouillon' : 'Suspendu'),
            'logo_url' => $profile?->logo_path ? asset('storage/' . ltrim($profile->logo_path, '/')) : null,
            'profile' => $profile,
        ];
    }

    public function metrics(Carrier $carrier): array
    {
        $vehicleCount = $carrier->relationLoaded('vehicles')
            ? $carrier->vehicles->where('is_active', true)->count()
            : $carrier->vehicles()->where('is_active', true)->count();

        $all = $this->realShipmentsQuery($carrier)->get([
            'id', 'status', 'created_at', 'estimated_delivery_at', 'delivered_at', 'carrier_id',
        ]);

        $missionCount = $all->count();
        $successful = $all->filter(fn (Shipment $s) => $this->isDelivered($s))->count();
        $active = $all->filter(fn (Shipment $s) => ! $this->isClosed($s))->count();

        $recent = $all->filter(fn (Shipment $s) => $s->created_at && $s->created_at->gte(now()->subDays(30)));
        $slaEligible = $recent->filter(fn (Shipment $s) => $this->isDelivered($s) && $s->estimated_delivery_at && $s->delivered_at);
        $onTime = $slaEligible->filter(fn (Shipment $s) => $s->delivered_at->lte($s->estimated_delivery_at))->count();
        $sla = $slaEligible->isNotEmpty() ? round(($onTime / $slaEligible->count()) * 100, 1) : null;

        $delays = $recent->filter(function (Shipment $s) {
            if ($s->delivered_at && $s->estimated_delivery_at) {
                return $s->delivered_at->gt($s->estimated_delivery_at);
            }

            return ! $this->isClosed($s)
                && $s->estimated_delivery_at
                && $s->estimated_delivery_at->isPast();
        })->count();

        return [
            'vehicle_count' => $vehicleCount,
            'mission_count' => $missionCount,
            'successful_deliveries' => $successful,
            'active_missions' => $active,
            'sla_percent' => $sla,
            'delays_count' => $delays,
        ];
    }

    public function coverage(Carrier $carrier): Collection
    {
        $profileCoverage = collect($carrier->partnerProfile?->coverage ?? []);

        $serviceCoverage = $carrier->deliveryServices
            ->flatMap(fn ($service) => $service->zones)
            ->filter(fn ($zone) => $zone->is_active !== false)
            ->flatMap(fn ($zone) => [$zone->commune, $zone->city, $zone->delivery_zone]);

        $rateCoverage = $carrier->rateCards
            ->filter(fn ($rate) => $rate->is_active !== false)
            ->pluck('zone');

        return $profileCoverage
            ->merge($serviceCoverage)
            ->merge($rateCoverage)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => $this->normalize($value))
            ->values();
    }

    public function fleetDistribution(Carrier $carrier): Collection
    {
        $vehicles = $carrier->relationLoaded('vehicles')
            ? $carrier->vehicles->where('is_active', true)
            : $carrier->vehicles()->where('is_active', true)->get();

        return $vehicles
            ->groupBy(fn (CarrierVehicle $vehicle) => $this->vehicleTypeLabel($vehicle->type))
            ->map(fn (Collection $group) => $group->count())
            ->sortDesc();
    }

    public function availableMissions(Carrier $carrier): Collection
    {
        $coverage = $this->coverage($carrier);

        return LogisticsOperationalDataScope::shipments(Shipment::query())
            ->with(['order', 'orderItem'])
            ->whereNull('carrier_id')
            ->whereIn('status', ['pending', 'ready_for_pickup'])
            ->latest('id')
            ->limit(80)
            ->get()
            ->map(function (Shipment $shipment) use ($coverage) {
                $commune = trim((string) ($shipment->order?->delivery_commune ?: $shipment->order?->delivery_city ?: ''));
                $destination = trim((string) ($shipment->delivery_address ?: $shipment->order?->delivery_address ?: $shipment->order?->address ?: ''));
                $weight = (float) ($shipment->total_weight_kg ?? 0);
                $vehicle = $shipment->vehicle_label ?: $this->recommendedVehicle($weight);
                $compatible = $this->coverageMatches($coverage, $commune ?: $destination);
                $priority = data_get($shipment->meta, 'priority');

                if (! in_array($priority, ['urgent', 'high', 'normal'], true)) {
                    $priority = $shipment->estimated_delivery_at && $shipment->estimated_delivery_at->lte(now()->addHours(6))
                        ? 'urgent'
                        : 'normal';
                }

                return [
                    'id' => $shipment->id,
                    'reference' => $shipment->tracking_number ?: 'SHP-' . str_pad((string) $shipment->id, 6, '0', STR_PAD_LEFT),
                    'destination' => $destination ?: 'Adresse non renseignée',
                    'commune' => $commune ?: '—',
                    'weight_kg' => $weight,
                    'vehicle' => $vehicle,
                    'eta' => $shipment->estimated_delivery_at,
                    'priority' => $priority,
                    'compatible' => $compatible,
                    'order_item_id' => $shipment->order_item_id,
                ];
            });
    }

    public function missionHistory(Carrier $carrier, int $limit = 30): Collection
    {
        return $this->realShipmentsQuery($carrier)
            ->with(['order', 'orderItem', 'deliveryAssignment.driver'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (Shipment $shipment) {
                $assignment = $shipment->deliveryAssignment;
                return [
                    'id' => $shipment->id,
                    'reference' => $assignment?->resolved_mission_number
                        ?: $shipment->tracking_number
                        ?: 'SHP-' . str_pad((string) $shipment->id, 6, '0', STR_PAD_LEFT),
                    'destination' => $shipment->delivery_address
                        ?: $shipment->order?->delivery_address
                        ?: $shipment->order?->delivery_commune
                        ?: '—',
                    'vehicle' => $shipment->vehicle_label ?: data_get($assignment?->meta, 'vehicle_label') ?: '—',
                    'driver' => $assignment?->driver?->name ?: 'Non affecté',
                    'date' => $shipment->delivered_at ?: $shipment->updated_at ?: $shipment->created_at,
                    'status' => $this->statusLabel($shipment->status),
                    'status_key' => $this->statusKey($shipment->status),
                    'order_item_id' => $shipment->order_item_id,
                ];
            });
    }

    public function globalVehicleDistribution(): Collection
    {
        return CarrierVehicle::query()
            ->where('is_active', true)
            ->whereHas('carrier', function (Builder $carrier) {
                $carrier->where(function (Builder $code) {
                    $code->whereNull('code')->orWhere('code', '!=', 'ovanie-logistics');
                })->whereDoesntHave('deliveryServices', fn (Builder $service) => $service->where('provider_type', 'ovanie'));
            })
            ->get(['type'])
            ->groupBy(fn (CarrierVehicle $vehicle) => $this->vehicleTypeLabel($vehicle->type))
            ->map(fn (Collection $group) => $group->count())
            ->sortDesc();
    }

    public function coverageOptions(): Collection
    {
        $serviceZones = DeliveryServiceZone::query()
            ->where('is_active', true)
            ->whereHas('service', function (Builder $query) {
                $query->whereNotNull('carrier_id')
                    ->where('provider_type', '!=', 'ovanie')
                    ->whereHas('carrier', function (Builder $carrier) {
                        $carrier->where(function (Builder $code) {
                            $code->whereNull('code')->orWhere('code', '!=', 'ovanie-logistics');
                        });
                    });
            })
            ->get(['commune', 'city', 'delivery_zone'])
            ->flatMap(fn ($zone) => [$zone->commune, $zone->city, $zone->delivery_zone]);

        $profileZones = LogisticsPartner::query()
            ->get(['coverage'])
            ->flatMap(fn (LogisticsPartner $profile) => $profile->coverage ?? []);

        return $serviceZones
            ->merge($profileZones)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->unique(fn ($value) => $this->normalize($value))
            ->sort()
            ->values();
    }

    public function isActive(Carrier $carrier): bool
    {
        return (bool) $carrier->is_active && ! in_array($carrier->status, ['suspended', 'inactive', 'draft'], true);
    }

    public function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'pending' => 'En attente',
            'ready_for_pickup' => 'Prête à affecter',
            'assigned' => 'Affectée',
            'picked_up', 'collected' => 'Collectée',
            'in_transit', 'shipped' => 'En transit',
            'arrived' => 'Arrivée',
            'delivered', 'completed' => 'Livrée',
            'cancelled', 'canceled' => 'Annulée',
            'incident' => 'Incident',
            default => filled($status) ? Str::headline((string) $status) : '—',
        };
    }

    public function statusKey(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'delivered', 'completed' => 'good',
            'cancelled', 'canceled', 'incident' => 'bad',
            'in_transit', 'shipped', 'picked_up', 'collected', 'assigned', 'arrived' => 'progress',
            default => 'pending',
        };
    }

    public function recommendedVehicle(float $weightKg): string
    {
        return match (true) {
            $weightKg <= 20 => 'Moto',
            $weightKg <= 250 => 'Tricycle',
            $weightKg <= 1000 => 'Pickup',
            $weightKg <= 3000 => 'Camion 3T',
            default => 'Camion 10T',
        };
    }

    public function vehicleTypeLabel(?string $value): string
    {
        $type = $this->normalize((string) $value);

        return match (true) {
            str_contains($type, 'moto'), str_contains($type, 'motorcycle') => 'Moto',
            str_contains($type, 'tri') => 'Tricycle',
            str_contains($type, 'pickup'), str_contains($type, 'pick up') => 'Pickup',
            str_contains($type, '10t'), str_contains($type, '10 t') => 'Camion 10T',
            str_contains($type, '3t'), str_contains($type, '3 t') => 'Camion 3T',
            str_contains($type, 'camion'), str_contains($type, 'truck') => 'Camion',
            default => filled($value) ? Str::headline((string) $value) : 'Autre',
        };
    }

    public function coverageMatches(Collection $coverage, string $location): bool
    {
        if ($coverage->isEmpty() || blank($location)) {
            return false;
        }

        $needle = $this->normalize($location);

        return $coverage->contains(function ($zone) use ($needle) {
            $normalized = $this->normalize((string) $zone);
            return $normalized === $needle
                || str_contains($needle, $normalized)
                || str_contains($normalized, $needle);
        });
    }

    public function realShipmentsQuery(Carrier $carrier): Builder
    {
        return LogisticsOperationalDataScope::shipments(Shipment::query())
            ->where('carrier_id', $carrier->id);
    }

    private function isDelivered(Shipment $shipment): bool
    {
        return (bool) $shipment->delivered_at
            || in_array(strtolower((string) $shipment->status), ['delivered', 'completed'], true);
    }

    private function isClosed(Shipment $shipment): bool
    {
        return $this->isDelivered($shipment)
            || in_array(strtolower((string) $shipment->status), ['cancelled', 'canceled'], true);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->value();
    }
}
