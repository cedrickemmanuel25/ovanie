@extends('layouts.staff')
@section('title', 'Nouveau dossier de suivi | Support OVANIE')
@section('content')
@php
    $value = fn($key,$default=null) => old($key, $defaults[$key] ?? $default);
    $linked = collect([
        'requester_user_id'=>'Compte OVANIE','delivery_driver_id'=>'Livreur partenaire','order_id'=>'Commande',
        'shop_id'=>'Boutique','payment_id'=>'Paiement','shipment_id'=>'Livraison','return_id'=>'Retour',
        'dispute_id'=>'Litige','delivery_incident_id'=>'Incident','submission_id'=>'Message entrant'
    ])->filter(fn($label,$key)=>filled($value($key)));
    $defaultRequesterType = $value('requester_type', filled($value('delivery_driver_id')) ? 'driver' : 'guest');
@endphp
<div class="page-header">
    <div class="page-header-main">
        <span class="page-header-icon"><i data-lucide="circle-plus"></i></span>
        <div>
            <h1 class="page-title">Créer un dossier de suivi</h1>
            <p class="page-subtitle">À utiliser lorsqu’une préoccupation doit être suivie dans le temps. Le dossier reste sous la responsabilité du Support. Si une action métier est nécessaire, vous pourrez ensuite le transmettre à la Logistique, au Commercial ou à l’Administration.</p>
        </div>
    </div>
    <div class="page-actions"><a class="btn" href="{{ route('support.tickets.index') }}"><i data-lucide="arrow-left"></i>Retour aux dossiers</a></div>
</div>

<div class="notice"><i data-lucide="info"></i><div><strong>À quoi sert ce dossier ?</strong> Il formalise un problème signalé par un client, un vendeur, un livreur partenaire, un commercial ou un visiteur. Une simple question qui reçoit une réponse immédiate peut rester dans la Boîte de réception sans créer de dossier.</div></div>

@if($linked->isNotEmpty())
<div class="notice"><i data-lucide="link"></i><div><strong>Contexte déjà lié :</strong> @foreach($linked as $key=>$label){{ $label }} #{{ $value($key) }}@if(!$loop->last) · @endif @endforeach. Ces données restent dans leurs modules d’origine et sont seulement reliées au dossier.</div></div>
@endif

<form method="POST" action="{{ route('support.tickets.store') }}">@csrf
    @foreach(['requester_user_id','delivery_driver_id','context_type','context_id','order_id','shop_id','payment_id','shipment_id','return_id','dispute_id','delivery_incident_id','submission_id'] as $field)
        <input type="hidden" name="{{ $field }}" value="{{ $value($field) }}">
    @endforeach

    <section class="form-section">
        <div class="form-section-title"><span><i data-lucide="user-round"></i></span><div><h3>1. Pour qui est ce dossier ?</h3><p>Identifiez la personne ou l’acteur OVANIE à l’origine de la demande.</p></div></div>
        <div class="form-grid">
            <div class="form-group">
                <label>Type de demandeur *</label>
                <select name="requester_type" required>
                    @foreach(['client'=>'Client','vendor'=>'Vendeur','driver'=>'Livreur partenaire','commercial'=>'Commercial','guest'=>'Visiteur / non identifié'] as $v=>$l)
                        <option value="{{ $v }}" @selected($defaultRequesterType===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Canal d’origine *</label><select name="channel" required>@foreach(['internal'=>'Interne / saisi par le Support','phone'=>'Téléphone','email'=>'E-mail','whatsapp'=>'WhatsApp','web'=>'Application / site','social'=>'Réseaux sociaux'] as $v=>$l)<option value="{{ $v }}" @selected(old('channel','internal')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="form-group"><label>Nom du demandeur</label><input name="requester_name" value="{{ old('requester_name') }}" placeholder="Nom complet ou raison sociale"></div>
            <div class="form-group"><label>Téléphone</label><input name="requester_phone" value="{{ old('requester_phone') }}"></div>
            <div class="form-group full"><label>E-mail</label><input type="email" name="requester_email" value="{{ old('requester_email') }}"></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-title"><span><i data-lucide="tags"></i></span><div><h3>2. Qualification du suivi</h3><p>Le Support reste l’équipe propriétaire du dossier. La catégorie aide seulement à comprendre et prioriser la demande.</p></div></div>
        <div class="form-grid">
            <div class="form-group"><label>Catégorie *</label><select name="category" required>@foreach(['general'=>'Général','account'=>'Compte','order'=>'Commande','payment'=>'Paiement','delivery'=>'Livraison','return'=>'Retour / remboursement','dispute'=>'Litige','vendor'=>'Vendeur','product'=>'Produit','business'=>'OVANIE Business'] as $v=>$l)<option value="{{ $v }}" @selected(old('category','general')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="form-group"><label>Priorité *</label><select name="priority" required>@foreach(['low'=>'Faible','normal'=>'Normale','high'=>'Haute','urgent'=>'Urgente'] as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="form-group full"><label>Conseiller Support responsable</label><select name="assigned_to"><option value="">Moi-même par défaut</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)old('assigned_to')===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select><span class="form-help">Le transfert à une autre équipe OVANIE se fait ensuite depuis la fiche du dossier.</span></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-title"><span><i data-lucide="file-text"></i></span><div><h3>3. Préoccupation à suivre</h3><p>Décrivez les faits utiles. Le Support pourra répondre directement ou demander l’intervention d’une autre équipe OVANIE.</p></div></div>
        <div class="form-grid">
            <div class="form-group full"><label>Objet *</label><input name="subject" value="{{ old('subject') }}" required maxlength="255" placeholder="Ex. Paiement débité mais commande non confirmée"></div>
            <div class="form-group full"><label>Description *</label><textarea name="description" required placeholder="Décrivez la préoccupation, les vérifications déjà faites et ce qui doit être vérifié…">{{ old('description') }}</textarea><span class="form-help">N’ajoutez pas de données sensibles inutiles. Les commandes, paiements, livraisons et autres références liées sont déjà consultables depuis le dossier.</span></div>
        </div>
    </section>

    <div class="page-actions" style="margin-top:16px;justify-content:flex-end"><a class="btn" href="{{ route('support.tickets.index') }}">Annuler</a><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Créer le dossier de suivi</button></div>
</form>
@endsection
