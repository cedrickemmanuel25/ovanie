@extends('admin.layouts.app')

@section('title', 'Validation des boutiques | Administration OVANIE')
@section('page-title', 'Validation des boutiques')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_shops.css') }}">
@endpush

@section('content')
@php
    $statusLabels = [
        'pending' => 'En attente',
        'approved' => 'Approuvée',
        'rejected' => 'Rejetée',
    ];
    $sellerTypeLabels = [
        'particulier' => 'Particulier',
        'entreprise' => 'Entreprise',
        'artisan' => 'Artisan',
        'grossiste' => 'Grossiste / distributeur',
    ];
    $kycLabels = [
        'pending' => 'KYC à contrôler',
        'verified' => 'KYC vérifié',
        'rejected' => 'KYC rejeté',
    ];
    $logisticsLabels = [
        'ready' => 'Logistique prête',
        'incomplete' => 'Logistique incomplète',
        'suspended' => 'Logistique suspendue',
    ];
@endphp

<div class="shops-admin-page">
    <header class="shops-page-heading">
        <div>
            <span class="shops-kicker">Vendeurs et conformité</span>
            <h2>Validation des boutiques vendeurs</h2>
            <p>Contrôlez l’identité, la localisation, la logistique et l’attribution commerciale avant d’autoriser la vente.</p>
        </div>
        <a class="shops-secondary-button" href="{{ route('admin.dashboard') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></svg>
            Retour au tableau de bord
        </a>
    </header>

    @if(session('success'))
        <div class="shops-alert shops-alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shops-alert shops-alert-danger">{{ session('error') }}</div>
    @endif

    <section class="shops-stat-grid" aria-label="Indicateurs des boutiques">
        <a href="{{ route('admin.shops.index') }}" class="shops-stat-card">
            <span class="shops-stat-icon is-blue"><svg viewBox="0 0 24 24"><path d="M3 10h18"/><path d="M5 10v9h14v-9"/><path d="m4 10 2-5h12l2 5"/><path d="M9 19v-5h6v5"/></svg></span>
            <span><small>Total des boutiques</small><strong>{{ number_format($stats['all'] ?? 0, 0, ',', ' ') }}</strong><em>Tous les dossiers enregistrés</em></span>
        </a>
        <a href="{{ route('admin.shops.index', ['status' => 'pending']) }}" class="shops-stat-card">
            <span class="shops-stat-icon is-orange"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <span><small>En attente de décision</small><strong>{{ number_format($stats['pending'] ?? 0, 0, ',', ' ') }}</strong><em>Dossiers à examiner</em></span>
        </a>
        <a href="{{ route('admin.shops.index', ['status' => 'approved']) }}" class="shops-stat-card">
            <span class="shops-stat-icon is-green"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
            <span><small>Boutiques approuvées</small><strong>{{ number_format($stats['approved'] ?? 0, 0, ',', ' ') }}</strong><em>Validation commerciale accordée</em></span>
        </a>
        <a href="{{ route('admin.shops.index', ['readiness' => 'gps_missing']) }}" class="shops-stat-card">
            <span class="shops-stat-icon is-red"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/><path d="m4 4 16 16"/></svg></span>
            <span><small>Position GPS manquante</small><strong>{{ number_format($stats['gps_missing'] ?? 0, 0, ',', ' ') }}</strong><em>Collecte impossible à localiser</em></span>
        </a>
        <a href="{{ route('admin.shops.index', ['readiness' => 'logistics_incomplete']) }}" class="shops-stat-card">
            <span class="shops-stat-icon is-purple"><svg viewBox="0 0 24 24"><path d="M3 6h13v10H3z"/><path d="M16 10h3l2 3v3h-5z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
            <span><small>Logistique incomplète</small><strong>{{ number_format($stats['logistics_incomplete'] ?? 0, 0, ',', ' ') }}</strong><em>Publication encore bloquée</em></span>
        </a>
    </section>

    <form method="GET" action="{{ route('admin.shops.index') }}" class="shops-filter-panel">
        <div class="shops-filter-main">
            <div class="shops-search-field">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Boutique, vendeur, téléphone, RCCM ou commune…">
            </div>
            <input type="text" name="city" value="{{ request('city') }}" placeholder="Ville ou commune">
        </div>

        <div class="shops-filter-options">
            <select name="type">
                <option value="">Tous les profils vendeurs</option>
                @foreach($sellerTypeLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="readiness">
                <option value="">Tous les niveaux de préparation</option>
                <option value="kyc_pending" @selected(request('readiness') === 'kyc_pending')>KYC à contrôler</option>
                <option value="gps_missing" @selected(request('readiness') === 'gps_missing')>GPS manquant</option>
                <option value="logistics_incomplete" @selected(request('readiness') === 'logistics_incomplete')>Logistique incomplète</option>
                <option value="publishable" @selected(request('readiness') === 'publishable')>Prête à publier</option>
            </select>
            <select name="sort">
                <option value="recent" @selected(request('sort', 'recent') === 'recent')>Plus récentes</option>
                <option value="pending_first" @selected(request('sort') === 'pending_first')>En attente en premier</option>
                <option value="old" @selected(request('sort') === 'old')>Plus anciennes</option>
                <option value="az" @selected(request('sort') === 'az')>Nom de A à Z</option>
                <option value="za" @selected(request('sort') === 'za')>Nom de Z à A</option>
            </select>
        </div>

        <div class="shops-filter-actions">
            <button type="submit" class="shops-primary-button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                Rechercher
            </button>
            <a class="shops-reset-button" href="{{ route('admin.shops.index') }}">Réinitialiser</a>
        </div>
    </form>

    <section class="shops-results-header">
        <div>
            <h3>Boutiques trouvées</h3>
            <p>{{ number_format($shops->total(), 0, ',', ' ') }} dossier(s) correspondant aux critères.</p>
        </div>
    </section>

    <div class="shops-card-list">
        @forelse($shops as $shop)
            @php
                $status = $shop->status ?: 'pending';
                $kycStatus = $shop->kyc_status ?: 'pending';
                $logisticsStatus = $shop->logistics_status ?: 'incomplete';
                $hasGps = $shop->latitude !== null && $shop->longitude !== null;
                $canPublish = $shop->canPublishProducts();
                $issues = [];
                if ($kycStatus !== 'verified') $issues[] = 'KYC';
                if (!$hasGps && $shop->usesOvanieLogistics()) $issues[] = 'position GPS';
                if ($logisticsStatus !== 'ready') $issues[] = 'logistique';
                if (!$shop->is_active) $issues[] = 'activation';
            @endphp
            <article class="shop-review-card">
                <header class="shop-review-header">
                    <div class="shop-review-identity">
                        <span class="shop-avatar">{{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}</span>
                        <div>
                            <div class="shop-title-line">
                                <h3>{{ $shop->name ?: 'Boutique sans nom' }}</h3>
                                <span class="shops-status is-{{ $status }}">{{ $statusLabels[$status] ?? 'En attente' }}</span>
                            </div>
                            <p>{{ $shop->user?->name ?: 'Responsable non renseigné' }} · {{ $sellerTypeLabels[$shop->seller_type] ?? 'Profil non renseigné' }}</p>
                            <span>{{ $shop->user?->email ?: 'Adresse e-mail non renseignée' }}{{ $shop->user?->phone ? ' · ' . $shop->user->phone : '' }}</span>
                        </div>
                    </div>
                    <div class="shop-review-id">
                        <small>Dossier</small>
                        <strong>#{{ $shop->id }}</strong>
                        <span>Créé le {{ optional($shop->created_at)->format('d/m/Y') }}</span>
                    </div>
                </header>

                <div class="shop-review-grid">
                    <section>
                        <span class="shop-section-label">Localisation</span>
                        <strong>{{ collect([$shop->commune, $shop->district])->filter()->join(', ') ?: 'Commune et quartier non renseignés' }}</strong>
                        <p>{{ collect([$shop->city, $shop->region])->filter()->join(' · ') ?: 'Ville non renseignée' }}</p>
                        <span class="mini-status {{ $hasGps ? 'is-success' : 'is-danger' }}">
                            {{ $hasGps ? $shop->geo_status_label : 'Position GPS manquante' }}
                        </span>
                    </section>

                    <section>
                        <span class="shop-section-label">Vérifications</span>
                        <div class="shop-check-list">
                            <span class="mini-status {{ $kycStatus === 'verified' ? 'is-success' : ($kycStatus === 'rejected' ? 'is-danger' : 'is-warning') }}">
                                {{ $kycLabels[$kycStatus] ?? 'KYC à contrôler' }}
                            </span>
                            <span class="mini-status {{ $canPublish ? 'is-success' : 'is-warning' }}">
                                {{ $canPublish ? 'Publication autorisée' : 'Publication bloquée' }}
                            </span>
                        </div>
                        @if($issues)
                            <p>À compléter : {{ implode(', ', $issues) }}.</p>
                        @else
                            <p>Le dossier ne présente aucun blocage majeur.</p>
                        @endif
                    </section>

                    <section>
                        <span class="shop-section-label">Logistique</span>
                        <strong>{{ $shop->logistics_mode_label }}</strong>
                        <p>{{ $logisticsLabels[$logisticsStatus] ?? 'État logistique non renseigné' }}</p>
                        <span class="mini-status {{ $logisticsStatus === 'ready' ? 'is-success' : ($logisticsStatus === 'suspended' ? 'is-danger' : 'is-warning') }}">
                            {{ $logisticsLabels[$logisticsStatus] ?? 'Logistique incomplète' }}
                        </span>
                    </section>

                    <section>
                        <span class="shop-section-label">Activité et attribution</span>
                        <strong>{{ number_format($shop->products_count ?? 0, 0, ',', ' ') }} produit(s)</strong>
                        <p>Créée par : {{ $shop->commercialCreator?->name ?: 'Création directe' }}</p>
                        <form method="POST" action="{{ route('admin.shops.updateCommercialManager', $shop) }}" class="manager-form">
                            @csrf
                            <label for="manager-{{ $shop->id }}">Commercial responsable</label>
                            <select id="manager-{{ $shop->id }}" name="managed_by_commercial_id" onchange="this.form.submit()">
                                <option value="">Non attribué</option>
                                @foreach($commercials as $commercial)
                                    <option value="{{ $commercial->id }}" @selected((int) $shop->managed_by_commercial_id === (int) $commercial->id)>
                                        {{ $commercial->name ?: $commercial->email }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </section>
                </div>

                <footer class="shop-review-actions">
                    <a class="shops-primary-button" href="{{ route('admin.shops.show', $shop) }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        Examiner le dossier
                    </a>
                    @if($status !== 'approved')
                        <form method="POST" action="{{ route('admin.shops.updateStatus', $shop) }}" onsubmit="return confirm('Approuver et activer cette boutique ?')">
                            @csrf
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="shops-approve-button">Approuver</button>
                        </form>
                    @endif
                    @if($status === 'approved')
                        <form method="POST" action="{{ route('admin.shops.updateStatus', $shop) }}" onsubmit="return confirm('Remettre cette boutique en attente ?')">
                            @csrf
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="shops-warning-button">Suspendre la validation</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.shops.destroy', $shop) }}" class="shop-delete-form" onsubmit="return confirm('Supprimer définitivement cette boutique ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="shops-danger-link">Supprimer</button>
                    </form>
                </footer>
            </article>
        @empty
            <div class="shops-empty-state">
                <span><svg viewBox="0 0 24 24"><path d="M3 10h18"/><path d="M5 10v9h14v-9"/><path d="m4 10 2-5h12l2 5"/></svg></span>
                <h3>Aucune boutique trouvée</h3>
                <p>Modifiez les filtres ou réinitialisez la recherche pour afficher les dossiers disponibles.</p>
            </div>
        @endforelse
    </div>

    @if($shops->hasPages())
        <nav class="shops-pagination" aria-label="Pagination des boutiques">
            @if($shops->onFirstPage())
                <span class="is-disabled">Précédent</span>
            @else
                <a href="{{ $shops->previousPageUrl() }}">Précédent</a>
            @endif

            @foreach($shops->getUrlRange(max(1, $shops->currentPage() - 2), min($shops->lastPage(), $shops->currentPage() + 2)) as $page => $url)
                @if($page === $shops->currentPage())
                    <span class="is-current">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($shops->hasMorePages())
                <a href="{{ $shops->nextPageUrl() }}">Suivant</a>
            @else
                <span class="is-disabled">Suivant</span>
            @endif
        </nav>
    @endif
</div>
@endsection
