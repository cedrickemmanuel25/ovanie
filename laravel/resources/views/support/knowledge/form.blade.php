@extends('layouts.staff')
@section('title', ($article->exists ? 'Modifier une procédure' : 'Nouvelle procédure').' | OVANIE Support')
@section('content')
<div class="page-header">
    <div>
        <span class="eyebrow"><i data-lucide="book-open-check"></i> Réponses & procédures</span>
        <h1 class="page-title">{{ $article->exists ? 'Modifier la procédure' : 'Créer une réponse ou procédure' }}</h1>
        <p class="page-subtitle">Enregistrez ici une consigne officielle afin que toute l’équipe Support utilise la même réponse et la même méthode de traitement.</p>
    </div>
    <div class="page-actions"><a class="btn" href="{{ route('support.knowledge.index') }}"><i data-lucide="arrow-left"></i>Retour</a></div>
</div>

@if($article->exists)
    <div class="notice notice-info"><i data-lucide="history"></i><div><strong>Version {{ $article->version }}</strong><span>Une modification importante du titre ou du contenu crée automatiquement une nouvelle version de référence.</span></div></div>
@endif

<form method="POST" action="{{ $article->exists ? route('support.knowledge.update',$article) : route('support.knowledge.store') }}">
    @csrf
    @if($article->exists) @method('PUT') @endif

    <section class="card form-section">
        <div class="form-section-title"><span>1</span><div><h3>Identification</h3><p>Donnez un titre clair et classez la procédure pour la retrouver rapidement.</p></div></div>
        <div class="form-grid">
            <div class="form-group full"><label>Titre *</label><input name="title" required value="{{ old('title',$article->title) }}" placeholder="Ex. Livraison en retard — procédure de traitement"></div>
            <div class="form-group"><label>Catégorie *</label><input name="category" required value="{{ old('category',$article->category ?: 'general') }}" placeholder="Ex. Livraison, Paiement, Vendeur"></div>
            <div class="form-group"><label>Disponibilité *</label><select name="status" required>@foreach(['draft'=>'Brouillon','published'=>'Disponible pour l’équipe','archived'=>'Archivée'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$article->status ?: 'draft')===$value)>{{ $label }}</option>@endforeach</select></div>
        </div>
    </section>

    <section class="card form-section">
        <div class="form-section-title"><span>2</span><div><h3>Source et validité</h3><p>Indiquez d’où vient la consigne et, si nécessaire, jusqu’à quand elle est valable.</p></div></div>
        <div class="form-grid">
            <div class="form-group"><label>Type de source *</label><select name="source_type" required>@foreach(['manual'=>'Consigne interne','policy'=>'Politique OVANIE','faq'=>'Question fréquente','procedure'=>'Procédure opérationnelle'] as $value=>$label)<option value="{{ $value }}" @selected(old('source_type',$article->source_type ?: 'manual')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="form-group"><label>Référence de la source</label><input name="source_reference" value="{{ old('source_reference',$article->source_reference) }}" placeholder="Ex. Procédure logistique v2"></div>
            <div class="form-group"><label>Date d’expiration</label><input type="datetime-local" name="expires_at" value="{{ old('expires_at',$article->expires_at?->format('Y-m-d\TH:i')) }}"></div>
        </div>
    </section>

    <section class="card form-section">
        <div class="form-section-title"><span>3</span><div><h3>Réponse ou procédure</h3><p>Rédigez ce que le conseiller doit vérifier, expliquer ou faire dans cette situation.</p></div></div>
        <div class="form-group"><label>Contenu officiel *</label><textarea name="content" required style="min-height:320px" placeholder="Décrivez les étapes de traitement, les vérifications à effectuer et la réponse à donner…">{{ old('content',$article->content) }}</textarea><span class="form-help">N’ajoutez pas de mots de passe, clés techniques ou données personnelles d’un utilisateur.</span></div>
        <div class="page-actions" style="margin-top:18px"><a class="btn" href="{{ route('support.knowledge.index') }}">Annuler</a><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>{{ $article->exists ? 'Enregistrer les modifications' : 'Publier la procédure' }}</button></div>
    </section>
</form>
@endsection
