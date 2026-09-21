@php
    $shop = $shop ?? null;
    $showMode = $showMode ?? true;
    $defaultMode = $defaultLocationMode ?? ($shop ? (filled(old('latitude', $shop->latitude)) && filled(old('longitude', $shop->longitude)) ? 'gps_now' : 'later') : 'gps_now');
    $locationMode = old('location_mode', $showMode ? $defaultMode : 'gps_now');
    $initialLatitude = old('latitude', $shop?->latitude);
    $initialLongitude = old('longitude', $shop?->longitude);
    $initialAccuracy = old('geo_accuracy', $shop?->geo_accuracy);
    $initialSource = old('geo_source', $shop?->geo_source);
    $initialConfirmed = old('location_confirmed', filled($initialLatitude) && filled($initialLongitude) ? '1' : '');
@endphp

@once
    @push('styles')
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.css" rel="stylesheet">
    @endpush
    <style>
        .shop-location-picker{grid-column:1/-1;border:1px solid #dbe5f1;border-radius:14px;background:linear-gradient(180deg,#fbfdff 0%,#f7faff 100%);padding:18px;display:grid;gap:16px}
        .shop-location-picker.is-hidden{display:none}.location-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.location-title{display:flex;gap:12px;align-items:flex-start}.location-title-icon{width:40px;height:40px;border-radius:11px;background:#eaf2ff;color:#174b8f;display:grid;place-items:center;flex:0 0 40px}.location-title-icon svg{width:20px}.location-title h3{margin:0;font-size:14px;color:#10264f}.location-title p{margin:5px 0 0;color:#708098;font-size:10.5px;line-height:1.55}.location-required{padding:6px 9px;border-radius:999px;background:#fff1e8;color:#c55208;font-size:9px;font-weight:850;white-space:nowrap}
        .location-modes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.location-mode{position:relative;display:flex;align-items:flex-start;gap:11px;border:1px solid #dce5f0;border-radius:11px;background:#fff;padding:13px;cursor:pointer}.location-mode:has(input:checked){border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.08);background:#fffaf6}.location-mode input{width:17px!important;height:17px!important;margin:2px 0 0!important;flex:0 0 17px}.location-mode strong{display:block;color:#17345f;font-size:11px}.location-mode span{display:block;color:#7c899d;font-size:9.5px;line-height:1.5;margin-top:3px}
        .location-capture-panel{display:grid;gap:13px}.location-capture-panel.is-hidden{display:none}.location-actions{display:flex;gap:9px;flex-wrap:wrap}.location-actions .btn{min-height:42px}.location-status{border:1px solid #dce5f0;border-radius:10px;background:#fff;padding:11px 13px;color:#5d6e86;font-size:10px;line-height:1.5}.location-status[data-tone="success"]{background:#ecfdf5;border-color:#a7f3d0;color:#047857}.location-status[data-tone="warning"]{background:#fffbeb;border-color:#fde68a;color:#a16207}.location-status[data-tone="danger"]{background:#fff1f2;border-color:#fecdd3;color:#be123c}.location-status[data-tone="loading"]{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
        .location-map-shell{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(230px,.6fr);gap:12px}.location-map{min-height:260px;border:1px solid #dce5f0;border-radius:12px;overflow:hidden;background:#eef4fa}.location-map.is-hidden,.location-map-fallback.is-hidden,.location-open-map.is-hidden{display:none}.location-map-fallback{min-height:180px;border:1px dashed #b9c8db;border-radius:12px;display:grid;place-items:center;text-align:center;padding:20px;color:#6d7d94;background:#fff}.location-map-fallback svg{width:30px;margin-bottom:8px}.location-summary{border:1px solid #dce5f0;border-radius:12px;background:#fff;padding:15px;display:flex;flex-direction:column;gap:12px}.location-summary-label{color:#8190a4;font-size:8.8px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.location-summary strong{font-size:12px;line-height:1.45;word-break:break-all}.location-summary p{margin:0;color:#6e7e95;font-size:9.5px;line-height:1.55}.location-note{display:flex;align-items:flex-start;gap:9px;padding:11px;border-radius:10px;background:#fff8ed;color:#87500b;font-size:9.5px;line-height:1.55}.location-note svg{width:17px;flex:0 0 17px}.location-spinner{width:15px;height:15px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:location-spin .7s linear infinite}@keyframes location-spin{to{transform:rotate(360deg)}}
        @media(max-width:800px){.location-map-shell{grid-template-columns:1fr}.location-map{min-height:230px}}
        @media(max-width:620px){.shop-location-picker{padding:14px}.location-head{flex-direction:column}.location-modes{grid-template-columns:1fr}.location-actions{display:grid;grid-template-columns:1fr}.location-actions .btn{width:100%}.location-title p{font-size:10px}}
    </style>
@endonce

<div class="shop-location-picker"
     data-shop-location-picker
     data-logistics-type="{{ $shop?->logistics_type ?: 'ovanie' }}"
     data-mapbox-token="{{ $mapboxToken ?? '' }}"
     data-mapbox-style="{{ $mapboxStyle ?? 'mapbox://styles/mapbox/streets-v12' }}">
    <div class="location-head">
        <div class="location-title">
            <span class="location-title-icon"><i data-lucide="map-pin-check"></i></span>
            <div>
                <h3>Position exacte du point d’enlèvement</h3>
                <p>Le point de repère reste facultatif. OVANIE Logistics utilise surtout la latitude et la longitude pour afficher la boutique, calculer le trajet et organiser la collecte.</p>
            </div>
        </div>
        <span class="location-required">Obligatoire avant publication</span>
    </div>

    @if($showMode)
        <div class="location-modes">
            <label class="location-mode">
                <input type="radio" name="location_mode" value="gps_now" @checked($locationMode === 'gps_now')>
                <span><strong>Je suis dans la boutique</strong><span>Capturer maintenant la position de l’entrée ou du point où le livreur récupérera les produits.</span></span>
            </label>
            <label class="location-mode">
                <input type="radio" name="location_mode" value="later" @checked($locationMode === 'later')>
                <span><strong>Je confirmerai la position plus tard</strong><span>La boutique sera créée, mais ses produits resteront non publiables tant que le GPS n’est pas confirmé.</span></span>
            </label>
        </div>
    @else
        <input type="hidden" name="location_mode" value="gps_now">
    @endif

    <div class="location-capture-panel {{ $locationMode === 'gps_now' ? '' : 'is-hidden' }}" data-location-capture-panel>
        <input type="hidden" name="latitude" value="{{ $initialLatitude }}" data-location-latitude>
        <input type="hidden" name="longitude" value="{{ $initialLongitude }}" data-location-longitude>
        <input type="hidden" name="geo_accuracy" value="{{ $initialAccuracy }}" data-location-accuracy>
        <input type="hidden" name="geo_source" value="{{ $initialSource }}" data-location-source>
        <input type="hidden" name="location_confirmed" value="{{ $initialConfirmed }}" data-location-confirmed>

        <div class="location-actions">
            <button class="btn btn-orange" type="button" data-capture-location><i data-lucide="locate-fixed"></i>Utiliser ma position actuelle</button>
            <button class="btn" type="button" data-clear-location><i data-lucide="rotate-ccw"></i>Reprendre la position</button>
        </div>

        <div class="location-status" data-location-status>
            Placez-vous près de l’entrée ou du point de collecte, activez le GPS du téléphone, puis appuyez sur « Utiliser ma position actuelle ».
        </div>

        <div class="location-map-shell">
            <div class="location-map is-hidden" data-location-map aria-label="Carte de la position de la boutique"></div>
            <div class="location-map-fallback is-hidden" data-location-map-fallback>
                <div><i data-lucide="map"></i><div>La carte interactive n’est pas configurée, mais les coordonnées GPS seront bien enregistrées.</div></div>
            </div>
            <aside class="location-summary">
                <div>
                    <span class="location-summary-label">Coordonnées enregistrées</span>
                    <strong data-location-coordinates>Aucune position enregistrée</strong>
                </div>
                <p data-location-precision>Capturez le GPS lorsque vous êtes dans la boutique.</p>
                <a class="btn location-open-map is-hidden" target="_blank" rel="noopener" href="#" data-open-location-map><i data-lucide="external-link"></i>Vérifier sur la carte</a>
                <div class="location-note"><i data-lucide="info"></i><span>Le marqueur doit correspondre au point où le chauffeur peut réellement s’arrêter et charger les produits, pas au centre de la commune.</span></div>
            </aside>
        </div>
    </div>
</div>
