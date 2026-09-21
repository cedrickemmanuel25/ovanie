@extends('admin.layouts.app')

@section('title', 'Gestion des clients | Administration OVANIE')
@section('page-title', 'Gestion des clients')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_clients.css') }}">
@endpush

@section('content')
@php
    $statusLabels = [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'blocked' => 'Bloqué',
        'suspended' => 'Suspendu',
    ];
@endphp

<div class="ac-page">
    <section class="ac-page-head">
        <div>
            <span class="ac-eyebrow">Relation client</span>
            <h2>Gestion des clients</h2>
            <p>Consultez les comptes acheteurs, leur activité et leur origine commerciale.</p>
        </div>
        <a class="ac-btn ac-btn-secondary" href="{{ route('admin.orders.index') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6"/></svg>
            Voir les commandes
        </a>
    </section>

    @if (session('success'))
        <div class="ac-alert ac-alert-success">{{ session('success') }}</div>
    @endif

    <section class="ac-stats" aria-label="Indicateurs clients">
        <article class="ac-stat-card">
            <span class="ac-stat-icon ac-stat-blue">C</span>
            <div><span>Total des clients</span><strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong><small>Comptes acheteurs enregistrés</small></div>
        </article>
        <article class="ac-stat-card">
            <span class="ac-stat-icon ac-stat-green">✓</span>
            <div><span>Comptes actifs</span><strong>{{ number_format($stats['active'], 0, ',', ' ') }}</strong><small>Accès actuellement autorisé</small></div>
        </article>
        <article class="ac-stat-card">
            <span class="ac-stat-icon ac-stat-orange">A</span>
            <div><span>Clients acheteurs</span><strong>{{ number_format($stats['buyers'], 0, ',', ' ') }}</strong><small>Au moins une commande validée</small></div>
        </article>
        <article class="ac-stat-card">
            <span class="ac-stat-icon ac-stat-purple">+</span>
            <div><span>Nouveaux ce mois</span><strong>{{ number_format($stats['new_this_month'], 0, ',', ' ') }}</strong><small>Inscriptions depuis le {{ now()->startOfMonth()->format('d/m/Y') }}</small></div>
        </article>
    </section>

    <section class="ac-panel ac-filter-panel">
        <form method="GET" action="{{ route('admin.clients.index') }}" class="ac-filter-form">
            <label class="ac-search-field">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Nom, e-mail, téléphone ou ville">
            </label>

            <select name="status" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(request('status') === 'active')>Actifs</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
                <option value="blocked" @selected(request('status') === 'blocked')>Bloqués</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspendus</option>
            </select>

            <select name="source" aria-label="Filtrer par origine">
                <option value="">Toutes les origines</option>
                <option value="commercial" @selected(request('source') === 'commercial')>Créés par un commercial</option>
                <option value="direct" @selected(request('source') === 'direct')>Créations directes</option>
            </select>

            <select name="sort" aria-label="Trier les clients">
                <option value="recent" @selected(request('sort', 'recent') === 'recent')>Plus récents</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                <option value="name" @selected(request('sort') === 'name')>Nom A à Z</option>
                <option value="orders" @selected(request('sort') === 'orders')>Plus de commandes</option>
            </select>

            <button class="ac-btn ac-btn-primary" type="submit">Rechercher</button>

            @if (request()->hasAny(['search', 'status', 'source', 'sort']))
                <a class="ac-reset-link" href="{{ route('admin.clients.index') }}">Réinitialiser</a>
            @endif
        </form>
    </section>

    <section class="ac-panel ac-client-list-panel">
        <div class="ac-panel-head">
            <div>
                <h3>Clients trouvés</h3>
                <p>{{ number_format($clients->total(), 0, ',', ' ') }} compte(s) correspondant aux critères.</p>
            </div>
        </div>

        <div class="ac-client-list">
            @forelse ($clients as $client)
                @php
                    $displayName = trim($client->full_name) ?: ($client->name ?: 'Client sans nom');
                    $initials = collect(preg_split('/\s+/', $displayName))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                    $status = $client->status ?: 'inactive';
                @endphp
                <article class="ac-client-row">
                    <div class="ac-client-identity">
                        <span class="ac-avatar">{{ $initials ?: 'C' }}</span>
                        <div class="ac-client-main">
                            <div class="ac-name-line">
                                <a href="{{ route('admin.clients.show', $client->id) }}">{{ $displayName }}</a>
                                <span class="ac-status ac-status-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
                            </div>
                            <span>{{ $client->email }}</span>
                            <small>Client #{{ $client->id }}</small>
                        </div>
                    </div>

                    <div class="ac-client-cell">
                        <span class="ac-cell-label">Contact</span>
                        <strong>{{ $client->phone ?: 'Téléphone non renseigné' }}</strong>
                        <small>{{ $client->city ?: 'Ville non renseignée' }}</small>
                    </div>

                    <div class="ac-client-cell">
                        <span class="ac-cell-label">Activité</span>
                        <strong>{{ number_format((int) $client->orders_count, 0, ',', ' ') }} commande(s)</strong>
                        <small>{{ number_format((float) ($client->total_spent ?? 0), 0, ',', ' ') }} FCFA dépensés</small>
                    </div>

                    <div class="ac-client-cell">
                        <span class="ac-cell-label">Origine</span>
                        <strong>{{ $client->commercialCreator?->name ?: 'Création directe' }}</strong>
                        <small>Inscrit le {{ $client->created_at?->format('d/m/Y') }}</small>
                    </div>

                    <div class="ac-row-actions">
                        <a class="ac-icon-btn" href="{{ route('admin.clients.show', $client->id) }}" title="Consulter la fiche" aria-label="Consulter la fiche de {{ $displayName }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </a>
                        <a class="ac-icon-btn" href="{{ route('admin.clients.edit', $client->id) }}" title="Modifier" aria-label="Modifier {{ $displayName }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16-.8 4 4-.8L18 8.4 15.6 6 4 16Z"/><path d="m14 7 3 3"/></svg>
                        </a>
                    </div>
                </article>
            @empty
                <div class="ac-empty-state">
                    <span class="ac-empty-icon">C</span>
                    <h3>Aucun client trouvé</h3>
                    <p>Modifiez les filtres ou vérifiez le terme recherché.</p>
                    <a class="ac-btn ac-btn-secondary" href="{{ route('admin.clients.index') }}">Afficher tous les clients</a>
                </div>
            @endforelse
        </div>

        @if ($clients->hasPages())
            <footer class="ac-pagination">
                <p>Affichage de {{ $clients->firstItem() }} à {{ $clients->lastItem() }} sur {{ $clients->total() }} clients</p>
                <nav aria-label="Pagination des clients">
                    @if ($clients->onFirstPage())
                        <span class="is-disabled">Précédent</span>
                    @else
                        <a href="{{ $clients->previousPageUrl() }}">Précédent</a>
                    @endif

                    @foreach ($clients->getUrlRange(max(1, $clients->currentPage() - 2), min($clients->lastPage(), $clients->currentPage() + 2)) as $page => $url)
                        @if ($page === $clients->currentPage())
                            <span class="is-current">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($clients->hasMorePages())
                        <a href="{{ $clients->nextPageUrl() }}">Suivant</a>
                    @else
                        <span class="is-disabled">Suivant</span>
                    @endif
                </nav>
            </footer>
        @endif
    </section>
</div>
@endsection
