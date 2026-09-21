@extends('admin.layouts.app')

@section('title', 'Commandes | Admin OVANIE')
@section('page-title', 'Commandes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_orders.css') }}">
@endpush

@section('content')
    @php
        $statusLabels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En traitement',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            'completed' => 'Finalisée',
            'paid' => 'Payée',
        ];

        $paymentLabels = [
            'pending' => 'En attente',
            'paid' => 'Payé',
            'failed' => 'Échoué',
            'commission_paid' => 'Commission payée',
            'cod_completed' => 'PAL finalisé',
            'verified' => 'Vérifié',
            'partial' => 'Partiel',
        ];

        $paymentMethodLabels = [
            'online' => 'Paiement en ligne',
            'paydunya' => 'Paiement en ligne',
            'mobile_money' => 'Mobile Money',
            'cash_on_delivery' => 'Paiement à la livraison',
            'bank_transfer' => 'Virement bancaire',
        ];

        $stats = $stats ?? [
            'total' => $orders->total(),
            'today' => 0,
            'pending' => 0,
            'paid' => 0,
        ];
    @endphp

    <div class="admin-orders-page">
        <header class="orders-page-header">
            <div>
                <span class="orders-eyebrow">Gestion commerciale</span>
                <h1>Commandes clients</h1>
                <p>Consultez les commandes, les paiements et leur répartition entre les boutiques.</p>
            </div>

            <div class="orders-result-chip">
                <span>{{ number_format($orders->total(), 0, ',', ' ') }}</span>
                commande(s)
            </div>
        </header>

        <section class="orders-kpis" aria-label="Indicateurs commandes">
            <article class="orders-kpi">
                <span class="orders-kpi-icon is-blue">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6"/></svg>
                </span>
                <div>
                    <small>Total commandes</small>
                    <strong>{{ number_format($stats['total'] ?? 0, 0, ',', ' ') }}</strong>
                </div>
            </article>

            <article class="orders-kpi">
                <span class="orders-kpi-icon is-orange">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
                <div>
                    <small>En attente</small>
                    <strong>{{ number_format($stats['pending'] ?? 0, 0, ',', ' ') }}</strong>
                </div>
            </article>

            <article class="orders-kpi">
                <span class="orders-kpi-icon is-green">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                </span>
                <div>
                    <small>Paiements confirmés</small>
                    <strong>{{ number_format($stats['paid'] ?? 0, 0, ',', ' ') }}</strong>
                </div>
            </article>

            <article class="orders-kpi">
                <span class="orders-kpi-icon is-navy">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4M16 2v4M3 9h18M5 4h14a2 2 0 0 1 2 2v15H3V6a2 2 0 0 1 2-2Z"/></svg>
                </span>
                <div>
                    <small>Aujourd’hui</small>
                    <strong>{{ number_format($stats['today'] ?? 0, 0, ',', ' ') }}</strong>
                </div>
            </article>
        </section>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="orders-filter-panel">
            <div class="orders-filter-grid">
                <label class="orders-field orders-field-search">
                    <span>Recherche</span>
                    <div class="orders-input-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                        <input type="search" name="q" value="{{ request('q') }}"
                               placeholder="Commande, client, produit ou boutique">
                    </div>
                </label>

                <label class="orders-field">
                    <span>Commande</span>
                    <select name="status">
                        <option value="">Tous les statuts</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="orders-field">
                    <span>Paiement</span>
                    <select name="payment_status">
                        <option value="">Tous les paiements</option>
                        @foreach($paymentLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="orders-field">
                    <span>Boutique</span>
                    <select name="shop_id">
                        <option value="">Toutes les boutiques</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>
                                {{ $shop->name }}{{ $shop->user?->name ? ' — ' . $shop->user->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="orders-field">
                    <span>Date</span>
                    <input type="date" name="date" value="{{ request('date') }}">
                </label>

                <label class="orders-field">
                    <span>Tri</span>
                    <select name="sort">
                        <option value="desc" @selected(request('sort', 'desc') === 'desc')>Plus récentes</option>
                        <option value="asc" @selected(request('sort') === 'asc')>Plus anciennes</option>
                    </select>
                </label>
            </div>

            <div class="orders-filter-actions">
                <button type="submit" class="orders-btn orders-btn-primary">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M7 12h10M10 19h4"/></svg>
                    Appliquer
                </button>
                <a href="{{ route('admin.orders.index') }}" class="orders-btn orders-btn-secondary">Réinitialiser</a>
            </div>
        </form>

        <section class="orders-content-section">
            <div class="orders-section-heading">
                <div>
                    <h2>Liste des commandes</h2>
                    <p>Une vue compacte. Ouvrez la répartition uniquement lorsque vous en avez besoin.</p>
                </div>
                <span>{{ number_format($orders->total(), 0, ',', ' ') }} résultat(s)</span>
            </div>

            <div class="orders-card-list">
                @forelse($orders as $order)
                    @php
                        $shopGroups = $order->items->groupBy(function ($item) {
                            $shop = $item->shop ?: $item->product?->shop;
                            return $shop?->id ? 'shop-' . $shop->id : 'shop-unknown';
                        });

                        if ($shopGroups->isEmpty() && $order->shop) {
                            $shopGroups = collect(['shop-' . $order->shop->id => collect()]);
                        }

                        $clientName = $order->client?->name
                            ?: trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? ''))
                            ?: ($order->customer_name ?? 'Client non identifié');

                        $displayNumber = $order->order_number ?: ('#' . $order->id);
                        $itemsCount = (int) ($order->items_count ?? $order->items->count());
                        $referencesCount = $order->items->pluck('product_id')->filter()->unique()->count();

                        $ovanieCount = 0;
                        $sellerLogisticsCount = 0;

                        foreach ($shopGroups as $groupItems) {
                            $firstItem = $groupItems->first();
                            $shop = $firstItem?->shop ?: $firstItem?->product?->shop ?: $order->shop;
                            $isSellerLogistics = in_array($shop?->logistics_type, ['seller', 'vendor'], true)
                                || in_array($shop?->delivery_mode, ['seller', 'vendor'], true);

                            if ($isSellerLogistics) {
                                $sellerLogisticsCount++;
                            } else {
                                $ovanieCount++;
                            }
                        }
                    @endphp

                    <article class="admin-order-card">
                        <header class="admin-order-card-head">
                            <div class="admin-order-identity">
                                <span class="admin-order-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 6h6M9 13h4"/></svg>
                                </span>
                                <div>
                                    <a href="{{ route('admin.orders.show', $order) }}">{{ $displayNumber }}</a>
                                    <span>{{ $order->created_at?->format('d/m/Y à H:i') ?? 'Date indisponible' }}</span>
                                    @if($order->invoice_number)
                                        <small>Facture {{ $order->invoice_number }}</small>
                                    @endif
                                </div>
                            </div>

                            <div class="admin-order-head-right">
                                <div class="admin-order-statuses">
                                    <span class="order-status status-{{ $order->status ?: 'pending' }}">
                                        {{ $statusLabels[$order->status] ?? ucfirst($order->status ?: 'pending') }}
                                    </span>
                                    <span class="order-status status-{{ $order->payment_status ?: 'pending' }}">
                                        {{ $paymentLabels[$order->payment_status] ?? ucfirst($order->payment_status ?: 'pending') }}
                                    </span>
                                </div>

                                <div class="admin-order-total">
                                    <strong>{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</strong>
                                    <span>{{ $paymentMethodLabels[$order->payment_method] ?? ($order->payment_method ?: 'Mode non défini') }}</span>
                                </div>

                                <a href="{{ route('admin.orders.show', $order) }}" class="orders-btn orders-btn-open">
                                    Voir le détail
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            </div>
                        </header>

                        <div class="admin-order-summary-grid">
                            <section class="admin-order-summary-block">
                                <span class="summary-label">Client</span>
                                <div class="admin-client-summary">
                                    <span class="admin-client-avatar">{{ strtoupper(substr($clientName, 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $clientName }}</strong>
                                        <span>{{ $order->client?->phone ?? $order->phone ?? 'Téléphone non renseigné' }}</span>
                                        <small>{{ $order->client?->email ?? 'E-mail non renseigné' }}</small>
                                    </div>
                                </div>
                            </section>

                            <section class="admin-order-summary-block">
                                <span class="summary-label">Répartition</span>
                                <strong class="summary-value">{{ $shopGroups->count() }} boutique(s)</strong>
                                <div class="delivery-mode-summary">
                                    @if($ovanieCount > 0)
                                        <span class="delivery-mode-chip is-ovanie">{{ $ovanieCount }} OVANIE Logistics</span>
                                    @endif
                                    @if($sellerLogisticsCount > 0)
                                        <span class="delivery-mode-chip is-seller">{{ $sellerLogisticsCount }} Logistique vendeur</span>
                                    @endif
                                </div>
                            </section>

                            <section class="admin-order-summary-block">
                                <span class="summary-label">Articles</span>
                                <strong class="summary-value">{{ $itemsCount }} article(s)</strong>
                                <span class="summary-subtext">{{ $referencesCount }} référence(s)</span>
                                <div class="product-name-preview">
                                    @foreach($order->items->take(2) as $item)
                                        <span>{{ $item->product?->name ?? 'Produit supprimé' }}</span>
                                    @endforeach
                                    @if($itemsCount > 2)
                                        <small>+ {{ $itemsCount - 2 }} autre(s)</small>
                                    @endif
                                </div>
                            </section>

                            <section class="admin-order-summary-block">
                                <span class="summary-label">Suivi</span>
                                <div class="tracking-summary-row">
                                    <span>Commande</span>
                                    <strong>{{ $statusLabels[$order->status] ?? ucfirst($order->status ?: 'pending') }}</strong>
                                </div>
                                <div class="tracking-summary-row">
                                    <span>Paiement</span>
                                    <strong>{{ $paymentLabels[$order->payment_status] ?? ucfirst($order->payment_status ?: 'pending') }}</strong>
                                </div>
                            </section>
                        </div>

                        <details class="admin-order-breakdown">
                            <summary>
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                                    Afficher la répartition par boutique
                                </span>
                                <span class="breakdown-count">{{ $shopGroups->count() }}</span>
                            </summary>

                            <div class="admin-order-shop-grid">
                                @forelse($shopGroups as $groupItems)
                                    @php
                                        $firstItem = $groupItems->first();
                                        $shop = $firstItem?->shop ?: $firstItem?->product?->shop ?: $order->shop;
                                        $seller = $shop?->user;
                                        $groupItemCount = $groupItems->sum(fn ($item) => (int) $item->quantity);
                                        $isSellerLogistics = in_array($shop?->logistics_type, ['seller', 'vendor'], true)
                                            || in_array($shop?->delivery_mode, ['seller', 'vendor'], true);
                                    @endphp

                                    <article class="admin-shop-card">
                                        <div class="admin-shop-card-head">
                                            <span class="admin-shop-avatar">{{ strtoupper(substr($shop?->name ?? 'B', 0, 1)) }}</span>
                                            <div>
                                                <strong>{{ $shop?->name ?? 'Boutique non reliée' }}</strong>
                                                <span>{{ $seller?->name ?? 'Vendeur non identifié' }}</span>
                                            </div>
                                            <span class="delivery-mode-chip {{ $isSellerLogistics ? 'is-seller' : 'is-ovanie' }}">
                                                {{ $isSellerLogistics ? 'Logistique vendeur' : 'OVANIE Logistics' }}
                                            </span>
                                        </div>
                                        <div class="admin-shop-card-foot">
                                            <span>{{ $groupItemCount }} article(s)</span>
                                            @if($seller?->email)
                                                <small>{{ $seller->email }}</small>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="admin-order-no-data">Aucune boutique n’est reliée à cette commande.</p>
                                @endforelse
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="admin-orders-empty">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 6h6M9 13h4"/></svg>
                        </span>
                        <h3>Aucune commande trouvée</h3>
                        <p>Aucune commande ne correspond aux critères sélectionnés.</p>
                        <a href="{{ route('admin.orders.index') }}" class="orders-btn orders-btn-secondary">Afficher toutes les commandes</a>
                    </div>
                @endforelse
            </div>

            @if($orders->hasPages())
                <div class="orders-pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
