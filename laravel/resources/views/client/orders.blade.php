@extends('layouts.client')

@section('title', 'Mes commandes')

@section('content')
@inject('clientOrderStatus', 'App\Services\ClientOrderStatusService')
@inject('clientPaymentPresentation', 'App\Services\ClientPaymentPresentationService')

<section class="cs-page-head cs-orders-page-head">
    <div>
        <span class="cs-eyebrow">Espace client</span>
        <h1>Mes commandes</h1>
        <p>Consultez vos achats, vos livraisons et vos factures.</p>
    </div>

    <div class="cs-order-count-box">
        <strong>{{ $counts['all'] }}</strong>
        <span>commande(s)</span>
    </div>
</section>

<section class="cs-card cs-orders-tools">
    <form class="cs-toolbar cs-orders-toolbar" method="GET">
        <label class="cs-orders-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            <input
                name="search"
                value="{{ request('search') }}"
                placeholder="Rechercher une commande ou un article"
            >
        </label>

        <select name="period" aria-label="Période">
            <option value="">Toutes les périodes</option>
            <option value="month" @selected(request('period') === 'month')>Ce mois</option>
            <option value="3months" @selected(request('period') === '3months')>3 derniers mois</option>
            <option value="6months" @selected(request('period') === '6months')>6 derniers mois</option>
            <option value="year" @selected(request('period') === 'year')>Cette année</option>
        </select>

        <select name="sort" aria-label="Tri">
            <option value="">Plus récentes</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciennes</option>
            <option value="amount" @selected(request('sort') === 'amount')>Montant décroissant</option>
        </select>

        <button class="cs-btn" type="submit">Rechercher</button>
    </form>

    <nav class="cs-tabs cs-orders-tabs">
        @foreach([
            '' => ['Toutes', $counts['all']],
            'pending' => ['En cours', $counts['pending']],
            'shipped' => ['En livraison', $counts['shipped']],
            'completed' => ['Livrées', $counts['completed']],
            'cancelled' => ['Annulées', $counts['cancelled']],
            'returned' => ['Retournées', $counts['returned']],
        ] as $key => $tab)
            <a
                class="{{ request('status', '') === $key ? 'active' : '' }}"
                href="{{ route('client.orders', array_filter(
                    array_merge(request()->query(), ['status' => $key, 'page' => null]),
                    fn ($value) => $value !== null && $value !== ''
                )) }}"
            >
                <span>{{ $tab[0] }}</span>
                <b>{{ $tab[1] }}</b>
            </a>
        @endforeach
    </nav>
</section>

<section class="cs-card cs-orders-card">
    <div class="cs-table-wrap">
        <table class="cs-table cs-orders-table">
            <colgroup>
                <col class="col-order">
                <col class="col-date">
                <col class="col-items">
                <col class="col-total">
                <col class="col-payment">
                <col class="col-status">
                <col class="col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th>Commande</th>
                    <th>Date</th>
                    <th>Articles</th>
                    <th>Total</th>
                    <th>Paiement</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            @forelse($orders as $order)
                @php
                    $quantityTotal = $order->items->sum(fn ($item) => max(1, (int) $item->quantity));
                @endphp
                <tr>
                    <td data-label="Commande">
                        <a class="cs-link cs-order-reference" href="{{ route('client.orders.show', $order) }}">
                            #{{ $order->order_number }}
                        </a>
                        <small class="cs-order-invoice-ref">{{ $order->invoice_number ?: 'Facture disponible' }}</small>
                    </td>

                    <td data-label="Date">
                        <strong class="cs-date-primary">{{ $order->created_at?->format('d/m/Y') }}</strong>
                        <small>{{ $order->created_at?->format('H:i') }}</small>
                    </td>

                    <td data-label="Articles">
                        <a class="cs-order-products-preview cs-order-products-preview--compact" href="{{ route('client.orders.show', $order) }}">
                            <div class="cs-product-stack">
                                @foreach($order->items->take(3) as $item)
                                    <img
                                        src="{{ $item->product?->main_image_url }}"
                                        alt="Article commandé"
                                    >
                                @endforeach
                            </div>

                            <div>
                                <strong>{{ $quantityTotal }} article(s)</strong>
                                <small>{{ $order->items->count() }} référence(s)</small>
                            </div>
                        </a>
                    </td>

                    <td data-label="Total">
                        <strong class="cs-order-total">{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</strong>
                    </td>

                    <td data-label="Paiement">
                        <span class="cs-payment-method-label">
                            {{ $clientPaymentPresentation->methodLabel($order->payment_method) }}
                        </span>
                    </td>

                    <td data-label="Statut">
                        @include('client.partials.status', ['status' => $order->status])
                    </td>

                    <td data-label="Actions">
                        <div class="cs-order-actions cs-order-actions--inline">
                            <a class="cs-btn small" href="{{ route('client.orders.show', $order) }}">
                                Détails
                            </a>

                            @if($clientOrderStatus->canTrack($order))
                                <a class="cs-btn small outline" href="{{ route('client.orders.tracking', $order) }}">
                                    Suivre
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="cs-empty">
                            <h3>Aucune commande trouvée</h3>
                            <p>Modifiez vos filtres ou retournez au catalogue OVANIE.</p>
                            <a class="cs-btn" href="{{ Route::has('home') ? route('home') : url('/') }}">
                                Voir les produits
                            </a>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="cs-pagination">
        {{ $orders->links() }}
    </div>
</section>
@endsection
