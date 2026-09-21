<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Mission de livraison | OVANIE</title>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.css" rel="stylesheet">
    <link href="{{ asset('css/ovanie-delivery-map.css') }}?v={{ @filemtime(public_path('css/ovanie-delivery-map.css')) ?: '20260718-4' }}" rel="stylesheet">
    <style>
        :root{--navy:#071f4f;--blue:#1769e8;--green:#0b8a63;--orange:#f97316;--ink:#10254a;--muted:#6d7d92;--line:#dfe7f0;--bg:#f4f7fb;--danger:#b42318}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--bg);color:var(--ink)}button,input,select,textarea{font:inherit}.shell{width:min(980px,100%);margin:auto;padding:18px 18px 40px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}.brand{display:flex;align-items:center;gap:11px;font-weight:950}.brand-mark{width:46px;height:46px;border-radius:14px;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:20px}.brand small{display:block;color:var(--muted);font-size:12px;font-weight:750;margin-top:2px}.badge{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;font-size:11px;font-weight:900}.badge.success{background:#dcfce7;color:#166534}.badge.warning{background:#fff2d6;color:#92400e}.badge.danger{background:#fee2e2;color:#991b1b}.card{background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 14px 40px rgba(12,35,72,.07)}.hero{padding:22px;margin-bottom:14px}.hero-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.kicker{color:var(--green);font-size:10px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.hero h1{font-size:29px;line-height:1.1;margin:7px 0 5px}.muted{color:var(--muted);font-size:13px;line-height:1.55}.state-pill{white-space:nowrap;border:1px solid #d8e3f2;background:#f4f8ff;color:#1b5fc5;border-radius:999px;padding:9px 12px;font-size:11px;font-weight:900}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:18px}.metric{border:1px solid #e7edf4;border-radius:14px;padding:12px 13px;background:#fbfcfe}.metric span{display:block;color:#8290a2;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.055em}.metric strong{display:block;margin-top:6px;font-size:14px;line-height:1.35}.action-card{padding:18px 20px;margin-bottom:14px;border-color:#cfdcf0;background:linear-gradient(135deg,#fff,#f6f9ff)}.action-row{display:flex;align-items:center;justify-content:space-between;gap:18px}.action-copy h2{margin:3px 0 5px;font-size:20px}.action-copy p{margin:0}.action-controls{min-width:290px;display:grid;gap:8px}.btn{min-height:48px;border-radius:13px;border:1px solid transparent;padding:0 16px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;gap:8px}.btn:disabled{opacity:.55;cursor:not-allowed}.btn-primary{background:var(--blue);color:#fff;box-shadow:0 8px 18px rgba(23,105,232,.18)}.btn-green{background:var(--green);color:#fff}.btn-dark{background:var(--navy);color:#fff}.btn-light{background:#fff;border-color:var(--line);color:var(--ink)}.btn-danger{background:#fff5f5;border-color:#ffd0d0;color:var(--danger)}.main-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:14px;align-items:start}.section{padding:19px}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:13px}.section h2{font-size:17px;margin:0}.map-shell{position:relative;height:390px;border-radius:15px;overflow:hidden;background:#edf2f7;border:1px solid var(--line)}.map{position:absolute;inset:0;height:100%;border:0;border-radius:0;overflow:hidden;background:#edf2f7}.map-empty{height:100%;display:grid;place-items:center;text-align:center;padding:28px;color:var(--muted)}.ovd-map-shell__loading{position:absolute;inset:0;z-index:8;display:grid;place-items:center;padding:30px;text-align:center;background:#edf2f7;color:var(--muted)}.ovd-map-shell__loading>div{max-width:460px}.ovd-map-shell__loading strong{display:block;color:var(--ink);font-size:15px;margin-bottom:7px}.ovd-map-shell__loading span{display:block;font-size:12px;line-height:1.55}.ovd-map-shell__loading.is-error{background:#fff7f7;color:#8f3131}.ovd-map-shell__loading.is-error strong{color:#a52323}.ovd-map-shell.is-ready .ovd-map-shell__loading{display:none}.map-recenter{min-height:36px;padding:0 12px;border:1px solid #d8e3ef;border-radius:10px;background:#fff;color:#24425f;font-size:10px;font-weight:900;cursor:pointer;box-shadow:0 5px 14px rgba(15,39,64,.08)}.map-recenter[hidden]{display:none}.route-meta{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:10px}.address-box{border:1px solid #e7edf4;border-radius:14px;padding:13px;background:#fbfcfe}.address-box span{display:block;color:#8290a2;font-size:9px;font-weight:950;text-transform:uppercase}.address-box strong{display:block;margin-top:6px;font-size:14px;line-height:1.45}.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.status-box{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px;border:1px solid #e7edf4;border-radius:13px;background:#fbfcfe}.gps-left{display:flex;align-items:center;gap:9px}.dot{width:11px;height:11px;border-radius:50%;background:#94a3b8;flex:none}.dot.live{background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.14)}.dot.warn{background:#f59e0b;box-shadow:0 0 0 5px rgba(245,158,11,.12)}.status-box small{color:var(--muted)}.stack{display:grid;gap:9px}.field label{display:block;font-size:10px;color:#6d7d92;text-transform:uppercase;font-weight:950;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;min-height:44px;border:1px solid #d5deea;border-radius:11px;padding:10px 12px;outline:none;background:#fff}.field input:focus,.field select:focus,.field textarea:focus{border-color:#77a9f5;box-shadow:0 0 0 3px rgba(23,105,232,.1)}.hidden{display:none!important}.feedback{padding:12px 14px;border-radius:12px;font-size:12px;font-weight:800}.feedback.success{background:#eafaf2;color:#17633f}.feedback.danger{background:#fff0f0;color:#a52323}.item-list{display:grid;gap:0}.item-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid #edf1f6}.item-row:last-child{border-bottom:0}.item-row strong{font-size:13px;line-height:1.4}.item-row span{font-size:11px;color:var(--muted);white-space:nowrap}.details{border:1px solid #f2d6d6;border-radius:13px;overflow:hidden}.details summary{list-style:none;cursor:pointer;padding:13px;color:var(--danger);font-weight:900;font-size:12px}.details summary::-webkit-details-marker{display:none}.details-body{padding:0 13px 13px}.local-test{border:1px dashed #d6b36a;background:#fffaf0;border-radius:13px;padding:11px;color:#875b10;font-size:11px;line-height:1.5}.sticky-mobile{display:none}
        @media(max-width:800px){.summary-grid{grid-template-columns:repeat(2,1fr)}.main-grid{grid-template-columns:1fr}.action-row{align-items:stretch;flex-direction:column}.action-controls{min-width:0}.map-shell{height:330px}.hero-head{flex-direction:column}.state-pill{align-self:flex-start}}
        @media(max-width:520px){.shell{padding:12px 11px 100px}.summary-grid,.route-meta,.contact-grid{grid-template-columns:1fr}.hero{padding:17px}.hero h1{font-size:24px}.action-card,.section{padding:15px}.topbar{align-items:flex-start}.badge{max-width:190px;text-align:center}.sticky-mobile{display:block;position:fixed;left:10px;right:10px;bottom:10px;z-index:20}.sticky-mobile .btn{width:100%;box-shadow:0 12px 28px rgba(7,31,79,.25)}}
    </style>
</head>
<body>
@php
    $status = $session->mission_status ?: 'planned';
    $statusLabels = [
        'planned' => 'À accepter',
        'accepted' => 'Prête au départ',
        'in_transit' => 'En route',
        'arrived' => 'Arrivé chez le client',
        'delivered' => 'Livrée',
    ];
    $deliveryAddress = collect([
        $session->order?->delivery_address,
        $session->order?->address,
        $session->order?->delivery_quartier,
        $session->order?->delivery_commune,
        $session->order?->delivery_city,
    ])->filter()->unique()->implode(' - ');
    $recipientPhone = $session->order?->delivery_recipient_phone ?: $session->order?->phone ?: $session->order?->client?->phone;
    $destinationLat = $session->order?->delivery_latitude ?: $session->order?->delivery_lat;
    $destinationLng = $session->order?->delivery_longitude ?: $session->order?->delivery_lng;
    $manualTracking = in_array($session->gps_status, ['unavailable','denied','disabled'], true);
    $vehicleCode = $session->items->pluck('logistics_vehicle_code')->filter()->first() ?: 'pickup';
@endphp
<div class="shell">
    <header class="topbar">
        <div class="brand"><div class="brand-mark">O</div><div>OVANIE<small>Mission de livraison</small></div></div>
        <span id="signal-badge" class="badge {{ $signal['severity']==='success'?'success':($signal['severity']==='danger'?'danger':'warning') }}">{{ $signal['label'] }}</span>
    </header>

    <section class="card hero">
        <div class="hero-head">
            <div><span class="kicker">Mission sécurisée</span><h1>{{ $session->order?->order_number ?? 'Mission #'.$session->id }}</h1><p class="muted">Les informations affichées concernent uniquement la remise confiée à ce chauffeur.</p></div>
            <span class="state-pill" id="mission-state-pill">{{ $statusLabels[$status] ?? 'En cours' }}</span>
        </div>
        <div class="summary-grid">
            <div class="metric"><span>Chauffeur</span><strong>{{ $session->driver_name }}</strong></div>
            <div class="metric"><span>Immatriculation</span><strong>{{ $session->vehicle_plate ?: 'Non renseignée' }}</strong></div>
            <div class="metric"><span>Destination</span><strong>{{ $session->order?->delivery_commune ?: $session->order?->delivery_city ?: 'À confirmer' }}</strong></div>
            <div class="metric"><span>Livraison prévue</span><strong>{{ ($session->manual_eta_at ?: $session->estimated_delivery_at)?->format('d/m/Y à H:i') ?: 'À confirmer' }}</strong></div>
        </div>
    </section>

    @if($session->isTrackable())
    <section class="card action-card" id="mission-action-card">
        <div class="action-row">
            <div class="action-copy" id="mission-action-copy">
                <span class="kicker">Étape actuelle</span>
                @if($status === 'planned')
                    <h2>Confirmez la prise en charge</h2><p class="muted">Vérifiez les articles et la destination avant d’accepter.</p>
                @elseif($status === 'accepted')
                    <h2>Démarrez la livraison</h2><p class="muted">Le premier signal GPS sera enregistré avant le départ. Le mode manuel reste disponible.</p>
                @elseif($status === 'in_transit')
                    <h2>Livraison en cours</h2><p class="muted">Gardez cette page ouverte. L’arrivée sera détectée automatiquement à proximité du client.</p>
                @elseif($status === 'arrived')
                    <h2>Confirmez la remise</h2><p class="muted">Demandez le code au client après avoir remis tous les articles.</p>
                @endif
            </div>
            <div class="action-controls">
                @if($status === 'planned')
                    <button id="accept-mission" class="btn btn-primary">Accepter la mission</button>
                @elseif($status === 'accepted')
                    <button id="start-mission" class="btn btn-primary">Démarrer la livraison</button>
                @elseif($status === 'in_transit')
                    @if($manualTracking)
                        <button class="btn btn-green" data-status="arrived">Confirmer l’arrivée sans GPS</button>
                    @else
                        <div class="feedback success">GPS actif · l’arrivée sera confirmée uniquement sur place.</div>
                    @endif
                @elseif($status === 'arrived')
                    <input id="otp-code" inputmode="numeric" maxlength="6" placeholder="Code client à 6 chiffres" style="min-height:48px;border:1px solid #d5deea;border-radius:13px;padding:0 14px;text-align:center;font-size:18px;font-weight:900;letter-spacing:3px">
                    <button id="verify-otp" class="btn btn-green">Confirmer la livraison</button>
                @endif
            </div>
        </div>
    </section>
    @endif

    <div class="main-grid">
        <div class="stack">
            <section class="card section">
                <div class="section-head"><div><h2>Trajet de livraison</h2><p class="muted" style="margin:4px 0 0">Le tracé suit la route entre la position réelle du téléphone et la destination.</p></div><button type="button" id="seller-map-recenter" class="map-recenter" hidden>Recentrer</button></div>
                <div id="seller-map-shell" class="map-shell ovd-map-shell">
                    <div id="seller-driver-map" class="map"></div>
                    <div id="seller-map-loading" class="ovd-map-shell__loading"><div><strong>Localisation de la livraison</strong><span>OVANIE prépare la position du véhicule et la destination sans afficher de coordonnées approximatives.</span></div></div>
                </div>
                <div class="route-meta">
                    <div class="metric"><span>Distance restante</span><strong id="route-distance">{{ $session->remaining_distance_km !== null ? number_format($session->remaining_distance_km,1,',',' ').' km' : 'À confirmer' }}</strong></div>
                    <div class="metric"><span>Arrivée estimée</span><strong id="route-eta">{{ $status === 'arrived' ? 'Vous êtes arrivé' : (($session->manual_eta_at ?: $session->estimated_delivery_at)?->format('d/m/Y à H:i') ?: 'À confirmer') }}</strong></div>
                </div>
            </section>

            <section class="card section">
                <div class="section-head"><div><h2>Articles à remettre</h2><p class="muted" style="margin:4px 0 0">Vérifiez les quantités avant le départ et à la remise.</p></div></div>
                <div class="item-list">
                    @foreach($session->items as $item)
                        <div class="item-row"><strong>{{ $item->product?->name ?? 'Article à livrer' }}</strong><span>Quantité : {{ max(1,(int)$item->quantity) }}</span></div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="stack">
            <section class="card section">
                <div class="section-head"><div><h2>Destination</h2></div></div>
                <div class="address-box"><span>Adresse de livraison</span><strong>{{ $deliveryAddress ?: 'Adresse à confirmer avec OVANIE' }}</strong></div>
                <div class="contact-grid">
                    @if($recipientPhone)
                        <a class="btn btn-light" href="tel:{{ preg_replace('/\s+/', '', $recipientPhone) }}">Appeler</a>
                        <a class="btn btn-light" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D+/', '', $recipientPhone) }}">WhatsApp</a>
                    @endif
                </div>
                @if(is_numeric($destinationLat) && is_numeric($destinationLng))
                    <a class="btn btn-green" style="width:100%;margin-top:8px" target="_blank" rel="noopener" href="https://waze.com/ul?ll={{ $destinationLat }},{{ $destinationLng }}&navigate=yes">Ouvrir la navigation</a>
                @endif
            </section>

            @if($session->isTrackable())
            <section class="card section">
                <div class="section-head"><div><h2>Partage de position</h2><p class="muted" style="margin:4px 0 0">La position est envoyée régulièrement tant que cette page reste ouverte, même si le véhicule est immobile.</p></div></div>
                <div class="status-box"><div class="gps-left"><span id="gps-dot" class="dot {{ $signal['code']==='active'?'live':(($signal['code']??'')==='lost'?'warn':'') }}"></span><div><strong id="gps-state">{{ $signal['label'] }}</strong><small id="last-sent">{{ $session->last_location_at ? 'Dernière mise à jour '.$session->last_location_at->diffForHumans() : 'Aucune position envoyée' }}</small></div></div></div>
                <div class="stack" style="margin-top:10px">
                    <button id="start-gps" class="btn btn-primary {{ $manualTracking ? 'hidden' : '' }}">Activer le GPS</button>
                    <button id="stop-gps" class="btn btn-light hidden">Mettre en pause</button>
                    <button id="open-no-gps" class="btn btn-light">Continuer sans GPS</button>
                    @if(config('delivery.local_simulation'))
                        <div class="local-test"><strong>Test local</strong><br>Le marqueur réel ne bouge que lorsque les coordonnées du téléphone changent. Utilisez le bouton ci-dessous pour simuler un trajet uniquement en local.</div>
                        <button id="simulate-route" class="btn btn-dark">Simuler le trajet en local</button>
                    @endif
                </div>
                <div id="no-gps-box" class="{{ $manualTracking ? '' : 'hidden' }}" style="margin-top:12px">
                    <div class="field"><label>Motif</label><select id="gps-reason"><option value="permission_denied">Autorisation refusée</option><option value="no_signal">Aucun signal GPS</option><option value="device_issue">Problème du téléphone</option><option value="battery_saving">Économie de batterie</option><option value="other">Autre</option></select></div>
                    <div class="field"><label>Heure d’arrivée estimée</label><input id="manual-eta" type="datetime-local" value="{{ ($session->manual_eta_at ?: $session->estimated_delivery_at)?->format('Y-m-d\TH:i') }}"></div>
                    <button id="confirm-no-gps" class="btn btn-dark" style="width:100%">Activer le suivi manuel</button>
                </div>
            </section>

            <details class="card section details">
                <summary>Signaler un incident</summary>
                <div class="details-body">
                    <div class="field"><label>Type</label><select id="incident-type"><option value="traffic_jam">Embouteillage important</option><option value="client_absent">Client absent</option><option value="address_issue">Adresse difficile à trouver</option><option value="vehicle_breakdown">Panne véhicule</option><option value="accident">Accident</option><option value="product_damaged">Produit endommagé</option><option value="access_impossible">Accès impossible</option><option value="delivery_refused">Refus de réception</option><option value="other">Autre</option></select></div>
                    <div class="field"><label>Détails</label><textarea id="incident-note" rows="3" placeholder="Décrivez brièvement la situation"></textarea></div>
                    <button id="report-incident" class="btn btn-danger" style="width:100%">Transmettre l’incident</button>
                </div>
            </details>
            @endif
        </aside>
    </div>

    <div id="feedback" class="feedback hidden" style="margin-top:14px"></div>
</div>

<script src="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js"></script>
<script src="{{ asset('js/ovanie-delivery-map.js') }}?v={{ @filemtime(public_path('js/ovanie-delivery-map.js')) ?: '20260718-4' }}"></script>
<script>
(() => {
    const csrf=document.querySelector('meta[name="csrf-token"]').content;
    const mapToken=@json(config('geo.mapbox.public_token') ?: env('MAPBOX_PUBLIC_TOKEN'));
    const mapStyle=@json(config('geo.mapbox.style_url','mapbox://styles/mapbox/streets-v12'));
    const destination={lat:Number(@json($destinationLat)),lng:Number(@json($destinationLng))};
    const initialPosition={lat:Number(@json($session->last_latitude)),lng:Number(@json($session->last_longitude))};
    const positionCacheKey='ovanie:mission:last-position:'+@json($session->public_id);
    const cachedPosition=(()=>{try{const value=JSON.parse(localStorage.getItem(positionCacheKey)||'null');return value&&valid(value.lat,value.lng)?value:null;}catch(_){return null;}})();
    const effectiveInitialPosition=cachedPosition||initialPosition;
    const initialGeometry=@json(is_array($session->route_geometry) ? $session->route_geometry : (json_decode((string)$session->route_geometry,true) ?: null));
    const initialHeading=Number(@json($session->last_heading));
    const vehicleCode=@json($vehicleCode);
    const deliveryAssetBase=@json(asset('images/delivery'));
    let missionStatus=@json($status);
    const manualTracking=@json($manualTracking);
    const localMode=@json((bool) config('delivery.local_simulation'));
    const routeSnapThresholdMeters=@json((int) config('delivery.route_deviation_threshold_m',30));
    const urls={
        accept:@json(route('seller-driver.accept',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        start:@json(route('seller-driver.start',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        location:@json(route('seller-driver.location',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        noGps:@json(route('seller-driver.gps-unavailable',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        status:@json(route('seller-driver.status',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        incident:@json(route('seller-driver.incident',['publicId'=>$session->public_id,'token'=>$session->access_token])),
        otp:@json(route('seller-driver.verify-otp',['publicId'=>$session->public_id,'token'=>$session->access_token]))
    };

    let map=null,driverMarker=null,destinationMarker=null,currentGeometry=initialGeometry;
    let cameraLocked=false,mapHasFitted=false,lastDriverPoint=null,mapRevealed=false,cameraResumeTimer=null;
    let watchId=null,heartbeatId=null,lastPosition=null,lastPosted=0,wakeLock=null,simulationTimer=null;
    let arrivalReloading=false;

    bootMap();
    bindActions();
    if(['in_transit','arrived'].includes(missionStatus) && !manualTracking){window.setTimeout(()=>startGps(true),500);}

    function bindActions(){
        document.getElementById('accept-mission')?.addEventListener('click',async event=>{await runButton(event.currentTarget,async()=>{await send(urls.accept,{});location.reload();});});
        document.getElementById('start-mission')?.addEventListener('click',async event=>{await runButton(event.currentTarget,async()=>{
            if(!manualTracking){await obtainAndPostPosition();}
            await send(urls.start,{});
            location.reload();
        });});
        document.querySelectorAll('[data-status]').forEach(button=>button.addEventListener('click',async()=>{await runButton(button,async()=>{await send(urls.status,{status:button.dataset.status,manual_eta_at:document.getElementById('manual-eta')?.value||null});location.reload();});}));
        document.getElementById('start-gps')?.addEventListener('click',()=>startGps(false));
        document.getElementById('stop-gps')?.addEventListener('click',stopGps);
        document.getElementById('open-no-gps')?.addEventListener('click',()=>document.getElementById('no-gps-box')?.classList.toggle('hidden'));
        document.getElementById('confirm-no-gps')?.addEventListener('click',async event=>{await runButton(event.currentTarget,async()=>{const data=await send(urls.noGps,{reason:document.getElementById('gps-reason').value,manual_eta_at:document.getElementById('manual-eta').value||null});stopGps();setGpsState('Suivi manuel actif','Les étapes seront mises à jour depuis cette page.',false);feedback(data.message,true);});});
        document.getElementById('report-incident')?.addEventListener('click',async event=>{await runButton(event.currentTarget,async()=>{const payload={incident_type:document.getElementById('incident-type').value,incident_note:document.getElementById('incident-note').value};if(lastPosition){payload.latitude=lastPosition.coords.latitude;payload.longitude=lastPosition.coords.longitude;}const data=await send(urls.incident,payload);feedback(data.message,true);});});
        document.getElementById('verify-otp')?.addEventListener('click',async event=>{await runButton(event.currentTarget,async()=>{await send(urls.otp,{delivery_otp_code:document.getElementById('otp-code').value.trim()});location.reload();});});
        document.getElementById('simulate-route')?.addEventListener('click',event=>simulateLocalRoute(event.currentTarget));
        document.getElementById('seller-map-recenter')?.addEventListener('click',()=>resumeCameraFollow(true));
        document.addEventListener('visibilitychange',()=>{if(!document.hidden && ['in_transit','arrived'].includes(missionStatus) && !manualTracking && watchId===null){startGps(true);}});
        window.addEventListener('beforeunload',()=>{if(wakeLock){try{wakeLock.release();}catch(_){}}});
    }

    async function runButton(button,task){button.disabled=true;const label=button.textContent;button.textContent='Traitement…';try{await task();}catch(error){feedback(error.message,false);}finally{button.disabled=false;button.textContent=label;}}

    async function obtainAndPostPosition(){
        if(!navigator.geolocation)throw new Error('Le GPS est indisponible. Activez le suivi manuel pour continuer.');
        const position=await new Promise((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,maximumAge:5000,timeout:20000}));
        lastPosition=position;
        await postPosition(position,true);
        startGps(true);
        return position;
    }

    async function startGps(silent=false){
        if(watchId!==null)return;
        if(!navigator.geolocation){if(!silent)feedback('GPS indisponible sur cet appareil.',false);return;}
        requestWakeLock();
        try{const first=await new Promise((resolve,reject)=>navigator.geolocation.getCurrentPosition(resolve,reject,{enableHighAccuracy:true,maximumAge:5000,timeout:20000}));lastPosition=first;await postPosition(first,true);}catch(error){onGeoError(error);if(silent)return;}
        watchId=navigator.geolocation.watchPosition(position=>{lastPosition=position;postPosition(position,false);},onGeoError,{enableHighAccuracy:true,maximumAge:5000,timeout:30000});
        heartbeatId=window.setInterval(()=>{if(lastPosition)postPosition(lastPosition,true);else navigator.geolocation.getCurrentPosition(position=>{lastPosition=position;postPosition(position,true);},()=>{}, {enableHighAccuracy:true,maximumAge:10000,timeout:15000});},45000);
        document.getElementById('start-gps')?.classList.add('hidden');document.getElementById('stop-gps')?.classList.remove('hidden');
    }

    function stopGps(){if(watchId!==null)navigator.geolocation.clearWatch(watchId);watchId=null;if(heartbeatId)window.clearInterval(heartbeatId);heartbeatId=null;window.OvanieDeliveryMap?.setMarkerState(driverMarker,{stale:true});document.getElementById('start-gps')?.classList.remove('hidden');document.getElementById('stop-gps')?.classList.add('hidden');setGpsState('GPS en pause','La dernière position reste visible. Réactivez le GPS avant de reprendre la route.',false);}

    async function postPosition(position,force){
        const now=Date.now();if(!force && now-lastPosted<12000)return;lastPosted=now;
        setGpsState('GPS actif','Transmission sécurisée à OVANIE.',true);
        try{
            const data=await send(urls.location,{latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy:position.coords.accuracy,speed:position.coords.speed,heading:position.coords.heading});
            const sent=document.getElementById('last-sent');if(sent)sent.textContent='Dernier envoi à '+new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
            if(Number.isFinite(Number(data.route?.distance_km)))document.getElementById('route-distance').textContent=Number(data.route.distance_km).toLocaleString('fr-FR',{maximumFractionDigits:1})+' km';
            if(Number.isFinite(Number(data.route?.eta_minutes))){
                const eta=Math.max(0,Math.round(Number(data.route.eta_minutes)));
                document.getElementById('route-eta').textContent=eta===0?'Vous êtes arrivé':'Environ '+eta+' min';
            }
            const stableLat=Number(data.location?.latitude ?? position.coords.latitude);
            const stableLng=Number(data.location?.longitude ?? position.coords.longitude);
            updateMapPosition(stableLat,stableLng,data.route?.geometry || null,data.location?.heading ?? position.coords.heading);
            if(data.mission_status==='arrived'&&!arrivalReloading){
                arrivalReloading=true;
                missionStatus='arrived';
                document.getElementById('route-distance').textContent='0 km';
                document.getElementById('route-eta').textContent='Vous êtes arrivé';
                feedback('Arrivée détectée automatiquement. Préparez la remise au client.',true);
                window.setTimeout(()=>location.reload(),900);
            }
        }catch(error){window.OvanieDeliveryMap?.setMarkerState(driverMarker,{stale:true});setGpsState('Connexion momentanément interrompue','La dernière position connue reste visible. Nouvelle tentative automatique dans quelques secondes.',false);revealMap();}
    }

    function onGeoError(error){const denied=Number(error?.code)===1;setGpsState(denied?'Autorisation GPS refusée':'Position momentanément indisponible',denied?'Autorisez la localisation ou activez le suivi manuel.':'Le système réessaiera automatiquement.',false);window.OvanieDeliveryMap?.setMarkerState(driverMarker,{stale:true});revealMap();document.getElementById('no-gps-box')?.classList.remove('hidden');}
    function setGpsState(title,subtitle,live){const dot=document.getElementById('gps-dot');if(dot){dot.classList.toggle('live',!!live);dot.classList.toggle('warn',!live);}const titleNode=document.getElementById('gps-state');if(titleNode)titleNode.textContent=title;const sent=document.getElementById('last-sent');if(sent&&subtitle)sent.textContent=subtitle;}

    async function requestWakeLock(){try{if('wakeLock'in navigator)wakeLock=await navigator.wakeLock.request('screen');}catch(_){}}

    function revealMap(){
        if(mapRevealed)return;
        mapRevealed=true;
        document.getElementById('seller-map-shell')?.classList.add('is-ready');
        window.setTimeout(()=>map?.resize(),40);
    }

    function showMapMessage(title,message){
        const shell=document.getElementById('seller-map-shell');
        const panel=document.getElementById('seller-map-loading');
        shell?.classList.remove('is-ready');
        panel?.classList.add('is-error');
        if(panel)panel.innerHTML=`<div><strong>${escapeHtml(title)}</strong><span>${escapeHtml(message)}</span></div>`;
    }

    function escapeHtml(value){
        return String(value ?? '').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    }

    function bootMap(){
        const host=document.getElementById('seller-driver-map');
        const hasDestination=valid(destination.lat,destination.lng);
        const hasInitialPosition=valid(effectiveInitialPosition.lat,effectiveInitialPosition.lng);

        if(!host){return;}
        if(!window.mapboxgl){showMapMessage('Carte indisponible','La bibliothèque Mapbox ne s’est pas chargée. Vérifiez la connexion Internet puis rechargez la page.');return;}
        if(!window.OvanieDeliveryMap){showMapMessage('Composant de suivi non chargé','Le fichier public/js/ovanie-delivery-map.js est absent ou n’a pas été copié dans le projet.');return;}
        if(!mapToken){showMapMessage('Configuration Mapbox manquante','Ajoutez MAPBOX_PUBLIC_TOKEN dans le fichier .env puis exécutez php artisan optimize:clear.');return;}
        if(!hasDestination&&!hasInitialPosition){showMapMessage('Positions indisponibles','Aucune coordonnée réelle du véhicule ni de la destination n’est enregistrée. La carte n’affiche volontairement aucune position approximative.');return;}

        const center=hasInitialPosition?[effectiveInitialPosition.lng,effectiveInitialPosition.lat]:[destination.lng,destination.lat];
        mapboxgl.accessToken=mapToken;
        map=new mapboxgl.Map({container:host,style:mapStyle,center,zoom:hasInitialPosition?15:14,minZoom:10.5,maxZoom:18.5,renderWorldCopies:false,dragRotate:false,pitchWithRotate:false,cooperativeGestures:true,fadeDuration:0});
        OvanieDeliveryMap.applyMapConstraints(map,{minZoom:10.5,maxZoom:18.5});
        map.addControl(new mapboxgl.NavigationControl({showCompass:false}),'top-right');
        ['dragstart','zoomstart','rotatestart','pitchstart'].forEach(eventName=>map.on(eventName,event=>{
            if(!event.originalEvent)return;
            cameraLocked=true;
            document.getElementById('seller-map-recenter')?.removeAttribute('hidden');
            if(cameraResumeTimer)window.clearTimeout(cameraResumeTimer);
            cameraResumeTimer=window.setTimeout(()=>resumeCameraFollow(false),15000);
        }));
        map.on('error',event=>{
            const message=String(event?.error?.message||'');
            if(/401|403|token|unauthorized/i.test(message))showMapMessage('Accès Mapbox refusé','Le jeton MAPBOX_PUBLIC_TOKEN est absent, invalide ou non autorisé pour ce domaine.');
        });
        map.on('load',()=>{
            if(hasDestination){
                destinationMarker=OvanieDeliveryMap.createMarker(map,[destination.lng,destination.lat],'destination',{
                    label:'Destination client',color:'#f97316',assetBase:deliveryAssetBase
                });
            }
            if(hasInitialPosition){
                updateMapPosition(effectiveInitialPosition.lat,effectiveInitialPosition.lng,initialGeometry,initialHeading,true);
            }else if(hasDestination){
                map.jumpTo({center:[destination.lng,destination.lat],zoom:14});
                requestAnimationFrame(revealMap);
            }
        });
    }

    function updateMapPosition(lat,lng,geometry,heading=null,initial=false){
        if(!map||!valid(lat,lng))return;
        const gpsPoint=[Number(lng),Number(lat)];
        const usableGeometry=usableRouteGeometry(geometry,gpsPoint,[destination.lng,destination.lat])?geometry:null;
        const routeState=usableGeometry
            ? OvanieDeliveryMap.remainingRoute(usableGeometry,gpsPoint,routeSnapThresholdMeters)
            : {point:gpsPoint,geometry:null,snapped:false};
        const displayPoint=routeState.point||gpsPoint;
        lastDriverPoint=displayPoint;
        try{localStorage.setItem(positionCacheKey,JSON.stringify({lat:Number(lat),lng:Number(lng),savedAt:Date.now()}));}catch(_){}
        driverMarker=OvanieDeliveryMap.upsertMarker(driverMarker,map,displayPoint,'vehicle',{
            label:'Position du véhicule',vehicleCode,heading,assetBase:deliveryAssetBase,stale:false,animate:false
        });
        const routeGeometry=routeState.snapped ? routeState.geometry : null;
        if(routeGeometry){currentGeometry=routeGeometry;drawRoute(routeGeometry);}
        else if(usableGeometry){drawRoute(null);}
        followVehicle(initial);
        if(!mapHasFitted)revealMap();
    }

    function followVehicle(initial=false){
        if(!map||!lastDriverPoint)return;
        if(cameraLocked)return;
        if(!mapHasFitted){
            fitMission(true,initial);
            return;
        }
        map.easeTo({
            center:lastDriverPoint,
            zoom:Math.max(15.5,Math.min(17,map.getZoom())),
            duration:initial?0:700,
            essential:true
        });
        revealMap();
    }

    function resumeCameraFollow(immediate=false){
        cameraLocked=false;
        if(cameraResumeTimer)window.clearTimeout(cameraResumeTimer);
        cameraResumeTimer=null;
        document.getElementById('seller-map-recenter')?.setAttribute('hidden','hidden');
        if(lastDriverPoint)map?.easeTo({center:lastDriverPoint,zoom:Math.max(15.5,Math.min(17,map.getZoom())),duration:immediate?350:650,essential:true});
    }

    function fitMission(force=false,initial=false){
        if(!map||!lastDriverPoint)return;
        if(!force&&cameraLocked)return;
        const hasDestination=valid(destination.lat,destination.lng);
        cameraLocked=false;
        mapHasFitted=true;
        document.getElementById('seller-map-recenter')?.setAttribute('hidden','hidden');

        if(!hasDestination){
            map.easeTo({center:lastDriverPoint,zoom:15,duration:initial?0:450});
            revealMap();
            return;
        }

        OvanieDeliveryMap.fitOperationalBounds(map,[lastDriverPoint,[destination.lng,destination.lat]],{
            padding:{top:60,right:60,bottom:60,left:60},maxFitZoom:15,singleZoom:14,maxSpanKm:60,longTripZoom:11.5,duration:initial?0:450
        });
        if(initial)requestAnimationFrame(revealMap);else revealMap();
    }

    function drawRoute(geometry){
        if(!map||!map.isStyleLoaded())return;
        const data=geometry&&geometry.type==='LineString'
            ?{type:'Feature',properties:{},geometry}
            :{type:'FeatureCollection',features:[]};
        if(map.getSource('seller-route')){map.getSource('seller-route').setData(data);return;}
        map.addSource('seller-route',{type:'geojson',data});
        map.addLayer({id:'seller-route-outline',type:'line',source:'seller-route',layout:{'line-cap':'round','line-join':'round'},paint:{'line-color':'#fff','line-width':9,'line-opacity':.95}});
        map.addLayer({id:'seller-route-line',type:'line',source:'seller-route',layout:{'line-cap':'round','line-join':'round'},paint:{'line-color':'#1769e8','line-width':5,'line-opacity':.95}});
    }
    function valid(lat,lng){lat=Number(lat);lng=Number(lng);return Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=4&&lat<=11.2&&lng>=-9&&lng<=-2&&(lat!==0||lng!==0);}
    function distanceKm(a,b,c,d){const r=6371,p1=a*Math.PI/180,p2=c*Math.PI/180,dp=(c-a)*Math.PI/180,dl=(d-b)*Math.PI/180,x=Math.sin(dp/2)**2+Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;return r*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));}
    function usableRouteGeometry(geometry,start,end){
        const coordinates=geometry?.type==='LineString'&&Array.isArray(geometry.coordinates)?geometry.coordinates:null;
        if(!coordinates||coordinates.length<2||coordinates.some(point=>!Array.isArray(point)||point.length<2||!valid(point[1],point[0])))return false;
        if(!Array.isArray(start)||!Array.isArray(end)||!valid(start[1],start[0])||!valid(end[1],end[0]))return true;
        const straight=distanceKm(start[1],start[0],end[1],end[0]);
        let route=0;for(let i=1;i<coordinates.length;i++)route+=distanceKm(coordinates[i-1][1],coordinates[i-1][0],coordinates[i][1],coordinates[i][0]);
        if(straight>.1&&route>(straight*9)+3)return false;
        const first=coordinates[0],last=coordinates[coordinates.length-1];
        return distanceKm(start[1],start[0],first[1],first[0])<=2&&distanceKm(end[1],end[0],last[1],last[0])<=2;
    }

    async function simulateLocalRoute(button){
        if(!localMode)return;button.disabled=true;button.textContent='Simulation en cours…';
        try{
            let geometry=currentGeometry;
            if(!geometry?.coordinates?.length)throw new Error('Aucun itinéraire disponible pour la simulation. Activez d’abord le GPS.');
            const coordinates=geometry.coordinates.filter((_,index)=>index%Math.max(1,Math.floor(geometry.coordinates.length/35))===0);
            let index=0;
            if(simulationTimer)window.clearInterval(simulationTimer);
            simulationTimer=window.setInterval(async()=>{const coord=coordinates[index++];if(!coord){window.clearInterval(simulationTimer);button.disabled=false;button.textContent='Simulation terminée';return;}const fake={coords:{longitude:Number(coord[0]),latitude:Number(coord[1]),accuracy:5,speed:8,heading:null}};lastPosition=fake;await postPosition(fake,true);},1800);
        }catch(error){feedback(error.message,false);button.disabled=false;button.textContent='Simuler le trajet en local';}
    }

    async function send(url,payload){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});const data=await response.json().catch(()=>({}));if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Erreur réseau');return data;}
    function feedback(message,success){const box=document.getElementById('feedback');if(!box)return;box.className='feedback '+(success?'success':'danger');box.textContent=message;box.classList.remove('hidden');box.scrollIntoView({behavior:'smooth',block:'nearest'});}
})();
</script>
</body>
</html>
