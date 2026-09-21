@php
    $dashboard = $dashboard ?? [];
    $shop = $shop ?? auth()->user()?->shop;
    $user = $user ?? auth()->user();

    $products = $dashboard['products'] ?? ['total'=>0,'active'=>0,'pending'=>0,'archived'=>0];
    $orders = $dashboard['orders'] ?? ['total'=>0,'pending'=>0,'shipped'=>0,'delivered'=>0];
    $payouts = $dashboard['payouts'] ?? ['net_formatted'=>'0 FCFA','to_reverse_formatted'=>'0 FCFA','gross_formatted'=>'0 FCFA','commission_formatted'=>'0 FCFA','paid_formatted'=>'0 FCFA'];
    $deliveries = $dashboard['deliveries'] ?? ['total'=>0,'shipping'=>0,'delivered'=>0];
    $returns = $dashboard['returns'] ?? ['total'=>0];
    $disputes = $dashboard['disputes'] ?? ['total'=>0,'escalated'=>0];
    $reviews = $dashboard['reviews'] ?? ['average'=>0,'total'=>0];
    $negotiations = $dashboard['negotiations'] ?? ['total'=>0];
    $sales = $dashboard['sales_7_days'] ?? ['days'=>[], 'total_formatted'=>'0 FCFA'];
    $recentOrders = $dashboard['recent_orders'] ?? [];
    $criticalStock = $dashboard['critical_stock'] ?? [];
    $bestProducts = $dashboard['best_products'] ?? [];
    $recentPayouts = $dashboard['recent_payouts'] ?? [];
    $recentProducts = $dashboard['recent_products'] ?? [];
    $clientActivity = $dashboard['client_activity'] ?? [];
    $score = $dashboard['shop_score'] ?? 0;

    $safeRoute = function (string $name, array $params = [], string $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : $fallback;
    };

    $statusClass = function (?string $status) {
        return match ($status) {
            'pending', 'accepted', 'preparing' => 'orange',
            'shipped', 'shipping', 'in_transit', 'processing' => 'purple',
            'delivered', 'paid', 'completed', 'approved' => 'green',
            'cancelled', 'failed', 'rejected' => 'red',
            default => 'blue',
        };
    };
@endphp

<div class="seller-dashboard-page">
    <section class="seller-hero-grid">
        <div class="seller-hero-card">
            <div class="seller-hero-overlay"></div>
            <div class="seller-hero-content">
                <div class="seller-chip">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 10h16v10H4z"/><path d="m7 10 1-5h8l1 5"/></svg>
                    OVANIE SELLER CENTRAL
                </div>
                <h1>Bonjour, {{ $shop->name ?? $user?->name ?? 'vendeur' }} <span>👋</span></h1>
                <p>{{ now()->translatedFormat('l d F Y') }} — pilotez vos ventes, commandes, produits, livraisons, reversements et alertes depuis un seul tableau de bord.</p>

                <div class="seller-hero-actions">
                    <a class="btn btn-primary" href="{{ $safeRoute('vendor.add_product', [], url('/vendor/products/create')) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
                        Ajouter un produit
                    </a>
                    <a class="btn btn-light" href="{{ $safeRoute('vendor.orders', ['status'=>'pending'], url('/vendor/orders?status=pending')) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 5h12v15H6z"/><path d="M9 9h6M9 13h6"/></svg>
                        Traiter les commandes
                    </a>
                    <a class="btn btn-ghost" href="{{ $safeRoute('vendor.actes', [], url('/vendeur/vendeur-actes')) }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 19.5V5a2 2 0 0 1 2-2h7v18H6a2 2 0 0 1-2-1.5Z"/><path d="M13 3h5a2 2 0 0 1 2 2v14.5a2 2 0 0 0-2-1.5h-5"/></svg>
                        Vendeur actes
                    </a>
                    <a class="btn btn-ghost" href="{{ $safeRoute('catalog.index', [], url('/catalog')) }}" target="_blank" rel="noopener">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                        Retour sur OVANIE
                    </a>
                </div>
            </div>
        </div>

        <a class="seller-shop-card ov-card" href="{{ $safeRoute('vendor.shop.profile', [], '#') }}">
            <div class="seller-shop-head">
                <div class="seller-shop-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 10h16v10H4z"/><path d="m7 10 1-5h8l1 5"/><path d="M9 20v-6h6v6"/></svg>
                </div>
                <div>
                    <h2>{{ $shop->name ?? 'Boutique OVANIE' }}</h2>
                    <p>{{ $shop->city ?? 'Abidjan' }} · {{ $shop->commune ?? $shop->region ?? 'Côte d’Ivoire' }}</p>
                </div>
            </div>

            <div class="seller-score-row">
                <strong>Score boutique</strong>
                <strong>{{ $score }}%</strong>
            </div>
            <div class="seller-score-bar"><span style="width: {{ $score }}%"></span></div>

            <div class="seller-shop-info-grid">
                <div><small>Statut</small><strong><span class="dot"></span>{{ ucfirst($shop->status ?? 'pending') }} · {{ ($shop->is_active ?? false) ? 'Active' : 'Inactive' }}</strong></div>
                <div><small>Catégorie</small><strong>{{ $shop->main_category ?? 'Matériaux & Quincaillerie' }}</strong></div>
                <div><small>Zone</small><strong>{{ $shop->delivery_zone ?? 'Grand Abidjan' }}</strong></div>
                <div><small>Traitement</small><strong>{{ $shop->processing_time ?? '24–48h' }}</strong></div>
            </div>
        </a>
    </section>

    <section class="seller-stats-grid">
        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.products', [], url('/vendor/products')) }}">
            <div class="icon blue"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></svg></div>
            <div><small>Produits</small><strong>{{ $products['total'] ?? 0 }}</strong><p>{{ $products['active'] ?? 0 }} actifs · {{ $products['pending'] ?? 0 }} à valider · {{ $products['archived'] ?? 0 }} archivés</p></div>
        </a>

        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.orders', [], url('/vendor/orders')) }}">
            <div class="icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 4h10v16H7z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></div>
            <div><small>Commandes</small><strong>{{ $orders['total'] ?? 0 }}</strong><p>{{ $orders['preparing'] ?? $orders['pending'] ?? 0 }} à préparer · {{ $orders['shipped'] ?? 0 }} expédiées</p></div>
        </a>

        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.payouts.index', [], $safeRoute('vendor.payments', [], url('/vendor/payments'))) }}">
            <div class="icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 7h16v12H4z"/><path d="M4 10h16M15 15h3"/></svg></div>
            <div><small>Net vendeur</small><strong>{{ $payouts['net_formatted'] ?? '0 FCFA' }}</strong><p>{{ $payouts['to_reverse_formatted'] ?? '0 FCFA' }} à reverser</p></div>
        </a>

        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.orders', ['delivery_status'=>'shipping'], url('/vendor/orders?delivery_status=shipping')) }}">
            <div class="icon purple"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg></div>
            <div><small>Livraisons</small><strong>{{ $deliveries['total'] ?? 0 }}</strong><p>{{ $deliveries['shipping'] ?? 0 }} en expédition · {{ $deliveries['delivered'] ?? 0 }} livrées</p></div>
        </a>

        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.returns.index', [], url('/vendor/returns')) }}">
            <div class="icon red"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 7v6h6"/><path d="M5 13a7 7 0 1 0 2-6"/></svg></div>
            <div><small>Retours</small><strong>{{ $returns['total'] ?? 0 }}</strong><p>{{ $returns['total'] ?? 0 }} demandes au total</p></div>
        </a>

        <a class="seller-stat ov-card" href="{{ $safeRoute('vendor.disputes.index', [], url('/vendor/disputes')) }}">
            <div class="icon red"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="M12 8v5M12 16h.01"/></svg></div>
            <div><small>Litiges</small><strong>{{ $disputes['total'] ?? 0 }}</strong><p>{{ $disputes['escalated'] ?? 0 }} escaladé</p></div>
        </a>

        <a class="seller-stat ov-card" href="#">
            <div class="icon yellow"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.2 6.4 20.2 7.5 14 3 9.6l6.2-.9L12 3Z"/></svg></div>
            <div><small>Avis clients</small><strong>{{ $reviews['average'] ?? 0 }}/5</strong><p>{{ $reviews['total'] ?? 0 }} avis reçus</p></div>
        </a>

        <a class="seller-stat ov-card" href="#">
            <div class="icon orange"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H5l2.2-3.2A8 8 0 1 1 21 12Z"/></svg></div>
            <div><small>Négociations</small><strong>{{ $negotiations['total'] ?? 0 }}</strong><p>{{ $negotiations['total'] ?? 0 }} négociations au total</p></div>
        </a>
    </section>

    <section class="seller-main-grid">
        <div class="chart-card ov-card">
            <div class="section-head">
                <div><h3>Ventes des 7 derniers jours</h3><p>Aperçu rapide des ventes de votre boutique.</p></div>
                <strong>{{ $sales['total_formatted'] ?? '0 FCFA' }}</strong>
            </div>
            <div class="sales-chart">
                @foreach(($sales['days'] ?? []) as $day)
                    <div class="sales-day">
                        <span class="sales-amount">{{ $day['amount_formatted'] }}</span>
                        <div class="sales-bar"><span style="height: {{ $day['percent'] ?? 8 }}%"></span></div>
                        <strong>{{ $day['date'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="finance-card ov-card">
            <div class="section-head compact"><div><h3>Synthèse financière</h3><p>Montants calculés uniquement sur vos lignes de commande.</p></div></div>
            <div class="finance-grid">
                <div><span>🔗</span><strong>Ventes aujourd’hui</strong><p>{{ $payouts['gross_formatted'] ?? '0 FCFA' }}</p></div>
                <div><span>🗓️</span><strong>Ventes 30 jours</strong><p>{{ $payouts['gross_formatted'] ?? '0 FCFA' }}</p></div>
                <div><span>%</span><strong>Commission OVANIE</strong><p>{{ $payouts['commission_formatted'] ?? '0 FCFA' }}</p></div>
                <div><span>✓</span><strong>Déjà payé</strong><p>{{ $payouts['paid_formatted'] ?? '0 FCFA' }}</p></div>
            </div>
        </div>
    </section>

    <section class="seller-lists-grid">
        <div class="list-card ov-card wide">
            <div class="section-head compact"><div><h3>Commandes récentes</h3><p>Dernières commandes contenant vos produits.</p></div><a href="{{ $safeRoute('vendor.orders', [], url('/vendor/orders')) }}">Voir toutes</a></div>
            <div class="orders-table-wrap">
                <table>
                    <thead><tr><th>Commande</th><th>Client</th><th>Produit</th><th>Montant boutique</th><th>Statut</th></tr></thead>
                    <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td><a href="{{ $safeRoute('vendor.orders.show', ['order'=>$order['id']], '#') }}">{{ $order['number'] }}</a></td>
                            <td>{{ $order['client'] }}</td>
                            <td>{{ $order['product'] }}</td>
                            <td>{{ $order['amount'] }}</td>
                            <td><span class="status-pill {{ $statusClass($order['status'] ?? null) }}">{{ $order['status_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">Aucune commande pour le moment.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="list-card ov-card">
            <div class="section-head compact"><div><h3>Stock critique</h3><p>Produits à réapprovisionner.</p></div><a href="{{ $safeRoute('vendor.products', ['stock'=>'critical'], url('/vendor/products?stock=critical')) }}">Produits</a></div>
            @forelse($criticalStock as $product)
                <div class="mini-row"><span>{{ $product['name'] }}</span><strong class="red-text">{{ $product['stock'] }} {{ $product['unit'] ?? 'unités' }}</strong></div>
            @empty
                <div class="empty-state">Aucun stock critique.</div>
            @endforelse
        </div>

        <div class="list-card ov-card">
            <div class="section-head compact"><div><h3>Meilleurs produits</h3><p>Produits les plus vendus par quantité.</p></div><a href="{{ $safeRoute('vendor.products', ['sort'=>'best_sellers'], url('/vendor/products?sort=best_sellers')) }}">Voir</a></div>
            @forelse($bestProducts as $product)
                <div class="mini-row"><span>{{ $product['name'] }}</span><strong>{{ $product['quantity'] }} unités</strong></div>
            @empty
                <div class="empty-state">Aucune vente produit enregistrée.</div>
            @endforelse
        </div>

        <div class="list-card ov-card">
            <div class="section-head compact"><div><h3>Reversements récents</h3><p>Historique rapide de vos paiements vendeur.</p></div><a href="{{ $safeRoute('vendor.payouts.index', [], url('/vendor/payouts')) }}">Voir</a></div>
            @forelse($recentPayouts as $payout)
                <div class="mini-row"><span>{{ $payout['date'] }}</span><strong>{{ $payout['amount'] }}</strong><em class="status-pill {{ $statusClass($payout['status'] ?? null) }}">{{ $payout['status_label'] }}</em></div>
            @empty
                <div class="empty-state">Aucun reversement pour le moment.</div>
            @endforelse
        </div>

        <div class="list-card ov-card wide">
            <div class="section-head compact"><div><h3>Produits récents</h3><p>Derniers produits ajoutés ou modifiés.</p></div><a href="{{ $safeRoute('vendor.add_product', [], url('/vendor/products/create')) }}">Ajouter</a></div>
            <div class="recent-products">
                @forelse($recentProducts as $product)
                    <a href="{{ $safeRoute('vendor.products.edit', ['product'=>$product['id']], '#') }}" class="recent-product">
                        @if($product['image'])
                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                        @else
                            <span class="placeholder-img">📦</span>
                        @endif
                        <div><strong>{{ $product['name'] }}</strong><small>Ajouté le {{ $product['date'] }}</small></div>
                    </a>
                @empty
                    <div class="empty-state">Aucun produit. Ajoutez votre premier produit.</div>
                @endforelse
            </div>
        </div>

        <div class="list-card ov-card wide">
            <div class="section-head compact"><div><h3>Activité client</h3><p>Négociations, retours et litiges récents.</p></div></div>
            @forelse($clientActivity as $item)
                <div class="activity-row"><span>{{ $item['type'] === 'negotiation' ? '💬' : ($item['type'] === 'return' ? '↩️' : '⚠️') }}</span><strong>{{ $item['label'] }}</strong><small>{{ $item['time'] }}</small></div>
            @empty
                <div class="empty-state">Aucune activité récente.</div>
            @endforelse
        </div>
    </section>
</div>
