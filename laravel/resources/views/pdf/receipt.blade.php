<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $order->invoice_number }}</title>
    <style>
        @page { margin: 20px 22px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .invoice { border: 1px solid #d9e0e8; }
        .top { height: 5px; background: #f97316; }
        .header { padding: 18px 20px 14px; border-bottom: 1px solid #e5e7eb; }
        .header-table,.meta,.info,.items,.delivery-head,.total-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: 0; padding: 0; vertical-align: top; }
        .brand { font-size: 24px; font-weight: 900; color: #071b4b; }
        .brand small { display: block; margin-top: 2px; color: #64748b; font-size: 9px; font-weight: 600; }
        .invoice-title { text-align: right; }
        .invoice-title strong { display: block; color: #071b4b; font-size: 22px; }
        .invoice-title span { color: #64748b; font-size: 9px; }
        .meta { margin-top: 14px; }
        .meta td { width: 25%; padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { display: block; margin-bottom: 3px; color: #64748b; font-size: 7.5px; font-weight: 800; text-transform: uppercase; }
        .value { color: #111827; font-size: 9px; font-weight: 800; }
        .content { padding: 15px 20px 18px; }
        .info { margin-bottom: 12px; }
        .info td { width: 50%; padding: 10px; border: 1px solid #e5e7eb; vertical-align: top; }
        .info h3 { margin: 0 0 6px; color: #071b4b; font-size: 11px; }
        .info p { margin: 2px 0; color: #475569; font-size: 8.5px; }
        .cod-note { margin-bottom: 14px; padding: 9px 10px; border: 1px solid #fed7aa; background: #fffaf5; color: #7c2d12; font-size: 8.5px; font-weight: 700; }
        .section-title { margin: 0 0 9px; color: #071b4b; font-size: 13px; font-weight: 900; }
        .delivery { margin-bottom: 13px; border: 1px solid #d9e0e8; page-break-inside: avoid; }
        .delivery-head { background: #f8fafc; }
        .delivery-head td { padding: 9px 10px; border: 0; border-bottom: 1px solid #e5e7eb; }
        .delivery-no { width: 34px; color: #fff; background: #071b4b; text-align: center; font-weight: 900; }
        .delivery-name strong { display: block; color: #111827; font-size: 10px; }
        .delivery-name span { color: #64748b; font-size: 8px; }
        .delivery-status { text-align: right; color: #075985; font-size: 8px; font-weight: 800; }
        .items th { padding: 7px 8px; background: #fff; color: #64748b; border-bottom: 1px solid #e5e7eb; font-size: 7.5px; text-align: left; text-transform: uppercase; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #edf2f7; font-size: 8.5px; }
        .items th:nth-child(n+2), .items td:nth-child(n+2) { text-align: right; }
        .items tr:last-child td { border-bottom: 0; }
        .cod-total { padding: 9px 10px; border-top: 1px solid #e5e7eb; background: #fffaf5; color: #7c2d12; text-align: right; }
        .cod-total strong { margin-left: 12px; color: #9a3412; font-size: 10px; }
        .total-table { margin-top: 14px; }
        .total-table td { padding: 11px 12px; background: #071b4b; color: #fff; font-size: 11px; font-weight: 900; }
        .total-table td:last-child { text-align: right; font-size: 14px; }
        .footer { padding: 12px 20px; border-top: 1px solid #e5e7eb; background: #f8fafc; color: #64748b; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
@inject('deliveryGroupService', 'App\Services\ClientDeliveryGroupService')
@inject('clientPaymentPresentation', 'App\Services\ClientPaymentPresentationService')
@php
    $deliveryData = $deliveryGroupService->summary($order);
    $deliveryGroups = $deliveryData['groups'];
    $isCashOnDelivery = \App\Services\PaymentService::normalizeMethod($order->payment_method)
        === \App\Services\PaymentService::METHOD_CASH_ON_DELIVERY;
    $invoiceNumber = $order->invoice_number ?: ('FAC-'.optional($order->created_at)->format('Y').'-'.str_pad($order->id, 6, '0', STR_PAD_LEFT));
@endphp

<div class="invoice">
    <div class="top"></div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td><div class="brand">OVANIE<small>Facture officielle de commande</small></div></td>
                <td class="invoice-title"><strong>FACTURE</strong><span>N° {{ $invoiceNumber }}</span></td>
            </tr>
        </table>
        <table class="meta">
            <tr>
                <td><span class="label">Commande</span><span class="value">#{{ $order->order_number }}</span></td>
                <td><span class="label">Date</span><span class="value">{{ $order->created_at?->format('d/m/Y H:i') }}</span></td>
                <td><span class="label">Paiement</span><span class="value">{{ $clientPaymentPresentation->methodLabel($order->payment_method) }}</span></td>
                <td><span class="label">Statut</span><span class="value">{{ $clientPaymentPresentation->statusLabel($order->payment_status) }}</span></td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="info">
            <tr>
                <td>
                    <h3>Client</h3>
                    <p><strong>{{ $order->delivery_recipient_name ?: $order->client?->name }}</strong></p>
                    <p>{{ $order->delivery_recipient_phone ?: $order->phone ?: '—' }}</p>
                    <p>{{ $order->client?->email ?: '—' }}</p>
                </td>
                <td>
                    <h3>Adresse de livraison</h3>
                    <p>{{ $order->delivery_address ?: $order->address ?: 'Adresse non renseignée' }}</p>
                    <p>{{ collect([$order->delivery_quartier, $order->delivery_commune, $order->delivery_city])->filter()->implode(', ') }}</p>
                </td>
            </tr>
        </table>

        @if($isCashOnDelivery)
            <div class="cod-note">Paiement à la réception : payez uniquement le montant indiqué pour la livraison effectivement reçue.</div>
        @endif

        <div class="section-title">Livraisons prévues ({{ $deliveryData['deliveries_count'] }})</div>

        @foreach($deliveryGroups as $group)
            <div class="delivery">
                <table class="delivery-head">
                    <tr>
                        <td class="delivery-no">{{ $group['number'] }}</td>
                        <td class="delivery-name"><strong>{{ $group['label'] }}</strong><span>{{ $group['item_count'] }} article(s) · {{ $group['line_count'] }} référence(s)</span></td>
                        <td class="delivery-status">{{ $group['status_label'] }}</td>
                    </tr>
                </table>

                <table class="items">
                    <thead><tr><th>Article</th><th>Prix unitaire</th><th>Quantité</th><th>Montant</th></tr></thead>
                    <tbody>
                        @foreach($group['items'] as $item)
                            @php $lineTotal = (float) ($item->subtotal ?: ((float) $item->price * (int) $item->quantity)); @endphp
                            <tr>
                                <td>{{ $item->product?->name ?: 'Produit indisponible' }}</td>
                                <td>{{ number_format($item->price, 0, ',', ' ') }} FCFA</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format($lineTotal, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($isCashOnDelivery)
                    <div class="cod-total">
                        {{ $group['is_paid'] ? 'Règlement reçu' : 'Montant à régler à la réception' }}
                        <strong>{{ number_format($group['is_paid'] ? $group['total'] : $group['amount_due'], 0, ',', ' ') }} FCFA</strong>
                    </div>
                @endif
            </div>
        @endforeach

        <table class="total-table"><tr><td>Montant total de la commande</td><td>{{ number_format($financialSummary['display_total'], 0, ',', ' ') }} FCFA</td></tr></table>
    </div>

    <div class="footer">Merci pour votre confiance. Assistance OVANIE : 01 61 78 18 18.</div>
</div>
</body>
</html>
