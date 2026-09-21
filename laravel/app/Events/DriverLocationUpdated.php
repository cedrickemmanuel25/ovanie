<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public DriverLocation $location)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('logistics.tracking');
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->location->driver_id,
            'shipment_id' => $this->location->shipment_id,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'accuracy' => $this->location->accuracy,
            'speed' => $this->location->speed,
            'heading' => $this->location->heading,
            'recorded_at' => optional($this->location->recorded_at)->toIso8601String(),
        ];
    }
}
