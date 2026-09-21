@extends('layouts.staff')

@section('title', 'Position de la boutique | Commercial OVANIE')

@section('inline_styles')
    .location-page-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:start}.location-form{padding:20px}.location-address-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-bottom:15px}.location-address-grid .full{grid-column:1/-1}.location-side{display:grid;gap:13px;position:sticky;top:84px}.location-shop-card{padding:18px}.location-shop-name{display:flex;gap:11px;align-items:center}.location-shop-icon{width:40px;height:40px;border-radius:10px;background:#fff1e8;color:#f97316;display:grid;place-items:center}.location-shop-icon svg{width:20px}.location-shop-card h3{margin:0;font-size:13px}.location-shop-card p{margin:4px 0 0;color:#78869a;font-size:9.5px;line-height:1.5}.location-detail{display:grid;gap:10px;margin-top:15px}.location-detail div{padding-top:10px;border-top:1px solid #edf1f5}.location-detail span{display:block;color:#8793a5;font-size:8.5px;text-transform:uppercase;letter-spacing:.06em}.location-detail strong{display:block;margin-top:4px;font-size:10px;line-height:1.45}.location-page-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:15px}
    @media(max-width:980px){.location-page-grid{grid-template-columns:1fr}.location-side{position:static}}
    @media(max-width:650px){.location-form{padding:14px}.location-address-grid{grid-template-columns:1fr}.location-address-grid .full{grid-column:auto}.location-page-actions{position:sticky;bottom:7px;background:rgba(255,255,255,.96);padding:8px;border:1px solid #e1e8f1;border-radius:10px}.location-page-actions .btn{flex:1}.location-address-grid input{font-size:16px!important}}
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Confirmer la position de la boutique</h1>
        <p class="page-subtitle">Enregistrez le point d’enlèvement exact utilisé par OVANIE Logistics pour la collecte, la carte et le calcul des trajets.</p>
    </div>
    <div class="page-actions"><a class="btn" href="{{ route('commercial.vendors.index') }}"><i data-lucide="arrow-left"></i>Retour aux boutiques</a></div>
</div>

<div class="location-page-grid">
    <form class="card location-form" method="POST" action="{{ route('commercial.vendors.location.update', $shop) }}">
        @csrf
        @method('PUT')

        <div class="location-address-grid">
            <div class="form-group"><label>Commune *</label><input name="commune" value="{{ old('commune', $shop->commune) }}" required>@error('commune')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="form-group"><label>Quartier *</label><input name="district" value="{{ old('district', $shop->district) }}" required>@error('district')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="form-group"><label>Point de repère <span class="form-help">(facultatif)</span></label><input name="landmark" value="{{ old('landmark', $shop->landmark) }}" placeholder="Ex. en face du marché"></div>
            <div class="form-group"><label>Adresse complète <span class="form-help">(facultatif)</span></label><input name="address" value="{{ old('address', $shop->address) }}" placeholder="Rue, lot, îlot ou précision utile"></div>
        </div>

        @include('commercial.vendors.partials.location-picker', ['shop' => $shop, 'showMode' => false])

        <div class="location-page-actions">
            <a class="btn" href="{{ route('commercial.vendors.index') }}">Annuler</a>
            <button class="btn btn-orange" type="submit"><i data-lucide="map-pin-check"></i>Enregistrer la position</button>
        </div>
    </form>

    <aside class="location-side">
        <section class="card location-shop-card">
            <div class="location-shop-name"><span class="location-shop-icon"><i data-lucide="store"></i></span><div><h3>{{ $shop->name }}</h3><p>{{ $shop->user?->name ?: $shop->user?->email }}</p></div></div>
            <div class="location-detail">
                <div><span>Mode logistique</span><strong>{{ $shop->logistics_mode_label }}</strong></div>
                <div><span>Adresse actuelle</span><strong>{{ collect([$shop->district,$shop->commune,$shop->city])->filter()->implode(', ') ?: 'Non renseignée' }}</strong></div>
                <div><span>État GPS</span><strong>{{ $shop->geo_status_label }}</strong></div>
                <div><span>État logistique</span><strong>{{ $shop->logistics_status === 'ready' ? 'Prête' : 'À compléter' }}</strong></div>
            </div>
        </section>
        <div class="alert alert-success" style="margin:0">Une fois la position fiable enregistrée, elle est disponible pour les cartes et les missions OVANIE Logistics. Le point de repère reste une aide humaine facultative.</div>
    </aside>
</div>
@endsection

@push('scripts')
<script src="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js"></script>
<script src="{{ asset('js/commercial-shop-location.js') }}"></script>
@endpush
