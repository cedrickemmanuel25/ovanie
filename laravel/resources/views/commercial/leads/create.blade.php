@extends('layouts.staff')
@section('title', 'Nouvelle opportunité | Commercial OVANIE')
@section('content')
@php
    $value = fn($key,$default=null) => old($key, $defaults[$key] ?? $default);
    $linked = collect(['user_id'=>'Client','shop_id'=>'Boutique','business_request_id'=>'Demande Business','devis_id'=>'Devis','appel_offre_id'=>'Appel d’offre'])->filter(fn($label,$key)=>filled($value($key)));
@endphp
<div class="page-header"><div><h1 class="page-title">Nouvelle opportunité</h1><p class="page-subtitle">Créez un suivi commercial lié aux données existantes sans créer de doublon client, vendeur ou demande.</p></div><div class="page-actions"><a class="btn" href="{{ route('commercial.leads.index') }}"><i data-lucide="arrow-left"></i>Retour</a></div></div>
@if($linked->isNotEmpty())<div class="alert alert-success">Dossier prérempli : @foreach($linked as $key=>$label)<strong>{{ $label }} #{{ $value($key) }}</strong>@if(!$loop->last) · @endif @endforeach</div>@endif
<form method="POST" action="{{ route('commercial.leads.store') }}" class="card">@csrf
    @foreach(['user_id','shop_id','business_request_id','devis_id','appel_offre_id'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $value($field) }}">@endforeach
    <div class="form-grid">
        <div class="form-group"><label>Source *</label><select name="source" required>@foreach(['manual'=>'Saisie manuelle','website'=>'Site public','client'=>'Espace client','vendor'=>'Espace vendeur','business_request'=>'Demande Business','devis'=>'Devis','appel_offre'=>'Appel d’offre','referral'=>'Recommandation','campaign'=>'Campagne'] as $v=>$l)<option value="{{ $v }}" @selected($value('source','manual')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Type de profil *</label><select name="lead_type" required>@foreach(['buyer'=>'Acheteur','vendor'=>'Vendeur','business'=>'Client Business','partner'=>'Partenaire'] as $v=>$l)<option value="{{ $v }}" @selected($value('lead_type','buyer')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Société / boutique</label><input name="company_name" value="{{ $value('company_name') }}" placeholder="Raison sociale ou boutique"></div>
        <div class="form-group"><label>Contact principal *</label><input name="contact_name" value="{{ $value('contact_name') }}" required></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="email" value="{{ $value('email') }}"></div>
        <div class="form-group"><label>Téléphone</label><input name="phone" value="{{ $value('phone') }}"></div>
        <div class="form-group"><label>Ville</label><input name="city" value="{{ $value('city') }}"></div>
        <div class="form-group"><label>Secteur</label><input name="sector" value="{{ $value('sector') }}"></div>
        <div class="form-group full"><label>Objet de l’opportunité *</label><input name="title" value="{{ $value('title') }}" required maxlength="255" placeholder="Ex. Fourniture de ciment pour chantier de 20 logements"></div>
        <div class="form-group full"><label>Besoin / contexte</label><textarea name="need_summary" placeholder="Besoin, volumes, contraintes, échéance et prochaine étape…">{{ $value('need_summary') }}</textarea></div>
        <div class="form-group"><label>Valeur estimée</label><input type="number" min="0" step="1" name="estimated_value" value="{{ $value('estimated_value',0) }}"></div>
        <div class="form-group"><label>Probabilité (%)</label><input type="number" min="0" max="100" name="probability" value="{{ $value('probability',10) }}"></div>
        <div class="form-group"><label>Étape *</label><select name="status" required>@foreach(['new'=>'Nouveau','qualified'=>'Qualifié','proposal'=>'Proposition','negotiation'=>'Négociation','won'=>'Gagné','lost'=>'Perdu','cancelled'=>'Annulé'] as $v=>$l)<option value="{{ $v }}" @selected($value('status','new')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Conseiller assigné</label><select name="assigned_to"><option value="">Moi-même par défaut</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)$value('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select></div>
        <div class="form-group"><label>Date de conclusion prévue</label><input type="date" name="expected_close_at" value="{{ $value('expected_close_at') }}"></div>
        <div class="form-group"><label>Prochaine action</label><input type="datetime-local" name="next_action_at" value="{{ $value('next_action_at') }}"></div>
        <div class="form-group full"><label>Raison de perte</label><textarea name="lost_reason" style="min-height:80px">{{ $value('lost_reason') }}</textarea></div>
    </div>
    <div class="page-actions" style="margin-top:16px;justify-content:flex-end"><a class="btn" href="{{ route('commercial.leads.index') }}">Annuler</a><button class="btn btn-orange" type="submit"><i data-lucide="save"></i>Créer l’opportunité</button></div>
</form>
@endsection
