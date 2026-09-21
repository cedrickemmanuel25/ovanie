<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Services\DriverMissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * API missions de l'application Flutter « OVANIE Livreur ».
 *
 * Toute la logique métier reste centralisée dans DriverMissionService, déjà
 * utilisée par l'espace web livreur. Ce contrôleur ne fait que valider les
 * requêtes et sérialiser un contrat JSON stable pour l'application mobile.
 */
class DriverMissionController extends Controller
{
    public function index(Request $request, DriverMissionService $service): JsonResponse
    {
        $driver = $this->driver($request);
        $missions = $service->listForDriver($driver);

        $unreadNotifications = 0;
        if (Schema::hasTable('notifications')) {
            $unreadNotifications = $driver->unreadNotifications()->count();
        }

        return response()->json([
            'missions' => $missions->map(fn (array $mission) => $this->serializeSummary($mission))->values()->all(),
            'counts' => [
                'to_accept' => $missions->filter(fn (array $mission) => in_array($mission['status'] ?? '', ['assigned', 'planned', 'offered'], true))->count(),
                'accepted' => $missions->where('status', 'accepted')->count(),
                'in_progress' => $missions->filter(fn (array $mission) => in_array($mission['status'] ?? '', [
                    'collecting', 'picked_up', 'in_transit', 'arrived', 'incident',
                ], true))->count(),
                'delivered' => $missions->where('status', 'delivered')->count(),
            ],
            'unread_notifications' => $unreadNotifications,
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }


    /**
     * Configuration Mapbox publique utilisée par l'application Livreur.
     *
     * Le token Mapbox exposé ici doit être un token public (pk...). Il reste
     * configuré côté Laravel dans MAPBOX_PUBLIC_TOKEN et n'est jamais codé en
     * dur dans l'application Flutter.
     */
    public function mapConfig(Request $request): JsonResponse
    {
        $this->driver($request);

        $token = trim((string) config('geo.mapbox.public_token'));
        abort_unless(
            $token !== '' && str_starts_with($token, 'pk.'),
            503,
            'Le token public Mapbox OVANIE est absent ou invalide.'
        );

        return response()->json([
            'provider' => 'mapbox',
            'access_token' => $token,
            'style_uri' => (string) (
                config('services.mapbox.style_url')
                ?: 'mapbox://styles/mapbox/streets-v12'
            ),
        ]);
    }

    public function show(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        return $this->missionResponse($service->detail($this->driver($request), $missionNumber));
    }

    public function accept(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $driver = $this->driver($request);
        $service->accept($driver, $missionNumber);

        return $this->missionResponse(
            $service->detail($driver->fresh(), $missionNumber),
            'Mission acceptée.'
        );
    }

    public function reject(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $service->reject($this->driver($request), $missionNumber, $validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Mission refusée et renvoyée au centre logistique.',
        ]);
    }

    public function start(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $driver = $this->driver($request);
        $service->start($driver, $missionNumber);

        return $this->missionResponse(
            $service->detail($driver->fresh(), $missionNumber),
            'Mission démarrée.'
        );
    }

    public function completePickup(
        Request $request,
        string $missionNumber,
        string $stopId,
        DriverMissionService $service
    ): JsonResponse {
        $driver = $this->driver($request);
        $service->completePickup($driver, $missionNumber, $stopId);

        return $this->missionResponse(
            $service->detail($driver->fresh(), $missionNumber),
            'Collecte confirmée.'
        );
    }

    public function stage(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'in:loaded,in_transit,arrived'],
        ]);

        $driver = $this->driver($request);
        $service->updateStage($driver, $missionNumber, $validated['stage']);

        return $this->missionResponse(
            $service->detail($driver->fresh(), $missionNumber),
            'Étape mise à jour.'
        );
    }

    public function location(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $driver = $this->driver($request);
        $result = $service->recordLocation($driver, $missionNumber, $validated);
        $location = $result['location'] ?? null;

        return response()->json([
            'success' => true,
            'recorded_at' => $location?->recorded_at?->toIso8601String(),
            'location' => $location ? [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => is_numeric($location->accuracy) ? (float) $location->accuracy : null,
            ] : null,
            'route' => $this->serializeRoutePlan($result['route_plan'] ?? []),
        ]);
    }

    public function gpsUnavailable(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:permission_denied,no_signal,device_issue,battery_saving,other'],
            'manual_eta_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $service->gpsUnavailable(
            $this->driver($request),
            $missionNumber,
            $validated['reason'],
            filled($validated['manual_eta_at'] ?? null) ? Carbon::parse($validated['manual_eta_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Mode sans GPS activé. Continuez à mettre à jour les étapes manuellement.',
        ]);
    }

    public function incident(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'incident_type' => ['required', 'in:traffic_jam,client_absent,address_issue,vehicle_breakdown,accident,product_damaged,access_impossible,other'],
            'description' => ['nullable', 'string', 'max:1500'],
        ]);

        $driver = $this->driver($request);
        $service->reportIncident(
            $driver,
            $missionNumber,
            $validated['incident_type'],
            $validated['description'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Incident transmis au centre logistique OVANIE.',
        ]);
    }

    public function verifyOtp(Request $request, string $missionNumber, DriverMissionService $service): JsonResponse
    {
        $validated = $request->validate([
            'delivery_otp_code' => ['required', 'digits:6'],
        ]);

        $service->verifyOtp(
            $this->driver($request),
            $missionNumber,
            $validated['delivery_otp_code'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Livraison confirmée.',
        ]);
    }

    private function driver(Request $request): DeliveryDriver
    {
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        return $driver;
    }

    private function missionResponse(array $detail, ?string $message = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'mission' => $this->serializeDetail($detail),
        ], fn ($value) => $value !== null));
    }

    private function serializeSummary(array $mission): array
    {
        $pickup = $mission['pickup_scheduled_at'] ?? null;
        $eta = $mission['estimated_delivery_at'] ?? null;

        return [
            'mission_number' => $mission['mission_number'] ?? null,
            'order_number' => $mission['order_number'] ?? null,
            'client_name' => $mission['client_name'] ?? null,
            'destination' => $mission['destination'] ?? null,
            'destination_label' => $mission['destination_label'] ?? null,
            'commune' => $mission['commune'] ?? null,
            'status' => $mission['status'] ?? null,
            'status_label' => $mission['status_label'] ?? null,
            'pickup_scheduled_at' => $pickup instanceof Carbon ? $pickup->toIso8601String() : null,
            'estimated_delivery_at' => $eta instanceof Carbon ? $eta->toIso8601String() : null,
            'accepted_at' => ($mission['accepted_at'] ?? null) instanceof Carbon
                ? $mission['accepted_at']->toIso8601String() : null,
            'rejected_at' => ($mission['rejected_at'] ?? null) instanceof Carbon
                ? $mission['rejected_at']->toIso8601String() : null,
            'rejection_reason' => $mission['rejection_reason'] ?? null,
            'delivered_at' => ($mission['delivered_at'] ?? null) instanceof Carbon
                ? $mission['delivered_at']->toIso8601String() : null,
            'pickup_count' => (int) ($mission['pickup_count'] ?? 0),
            'item_count' => (int) ($mission['item_count'] ?? 0),
            'line_count' => (int) ($mission['line_count'] ?? 0),
            'total_weight_kg' => (float) ($mission['total_weight_kg'] ?? 0),
            'total_volume_m3' => (float) ($mission['total_volume_m3'] ?? 0),
            'net_amount' => (float) ($mission['net_amount'] ?? 0),
            'vehicle_code' => $mission['vehicle_code'] ?? null,
            'vehicle_label' => $mission['vehicle_label'] ?? null,
            'preparation_percent' => (int) ($mission['preparation_percent'] ?? 0),
            'ready_count' => (int) ($mission['ready_count'] ?? 0),
        ];
    }

    private function serializeDetail(array $detail): array
    {
        $summary = $this->serializeSummary($detail);
        /** @var Collection<int, DeliveryAssignment> $assignments */
        $assignments = collect($detail['assignments'] ?? [])->values();
        $assignmentsByItem = $assignments->keyBy('order_item_id');
        $status = (string) ($detail['status'] ?? 'planned');
        $terminalPickupStatus = in_array($status, ['picked_up', 'in_transit', 'arrived', 'delivered'], true);

        $stops = collect($detail['pickup_stops'] ?? [])->values()->map(function ($stop, int $index) use ($assignmentsByItem, $terminalPickupStatus) {
            $stop = is_array($stop) ? $stop : [];
            $items = collect($stop['items'] ?? [])->values();
            $itemIds = $items->pluck('id')->filter()->values();
            $assignmentRows = $itemIds->map(fn ($itemId) => $assignmentsByItem->get($itemId))->filter();
            $completedTimes = $assignmentRows
                ->map(fn ($assignment) => data_get($assignment->meta, 'pickup_completed_at'))
                ->filter()
                ->map(fn ($value) => Carbon::parse($value));
            $completed = $terminalPickupStatus || (
                $itemIds->isNotEmpty()
                && $assignmentRows->count() === $itemIds->count()
                && $assignmentRows->every(fn ($assignment) => filled(data_get($assignment->meta, 'pickup_completed_at')))
            );
            $shop = $stop['shop'] ?? null;
            $locationParts = collect([
                $shop?->commune,
                $shop?->district ?: $shop?->landmark,
                $shop?->city,
            ])->filter()->map(fn ($value) => trim((string) $value))->unique()->take(2);

            return [
                'id' => (string) ($stop['id'] ?? $shop?->id ?? $index + 1),
                'index' => $index + 1,
                'label' => 'Point ' . ($index + 1),
                'address' => (string) ($stop['address'] ?? 'Adresse de collecte à confirmer'),
                'location_label' => $locationParts->implode(' • ') ?: (string) ($stop['address'] ?? 'Adresse à confirmer'),
                'latitude' => is_numeric($stop['latitude'] ?? null) ? (float) $stop['latitude'] : null,
                'longitude' => is_numeric($stop['longitude'] ?? null) ? (float) $stop['longitude'] : null,
                'weight_kg' => (float) ($stop['weight'] ?? 0),
                'volume_m3' => (float) ($stop['volume'] ?? 0),
                'item_count' => (int) $items->sum(fn ($item) => max(1, (int) ($item->quantity ?? 1))),
                'ready' => (bool) ($stop['ready'] ?? false),
                'completed' => $completed,
                'completed_at' => $completedTimes->sortDesc()->first()?->toIso8601String()
                    ?: ($terminalPickupStatus ? optional($assignmentRows->pluck('picked_up_at')->filter()->sortDesc()->first())->toIso8601String() : null),
                'items' => $items->map(fn ($item) => [
                    'id' => (string) $item->id,
                    'name' => (string) ($item->product?->name ?: 'Article'),
                    'quantity' => max(1, (int) $item->quantity),
                ])->all(),
            ];
        })->values();

        $currentStopId = null;
        if ($status === 'collecting') {
            $currentStopId = data_get($stops->first(fn (array $stop) => ! $stop['completed']), 'id');
        }

        $stops = $stops->map(function (array $stop) use ($currentStopId) {
            $stop['current'] = $currentStopId !== null && (string) $stop['id'] === (string) $currentStopId;
            return $stop;
        })->values();

        $route = $this->serializeRoutePlan($detail['route_plan'] ?? []);
        $mapPoints = $this->serializeGeoPoints($detail['map_points'] ?? [], $stops);
        $route['points'] = $this->serializeGeoPoints(data_get($detail, 'route_plan.points', []), $stops);

        return array_merge($summary, [
            'destination_address' => $detail['destination_address'] ?? $detail['destination'] ?? null,
            'pickup_stops' => $stops->all(),
            'pickup_completed_count' => $stops->where('completed', true)->count(),
            'current_pickup_stop_id' => $currentStopId !== null ? (string) $currentStopId : null,
            'gps_status' => $detail['gps_status'] ?? null,
            'gps_disabled_reason' => $detail['gps_disabled_reason'] ?? null,
            'tracking_phase' => $detail['tracking_phase'] ?? null,
            'route_target' => $detail['route_target'] ?? null,
            'incident_type' => $detail['incident_type'] ?? null,
            'incident_description' => $detail['incident_description'] ?? null,
            'incident_occurred_at' => ($detail['incident_occurred_at'] ?? null) instanceof Carbon
                ? $detail['incident_occurred_at']->toIso8601String() : null,
            'route_plan' => $route,
            'map_points' => $mapPoints,
        ]);
    }

    private function serializeRoutePlan(array $route): array
    {
        return [
            'success' => (bool) ($route['success'] ?? false),
            'provider' => $route['provider'] ?? null,
            'message' => $route['message'] ?? null,
            'geometry' => $route['geometry'] ?? null,
            'distance_km' => is_numeric($route['distance_km'] ?? null) ? (float) $route['distance_km'] : null,
            'duration_minutes' => is_numeric($route['duration_minutes'] ?? null) ? (int) $route['duration_minutes'] : null,
            'traffic_delay_minutes' => is_numeric($route['traffic_delay_minutes'] ?? null) ? (int) $route['traffic_delay_minutes'] : null,
            'arrival_at' => isset($route['arrival_at']) ? Carbon::parse($route['arrival_at'])->toIso8601String() : null,
            'calculated_at' => isset($route['calculated_at']) ? Carbon::parse($route['calculated_at'])->toIso8601String() : null,
            'route_phase' => $route['route_phase'] ?? null,
            'points' => [],
        ];
    }

    private function serializeGeoPoints(iterable $points, Collection $stops): array
    {
        $labelsById = $stops->keyBy(fn (array $stop) => (string) $stop['id']);

        return collect($points)->map(function ($point) use ($labelsById) {
            $point = is_array($point) ? $point : [];
            if (! is_numeric($point['latitude'] ?? null) || ! is_numeric($point['longitude'] ?? null)) {
                return null;
            }

            $id = (string) ($point['id'] ?? 'point');
            $type = (string) ($point['type'] ?? 'point');
            $pickup = $labelsById->get($id);
            $name = match ($type) {
                'driver' => 'Votre position',
                'destination' => 'Livraison client',
                'pickup' => $pickup['label'] ?? 'Point de collecte',
                default => (string) ($point['name'] ?? 'Point'),
            };

            return [
                'id' => $id,
                'type' => $type,
                'name' => $name,
                'address' => $type === 'pickup'
                    ? ($pickup['location_label'] ?? $point['address'] ?? '')
                    : (string) ($point['address'] ?? ''),
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'completed' => $type === 'pickup'
                    ? (bool) ($pickup['completed'] ?? false)
                    : (bool) ($point['completed'] ?? false),
            ];
        })->filter()->values()->all();
    }
}
