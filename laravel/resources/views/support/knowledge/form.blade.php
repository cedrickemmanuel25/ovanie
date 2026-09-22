@extends('layouts.staff')
@section('title', ($article->exists ? 'Modifier' : 'Créer').' un article | OVANIE')
@section('content')
<div class="page-header"><div><h1 class="page-title">{{ $article->exists ? 'Modifier l’article' : 'Nouvel article de connaissance' }}</h1><p class="page-subtitle">Seules les versions publiées, approuvées et non expirées sont disponibles pour N’Nan, Miss Rita et Miss Salomé.</p></div><div class="page-actions"><a class="btn" href="{{ route('support.knowledge.index') }}">Retour</a></div></div>
<section class="card">
@if($article->exists)<div class="alert alert-success">Version actuelle : v{{ $article->version }}. Toute modification du titre ou du contenu incrémente automatiquement la version.</div>@endif
<form method="POST" action="{{ $article->exists ? route('support.knowledge.update',$article) : route('support.knowledge.store') }}">@csrf @if($article->exists) @method('PUT') @endif
<div class="form-grid">
    <div class="form-group full"><label>Titre</label><input name="title" required value="{{ old('title',$article->title) }}"></div>
    <div class="form-group"><label>Catégorie</label><input name="category" required value="{{ old('category',$article->category ?: 'general') }}"></div>
    <div class="form-group"><label>Statut</label><select name="status" required>@foreach(['draft'=>'Brouillon','published'=>'Publié et approuvé','archived'=>'Archivé'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$article->status ?: 'draft')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div class="form-group"><label>Type de source</label><select name="source_type" required>@foreach(['manual'=>'Saisie manuelle','policy'=>'Politique OVANIE','faq'=>'FAQ','procedure'=>'Procédure'] as $value=>$label)<option value="{{ $value }}" @selected(old('source_type',$article->source_type ?: 'manual')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div class="form-group"><label>Référence de la source</label><input name="source_reference" value="{{ old('source_reference',$article->source_reference) }}" placeholder="Ex. Procédure livraison v2"></div>
    <div class="form-group"><label>Date d’expiration (facultative)</label><input type="datetime-local" name="expires_at" value="{{ old('expires_at',$article->expires_at?->format('Y-m-d\TH:i')) }}"></div>
    <div class="form-group full"><label>Contenu validé</label><textarea name="content" required style="min-height:320px">{{ old('content',$article->content) }}</textarea><span class="form-help">N’insérez pas de clés API, mots de passe ou données personnelles. Un article archivé ou expiré n’est jamais envoyé au moteur IA.</span></div>
</div>
<button class="btn btn-primary" type="submit" style="margin-top:14px"><i data-lucide="save"></i>Enregistrer</button>
</form></section>
@endsection
