<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsFleetVehicle extends Model
{
    protected $guarded = [];
    protected $casts = [
        'documents'=>'array','mission_history'=>'array','maintenance_history'=>'array','features'=>'array','meta'=>'array','service_date'=>'date',
    ];
    public function getRouteKeyName(): string { return 'code'; }

    public function serviceZones(?DeliveryDriver $driver): array
    {
        if ($driver && data_get($this->meta, 'source') === 'driver_profile'
            && (int) data_get($this->meta, 'driver_id', 0) === (int) $driver->id) {
            return $driver->interventionZones();
        }
        return filled($this->zone) ? [$this->zone] : [];
    }

    public function vehicleSpecification(): array
    {
        $code = str_replace(' ', '_', mb_strtolower(trim((string) $this->vehicle_type)));
        return \App\Services\LogisticsPricingWorkspaceService::VEHICLES[$code] ?? [];
    }

    public function capacityKg(): ?float
    {
        return $this->capacity_kg > 0 ? (float) $this->capacity_kg : ($this->vehicleSpecification()['max_weight_kg'] ?? null);
    }

    public function volumeM3(): ?float
    {
        return $this->volume_m3 > 0 ? (float) $this->volume_m3 : ($this->vehicleSpecification()['max_volume_m3'] ?? null);
    }

    public function typeLabel(): string
    {
        return $this->vehicleSpecification()['label'] ?? ucfirst((string) $this->vehicle_type);
    }

    public function capacityLabel(): string
    {
        return $this->capacityKg() !== null ? number_format($this->capacityKg(), 0, ',', ' ').' kg' : 'Non renseignée';
    }

    public function photoPathForDriver(?DeliveryDriver $driver): ?string
    {
        $sameVehicle = $driver && (
            (int) data_get($this->meta, 'driver_id', 0) === (int) $driver->id
            || (filled(data_get($driver->profile, 'plate'))
                && mb_strtolower(trim(data_get($driver->profile, 'plate'))) === mb_strtolower(trim($this->registration)))
        );
        return ($sameVehicle ? $driver->vehiclePhotoPath() : null) ?: data_get($this->meta, 'photo_path');
    }
}
