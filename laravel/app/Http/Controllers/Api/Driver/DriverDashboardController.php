<?php

namespace App\Http\Controllers\Api\Driver;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Services\DriverMissionService;
use App\Services\VehicleAppearanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DriverDashboardController
{
    public function __invoke(Request $request, DriverMissionService $missions, VehicleAppearanceService $appearanceService): JsonResponse
    {
        /** @var DeliveryDriver|null $driver */
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        // La Flotte est la source prioritaire pour le véhicule affiché dans l'app.
        // Si une ancienne fiche n'a pas encore de couleur, le service l'extrait une
        // fois depuis la vraie photo d'inscription et la mémorise.
        $vehicleAppearance = $appearanceService->forDriver($driver);
        $driver->refresh();
        $driver->loadMissing('currentLocation');

        $all = $missions->listForDriver($driver);
        $activeStatuses = ['accepted', 'collecting', 'picked_up', 'in_transit', 'arrived', 'incident'];
        $waitingStatuses = ['assigned', 'planned'];

        $currentMission = $this->pickCurrentMission($all, $activeStatuses);
        $priorityMission = $currentMission ?: $this->pickPriorityMission($all, $waitingStatuses);
        $nextMission = $this->pickNextMission($all, $priorityMission, $waitingStatuses);

        $deliveredTodayMissionNumbers = DeliveryAssignment::query()
            ->where('driver_id', $driver->id)
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', now()->toDateString())
            ->get(['id', 'mission_number', 'order_id', 'meta'])
            ->map(fn (DeliveryAssignment $assignment) => $assignment->resolved_mission_number)
            ->filter()
            ->unique()
            ->values();
        $completedToday = $deliveredTodayMissionNumbers->count();

        $todayMissionNumbers = $all->filter(function (array $mission): bool {
            $pickup = $mission['pickup_scheduled_at'] ?? null;
            $eta = $mission['estimated_delivery_at'] ?? null;

            return ($pickup instanceof Carbon && $pickup->isToday())
                || ($eta instanceof Carbon && $eta->isToday());
        })->pluck('mission_number')->filter();

        $todayMissions = $todayMissionNumbers
            ->merge($deliveredTodayMissionNumbers)
            ->unique()
            ->count();

        $notifications = collect();
        $unreadCount = 0;
        if (Schema::hasTable('notifications')) {
            $unreadCount = $driver->unreadNotifications()->count();
            $notifications = $driver->notifications()
                ->latest()
                ->take(4)
                ->get()
                ->map(fn ($notification) => [
                    'id' => (string) $notification->id,
                    'title' => (string) data_get($notification->data, 'title', 'Notification OVANIE'),
                    'message' => (string) data_get($notification->data, 'message', data_get($notification->data, 'body', '')),
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'mission_number' => data_get($notification->data, 'mission_number'),
                ]);
        }

        return response()->json([
            'driver' => [
                'id' => $driver->id,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'avatar_url' => $driver->avatar
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($driver->avatar)
                    : null,
                'onboarding_status' => $driver->onboarding_status,
                'availability' => $driver->status ?: 'Indisponible',
                'is_online' => $driver->hasFreshGpsPresence(),
                'last_gps_seen_at' => $driver->lastGpsSeenAt()?->toIso8601String(),
                'vehicle' => $vehicleAppearance['type_label'],
                'vehicle_code' => $vehicleAppearance['type_code'],
                'vehicle_plate' => $vehicleAppearance['plate'],
                'vehicle_color' => $vehicleAppearance['color'],
                'vehicle_color_hex' => $vehicleAppearance['color_hex'],
                'vehicle_photo_url' => $vehicleAppearance['photo_url'],
                'vehicle_photo_is_real' => $vehicleAppearance['photo_is_real'],
                'vehicle_reference_asset_url' => $vehicleAppearance['reference_photo_url'],
                'fleet_vehicle_code' => $vehicleAppearance['fleet_code'],
                'vehicle_brand' => $vehicleAppearance['brand'],
                'vehicle_model' => $vehicleAppearance['model'],
                // Toutes les communes réellement choisies à l'inscription.
                // `zone` est conservé uniquement pour compatibilité avec les anciens clients.
                'zone' => $driver->zone,
                'zones' => $driver->interventionZones(),
                'zone_label' => $driver->interventionZonesLabel(2),
                'zone_count' => count($driver->interventionZones()),
                'gps_accuracy_m' => optional($driver->currentLocation)->accuracy,
                'rating' => $driver->rating,
            ],
            'account' => [
                'status' => $driver->onboarding_status,
                'is_active' => (bool) $driver->is_active,
                'reviewed_at' => $driver->reviewed_at?->toIso8601String(),
                'rejection_reason' => $driver->rejection_reason,
            ],
            'availability' => [
                'status' => $currentMission ? 'En mission' : ($driver->status ?: 'Indisponible'),
                'can_change' => $currentMission === null,
                'can_receive_missions' => $currentMission === null
                    && $driver->status === 'Disponible'
                    && $driver->isOnboardingActive(),
            ],
            'priority_mission' => $this->serializeMission($priorityMission),
            'current_mission' => $this->serializeMission($currentMission),
            'next_mission' => $this->serializeMission($nextMission),
            'summary' => [
                'missions_today' => $todayMissions,
                'active_missions' => $all->whereIn('status', array_merge($activeStatuses, $waitingStatuses))->count(),
                'completed_today' => $completedToday,
                'completed_total' => $all->where('status', 'delivered')->count(),
                'unread_notifications' => $unreadCount,
            ],
            'notifications' => $notifications->values()->all(),
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    private function pickCurrentMission(Collection $missions, array $statuses): ?array
    {
        $rank = [
            'incident' => 0,
            'arrived' => 1,
            'in_transit' => 2,
            'picked_up' => 3,
            'collecting' => 4,
            'accepted' => 5,
        ];

        return $missions
            ->filter(fn (array $mission) => in_array($mission['status'] ?? '', $statuses, true))
            ->sortBy(fn (array $mission) => $rank[$mission['status'] ?? ''] ?? 99)
            ->first();
    }

    private function pickPriorityMission(Collection $missions, array $statuses): ?array
    {
        return $missions
            ->filter(fn (array $mission) => in_array($mission['status'] ?? '', $statuses, true))
            ->sortBy(function (array $mission) {
                $statusRank = ($mission['status'] ?? '') === 'assigned' ? 0 : 1;
                $scheduled = $mission['pickup_scheduled_at'] ?? $mission['estimated_delivery_at'] ?? null;
                $timestamp = $scheduled instanceof Carbon ? $scheduled->timestamp : PHP_INT_MAX;

                return sprintf('%d-%020d', $statusRank, $timestamp);
            })
            ->first();
    }

    private function pickNextMission(Collection $missions, ?array $priority, array $statuses): ?array
    {
        $priorityNumber = $priority['mission_number'] ?? null;

        return $missions
            ->filter(fn (array $mission) => in_array($mission['status'] ?? '', $statuses, true))
            ->reject(fn (array $mission) => ($mission['mission_number'] ?? null) === $priorityNumber)
            ->sortBy(function (array $mission) {
                $scheduled = $mission['pickup_scheduled_at'] ?? $mission['estimated_delivery_at'] ?? null;
                return $scheduled instanceof Carbon ? $scheduled->timestamp : PHP_INT_MAX;
            })
            ->first();
    }

    private function serializeMission(?array $mission): ?array
    {
        if (! $mission) {
            return null;
        }

        $pickup = $mission['pickup_scheduled_at'] ?? null;
        $eta = $mission['estimated_delivery_at'] ?? null;

        return [
            'mission_number' => $mission['mission_number'] ?? null,
            'order_number' => $mission['order_number'] ?? null,
            'client_name' => $mission['client_name'] ?? null,
            'destination' => $mission['destination'] ?? null,
            'commune' => $mission['commune'] ?? null,
            'status' => $mission['status'] ?? null,
            'status_label' => $mission['status_label'] ?? null,
            'pickup_scheduled_at' => $pickup instanceof Carbon ? $pickup->toIso8601String() : null,
            'estimated_delivery_at' => $eta instanceof Carbon ? $eta->toIso8601String() : null,
            'pickup_count' => (int) ($mission['pickup_count'] ?? 0),
            'item_count' => (int) ($mission['item_count'] ?? 0),
            'total_weight_kg' => (float) ($mission['total_weight_kg'] ?? 0),
            'vehicle_label' => $mission['vehicle_label'] ?? null,
            'preparation_percent' => (int) ($mission['preparation_percent'] ?? 0),
        ];
    }
}
