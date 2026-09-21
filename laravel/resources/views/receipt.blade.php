@extends('layouts.guest')

@section('title', 'Facture '.$order->invoice_number.' | OVANIE')

@push('styles')
<style>
    .ov-invoice-page{padding:38px 0 64px;background:#f3f6fa}.ov-invoice-wrap{width:min(1180px,calc(100% - 36px));margin:auto}.ov-invoice-card{overflow:hidden;border:1px solid #dfe6ee;border-radius:18px;background:#fff;box-shadow:0 18px 48px rgba(15,23,42,.08)}
    .ov-invoice-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:28px 30px;border-top:5px solid #ff6a00;border-bottom:1px solid #e8edf3}.ov-invoice-head h1{margin:0;color:#071b4b;font-size:32px}.ov-invoice-head p{margin:6px 0 0;color:#64748b}.ov-invoice-number{text-align:right}.ov-invoice-number span,.ov-invoice-number strong{display:block}.ov-invoice-number span{color:#64748b;font-size:12px}.ov-invoice-number strong{margin-top:4px;color:#071b4b;font-size:18px}
    .ov-invoice-meta{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #e8edf3}.ov-invoice-meta div{padding:16px 20px;border-right:1px solid #e8edf3}.ov-invoice-meta div:last-child{border-right:0}.ov-invoice-meta span,.ov-invoice-meta strong{display:block}.ov-invoice-meta span{margin-bottom:5px;color:#94a3b8;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.ov-invoice-meta strong{color:#0f172a;font-size:12px}
    .ov-invoice-body{padding:26px 30px}.ov-invoice-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}.ov-invoice-info{padding:16px;border:1px solid #e5eaf0;border-radius:13px;background:#fbfcfe}.ov-invoice-info h2{margin:0 0 10px;color:#071b4b;font-size:15px}.ov-invoice-info p{margin:4px 0;color:#475569;font-size:12px;line-height:1.55}
    .ov-cod-note{margin-bottom:20px;padding:14px 16px;border:1px solid #fed7aa;border-radius:12px;background:#fffaf5;color:#7c2d12}.ov-cod-note strong{display:block;margin-bottom:3px;color:#9a3412}.ov-cod-note p{margin:0;font-size:11px;line-height:1.55}
    .ov-invoice-section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:15px;margin:0 0 13px}.ov-invoice-section-title h2{margin:0;color:#071b4b;font-size:19px}.ov-invoice-section-title span{color:#64748b;font-size:11px}
    .ov-delivery-invoice-list{display:grid;gap:15px}.ov-delivery-invoice{overflow:hidden;border:1px solid #dfe6ee;border-radius:14px}.ov-delivery-invoice>header{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:12px;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e5eaf0}.ov-delivery-invoice-number{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#071b4b;color:#fff;font-weight:900}.ov-delivery-invoice h3{margin:0;color:#0f172a;font-size:14px}.ov-delivery-invoice header p{margin:3px 0 0;color:#64748b;font-size:10px}.ov-status{padding:6px 9px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:9px;font-weight:900;white-space:nowrap}
    .ov-invoice-items{width:100%;border-collapse:collapse}.ov-invoice-items th{padding:9px 12px;background:#fff;color:#64748b;font-size:9px;text-align:left;text-transform:uppercase}.ov-invoice-items td{padding:11px 12px;border-top:1px solid #edf2f7;color:#0f172a;font-size:11px}.ov-invoice-items th:nth-child(n+2),.ov-invoice-items td:nth-child(n+2){text-align:right}.ov-product-cell{display:flex;align-items:center;gap:9px}.ov-product-cell img{width:38px;height:38px;object-fit:contain;border-radius:8px;background:#f8fafc}.ov-product-cell strong{font-size:11px}
    .ov-cod-group-total{display:flex;align-items:center;justify-content:flex-end;gap:20px;padding:13px 16px;border-top:1px solid #e5eaf0;background:#fffaf5}.ov-cod-group-total span{color:#7c2d12;font-size:11px;font-weight:700}.ov-cod-group-total strong{color:#9a3412;font-size:14px}
    .ov-invoice-total{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:22px;padding:18px 20px;border:1px solid #dfe6ee;border-radius:13px;background:#071b4b;color:#fff}.ov-invoice-total span{font-size:13px;font-weight:800}.ov-invoice-total strong{font-size:22px;white-space:nowrap}
    .ov-invoice-actions{display:flex;justify-content:flex-end;gap:10px;padding:18px 30px;border-top:1px solid #e8edf3;background:#fbfcfe}.ov-invoice-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:1px solid #dbe3ec;border-radius:10px;background:#fff;color:#071b4b;font-size:11px;font-weight:800}.ov-invoice-btn.primary{border-color:#ff6a00;background:#ff6a00;color:#fff}
    @media(max-width:850px){.ov-invoice-meta{grid-template-columns:repeat(2,1fr)}.ov-invoice-meta div:nth-child(2){border-right:0}.ov-invoice-grid{grid-template-columns:1fr}.ov-delivery-invoice>header{grid-template-columns:38px 1fr}.ov-status{grid-column:1/-1;width:max-content}.ov-invoice-items th:nth-child(2),.ov-invoice-items td:nth-child(2){display:none}}
    @media(max-width:560px){.ov-invoice-page{padding:18px 0}.ov-invoice-wrap{width:min(100% - 18px,1180px)}.ov-invoice-head{padding:20px;flex-direction:column}.ov-invoice-number{text-align:left}.ov-invoice-body{padding:18px}.ov-invoice-meta{grid-template-columns:1fr}.ov-invoice-meta div{border-right:0}.ov-invoice-actions{padding:14px 18px;flex-direction:column}.ov-invoice-items th:nth-child(3),.ov-invoice-items td:nth-child(3){display:none}.ov-invoice-total{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@section('content')
@inject('deliveryGroupService', 'App\Services\ClientDeliveryGroupService')
@inject('clientPaymentPresentation', 'App\Services\ClientPaymentPresentationService')
@php
    $deliveryData = $deliveryGroupService->summary($order);
    $deliveryGroups = $deliveryData['groups'];
    $isCashOnDelivery = \App\Services\PaymentService::normalizeMethod($order->payment_method)
        === \App\Services\PaymentService::METHOD_CASH_ON_DELIVERY;
@endphp

<section class="ov-invoice-page">
    <div class="ov-invoice-wrap">
        <article class="ov-invoice-card">
            <header class="ov-invoice-head">
                <div>
                    <h1>Facture OVANIE</h1>
                    <p>Détail de votre commande.</p>
                </div>
                <div class="ov-invoice-number">
                    <span>Numéro de facture</span>
                    <strong>{{ $order->invoice_number ?: 'FAC-'.str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</strong>
                </div>
            </header>

            <div class="ov-invoice-meta">
                <div><span>Commande</span><strong>#{{ $order->order_number }}</strong></div>
                <div><span>Date</span><strong>{{ $order->created_at?->format('d/m/Y H:i') }}</strong></div>
                <div><span>Paiement</span><strong>{{ $clientPaymentPresentation->methodLabel($order->payment_method) }}</strong></div>
                <div><span>Statut</span><strong>{{ $clientPaymentPresentation->statusLabel($order->payment_status) }}</strong></div>
            </div>

            <div class="ov-invoice-body">
                <div class="ov-invoice-grid">
                    <section class="ov-invoice-info">
                        <h2>Client</h2>
                        <p><strong>{{ $order->delivery_recipient_name ?: $order->client?->name }}</strong></p>
                        <p>{{ $order->delivery_recipient_phone ?: $order->phone ?: '—' }}</p>
                        <p>{{ $order->client?->email ?: '—' }}</p>
                    </section>
                    <section class="ov-invoice-info">
                        <h2>Adresse de livraison</h2>
                        <p>{{ $order->delivery_address ?: $order->address ?: 'Adresse non renseignée' }}</p>
                        <p>{{ collect([$order->delivery_quartier, $order->delivery_commune, $order->delivery_city])->filter()->implode(', ') }}</p>
                    </section>
                </div>

                @if($isCashOnDelivery)
                    <div class="ov-cod-note">
                        <strong>Paiement à la réception</strong>
                        <p>Le montant à régler est indiqué pour chaque livraison. Payez uniquement la livraison effectivement reçue.</p>
                    </div>
                @endif

                <div class="ov-invoice-section-title">
                    <h2>Livraisons prévues</h2>
                    <span>{{ $deliveryData['deliveries_count'] }} livraison(s)</span>
                </div>

                <div class="ov-delivery-invoice-list">
                    @foreach($deliveryGroups as $group)
                        <section class="ov-delivery-invoice">
                            <header>
                                <div class="ov-delivery-invoice-number">{{ $group['number'] }}</div>
                                <div>
                                    <h3>{{ $group['label'] }}</h3>
                                    <p>{{ $group['item_count'] }} article(s) · {{ $group['line_count'] }} référence(s)</p>
                                </div>
                                <span class="ov-status">{{ $group['status_label'] }}</span>
                            </header>

                            <table class="ov-invoice-items">
                                <thead><tr><th>Article</th><th>Prix unitaire</th><th>Quantité</th><th>Montant</th></tr></thead>
                                <tbody>
                                    @foreach($group['items'] as $item)
                                        @php $lineTotal = (float) ($item->subtotal ?: ((float) $item->price * (int) $item->quantity)); @endphp
                                        <tr>
                                            <td>
                                                <div class="ov-product-cell">
                                                    <img src="{{ $item->product?->main_image_url }}" alt="Article">
                                                    <strong>{{ $item->product?->name ?: 'Produit indisponible' }}</strong>
                                                </div>
                                            </td>
                                            <td>{{ number_format($item->price, 0, ',', ' ') }} FCFA</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td><strong>{{ number_format($lineTotal, 0, ',', ' ') }} FCFA</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if($isCashOnDelivery)
                                <footer class="ov-cod-group-total">
                                    <span>{{ $group['is_paid'] ? 'Règlement reçu' : 'Montant à régler à la réception' }}</span>
                                    <strong>{{ number_format($group['is_paid'] ? $group['total'] : $group['amount_due'], 0, ',', ' ') }} FCFA</strong>
                                </footer>
                            @endif
                        </section>
                    @endforeach
                </div>

                <div class="ov-invoice-total">
                    <span>Montant total de la commande</span>
                    <strong>{{ number_format($deliveryData['order_total'], 0, ',', ' ') }} FCFA</strong>
                </div>
            </div>

            <footer class="ov-invoice-actions">
                <a class="ov-invoice-btn" href="{{ route('client.orders.show', $order) }}">Retour à la commande</a>
                <a class="ov-invoice-btn primary" href="{{ route('receipt.pdf', $order->id) }}">Télécharger la facture PDF</a>
            </footer>
        </article>
    </div>
</section>
@endsection
