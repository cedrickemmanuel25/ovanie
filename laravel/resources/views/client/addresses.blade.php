@extends('layouts.client')
@section('title', 'Mes adresses')
@section('content')
<section class="cs-page-head"><div><h1>Mes adresses de livraison</h1><p>{{ $addresses->count() }} adresses enregistrées</p></div><button class="cs-btn" data-modal-open="addressModal">Ajouter une nouvelle adresse</button></section>
<div class="cs-address-grid">
@foreach($addresses as $address)
    <article class="cs-card cs-address">
        <div class="cs-section-head"><h2>{{ ['home'=>'Domicile','office'=>'Bureau','site'=>'Chantier','other'=>'Autre'][$address->type] ?? 'Adresse' }}</h2>@if($address->is_default)<span class="cs-badge success">Par défaut</span>@endif</div>
        <strong>{{ $address->recipient_name }}</strong>
        <p>{{ $address->label }}<br>{{ $address->address }}<br>@if($address->quartier){{ $address->quartier }}<br>@endif{{ $address->commune }}, {{ $address->city }}<br>{{ $address->phone }}</p>
        <div class="cs-map-card"><span>{{ $address->commune ?: 'Abidjan' }}</span></div>
        <div class="cs-actions">
            <button class="cs-btn small outline" data-fill-address='@json($address)' data-modal-open="addressModal">Modifier</button>
            <form method="POST" action="{{ route('client.addresses.destroy',$address) }}" onsubmit="return confirm('Supprimer cette adresse ?')">@csrf @method('DELETE')<button class="cs-btn small danger">Supprimer</button></form>
            @unless($address->is_default)<form method="POST" action="{{ route('client.addresses.default',$address) }}">@csrf<button class="cs-btn small ghost">Définir par défaut</button></form>@endunless
        </div>
    </article>
@endforeach
    <button class="cs-add-card" data-modal-open="addressModal"><span>+</span><strong>Ajouter une nouvelle adresse</strong><small>Maison, chantier, bureau...</small></button>
</div>

<div class="cs-modal" id="addressModal" aria-hidden="true"><div class="cs-modal-panel"><button class="cs-modal-close" data-modal-close>×</button><h2>Ajouter / Modifier une adresse</h2>
<form method="POST" action="{{ route('client.addresses.store') }}" data-address-form>@csrf <input type="hidden" name="_method" value="POST">
    <div class="cs-form-grid">
        <label>Type<select name="type" required><option value="home">Domicile</option><option value="office">Bureau</option><option value="site">Chantier</option><option value="other">Autre</option></select></label>
        <label>Nom complet<input name="recipient_name" required value="{{ auth()->user()->name }}"></label>
        <label>Libellé<input name="label" required placeholder="Résidence Les Palmiers"></label>
        <label>Ville<input name="city" required value="Abidjan"></label>
        <label>Commune<input name="commune" required placeholder="Yopougon"></label>
        <label>Quartier / repère<input name="quartier" placeholder="Ex. Niangon, Riviera Palmeraie, près de la pharmacie..."></label>
        <label>Téléphone<input name="phone" required value="{{ auth()->user()->phone }}"></label>
        <label class="span-2">Adresse complète<textarea name="address" required></textarea></label>
        <label class="cs-check span-2"><input type="checkbox" name="is_default" value="1"> Définir comme adresse par défaut</label>
    </div>
    <div class="cs-modal-actions"><button type="button" class="cs-btn outline" data-modal-close>Annuler</button><button class="cs-btn">Enregistrer</button></div>
</form></div></div>
@endsection
