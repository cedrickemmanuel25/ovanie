@extends('admin.layouts.app')
@section('title','Nouvelle mission de prospection | Admin OVANIE')
@section('page-title','Nouvelle mission de prospection')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_prospecting.css') }}">@endpush
@section('content')
<div class="prospecting-admin">
    <section class="prospecting-hero"><div><h2>Affecter une commune à une équipe</h2><p>La commune constitue le périmètre de mission. OVANIE charge automatiquement tous ses quartiers actifs afin que l’équipe puisse suivre précisément ce qui a été couvert pendant la période.</p></div><div class="prospecting-actions"><a class="prospecting-btn" href="{{ route('admin.prospecting-missions.index') }}">Retour aux missions</a></div></section>
    <section class="prospecting-card"><form class="prospecting-form" method="POST" action="{{ route('admin.prospecting-missions.store') }}">@csrf
        <div class="prospecting-grid">
            <div class="prospecting-field"><label>Commune à prospecter *</label><select name="commune_id" required><option value="">Choisir une commune</option>@foreach($communes as $commune)<option value="{{ $commune->id }}" @selected((int)old('commune_id')===$commune->id)>{{ $commune->name }}</option>@endforeach</select><span class="prospecting-help">Le commercial ne pourra pas changer de commune dans son espace web ou mobile.</span></div>
            <div class="prospecting-field"><label>Objectif boutiques ouvertes</label><input type="number" min="1" name="shop_target" value="{{ old('shop_target') }}" placeholder="Ex : 10"><span class="prospecting-help">Optionnel. Sert uniquement au suivi de la mission.</span></div>
            <div class="prospecting-field"><label>Date de début *</label><input type="date" name="starts_on" required value="{{ old('starts_on',now()->toDateString()) }}"></div>
            <div class="prospecting-field"><label>Date de fin *</label><input type="date" name="ends_on" required value="{{ old('ends_on',now()->addDays(2)->toDateString()) }}"><span class="prospecting-help">Exemple : du lundi au mercredi = mission de 3 jours.</span></div>
            <div class="prospecting-field full"><label>Équipe commerciale *</label><div class="commercial-checks">@foreach($commercials as $commercial)<label class="commercial-check"><input type="checkbox" name="commercial_ids[]" value="{{ $commercial->id }}" @checked(in_array($commercial->id,(array)old('commercial_ids',[])))><span><strong>{{ $commercial->name }}</strong><span>{{ $commercial->email }}{{ $commercial->phone ? ' · '.$commercial->phone : '' }}</span></span></label>@endforeach</div><span class="prospecting-help">Plusieurs commerciaux peuvent être affectés à la même commune et travailler en équipe. Un commercial ne peut pas avoir deux missions qui se chevauchent.</span></div>
            <div class="prospecting-field full"><label>Consignes de mission</label><textarea name="instructions" placeholder="Ex : cibler en priorité les quincailleries, magasins de plomberie, peinture et matériaux de construction. Faire inscrire les vendeurs intéressés et ouvrir leur boutique OVANIE.">{{ old('instructions') }}</textarea></div>
        </div>
        <div class="mission-note" style="margin-top:18px"><strong>Fonctionnement :</strong> dès la création, seuls les commerciaux sélectionnés voient la commune dans leur espace. Ils visualisent les quartiers de cette commune, enregistrent les vendeurs visités et marquent les quartiers couverts. Une fois tous les quartiers terminés, la mission se clôture automatiquement.</div>
        <div class="form-footer"><a class="prospecting-btn" href="{{ route('admin.prospecting-missions.index') }}">Annuler</a><button class="prospecting-btn prospecting-btn-primary" type="submit">Créer et affecter la mission</button></div>
    </form></section>
</div>
@endsection
