@extends('admin.layouts.app')

@section('title', "Devis et appels d'offres | Administration OVANIE")
@section('page-title', "Devis et appels d'offres")

@section('content')
<main class="submission-admin-page">
    <section class="submission-hero">
        <div>
            <p class="submission-eyebrow">OVANIE PRO</p>
            <h1>Devis et appels d’offres</h1>
            <p>Consultez les demandes reçues, contrôlez leur budget et suivez la validation des commissions.</p>
        </div>

        @if(request()->hasAny(['q', 'type', 'commission_status', 'sort']))
            <a href="{{ route('admin.submissions.indexAll') }}" class="submission-button submission-button--secondary">
                Réinitialiser les filtres
            </a>
        @endif
    </section>

    <section class="submission-stats">
        <article class="submission-stat">
            <span class="submission-stat__icon submission-stat__icon--blue">D</span>
            <div><small>Total des dossiers</small><strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong><span>Toutes les demandes reçues</span></div>
        </article>
        <article class="submission-stat">
            <span class="submission-stat__icon submission-stat__icon--orange">DV</span>
            <div><small>Demandes de devis</small><strong>{{ number_format($stats['devis'], 0, ',', ' ') }}</strong><span>Besoins chiffrés par les clients</span></div>
        </article>
        <article class="submission-stat">
            <span class="submission-stat__icon submission-stat__icon--purple">AO</span>
            <div><small>Appels d’offres</small><strong>{{ number_format($stats['appel_offre'], 0, ',', ' ') }}</strong><span>Consultations professionnelles</span></div>
        </article>
        <article class="submission-stat">
            <span class="submission-stat__icon submission-stat__icon--green">F</span>
            <div><small>Budget déclaré</small><strong>{{ number_format($stats['budget'], 0, ',', ' ') }} FCFA</strong><span>Somme des budgets renseignés</span></div>
        </article>
    </section>

    <section class="submission-filter-card">
        <div class="submission-section-heading">
            <div>
                <p class="submission-eyebrow">RECHERCHE ET FILTRES</p>
                <h2>Retrouver un dossier</h2>
            </div>
            <span>{{ $allSubmissions->total() }} résultat(s)</span>
        </div>

        <form method="GET" action="{{ route('admin.submissions.indexAll') }}" class="submission-filter-form">
            <div class="submission-field submission-field--wide">
                <label for="submission_q">Client, email, téléphone, projet ou ville</label>
                <input id="submission_q" type="search" name="q" value="{{ request('q') }}" placeholder="Ex. Kouamé, Abidjan, ciment...">
            </div>
            <div class="submission-field">
                <label for="submission_type">Type</label>
                <select id="submission_type" name="type">
                    <option value="">Tous les types</option>
                    <option value="devis" @selected(request('type') === 'devis')>Devis</option>
                    <option value="appel_offre" @selected(request('type') === 'appel_offre')>Appels d’offres</option>
                </select>
            </div>
            <div class="submission-field">
                <label for="submission_status">Commission</label>
                <select id="submission_status" name="commission_status">
                    <option value="">Tous les états</option>
                    <option value="pending" @selected(request('commission_status') === 'pending')>En attente</option>
                    <option value="validated" @selected(request('commission_status') === 'validated')>Validée</option>
                </select>
            </div>
            <div class="submission-field">
                <label for="submission_sort">Classement</label>
                <select id="submission_sort" name="sort">
                    <option value="recent" @selected(request('sort', 'recent') === 'recent')>Plus récents</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                    <option value="budget_desc" @selected(request('sort') === 'budget_desc')>Budget décroissant</option>
                    <option value="budget_asc" @selected(request('sort') === 'budget_asc')>Budget croissant</option>
                </select>
            </div>
            <button type="submit" class="submission-button submission-button--primary">Rechercher</button>
        </form>
    </section>

    <section class="submission-list-card">
        <div class="submission-list-heading">
            <div>
                <p class="submission-eyebrow">DOSSIERS REÇUS</p>
                <h2>Historique des soumissions</h2>
            </div>
            <span>{{ $allSubmissions->firstItem() ?? 0 }}–{{ $allSubmissions->lastItem() ?? 0 }} sur {{ $allSubmissions->total() }}</span>
        </div>

        <div class="submission-list">
            @forelse($allSubmissions as $sub)
                @php
                    $fullName = trim(($sub->prenom ?? '') . ' ' . ($sub->nom ?? '')) ?: 'Demandeur non renseigné';
                    $initial = mb_strtoupper(mb_substr($fullName, 0, 1));
                    $isValidated = ($sub->commission_status ?? 'pending') === 'validated';
                    $description = $sub->projet ?? $sub->description ?? $sub->message ?? 'Aucune description fournie.';
                @endphp

                <article class="submission-item">
                    <div class="submission-item__header">
                        <div class="submission-identity">
                            <span class="submission-avatar">{{ $initial }}</span>
                            <div>
                                <div class="submission-title-line">
                                    <h3>{{ $fullName }}</h3>
                                    <span class="submission-badge submission-badge--type">{{ $sub->type }}</span>
                                    <span class="submission-badge {{ $isValidated ? 'submission-badge--success' : 'submission-badge--warning' }}">
                                        {{ $isValidated ? 'Commission validée' : 'Commission en attente' }}
                                    </span>
                                </div>
                                <p>{{ $sub->ville ?: 'Ville non renseignée' }}{{ $sub->pays ? ' · ' . $sub->pays : '' }} · Dossier #{{ $sub->id }}</p>
                            </div>
                        </div>
                        <time datetime="{{ optional($sub->created_at)->toIso8601String() }}">
                            {{ optional($sub->created_at)->format('d/m/Y à H:i') ?? 'Date indisponible' }}
                        </time>
                    </div>

                    <div class="submission-item__body">
                        <div class="submission-contact-grid">
                            <div><span>Email</span><strong>{{ $sub->email ?: 'Non renseigné' }}</strong></div>
                            <div><span>Téléphone</span><strong>{{ $sub->telephone ?: 'Non renseigné' }}</strong></div>
                            <div><span>Secteur</span><strong>{{ $sub->secteur ?: 'Non renseigné' }}</strong></div>
                            <div><span>Activité</span><strong>{{ $sub->activites ?: 'Non renseignée' }}</strong></div>
                        </div>

                        <div class="submission-finance">
                            <div><span>Budget</span><strong>{{ number_format($sub->budget ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                            <div><span>Commission indicative</span><strong>{{ number_format($sub->commission ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                        </div>
                    </div>

                    <div class="submission-description">
                        <span>Projet ou besoin exprimé</span>
                        <p>{{ \Illuminate\Support\Str::limit($description, 280) }}</p>
                    </div>

                    <div class="submission-item__footer">
                        <div class="submission-actions">
                            <a href="{{ route('admin.submissions.show', ['type' => $sub->source_type, 'id' => $sub->id]) }}" class="submission-button submission-button--secondary">Examiner le dossier</a>

                            @unless($isValidated)
                                <form method="POST" action="{{ route('admin.submissions.validateCommission', ['type' => $sub->source_type, 'id' => $sub->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="submission-button submission-button--success">Valider la commission</button>
                                </form>
                            @endunless
                        </div>

                        <form method="POST" action="{{ route('admin.submissions.destroy', ['type' => $sub->source_type, 'id' => $sub->id]) }}" onsubmit="return confirm('Supprimer définitivement ce dossier ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="submission-button submission-button--danger">Supprimer</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="submission-empty">
                    <span>📄</span>
                    <h3>Aucune soumission trouvée</h3>
                    <p>Aucun devis ni appel d’offres ne correspond aux critères sélectionnés.</p>
                </div>
            @endforelse
        </div>

        @if($allSubmissions->hasPages())
            <div class="submission-pagination">{{ $allSubmissions->links() }}</div>
        @endif
    </section>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_submissions.css') }}">
@endpush
