@extends('admin.layouts.app')

@section('title', 'Messages reçus | Administration OVANIE')
@section('page-title', 'Messages')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_messages.css') }}">
@endpush

@section('content')
@php
    $hasFilters = request()->filled('q')
        || request()->filled('date_from')
        || request()->filled('date_to')
        || request()->filled('sort');
@endphp

<div class="msg-page">
    <header class="msg-hero">
        <div class="msg-hero-copy">
            <span class="msg-kicker">CONTACT PUBLIC</span>
            <h2>Messages reçus depuis le site</h2>
            <p>
                Consultez les demandes envoyées par les visiteurs, retrouvez rapidement un expéditeur
                et répondez depuis l’adresse e-mail professionnelle d’OVANIE.
            </p>
        </div>

        <div class="msg-hero-actions">
            <a href="{{ route('admin.messages.index') }}" class="msg-btn msg-btn-secondary">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 11a8 8 0 1 1-2.34-5.66M20 4v7h-7" />
                </svg>
                Actualiser
            </a>
        </div>
    </header>

    @if($errors->any())
        <div class="msg-alert msg-alert-error" role="alert">
            <strong>Les filtres n’ont pas pu être appliqués.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="msg-stats" aria-label="Résumé des messages">
        <article class="msg-stat-card msg-stat-total">
            <div class="msg-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H7l-3 3V5Z" /></svg>
            </div>
            <div>
                <span>Total reçu</span>
                <strong>{{ number_format($summary['total'], 0, ',', ' ') }}</strong>
                <small>Tous les messages enregistrés</small>
            </div>
        </article>

        <article class="msg-stat-card msg-stat-today">
            <div class="msg-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3m10-3v3M4 8h16v12H4z" /><path d="M8 12h3m2 0h3m-8 4h3" /></svg>
            </div>
            <div>
                <span>Aujourd’hui</span>
                <strong>{{ number_format($summary['today'], 0, ',', ' ') }}</strong>
                <small>Messages reçus depuis minuit</small>
            </div>
        </article>

        <article class="msg-stat-card msg-stat-week">
            <div class="msg-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l4 2" /></svg>
            </div>
            <div>
                <span>7 derniers jours</span>
                <strong>{{ number_format($summary['last_seven_days'], 0, ',', ' ') }}</strong>
                <small>Activité récente du formulaire</small>
            </div>
        </article>

        <article class="msg-stat-card msg-stat-senders">
            <div class="msg-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20c0-4 2.5-6 6-6s6 2 6 6M17 7h4m-2-2v4" /></svg>
            </div>
            <div>
                <span>Expéditeurs uniques</span>
                <strong>{{ number_format($summary['unique_senders'], 0, ',', ' ') }}</strong>
                <small>Adresses e-mail distinctes</small>
            </div>
        </article>
    </section>

    <section class="msg-filter-card">
        <div class="msg-section-heading">
            <div>
                <span class="msg-section-kicker">RECHERCHE ET FILTRES</span>
                <h3>Retrouver un message</h3>
                <p>Recherchez par nom, adresse e-mail ou contenu du message.</p>
            </div>

            @if($hasFilters)
                <a href="{{ route('admin.messages.index') }}" class="msg-reset-link">Réinitialiser</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.messages.index') }}" class="msg-filter-form">
            <div class="msg-field msg-field-search">
                <label for="q">Nom, e-mail ou message</label>
                <div class="msg-input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Ex. Kouassi, contact@email.com..."
                    >
                </div>
            </div>

            <div class="msg-field">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
            </div>

            <div class="msg-field">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
            </div>

            <div class="msg-field">
                <label for="sort">Classement</label>
                <select id="sort" name="sort">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Plus récents</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                    <option value="name_asc" @selected(request('sort') === 'name_asc')>Nom de A à Z</option>
                    <option value="name_desc" @selected(request('sort') === 'name_desc')>Nom de Z à A</option>
                </select>
            </div>

            <button type="submit" class="msg-search-button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                Rechercher
            </button>
        </form>
    </section>

    <section class="msg-history-card">
        <div class="msg-history-heading">
            <div>
                <span class="msg-section-kicker">BOÎTE DE RÉCEPTION</span>
                <h3>Historique des messages</h3>
                <p>{{ number_format($submissions->total(), 0, ',', ' ') }} résultat(s) trouvé(s)</p>
            </div>
        </div>

        @if($submissions->isEmpty())
            <div class="msg-empty-state">
                <div class="msg-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H7l-3 3V5Z" /><path d="M8 10h8m-8 4h5" /></svg>
                </div>
                <h4>{{ $hasFilters ? 'Aucun message ne correspond aux filtres.' : 'Aucun message reçu pour le moment.' }}</h4>
                <p>
                    {{ $hasFilters
                        ? 'Modifiez les critères ou réinitialisez les filtres pour afficher d’autres messages.'
                        : 'Les messages envoyés depuis le formulaire public apparaîtront automatiquement ici.' }}
                </p>
            </div>
        @else
            <div class="msg-list">
                @foreach($submissions as $submission)
                    @php
                        $displayName = trim((string) $submission->name) !== '' ? $submission->name : 'Visiteur sans nom';
                        $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
                        $message = trim((string) $submission->message);
                        $isLong = mb_strlen($message) > 320;
                        $replySubject = rawurlencode('Réponse à votre message envoyé à OVANIE');
                        $replyBody = rawurlencode("Bonjour {$displayName},\n\nNous revenons vers vous concernant votre message envoyé à OVANIE.\n\nCordialement,\nL’équipe OVANIE");
                    @endphp

                    <article class="msg-card">
                        <div class="msg-card-header">
                            <div class="msg-sender">
                                <div class="msg-avatar">{{ $initial }}</div>
                                <div class="msg-sender-copy">
                                    <div class="msg-title-line">
                                        <h4>{{ $displayName }}</h4>
                                        <span class="msg-received-badge">Reçu</span>
                                    </div>
                                    <a href="mailto:{{ $submission->email }}" class="msg-email">{{ $submission->email ?: 'Adresse e-mail non renseignée' }}</a>
                                </div>
                            </div>

                            <div class="msg-date-block">
                                <span>Message #{{ $submission->id }}</span>
                                <strong>{{ $submission->created_at?->locale('fr')->translatedFormat('d F Y à H:i') }}</strong>
                            </div>
                        </div>

                        <div class="msg-card-body">
                            @if($message !== '')
                                @if($isLong)
                                    <p class="msg-preview">{{ \Illuminate\Support\Str::limit($message, 320) }}</p>
                                    <details class="msg-full-message">
                                        <summary>Lire le message complet</summary>
                                        <div>{!! nl2br(e($message)) !!}</div>
                                    </details>
                                @else
                                    <p>{!! nl2br(e($message)) !!}</p>
                                @endif
                            @else
                                <p class="msg-no-content">Aucun contenu n’a été enregistré pour ce message.</p>
                            @endif
                        </div>

                        <div class="msg-card-footer">
                            <div class="msg-source">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z" /><path d="m4 7 8 6 8-6" /></svg>
                                Formulaire public OVANIE
                            </div>

                            @if($submission->email)
                                <a
                                    href="mailto:{{ $submission->email }}?subject={{ $replySubject }}&body={{ $replyBody }}"
                                    class="msg-reply-button"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 17-5-5 5-5" /><path d="M4 12h10c4 0 6 2 6 6" /></svg>
                                    Répondre par e-mail
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if($submissions->hasPages())
                @php
                    $startPage = max(1, $submissions->currentPage() - 2);
                    $endPage = min($submissions->lastPage(), $submissions->currentPage() + 2);
                @endphp
                <nav class="msg-pagination" aria-label="Pagination des messages">
                    <p>
                        Affichage de {{ number_format($submissions->firstItem(), 0, ',', ' ') }}
                        à {{ number_format($submissions->lastItem(), 0, ',', ' ') }}
                        sur {{ number_format($submissions->total(), 0, ',', ' ') }} message(s)
                    </p>

                    <div class="msg-pagination-links">
                        @if($submissions->onFirstPage())
                            <span class="is-disabled">Précédent</span>
                        @else
                            <a href="{{ $submissions->previousPageUrl() }}" rel="prev">Précédent</a>
                        @endif

                        @for($page = $startPage; $page <= $endPage; $page++)
                            @if($page === $submissions->currentPage())
                                <span class="is-current">{{ $page }}</span>
                            @else
                                <a href="{{ $submissions->url($page) }}">{{ $page }}</a>
                            @endif
                        @endfor

                        @if($submissions->hasMorePages())
                            <a href="{{ $submissions->nextPageUrl() }}" rel="next">Suivant</a>
                        @else
                            <span class="is-disabled">Suivant</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </section>
</div>
@endsection
