@extends('admin.layouts.app')

@section('title', 'Tableau de bord Admin | OVANIE')
@section('page-title', 'Tableau de bord')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_dashboard.css') }}">
@endpush

@section('content')
@php
    $overview = $overview ?? [];
    $salesTrend = $sales_trend ?? ['points' => [], 'total' => 0, 'orders' => 0, 'days' => 14];
    $trendPoints = collect($salesTrend['points'] ?? []);
    $actionsRequired = collect($actions_required ?? []);

    $money = fn ($amount) => number_format((float) $amount, 0, ',', ' ') . ' FCFA';
    $number = fn ($value) => number_format((int) $value, 0, ',', ' ');
    $currentPeriodLabel = ucfirst(now()->locale('fr')->translatedFormat('F Y'));
    $relativeDate = fn ($date) => $date
        ? \Illuminate\Support\Carbon::parse($date)->locale('fr')->diffForHumans()
        : 'Aucune activité';

    $statusLabel = function ($status) {
        return match (strtolower((string) $status)) {
            'pending', 'en_attente', 'waiting' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing', 'preparing' => 'En préparation',
            'ready_for_pickup' => 'Prête à collecter',
            'shipped' => 'Expédiée',
            'in_transit' => 'En cours de livraison',
            'delivered' => 'Livrée',
            'completed' => 'Terminée',
            'paid', 'success', 'verified' => 'Payée',
            'escrow_held' => 'Paiement sécurisé',
            'commission_paid' => 'Commission réglée',
            'cancelled', 'canceled' => 'Annulée',
            'failed' => 'Échec',
            'rejected' => 'Refusée',
            'refunded' => 'Remboursée',
            'approved' => 'Approuvée',
            'active', 'actif', 'published' => 'Publié',
            'draft' => 'Brouillon',
            'inactive' => 'Inactif',
            'incomplete' => 'À compléter',
            'pending_logistics' => 'Logistique incomplète',
            'manual_review' => 'Contrôle manuel',
            'awaiting_proof' => 'Preuve attendue',
            default => ucfirst(str_replace('_', ' ', (string) ($status ?: 'Non défini'))),
        };
    };

    $statusTone = function ($status) {
        return match (strtolower((string) $status)) {
            'paid', 'completed', 'delivered', 'approved', 'active', 'actif', 'published', 'success', 'verified' => 'success',
            'pending', 'en_attente', 'waiting', 'processing', 'manual_review', 'awaiting_proof', 'escrow_held', 'draft', 'pending_logistics' => 'warning',
            'failed', 'cancelled', 'canceled', 'rejected', 'refunded' => 'danger',
            'confirmed', 'shipped', 'in_transit', 'ready_for_pickup' => 'info',
            default => 'neutral',
        };
    };

    $paymentMethodLabel = function ($method) {
        return match (strtolower((string) $method)) {
            'cash_on_delivery', 'cod' => 'Paiement à la livraison',
            'paydunya', 'online', 'card' => 'Paiement en ligne',
            'wave' => 'Wave',
            'orange', 'orange_money' => 'Orange Money',
            'mtn', 'mtn_money' => 'MTN Mobile Money',
            'moov', 'moov_money' => 'Moov Money',
            'bank_transfer', 'bank' => 'Virement bancaire',
            default => $method ? ucfirst(str_replace('_', ' ', (string) $method)) : 'Non renseigné',
        };
    };

    $changeMeta = function ($change) {
        if ($change === null) {
            return ['label' => 'Nouvelle activité', 'tone' => 'positive', 'symbol' => '↗'];
        }

        $change = (float) $change;
        if ($change > 0) {
            return ['label' => '+' . number_format($change, 1, ',', ' ') . '%', 'tone' => 'positive', 'symbol' => '↗'];
        }
        if ($change < 0) {
            return ['label' => number_format($change, 1, ',', ' ') . '%', 'tone' => 'negative', 'symbol' => '↘'];
        }

        return ['label' => 'Stable', 'tone' => 'neutral', 'symbol' => '→'];
    };

    $salesChange = $changeMeta($overview['sales_month_change'] ?? 0);
    $ordersChange = $changeMeta($overview['orders_month_change'] ?? 0);

    $chartWidth = 760;
    $chartHeight = 230;
    $chartLeft = 48;
    $chartRight = 18;
    $chartTop = 20;
    $chartBottom = 42;
    $plotWidth = $chartWidth - $chartLeft - $chartRight;
    $plotHeight = $chartHeight - $chartTop - $chartBottom;
    $chartMax = max(1, (float) $trendPoints->max('amount'));
    $chartCount = max(1, $trendPoints->count());
    $chartStep = $chartCount > 1 ? $plotWidth / ($chartCount - 1) : 0;
    $chartCoordinates = $trendPoints->values()->map(function ($point, $index) use ($chartLeft, $chartTop, $plotHeight, $chartMax, $chartStep) {
        $x = $chartLeft + ($index * $chartStep);
        $y = $chartTop + $plotHeight - (((float) ($point['amount'] ?? 0) / $chartMax) * $plotHeight);
        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'amount' => (float) ($point['amount'] ?? 0),
            'label' => $point['label'] ?? '',
            'orders' => (int) ($point['orders'] ?? 0),
        ];
    });
    $linePoints = $chartCoordinates->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
    $areaPoints = $chartCoordinates->isEmpty()
        ? ''
        : $chartLeft . ',' . ($chartTop + $plotHeight) . ' ' . $linePoints . ' ' . $chartCoordinates->last()['x'] . ',' . ($chartTop + $plotHeight);
    $chartLabels = $chartCoordinates->filter(fn ($point, $index) => $index === 0 || $index === $chartCoordinates->count() - 1 || $index % 3 === 0);
@endphp

<div class="adash-shell">
    <section class="adash-heading">
        <div>
            <div class="adash-eyebrow">Vue générale</div>
            <h2>Pilotage de la marketplace</h2>
            <p>Suivez les ventes, les commandes, les boutiques, les paiements et l’activité des équipes commerciales.</p>
        </div>

        <div class="adash-heading-actions">
            <span class="adash-period">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                {{ $currentPeriodLabel }}
            </span>
            <a class="adash-button adash-button-secondary" href="{{ route('admin.orders.index') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6"/></svg>
                Commandes
            </a>
            <a class="adash-button adash-button-primary" href="{{ route('admin.shops.index') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10 5 4h14l2 6M5 10v10h14V10M9 20v-6h6v6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
                Boutiques à traiter
            </a>
        </div>
    </section>


    @php
        $visibleSecurityAlerts = collect($security_alerts ?? [])->reject(function ($alert) {
            $title = mb_strtolower((string) ($alert['title'] ?? ''));
            $message = mb_strtolower((string) ($alert['message'] ?? ''));

            return str_contains($title, 'mode diagnostic')
                || str_contains($message, 'app_debug')
                || str_contains($title, 'environnement de test');
        });
    @endphp

    @if($visibleSecurityAlerts->isNotEmpty())
        <section class="adash-notice-row">
            @foreach($visibleSecurityAlerts as $alert)
                <div class="adash-notice {{ ($alert['level'] ?? '') === 'critical' ? 'adash-notice-danger' : 'adash-notice-warning' }}">
                    <span class="adash-notice-icon">!</span>
                    <div>
                        <strong>{{ $alert['title'] ?? 'Alerte système' }}</strong>
                        <p>{{ $alert['message'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="adash-kpis">
        <article class="adash-kpi">
            <div class="adash-kpi-top">
                <span class="adash-kpi-icon adash-kpi-icon-blue">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17 9 12l4 4 7-9M4 21h16"/></svg>
                </span>
                <span class="adash-change adash-change-{{ $salesChange['tone'] }}">{{ $salesChange['symbol'] }} {{ $salesChange['label'] }}</span>
            </div>
            <p class="adash-kpi-label">Chiffre d’affaires encaissé</p>
            <strong class="adash-kpi-value">{{ $money($overview['sales_month'] ?? 0) }}</strong>
            <small>Comparé au mois précédent</small>
        </article>

        <article class="adash-kpi">
            <div class="adash-kpi-top">
                <span class="adash-kpi-icon adash-kpi-icon-orange">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6"/></svg>
                </span>
                <span class="adash-change adash-change-{{ $ordersChange['tone'] }}">{{ $ordersChange['symbol'] }} {{ $ordersChange['label'] }}</span>
            </div>
            <p class="adash-kpi-label">Commandes du mois</p>
            <strong class="adash-kpi-value">{{ $number($overview['orders_month'] ?? 0) }}</strong>
            <small>{{ $number($overview['orders_today'] ?? 0) }} enregistrée(s) aujourd’hui</small>
        </article>

        <article class="adash-kpi">
            <div class="adash-kpi-top">
                <span class="adash-kpi-icon adash-kpi-icon-green">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
                <a class="adash-kpi-link" href="{{ route('admin.commissions.index') }}">Consulter</a>
            </div>
            <p class="adash-kpi-label">Commissions OVANIE</p>
            <strong class="adash-kpi-value">{{ $money($overview['commissions_month'] ?? 0) }}</strong>
            <small>Commissions générées ce mois</small>
        </article>

        <article class="adash-kpi">
            <div class="adash-kpi-top">
                <span class="adash-kpi-icon adash-kpi-icon-purple">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v12H3V7Zm0 4h18M7 15h4"/></svg>
                </span>
                <a class="adash-kpi-link" href="{{ route('admin.payouts.index') }}">Traiter</a>
            </div>
            <p class="adash-kpi-label">Fonds prêts à reverser</p>
            <strong class="adash-kpi-value">{{ $money($overview['vendor_funds_ready'] ?? 0) }}</strong>
            <small>Reversements vendeurs arrivés à échéance</small>
        </article>
    </section>

    <section class="adash-main-grid">
        <article class="adash-panel adash-chart-panel">
            <header class="adash-panel-head">
                <div>
                    <span class="adash-panel-kicker">Performance</span>
                    <h3>Évolution des ventes</h3>
                    <p>{{ $salesTrend['days'] ?? 14 }} derniers jours · {{ $number($salesTrend['orders'] ?? 0) }} commande(s) encaissée(s)</p>
                </div>
                <strong class="adash-chart-total">{{ $money($salesTrend['total'] ?? 0) }}</strong>
            </header>

            <div class="adash-chart-wrap">
                <svg class="adash-chart" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Évolution des ventes sur les quatorze derniers jours">
                    <defs>
                        <linearGradient id="salesArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563eb" stop-opacity=".22"/>
                            <stop offset="100%" stop-color="#2563eb" stop-opacity="0"/>
                        </linearGradient>
                    </defs>

                    @for($grid = 0; $grid <= 3; $grid++)
                        @php
                            $gridY = $chartTop + (($plotHeight / 3) * $grid);
                        @endphp
                        <line x1="{{ $chartLeft }}" y1="{{ $gridY }}" x2="{{ $chartWidth - $chartRight }}" y2="{{ $gridY }}" class="adash-chart-grid"/>
                    @endfor

                    <text x="4" y="{{ $chartTop + 5 }}" class="adash-chart-y-label">{{ number_format($chartMax, 0, ',', ' ') }}</text>
                    <text x="4" y="{{ $chartTop + ($plotHeight / 2) + 5 }}" class="adash-chart-y-label">{{ number_format($chartMax / 2, 0, ',', ' ') }}</text>
                    <text x="34" y="{{ $chartTop + $plotHeight + 5 }}" class="adash-chart-y-label">0</text>

                    @if($areaPoints !== '')
                        <polygon points="{{ $areaPoints }}" fill="url(#salesArea)"/>
                        <polyline points="{{ $linePoints }}" class="adash-chart-line"/>

                        @foreach($chartCoordinates as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="adash-chart-dot">
                                <title>{{ $point['label'] }} : {{ $money($point['amount']) }} · {{ $point['orders'] }} commande(s)</title>
                            </circle>
                        @endforeach

                        @foreach($chartLabels as $point)
                            <text x="{{ $point['x'] }}" y="{{ $chartHeight - 12 }}" text-anchor="middle" class="adash-chart-x-label">{{ $point['label'] }}</text>
                        @endforeach
                    @endif
                </svg>
            </div>
        </article>

        <article class="adash-panel adash-actions-panel">
            <header class="adash-panel-head">
                <div>
                    <span class="adash-panel-kicker">Priorités</span>
                    <h3>Actions requises</h3>
                    <p>Éléments qui nécessitent une intervention de l’administration.</p>
                </div>
                <span class="adash-action-total">{{ $actionsRequired->sum('count') }}</span>
            </header>

            <div class="adash-action-list">
                @forelse($actionsRequired as $action)
                    <a class="adash-action-item" href="{{ route($action['route']) }}">
                        <span class="adash-action-count adash-tone-{{ $action['tone'] }}">{{ $number($action['count']) }}</span>
                        <span class="adash-action-copy">
                            <strong>{{ $action['label'] }}</strong>
                            <small>{{ $action['description'] }}</small>
                        </span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @empty
                    <div class="adash-empty adash-empty-compact">
                        <span class="adash-empty-icon">✓</span>
                        <strong>Aucune action prioritaire</strong>
                        <p>Les contrôles suivis par ce tableau de bord sont à jour.</p>
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="adash-operations">
        <a class="adash-operation" href="{{ route('admin.orders.index') }}">
            <span class="adash-operation-icon"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3"/></svg></span>
            <span><small>Commandes</small><strong>{{ $number($overview['orders_total'] ?? 0) }}</strong></span>
            <em>{{ $number($overview['orders_today'] ?? 0) }} aujourd’hui</em>
        </a>
        <a class="adash-operation" href="{{ route('admin.products.index') }}">
            <span class="adash-operation-icon"><svg viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5 9-5Zm-9 5v10l9 5 9-5V7M12 12v10"/></svg></span>
            <span><small>Produits publiés</small><strong>{{ $number($overview['products_active'] ?? 0) }}</strong></span>
            <em>{{ $number($overview['products_pending'] ?? 0) }} à compléter</em>
        </a>
        <a class="adash-operation" href="{{ route('admin.shops.index') }}">
            <span class="adash-operation-icon"><svg viewBox="0 0 24 24"><path d="M3 10 5 4h14l2 6M5 10v10h14V10M9 20v-6h6v6"/></svg></span>
            <span><small>Boutiques prêtes</small><strong>{{ $number($overview['shops_ready'] ?? 0) }}</strong></span>
            <em>{{ $number($overview['shops_total'] ?? 0) }} au total</em>
        </a>
        <a class="adash-operation" href="{{ route('admin.clients.index') }}">
            <span class="adash-operation-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3h5M19.5 5.5v5"/></svg></span>
            <span><small>Clients</small><strong>{{ $number($overview['clients_total'] ?? 0) }}</strong></span>
            <em>{{ $number($overview['clients_month'] ?? 0) }} ce mois</em>
        </a>
    </section>

    <section class="adash-two-columns">
        <article class="adash-panel">
            <header class="adash-panel-head adash-panel-head-border">
                <div>
                    <span class="adash-panel-kicker">Transactions</span>
                    <h3>Commandes récentes</h3>
                </div>
                <a class="adash-text-link" href="{{ route('admin.orders.index') }}">Voir toutes</a>
            </header>

            <div class="adash-table-wrap">
                <table class="adash-table">
                    <thead>
                        <tr>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Paiement</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent_orders ?? [] as $order)
                            @php
                                $client = $order->client ?? $order->user ?? null;
                                $clientName = trim((string) ($client->first_name ?? '') . ' ' . (string) ($client->last_name ?? ''));
                                $clientName = $clientName !== '' ? $clientName : ($client->name ?? $order->customer_name ?? 'Client');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $order->order_number ?: '#' . $order->id }}</strong>
                                    <small>{{ optional($order->created_at)->format('d/m/Y H:i') }}</small>
                                </td>
                                <td><span>{{ $clientName }}</span></td>
                                <td><span>{{ $paymentMethodLabel($order->payment_method ?? null) }}</span></td>
                                <td><strong>{{ $money($order->total_amount ?? $order->grand_total ?? $order->total ?? 0) }}</strong></td>
                                <td><span class="adash-badge adash-badge-{{ $statusTone($order->status ?? '') }}">{{ $statusLabel($order->status ?? '') }}</span></td>
                                <td><a class="adash-row-action" href="{{ route('admin.orders.show', $order) }}" aria-label="Voir la commande"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="adash-empty"><strong>Aucune commande récente</strong><p>Les nouvelles commandes apparaîtront ici.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="adash-panel">
            <header class="adash-panel-head adash-panel-head-border">
                <div>
                    <span class="adash-panel-kicker">Conformité</span>
                    <h3>Boutiques à traiter</h3>
                </div>
                <a class="adash-text-link" href="{{ route('admin.shops.index') }}">Gérer les boutiques</a>
            </header>

            <div class="adash-shop-list">
                @forelse($attention_shops ?? [] as $shop)
                    @php
                        $owner = $shop->user;
                        $ownerName = trim((string) ($owner->first_name ?? '') . ' ' . (string) ($owner->last_name ?? ''));
                        $ownerName = $ownerName !== '' ? $ownerName : ($owner->name ?? 'Responsable non renseigné');
                        $commercial = $shop->commercialManager ?? $shop->commercialCreator;
                        $commercialName = $commercial ? trim((string) ($commercial->first_name ?? '') . ' ' . (string) ($commercial->last_name ?? '')) : '';
                        $commercialName = $commercialName !== '' ? $commercialName : ($commercial->name ?? 'Non attribuée');
                    @endphp
                    <div class="adash-shop-item">
                        <div class="adash-shop-avatar">{{ mb_strtoupper(mb_substr($shop->name ?? 'B', 0, 1)) }}</div>
                        <div class="adash-shop-main">
                            <div class="adash-shop-title-row">
                                <strong>{{ $shop->name ?? 'Boutique' }}</strong>
                                <span>{{ $number($shop->products_count ?? 0) }} produit(s)</span>
                            </div>
                            <p>{{ $ownerName }} · {{ $shop->commune ?: ($shop->city ?: 'Localisation non renseignée') }}</p>
                            <div class="adash-issue-list">
                                @foreach($shop->dashboard_issues ?? [] as $issue)
                                    <span>{{ $issue }}</span>
                                @endforeach
                            </div>
                            <small>Suivi commercial : {{ $commercialName }}</small>
                        </div>
                        <a class="adash-row-action" href="{{ route('admin.shops.show', $shop) }}" aria-label="Voir la boutique"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></a>
                    </div>
                @empty
                    <div class="adash-empty adash-empty-compact"><span class="adash-empty-icon">✓</span><strong>Aucune boutique prioritaire</strong><p>Aucun dossier récent ne nécessite de correction.</p></div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="adash-panel">
        <header class="adash-panel-head adash-panel-head-border">
            <div>
                <span class="adash-panel-kicker">Équipe terrain</span>
                <h3>Activité commerciale</h3>
                <p>Traçabilité des clients, boutiques et produits créés par chaque Commercial.</p>
            </div>
            <a class="adash-text-link" href="{{ route('admin.staff.index') }}">Gérer l’équipe</a>
        </header>

        <div class="adash-table-wrap">
            <table class="adash-table adash-commercial-table">
                <thead>
                    <tr>
                        <th>Commercial</th>
                        <th>Clients créés</th>
                        <th>Boutiques créées</th>
                        <th>Boutiques suivies</th>
                        <th>Produits ajoutés</th>
                        <th>Commandes liées</th>
                        <th>Dernière activité</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commercial_activity ?? [] as $commercial)
                        <tr>
                            <td>
                                <div class="adash-person">
                                    <span>{{ mb_strtoupper(mb_substr($commercial['name'] ?? 'C', 0, 1)) }}</span>
                                    <div><strong>{{ $commercial['name'] }}</strong><small>{{ $commercial['email'] }}</small></div>
                                </div>
                            </td>
                            <td><strong>{{ $number($commercial['clients_created']) }}</strong></td>
                            <td><strong>{{ $number($commercial['shops_created']) }}</strong></td>
                            <td><strong>{{ $number($commercial['shops_managed']) }}</strong></td>
                            <td><strong>{{ $number($commercial['products_created']) }}</strong></td>
                            <td><strong>{{ $number($commercial['orders_linked']) }}</strong></td>
                            <td><span>{{ $relativeDate($commercial['last_activity'] ?? null) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="adash-empty"><strong>Aucun Commercial enregistré</strong><p>Les statistiques apparaîtront après la création des comptes commerciaux.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="adash-bottom-grid">
        <article class="adash-panel">
            <header class="adash-panel-head adash-panel-head-border">
                <div>
                    <span class="adash-panel-kicker">Finance</span>
                    <h3>Répartition financière</h3>
                </div>
                <a class="adash-text-link" href="{{ route('admin.commissions.index') }}">Voir les commissions</a>
            </header>
            <div class="adash-finance-list">
                <div><span>Ventes produits</span><strong>{{ $money($overview['product_sales_total'] ?? 0) }}</strong></div>
                <div><span>Livraison encaissée</span><strong>{{ $money($overview['delivery_fees_collected'] ?? 0) }}</strong></div>
                <div><span>Revenus OVANIE Logistics</span><strong>{{ $money($overview['ovanie_delivery_revenue'] ?? 0) }}</strong></div>
                <div><span>Livraison vendeur à reverser</span><strong>{{ $money($overview['seller_delivery_payable'] ?? 0) }}</strong></div>
                <div><span>Fonds vendeurs en attente</span><strong>{{ $money($overview['vendor_funds_pending'] ?? 0) }}</strong></div>
                <div><span>Déjà reversé</span><strong>{{ $money($overview['vendor_funds_paid'] ?? 0) }}</strong></div>
            </div>
        </article>

        <article class="adash-panel">
            <header class="adash-panel-head adash-panel-head-border">
                <div>
                    <span class="adash-panel-kicker">Paiement</span>
                    <h3>Derniers contrôles à effectuer</h3>
                </div>
                <a class="adash-text-link" href="{{ route('admin.payment-verifications.index') }}">Ouvrir les vérifications</a>
            </header>
            <div class="adash-payment-list">
                @forelse($pending_payments ?? [] as $payment)
                    <div class="adash-payment-item">
                        <div>
                            <strong>Paiement #{{ $payment->id }}</strong>
                            <span>{{ $paymentMethodLabel($payment->method ?? $payment->provider ?? null) }}</span>
                        </div>
                        <div>
                            <strong>{{ $money($payment->amount ?? $payment->total ?? 0) }}</strong>
                            <span class="adash-badge adash-badge-{{ $statusTone($payment->status ?? 'pending') }}">{{ $statusLabel($payment->status ?? 'pending') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="adash-empty adash-empty-compact"><span class="adash-empty-icon">✓</span><strong>Aucun paiement à vérifier</strong><p>Les contrôles en attente apparaîtront ici.</p></div>
                @endforelse
            </div>
        </article>
    </section>
</div>
@endsection
