@extends('admin.layouts.app')

@section('title', 'Service de livraison | Admin OVANIE')
@section('page-title', $service->exists ? 'Modifier service de livraison' : 'Ajouter service de livraison')

@section('content')
<style>
    .admin-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.full{grid-column:1/-1}
    label{display:flex;flex-direction:column;gap:7px;font-weight:800;color:#334155}input,select,textarea{border:1px solid #dbe4f0;border-radius:12px;padding:11px 13px}textarea{min-height:90px}.btn{border:none;border-radius:12px;padding:12px 18px;font-weight:900;text-decoration:none;cursor:pointer}.primary{background:#ff5a1f;color:white}.light{background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0}.rate-row{display:grid;grid-template-columns:110px 1fr 1fr repeat(6,110px);gap:8px;margin-bottom:8px}.rate-row input,.rate-row select{width:100%;font-size:12px;padding:9px}
    .alert{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;padding:12px;border-radius:12px;margin-bottom:14px}@media(max-width:1100px){.grid,.rate-row{grid-template-columns:1fr}.full{grid-column:auto}}
</style>

@if($errors->any())
    <div class="alert"><ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form class="admin-card" method="POST" action="{{ $service->exists ? route('admin.delivery-services.update', $service) : route('admin.delivery-services.store') }}">
    @csrf
    @if($service->exists) @method('PUT') @endif

    <div class="grid">
        <label>Type de fournisseur
            <select name="provider_type" required>
                <option value="ovanie" selected>Logistique OVANIE</option>
            </select>
        </label>
        <label>Transporteur
            <select name="carrier_id">
                <option value="">Aucun / vendeur</option>
                @foreach($carriers as $carrier)
                    <option value="{{ $carrier->id }}" @selected((string) old('carrier_id', $service->carrier_id) === (string) $carrier->id)>{{ $carrier->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Code unique<input name="code" value="{{ old('code', $service->code) }}" required placeholder="ovanie-express"></label>
        <label>Nom commercial<input name="name" value="{{ old('name', $service->name) }}" required placeholder="OVANIE Express"></label>
        <label>Délai estimé en heures<input type="number" name="estimated_hours" value="{{ old('estimated_hours', $service->estimated_hours) }}" min="1" required></label>
        <label>Ordre d’affichage<input type="number" name="sort_order" value="{{ old('sort_order', $service->sort_order ?? 100) }}" min="0"></label>
        <label>Poids max kg<input type="number" step="0.001" name="max_weight_kg" value="{{ old('max_weight_kg', $service->max_weight_kg) }}"></label>
        <label>Volume max m³<input type="number" step="0.0001" name="max_volume_m3" value="{{ old('max_volume_m3', $service->max_volume_m3) }}"></label>
        <label class="full">Description<textarea name="description">{{ old('description', $service->description) }}</textarea></label>
        <label style="display:flex;flex-direction:row;align-items:center;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> Actif</label>
    </div>

    <h3 style="margin-top:26px;">Tarifs et règles</h3>
    <p style="color:#64748b;">Ajoutez une ligne par zone. Laissez ville/commune vide pour une règle générale.</p>

    @php
        $rates = old('rates', $service->rates?->map(fn($r) => $r->toArray())->toArray() ?: [[], []]);
        $rates = array_pad($rates, 2, []);
    @endphp
    @foreach($rates as $i => $rate)
        <div class="rate-row">
            <input type="hidden" name="rates[{{ $i }}][id]" value="{{ $rate['id'] ?? '' }}">
            <select name="rates[{{ $i }}][delivery_zone]">
                <option value="">Zone</option>
                <option value="abidjan" @selected(($rate['delivery_zone'] ?? '') === 'abidjan')>Abidjan</option>
                <option value="interieur" @selected(($rate['delivery_zone'] ?? '') === 'interieur')>Intérieur</option>
            </select>
            <input name="rates[{{ $i }}][city]" value="{{ $rate['city'] ?? '' }}" placeholder="Ville">
            <input name="rates[{{ $i }}][commune]" value="{{ $rate['commune'] ?? '' }}" placeholder="Commune">
            <input type="number" step="1" name="rates[{{ $i }}][base_fee]" value="{{ $rate['base_fee'] ?? '' }}" placeholder="Base">
            <input type="number" step="1" name="rates[{{ $i }}][price_per_kg]" value="{{ $rate['price_per_kg'] ?? '' }}" placeholder="/kg">
            <input type="number" step="1" name="rates[{{ $i }}][price_per_m3]" value="{{ $rate['price_per_m3'] ?? '' }}" placeholder="/m³">
            <input type="number" step="1" name="rates[{{ $i }}][fragile_fee]" value="{{ $rate['fragile_fee'] ?? '' }}" placeholder="Fragile">
            <input type="number" step="1" name="rates[{{ $i }}][unloading_fee]" value="{{ $rate['unloading_fee'] ?? '' }}" placeholder="Décharg.">
            <input type="number" step="1" name="rates[{{ $i }}][min_fee]" value="{{ $rate['min_fee'] ?? '' }}" placeholder="Min">
        </div>
    @endforeach

    <div style="display:flex;gap:12px;margin-top:22px;">
        <button class="btn primary" type="submit">Enregistrer</button>
        <a class="btn light" href="{{ route('admin.delivery-services.index') }}">Retour</a>
    </div>
</form>
@endsection
