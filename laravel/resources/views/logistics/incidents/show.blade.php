@extends('layouts.logistics-operations')
@section('title', 'Détail incident')
@section('page-class', 'ops-directory directory-detail')
@include('logistics.directory.assets')

@section('content')
@php
    use App\ViewModels\LogisticsDirectoryData as D;

    [$severity, $color] = D::severity($incident->severity);
    [$status, $tone] = D::incidentStatus($incident->status);
    $severityLabel = ucfirst((string) $severity);
    $statusLabel = ucfirst((string) $status);

    $item = $incident->orderItem;
    $order = $incident->order;
    $assignment = $item?->latestDeliveryAssignment;
    $driver = $assignment?->driver;
    $latestLocation = $assignment?->latestLocation;
    $shop = $item?->product?->shop;
    $product = $item?->product;

    $mission = $assignment?->mission_number
        ?: data_get($incident->meta, 'mission_number')
        ?: $incident->shipment?->tracking_number
        ?: 'OVL-' . str_pad((string) ($incident->order_item_id ?? $incident->id), 6, '0', STR_PAD_LEFT);

    $orderNumber = $order?->order_number
        ?: data_get($incident->meta, 'order_number')
        ?: ($incident->order_id ? 'Commande #' . $incident->order_id : 'Non renseignée');

    $clientName = $order?->delivery_recipient_name
        ?: $order?->customer_name
        ?: $order?->client?->name
        ?: data_get($incident->meta, 'client_name')
        ?: 'Non renseigné';
    $clientPhone = $order?->delivery_recipient_phone
        ?: $order?->phone
        ?: $order?->client?->phone
        ?: data_get($incident->meta, 'client_phone')
        ?: 'Téléphone non renseigné';

    $shopName = $shop?->display_name
        ?: $shop?->name
        ?: data_get($incident->meta, 'shop_name')
        ?: 'Boutique non renseignée';
    $shopPhone = $shop?->whatsapp
        ?: data_get($incident->meta, 'shop_phone')
        ?: 'Téléphone non renseigné';

    $pickup = collect([
        $assignment?->pickup_address,
        $shop?->address,
        $shop?->commune,
    ])->filter()->unique()->implode(' · ');
    if ($pickup === '') {
        $pickup = collect([
            data_get($incident->meta, 'pickup_address'),
            data_get($incident->meta, 'pickup_commune'),
        ])->filter()->unique()->implode(' · ');
    }
    $pickup = $pickup ?: 'Point de collecte non renseigné';

    $destination = collect([
        $order?->delivery_address,
        $order?->delivery_quartier,
        $order?->delivery_commune,
        $order?->delivery_city,
    ])->filter()->unique()->implode(' · ');
    if ($destination === '') {
        $destination = collect([
            data_get($incident->meta, 'destination_address'),
            data_get($incident->meta, 'destination_quartier'),
            data_get($incident->meta, 'destination_commune'),
            data_get($incident->meta, 'destination_city'),
            $assignment?->delivery_address,
        ])->filter()->unique()->implode(' · ');
    }
    $destination = $destination ?: 'Destination non renseignée';

    $driverName = $driver?->name ?: data_get($incident->meta, 'driver_name') ?: 'Non affecté';
    $driverPhone = $driver?->phone ?: data_get($incident->meta, 'driver_phone') ?: 'Téléphone non renseigné';
    $driverVehicle = $driver?->vehicle ?: data_get($incident->meta, 'driver_vehicle') ?: 'Véhicule non renseigné';
    $driverZone = $driver?->zone ?: data_get($incident->meta, 'driver_zone') ?: 'Zone non renseignée';
    $driverOnline = $driver ? (bool) $driver->is_online : data_get($incident->meta, 'driver_online');
    $driverStatus = $driverOnline === null ? 'État de connexion non renseigné' : ($driverOnline ? 'En ligne' : 'Hors ligne');

    $paymentLabels = [
        'paid' => 'Payé',
        'pending' => 'En attente',
        'authorized' => 'Autorisé',
        'partially_paid' => 'Partiellement payé',
        'partial' => 'Partiellement payé',
        'commission_paid' => 'Payé',
        'verified' => 'Vérifié',
        'escrow_held' => 'Paiement sécurisé',
        'failed' => 'Échec du paiement',
        'cancelled' => 'Annulé',
        'canceled' => 'Annulé',
        'refunded' => 'Remboursé',
        'cash_on_delivery' => 'Paiement à la livraison',
        'cod' => 'Paiement à la livraison',
    ];
    $paymentStatus = (string) ($order?->payment_status ?: data_get($incident->meta, 'payment_status', ''));
    $paymentLabel = $paymentLabels[$paymentStatus] ?? ($paymentStatus !== '' ? ucfirst(str_replace('_', ' ', $paymentStatus)) : 'Non renseigné');

    $deliveryStatusLabels = [
        'ready_for_pickup' => 'Prête pour enlèvement',
        'assigned' => 'Livreur affecté',
        'picked_up' => 'Collectée',
        'in_transit' => 'En transit',
        'late' => 'En retard',
        'delivered' => 'Livrée',
        'failed' => 'Livraison interrompue',
        'delivery_failed' => 'Livraison interrompue',
        'cancelled' => 'Annulée',
        'canceled' => 'Annulée',
    ];
    $rawDeliveryStatus = (string) ($item?->delivery_status ?: data_get($incident->meta, 'delivery_status_before_incident', ''));
    $deliveryStatusLabel = $item?->delivery_status_label
        ?: data_get($incident->meta, 'delivery_status_label')
        ?: ($deliveryStatusLabels[$rawDeliveryStatus] ?? ($rawDeliveryStatus !== '' ? ucfirst(str_replace('_', ' ', $rawDeliveryStatus)) : 'Non renseigné'));

    $responsibilityLabels = [
        'vehicle' => 'Véhicule',
        'driver' => 'Livreur',
        'client' => 'Client',
        'point_vente' => 'Boutique',
        'transport' => 'Trafic / événement externe',
        'ovanie' => 'OVANIE Logistics',
        'unknown' => 'À déterminer',
    ];
    $responsibilityLabel = $responsibilityLabels[$incident->responsibility] ?? 'À déterminer';

    $sourceLabels = [
        'ovanie_driver' => 'Application du livreur OVANIE',
        'ovanie_driver_app' => 'Application du livreur OVANIE',
        'seller_driver' => 'Application du chauffeur vendeur',
        'seller_driver_app' => 'Application du chauffeur vendeur',
        'support_ai' => 'Assistance client automatisée',
        'support_transfer' => 'Transfert de l’assistance client',
        'driver_phone' => 'Livreur — appel / WhatsApp',
        'client_support' => 'Client — assistance / téléphone',
        'shop_contact' => 'Boutique — appel / WhatsApp',
        'system_alert' => 'Alerte automatique / GPS',
        'operations_control' => 'Contrôle du centre logistique',
        'logistics' => 'Contrôle du centre logistique',
    ];
    $sourceCode = data_get($incident->meta, 'signal_source') ?: $incident->reported_by_type;
    $isDriverSource = in_array($sourceCode, ['ovanie_driver','ovanie_driver_app','driver','delivery_driver'], true);
    $isSellerSource = in_array($sourceCode, ['seller_driver','seller_driver_app','seller','vendor','seller_app','vendor_app'], true);
    $isClientSource = in_array($sourceCode, ['support_ai','support_transfer','client','customer','client_app'], true);
    $vendorReviewTypes = ['produit_endommage','produit_incomplet','quantite_incorrecte','probleme_chargement','litige_client'];
    $sourceRoleLabel = $isDriverSource ? 'Livreur' : ($isSellerSource ? 'Vendeur / boutique' : ($isClientSource ? 'Client' : 'Système'));
    $sourceLabel = data_get($incident->meta, 'signal_source_label')
        ?: ($sourceLabels[$sourceCode] ?? $sourceLabels[$incident->reported_by_type] ?? 'Origine non enregistrée');

    $conversation = $incident->supportConversations->first();
    $handoff = $incident->supportHandoffs->sortByDesc('id')->first();
    $ticket = $conversation?->ticket ?? $handoff?->ticket;
    $supportAssignee = $handoff?->assignee ?? $ticket?->assignee;

    $sourceActor = data_get($incident->meta, 'source_actor_name');
    $sourcePhone = data_get($incident->meta, 'source_actor_phone');
    if (!$sourceActor) {
        if (in_array($sourceCode, ['driver_phone', 'ovanie_driver', 'ovanie_driver_app', 'seller_driver', 'seller_driver_app'], true)) {
            $sourceActor = $driverName;
            $sourcePhone = $sourcePhone ?: $driverPhone;
        } elseif (in_array($sourceCode, ['client_support', 'support_ai', 'support_transfer'], true)) {
            $sourceActor = $conversation?->requester?->name ?: $clientName;
            $sourcePhone = $sourcePhone ?: $clientPhone;
        } elseif ($sourceCode === 'shop_contact') {
            $sourceActor = $shopName;
            $sourcePhone = $sourcePhone ?: $shopPhone;
        } elseif ($sourceCode === 'system_alert') {
            $sourceActor = 'Supervision automatique OVANIE';
        } elseif (in_array($sourceCode, ['operations_control', 'logistics'], true)) {
            $sourceActor = data_get($incident->meta, 'recorded_by_name') ?: 'Centre logistique OVANIE';
        }
    }
    $sourceActor = $sourceActor ?: 'Signalant non enregistré';
    $sourcePhone = $sourcePhone ?: 'Coordonnée non enregistrée';

    $sourceReference = data_get($incident->meta, 'reporter_reference') ?: ($ticket?->reference ?? null);
    $sourceNote = data_get($incident->meta, 'reporter_note');
    $recordedBy = data_get($incident->meta, 'recorded_by_name') ?: (str_contains((string) $sourceCode, 'app') || $sourceCode === 'system_alert' ? 'Création automatique' : 'Non enregistré');

    $formatMetaDate = function ($value, $fallback = 'Non enregistré') {
        if (!$value) return $fallback;
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };
    $sourceReceivedAt = $formatMetaDate(data_get($incident->meta, 'source_received_at'), $incident->occurred_at?->format('d/m/Y H:i:s') ?: 'Non enregistré');

    $gpsLat = $incident->latitude ?? $latestLocation?->latitude ?? $driver?->latitude ?? $item?->driver_latitude;
    $gpsLng = $incident->longitude ?? $latestLocation?->longitude ?? $driver?->longitude ?? $item?->driver_longitude;
    $gpsRecordedAt = data_get($incident->meta, 'gps_recorded_at')
        ?: $latestLocation?->recorded_at
        ?: $driver?->last_seen_at
        ?: $item?->driver_location_updated_at;
    $gpsSourceLabels = [
        'driver_latest_location' => 'Dernière position GPS du livreur',
        'driver_profile' => 'Dernière position enregistrée du livreur',
        'order_item' => 'Dernière position enregistrée sur la mission',
        'seller_driver_report' => 'Position transmise avec le signalement',
        'seller_tracking_session' => 'Suivi GPS du chauffeur vendeur',
    ];
    $gpsSource = $gpsSourceLabels[data_get($incident->meta, 'gps_source')] ?? 'Source GPS non enregistrée';
    $mapPoints = ($gpsLat !== null && $gpsLng !== null)
        ? [[
            'lat' => (float) $gpsLat,
            'lng' => (float) $gpsLng,
            'label' => $driverName !== 'Non affecté' ? $driverName : $destination,
            'color' => '#ff1741',
        ]]
        : [];

    $etaClient = $assignment?->estimated_delivery_at?->format('d/m/Y H:i') ?: 'Non calculée';
    $proof = $incident->photo_path ? route('logistics.private-documents.incident', $incident) : null;
    $notes = $incident->meta['notes'] ?? [];

    $supportChannelLabels = [
        'phone' => 'Téléphone',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'web' => 'Formulaire web',
        'chat' => 'Messagerie',
    ];
    $supportStatusLabels = [
        'open' => 'Ouvert',
        'in_progress' => 'En cours',
        'pending' => 'En attente',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
    ];
    $supportChannel = $ticket?->channel ? ($supportChannelLabels[$ticket->channel] ?? ucfirst(str_replace('_', ' ', $ticket->channel))) : 'Aucun canal lié';
    $supportStatus = $ticket?->status ? ($supportStatusLabels[$ticket->status] ?? ucfirst(str_replace('_', ' ', $ticket->status))) : 'Aucun ticket lié';

    $typeLabel = $types[$incident->incident_type]
        ?? data_get($incident->meta, 'incident_type_label')
        ?? ucfirst(str_replace('_', ' ', (string) $incident->incident_type));

    $impactLevel = (string) data_get($incident->meta, 'impact_level', 'none');
    $impactLabel = ($impactLevels ?? [
        'none' => 'Aucun impact confirmé',
        'delay' => 'Retard probable',
        'blocked' => 'Livraison interrompue',
        'rescheduled' => 'Livraison à reprogrammer',
    ])[$impactLevel] ?? ucfirst(str_replace('_', ' ', $impactLevel));
    $deliveryInterrupted = (bool) data_get($incident->meta, 'delivery_interrupted', false);
    $customerNotificationRequired = (bool) data_get($incident->meta, 'customer_notification_required', false);
    $customerNotificationAt = data_get($incident->meta, 'customer_notification_sent_at') ?: data_get($incident->meta, 'customer_notification_requested_at');
    $vendorNotificationAt = data_get($incident->meta, 'vendor_notification_sent_at');
    $customerNotificationLabel = $customerNotificationAt ? 'Client informé' : ($customerNotificationRequired ? 'Client à informer après qualification' : 'Information client non requise');
    $vendorNotificationRequired = $isClientSource && in_array($incident->incident_type, $vendorReviewTypes, true);
    $canNotifyClient = ($isDriverSource || $isSellerSource) && !in_array($incident->status, ['resolved','closed'], true);
    $canForwardVendor = $vendorNotificationRequired && !in_array($incident->status, ['resolved','closed'], true);
@endphp

<x-operations.directory-header
    title="Détail incident"
    section="Incidents"
    :url="route('logistics.incidents.index')"
    subtitle="Données réelles de la mission, origine du signalement, preuve et traitement"
>
    <a class="ops-button" href="{{ route('logistics.incidents.index') }}">← Retour à la liste</a>
    <a class="ops-button" href="{{ route('logistics.handoffs.index', ['q' => D::ref($incident, 'INC')]) }}">Ouvrir l’assistance ↗</a>
    <form method="post" action="{{ route('logistics.incidents.update', $incident) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="resolved">
        <button class="ops-button ops-button-primary" @disabled(in_array($incident->status, ['resolved', 'closed'], true))>✓ Marquer comme résolu</button>
    </form>
</x-operations.directory-header>

<div class="directory-kpis six">
    @foreach([
        ['list', 'Incident', D::ref($incident, 'INC'), 'blue'],
        ['alert', 'Type', $typeLabel, 'red'],
        ['alert', 'Priorité', $severityLabel, $color],
        ['check', 'Statut', $statusLabel, $tone],
        ['truck', 'Mission concernée', $mission, 'blue'],
        ['calendar', 'Dernière mise à jour', $incident->updated_at?->format('d/m/Y H:i') ?: 'Non renseignée', 'blue'],
    ] as [$icon, $label, $value, $c])
        <x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$c"/>
    @endforeach
</div>

<div class="directory-detail-grid">
    <div class="directory-stack">
        <x-operations.panel title="Informations sur l’incident" icon="list">
            <div class="directory-two">
                <dl class="directory-facts incident-detail-facts">
                    <dt>Date et heure</dt><dd>{{ $incident->occurred_at?->format('d/m/Y H:i:s') ?: 'Non renseignées' }}</dd>
                    <dt>Type</dt><dd>{{ $typeLabel }}</dd>
                    <dt>Gravité</dt><dd><x-operations.tag :tone="$color">{{ $severityLabel }}</x-operations.tag></dd>
                    <dt>Responsabilité présumée</dt><dd>{{ $responsibilityLabel }}</dd>
                    <dt>Impact livraison</dt><dd><span class="incident-impact-badge" data-impact="{{ $impactLevel }}">{{ $impactLabel }}</span></dd>
                    <dt>État du dossier</dt><dd><x-operations.tag :tone="$tone">{{ $statusLabel }}</x-operations.tag></dd>
                    <dt>Information client</dt><dd>{{ $customerNotificationLabel }}@if($customerNotificationAt)<small>{{ $formatMetaDate($customerNotificationAt) }}</small>@endif</dd>
                    <dt>Description</dt><dd>{{ $incident->description ?: 'Description non renseignée' }}</dd>
                </dl>
                <div class="incident-origin-summary">
                    <strong>Origine du signalement</strong>
                    <dl class="directory-facts incident-detail-facts" style="margin-top:8px">
                        <dt>Signalé par</dt><dd>{{ $sourceRoleLabel }}</dd>
                        <dt>Canal</dt><dd>{{ $sourceLabel }}</dd>
                        <dt>Signalant</dt><dd>{{ $sourceActor }}<small>{{ $sourcePhone }}</small></dd>
                        <dt>Reçu le</dt><dd>{{ $sourceReceivedAt }}</dd>
                        <dt>Référence</dt><dd>{{ $sourceReference ?: 'Aucune référence externe' }}</dd>
                        <dt>Enregistré par</dt><dd>{{ $recordedBy }}</dd>
                        <dt>Note de provenance</dt><dd>{{ $sourceNote ?: 'Aucune note de provenance' }}</dd>
                    </dl>
                </div>
            </div>
        </x-operations.panel>

        <x-operations.panel title="Mission concernée — données OVANIE" icon="truck">
            <div class="incident-real-data-grid">
                <div class="incident-real-card"><span>Mission</span><strong>{{ $mission }}</strong><small>Commande : {{ $orderNumber }}</small></div>
                <div class="incident-real-card"><span>État de livraison</span><strong>{{ $deliveryStatusLabel }}</strong><small>Paiement : {{ $paymentLabel }}</small></div>
                <div class="incident-real-card"><span>Impact opérationnel</span><strong>{{ $impactLabel }}</strong><small>{{ $deliveryInterrupted ? 'Mission interrompue pour traitement' : 'Mission maintenue active si son statut métier le permet' }}</small></div>
                <div class="incident-real-card"><span>Client</span><strong>{{ $clientName }}</strong><small>{{ $clientPhone }}</small></div>
                <div class="incident-real-card"><span>Boutique de collecte</span><strong>{{ $shopName }}</strong><small>{{ $pickup }}</small></div>
                <div class="incident-real-card"><span>Destination client</span><strong>{{ $destination }}</strong><small>Arrivée estimée : {{ $etaClient }}</small></div>
                <div class="incident-real-card"><span>Livreur et véhicule</span><strong>{{ $driverName }} — {{ $driverVehicle }}</strong><small>{{ $driverStatus }} · {{ $driverZone }} · {{ $driverPhone }}</small></div>
                <div class="incident-real-card"><span>Produit concerné</span><strong>{{ $product?->name ?: 'Produit non renseigné' }}</strong><small>Quantité : {{ $item?->quantity ?? 'Non renseignée' }}</small></div>
                <div class="incident-real-card"><span>Référence article</span><strong>{{ $item?->id ? 'Article #' . $item->id : 'Non renseignée' }}</strong><small>Fournisseur logistique : OVANIE Logistics</small></div>
            </div>
            @if($incident->order_item_id)
                <div style="margin-top:12px"><a class="directory-link" href="{{ route('logistics.shipments.details', $incident->order_item_id) }}">Voir la mission complète ↗</a></div>
            @endif
        </x-operations.panel>

        <x-operations.panel title="Preuve et pièces jointes" icon="list">
            <div class="directory-two">
                @if($proof && !str_ends_with(strtolower((string) $incident->photo_path), '.pdf'))
                    <img class="directory-preview" src="{{ $proof }}" alt="Preuve de l’incident">
                @else
                    <div class="directory-placeholder"><x-operations.icon name="list"/>{{ $proof ? 'Document PDF joint' : 'Aucune preuve jointe' }}</div>
                @endif
                <div class="directory-files">
                    @if($proof)
                        <div><x-operations.icon name="list"/><span>{{ basename($incident->photo_path) }}<small>{{ $incident->occurred_at?->format('d/m/Y H:i') }}</small></span><a href="{{ $proof }}" aria-label="Télécharger la preuve"><x-operations.icon name="download"/></a></div>
                    @endif
                    <div><x-operations.icon name="pin"/><span>Position GPS<br>{{ $gpsLat !== null ? number_format((float) $gpsLat, 6, '.', '') : 'Non disponible' }}, {{ $gpsLng !== null ? number_format((float) $gpsLng, 6, '.', '') : 'Non disponible' }}<small>{{ $gpsSource }} · {{ $formatMetaDate($gpsRecordedAt, 'Aucune date GPS enregistrée') }}</small></span></div>
                </div>
            </div>
        </x-operations.panel>

        <x-operations.panel title="Historique de traitement" icon="clock">
            <div class="directory-history">
                <div><time>{{ $incident->occurred_at?->format('H:i') ?: '—' }}</time><span>Signalement reçu</span><small>{{ $sourceLabel }} — {{ $incident->description }}</small></div>
                @if($customerNotificationAt)<div><time>{{ \Carbon\Carbon::parse($customerNotificationAt)->format('H:i') }}</time><span>Client informé</span><small>Le client a reçu une information sur l’impact possible de l’incident sur sa livraison.</small></div>@endif
                @if($vendorNotificationAt)<div><time>{{ \Carbon\Carbon::parse($vendorNotificationAt)->format('H:i') }}</time><span>Vendeur informé</span><small>Le dossier client a été transmis à la boutique concernée pour vérification et retour.</small></div>@endif
                @if(data_get($incident->meta, 'teams_notification_requested_at'))<div><time>{{ \Carbon\Carbon::parse(data_get($incident->meta, 'teams_notification_requested_at'))->format('H:i') }}</time><span>Équipes internes notifiées</span><small>Notification opérationnelle envoyée aux équipes concernées.</small></div>@endif
                @if($incident->rescheduled_at)<div><time>{{ $incident->rescheduled_at->format('H:i') }}</time><span>Mission reprogrammée</span><small>{{ $incident->next_action ?: 'Nouvelle planification enregistrée' }}</small></div>@endif
                @if($incident->resolved_at)<div><time>{{ $incident->resolved_at->format('H:i') }}</time><span>Incident résolu</span><small>{{ $incident->resolution_note ?: 'Résolution enregistrée' }}</small></div>@endif
            </div>
        </x-operations.panel>
    </div>

    <aside class="directory-stack">
        <x-operations.panel title="État et traitement" icon="settings" id="treatment">
            <form class="directory-form" method="post" action="{{ route('logistics.incidents.update', $incident) }}">
                @csrf
                @method('PATCH')
                <dl class="directory-facts incident-detail-facts">
                    <dt>Statut</dt><dd><select name="status">@foreach(['open','in_progress','rescheduled','resolved','closed'] as $s)<option value="{{ $s }}" @selected($incident->status === $s)>{{ ucfirst((string) D::incidentStatus($s)[0]) }}</option>@endforeach</select></dd>
                    <dt>Priorité</dt><dd><x-operations.tag :tone="$color">{{ $severityLabel }}</x-operations.tag></dd>
                    <dt>Assigné à</dt><dd>{{ $supportAssignee?->name ?? 'Non assigné' }}</dd>
                    <dt>Reprogrammation</dt><dd><input type="datetime-local" name="rescheduled_at" value="{{ $incident->rescheduled_at?->format('Y-m-d\TH:i') }}"></dd>
                    <dt>Prochaine action</dt><dd><input name="next_action" value="{{ $incident->next_action }}" maxlength="255" placeholder="Action à effectuer"></dd>
                </dl>
                <button class="ops-button ops-button-primary" style="margin-top:10px">Enregistrer le traitement</button>
            </form>
        </x-operations.panel>

        <x-operations.panel title="Assistance liée" icon="help">
            <dl class="directory-facts incident-detail-facts">
                <dt>Ticket d’assistance</dt><dd>{{ $ticket?->reference ?? ($ticket ? '#' . $ticket->id : 'Aucun ticket lié') }}</dd>
                <dt>Canal</dt><dd>{{ $supportChannel }}</dd>
                <dt>Statut du ticket</dt><dd>{{ $supportStatus }}</dd>
                <dt>Responsable</dt><dd>{{ $supportAssignee?->name ?? 'Non assigné' }}</dd>
            </dl>
            <br><a class="directory-link" href="{{ route('logistics.handoffs.index') }}">Ouvrir l’assistance ↗</a>
        </x-operations.panel>

        <x-operations.panel title="Localisation GPS réelle" icon="pin">
            @if($mapPoints)
                <div class="directory-map small" data-directory-map="{{ json_encode($mapPoints) }}"></div>
            @else
                <div class="incident-detail-map-empty">Aucune position GPS enregistrée pour cette mission.</div>
            @endif
            <dl class="directory-facts incident-detail-facts" style="margin-top:10px">
                <dt>Latitude</dt><dd>{{ $gpsLat !== null ? number_format((float) $gpsLat, 6, '.', '') : 'Non disponible' }}</dd>
                <dt>Longitude</dt><dd>{{ $gpsLng !== null ? number_format((float) $gpsLng, 6, '.', '') : 'Non disponible' }}</dd>
                <dt>Source</dt><dd>{{ $gpsSource }}</dd>
                <dt>Dernière mise à jour</dt><dd>{{ $formatMetaDate($gpsRecordedAt, 'Aucune position reçue') }}</dd>
            </dl>
        </x-operations.panel>

        <x-operations.panel title="Communication opérationnelle" icon="bolt">
            <div class="incident-communication-card">
                @if($canNotifyClient)
                    <div class="incident-communication-line">
                        <div><strong>Information au client</strong><small>{{ $customerNotificationAt ? 'Le client a déjà été informé.' : 'Informer le client que l’incident peut modifier le délai de livraison.' }}</small></div>
                        @if($customerNotificationAt)
                            <span class="incident-action-state is-done">Client informé</span>
                        @else
                            <form method="post" action="{{ route('logistics.incidents.update',$incident) }}">@csrf @method('PATCH')<input type="hidden" name="incident_action" value="notify_client"><button class="incident-row-action is-client" type="submit">Informer le client</button></form>
                        @endif
                    </div>
                @endif
                @if($canForwardVendor)
                    <div class="incident-communication-line">
                        <div><strong>Transmission au vendeur</strong><small>{{ $vendorNotificationAt ? 'La boutique a déjà reçu le dossier.' : 'Transmettre au vendeur le problème de commande ou de produit signalé par le client.' }}</small></div>
                        @if($vendorNotificationAt)
                            <span class="incident-action-state is-done">Vendeur informé</span>
                        @else
                            <form method="post" action="{{ route('logistics.incidents.update',$incident) }}">@csrf @method('PATCH')<input type="hidden" name="incident_action" value="forward_vendor"><button class="incident-row-action is-vendor" type="submit">Transmettre au vendeur</button></form>
                        @endif
                    </div>
                @endif
                @if(!$canNotifyClient && !$canForwardVendor)
                    <div class="incident-communication-note">Aucune communication automatique n’est proposée pour ce type de dossier. Le responsable logistique peut poursuivre la qualification et utiliser les contacts réels affichés ci-dessous.</div>
                @endif
            </div>
        </x-operations.panel>

        <x-operations.panel title="Actions rapides" icon="bolt">
            <div class="directory-form-grid">
                @if($driver?->phone)<a class="ops-button ops-button-primary" href="tel:{{ $driver->phone }}">Contacter le livreur</a>@endif
                @if($clientPhone !== 'Téléphone non renseigné')<a class="ops-button" href="tel:{{ $clientPhone }}">Contacter le client</a>@endif
                @if($proof)<a class="ops-button" href="{{ $proof }}">Télécharger la preuve</a>@endif
                <button class="ops-button" type="button" data-note-focus="#incident-note">Ajouter une note</button>
                <a class="ops-button" href="#treatment">Reprogrammer la mission</a>
            </div>
        </x-operations.panel>

        <x-operations.panel title="Notes internes" icon="list">
            <div class="directory-notes">
                @forelse($notes as $note)
                    <div><span class="directory-avatar">RL</span><span><strong>{{ $note['author'] ?? 'Responsable logistique' }}</strong><p>{{ $note['text'] ?? '' }}</p><small>{{ $formatMetaDate($note['at'] ?? null) }}</small></span></div>
                @empty
                    <div class="incident-detail-note">Aucune note interne enregistrée.</div>
                @endforelse
            </div>
            <details class="directory-note-disclosure"><summary class="directory-link">+ Ajouter une note</summary><form class="directory-form" method="post" action="{{ route('logistics.incidents.update', $incident) }}">@csrf @method('PATCH')<label>Note interne<textarea name="note" id="incident-note" required maxlength="1000"></textarea></label><button class="ops-button">Enregistrer la note</button></form></details>
        </x-operations.panel>
    </aside>
</div>
@endsection
