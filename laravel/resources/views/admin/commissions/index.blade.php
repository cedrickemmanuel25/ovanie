@extends('admin.layouts.app')

@section('title', 'Commissions | Administration OVANIE')
@section('page-title', 'Commissions')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_commissions.css') }}">
@endpush

@section('content')
@php
    $statusLabels = [
        'pending' => 'En attente',
        'paid' => 'Encaissée',
        'cancelled' => 'Annulée',
    ];

    $ratePercent = number_format($commissionRate * 100, 0, ',', ' ');
    $hasFilters = request()->filled('q')
        || request()->filled('shop_id')
        || request()->filled('status')
        || request()->filled('date_from')
        || request()->filled('date_to')
        || request()->filled('sort');
@endphp

<div class="comm-page">
    <header class="comm-hero">
        <div class="comm-hero-copy">
            <span class="comm-kicker">FINANCE MARKETPLACE</span>
            <h2>Suivi des commissions OVANIE</h2>
            <p>
                Consultez les commissions générées par les commandes, contrôlez leur état
                et retrouvez l’origine de chaque montant par client et par boutique.
            </p>
            <div class="comm-hero-badges">
                <span>Taux standard : {{ $ratePercent }} %</span>
                <span>{{ number_format($summary['records_count'], 0, ',', ' ') }} opération(s)</span>
            </div>
        </div>

        <div class="comm-hero-actions">
            @if(Route::has('admin.payouts.index'))
                <a href="{{ route('admin.payouts.index') }}" class="comm-btn comm-btn-secondary">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h10" />
                    </svg>
                    Voir les reversements
                </a>
            @endif

            @if(Route::has('admin.payouts.export'))
                <a href="{{ route('admin.payouts.export') }}" class="comm-btn comm-btn-primary">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                    </svg>
                    Exporter pour PayDunya
                </a>
            @endif
        </div>
    </header>

    <section class="comm-stats" aria-label="Résumé des commissions">
        <article class="comm-stat-card comm-stat-total">
            <div class="comm-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19V9m6 10V5m6 14v-7m4 7H2" />
                </svg>
            </div>
            <div>
                <span>Commissions générées</span>
                <strong>{{ number_format($summary['total_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Montant correspondant aux filtres</small>
            </div>
        </article>

        <article class="comm-stat-card comm-stat-pending">
            <div class="comm-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            </div>
            <div>
                <span>À encaisser</span>
                <strong>{{ number_format($summary['pending_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Commissions encore en attente</small>
            </div>
        </article>

        <article class="comm-stat-card comm-stat-paid">
            <div class="comm-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8 12 2.5 2.5L16 9" />
                </svg>
            </div>
            <div>
                <span>Déjà encaissées</span>
                <strong>{{ number_format($summary['paid_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Commissions confirmées</small>
            </div>
        </article>

        <article class="comm-stat-card comm-stat-shops">
            <div class="comm-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 10h16M5 10v9h14v-9M3 10l2-6h14l2 6M9 19v-5h6v5" />
                </svg>
            </div>
            <div>
                <span>Boutiques concernées</span>
                <strong>{{ number_format($summary['shops_count'], 0, ',', ' ') }}</strong>
                <small>Ayant généré une commission</small>
            </div>
        </article>
    </section>

    <section class="comm-filter-card">
        <div class="comm-section-heading">
            <div>
                <span class="comm-section-kicker">RECHERCHE ET FILTRES</span>
                <h3>Affiner l’historique</h3>
            </div>
            @if($hasFilters)
                <a href="{{ route('admin.commissions.index') }}" class="comm-reset-link">Réinitialiser les filtres</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.commissions.index') }}" class="comm-filter-form">
            <div class="comm-field comm-field-search">
                <label for="q">Commande, client ou boutique</label>
                <div class="comm-input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-4-4" />
                    </svg>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Ex. OVANIE-123, Awa Koné..."
                    >
                </div>
            </div>

            <div class="comm-field">
                <label for="shop_id">Boutique</label>
                <select id="shop_id" name="shop_id">
                    <option value="">Toutes les boutiques</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>
                            {{ $shop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="comm-field">
                <label for="status">État</label>
                <select id="status" name="status">
                    <option value="">Tous les états</option>
                    <option value="pending" @selected(request('status') === 'pending')>En attente</option>
                    <option value="paid" @selected(request('status') === 'paid')>Encaissée</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Annulée</option>
                </select>
            </div>

            <div class="comm-field">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
            </div>

            <div class="comm-field">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
            </div>

            <div class="comm-field">
                <label for="sort">Classement</label>
                <select id="sort" name="sort">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Plus récentes</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciennes</option>
                    <option value="amount_desc" @selected(request('sort') === 'amount_desc')>Commission la plus élevée</option>
                    <option value="amount_asc" @selected(request('sort') === 'amount_asc')>Commission la plus faible</option>
                </select>
            </div>

            <button type="submit" class="comm-search-button">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-4-4" />
                </svg>
                Rechercher
            </button>
        </form>
    </section>

    <section class="comm-history-card">
        <div class="comm-section-heading comm-history-heading">
            <div>
                <span class="comm-section-kicker">HISTORIQUE FINANCIER</span>
                <h3>Détail des commissions</h3>
                <p>{{ number_format($commissions->total(), 0, ',', ' ') }} résultat(s) trouvé(s)</p>
            </div>
        </div>

        @if($commissions->isEmpty())
            <div class="comm-empty-state">
                <div class="comm-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z" />
                        <path d="M8 8h8M8 12h6" />
                    </svg>
                </div>
                <h4>{{ $hasFilters ? 'Aucune commission ne correspond aux filtres.' : 'Aucune commission enregistrée.' }}</h4>
                <p>
                    {{ $hasFilters
                        ? 'Modifiez les critères de recherche ou réinitialisez les filtres pour afficher d’autres opérations.'
                        : 'Les commissions apparaîtront ici automatiquement lorsque des commandes généreront une commission OVANIE.' }}
                </p>
                @if($hasFilters)
                    <a href="{{ route('admin.commissions.index') }}" class="comm-btn comm-btn-secondary">Afficher tout l’historique</a>
                @endif
            </div>
        @else
            <div class="comm-table-wrap">
                <table class="comm-table">
                    <thead>
                        <tr>
                            <th>Opération</th>
                            <th>Client et commande</th>
                            <th>Boutique</th>
                            <th>Montant commande</th>
                            <th>Commission</th>
                            <th>État</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commissions as $commission)
                            @php
                                $order = $commission->order;
                                $client = $order?->client;
                                $clientName = trim((string) ($client?->name ?? ''));
                                if ($clientName === '') {
                                    $clientName = trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? ''));
                                }
                                $clientName = $clientName !== '' ? $clientName : 'Client non renseigné';
                                $orderNumber = $order?->order_number ?: ($order?->invoice_number ?: 'Commande non disponible');
                                $orderAmount = (float) ($order?->total_amount ?? $order?->subtotal ?? 0);
                                $status = $commission->status ?: 'pending';
                                $statusLabel = $statusLabels[$status] ?? 'À contrôler';
                            @endphp
                            <tr>
                                <td data-label="Opération">
                                    <div class="comm-operation-id">#{{ $commission->id }}</div>
                                    <span class="comm-muted">Commission OVANIE</span>
                                </td>
                                <td data-label="Client et commande">
                                    <strong>{{ $clientName }}</strong>
                                    @if($order && Route::has('admin.orders.show'))
                                        <a href="{{ route('admin.orders.show', $order) }}" class="comm-order-link">
                                            {{ $orderNumber }}
                                        </a>
                                    @else
                                        <span class="comm-muted">{{ $orderNumber }}</span>
                                    @endif
                                </td>
                                <td data-label="Boutique">
                                    <strong>{{ $commission->shop?->name ?? 'Boutique non renseignée' }}</strong>
                                    <span class="comm-muted">
                                        {{ $commission->shop?->user?->email ?? 'Vendeur non renseigné' }}
                                    </span>
                                </td>
                                <td data-label="Montant commande">
                                    <strong>{{ number_format($orderAmount, 0, ',', ' ') }} FCFA</strong>
                                    <span class="comm-muted">Montant total</span>
                                </td>
                                <td data-label="Commission">
                                    <strong class="comm-amount">{{ number_format((float) $commission->amount, 0, ',', ' ') }} FCFA</strong>
                                    <span class="comm-muted">Taux indicatif {{ $ratePercent }} %</span>
                                </td>
                                <td data-label="État">
                                    <span class="comm-status comm-status-{{ $status }}">{{ $statusLabel }}</span>
                                    @if($commission->paid_at)
                                        <span class="comm-muted">le {{ $commission->paid_at->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td data-label="Date">
                                    <strong>{{ $commission->created_at?->format('d/m/Y') ?? 'Non renseignée' }}</strong>
                                    <span class="comm-muted">{{ $commission->created_at?->format('H:i') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($commissions->hasPages())
                <div class="comm-pagination">
                    <div class="comm-pagination-summary">
                        Affichage de {{ number_format($commissions->firstItem(), 0, ',', ' ') }}
                        à {{ number_format($commissions->lastItem(), 0, ',', ' ') }}
                        sur {{ number_format($commissions->total(), 0, ',', ' ') }} résultat(s)
                    </div>
                    <div class="comm-pagination-links">
                        {{ $commissions->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        @endif
    </section>
</div>
@endsection
