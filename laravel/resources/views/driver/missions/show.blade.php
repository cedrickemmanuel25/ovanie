@extends('driver.layouts.app')
@section('title', $mission['mission_number'].' | OVANIE')
@section('page-title', 'Détail de la mission')

@push('styles')
<link href="https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.css" rel="stylesheet">
<link href="{{ asset('css/ovanie-delivery-map.css') }}?v={{ @filemtime(public_path('css/ovanie-delivery-map.css')) ?: '20260718-4' }}" rel="stylesheet">
@endpush

@section('content')
<div class="driver-page-heading driver-page-heading--mission">
    <div>
        <div class="driver-breadcrumb">
            <a href="{{ route('driver.missions.index') }}">Mes missions</a>
            <i data-lucide="chevron-right"></i>
            <strong>{{ $mission['mission_number'] }}</strong>
        </div>
        <h1>{{ $mission['mission_number'] }}</h1>
        <p>{{ $mission['order_number'] }} · Livraison vers {{ $mission['commune'] ?: 'destination à confirmer' }}</p>
    </div>
    <span class="driver-status driver-status--{{ $mission['status'] }}">{{ $mission['status_label'] }}</span>
</div>

<section class="driver-mission-summary">
    <div><span>Client</span><strong>{{ $mission['client_name'] }}</strong></div>
    <div><span>Collectes</span><strong>{{ $mission['pickup_count'] }} point(s)</strong></div>
    <div><span>Charge totale</span><strong>{{ number_format($mission['total_weight_kg'], 2, ',', ' ') }} kg · {{ number_format($mission['total_volume_m3'], 2, ',', ' ') }} m³</strong></div>
    <div><span>Véhicule</span><strong>{{ $mission['vehicle_label'] ?: 'À confirmer' }}</strong></div>
    <div><span>Livraison prévue</span><strong>{{ $mission['estimated_delivery_at']?->format('d/m/Y H:i') ?: 'À confirmer' }}</strong></div>
</section>

<section class="driver-operation-panel">
    <div class="driver-operation-panel__head">
        <div>
            <span class="driver-section-kicker">GESTION DE LA MISSION</span>
            <h2>Actions opérationnelles</h2>
            <p>Effectuez uniquement l’action correspondant à l’étape actuelle.</p>
        </div>
        <div class="driver-preparation-status">
            <div>
                <span>Préparation</span>
                <strong>{{ $mission['ready_count'] }}/{{ $mission['line_count'] }} référence(s)</strong>
            </div>
            <div class="driver-inline-progress driver-inline-progress--large">
                <div><span style="width: {{ $mission['preparation_percent'] }}%"></span></div>
                <strong>{{ $mission['preparation_percent'] }}%</strong>
            </div>
        </div>
    </div>

    <div class="driver-operation-grid">
        <article class="driver-operation-card">
            <div class="driver-operation-card__title">
                <span><i data-lucide="clipboard-check"></i></span>
                <div><h3>Mission</h3><p>Acceptation et démarrage</p></div>
            </div>

            @if(in_array($mission['status'], ['planned', 'assigned']))
                <form method="POST" action="{{ route('driver.missions.accept', $mission['mission_number']) }}">
                    @csrf
                    <button class="driver-btn driver-btn--primary driver-btn--block" type="submit">
                        <i data-lucide="circle-check"></i>
                        Accepter la mission
                    </button>
                </form>
                <details class="driver-collapsible driver-collapsible--danger">
                    <summary>Je ne peux pas assurer cette mission</summary>
                    <form method="POST" action="{{ route('driver.missions.reject', $mission['mission_number']) }}">
                        @csrf
                        <textarea name="reason" rows="3" placeholder="Motif du refus (facultatif)"></textarea>
                        <button class="driver-btn driver-btn--danger driver-btn--block" type="submit">Refuser la mission</button>
                    </form>
                </details>
            @elseif($mission['status'] === 'accepted')
                <div class="driver-operation-note {{ $mission['preparation_percent'] >= 100 ? 'is-ready' : '' }}">
                    <i data-lucide="{{ $mission['preparation_percent'] >= 100 ? 'circle-check' : 'clock-3' }}"></i>
                    <span>{{ $mission['preparation_percent'] >= 100 ? 'Tous les articles sont prêts.' : 'Le départ sera disponible quand tous les articles seront prêts.' }}</span>
                </div>
                <form method="POST" action="{{ route('driver.missions.start', $mission['mission_number']) }}">
                    @csrf
                    <button class="driver-btn driver-btn--primary driver-btn--block" type="submit" {{ $mission['preparation_percent'] < 100 ? 'disabled' : '' }}>
                        <i data-lucide="play"></i>
                        Démarrer les collectes
                    </button>
                </form>
            @elseif($mission['status'] === 'collecting')
                <div class="driver-operation-note"><i data-lucide="package-search"></i><span>Récupérez tous les articles indiqués aux points de collecte.</span></div>
                <button type="button" class="driver-stage-button" data-stage="loaded">
                    <i data-lucide="package-check"></i><span>Confirmer le chargement terminé</span><i data-lucide="chevron-right"></i>
                </button>
            @elseif($mission['status'] === 'picked_up')
                <div class="driver-operation-note is-ready"><i data-lucide="package-check"></i><span>Le chargement est terminé. Confirmez votre départ vers le client.</span></div>
                <button type="button" class="driver-stage-button" data-stage="in_transit">
                    <i data-lucide="truck"></i><span>Démarrer le trajet vers le client</span><i data-lucide="chevron-right"></i>
                </button>
            @elseif($mission['status'] === 'in_transit')
                <div class="driver-operation-note is-ready"><i data-lucide="navigation"></i><span>Vous êtes en route vers le client.</span></div>
                <button type="button" class="driver-stage-button" data-stage="arrived">
                    <i data-lucide="map-pin-check"></i><span>Confirmer l’arrivée à destination</span><i data-lucide="chevron-right"></i>
                </button>
            @elseif($mission['status'] === 'arrived')
                <div class="driver-operation-note is-ready"><i data-lucide="map-pin-check"></i><span>Vous êtes arrivé. Remettez les articles puis utilisez le code du client.</span></div>
            @elseif($mission['status'] === 'incident')
                <div class="driver-operation-note"><i data-lucide="triangle-alert"></i><span>La mission est bloquée par un incident. Attendez les instructions du responsable logistique.</span></div>
            @else
                <div class="driver-operation-note is-ready"><i data-lucide="badge-check"></i><span>Cette mission est terminée.</span></div>
            @endif
        </article>

        <article class="driver-operation-card">
            <div class="driver-operation-card__title">
                <span><i data-lucide="navigation"></i></span>
                <div><h3>Suivi de position</h3><p>GPS ou suivi manuel</p></div>
            </div>

            @if(in_array($mission['status'], ['collecting', 'picked_up', 'in_transit', 'arrived', 'incident']))
                <div id="gps-feedback" class="driver-gps-state">
                    <span class="driver-gps-dot"></span>
                    <div><strong>GPS en attente</strong><small>Démarrez le suivi au début de la mission.</small></div>
                </div>
                <button id="start-gps" class="driver-btn driver-btn--primary driver-btn--block" type="button">
                    <i data-lucide="locate-fixed"></i>
                    Démarrer le GPS
                </button>
                <button id="stop-gps" class="driver-btn driver-btn--light driver-btn--block" type="button" hidden>
                    <i data-lucide="pause"></i>
                    Mettre en pause
                </button>
                <button id="gps-unavailable" class="driver-text-button" type="button">Je ne peux pas partager ma position</button>
                @if(config('delivery.local_simulation'))
                    <button id="simulate-route" class="driver-btn driver-btn--light driver-btn--block" type="button">
                        <i data-lucide="route"></i>
                        Simuler le trajet en local
                    </button>
                @endif
                <div id="manual-eta" class="driver-manual-gps" hidden>
                    <div class="driver-form-group"><label>Arrivée estimée</label><input type="datetime-local" id="manual-eta-at" value="{{ $mission['estimated_delivery_at']?->format('Y-m-d\TH:i') }}"></div>
                    <div class="driver-form-group"><label>Raison</label><select id="gps-reason"><option value="permission_denied">Autorisation refusée</option><option value="no_signal">Aucun signal</option><option value="device_issue">Problème téléphone</option><option value="battery_saving">Économie de batterie</option><option value="other">Autre</option></select></div>
                    <button id="confirm-no-gps" class="driver-btn driver-btn--dark driver-btn--block" type="button">Activer le suivi manuel</button>
                </div>
            @else
                <div class="driver-operation-note"><i data-lucide="info"></i><span>Le suivi sera disponible après le démarrage de la mission.</span></div>
            @endif
        </article>

        <article class="driver-operation-card">
            <div class="driver-operation-card__title">
                <span><i data-lucide="shield-check"></i></span>
                <div><h3>Remise et assistance</h3><p>Confirmation ou incident</p></div>
            </div>

            @if($mission['status'] === 'arrived')
                <p class="driver-operation-copy">Demandez le code à 6 chiffres au client après la remise complète des articles.</p>
                <input id="otp-code" class="driver-otp-input" inputmode="numeric" maxlength="6" placeholder="000000">
                <button id="verify-otp" class="driver-btn driver-btn--primary driver-btn--block" type="button">
                    <i data-lucide="badge-check"></i>
                    Confirmer la livraison
                </button>
            @else
                <div class="driver-operation-note"><i data-lucide="key-round"></i><span>La confirmation par code sera disponible à votre arrivée.</span></div>
            @endif

            @if(in_array($mission['status'], ['accepted', 'collecting', 'picked_up', 'in_transit', 'arrived', 'incident']))
                <details class="driver-collapsible driver-collapsible--danger">
                    <summary><i data-lucide="triangle-alert"></i> Signaler un incident</summary>
                    <form method="POST" action="{{ route('driver.missions.incident', $mission['mission_number']) }}">
                        @csrf
                        <select name="incident_type" required>
                            <option value="">Sélectionner un motif</option>
                            <option value="traffic_jam">Circulation importante</option>
                            <option value="client_absent">Client absent</option>
                            <option value="address_issue">Adresse imprécise</option>
                            <option value="vehicle_breakdown">Panne du véhicule</option>
                            <option value="accident">Accident</option>
                            <option value="product_damaged">Produit endommagé</option>
                            <option value="access_impossible">Accès impossible</option>
                            <option value="other">Autre</option>
                        </select>
                        <textarea name="description" rows="3" placeholder="Décrivez la situation"></textarea>
                        <button class="driver-btn driver-btn--danger driver-btn--block" type="submit">Transmettre l’incident</button>
                    </form>
                </details>
            @endif
        </article>
    </div>
</section>


<section class="driver-panel driver-map-panel">
    <div class="driver-panel__header">
        <div>
            <h2>Itinéraire de la mission</h2>
            <p>Les collectes sont regroupées avant la destination finale.</p>
        </div>
        <div class="driver-map-meta">
            <span><i data-lucide="route"></i> {{ data_get($mission, 'route_plan.distance_km') ? number_format(data_get($mission, 'route_plan.distance_km'), 1, ',', ' ').' km' : 'Distance à confirmer' }}</span>
            <span><i data-lucide="clock-3"></i> {{ data_get($mission, 'route_plan.duration_minutes') ? data_get($mission, 'route_plan.duration_minutes').' min' : 'Durée à confirmer' }}</span>
        </div>
    </div>
    <div id="driver-map" class="driver-map"></div>
</section>


<section class="driver-panel">
    <div class="driver-panel__header">
        <div>
            <h2>Points de collecte</h2>
            <p>Vérifiez les produits avant de quitter chaque point de retrait.</p>
        </div>
        <span class="driver-panel-counter">{{ $mission['pickup_count'] }} point(s)</span>
    </div>

    <div class="driver-stop-grid">
        @foreach($mission['pickup_stops'] as $index => $stop)
            <article class="driver-stop-card">
                <div class="driver-stop-card__head">
                    <span class="driver-stop-card__icon"><i data-lucide="store"></i></span>
                    <div>
                        <small>Collecte {{ $index + 1 }}</small>
                        <h3>{{ $stop['shop']?->name ?? 'Point de collecte' }}</h3>
                        <p><i data-lucide="map-pin"></i> {{ $stop['address'] ?? 'Adresse à confirmer' }}</p>
                    </div>
                </div>
                <div class="driver-stop-card__items">
                    @foreach(($stop['items'] ?? []) as $item)
                        <div>
                            <span>{{ $item->product?->name ?? 'Produit' }}</span>
                            <strong>{{ $item->quantity }} unité(s) · {{ number_format($item->logistics_weight_kg ?? 0, 2, ',', ' ') }} kg</strong>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v3.4.0/mapbox-gl.js"></script>
<script src="{{ asset('js/ovanie-delivery-map.js') }}?v={{ @filemtime(public_path('js/ovanie-delivery-map.js')) ?: '20260718-4' }}"></script>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const routePlan = @json($mission['route_plan']);
    const mapPoints = @json($mission['map_points'] ?? ($mission['route_plan']['points'] ?? []));
    const mapToken = @json(config('geo.mapbox.public_token') ?: env('MAPBOX_PUBLIC_TOKEN'));
    const deliveryAssetBase = @json(asset('images/delivery'));
    const missionVehicleCode = @json($mission['vehicle_code'] ?? $mission['vehicle_label'] ?? 'vehicle');
    const stageUrl = @json(route('driver.missions.stage', $mission['mission_number']));
    const locationUrl = @json(route('driver.missions.location', $mission['mission_number']));
    const noGpsUrl = @json(route('driver.missions.gps-unavailable', $mission['mission_number']));
    const otpUrl = @json(route('driver.missions.verify-otp', $mission['mission_number']));
    let watchId = null;
    let lastSent = 0;
    let lastPosition = null;
    let heartbeatTimer = null;
    let wakeLock = null;
    let driverMarker = null;
    let simulationTimer = null;

    const mapContainer = document.getElementById('driver-map');
    let driverMap = null;

    if (window.mapboxgl && mapToken && mapPoints?.length) {
        mapboxgl.accessToken = mapToken;
        const first = mapPoints[0];
        driverMap = new mapboxgl.Map({
            container: 'driver-map',
            style: @json(config('geo.mapbox.style_url', 'mapbox://styles/mapbox/streets-v12')),
            center: [first.longitude, first.latitude],
            zoom: 13,
            minZoom: 10.5,
            maxZoom: 18.5,
            renderWorldCopies: false,
            dragRotate: false,
            pitchWithRotate: false,
            cooperativeGestures: true,
        });
        OvanieDeliveryMap.applyMapConstraints(driverMap, {minZoom:10.5,maxZoom:18.5});
        driverMap.addControl(new mapboxgl.NavigationControl({showCompass: false}), 'top-right');

        driverMap.on('load', async () => {
            let geometry = validGeometry(routePlan.geometry) ? routePlan.geometry : await browserRoute(routePlan.points);
            const initialDriver=mapPoints.find(point=>point.type==='driver');
            const initialRouteState=geometry&&initialDriver
                ? OvanieDeliveryMap.remainingRoute(geometry,[Number(initialDriver.longitude),Number(initialDriver.latitude)],250)
                : {geometry,point:null};
            if (initialRouteState.geometry) drawRoute(initialRouteState.geometry);

            const visibleCoordinates = [];
            mapPoints.forEach(point => {
                const longitude = point.type==='driver'&&initialRouteState.point ? Number(initialRouteState.point[0]) : Number(point.longitude);
                const latitude = point.type==='driver'&&initialRouteState.point ? Number(initialRouteState.point[1]) : Number(point.latitude);
                if (!Number.isFinite(longitude) || !Number.isFinite(latitude)) return;
                const type = point.type === 'destination' ? 'destination' : (point.type === 'driver' ? 'vehicle' : 'shop');
                const options = {
                    assetBase: deliveryAssetBase,
                    vehicleCode: missionVehicleCode,
                    heading: point.heading,
                    stale: Boolean(point.completed),
                    offline: Boolean(point.completed),
                    animate: false,
                    label: point.name || (type === 'vehicle' ? 'Votre véhicule' : (type === 'destination' ? 'Destination' : 'Point de collecte')),
                };
                const mapMarker = OvanieDeliveryMap.createMarker(driverMap, [longitude, latitude], type, options)
                    .setPopup(new mapboxgl.Popup({offset: type === 'vehicle' ? 25 : 28, closeButton:false}).setHTML(`<strong>${escapeHtml(point.name)}</strong><br>${escapeHtml(point.address || '')}${point.completed ? '<br><small>Collecte terminée</small>' : ''}`));
                if (point.type === 'driver') driverMarker = mapMarker;
                visibleCoordinates.push([longitude, latitude]);
            });
            if (visibleCoordinates.length) OvanieDeliveryMap.fitOperationalBounds(driverMap, visibleCoordinates, {padding:{top:70,right:70,bottom:70,left:70},maxFitZoom:15,singleZoom:14,maxSpanKm:60,longTripZoom:11.5,duration:500});
        });
    } else if (mapContainer) {
        mapContainer.innerHTML = '<div class="driver-map-empty"><span><svg viewBox="0 0 24 24"><path d="M9 18 3.5 21V6L9 3l6 3 5.5-3v15L15 21l-6-3Z"></path><path d="M9 3v15"></path><path d="M15 6v15"></path></svg></span><strong>Coordonnées en attente</strong><p>Activez le GPS pour calculer le trajet jusqu’à la prochaine étape.</p></div>';
    }

    function validGeometry(geometry) {
        return geometry?.type === 'LineString' && Array.isArray(geometry.coordinates) && geometry.coordinates.length > 1;
    }

    async function browserRoute(points) {
        const coordinates = (points || [])
            .filter(point => Number.isFinite(Number(point.longitude)) && Number.isFinite(Number(point.latitude)))
            .slice(0, 25)
            .map(point => `${Number(point.longitude).toFixed(7)},${Number(point.latitude).toFixed(7)}`)
            .join(';');
        if (!coordinates || coordinates.split(';').length < 2) return null;

        try {
            const response = await fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/${coordinates}?access_token=${encodeURIComponent(mapToken)}&overview=full&geometries=geojson&steps=false&language=fr`, {cache: 'no-store'});
            const route = response.ok ? (await response.json())?.routes?.[0] : null;
            return validGeometry(route?.geometry) ? route.geometry : null;
        } catch (_) {
            return null;
        }
    }

    function drawRoute(geometry) {
        if (!driverMap || !driverMap.isStyleLoaded() || !validGeometry(geometry)) return;
        const data = {type: 'Feature', properties: {}, geometry};
        const source = driverMap.getSource('mission-route');
        if (source) {
            source.setData(data);
            return;
        }
        driverMap.addSource('mission-route', {type: 'geojson', data});
        driverMap.addLayer({id: 'mission-route-outline', type: 'line', source: 'mission-route', layout: {'line-cap':'round','line-join':'round'}, paint: {'line-color': '#ffffff', 'line-width': 9, 'line-opacity': .95}});
        driverMap.addLayer({id: 'mission-route-line', type: 'line', source: 'mission-route', layout: {'line-cap':'round','line-join':'round'}, paint: {'line-color': '#0d63ef', 'line-width': 5, 'line-opacity': .95}});
    }

    document.querySelectorAll('[data-stage]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            await post(stageUrl, {stage: button.dataset.stage});
            location.reload();
        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    }));

    const startGps = document.getElementById('start-gps');
    const stopGps = document.getElementById('stop-gps');
    const gpsFeedback = document.getElementById('gps-feedback');
    const missionStatus = @json($mission['status']);

    function setGpsFeedback(title, detail, tone = 'active') {
        if (!gpsFeedback) return;
        gpsFeedback.classList.toggle('is-active', tone === 'active');
        gpsFeedback.innerHTML = `<span class="driver-gps-dot ${tone === 'warning' ? 'is-warning' : ''}"></span><div><strong>${escapeHtml(title)}</strong><small>${escapeHtml(detail)}</small></div>`;
    }

    async function requestWakeLock() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => { wakeLock = null; });
        } catch (_) {}
    }

    async function transmitPosition(position, force = false) {
        if (!position?.coords) return;
        lastPosition = position;
        const now = Date.now();
        if (!force && now - lastSent < 15000) return;
        lastSent = now;

        const result = await post(locationUrl, {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            speed: position.coords.speed,
            heading: position.coords.heading,
        });

        setGpsFeedback('GPS actif', `Dernière position transmise à ${new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit', second:'2-digit'})}.`);
        const stableLongitude = Number(result?.location?.longitude ?? position.coords.longitude);
        const stableLatitude = Number(result?.location?.latitude ?? position.coords.latitude);
        if (validGeometry(result?.route?.geometry)) {
            const routeState=OvanieDeliveryMap.remainingRoute(result.route.geometry,[stableLongitude,stableLatitude],250);
            drawRoute(routeState.geometry);
            moveDriverMarker(routeState.point[0],routeState.point[1],result?.location?.heading ?? position.coords.heading);
        } else {
            moveDriverMarker(stableLongitude,stableLatitude,result?.location?.heading ?? position.coords.heading);
        }
    }

    function startHeartbeat() {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = window.setInterval(() => {
            if (lastPosition) {
                transmitPosition(lastPosition, true).catch(() => setGpsFeedback('Connexion GPS en attente', 'La dernière position sera renvoyée automatiquement.', 'warning'));
            }
        }, 45000);
    }

    function startGpsTracking({silent = false} = {}) {
        if (watchId !== null) return;
        if (!navigator.geolocation) {
            if (!silent) alert('Le GPS est indisponible sur cet appareil.');
            setGpsFeedback('GPS indisponible', 'Utilisez le suivi manuel.', 'warning');
            return;
        }

        setGpsFeedback('Activation du GPS', 'Autorisez la localisation précise sur cet appareil.', 'warning');
        requestWakeLock();
        navigator.geolocation.getCurrentPosition(
            position => transmitPosition(position, true).catch(() => setGpsFeedback('Connexion en attente', 'La position sera renvoyée automatiquement.', 'warning')),
            () => setGpsFeedback('Autorisation GPS nécessaire', 'Activez la localisation ou utilisez le suivi manuel.', 'warning'),
            {enableHighAccuracy: true, maximumAge: 0, timeout: 20000}
        );

        watchId = navigator.geolocation.watchPosition(
            position => transmitPosition(position).catch(() => setGpsFeedback('Connexion en attente', 'La position sera renvoyée automatiquement.', 'warning')),
            () => {
                OvanieDeliveryMap?.setMarkerState(driverMarker, {stale:true});
                setGpsFeedback('Signal GPS indisponible', 'La dernière position réelle reste visible pendant la reprise du signal.', 'warning');
            },
            {enableHighAccuracy: true, maximumAge: 10000, timeout: 30000}
        );

        startHeartbeat();
        if (startGps) startGps.hidden = true;
        if (stopGps) stopGps.hidden = false;
    }

    function stopGpsTracking() {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        if (wakeLock) wakeLock.release().catch(() => {});
        wakeLock = null;
        if (startGps) startGps.hidden = false;
        if (stopGps) stopGps.hidden = true;
        OvanieDeliveryMap?.setMarkerState(driverMarker, {stale:true, offline:true});
        setGpsFeedback('GPS en pause', 'Appuyez sur Démarrer le GPS pour reprendre.', 'warning');
    }

    function moveDriverMarker(longitude, latitude, heading = null) {
        if (!driverMap || !window.OvanieDeliveryMap || !Number.isFinite(longitude) || !Number.isFinite(latitude)) return;
        driverMarker = OvanieDeliveryMap.upsertMarker(
            driverMarker,
            driverMap,
            [longitude, latitude],
            'vehicle',
            {
                assetBase: deliveryAssetBase,
                vehicleCode: missionVehicleCode,
                heading,
                stale: false,
                offline: false,
                animate: Boolean(driverMarker),
                duration: 700,
                label: 'Votre position de livraison',
            }
        );
    }

    startGps?.addEventListener('click', () => startGpsTracking());
    stopGps?.addEventListener('click', stopGpsTracking);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && watchId !== null) {
            requestWakeLock();
            navigator.geolocation?.getCurrentPosition(position => transmitPosition(position, true).catch(() => {}), () => {}, {enableHighAccuracy:true, maximumAge:0, timeout:15000});
        }
    });

    if (['collecting', 'picked_up', 'in_transit', 'arrived'].includes(missionStatus)) {
        window.setTimeout(() => startGpsTracking({silent: true}), 600);
    }

    document.getElementById('gps-unavailable')?.addEventListener('click', () => {
        const manual = document.getElementById('manual-eta');
        if (manual) manual.hidden = !manual.hidden;
    });

    document.getElementById('confirm-no-gps')?.addEventListener('click', async () => {
        try {
            await post(noGpsUrl, {
                reason: document.getElementById('gps-reason').value,
                manual_eta_at: document.getElementById('manual-eta-at').value,
            });
            if (gpsFeedback) gpsFeedback.innerHTML = '<span class="driver-gps-dot is-warning"></span><div><strong>Suivi manuel actif</strong><small>Mettez les étapes à jour depuis cette page.</small></div>';
        } catch (error) {
            alert(error.message);
        }
    });

    document.getElementById('simulate-route')?.addEventListener('click', event => {
        const button = event.currentTarget;
        const geometry = validGeometry(routePlan?.geometry) ? routePlan.geometry : null;
        const coordinates = geometry?.coordinates || (routePlan?.points || []).map(point => [Number(point.longitude), Number(point.latitude)]).filter(pair => pair.every(Number.isFinite));
        if (coordinates.length < 2) return alert('Aucun itinéraire disponible pour la simulation locale.');

        window.clearInterval(simulationTimer);
        let index = 0;
        button.disabled = true;
        button.textContent = 'Simulation en cours…';
        simulationTimer = window.setInterval(async () => {
            const pair = coordinates[Math.min(index, coordinates.length - 1)];
            const fakePosition = {coords: {longitude: pair[0], latitude: pair[1], accuracy: 8, speed: 8, heading: null}};
            try { await transmitPosition(fakePosition, true); } catch (_) {}
            index += Math.max(1, Math.floor(coordinates.length / 35));
            if (index >= coordinates.length) {
                window.clearInterval(simulationTimer);
                button.disabled = false;
                button.textContent = 'Simuler le trajet en local';
            }
        }, 1200);
    });

    document.getElementById('verify-otp')?.addEventListener('click', async () => {
        const code = document.getElementById('otp-code').value.trim();
        try {
            await post(otpUrl, {delivery_otp_code: code});
            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Une erreur est survenue.');
        return data;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character]));
    }
})();
</script>
@endpush
