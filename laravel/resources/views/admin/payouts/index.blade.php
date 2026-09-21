@extends('admin.layouts.app')

@section('title', 'Reversements vendeurs | Administration OVANIE')
@section('page-title', 'Reversements vendeurs')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_payouts.css') }}">
@endpush

@section('content')
@php
    $hasFilters = request()->filled('q')
        || request()->filled('shop_id')
        || request()->filled('status')
        || request()->filled('date_from')
        || request()->filled('date_to')
        || request()->filled('sort');

    $statusOptions = [
        'blocked' => 'Bloqué',
        'waiting_payment' => 'En attente du paiement client',
        'waiting_reception' => 'En attente de la réception client',
        'pending' => 'Programmé',
        'approved' => 'Prêt à payer',
        'processing' => 'En traitement',
        'paid' => 'Payé',
        'failed' => 'Échec',
        'cancelled' => 'Annulé',
    ];
@endphp

<div class="pay-page">
    <header class="pay-hero">
        <div class="pay-hero-copy">
            <span class="pay-kicker">FINANCE VENDEURS</span>
            <h2>Pilotez les reversements aux boutiques</h2>
            <p>
                Contrôlez les montants dus, approuvez les paiements arrivés à échéance,
                suivez leur traitement et conservez une référence pour chaque transfert.
            </p>
            <div class="pay-hero-badges">
                <span>{{ number_format($summary['records_count'], 0, ',', ' ') }} reversement(s)</span>
                <span>{{ number_format($summary['blocked_count'], 0, ',', ' ') }} dossier(s) bloqué(s)</span>
            </div>
        </div>

        <div class="pay-hero-actions">
            @if(Route::has('admin.commissions.index'))
                <a href="{{ route('admin.commissions.index') }}" class="pay-btn pay-btn-secondary">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2" /></svg>
                    Voir les commissions
                </a>
            @endif

            @if(Route::has('admin.payouts.export'))
                <a href="{{ route('admin.payouts.export') }}" class="pay-btn pay-btn-primary">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
                    Exporter les paiements
                </a>
            @endif
        </div>
    </header>

    <div class="pay-environment {{ $isLive ? 'is-live' : 'is-test' }}">
        <div class="pay-environment-icon">{{ $isLive ? '✓' : 'T' }}</div>
        <div>
            <strong>{{ $isLive ? 'Environnement de production' : 'Environnement de test' }}</strong>
            <p>
                @if(!$isLive)
                    Les confirmations de paiement sont simulées. Aucun argent réel n’est transféré depuis cet environnement.
                @elseif($executionMode === 'paydunya')
                    Les reversements arrivés à échéance sont envoyés automatiquement à PayDunya. OVANIE ne marque payé qu’après confirmation du fournisseur.
                @else
                    Chaque paiement doit correspondre à un transfert réel et disposer d’une référence vérifiable.
                @endif
            </p>
        </div>
        <span class="pay-mode-label">
            {{ match($executionMode) {
                'simulation' => 'Simulation',
                'paydunya' => 'PayDunya automatique',
                default => 'Traitement manuel',
            } }}
        </span>
    </div>

    @if(session('success'))
        <div class="pay-alert pay-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="pay-alert pay-alert-error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="pay-alert pay-alert-error">
            <strong>Une action n’a pas pu être enregistrée.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="pay-stats" aria-label="Résumé des reversements">
        <article class="pay-stat-card pay-stat-total">
            <div class="pay-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 7V5h8v2M8 13h8" /></svg>
            </div>
            <div>
                <span>Total net vendeur</span>
                <strong>{{ number_format($summary['total_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Montant correspondant aux filtres</small>
            </div>
        </article>

        <article class="pay-stat-card pay-stat-ready">
            <div class="pay-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></svg>
            </div>
            <div>
                <span>Prêts à payer</span>
                <strong>{{ number_format($summary['ready_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>{{ number_format($summary['ready_count'], 0, ',', ' ') }} reversement(s) approuvé(s)</small>
            </div>
        </article>

        <article class="pay-stat-card pay-stat-processing">
            <div class="pay-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v4m0 10v4M3 12h4m10 0h4M5.6 5.6l2.8 2.8m7.2 7.2 2.8 2.8m0-12.8-2.8 2.8m-7.2 7.2-2.8 2.8" /></svg>
            </div>
            <div>
                <span>En traitement</span>
                <strong>{{ number_format($summary['processing_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Transferts lancés mais non confirmés</small>
            </div>
        </article>

        <article class="pay-stat-card pay-stat-paid">
            <div class="pay-stat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 12h8M8 16h5" /><path d="m15 5 2 2 4-4" /></svg>
            </div>
            <div>
                <span>Déjà payés</span>
                <strong>{{ number_format($summary['paid_amount'], 0, ',', ' ') }} FCFA</strong>
                <small>Reversements confirmés</small>
            </div>
        </article>
    </section>

    <section class="pay-filter-card">
        <div class="pay-section-heading">
            <div>
                <span class="pay-section-kicker">RECHERCHE ET FILTRES</span>
                <h3>Retrouver un reversement</h3>
            </div>
            @if($hasFilters)
                <a href="{{ route('admin.payouts.index') }}" class="pay-reset-link">Réinitialiser</a>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.payouts.index') }}" class="pay-filter-form">
            <div class="pay-field pay-field-search">
                <label for="q">Commande, boutique, vendeur ou référence</label>
                <div class="pay-input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Ex. OVANIE-123, Boutique Awa...">
                </div>
            </div>

            <div class="pay-field">
                <label for="shop_id">Boutique</label>
                <select id="shop_id" name="shop_id">
                    <option value="">Toutes les boutiques</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pay-field">
                <label for="status">État</label>
                <select id="status" name="status">
                    <option value="">Tous les états</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pay-field">
                <label for="date_from">Du</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
            </div>

            <div class="pay-field">
                <label for="date_to">Au</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
            </div>

            <div class="pay-field">
                <label for="sort">Classement</label>
                <select id="sort" name="sort">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Plus récents</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                    <option value="due_first" @selected(request('sort') === 'due_first')>Échéance la plus proche</option>
                    <option value="amount_desc" @selected(request('sort') === 'amount_desc')>Montant le plus élevé</option>
                    <option value="amount_asc" @selected(request('sort') === 'amount_asc')>Montant le plus faible</option>
                </select>
            </div>

            <button type="submit" class="pay-search-button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                Rechercher
            </button>
        </form>
    </section>

    <section class="pay-list-card">
        <div class="pay-section-heading pay-list-heading">
            <div>
                <span class="pay-section-kicker">VERSEMENTS VENDEURS</span>
                <h3>Historique et traitement</h3>
                <p>{{ number_format($payouts->total(), 0, ',', ' ') }} résultat(s) trouvé(s)</p>
            </div>
        </div>

        @if($payouts->isEmpty())
            <div class="pay-empty-state">
                <div class="pay-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4zM8 7V5h8v2M8 13h8" /></svg>
                </div>
                <h4>{{ $hasFilters ? 'Aucun reversement ne correspond aux filtres.' : 'Aucun reversement vendeur enregistré.' }}</h4>
                <p>
                    {{ $hasFilters
                        ? 'Modifiez les critères ou réinitialisez les filtres pour afficher d’autres opérations.'
                        : 'Les reversements apparaîtront automatiquement après l’éligibilité des commandes vendeurs.' }}
                </p>
            </div>
        @else
            <div class="pay-payout-list">
                @foreach($payouts as $payout)
                    @php
                        $vendorName = trim(($payout->vendor?->first_name ?? '') . ' ' . ($payout->vendor?->last_name ?? ''));
                        $vendorName = $vendorName !== '' ? $vendorName : ($payout->vendor?->name ?? 'Vendeur non renseigné');
                        $orderReference = $payout->order?->order_number ?? ('Commande #' . $payout->order_id);
                        $scheduleDate = $payout->scheduled_for ?? $payout->eligible_at;
                        $isFuture = $payout->scheduled_for && $payout->scheduled_for->isFuture();
                        $canApprove = $payout->status === 'pending' && ! $isFuture;
                        $canProcess = $payout->status === 'approved';
                        $canPay = in_array($payout->status, ['approved', 'processing'], true);
                        $canFail = in_array($payout->status, ['pending', 'approved', 'processing'], true);
                    @endphp

                    <article class="pay-payout-card">
                        <div class="pay-payout-main">
                            <div class="pay-payout-identity">
                                <div class="pay-shop-avatar">{{ strtoupper(mb_substr($payout->shop?->name ?? 'V', 0, 1)) }}</div>
                                <div>
                                    <div class="pay-payout-title-line">
                                        <h4>{{ $payout->shop?->name ?? 'Boutique non renseignée' }}</h4>
                                        <span class="pay-status pay-status-{{ $payout->status_tone }}">{{ $payout->status_label }}</span>
                                    </div>
                                    <p>{{ $vendorName }}</p>
                                    <div class="pay-meta-chips">
                                        <span>{{ $orderReference }}</span>
                                        <span>{{ $payout->payment_method_label }}</span>
                                        @if($payout->phone)<span>{{ $payout->phone }}</span>@endif
                                    </div>
                                </div>
                            </div>

                            <div class="pay-financial-grid">
                                <div>
                                    <span>Montant brut</span>
                                    <strong>{{ number_format((float) $payout->total_amount, 0, ',', ' ') }} FCFA</strong>
                                </div>
                                <div>
                                    <span>Commission OVANIE</span>
                                    <strong>{{ number_format((float) $payout->commission_amount, 0, ',', ' ') }} FCFA</strong>
                                </div>
                                <div class="pay-net-amount">
                                    <span>Net à verser</span>
                                    <strong>{{ number_format((float) $payout->payout_amount, 0, ',', ' ') }} FCFA</strong>
                                </div>
                            </div>

                            <div class="pay-payout-details">
                                <div>
                                    <span>Échéance</span>
                                    <strong>{{ $scheduleDate ? $scheduleDate->locale('fr')->translatedFormat('d F Y à H:i') : 'Non programmée' }}</strong>
                                </div>
                                <div>
                                    <span>Référence du versement</span>
                                    <strong>{{ $payout->payout_reference ?: 'À renseigner' }}</strong>
                                    @if($payout->batch_reference)<small>Lot : {{ $payout->batch_reference }}</small>@endif
                                </div>
                                <div>
                                    <span>Créé le</span>
                                    <strong>{{ $payout->created_at?->locale('fr')->translatedFormat('d F Y à H:i') }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="pay-payout-footer">
                            <div class="pay-state-note">
                                @if($payout->status === 'blocked')
                                    Le reversement reste bloqué tant que les conditions de paiement ne sont pas réunies.
                                @elseif($payout->status === 'waiting_payment')
                                    En attente de la confirmation du paiement client.
                                @elseif($payout->status === 'waiting_reception')
                                    En attente de la réception ou de la validation de la commande.
                                @elseif($isFuture)
                                    Approbation disponible à partir du {{ $payout->scheduled_for->locale('fr')->translatedFormat('d F Y à H:i') }}.
                                @elseif($payout->status === 'paid')
                                    Paiement confirmé{{ $payout->paid_at ? ' le ' . $payout->paid_at->locale('fr')->translatedFormat('d F Y à H:i') : '' }}.
                                @elseif($payout->status === 'failed')
                                    Échec enregistré{{ $payout->admin_note ? ' : ' . $payout->admin_note : '.' }}
                                @else
                                    Utilisez les actions disponibles pour faire avancer ce reversement.
                                @endif
                            </div>

                            @if($canApprove || $canProcess || $canPay || $canFail)
                                <details class="pay-actions-panel">
                                    <summary>
                                        Gérer le reversement
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4" /></svg>
                                    </summary>

                                    <div class="pay-actions-content">
                                        @if($canApprove)
                                            <section class="pay-action-box is-approve">
                                                <div>
                                                    <strong>1. Approuver le reversement</strong>
                                                    <p>Confirmez que le montant est arrivé à échéance et peut être traité.</p>
                                                </div>
                                                <form action="{{ route('admin.payouts.approve', $payout) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="pay-action-button pay-action-approve">Approuver</button>
                                                </form>
                                            </section>
                                        @endif

                                        @if($canProcess)
                                            <section class="pay-action-box is-processing">
                                                <div>
                                                    <strong>2. Passer en traitement</strong>
                                                    <p>Regroupez le versement dans un lot de paiement si nécessaire.</p>
                                                </div>
                                                <form action="{{ route('admin.payouts.processing', $payout) }}" method="POST" class="pay-action-form">
                                                    @csrf
                                                    <label>
                                                        Référence du lot
                                                        <input type="text" name="batch_reference" value="{{ $payout->batch_reference }}" placeholder="Ex. LOT-2026-08-001">
                                                    </label>
                                                    <button type="submit" class="pay-action-button pay-action-processing">Démarrer le traitement</button>
                                                </form>
                                            </section>
                                        @endif

                                        @if($canPay)
                                            <section class="pay-action-box is-paid">
                                                @if($isLive && $executionMode === 'paydunya')
                                                    <div>
                                                        <strong>3. Paiement PayDunya automatique</strong>
                                                        <p>Le serveur OVANIE transmet ce reversement à PayDunya et vérifie ensuite son statut. Aucune confirmation manuelle « payé » n’est autorisée.</p>
                                                    </div>
                                                @else
                                                    <div>
                                                        <strong>{{ $isLive ? '3. Confirmer le paiement réel' : '3. Simuler le paiement' }}</strong>
                                                        <p>{{ $isLive ? 'Saisissez la référence fournie par le service de paiement.' : 'La confirmation reste une simulation dans cet environnement.' }}</p>
                                                    </div>
                                                    <form action="{{ route('admin.payouts.markPaid', $payout) }}" method="POST" class="pay-action-form">
                                                        @csrf
                                                        <label>
                                                            Référence du transfert {{ $isLive ? '*' : '(facultative en test)' }}
                                                            <input type="text" name="payout_reference" value="{{ $payout->payout_reference }}" placeholder="Ex. TRANSFERT-OVANIE-001" @required($isLive)>
                                                        </label>
                                                        <label>
                                                            Note administrative
                                                            <textarea name="admin_note" rows="2" placeholder="Information facultative sur le transfert">{{ $payout->admin_note }}</textarea>
                                                        </label>
                                                        <button type="submit" class="pay-action-button pay-action-paid">{{ $isLive ? 'Confirmer comme payé' : 'Confirmer la simulation' }}</button>
                                                    </form>
                                                @endif
                                            </section>
                                        @endif

                                        @if($canFail)
                                            <section class="pay-action-box is-failed">
                                                <div>
                                                    <strong>Signaler un échec</strong>
                                                    <p>Utilisez cette action uniquement lorsqu’un transfert a réellement échoué.</p>
                                                </div>
                                                <form action="{{ route('admin.payouts.failed', $payout) }}" method="POST" class="pay-action-form" onsubmit="return confirm('Confirmer l’échec de ce reversement ?');">
                                                    @csrf
                                                    <label>
                                                        Motif de l’échec *
                                                        <textarea name="admin_note" rows="2" required placeholder="Ex. numéro Mobile Money invalide ou transfert refusé"></textarea>
                                                    </label>
                                                    <button type="submit" class="pay-action-button pay-action-failed">Marquer en échec</button>
                                                </form>
                                            </section>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <span class="pay-no-action">Aucune action disponible</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if($payouts->hasPages())
                <nav class="pay-pagination" aria-label="Pagination des reversements">
                    <p>
                        Affichage de {{ number_format($payouts->firstItem(), 0, ',', ' ') }} à
                        {{ number_format($payouts->lastItem(), 0, ',', ' ') }} sur
                        {{ number_format($payouts->total(), 0, ',', ' ') }} résultats
                    </p>
                    <div class="pay-pagination-links">
                        @if($payouts->onFirstPage())
                            <span class="is-disabled">Précédent</span>
                        @else
                            <a href="{{ $payouts->previousPageUrl() }}">Précédent</a>
                        @endif

                        @foreach($payouts->getUrlRange(max(1, $payouts->currentPage() - 2), min($payouts->lastPage(), $payouts->currentPage() + 2)) as $page => $url)
                            @if($page === $payouts->currentPage())
                                <span class="is-current">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($payouts->hasMorePages())
                            <a href="{{ $payouts->nextPageUrl() }}">Suivant</a>
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
