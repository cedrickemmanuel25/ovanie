@extends('layouts.staff')
@section('title', 'Créer un ticket | Support OVANIE')
@section('content')
@php
    $value = fn($key,$default=null) => old($key, $defaults[$key] ?? $default);
    $linked = collect(['requester_user_id'=>'Client','order_id'=>'Commande','shop_id'=>'Boutique','payment_id'=>'Paiement','shipment_id'=>'Livraison','return_id'=>'Retour','dispute_id'=>'Litige','delivery_incident_id'=>'Incident','submission_id'=>'Message entrant'])->filter(fn($label,$key)=>filled($value($key)));
@endphp
<div class="page-header"><div><h1 class="page-title">Créer un ticket Support</h1><p class="page-subtitle">Le ticket sera relié aux données opérationnelles sélectionnées sans recopier leur contenu.</p></div><div class="page-actions"><a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="arrow-left"></i>Retour</a></div></div>
@if($linked->isNotEmpty())<div class="alert alert-success">Dossier prérempli : @foreach($linked as $key=>$label)<strong>{{ $label }} #{{ $value($key) }}</strong>@if(!$loop->last) · @endif @endforeach</div>@endif
<form method="POST" action="{{ route('support.tickets.store') }}" class="card">@csrf
    @foreach(['requester_user_id','order_id','shop_id','payment_id','shipment_id','return_id','dispute_id','delivery_incident_id','submission_id'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $value($field) }}">@endforeach
    <div class="form-grid">
        <div class="form-group"><label>Nom du demandeur</label><input name="requester_name" value="{{ old('requester_name') }}" placeholder="Nom complet ou raison sociale"></div>
        <div class="form-group"><label>Canal *</label><select name="channel" required>@foreach(['internal'=>'Interne','phone'=>'Téléphone','email'=>'E-mail','whatsapp'=>'WhatsApp','web'=>'Formulaire web','social'=>'Réseaux sociaux'] as $v=>$l)<option value="{{ $v }}" @selected(old('channel','internal')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="requester_email" value="{{ old('requester_email') }}"></div>
        <div class="form-group"><label>Téléphone</label><input name="requester_phone" value="{{ old('requester_phone') }}"></div>
        <div class="form-group"><label>Catégorie *</label><select name="category" required>@foreach(['general'=>'Général','account'=>'Compte','order'=>'Commande','payment'=>'Paiement','delivery'=>'Livraison','return'=>'Retour / remboursement','dispute'=>'Litige','vendor'=>'Vendeur','product'=>'Produit','business'=>'OVANIE Pro'] as $v=>$l)<option value="{{ $v }}" @selected(old('category','general')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Priorité *</label><select name="priority" required>@foreach(['low'=>'Faible','normal'=>'Normale','high'=>'Haute','urgent'=>'Urgente'] as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Équipe *</label><select name="team" required>@foreach(['support'=>'Support général','payments'=>'Paiements','logistics'=>'Logistique','returns'=>'Retours','disputes'=>'Litiges','vendors'=>'Vendeurs','business'=>'OVANIE Pro'] as $v=>$l)<option value="{{ $v }}" @selected(old('team','support')===$v)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-group"><label>Assigner à</label><select name="assigned_to"><option value="">Moi-même par défaut</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)old('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select></div>
        <div class="form-group full"><label>Objet *</label><input name="subject" value="{{ old('subject') }}" required maxlength="255" placeholder="Résumé précis de la demande"></div>
        <div class="form-group full"><label>Description complète *</label><textarea name="description" required placeholder="Contexte, vérifications déjà faites et résultat attendu…">{{ old('description') }}</textarea><span class="form-help">N’ajoutez pas de données sensibles inutiles. Les références liées sont déjà accessibles depuis le dossier.</span></div>
    </div>
    <div class="page-actions" style="margin-top:16px;justify-content:flex-end"><a class="btn" href="{{ route('support.tickets.index') }}">Annuler</a><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Créer le ticket</button></div>
</form>
@endsection
