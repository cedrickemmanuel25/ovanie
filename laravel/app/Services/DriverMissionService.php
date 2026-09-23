<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryIncident;
use App\Models\DeliveryDriver;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Notifications\Delivery\DeliveryIncidentNotification;
use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\DriverTrackingService;
use App\Services\Geo\MissionRoutingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverMissionService
{
    public function __construct(
        private readonly OvanieShipmentConsolidationService $consolidation,
        private readonly MissionRoutingService $routing,
        private readonly DriverTrackingService $tracking,
        private readonly DeliveryCoordinateService $coordinates,
        private readonly OrderWorkflowService $workflow,
        private readonly OvanieNotificationDispatcher $notifications,
        private readonly DeliveryNotificationService $deliveryNotifications,
    ) {
    }

    public function listForDriver(DeliveryDriver $driver): Collection
    {
        return DeliveryAssignment::query()
            ->with(['order.client', 'orderItem.product.shop', 'orderItem.shipment', 'driver', 'latestLocation'])
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->get()
            ->groupBy(fn (DeliveryAssignment $assignment) => $assignment->resolved_mission_number)
            ->map(fn (Collection $assignments, string $mission) => $this->summary($driver, $mission, $assignments))
            ->sortByDesc(fn (array $mission) => $mission['sort_at'])
            ->values();
    }

    public function detail(DeliveryDriver $driver, string $missionNumber): array
    {
        $assignments = $this->assignments($driver, $missionNumber);

        if ($assignments->isEmpty()) {
            abort(404, 'Mission introuvable.');
        }

        $latest = $this->latestPerItem($assignments);
        $representative = $latest->first()?->orderItem;

        abort_unless($representative, 404, 'Mission sans article.');

        $group = $this->consolidation->groupForItem($representative);
        $order = $representative->order()->with('client')->first();
        $missionSummary = $this->summary($driver, $missionNumber, $assignments);
        $allPickupMarkers = collect($group['pickup_stops'] ?? [])->map(fn (array $stop) => [
            'id' => $stop['id'] ?? $stop['shop']?->id,
            'name' => $stop['shop']?->name ?: 'Point de collecte',
            'address' => $stop['address'] ?? 'Adresse non renseignée',
            'latitude' => $stop['latitude'] ?? null,
            'longitude' => $stop['longitude'] ?? null,
            'weight' => $stop['weight'] ?? 0,
            'volume' => $stop['volume'] ?? 0,
        ])->values();

        $missionStatus = strtolower(trim((string) ($missionSummary['status'] ?? '')));
        // Une mission acceptée mais pas encore démarrée reste une réservation :
        // le suivi GPS vers le vendeur commence uniquement quand le livreur
        // appuie réellement sur « Démarrer les collectes ».
        $pickupLegActive = $missionStatus === 'collecting';
        $deliveryLegActive = in_array($missionStatus, ['picked_up', 'in_transit', 'arrived', 'delivered'], true);
        $waitingLeg = in_array($missionStatus, ['offered', 'assigned', 'planned', 'accepted'], true);
        // Mission terminée : le trajet ne dépend plus d'une position GPS
        // récente puisque la livraison a déjà eu lieu, on rejoue simplement
        // le parcours collectes -> client pour l'écran d'historique.
        $archivedLeg = $missionStatus === 'delivered';
        $pickupsCompleted = $deliveryLegActive;
        $pickupMarkers = ($pickupLegActive || $archivedLeg) ? $allPickupMarkers->all() : [];

        $destination = $this->coordinates->forOrder($order);
        $destinationLat = $destination['latitude'];
        $destinationLng = $destination['longitude'];
        $driverPoint = [
            'name' => $driver->name,
            'destination_address' => $this->deliveryAddress($order),
        ];
        $latestMissionLocation = $latest
            ->pluck('latestLocation')
            ->filter()
            ->sortByDesc(fn ($location) => optional($location->recorded_at)->timestamp ?: 0)
            ->first();

        $lastSeenAt = $latestMissionLocation?->recorded_at;
        $hasRecentPosition = ($pickupLegActive || $deliveryLegActive)
            && $latestMissionLocation
            && $lastSeenAt
            && $lastSeenAt->diffInSeconds(now()) <= (int) config('delivery.gps_weak_seconds', 180)
            && is_numeric($latestMissionLocation->latitude)
            && is_numeric($latestMissionLocation->longitude);

        if ($hasRecentPosition) {
            $driverPoint['latitude'] = (float) $latestMissionLocation->latitude;
            $driverPoint['longitude'] = (float) $latestMissionLocation->longitude;
        }

        $routePlan = [
            'success' => false,
            'provider' => null,
            'message' => ($pickupLegActive || $deliveryLegActive)
                ? 'Le tracé sera calculé dès qu’une position GPS récente sera reçue.'
                : 'Acceptez la mission pour activer le calcul du trajet.',
            'geometry' => null,
            'distance_km' => null,
            'duration_minutes' => null,
            'traffic_delay_minutes' => null,
            'points' => [],
            'route_phase' => $pickupLegActive ? 'pickup' : ($deliveryLegActive ? 'delivery' : 'waiting'),
        ];

        if ($hasRecentPosition) {
            $routePlan = $this->routing->build(
                $pickupMarkers,
                is_numeric($destinationLat) ? (float) $destinationLat : null,
                is_numeric($destinationLng) ? (float) $destinationLng : null,
                (string) ($group['vehicle_code'] ?? 'pickup'),
                $driverPoint,
                [
                    'include_destination' => $deliveryLegActive,
                    'require_driver' => true,
                    'phase' => $pickupLegActive ? 'pickup' : 'delivery',
                ]
            );
        }

        // Avant l'acceptation, l'application mobile affiche déjà un aperçu
        // cartographique de la mission. Le tracé reste un vrai itinéraire
        // routier calculé par le même service (collectes -> client), sans
        // inventer de segment droit ni exposer la position du livreur.
        if ($waitingLeg) {
            $routePlan = $this->routing->build(
                $allPickupMarkers->all(),
                is_numeric($destinationLat) ? (float) $destinationLat : null,
                is_numeric($destinationLng) ? (float) $destinationLng : null,
                (string) ($group['vehicle_code'] ?? 'pickup'),
                $driverPoint,
                [
                    'include_destination' => true,
                    'require_driver' => false,
                    'phase' => 'preview',
                ]
            );
        }

        // Mission livrée (écran Historique) : on rejoue le trajet réellement
        // parcouru (collectes -> client) à partir des coordonnées connues,
        // sans dépendre d'une position GPS live qui n'existe plus.
        if ($archivedLeg) {
            $routePlan = $this->routing->build(
                $allPickupMarkers->all(),
                is_numeric($destinationLat) ? (float) $destinationLat : null,
                is_numeric($destinationLng) ? (float) $destinationLng : null,
                (string) ($group['vehicle_code'] ?? 'pickup'),
                $driverPoint,
                [
                    'include_destination' => true,
                    'require_driver' => false,
                    'phase' => 'completed',
                ]
            );
        }

        $mapPoints = collect();
        if (($pickupLegActive || $deliveryLegActive) && isset($driverPoint['latitude'], $driverPoint['longitude'])) {
            $mapPoints->push([
                'id' => 'driver',
                'type' => 'driver',
                'name' => $driverPoint['name'],
                'address' => 'Position actuelle',
                'latitude' => $driverPoint['latitude'],
                'longitude' => $driverPoint['longitude'],
            ]);
        }
        $allPickupMarkers->each(function (array $stop) use ($mapPoints, $pickupsCompleted) {
            if (! is_numeric($stop['latitude'] ?? null) || ! is_numeric($stop['longitude'] ?? null)) {
                return;
            }
            $mapPoints->push(array_merge($stop, [
                'type' => 'pickup',
                'completed' => $pickupsCompleted,
                'context_only' => $pickupsCompleted,
            ]));
        });
        if (($deliveryLegActive || $waitingLeg) && is_numeric($destinationLat) && is_numeric($destinationLng)) {
            $mapPoints->push([
                'id' => 'destination',
                'type' => 'destination',
                'name' => 'Destination client',
                'address' => $this->deliveryAddress($order),
                'latitude' => (float) $destinationLat,
                'longitude' => (float) $destinationLng,
            ]);
        }

        $latestIncident = DeliveryIncident::query()
            ->whereIn('order_item_id', $latest->pluck('order_item_id')->filter()->all())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('occurred_at')
            ->first();

        return array_merge($missionSummary, [
            'assignments' => $latest,
            'items' => $group['items'] ?? collect(),
            'pickup_stops' => collect($group['pickup_stops'] ?? []),
            'route_plan' => $routePlan,
            'tracking_phase' => $pickupLegActive ? 'to_pickup' : ($deliveryLegActive ? 'to_customer' : 'waiting_start'),
            'route_target' => $pickupLegActive ? 'pickup' : ($deliveryLegActive ? 'destination' : null),
            'map_points' => $mapPoints->values()->all(),
            'order' => $order,
            'destination_address' => $this->deliveryAddress($order),
            'incident_type' => $latestIncident?->incident_type,
            'incident_description' => $latestIncident?->description,
            'incident_occurred_at' => $latestIncident?->occurred_at,
        ]);
    }

    public function accept(DeliveryDriver $driver, string $missionNumber): void
    {
        $assignments = $this->assignments($driver, $missionNumber);
        abort_if($assignments->isEmpty(), 404);

        $latest = $this->latestPerItem($assignments);
        $representative = $latest->first()?->orderItem;
        $group = $representative ? $this->consolidation->groupForItem($representative) : [];
        $itemIds = collect($group['items'] ?? [])
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            $itemIds = $latest->pluck('order_item_id')->filter()->unique()->values();
        }

        if ($itemIds->isEmpty()) {
            throw ValidationException::withMessages([
                'mission' => 'Cette mission ne contient aucun article réservable.',
            ]);
        }

        DB::transaction(function () use ($driver, $missionNumber, $itemIds) {
            $items = OrderItem::query()
                ->whereIn('id', $itemIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'mission' => 'Cette mission n’est plus disponible.',
                ]);
            }

            $allowedDeliveryStatuses = [
                OrderWorkflowService::DELIVERY_PENDING,
                OrderWorkflowService::DELIVERY_PREPARING,
                OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                OrderWorkflowService::DELIVERY_ASSIGNED,
            ];

            if ($items->contains(fn (OrderItem $item) => ! in_array($item->delivery_status, $allowedDeliveryStatuses, true))) {
                throw ValidationException::withMessages([
                    'mission' => 'Cette mission n’est plus disponible.',
                ]);
            }

            // Réservation atomique de toute la mission consolidée : un autre
            // livreur ne peut pas gagner une autre ligne de la même mission
            // pendant que cette acceptation est en cours.
            $winnerStatuses = [
                'accepted', 'assigned', 'collecting', 'picked_up',
                'in_transit', 'arrived', 'delivered',
            ];

            $reservedByAnotherDriver = DeliveryAssignment::query()
                ->whereIn('order_item_id', $itemIds->all())
                ->where('driver_id', '!=', $driver->id)
                ->whereIn('status', $winnerStatuses)
                ->orderBy('id')
                ->lockForUpdate()
                ->first() !== null;

            if ($reservedByAnotherDriver) {
                DeliveryAssignment::query()
                    ->where('driver_id', $driver->id)
                    ->whereIn('order_item_id', $itemIds->all())
                    ->where('status', 'offered')
                    ->update(['status' => 'offer_expired']);

                throw ValidationException::withMessages([
                    'mission' => 'Cette mission a déjà été réservée par un autre livreur.',
                ]);
            }

            $driverOffers = DeliveryAssignment::query()
                ->where('driver_id', $driver->id)
                ->whereIn('order_item_id', $itemIds->all())
                ->where(function ($query) use ($missionNumber) {
                    $query->where('mission_number', $missionNumber)
                        ->orWhere('meta->mission_number', $missionNumber)
                        ->orWhereNull('mission_number');
                })
                ->whereIn('status', ['offered', 'assigned', 'planned', 'accepted'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($driverOffers->isEmpty()
                || $driverOffers->pluck('order_item_id')->filter()->unique()->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'mission' => 'Cette offre n’est pas complète ou n’est plus disponible pour votre compte. Actualisez vos missions.',
                ]);
            }

            // Toutes les offres concurrentes de toutes les lignes expirent en
            // une seule transaction. Le premier livreur validé réserve donc la
            // mission complète et pas seulement un produit.
            DeliveryAssignment::query()
                ->whereIn('order_item_id', $itemIds->all())
                ->where('driver_id', '!=', $driver->id)
                ->where('status', 'offered')
                ->update(['status' => 'offer_expired']);

            foreach ($driverOffers as $offer) {
                $meta = is_array($offer->meta) ? $offer->meta : [];
                $meta['mission_number'] = $missionNumber;
                $meta['reservation_state'] = 'reserved_waiting_vendor';
                $meta['reserved_at'] = now()->toIso8601String();

                $offer->forceFill([
                    'mission_number' => $missionNumber,
                    'status' => 'accepted',
                    'accepted_at' => $offer->accepted_at ?: now(),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'meta' => $meta,
                ])->save();
            }

            // Accepter = réserver. Aucun article n'est marqué "assigned" ici.
            // Le statut physique de livraison avance uniquement quand le
            // livreur démarre réellement les collectes après préparation 100 %.
        });
    }

    public function reject(DeliveryDriver $driver, string $missionNumber, ?string $reason): void
    {
        $assignments = $this->assignments($driver, $missionNumber);
        abort_if($assignments->isEmpty(), 404);

        $this->latestPerItem($assignments)->each(fn (DeliveryAssignment $assignment) =>
            $assignment->forceFill([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save()
        );

        $driver->forceFill(['status' => 'Disponible'])->save();
    }

    public function start(DeliveryDriver $driver, string $missionNumber): void
    {
        $detail = $this->detail($driver, $missionNumber);

        if (($detail['status'] ?? null) !== 'accepted') {
            throw ValidationException::withMessages([
                'mission' => 'Cette mission doit d’abord être réservée avant de démarrer les collectes.',
            ]);
        }

        if (! ($detail['can_start'] ?? false)) {
            throw ValidationException::withMessages([
                'mission' => 'La mission ne peut pas démarrer tant que tous les points de collecte ne sont pas prêts.',
            ]);
        }

        DB::transaction(function () use ($detail, $driver) {
            foreach ($detail['items'] as $item) {
                if ($item->delivery_status === OrderWorkflowService::DELIVERY_READY_FOR_PICKUP) {
                    $this->workflow->setDeliveryStatus(
                        $item,
                        OrderWorkflowService::DELIVERY_ASSIGNED,
                        null,
                        'logistics',
                        'Mission acceptée par le livreur OVANIE.',
                        [
                            'driver_name' => $driver->name,
                            'driver_phone' => $driver->phone,
                            'suppress_notifications' => true,
                        ]
                    );
                }
            }

            $detail['assignments']->each(fn (DeliveryAssignment $assignment) =>
                $assignment->forceFill([
                    'status' => 'collecting',
                    'accepted_at' => $assignment->accepted_at ?: now(),
                    'started_at' => $assignment->started_at ?: now(),
                ])->save()
            );

            $driver->forceFill([
                'status' => 'En livraison',
                'is_online' => true,
                'last_seen_at' => now(),
            ])->save();
        });
    }

    public function completePickup(
        DeliveryDriver $driver,
        string $missionNumber,
        string $stopId,
        string $action,
        array $payload = []
    ): void {
        $detail = $this->detail($driver, $missionNumber);

        if (($detail['status'] ?? null) !== 'collecting') {
            throw ValidationException::withMessages([
                'pickup' => 'Les étapes de collecte ne sont disponibles que lorsqu’une mission est en cours de collecte.',
            ]);
        }

        $stops = collect($detail['pickup_stops'] ?? [])->values();
        $targetIndex = $stops->search(fn ($stop) => (string) data_get($stop, 'id') === (string) $stopId);

        if ($targetIndex === false) {
            throw ValidationException::withMessages(['pickup' => 'Point de collecte introuvable.']);
        }

        $assignmentsByItem = $detail['assignments']->keyBy('order_item_id');

        // Le livreur suit les points dans l'ordre calculé par OVANIE Logistics.
        for ($index = 0; $index < $targetIndex; $index++) {
            $previous = $stops->get($index);
            if (is_array($previous) && ! $this->pickupStopCompleted($previous, $assignmentsByItem)) {
                throw ValidationException::withMessages([
                    'pickup' => 'Terminez d’abord le point de collecte précédent.',
                ]);
            }
        }

        $target = $stops->get($targetIndex);
        $items = collect(data_get($target, 'items', []));
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['pickup' => 'Aucun article n’est rattaché à ce point de collecte.']);
        }

        $assignmentRows = $items
            ->map(fn ($item) => $assignmentsByItem->get($item->id))
            ->filter()
            ->values();

        if ($assignmentRows->count() !== $items->count()) {
            throw ValidationException::withMessages([
                'pickup' => 'La mission n’est pas complètement rattachée à ce point de collecte. Actualisez la mission avant de continuer.',
            ]);
        }

        $wasArrived = $assignmentRows->every(fn ($assignment) => filled(data_get($assignment->meta, 'pickup_arrived_at')));
        $wasVerified = $assignmentRows->every(fn ($assignment) => filled(data_get($assignment->meta, 'pickup_verified_at')));
        $wasCompleted = $assignmentRows->every(fn ($assignment) => filled(data_get($assignment->meta, 'pickup_completed_at')));

        if ($wasCompleted) {
            return;
        }

        if ($action === 'verified') {
            if (! $wasArrived) {
                throw ValidationException::withMessages([
                    'pickup' => 'Confirmez d’abord votre arrivée chez le vendeur.',
                ]);
            }

            foreach (['items_checked', 'quantities_checked', 'condition_checked'] as $field) {
                if (! filter_var($payload[$field] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    throw ValidationException::withMessages([
                        $field => 'Toutes les vérifications doivent être confirmées avant le chargement.',
                    ]);
                }
            }
        }

        if ($action === 'loaded') {
            if (! $wasVerified) {
                throw ValidationException::withMessages([
                    'pickup' => 'Vérifiez d’abord les articles, les quantités et leur état avant de confirmer le chargement.',
                ]);
            }

            if (! filter_var($payload['handover_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'handover_confirmed' => 'Confirmez que le vendeur vous a effectivement remis les articles.',
                ]);
            }

            $expectedCodes = $assignmentRows
                ->map(fn ($assignment) => (string) data_get($assignment->meta, 'pickup_handover_code'))
                ->filter()
                ->unique()
                ->values();
            $enteredCode = trim((string) ($payload['pickup_code'] ?? ''));

            if ($expectedCodes->count() !== 1 || $enteredCode === '' || ! hash_equals((string) $expectedCodes->first(), $enteredCode)) {
                throw ValidationException::withMessages([
                    'pickup_code' => 'Le code de remise est incorrect. Demandez au vendeur le code affiché dans son espace OVANIE.',
                ]);
            }
        }

        $handoverCode = $assignmentRows
            ->map(fn ($assignment) => (string) data_get($assignment->meta, 'pickup_handover_code'))
            ->filter()
            ->first();
        if ($action === 'arrived' && ! filled($handoverCode)) {
            $handoverCode = (string) random_int(100000, 999999);
        }

        DB::transaction(function () use ($assignmentRows, $stopId, $action, $payload, $handoverCode) {
            foreach ($assignmentRows as $assignment) {
                $meta = is_array($assignment->meta) ? $assignment->meta : [];
                $meta['pickup_stop_id'] = (string) $stopId;

                if ($action === 'arrived') {
                    $meta['pickup_arrived_at'] = $meta['pickup_arrived_at'] ?? now()->toIso8601String();
                    $meta['pickup_handover_code'] = $meta['pickup_handover_code'] ?? $handoverCode;
                }

                if ($action === 'verified') {
                    $meta['pickup_verified_at'] = $meta['pickup_verified_at'] ?? now()->toIso8601String();
                    $meta['pickup_items_checked'] = true;
                    $meta['pickup_quantities_checked'] = true;
                    $meta['pickup_condition_checked'] = true;
                    if (filled($payload['notes'] ?? null)) {
                        $meta['pickup_verification_notes'] = trim((string) $payload['notes']);
                    }
                }

                if ($action === 'loaded') {
                    $meta['pickup_loaded_at'] = $meta['pickup_loaded_at'] ?? now()->toIso8601String();
                    $meta['vendor_handover_confirmed_at'] = $meta['vendor_handover_confirmed_at'] ?? now()->toIso8601String();
                    $meta['pickup_completed_at'] = $meta['pickup_completed_at'] ?? now()->toIso8601String();
                    if (filled($payload['notes'] ?? null)) {
                        $meta['pickup_handover_notes'] = trim((string) $payload['notes']);
                    }
                }

                $assignment->forceFill([
                    'meta' => $meta,
                    'last_manual_status_at' => now(),
                ])->save();
            }
        });

        $shop = data_get($target, 'shop');
        $vendor = $shop?->user;
        $order = $items->first()?->order;
        $orderNumber = $order?->order_number ?: ($order?->id ? 'Commande #' . $order->id : 'la commande');
        $vendorUrl = $order?->id ? route('vendor.orders.show', $order->id) : null;

        if ($action === 'arrived' && ! $wasArrived && $vendor) {
            $this->notifications->send(
                $vendor,
                'deliveries',
                'Livreur arrivé pour la collecte',
                "Le livreur partenaire {$driver->name} est arrivé pour récupérer {$orderNumber}. Vérifiez le chargement puis communiquez le code de remise uniquement lorsque les articles lui ont été remis.",
                ['order_id' => $order?->id, 'url' => $vendorUrl],
                ['in_app', 'push']
            );
        }

        if ($action === 'loaded' && ! $wasCompleted && $vendor) {
            $this->notifications->send(
                $vendor,
                'deliveries',
                'Remise à OVANIE Logistics confirmée',
                "La remise de {$orderNumber} au livreur partenaire a été confirmée. OVANIE Logistics prend maintenant en charge la suite de la livraison.",
                ['order_id' => $order?->id, 'url' => $vendorUrl],
                ['in_app', 'push']
            );
        }
    }

    public function updateStage(DeliveryDriver $driver, string $missionNumber, string $stage): void
    {
        $detail = $this->detail($driver, $missionNumber);
        $current = (string) ($detail['status'] ?? 'planned');
        $next = match ($current) {
            'collecting' => 'loaded',
            'picked_up' => 'in_transit',
            'in_transit' => 'arrived',
            default => null,
        };

        if ($stage !== $next) {
            throw ValidationException::withMessages([
                'stage' => $next
                    ? "L’étape attendue est « {$this->statusLabel($next === 'loaded' ? 'picked_up' : $next)} »."
                    : 'Aucune transition n’est disponible pour l’état actuel de la mission.',
            ]);
        }

        // Sécurité serveur : l'application ne peut pas faire passer une mission
        // de collecte à chargée tant que chaque vendeur n'a pas réellement
        // terminé sa remise (arrivée + vérification + code de remise).
        if ($stage === 'loaded') {
            $assignmentsByItem = $detail['assignments']->keyBy('order_item_id');
            $stops = collect($detail['pickup_stops'] ?? []);
            $allStopsCompleted = $stops->isNotEmpty()
                && $stops->every(fn (array $stop) => $this->pickupStopCompleted($stop, $assignmentsByItem));

            if (! $allStopsCompleted) {
                throw ValidationException::withMessages([
                    'stage' => 'Tous les points vendeurs doivent être collectés et confirmés avant de partir vers le client.',
                ]);
            }
        }

        DB::transaction(function () use ($detail, $driver, $stage) {
            foreach ($detail['items'] as $item) {
                $item->refresh();

                if ($stage === 'loaded' && $item->delivery_status === OrderWorkflowService::DELIVERY_ASSIGNED) {
                    $this->workflow->setDeliveryStatus(
                        $item,
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        null,
                        'logistics',
                        'Chargement confirmé par le livreur.',
                        ['suppress_notifications' => true]
                    );
                }

                if ($stage === 'in_transit') {
                    if ($item->delivery_status === OrderWorkflowService::DELIVERY_ASSIGNED) {
                        $this->workflow->setDeliveryStatus(
                            $item,
                            OrderWorkflowService::DELIVERY_PICKED_UP,
                            null,
                            'logistics',
                            'Chargement confirmé par le livreur.',
                            ['suppress_notifications' => true]
                        );
                        $item->refresh();
                    }
                    if ($item->delivery_status === OrderWorkflowService::DELIVERY_PICKED_UP) {
                        $this->workflow->setDeliveryStatus(
                            $item,
                            OrderWorkflowService::DELIVERY_IN_TRANSIT,
                            null,
                            'logistics',
                            'Le livreur est en route vers le client.',
                            ['suppress_notifications' => true]
                        );
                    }
                }
            }

            if ($stage === 'in_transit') {
                $this->ensureGroupOtp($detail['items']);
            }

            $assignmentStatus = match ($stage) {
                'loaded' => 'picked_up',
                'in_transit' => 'in_transit',
                'arrived' => 'arrived',
                default => 'collecting',
            };

            $detail['assignments']->each(function (DeliveryAssignment $assignment) use ($assignmentStatus, $stage) {
                $meta = is_array($assignment->meta) ? $assignment->meta : [];

                if ($stage === 'loaded') {
                    $meta['all_pickups_completed_at'] = $meta['all_pickups_completed_at'] ?? now()->toIso8601String();
                }
                if ($stage === 'in_transit') {
                    $meta['delivery_departed_at'] = $meta['delivery_departed_at'] ?? now()->toIso8601String();
                }
                if ($stage === 'arrived') {
                    $meta['customer_arrived_at'] = $meta['customer_arrived_at'] ?? now()->toIso8601String();
                }

                $assignment->forceFill(array_filter([
                    'status' => $assignmentStatus,
                    'started_at' => $assignment->started_at ?: now(),
                    'picked_up_at' => $stage === 'loaded' ? ($assignment->picked_up_at ?: now()) : null,
                    'arrived_at' => $stage === 'arrived' ? ($assignment->arrived_at ?: now()) : null,
                    'meta' => $meta,
                ], fn ($value) => $value !== null))->save();
            });

            $driver->forceFill([
                'status' => 'En livraison',
                'is_online' => true,
                'last_seen_at' => now(),
            ])->save();
        });

        $first = $detail['items']->first();
        if ($first && $stage === 'loaded') {
            $this->deliveryNotifications->notifyGroup(
                $first->refresh(),
                'picked_up',
                'Chargement terminé',
                'Les articles de cette livraison ont été chargés. Le départ vers votre adresse sera confirmé prochainement.'
            );
        }
        if ($first && $stage === 'in_transit') {
            $this->deliveryNotifications->notifyGroup(
                $first->refresh(),
                'in_transit',
                'Livreur en route',
                'Votre livraison est en route. Votre code de confirmation a été généré : ne le communiquez qu’après réception complète de tous les articles.',
                'Votre livraison OVANIE est en route.'
            );
        }
        if ($first && $stage === 'arrived') {
            $this->deliveryNotifications->notifyGroup(
                $first->refresh(),
                'arrived',
                'Votre livreur est arrivé',
                'Le livreur partenaire OVANIE est arrivé à votre adresse. Vérifiez la remise complète des articles puis communiquez votre code à 6 chiffres.',
                'Votre livreur OVANIE est arrivé.'
            );
        }
    }

    public function recordLocation(DeliveryDriver $driver, string $missionNumber, array $payload): array
    {
        $detail = $this->detail($driver, $missionNumber);
        $recordedAt = now();
        $locations = collect();

        DB::transaction(function () use ($detail, $driver, $missionNumber, $payload, $recordedAt, $locations) {
            $assignmentsByItem = $detail['assignments']->keyBy('order_item_id');
            $writtenShipmentIds = collect();

            foreach ($detail['items'] as $item) {
                $assignment = $assignmentsByItem->get($item->id);
                $shipmentId = $item->shipment_id ?: $item->shipment?->id;

                // Une même mission peut contenir plusieurs expéditions. Chaque
                // expédition reçoit la position afin que tous les écrans lisent
                // exactement le même point GPS.
                if ($shipmentId && $writtenShipmentIds->contains((int) $shipmentId)) {
                    continue;
                }

                $location = $this->tracking->storeLocation($driver, [
                    'delivery_assignment_id' => $assignment?->id,
                    'mission_number' => $missionNumber,
                    'shipment_id' => $shipmentId,
                    'order_id' => $item->order_id,
                    'latitude' => $payload['latitude'],
                    'longitude' => $payload['longitude'],
                    'accuracy' => $payload['accuracy'] ?? null,
                    'speed' => $payload['speed'] ?? null,
                    'heading' => $payload['heading'] ?? null,
                    'battery_level' => $payload['battery_level'] ?? null,
                    'recorded_at' => $recordedAt,
                ]);

                $locations->push($location);
                if ($shipmentId) {
                    $writtenShipmentIds->push((int) $shipmentId);
                }
            }

            // Compatibilité avec les anciennes commandes qui n'ont pas encore
            // d'enregistrement shipment.
            if ($locations->isEmpty() && ($item = $detail['items']->first())) {
                $assignment = $assignmentsByItem->get($item->id);
                $locations->push($this->tracking->storeLocation($driver, [
                    'delivery_assignment_id' => $assignment?->id,
                    'mission_number' => $missionNumber,
                    'shipment_id' => null,
                    'order_id' => $item->order_id,
                    'latitude' => $payload['latitude'],
                    'longitude' => $payload['longitude'],
                    'accuracy' => $payload['accuracy'] ?? null,
                    'speed' => $payload['speed'] ?? null,
                    'heading' => $payload['heading'] ?? null,
                    'battery_level' => $payload['battery_level'] ?? null,
                    'recorded_at' => $recordedAt,
                ]));
            }

            $stableLocation = $locations->first();
            OrderItem::query()
                ->whereIn('id', $detail['items']->pluck('id'))
                ->update([
                    'driver_latitude' => $stableLocation?->latitude ?? $payload['latitude'],
                    'driver_longitude' => $stableLocation?->longitude ?? $payload['longitude'],
                    'driver_location_updated_at' => $recordedAt,
                ]);

            $detail['assignments']->each(fn (DeliveryAssignment $assignment) =>
                $assignment->forceFill([
                    'gps_status' => 'active',
                    'gps_last_seen_at' => $recordedAt,
                    'gps_disabled_reason' => null,
                ])->save()
            );
        });

        $freshDetail = $this->detail($driver->fresh(), $missionNumber);
        $routePlan = $freshDetail['route_plan'] ?? [];

        // L'espace Logistique lisait un tracé statique (boutique -> client,
        // calculé une seule fois à la création de la commande) totalement
        // différent du tracé réel affiché dans l'app livreur (calculé en
        // direct depuis sa position GPS, et qui change de sens selon qu'il va
        // chercher le colis ou qu'il le livre). Les deux espaces doivent
        // montrer le même trajet : on persiste donc ici le tracé réellement
        // utilisé par le livreur, pour que la logistique affiche exactement
        // la même chose.
        if ($routePlan['success'] ?? false) {
            $shipmentIds = $freshDetail['items']->pluck('shipment_id')->filter()->unique();
            if ($shipmentIds->isEmpty()) {
                $shipmentIds = $freshDetail['items']->map(fn ($item) => $item->shipment?->id)->filter()->unique();
            }
            Shipment::query()->whereIn('id', $shipmentIds)->update([
                'route_geometry' => is_array($routePlan['geometry'] ?? null)
                    ? json_encode($routePlan['geometry'])
                    : ($routePlan['geometry'] ?? null),
                'distance_km' => $routePlan['distance_km'] ?? null,
                'duration_minutes' => $routePlan['duration_minutes'] ?? null,
                'routing_provider' => $routePlan['provider'] ?? null,
                'routed_at' => now(),
            ]);
        }

        return [
            'location' => $locations->first(),
            'route_plan' => $routePlan,
        ];
    }

    public function gpsUnavailable(DeliveryDriver $driver, string $missionNumber, string $reason, ?Carbon $manualEta = null): void
    {
        $detail = $this->detail($driver, $missionNumber);
        $detail['assignments']->each(fn (DeliveryAssignment $assignment) =>
            $assignment->forceFill([
                'gps_status' => 'unavailable',
                'gps_disabled_reason' => $reason,
                'manual_eta_at' => $manualEta ?: $assignment->manual_eta_at ?: $assignment->estimated_delivery_at,
                'last_manual_status_at' => now(),
            ])->save()
        );
    }

    public function reportIncident(
        DeliveryDriver $driver,
        string $missionNumber,
        string $type,
        ?string $description = null
    ): void {
        $detail = $this->detail($driver, $missionNumber);
        $item = $detail['items']->first();

        $canonicalType = match ($type) {
            'traffic_jam' => 'blocage_route',
            'client_absent' => 'client_absent',
            'address_issue' => 'adresse_introuvable',
            'vehicle_breakdown' => 'panne_vehicule',
            'accident' => 'accident',
            'product_damaged' => 'produit_endommage',
            'access_impossible' => 'acces_chantier_difficile',
            'delivery_refused' => 'refus_reception',
            default => 'autre_incident',
        };

        $responsibility = match ($canonicalType) {
            'panne_vehicule' => 'vehicle',
            'client_absent', 'refus_reception' => 'client',
            'blocage_route', 'acces_chantier_difficile' => 'transport',
            default => 'unknown',
        };

        $impactLevel = match ($canonicalType) {
            'panne_vehicule', 'accident', 'produit_endommage', 'refus_reception' => 'blocked',
            'client_absent' => 'rescheduled',
            'blocage_route', 'adresse_introuvable', 'acces_chantier_difficile' => 'delay',
            default => 'delay',
        };
        $impactLabel = match ($impactLevel) {
            'blocked' => 'Livraison interrompue',
            'rescheduled' => 'Livraison à reprogrammer',
            'delay' => 'Retard probable',
            default => 'Aucun impact confirmé',
        };
        $deliveryInterrupted = in_array($impactLevel, ['blocked', 'rescheduled'], true);

        $incident = DB::transaction(function () use ($detail, $driver, $canonicalType, $responsibility, $description, $item, $missionNumber, $impactLevel, $impactLabel, $deliveryInterrupted) {
            $incident = DeliveryIncident::create([
                'order_id' => $item?->order_id,
                'order_item_id' => $item?->id,
                'shipment_id' => $item?->shipment_id,
                'reported_by_type' => 'ovanie_driver',
                'reported_by_id' => $driver->id,
                'incident_type' => $canonicalType,
                'severity' => in_array($canonicalType, ['accident', 'panne_vehicule', 'produit_endommage'], true) ? 'high' : 'medium',
                'responsibility' => $responsibility,
                'description' => $description ?: 'Incident signalé depuis l’application du livreur OVANIE.',
                'latitude' => $driver->latitude,
                'longitude' => $driver->longitude,
                'occurred_at' => now(),
                'status' => $impactLevel === 'rescheduled' ? 'rescheduled' : 'open',
                'next_action' => $deliveryInterrupted
                    ? 'Le centre logistique doit organiser la reprise de la livraison.'
                    : 'Le centre logistique contrôle l’impact sur l’ETA.',
                'meta' => [
                    'signal_source' => 'ovanie_driver_app',
                    'signal_source_label' => 'Application du livreur OVANIE',
                    'source_actor_name' => $driver->name,
                    'source_actor_phone' => $driver->phone,
                    'mission_number' => $missionNumber,
                    'source_received_at' => now()->toIso8601String(),
                    'gps_source' => 'driver_profile',
                    'gps_recorded_at' => $driver->last_seen_at?->toIso8601String(),
                    'impact_level' => $impactLevel,
                    'impact_label' => $impactLabel,
                    'delivery_interrupted' => $deliveryInterrupted,
                    'customer_notification_required' => true,
                    'delivery_status_before_incident' => $item?->delivery_status,
                    'driver_name' => $driver->name,
                    'driver_phone' => $driver->phone,
                    'driver_vehicle' => $driver->vehicle,
                    'driver_zone' => $driver->zone,
                    'driver_online' => $driver->is_online,
                ],
            ]);

            // Un retard simple ne doit pas immobiliser la mission. Seuls les
            // incidents réellement bloquants/reprogrammés basculent les
            // affectations en statut `incident`. On conserve le statut
            // précédent dans les métadonnées pour permettre une reprise sûre.
            $previousAssignmentStatuses = [];
            foreach ($detail['assignments'] as $assignment) {
                $previousAssignmentStatuses[(string) $assignment->id] = (string) $assignment->status;
                $assignmentMeta = is_array($assignment->meta) ? $assignment->meta : [];
                $assignmentMeta['active_incident_id'] = $incident->id;
                $assignmentMeta['incident_impact_level'] = $impactLevel;
                $assignmentMeta['status_before_incident'] = (string) $assignment->status;

                $assignment->forceFill([
                    'status' => $deliveryInterrupted ? 'incident' : $assignment->status,
                    'meta' => $assignmentMeta,
                ])->save();
            }

            $incidentMeta = is_array($incident->meta) ? $incident->meta : [];
            $incidentMeta['assignment_statuses_before_incident'] = $previousAssignmentStatuses;
            $incidentMeta['item_statuses_before_incident'] = collect($detail['items'] ?? [])->mapWithKeys(
                fn ($missionItem) => [(string) $missionItem->id => (string) $missionItem->delivery_status]
            )->all();
            $incidentMeta['mission_item_ids'] = collect($detail['items'] ?? [])->pluck('id')->filter()->values()->all();
            $incidentMeta['mission_status_before_incident'] = (string) ($detail['status'] ?? '');
            $incidentMeta['mission_phase'] = (string) ($detail['tracking_phase'] ?? '');
            $incident->forceFill(['meta' => $incidentMeta])->save();

            if ($deliveryInterrupted) {
                foreach ($detail['items'] as $missionItem) {
                    if (in_array($missionItem->delivery_status, [
                        OrderWorkflowService::DELIVERY_ASSIGNED,
                        OrderWorkflowService::DELIVERY_PICKED_UP,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        OrderWorkflowService::DELIVERY_LATE,
                    ], true)) {
                        $this->workflow->setDeliveryStatus(
                            $missionItem,
                            OrderWorkflowService::DELIVERY_FAILED,
                            null,
                            'system',
                            $description ?: 'Incident terrain bloquant signalé par le livreur.',
                            ['incident_id' => $incident->id, 'suppress_notifications' => true]
                        );
                    }
                }
            }

            return $incident;
        });

        if ($item) {
            // Le signalement terrain remonte immédiatement au centre logistique.
            // Le client n'est pas informé automatiquement : le responsable logistique
            // qualifie d'abord l'incident puis utilise l'action « Informer le client ».
            $this->deliveryNotifications->notifyLogistics(new DeliveryIncidentNotification($item->refresh()));

            $meta = $incident->meta ?? [];
            $meta['customer_notification_required'] = true;
            $meta['customer_notification_pending_since'] = now()->toIso8601String();
            $meta['teams_notification_requested_at'] = now()->toIso8601String();
            $incident->forceFill(['meta' => $meta, 'notified_at' => now()])->save();
        }
    }

    public function verifyOtp(
        DeliveryDriver $driver,
        string $missionNumber,
        string $otp,
        bool $handoverConfirmed = false
    ): void {
        $detail = $this->detail($driver, $missionNumber);

        if (($detail['status'] ?? null) !== 'arrived') {
            throw ValidationException::withMessages([
                'delivery_otp_code' => 'Confirmez d’abord votre arrivée chez le client avant de valider la livraison.',
            ]);
        }

        if (! $handoverConfirmed) {
            throw ValidationException::withMessages([
                'handover_confirmed' => 'Confirmez que tous les articles ont été remis au client avant de saisir son code.',
            ]);
        }

        $codes = $detail['items']->pluck('delivery_otp_code')->filter()->unique()->values();

        if ($codes->count() !== 1 || ! hash_equals((string) $codes->first(), $otp)) {
            // Les tentatives sont conservées dans les métadonnées existantes :
            // aucune migration n'est nécessaire et l'équipe Logistique peut
            // diagnostiquer un blocage sans connaître le code du client.
            $detail['assignments']->each(function (DeliveryAssignment $assignment) {
                $meta = is_array($assignment->meta) ? $assignment->meta : [];
                $meta['delivery_otp_failed_attempts'] = ((int) ($meta['delivery_otp_failed_attempts'] ?? 0)) + 1;
                $meta['last_delivery_otp_failed_at'] = now()->toIso8601String();
                $assignment->forceFill(['meta' => $meta])->save();
            });

            throw ValidationException::withMessages(['delivery_otp_code' => 'Code client incorrect. Vérifiez les 6 chiffres avec le client.']);
        }

        DB::transaction(function () use ($detail, $driver) {
            foreach ($detail['items'] as $item) {
                if ($item->delivery_status !== OrderWorkflowService::DELIVERY_DELIVERED) {
                    $this->workflow->setDeliveryStatus(
                        $item,
                        OrderWorkflowService::DELIVERY_DELIVERED,
                        null,
                        'logistics',
                        'Livraison remise au client et confirmée par OTP.',
                        [
                            'delivery_otp_verified_at' => now(),
                            'suppress_notifications' => true,
                        ]
                    );
                }
            }

            $detail['assignments']->each(function (DeliveryAssignment $assignment) {
                $meta = is_array($assignment->meta) ? $assignment->meta : [];
                $meta['customer_handover_confirmed_at'] = $meta['customer_handover_confirmed_at'] ?? now()->toIso8601String();
                $meta['delivery_otp_verified_at'] = $meta['delivery_otp_verified_at'] ?? now()->toIso8601String();
                $meta['delivery_completed_at'] = $meta['delivery_completed_at'] ?? now()->toIso8601String();

                $assignment->forceFill([
                    'status' => 'delivered',
                    'delivered_at' => $assignment->delivered_at ?: now(),
                    'meta' => $meta,
                ])->save();
            });

            // Le partenaire redevient immédiatement disponible pour une autre
            // mission, mais reste en ligne tant que l'application est ouverte.
            $driver->forceFill([
                'status' => 'Disponible',
                'is_online' => true,
                'last_seen_at' => now(),
            ])->save();
        });

        if ($first = $detail['items']->first()) {
            $this->deliveryNotifications->notifyGroup(
                $first->refresh(),
                'delivered',
                'Livraison confirmée',
                'Votre livraison a été remise et confirmée avec votre code de sécurité. La livraison est maintenant terminée.'
            );
        }
    }

    private function assignments(DeliveryDriver $driver, string $missionNumber): Collection
    {
        $base = DeliveryAssignment::query()
            ->with(['order.client', 'orderItem.product.shop', 'orderItem.shipment', 'driver', 'latestLocation'])
            ->where('driver_id', $driver->id)
            ->where(function ($query) use ($missionNumber) {
                $query->where('mission_number', $missionNumber)
                    ->orWhere('meta->mission_number', $missionNumber);
            })
            ->latest('id')
            ->get();

        if ($base->isNotEmpty()) {
            return $base;
        }

        // Compatibilité avec les offres créées avant la correction n°3 :
        // leur numéro de mission pouvait n'exister que via le fallback
        // OVL-{order_id} du modèle, donc pas être recherchable en SQL.
        if (preg_match('/^OVL-(\d+)(?:-\d+)?$/', $missionNumber, $matches)) {
            return DeliveryAssignment::query()
                ->with(['order.client', 'orderItem.product.shop', 'orderItem.shipment', 'driver', 'latestLocation'])
                ->where('driver_id', $driver->id)
                ->where('order_id', (int) $matches[1])
                ->latest('id')
                ->get();
        }

        return collect();
    }

    private function latestPerItem(Collection $assignments): Collection
    {
        return $assignments->sortByDesc('id')->unique('order_item_id')->values();
    }

    private function summary(DeliveryDriver $driver, string $missionNumber, Collection $assignments): array
    {
        $latest = $this->latestPerItem($assignments);
        $representative = $latest->first()?->orderItem;
        $group = $representative ? $this->consolidation->groupForItem($representative) : [];
        $order = $representative?->order;
        $status = $this->missionStatus($latest, collect($group['items'] ?? []));
        $pickupCount = (int) ($group['pickup_count'] ?? 0);
        $readyPickupCount = (int) ($group['ready_pickup_count'] ?? collect($group['pickup_stops'] ?? [])->where('ready', true)->count());
        $preparationPercent = (int) ($group['preparation_percent'] ?? 0);
        $canStart = $status === 'accepted'
            && $pickupCount > 0
            && $readyPickupCount >= $pickupCount
            && $preparationPercent >= 100;
        $reservationState = match (true) {
            $status === 'accepted' && $canStart => 'ready_for_pickup',
            $status === 'accepted' => 'waiting_vendor',
            $status === 'offered' => 'offered',
            default => null,
        };
        $statusLabel = match (true) {
            $status === 'accepted' && $canStart => 'Réservée · prête pour collecte',
            $status === 'accepted' => 'Réservée · attente vendeur',
            default => $this->statusLabel($status),
        };
        $estimated = $latest->pluck('manual_eta_at')->filter()->first()
            ?: $latest->pluck('estimated_delivery_at')->filter()->first()
            ?: collect($group['items'] ?? [])->pluck('shipment.estimated_delivery_at')->filter()->first();
        $pickup = $latest->pluck('pickup_scheduled_at')->filter()->first();
        $acceptedAt = $latest->pluck('accepted_at')->filter()->sortDesc()->first();
        $rejectedAt = $latest->pluck('rejected_at')->filter()->sortDesc()->first();
        $rejectionReason = $latest->pluck('rejection_reason')->filter()->first();
        $deliveredAt = $latest->pluck('delivered_at')->filter()->sortDesc()->first();

        return [
            'mission_number' => $missionNumber,
            'order_number' => $order?->order_number ?: ('Commande #' . $order?->id),
            'client_name' => $order?->client?->name ?: 'Client OVANIE',
            'destination' => $this->deliveryAddress($order),
            'destination_label' => $this->deliveryShortAddress($order),
            'commune' => $order?->delivery_commune ?: $order?->delivery_city ?: '—',
            'status' => $status,
            'status_label' => $statusLabel,
            'reservation_state' => $reservationState,
            'can_start' => $canStart,
            'pickup_scheduled_at' => $pickup ? Carbon::parse($pickup) : null,
            'estimated_delivery_at' => $estimated ? Carbon::parse($estimated) : null,
            'accepted_at' => $acceptedAt ? Carbon::parse($acceptedAt) : null,
            'rejected_at' => $rejectedAt ? Carbon::parse($rejectedAt) : null,
            'rejection_reason' => $rejectionReason,
            'delivered_at' => $deliveredAt ? Carbon::parse($deliveredAt) : null,
            'pickup_count' => $pickupCount,
            'ready_pickup_count' => $readyPickupCount,
            'item_count' => (int) collect($group['items'] ?? [])->sum(fn ($item) => max(1, (int) $item->quantity)),
            'line_count' => collect($group['items'] ?? [])->count(),
            'total_weight_kg' => (float) ($group['weight'] ?? 0),
            'total_volume_m3' => (float) ($group['volume'] ?? 0),
            'net_amount' => (float) $latest->sum('driver_net_amount'),
            // Sur les écrans Mission, on affiche le véhicule requis par le
            // chargement (poids/volume), comme sur les maquettes. Le véhicule
            // personnel du partenaire reste affiché dans son Accueil/Profil.
            'vehicle_code' => ($group['vehicle_code'] ?? null)
                ?: data_get($latest->first()?->meta, 'vehicle_code')
                ?: $driver->vehicle
                ?: 'vehicle',
            'vehicle_label' => ($group['vehicle_label'] ?? null)
                ?: data_get($latest->first()?->meta, 'vehicle_label')
                ?: $driver->vehicle
                ?: 'Véhicule',
            'preparation_percent' => $preparationPercent,
            'ready_count' => (int) ($group['ready_count'] ?? 0),
            'gps_status' => (string) ($latest->pluck('gps_status')->filter()->first() ?: 'unknown'),
            'gps_disabled_reason' => $latest->pluck('gps_disabled_reason')->filter()->first(),
            'sort_at' => optional($latest->max('updated_at'))->timestamp ?: 0,
        ];
    }

    private function missionStatus(Collection $assignments, Collection $items): string
    {
        if ($items->isNotEmpty() && $items->every(fn ($item) => $item->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED)) {
            return 'delivered';
        }

        $statuses = $assignments->pluck('status');
        foreach (['incident', 'arrived', 'in_transit', 'picked_up', 'collecting', 'accepted', 'assigned', 'offered', 'planned', 'rejected', 'offer_expired'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'planned';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'planned' => 'À réserver',
            'assigned' => 'À réserver',
            'offered' => 'Nouvelle mission à réserver',
            'offer_expired' => 'Prise par un autre livreur',
            'accepted' => 'Acceptée',
            'collecting' => 'Collectes en cours',
            'picked_up' => 'Chargement terminé',
            'in_transit' => 'En route vers le client',
            'arrived' => 'Arrivé à destination',
            'delivered' => 'Livrée',
            'rejected' => 'Refusée',
            'incident' => 'Incident signalé',
            default => 'Planifiée',
        };
    }

    private function ensureGroupOtp(Collection $items): void
    {
        $codes = $items->pluck('delivery_otp_code')->filter()->unique()->values();
        $otp = $codes->count() === 1 ? (string) $codes->first() : (string) random_int(100000, 999999);
        $isNew = $codes->count() !== 1;

        if ($isNew) {
            OrderItem::query()->whereIn('id', $items->pluck('id'))->update(['delivery_otp_code' => $otp]);
        }

        $order = $items->first()?->order;
        if ($isNew && $order?->client) {
            $this->notifications->send(
                $order->client,
                'deliveries',
                'Code de confirmation de livraison',
                "Votre code OVANIE est {$otp}. Communiquez-le uniquement après réception complète de cette livraison.",
                ['order_id' => $order->id, 'url' => route('client.orders.show', $order->id)],
                ['in_app', 'email', 'sms']
            );
        }
    }

    private function pickupStopCompleted(array $stop, Collection $assignmentsByItem): bool
    {
        $itemIds = collect($stop['items'] ?? [])->pluck('id')->filter();
        if ($itemIds->isEmpty()) {
            return false;
        }

        return $itemIds->every(function ($itemId) use ($assignmentsByItem) {
            $assignment = $assignmentsByItem->get($itemId);
            return $assignment && filled(data_get($assignment->meta, 'pickup_completed_at'));
        });
    }

    private function deliveryShortAddress($order): string
    {
        $parts = collect([
            $order?->delivery_commune,
            $order?->delivery_quartier ?: $order?->delivery_zone,
        ])->filter()->map(fn ($value) => trim((string) $value))->unique();

        if ($parts->isEmpty()) {
            $parts = collect([$order?->delivery_city, $order?->delivery_address])
                ->filter()
                ->map(fn ($value) => trim((string) $value))
                ->unique();
        }

        return $parts->implode(' • ') ?: 'Destination à confirmer';
    }

    private function deliveryAddress($order): string
    {
        return collect([
            $order?->delivery_address,
            $order?->address,
            $order?->delivery_quartier,
            $order?->delivery_commune,
            $order?->delivery_city,
        ])->filter()->unique()->implode(' - ');
    }
}
