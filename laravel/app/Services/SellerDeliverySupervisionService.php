<?php

namespace App\Services;

use App\Models\DeliveryIncident;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerDeliveryTrackingSession;
use App\Models\SellerDriverLocation;
use App\Models\Shop;
use App\Notifications\Delivery\DeliveryIncidentNotification;
use App\Services\Geo\DeliveryCoordinateService;
use App\Services\Geo\RoutingService;
use App\Support\LogisticsOperationalDataScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerDeliverySupervisionService
{
    public function __construct(
        private readonly RoutingService $routing,
        private readonly DeliveryCoordinateService $coordinates,
        private readonly OrderWorkflowService $workflow,
        private readonly DeliveryNotificationService $deliveryNotifications,
        private readonly OvanieNotificationDispatcher $notifications,
    ) {
    }

    /**
     * Démarre ou réutilise une session unique de suivi vendeur pour une commande/boutique.
     * Toutes les lignes du groupe vendeur reçoivent le même session_id.
     */
    public function startSession(Order $order, Shop $shop, Collection $items, array $driver): SellerDeliveryTrackingSession
    {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'delivery_provider' => 'Aucune ligne de livraison vendeur à superviser.',
            ]);
        }

        $session = SellerDeliveryTrackingSession::query()
            ->where('order_id', $order->id)
            ->where('shop_id', $shop->id)
            ->whereIn('status', [
                SellerDeliveryTrackingSession::STATUS_ACTIVE,
                SellerDeliveryTrackingSession::STATUS_INCIDENT,
            ])
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (! $session) {
            $session = SellerDeliveryTrackingSession::create([
                'public_id' => (string) Str::uuid(),
                'access_token' => bin2hex(random_bytes(32)),
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'status' => SellerDeliveryTrackingSession::STATUS_ACTIVE,
                'mission_status' => 'planned',
                'gps_status' => 'unknown',
                'driver_name' => trim((string) $driver['driver_name']),
                'driver_phone' => trim((string) $driver['driver_phone']),
                'vehicle_plate' => filled($driver['vehicle_plate'] ?? null)
                    ? trim((string) $driver['vehicle_plate'])
                    : null,
                'started_at' => null,
                'estimated_delivery_at' => $this->estimatedAt($items),
            ]);
        } else {
            $session->forceFill([
                'status' => SellerDeliveryTrackingSession::STATUS_ACTIVE,
                'mission_status' => $session->mission_status ?: 'planned',
                'gps_status' => $session->gps_status ?: 'unknown',
                'driver_name' => trim((string) $driver['driver_name']),
                'driver_phone' => trim((string) $driver['driver_phone']),
                'vehicle_plate' => filled($driver['vehicle_plate'] ?? null)
                    ? trim((string) $driver['vehicle_plate'])
                    : $session->vehicle_plate,
                'ended_at' => null,
            ])->save();
        }

        OrderItem::query()
            ->whereIn('id', $items->pluck('id'))
            ->update([
                'seller_tracking_session_id' => $session->id,
                'driver_name' => $session->driver_name,
                'driver_phone' => $session->driver_phone,
                'vehicle_plate' => $session->vehicle_plate,
            ]);

        return $session->refresh();
    }

    public function publicMissionUrl(SellerDeliveryTrackingSession $session): string
    {
        return route('seller-driver.mission', [
            'publicId' => $session->public_id,
            'token' => $session->access_token,
        ]);
    }

    public function resolvePublicSession(string $publicId, string $token): SellerDeliveryTrackingSession
    {
        $session = SellerDeliveryTrackingSession::query()
            ->with(['order.client', 'shop', 'items.product'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        abort_unless(hash_equals((string) $session->access_token, (string) $token), 404);

        return $session;
    }

    public function recordLocation(SellerDeliveryTrackingSession $session, array $payload): SellerDriverLocation
    {
        if (! $this->coordinates->valid($payload['latitude'] ?? null, $payload['longitude'] ?? null)) {
            throw ValidationException::withMessages([
                'location' => 'La position GPS reçue est invalide ou située hors de la zone de livraison autorisée.',
            ]);
        }

        if (! $session->isTrackable()) {
            throw ValidationException::withMessages([
                'tracking' => 'Cette mission n’accepte plus de positions GPS.',
            ]);
        }

        $session->loadMissing('latestLocation');
        $accuracy = isset($payload['accuracy']) && is_numeric($payload['accuracy'])
            ? (float) $payload['accuracy']
            : null;
        if (! $session->latestLocation && $accuracy !== null
            && $accuracy > (float) config('delivery.gps_max_accuracy_m', 60)) {
            throw ValidationException::withMessages([
                'accuracy' => 'La précision GPS est insuffisante. Attendez quelques secondes dans une zone dégagée.',
            ]);
        }
        $speed = isset($payload['speed']) && is_numeric($payload['speed'])
            ? (float) $payload['speed']
            : null;
        [$trustedLatitude, $trustedLongitude] = $this->guardAgainstImpossibleJump(
            (float) $payload['latitude'],
            (float) $payload['longitude'],
            $accuracy,
            $speed,
            $session->latestLocation,
            $payload['recorded_at'] ?? null,
        );
        [$stableLatitude, $stableLongitude] = $this->stabilizedCoordinates(
            $trustedLatitude,
            $trustedLongitude,
            $accuracy,
            $speed,
            $session->latestLocation?->latitude,
            $session->latestLocation?->longitude,
        );
        $heading = $this->resolvedHeading(
            $payload['heading'] ?? null,
            $session->latestLocation,
            $stableLatitude,
            $stableLongitude,
        );

        $location = SellerDriverLocation::create([
            'tracking_session_id' => $session->id,
            'shop_id' => $session->shop_id,
            'order_id' => $session->order_id,
            'latitude' => $stableLatitude,
            'longitude' => $stableLongitude,
            'accuracy' => $payload['accuracy'] ?? null,
            'speed' => $payload['speed'] ?? null,
            'heading' => $heading,
            'battery_level' => $payload['battery_level'] ?? null,
            'recorded_at' => $payload['recorded_at'] ?? now(),
        ]);

        $session->forceFill([
            'last_location_at' => $location->recorded_at,
            'last_latitude' => $location->latitude,
            'last_longitude' => $location->longitude,
            'last_accuracy' => $location->accuracy,
            'last_speed' => $location->speed,
            'last_heading' => $location->heading,
            'gps_status' => 'active',
            'gps_disabled_reason' => null,
        ])->save();

        // Compatibilité avec les anciennes vues et API qui lisent encore la position sur order_items.
        $session->items()->update([
            'driver_latitude' => $location->latitude,
            'driver_longitude' => $location->longitude,
            'driver_location_updated_at' => $location->recorded_at,
        ]);

        if ($this->shouldRefreshRoute($session)) {
            $this->refreshRoute($session->fresh());
        }

        $this->detectAutomaticArrival($session->fresh(), $location);

        return $location;
    }

    public function acceptSession(SellerDeliveryTrackingSession $session): SellerDeliveryTrackingSession
    {
        $session->forceFill([
            'mission_status' => 'accepted',
            'accepted_at' => $session->accepted_at ?: now(),
        ])->save();

        return $session->refresh();
    }

    public function startDelivery(SellerDeliveryTrackingSession $session): SellerDeliveryTrackingSession
    {
        return DB::transaction(function () use ($session) {
            $locked = SellerDeliveryTrackingSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->mission_status === 'in_transit') {
                return $locked->refresh();
            }

            if ($locked->mission_status !== 'accepted') {
                throw ValidationException::withMessages([
                    'mission' => 'Le chauffeur doit d’abord accepter la mission avant de démarrer la livraison.',
                ]);
            }

            $hasRecentGps = $locked->last_location_at
                && $locked->last_location_at->gte(now()->subMinutes(5));
            $manualTracking = in_array($locked->gps_status, ['unavailable', 'denied', 'disabled'], true);

            if (! $hasRecentGps && ! $manualTracking) {
                throw ValidationException::withMessages([
                    'mission' => 'Activez le GPS et attendez la confirmation de la première position avant de démarrer.',
                ]);
            }

            $items = OrderItem::query()
                ->with('order.client')
                ->where('seller_tracking_session_id', $locked->id)
                ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'mission' => 'Aucun article n’est rattaché à cette mission vendeur.',
                ]);
            }

            foreach ($items as $item) {
                if ($item->delivery_status !== OrderWorkflowService::DELIVERY_IN_TRANSIT) {
                    $this->workflow->setDeliveryStatus(
                        $item,
                        OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        null,
                        'seller_driver',
                        'Le chauffeur a démarré la livraison.',
                        ['suppress_notifications' => true]
                    );
                }
            }

            $locked->forceFill([
                'status' => SellerDeliveryTrackingSession::STATUS_ACTIVE,
                'mission_status' => 'in_transit',
                'accepted_at' => $locked->accepted_at ?: now(),
                'started_at' => $locked->started_at ?: now(),
                'departed_at' => $locked->departed_at ?: now(),
            ])->save();

            $this->ensureGroupOtp($items);

            if ($first = $items->first()) {
                $this->deliveryNotifications->notifyGroup(
                    $first->refresh(),
                    'in_transit',
                    'Livreur en route',
                    'Votre livraison est en route. Consultez votre espace client pour suivre son avancement.',
                    'Votre livraison OVANIE est en route.'
                );
            }

            return $locked->refresh();
        });
    }

    public function markGpsUnavailable(
        SellerDeliveryTrackingSession $session,
        string $reason,
        ?CarbonInterface $manualEta = null
    ): SellerDeliveryTrackingSession {
        $session->forceFill([
            'gps_status' => 'unavailable',
            'gps_disabled_reason' => $reason,
            'manual_eta_at' => $manualEta ?: $session->manual_eta_at ?: $session->estimated_delivery_at,
            'last_manual_status_at' => now(),
        ])->save();

        return $session->refresh();
    }

    public function updateManualStatus(
        SellerDeliveryTrackingSession $session,
        string $status,
        ?CarbonInterface $manualEta = null
    ): SellerDeliveryTrackingSession {
        $allowed = ['accepted', 'in_transit', 'arrived'];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Étape de livraison invalide.',
            ]);
        }

        if ($status === 'in_transit') {
            $session = $this->startDelivery($session);
        }

        $session->forceFill(array_filter([
            'mission_status' => $status,
            'accepted_at' => $session->accepted_at ?: now(),
            'started_at' => in_array($status, ['in_transit', 'arrived'], true)
                ? ($session->started_at ?: now())
                : $session->started_at,
            'departed_at' => in_array($status, ['in_transit', 'arrived'], true)
                ? ($session->departed_at ?: now())
                : null,
            'arrived_at' => $status === 'arrived' ? ($session->arrived_at ?: now()) : null,
            'manual_eta_at' => $manualEta ?: $session->manual_eta_at,
            'last_manual_status_at' => now(),
        ], fn ($value) => $value !== null))->save();

        return $session->refresh();
    }

    public function reportIncident(
        SellerDeliveryTrackingSession $session,
        string $type,
        ?string $note = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): SellerDeliveryTrackingSession {
        if (! $session->isTrackable()) {
            throw ValidationException::withMessages([
                'incident' => 'Cette mission est déjà terminée.',
            ]);
        }

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

        [$freshSession, $incident, $item] = DB::transaction(function () use ($session, $canonicalType, $responsibility, $note, $latitude, $longitude, $impactLevel, $impactLabel, $deliveryInterrupted) {
            $locked = SellerDeliveryTrackingSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => SellerDeliveryTrackingSession::STATUS_INCIDENT,
                'incident_type' => $canonicalType,
                'incident_note' => $note,
                'incident_reported_at' => now(),
            ])->save();

            $locked->loadMissing(['order.client', 'shop', 'items.product']);
            $item = $locked->items->sortBy('id')->first();
            $order = $locked->order;
            $shop = $locked->shop;
            $deliveryStatusLabel = match ($locked->mission_status) {
                'planned' => 'Planifiée',
                'accepted' => 'Mission acceptée',
                'in_transit' => 'En transit',
                'arrived' => 'Arrivée à destination',
                'delivered' => 'Livrée',
                default => 'État non renseigné',
            };

            $incident = DeliveryIncident::create([
                'order_id' => $locked->order_id,
                'order_item_id' => $item?->id,
                'shipment_id' => $item?->shipment_id,
                'reported_by_type' => 'seller_driver',
                'reported_by_id' => null,
                'incident_type' => $canonicalType,
                'severity' => in_array($canonicalType, ['accident', 'produit_endommage', 'panne_vehicule'], true) ? 'high' : 'medium',
                'responsibility' => $responsibility,
                'description' => $note ?: 'Incident signalé depuis la mission mobile du chauffeur vendeur.',
                'latitude' => $latitude ?: $locked->last_latitude,
                'longitude' => $longitude ?: $locked->last_longitude,
                'occurred_at' => now(),
                'status' => $impactLevel === 'rescheduled' ? 'rescheduled' : 'open',
                'next_action' => $deliveryInterrupted
                    ? 'Le centre logistique doit organiser la reprise de la livraison.'
                    : 'Le centre logistique contrôle l’impact sur l’heure estimée de livraison.',
                'meta' => [
                    'signal_source' => 'seller_driver_app',
                    'signal_source_label' => 'Application du chauffeur vendeur',
                    'source_actor_name' => $locked->driver_name ?: 'Chauffeur vendeur',
                    'source_actor_phone' => $locked->driver_phone,
                    'mission_number' => sprintf('LIV-%05d-%03d', (int) $locked->order_id, (int) $locked->id),
                    'source_received_at' => now()->toIso8601String(),
                    'gps_source' => ($latitude !== null && $longitude !== null) ? 'seller_driver_report' : 'seller_tracking_session',
                    'gps_recorded_at' => now()->toIso8601String(),
                    'order_number' => $order?->order_number,
                    'payment_status' => $order?->payment_status,
                    'client_name' => $order?->delivery_recipient_name ?: $order?->customer_name ?: $order?->client?->name,
                    'client_phone' => $order?->delivery_recipient_phone ?: $order?->phone ?: $order?->client?->phone,
                    'shop_name' => $shop?->display_name ?: $shop?->name,
                    'shop_phone' => $shop?->whatsapp,
                    'pickup_address' => $shop?->address,
                    'pickup_commune' => $shop?->commune,
                    'destination_address' => $order?->delivery_address,
                    'destination_quartier' => $order?->delivery_quartier,
                    'destination_commune' => $order?->delivery_commune,
                    'destination_city' => $order?->delivery_city,
                    'driver_name' => $locked->driver_name,
                    'driver_phone' => $locked->driver_phone,
                    'driver_vehicle' => $locked->vehicle_plate ?: 'Véhicule vendeur',
                    'driver_zone' => $shop?->commune,
                    'delivery_status_label' => $deliveryStatusLabel,
                    'delivery_status_before_incident' => $item?->delivery_status,
                    'incident_type_label' => ucfirst(str_replace('_', ' ', $canonicalType)),
                    'impact_level' => $impactLevel,
                    'impact_label' => $impactLabel,
                    'delivery_interrupted' => $deliveryInterrupted,
                    'customer_notification_required' => true,
                ],
            ]);

            if ($deliveryInterrupted) {
                foreach ($locked->items as $missionItem) {
                    if (! in_array($missionItem->delivery_status, [OrderWorkflowService::DELIVERY_DELIVERED, OrderWorkflowService::DELIVERY_CANCELLED], true)) {
                        $this->workflow->setDeliveryStatus(
                            $missionItem,
                            OrderWorkflowService::DELIVERY_FAILED,
                            null,
                            'system',
                            $note ?: 'Incident terrain bloquant signalé par le chauffeur.',
                            ['incident_id' => $incident->id, 'suppress_notifications' => true]
                        );
                    }
                }
            }

            return [$locked->refresh(), $incident, $item];
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

        return $freshSession;
    }

    public function completeSession(SellerDeliveryTrackingSession $session): SellerDeliveryTrackingSession
    {
        $session->forceFill([
            'status' => SellerDeliveryTrackingSession::STATUS_COMPLETED,
            'mission_status' => 'delivered',
            'ended_at' => now(),
        ])->save();

        return $session->refresh();
    }

    public function signalStatus(SellerDeliveryTrackingSession $session): array
    {
        if ($session->status === SellerDeliveryTrackingSession::STATUS_COMPLETED) {
            return ['code' => 'completed', 'label' => 'Mission terminée', 'severity' => 'success', 'age_seconds' => 0];
        }

        if (in_array($session->gps_status, ['unavailable', 'denied', 'disabled'], true)) {
            return ['code' => 'unavailable', 'label' => 'Suivi manuel actif', 'severity' => 'warning', 'age_seconds' => null];
        }

        $isMoving = in_array($session->mission_status, ['in_transit', 'arrived'], true);

        if (! $session->last_location_at) {
            return [
                'code' => $isMoving ? 'waiting' : 'standby',
                'label' => $isMoving ? 'En attente du premier signal GPS' : 'GPS en attente du départ',
                'severity' => 'warning',
                'age_seconds' => null,
            ];
        }

        $ageSeconds = $session->last_location_at->diffInSeconds(now());

        if ($ageSeconds <= (int) config('delivery.gps_active_seconds', 90)) {
            return ['code' => 'active', 'label' => 'GPS actif', 'severity' => 'success', 'age_seconds' => $ageSeconds];
        }

        if ($ageSeconds <= (int) config('delivery.gps_weak_seconds', 180)) {
            return ['code' => 'weak', 'label' => 'Dernière position reçue récemment', 'severity' => 'warning', 'age_seconds' => $ageSeconds];
        }

        if (! $isMoving) {
            return ['code' => 'standby', 'label' => 'GPS en attente du départ', 'severity' => 'warning', 'age_seconds' => $ageSeconds];
        }

        if ($ageSeconds <= (int) config('delivery.gps_lost_seconds', 300)) {
            return ['code' => 'stale', 'label' => 'Mise à jour GPS en attente', 'severity' => 'warning', 'age_seconds' => $ageSeconds];
        }

        return ['code' => 'lost', 'label' => 'Position GPS non actualisée', 'severity' => 'danger', 'age_seconds' => $ageSeconds];
    }

    public function trackingRows(bool $includeCompletedToday = true): Collection
    {
        $query = SellerDeliveryTrackingSession::query()
            ->with(['order.client', 'shop', 'items.product', 'latestLocation'])
            ->whereHas('order', fn ($orderQuery) => LogisticsOperationalDataScope::orders($orderQuery))
            ->whereHas('shop', fn ($shopQuery) => LogisticsOperationalDataScope::shops($shopQuery))
            ->where(function ($status) use ($includeCompletedToday) {
                $status->where(function ($active) {
                    $active->where('status', SellerDeliveryTrackingSession::STATUS_ACTIVE)
                        ->whereIn('mission_status', ['in_transit', 'arrived']);
                })->orWhere('status', SellerDeliveryTrackingSession::STATUS_INCIDENT);

                if ($includeCompletedToday) {
                    $status->orWhere(function ($done) {
                        $done->where('status', SellerDeliveryTrackingSession::STATUS_COMPLETED)
                            ->whereDate('ended_at', today());
                    });
                }
            })
            ->latest('started_at');

        return $query->get()->map(fn (SellerDeliveryTrackingSession $session) => $this->row($session));
    }

    public function row(SellerDeliveryTrackingSession $session): array
    {
        $session->loadMissing(['order.client', 'shop', 'items.product', 'latestLocation']);
        $order = $session->order;
        $shop = $session->shop;
        $signal = $this->signalStatus($session);
        $isCompleted = $session->status === SellerDeliveryTrackingSession::STATUS_COMPLETED;
        $isIncident = $session->status === SellerDeliveryTrackingSession::STATUS_INCIDENT;
        $etaAt = $session->manual_eta_at ?: $session->estimated_delivery_at;
        $isMoving = in_array($session->mission_status, ['in_transit', 'arrived'], true);
        $isEtaLate = ! $isCompleted && $isMoving && $etaAt && $etaAt->lt(now()->subMinutes(15));
        $isLate = ! $isCompleted && ($isIncident || ($session->traffic_delay_minutes ?? 0) >= 15 || $isEtaLate);
        $bucket = $isCompleted
            ? 'delivered'
            : ($isLate
                ? 'delay'
                : ($isMoving ? 'in_route' : 'pending'));
        $destination = $this->coordinates->forOrder($order);
        $destinationLat = $destination['latitude'];
        $destinationLng = $destination['longitude'];
        $pickupLat = $shop?->latitude;
        $pickupLng = $shop?->longitude;
        $pickupCompleted = $isCompleted || in_array($session->mission_status, ['in_transit', 'arrived', 'delivered'], true);
        $hasVisibleDriverPosition = $signal['code'] !== 'unavailable'
            && $this->valid($session->last_latitude, $session->last_longitude);

        return [
            'id' => 'seller-session-' . $session->id,
            'session_id' => $session->id,
            'mission_number' => sprintf('LIV-%05d-%03d', (int) $session->order_id, (int) $session->id),
            'provider_type' => OrderWorkflowService::PROVIDER_SELLER,
            'provider_label' => 'Logistique vendeur',
            'order_id' => $session->order_id,
            'order_number' => $order?->order_number ?: ('CMD-' . $session->order_id),
            'client_name' => $order?->client?->name ?: 'Client',
            'shop_name' => $shop?->name ?: 'Boutique',
            'commune' => $order?->delivery_commune ?: $order?->delivery_city ?: '—',
            'address' => collect([
                $order?->delivery_address,
                $order?->address,
                $order?->delivery_quartier,
                $order?->delivery_commune,
                $order?->delivery_city,
            ])->filter()->unique()->implode(' - '),
            'driver_name' => $session->driver_name,
            'driver_phone' => $session->driver_phone,
            'vehicle_plate' => $session->vehicle_plate,
            'vehicle_label' => 'Véhicule vendeur',
            'collection_count' => 1,
            'item_count' => (int) $session->items->sum(fn ($item) => max(1, (int) $item->quantity)),
            'status' => $isCompleted
                ? OrderWorkflowService::DELIVERY_DELIVERED
                : ($isIncident
                    ? OrderWorkflowService::DELIVERY_FAILED
                    : match ($session->mission_status) {
                        'in_transit', 'arrived' => OrderWorkflowService::DELIVERY_IN_TRANSIT,
                        'accepted' => OrderWorkflowService::DELIVERY_ASSIGNED,
                        default => OrderWorkflowService::DELIVERY_PREPARING,
                    }),
            'status_bucket' => $bucket,
            'signal_status' => $signal['code'],
            'signal_label' => $signal['label'],
            'gps_status' => $session->gps_status,
            'gps_disabled_reason' => $session->gps_disabled_reason,
            'mission_status' => $session->mission_status,
            'estimated_delivery_at' => $etaAt?->toIso8601String(),
            'delivered_at' => $isCompleted ? ($session->ended_at?->toIso8601String() ?: $session->updated_at?->toIso8601String()) : null,
            'eta_label' => $isCompleted
                ? 'Livré'
                : ($etaAt?->format('d/m/Y à H:i') ?: 'À confirmer'),
            'last_seen_at' => $session->last_location_at?->toIso8601String(),
            'signal_age_seconds' => $signal['age_seconds'] ?? null,
            'last_position_label' => $session->last_location_at?->diffForHumans() ?: 'Aucune position reçue',
            'gps_accuracy_m' => is_numeric($session->last_accuracy) ? round((float) $session->last_accuracy) : null,
            'eta' => $isCompleted
                ? 'Livré'
                : ($session->eta_minutes
                    ? $session->eta_minutes . ' min'
                    : ($etaAt?->format('d/m H:i') ?: 'À confirmer')),
            'pickup_lat' => is_numeric($pickupLat) ? (float) $pickupLat : null,
            'pickup_lng' => is_numeric($pickupLng) ? (float) $pickupLng : null,
            'destination_lat' => is_numeric($destinationLat) ? (float) $destinationLat : null,
            'destination_lng' => is_numeric($destinationLng) ? (float) $destinationLng : null,
            'driver_lat' => $signal['code'] === 'unavailable' ? null : $session->last_latitude,
            'driver_lng' => $signal['code'] === 'unavailable' ? null : $session->last_longitude,
            'route_points' => array_values(array_filter([
                $hasVisibleDriverPosition ? [
                    'id' => 'driver', 'type' => 'driver', 'name' => $session->driver_name,
                    'address' => 'Position actuelle',
                    'latitude' => (float) $session->last_latitude, 'longitude' => (float) $session->last_longitude,
                ] : null,
                $this->valid($destinationLat, $destinationLng) ? [
                    'id' => 'destination', 'type' => 'destination', 'name' => 'Destination client',
                    'address' => collect([$order?->delivery_address, $order?->delivery_quartier, $order?->delivery_commune])->filter()->unique()->implode(' - '),
                    'latitude' => (float) $destinationLat, 'longitude' => (float) $destinationLng,
                ] : null,
            ])),
            'map_points' => array_values(array_filter([
                $hasVisibleDriverPosition ? [
                    'id' => 'driver', 'type' => 'driver', 'name' => $session->driver_name,
                    'address' => 'Position actuelle',
                    'latitude' => (float) $session->last_latitude, 'longitude' => (float) $session->last_longitude,
                ] : null,
                $this->valid($pickupLat, $pickupLng) ? [
                    'id' => 'shop-' . (int) $session->shop_id, 'type' => 'pickup', 'name' => $shop?->name ?: 'Boutique',
                    'address' => collect([$shop?->address, $shop?->quartier, $shop?->commune, $shop?->city])->filter()->unique()->implode(' - '),
                    'completed' => $pickupCompleted, 'context_only' => $pickupCompleted,
                    'latitude' => (float) $pickupLat, 'longitude' => (float) $pickupLng,
                ] : null,
                $this->valid($destinationLat, $destinationLng) ? [
                    'id' => 'destination', 'type' => 'destination', 'name' => 'Destination client',
                    'address' => collect([$order?->delivery_address, $order?->delivery_quartier, $order?->delivery_commune])->filter()->unique()->implode(' - '),
                    'latitude' => (float) $destinationLat, 'longitude' => (float) $destinationLng,
                ] : null,
            ])),
            'route' => [
                'distance_km' => $signal['code'] === 'unavailable' ? null : $session->remaining_distance_km,
                'duration_minutes' => $signal['code'] === 'unavailable' ? null : $session->eta_minutes,
                'traffic_delay_minutes' => $signal['code'] === 'unavailable' ? null : $session->traffic_delay_minutes,
                'geometry' => $signal['code'] === 'unavailable' ? null : $this->decodeGeometry($session->route_geometry),
            ],
            'incident' => $isIncident ? [
                'type' => $session->incident_type,
                'note' => $session->incident_note,
                'reported_at' => $session->incident_reported_at?->toIso8601String(),
            ] : null,
        ];
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

    private function shouldRefreshRoute(SellerDeliveryTrackingSession $session): bool
    {
        if (blank($session->route_geometry) || ! $session->route_calculated_at) {
            return true;
        }

        $refreshSeconds = (int) config('delivery.route_refresh_seconds', 12);
        if ($session->route_calculated_at->lte(now()->subSeconds($refreshSeconds))) {
            return true;
        }

        $cooldown = (int) config('delivery.route_deviation_cooldown_seconds', 8);
        if ($session->route_calculated_at->gt(now()->subSeconds($cooldown))) {
            return false;
        }

        $geometry = $this->decodeGeometry($session->route_geometry);
        $distanceFromRoute = $this->distanceToRouteMeters(
            $geometry,
            (float) $session->last_latitude,
            (float) $session->last_longitude,
        );

        return $distanceFromRoute !== null
            && $distanceFromRoute > (float) config('delivery.route_deviation_threshold_m', 45);
    }

    private function refreshRoute(SellerDeliveryTrackingSession $session): void
    {
        $session->loadMissing('order');
        $order = $session->order;
        $destination = $this->coordinates->forOrder($order);
        $toLat = $destination['latitude'];
        $toLng = $destination['longitude'];

        if (! $this->valid($session->last_latitude, $session->last_longitude)
            || ! $this->valid($toLat, $toLng)) {
            return;
        }

        $route = $this->routing->route(
            (float) $session->last_latitude,
            (float) $session->last_longitude,
            (float) $toLat,
            (float) $toLng,
        );

        if (! ($route['success'] ?? false)) {
            return;
        }

        $session->forceFill([
            'remaining_distance_km' => $route['distance_km'] ?? null,
            'eta_minutes' => $route['duration_minutes'] ?? null,
            'traffic_delay_minutes' => $route['traffic_delay_minutes'] ?? null,
            'route_geometry' => is_array($route['route_geometry'] ?? null)
                ? json_encode($route['route_geometry'])
                : ($route['route_geometry'] ?? null),
            'route_calculated_at' => now(),
        ])->save();
    }

    private function detectAutomaticArrival(
        SellerDeliveryTrackingSession $session,
        SellerDriverLocation $location
    ): void {
        if ($session->mission_status !== 'in_transit') {
            return;
        }

        $accuracy = is_numeric($location->accuracy) ? (float) $location->accuracy : null;
        if ($accuracy !== null
            && $accuracy > (float) config('delivery.arrival_max_accuracy_m', 80)) {
            return;
        }

        $session->loadMissing('order');
        $destination = $this->coordinates->forOrder($session->order);
        if (! $this->valid($destination['latitude'] ?? null, $destination['longitude'] ?? null)) {
            return;
        }

        $distance = $this->distanceMeters(
            (float) $location->latitude,
            (float) $location->longitude,
            (float) $destination['latitude'],
            (float) $destination['longitude'],
        );

        if ($distance > (float) config('delivery.arrival_radius_m', 20)) {
            return;
        }

        $requiredFixes = (int) config('delivery.arrival_confirmation_fixes', 2);
        $recentFixes = $session->locations()
            ->latest('recorded_at')
            ->limit($requiredFixes)
            ->get();
        if ($recentFixes->count() < $requiredFixes) {
            return;
        }

        $arrivalRadius = (float) config('delivery.arrival_radius_m', 20);
        $maxAccuracy = (float) config('delivery.arrival_max_accuracy_m', 35);
        $allFixesConfirmArrival = $recentFixes->every(function (SellerDriverLocation $fix) use (
            $destination,
            $arrivalRadius,
            $maxAccuracy
        ): bool {
            if (is_numeric($fix->accuracy) && (float) $fix->accuracy > $maxAccuracy) {
                return false;
            }

            return $this->distanceMeters(
                (float) $fix->latitude,
                (float) $fix->longitude,
                (float) $destination['latitude'],
                (float) $destination['longitude'],
            ) <= $arrivalRadius;
        });
        if (! $allFixesConfirmArrival) {
            return;
        }

        $session->forceFill([
            'mission_status' => 'arrived',
            'arrived_at' => $session->arrived_at ?: now(),
            'remaining_distance_km' => 0,
            'eta_minutes' => 0,
            'traffic_delay_minutes' => 0,
        ])->save();
    }

    private function distanceToRouteMeters(?array $geometry, float $latitude, float $longitude): ?float
    {
        $coordinates = $geometry['coordinates'] ?? null;
        if (($geometry['type'] ?? null) !== 'LineString'
            || ! is_array($coordinates)
            || count($coordinates) < 2) {
            return null;
        }

        $nearest = null;
        for ($index = 0, $count = count($coordinates) - 1; $index < $count; $index++) {
            $start = $coordinates[$index];
            $end = $coordinates[$index + 1];
            if (! is_array($start) || ! is_array($end)
                || count($start) < 2 || count($end) < 2) {
                continue;
            }

            $candidate = $this->projectOnSegment(
                [$longitude, $latitude],
                [(float) $start[0], (float) $start[1]],
                [(float) $end[0], (float) $end[1]],
            );
            $distance = $this->distanceMeters(
                $latitude,
                $longitude,
                $candidate[1],
                $candidate[0],
            );
            $nearest = $nearest === null ? $distance : min($nearest, $distance);
        }

        return $nearest;
    }

    private function projectOnSegment(array $point, array $start, array $end): array
    {
        $latitudeScale = cos(deg2rad((float) $point[1]));
        $px = (float) $point[0] * $latitudeScale;
        $py = (float) $point[1];
        $ax = (float) $start[0] * $latitudeScale;
        $ay = (float) $start[1];
        $bx = (float) $end[0] * $latitudeScale;
        $by = (float) $end[1];
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = ($dx * $dx) + ($dy * $dy);
        $ratio = $lengthSquared > 0
            ? max(0.0, min(1.0, ((($px - $ax) * $dx) + (($py - $ay) * $dy)) / $lengthSquared))
            : 0.0;

        return [
            (float) $start[0] + (((float) $end[0] - (float) $start[0]) * $ratio),
            (float) $start[1] + (((float) $end[1] - (float) $start[1]) * $ratio),
        ];
    }

    private function guardAgainstImpossibleJump(
        float $latitude,
        float $longitude,
        ?float $accuracy,
        ?float $speed,
        ?SellerDriverLocation $previousLocation,
        mixed $recordedAt = null,
    ): array {
        if (! $previousLocation || ! $this->valid($previousLocation->latitude, $previousLocation->longitude)) {
            return [$latitude, $longitude];
        }

        $distance = $this->distanceMeters(
            (float) $previousLocation->latitude,
            (float) $previousLocation->longitude,
            $latitude,
            $longitude,
        );
        $candidateAt = $recordedAt ? \Carbon\Carbon::parse($recordedAt) : now();
        $previousAt = $previousLocation->recorded_at ?: $previousLocation->created_at ?: now();
        $elapsedSeconds = max(1, $previousAt->diffInSeconds($candidateAt));
        $maxSpeed = max(10.0, (float) config('delivery.gps_max_plausible_speed_mps', 45));
        $accuracyBuffer = max(30.0, min(250.0, (float) ($accuracy ?? 30.0) * 2));
        $allowedDistance = max(
            (float) config('delivery.gps_min_jump_allowance_m', 250),
            ($elapsedSeconds * $maxSpeed) + $accuracyBuffer,
        );

        if ($distance > $allowedDistance || ($speed !== null && $speed > $maxSpeed * 1.35)) {
            return [(float) $previousLocation->latitude, (float) $previousLocation->longitude];
        }

        return [$latitude, $longitude];
    }

    private function resolvedHeading(
        mixed $reportedHeading,
        ?SellerDriverLocation $previousLocation,
        float $latitude,
        float $longitude,
    ): ?float {
        if (is_numeric($reportedHeading)) {
            return round(fmod(((float) $reportedHeading) + 360.0, 360.0), 1);
        }

        if (! $previousLocation || ! $this->valid($previousLocation->latitude, $previousLocation->longitude)) {
            return null;
        }

        $distance = $this->distanceMeters(
            (float) $previousLocation->latitude,
            (float) $previousLocation->longitude,
            $latitude,
            $longitude,
        );
        if ($distance < 4.0) {
            return is_numeric($previousLocation->heading) ? (float) $previousLocation->heading : null;
        }

        $lat1 = deg2rad((float) $previousLocation->latitude);
        $lat2 = deg2rad($latitude);
        $deltaLng = deg2rad($longitude - (float) $previousLocation->longitude);
        $y = sin($deltaLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);

        return round(fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0), 1);
    }

    private function stabilizedCoordinates(
        float $latitude,
        float $longitude,
        ?float $accuracy,
        ?float $speed,
        mixed $previousLatitude,
        mixed $previousLongitude,
    ): array {
        if (! $this->valid($previousLatitude, $previousLongitude)) {
            return [$latitude, $longitude];
        }

        if ($accuracy !== null && $accuracy > (float) config('delivery.gps_max_accuracy_m', 100)) {
            return [(float) $previousLatitude, (float) $previousLongitude];
        }

        $distance = $this->distanceMeters(
            (float) $previousLatitude,
            (float) $previousLongitude,
            $latitude,
            $longitude,
        );
        $radius = min(
            (float) config('delivery.gps_jitter_radius_cap_m', 60),
            max(8.0, (float) ($accuracy ?? 8.0))
        );
        $stationaryRadius = $speed === null
            ? min(12.0, max(5.0, (float) ($accuracy ?? 10.0) * 0.20))
            : $radius;
        $isNearlyStationary = $speed === null
            ? $distance <= $stationaryRadius
            : ($speed <= 2.5 && $distance <= $stationaryRadius);

        return $isNearlyStationary
            ? [(float) $previousLatitude, (float) $previousLongitude]
            : [$latitude, $longitude];
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad($fromLat);
        $lat2 = deg2rad($toLat);
        $deltaLat = deg2rad($toLat - $fromLat);
        $deltaLng = deg2rad($toLng - $fromLng);
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    private function estimatedAt(Collection $items): ?CarbonInterface
    {
        $date = $items->pluck('vendor_shipment_date')
            ->filter()
            ->sort()
            ->first();

        return $date ? \Carbon\Carbon::parse($date)->endOfDay() : null;
    }

    private function valid($lat, $lng): bool
    {
        return $this->coordinates->valid($lat, $lng);
    }

    private function decodeGeometry($geometry): ?array
    {
        if (is_array($geometry)) {
            return $geometry;
        }

        if (! is_string($geometry) || trim($geometry) === '') {
            return null;
        }

        $decoded = json_decode($geometry, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
