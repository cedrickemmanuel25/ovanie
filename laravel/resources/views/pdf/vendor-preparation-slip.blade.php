<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de préparation {{ $order['order_number'] ?? '' }}</title>
    <style>
        @page { margin: 18px 20px 22px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0a1b4f; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .bar { height: 16px; background: #0a2a66; }
        .head { margin-top: 24px; }
        .head td { vertical-align: top; padding: 0 14px; }
        .head img { height: 38px; }
        .tagline { margin-top: 3px; color: #51607f; font-size: 10.5px; }
        .title { text-align: right; }
        .title strong { display: block; font-size: 24px; line-height: 1.1; }
        .title span { display: block; margin-top: 6px; color: #51607f; font-size: 10.5px; }
        .box { border: 1px solid #c9d1df; border-radius: 4px; }
        .meta { margin-top: 20px; }
        .meta td { width: 25%; padding: 11px 16px; vertical-align: top; border-left: 1px solid #c9d1df; }
        .meta td.first { border-left: 0; }
        .label { display: block; color: #51607f; font-size: 8.5px; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-size: 12.5px; font-weight: bold; }
        .parties { margin-top: 12px; }
        .parties td { width: 50%; vertical-align: top; padding: 0; }
        .party { min-height: 96px; padding: 12px 14px; }
        .party h3 { margin: 0 0 6px; color: #51607f; font-size: 10px; }
        .party .name { margin: 0 0 6px; font-size: 14px; font-weight: bold; }
        .party p { margin: 2px 0; color: #24376b; font-size: 10.5px; }
        .party .hint { margin-top: 8px; color: #51607f; font-size: 9px; font-style: italic; }
        h2 { margin: 20px 0 9px; font-size: 16.5px; }
        .items th { padding: 9px 10px; background: #eef1f6; color: #51607f; font-size: 10px; text-align: center; border-left: 1px solid #c9d1df; }
        .items td { padding: 10px; font-size: 10.5px; vertical-align: middle; border-top: 1px solid #c9d1df; border-left: 1px solid #c9d1df; }
        .items th.first, .items td.first { border-left: 0; }
        .items th.left { text-align: left; }
        .items .check { width: 36px; text-align: center; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .mid { text-align: left; white-space: nowrap; }
        .items strong { font-size: 11px; }
        .tick { display: inline-block; width: 11px; height: 11px; border: 1.3px solid #24376b; border-radius: 2px; }
        .muted { color: #51607f; font-size: 9.5px; }
        .total { margin-top: 0; }
        .total td { padding: 11px 12px; font-size: 11px; }
        .total .cap { text-align: right; color: #51607f; font-weight: bold; }
        .total .sum { width: 118px; text-align: right; font-size: 14px; font-weight: bold; border-left: 1px solid #c9d1df; white-space: nowrap; }
        .note { margin-top: 14px; padding: 10px 12px; border: 1px solid #ff8a33; border-radius: 4px; background: #fff3e8; }
        .note td { padding: 0; vertical-align: middle; color: #e85400; font-size: 10.5px; }
        .note .dot { width: 34px; }
        .note .dot span { display: block; width: 26px; height: 26px; border-radius: 13px; background: #ff6a00; color: #fff; font-size: 16px; font-weight: bold; line-height: 16px; padding-top: 2px; text-align: center; }
        .sign { margin-top: 14px; }
        .sign td { width: 50%; padding: 0; vertical-align: top; }
        .signbox { height: 118px; padding: 10px 14px; color: #51607f; font-size: 9.5px; }
        .signbox b { color: #24376b; }
        .footer { margin-top: 26px; color: #51607f; font-size: 8.5px; text-align: center; }
    </style>
</head>
<body>
@php
    $items = $order['items'] ?? [];
    $tracking = $order['tracking'] ?? [];
    $address = $order['delivery_address'] ?? [];
    $ovanie = ($order['delivery_provider'] ?? '') === 'ovanie';
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';
    $units = collect($items)->sum(fn ($i) => (int) ($i['quantity'] ?? 0));
    $weight = collect($items)->sum(function ($i) {
        $kg = (float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', (string) ($i['weight_label'] ?? '0')));
        return $kg * (int) ($i['quantity'] ?? 1);
    });
    $pickup = trim((string) ($tracking['pickup_address'] ?? ''));
    if (str_contains($pickup, ' - ')) { $pickup = trim(explode(' - ', $pickup)[0]); }
    if ($pickup === '') { $pickup = trim((string) $shop->address); }
    $unit = function ($item) {
        $label = mb_strtolower(trim((string) ($item['unit_label'] ?? '')));
        $qty = (int) ($item['quantity'] ?? 0);
        return $qty > 1 && $label !== '' && ! str_contains($label, ' ') && ! str_ends_with($label, 's') ? $label.'s' : $label;
    };
    $phone = function ($raw) {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if (strlen($digits) === 13 && str_starts_with($digits, '225')) { $digits = substr($digits, 3); }
        return strlen($digits) === 10 ? trim(chunk_split($digits, 2, ' ')) : trim((string) $raw);
    };
    $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',');
    $created = ! empty($order['created_at']) ? \Illuminate\Support\Carbon::parse($order['created_at'])->timezone(config('app.timezone')) : null;
    $summary = count($items).' · '.$units.' unité'.($units > 1 ? 's' : '')
        .($weight > 0 ? ' · '.number_format($weight, $weight == floor($weight) ? 0 : 1, ',', ' ').' kg' : '');
    $logoPath = public_path('images/pdf/ovanie-logo.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<div class="bar"></div>

<table class="head"><tr>
    <td>
        @if($logo)<img src="{{ $logo }}" alt="OVANIE">@else<strong style="font-size: 26px;">OVANIE</strong>@endif
        <div class="tagline">Matériaux et équipements BTP</div>
    </td>
    <td class="title">
        <strong>Bon de préparation</strong>
        <span>Édité le {{ $generatedAt->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</span>
    </td>
</tr></table>

<div class="box meta"><table><tr>
    <td class="first"><span class="label">Commande</span><span class="value">{{ $order['order_number'] ?? '' }}</span></td>
    <td><span class="label">Date</span><span class="value">{{ $created?->format('d/m/Y H:i') }}</span></td>
    <td><span class="label">Paiement</span><span class="value">{{ $order['payment_method_label'] ?? '' }}</span></td>
    <td><span class="label">Livraison</span><span class="value">{{ $ovanie ? 'OVANIE Logistics' : 'Livraison vendeur' }}</span></td>
</tr></table></div>

<table class="parties"><tr>
    <td style="padding-right: 8px;">
        <div class="box party">
            <h3>Boutique</h3>
            <p class="name">{{ $shop->name }}</p>
            @if($shop->phone)<p>{{ $phone($shop->phone) }}</p>@endif
            @if($pickup !== '')<p>Collecte : {{ $pickup }}</p>@endif
        </div>
    </td>
    <td style="padding-left: 8px;">
        <div class="box party">
            @if($ovanie)
                <h3>OVANIE Logistics</h3>
                @if(!empty($tracking['mission_number']))<p>Mission : <strong>{{ $tracking['mission_number'] }}</strong></p>@endif
                @if(!empty($tracking['tracking_number']))<p>Suivi : <strong>{{ $tracking['tracking_number'] }}</strong></p>@endif
                <p>Destination : {{ $address['formatted'] ?? ($address['commune'] ?? '') }}</p>
                <p class="hint">Remettez le colis au livreur OVANIE après vérification du code de remise dans l’application.</p>
            @else
                <h3>Client</h3>
                <p class="name">{{ $address['recipient_name'] ?? ($order['client']['name'] ?? '') }}</p>
                @if(!empty($address['recipient_phone']) || !empty($order['client']['phone']))<p>{{ $phone($address['recipient_phone'] ?? $order['client']['phone']) }}</p>@endif
                @if(!empty($address['formatted']))<p>{{ $address['formatted'] }}</p>@endif
                @if(!empty($address['address']) && ! str_contains((string) ($address['formatted'] ?? ''), (string) $address['address']))<p>{{ $address['address'] }}</p>@endif
            @endif
        </div>
    </td>
</tr></table>

<h2>Articles à préparer ({{ $summary }})</h2>

<div class="box">
    <table class="items">
        <thead><tr>
            <th class="first check">&#10003;</th>
            <th class="left">Produit</th>
            <th>Qté</th>
            <th>Poids unit.</th>
            <th>Prix vendeur</th>
            <th>Total</th>
        </tr></thead>
        <tbody>
        @foreach($items as $item)
            @php
                $itemKg = (float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', (string) ($item['weight_label'] ?? '')));
            @endphp
            <tr>
                <td class="first check"><span class="tick"></span></td>
                <td>
                    <strong>{{ $item['name'] ?? '' }}</strong>
                    @if(!empty($item['variant_label']))<br><span class="muted">{{ $item['variant_label'] }}</span>@endif
                    <br><span class="muted">{{ collect([$item['brand'] ?? null, $item['category_name'] ?? null, isset($item['product_id']) ? 'Réf. '.$item['product_id'] : null])->filter()->implode(' · ') }}</span>
                </td>
                <td class="mid">{{ $item['quantity'] ?? 0 }} {{ $unit($item) }}</td>
                <td class="mid">{{ $itemKg > 0 ? $kg($itemKg).' kg' : '' }}</td>
                <td class="num">{{ $money($item['seller_unit_price'] ?? 0) }}</td>
                <td class="num">{{ $money($item['seller_subtotal'] ?? 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="box" style="margin-top: -1px;"><table class="total"><tr>
    <td class="cap">Montant vendeur des produits</td>
    <td class="sum">{{ $money($order['seller_products_total'] ?? 0) }}</td>
</tr></table></div>

<div class="note"><table><tr>
    <td class="dot"><span>!</span></td>
    <td>
        <strong>Vérifiez la quantité, l’état et l’emballage de chaque article avant la remise.</strong><br>
        Les montants indiqués sont ceux reversés à la boutique, hors commission <strong>OVANIE</strong>.
    </td>
</tr></table></div>

<table class="sign"><tr>
    <td style="padding-right: 8px;"><div class="box signbox"><b>Préparé par</b> (nom et signature)</div></td>
    <td style="padding-left: 8px;"><div class="box signbox"><b>{{ $ovanie ? 'Remis au livreur OVANIE' : 'Reçu par le client' }}</b> (nom et signature)</div></td>
</tr></table>

<div class="footer">Document généré depuis l’application OVANIE Vendeur · {{ $shop->name }} · {{ $order['order_number'] ?? '' }}</div>
</body>
</html>
