@extends('admin.layouts.app')

@section('title', 'Détail du dossier | Administration OVANIE')
@section('page-title', 'Détail du dossier')

@section('content')
@php
    $fullName = trim(($submission->prenom ?? '') . ' ' . ($submission->nom ?? '')) ?: 'Demandeur non renseigné';
    $isValidated = ($submission->commission_status ?? 'pending') === 'validated';
    $description = $submission->projet ?? $submission->description ?? $submission->message ?? 'Aucune description fournie.';
@endphp

<main class="submission-admin-page">
    <section class="submission-detail-hero">
        <div>
            <p class="submission-eyebrow">DOSSIER #{{ $submission->id }}</p>
            <h1>{{ $submission->type }}</h1>
            <p>Demande transmise par {{ $fullName }} le {{ optional($submission->created_at)->format('d/m/Y à H:i') ?? 'date indisponible' }}.</p>
        </div>
        <a href="{{ route('admin.submissions.indexAll') }}" class="submission-button submission-button--secondary">Retour aux dossiers</a>
    </section>

    <section class="submission-detail-layout">
        <div class="submission-detail-main">
            <article class="submission-detail-card">
                <div class="submission-detail-card__heading">
                    <div>
                        <p class="submission-eyebrow">IDENTITÉ ET CONTACT</p>
                        <h2>{{ $fullName }}</h2>
                    </div>
                    <span class="submission-badge {{ $isValidated ? 'submission-badge--success' : 'submission-badge--warning' }}">
                        {{ $isValidated ? 'Commission validée' : 'Commission en attente' }}
                    </span>
                </div>

                <div class="submission-detail-grid">
                    <div><span>Email</span><strong>{{ $submission->email ?: 'Non renseigné' }}</strong></div>
                    <div><span>Téléphone</span><strong>{{ $submission->telephone ?: 'Non renseigné' }}</strong></div>
                    <div><span>Ville</span><strong>{{ $submission->ville ?: 'Non renseignée' }}</strong></div>
                    <div><span>Pays</span><strong>{{ $submission->pays ?: 'Non renseigné' }}</strong></div>
                    <div><span>Secteur</span><strong>{{ $submission->secteur ?: 'Non renseigné' }}</strong></div>
                    <div><span>Activité ou service</span><strong>{{ $submission->activites ?: 'Non renseigné' }}</strong></div>
                    @if(!empty($submission->delai))
                        <div><span>Délai souhaité</span><strong>{{ $submission->delai }}</strong></div>
                    @endif
                </div>
            </article>

            <article class="submission-detail-card">
                <div class="submission-detail-card__heading">
                    <div>
                        <p class="submission-eyebrow">BESOIN EXPRIMÉ</p>
                        <h2>Projet ou description</h2>
                    </div>
                </div>
                <div class="submission-detail-description">{{ $description }}</div>
            </article>

            @if(!empty($submission->image_path))
                <article class="submission-detail-card">
                    <div class="submission-detail-card__heading">
                        <div>
                            <p class="submission-eyebrow">PIÈCE JOINTE</p>
                            <h2>Visuel transmis</h2>
                        </div>
                    </div>
                    <a href="{{ asset($submission->image_path) }}" target="_blank" rel="noopener" class="submission-document-preview">
                        <img src="{{ asset($submission->image_path) }}" alt="Pièce jointe du dossier">
                        <span>Ouvrir le fichier</span>
                    </a>
                </article>
            @endif
        </div>

        <aside class="submission-detail-side">
            <article class="submission-detail-card">
                <p class="submission-eyebrow">SYNTHÈSE FINANCIÈRE</p>
                <div class="submission-detail-amount">
                    <span>Budget déclaré</span>
                    <strong>{{ number_format($submission->budget ?? 0, 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="submission-detail-amount submission-detail-amount--green">
                    <span>Commission indicative</span>
                    <strong>{{ number_format($submission->commission ?? 0, 0, ',', ' ') }} FCFA</strong>
                </div>
            </article>

            <article class="submission-detail-card">
                <p class="submission-eyebrow">ACTIONS</p>
                <div class="submission-detail-actions">
                    @unless($isValidated)
                        <form method="POST" action="{{ route('admin.submissions.validateCommission', ['type' => $type, 'id' => $submission->id]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="submission-button submission-button--success">Valider la commission</button>
                        </form>
                    @endunless

                    <a href="mailto:{{ $submission->email }}" class="submission-button submission-button--secondary">Écrire au demandeur</a>

                    <form method="POST" action="{{ route('admin.submissions.destroy', ['type' => $type, 'id' => $submission->id]) }}" onsubmit="return confirm('Supprimer définitivement ce dossier ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="submission-button submission-button--danger">Supprimer le dossier</button>
                    </form>
                </div>
            </article>
        </aside>
    </section>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_submissions.css') }}">
@endpush
