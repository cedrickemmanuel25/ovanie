@extends('layouts.client')

@section('title', 'Commande '.$order->order_number)

@section('content')
@inject('deliveryGroupService', 'App\Services\ClientDeliveryGroupService')

@php
    $deliveryData = $deliveryGroupService->summary($order);
    $deliveryGroups = $deliveryData['groups'];
    $isCashOnDelivery = \App\Services\PaymentService::normalizeMethod($order->payment_method)
        === \App\Services\PaymentService::METHOD_CASH_ON_DELIVERY;
    $allDelivered = $deliveryGroups->isNotEmpty()
        && $deliveryGroups->every(fn ($group) => $group['is_delivered']);
    $allReceived = $order->items->isNotEmpty()
        && $order->items->every(fn ($item) => $item->reception_status === 'confirmed');
@endphp

<div class="cs-breadcrumb">
    <a href="{{ route('client.orders') }}">Mes commandes</a>
    <span>›</span>
    <span>{{ $order->order_number }}</span>
</div>

<section class="cs-page-head cs-order-detail-head">
    <div>
        <span class="cs-eyebrow">Détail de la commande</span>
        <h1>Commande #{{ $order->order_number }}</h1>
        <p>Passée le {{ $order->created_at?->format('d/m/Y à H:i') }}</p>
    </div>

    <div class="cs-actions cs-order-head-actions">
        @include('client.partials.status', ['status' => $order->status])

        @if($canTrack)
            <a class="cs-btn outline" href="{{ route('client.orders.tracking', $order) }}">Suivre</a>
        @endif

        <a class="cs-btn ghost" href="{{ route('receipt.pdf', $order->id) }}">Télécharger la facture</a>
    </div>
</section>

<section class="cs-order-total-card">
    <div>
        <span>Montant total</span>
        <strong>{{ number_format($deliveryData['order_total'], 0, ',', ' ') }} FCFA</strong>
    </div>
    <p>{{ $deliveryData['deliveries_count'] }} livraison(s) prévue(s)</p>
</section>

@if($isCashOnDelivery)
    <section class="cs-cod-guidance">
        <strong>Paiement à la réception</strong>
        <p>Le montant à régler est indiqué dans chaque livraison. Payez uniquement la livraison effectivement reçue.</p>
    </section>
@endif

<div class="cs-order-detail-layout cs-order-detail-layout-single">
    <main class="cs-order-detail-main">
        <section class="cs-card cs-delivery-plan-card">
            <div class="cs-section-head">
                <div>
                    <span class="cs-eyebrow">Organisation de la commande</span>
                    <h2>Vos livraisons</h2>
                    <p>Les articles sont regroupés selon les livraisons prévues.</p>
                </div>
            </div>

            <div class="cs-client-delivery-list">
                @forelse($deliveryGroups as $group)
                    <article class="cs-client-delivery-card">
                        <header>
                            <div class="cs-delivery-number">{{ $group['number'] }}</div>
                            <div class="cs-delivery-title">
                                <h3>{{ $group['label'] }}</h3>
                                <p>{{ $group['item_count'] }} article(s) · {{ $group['line_count'] }} référence(s)</p>
                                <div class="cs-delivery-date date-{{ $group['date_state'] }}">
                                    <span>{{ $group['date_label'] }}</span>
                                    <strong>{{ $group['date_value'] }}</strong>
                                </div>
                            </div>
                            <div class="cs-delivery-badges">
                                <span class="cs-delivery-status status-{{ $group['status'] }}">{{ $group['status_label'] }}</span>
                            </div>
                        </header>

                        <div class="cs-delivery-items-table">
                            @foreach($group['items'] as $item)
                                @php
                                    $lineTotal = (float) ($item->subtotal ?: ((float) $item->price * (int) $item->quantity));
                                @endphp
                                <div class="cs-delivery-item-row">
                                    <div class="cs-delivery-item-product">
                                        <span class="cs-delivery-item-image">
                                            <img src="{{ $item->product?->main_image_url }}" alt="Article commandé">
                                        </span>
                                        <div>
                                            <strong>{{ $item->product?->name ?: 'Produit indisponible' }}</strong>
                                            @if($item->product?->sku)
                                                <small>Réf. {{ $item->product->sku }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <span>
                                        <small>Quantité</small>
                                        <strong>{{ $item->quantity }}</strong>
                                    </span>
                                    <span>
                                        <small>Prix unitaire</small>
                                        <strong>{{ number_format($item->price, 0, ',', ' ') }} FCFA</strong>
                                    </span>
                                    <span>
                                        <small>Montant</small>
                                        <strong>{{ number_format($lineTotal, 0, ',', ' ') }} FCFA</strong>
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <footer class="cs-delivery-total-footer {{ $isCashOnDelivery && ! $group['is_paid'] ? 'is-cod' : '' }}">
                            <div>
                                <span>{{ $isCashOnDelivery && ! $group['is_paid'] ? 'Montant à régler à la réception' : 'Total' }}</span>
                                @if($isCashOnDelivery && ! $group['is_paid'])
                                    <small>À payer uniquement lors de la remise de cette livraison.</small>
                                @endif
                            </div>
                            <strong>{{ number_format($group['total'], 0, ',', ' ') }} FCFA</strong>
                        </footer>
                    </article>
                @empty
                    <div class="cs-empty">
                        <h3>Livraisons en cours de préparation</h3>
                        <p>Le détail apparaîtra dès que l’organisation de la commande sera finalisée.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="cs-order-bottom-grid">
            <section class="cs-card">
                <h2>Adresse de livraison</h2>
                <strong>{{ $order->delivery_recipient_name ?: $order->client?->name }}</strong>
                <p class="cs-address-text">
                    {{ $order->delivery_address ?: $order->address ?: 'Adresse non renseignée' }}<br>
                    @if($order->delivery_quartier || $order->delivery_commune)
                        {{ collect([$order->delivery_quartier, $order->delivery_commune])->filter()->implode(', ') }}<br>
                    @endif
                    @if($order->delivery_city){{ $order->delivery_city }}<br>@endif
                    {{ $order->delivery_recipient_phone ?: $order->phone }}
                </p>
            </section>

            <section class="cs-card cs-action-list">
                <h2>Actions</h2>
                <a class="cs-btn outline" href="{{ route('client.orders') }}">Retour aux commandes</a>
                @if($canTrack)<a class="cs-btn" href="{{ route('client.orders.tracking', $order) }}">Suivre mes livraisons</a>@endif
                @if($allDelivered && ! $allReceived)
                    <a class="cs-btn outline" href="{{ route('client.orders.reception.form', $order) }}">Confirmer la réception</a>
                @endif
                @if($canCancel)
                    <form method="POST" action="{{ route('client.orders.cancel', $order->id) }}" onsubmit="return confirm('Annuler cette commande ?')">
                        @csrf @method('PATCH')
                        <button class="cs-btn outline full" type="submit">Annuler la commande</button>
                    </form>
                @endif
                <a class="cs-btn ghost" href="{{ route('contact.index') }}">Contacter le support</a>
            </section>
        </div>
    </main>
</div>
@endsection
