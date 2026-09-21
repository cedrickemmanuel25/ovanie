@extends('layouts.vendor')

@section('title', 'Détail de la commande | OVANIE')

@php
    use App\Services\OrderWorkflowService;

    $shopId = $shop->id ?? auth()->user()?->shop?->id;
    $shopItems = $order->items->where('shop_id', $shopId)->values();
    $currentItem = $shopItems->first();

    $providerOvanie = OrderWorkflowService::PROVIDER_OVANIE;
    $providerSeller = OrderWorkflowService::PROVIDER_SELLER;

    $ovanieLines = $shopItems->filter(fn ($line) => $line->delivery_provider === $providerOvanie)->values();
    $sellerLines = $shopItems->filter(fn ($line) => $line->delivery_provider === $providerSeller)->values();

    $hasOvanieDelivery = $ovanieLines->isNotEmpty();
    $hasSellerDelivery = $sellerLines->isNotEmpty();

    $allOvaniePreparationReady = $ovanieLines->isNotEmpty()
        && $ovanieLines->every(fn ($line) => $line->vendor_status === 'ready');
    $allOvanieTransferred = $ovanieLines->isNotEmpty()
        && $ovanieLines->every(fn ($line) => in_array($line->delivery_status, ['ready_for_pickup', 'assigned', 'picked_up', 'in_transit', 'delivered'], true));
    // 'ready_for_pickup' : en attente qu'OVANIE affecte un livreur.
    // 'assigned' : un livreur a été affecté MAIS n'est pas encore passé à la
    // boutique — la marchandise reste physiquement chez le vendeur jusqu'à ce
    // que le livreur confirme la collecte (picked_up). Le confondre avec un
    // enlèvement réel affichait "remise confirmée" avant même que le livreur
    // soit arrivé.
    $allOvanieAssigned = $ovanieLines->isNotEmpty()
        && $ovanieLines->every(fn ($line) => $line->delivery_status === 'assigned');
    $allOvaniePickedUp = $ovanieLines->isNotEmpty()
        && $ovanieLines->every(fn ($line) => in_array($line->delivery_status, ['picked_up', 'in_transit', 'delivered'], true));

    $allSellerPreparationReady = $sellerLines->isNotEmpty()
        && $sellerLines->every(fn ($line) => $line->vendor_status === 'ready');
    $allSellerInTransit = $sellerLines->isNotEmpty()
        && $sellerLines->every(fn ($line) => $line->delivery_status === OrderWorkflowService::DELIVERY_IN_TRANSIT);
    $allSellerDelivered = $sellerLines->isNotEmpty()
        && $sellerLines->every(fn ($line) => $line->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED);
    $sellerCurrentItem = $sellerLines->first();

    $vendorStatusLabels = [
        'pending' => 'À traiter',
        'accepted' => 'Acceptée',
        'preparing' => 'En préparation',
        'ready' => 'Prête',
        'shipped' => 'En livraison',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    $displayStatus = $vendorDisplayStatus ?? 'pending';
    $displayStatusLabel = $vendorStatusLabels[$displayStatus] ?? 'À traiter';

    $statusTone = [
        'pending' => 'warning',
        'accepted' => 'blue',
        'preparing' => 'orange',
        'ready' => 'blue',
        'shipped' => 'blue',
        'delivered' => 'green',
        'cancelled' => 'red',
    ][$displayStatus] ?? 'warning';

    $orderNumber = $order->order_number ?? ('OVANIE-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT));
    $orderDate = optional($order->created_at)->format('d/m/Y H:i') ?? '—';

    // Confidentialité logistique : le vendeur ne voit les données client/adresse et les frais
    // de livraison que lorsqu'il assure lui-même au moins une partie de la livraison.
    $shopDeliveryMode = data_get($shop, 'logistics_type') ?? data_get($shop, 'delivery_mode') ?? data_get($shop, 'logistics_mode');
    $shopUsesSellerDelivery = in_array($shopDeliveryMode, ['seller', 'seller_logistics', 'own', 'own_logistics'], true);
    $shopUsesOvanieDelivery = in_array($shopDeliveryMode, ['ovanie', 'ovanie_logistics'], true);

    $canViewCustomerDeliveryData = $hasSellerDelivery || (!$hasOvanieDelivery && $shopUsesSellerDelivery);
    $isOvanieOnlyDelivery = !$canViewCustomerDeliveryData && ($hasOvanieDelivery || $shopUsesOvanieDelivery);

    // En livraison mixte, seul le coût lié à la logistique vendeur est visible côté vendeur.
    $sellerDeliveryFee = (float) ($sellerDeliveryFee
        ?? $sellerLines->sum(fn ($line) => (float) ($line->delivery_price ?? 0)));
    $ovanieDeliveryFee = (float) ($ovanieDeliveryFee
        ?? $ovanieLines->sum(fn ($line) => (float) ($line->delivery_price ?? 0)));
    $vendorDeliveryFee = $canViewCustomerDeliveryData ? $sellerDeliveryFee : 0.0;

    $vendorLineBreakdowns = collect($vendorLineBreakdowns ?? []);
    $vendorPublicSubtotal = (float) ($vendorPublicSubtotal
        ?? $vendorSubtotal
        ?? $shopItems->sum(fn ($line) => (float) ($line->subtotal ?? ((float) $line->price * (int) $line->quantity))));
    $vendorCommission = (float) ($vendorCommission ?? 0);
    $vendorSellerSubtotal = (float) ($vendorSellerSubtotal
        ?? $vendorNetProducts
        ?? $vendorNet
        ?? max($vendorPublicSubtotal - $vendorCommission, 0));

    // Compatibilité avec les sections historiques de la vue.
    $vendorSubtotal = $vendorPublicSubtotal;
    $vendorNetProducts = $vendorSellerSubtotal;
    $vendorNet = $vendorSellerSubtotal;
    $vendorPayoutTotal = (float) ($vendorPayoutTotal
        ?? max(0, $vendorSellerSubtotal + $sellerDeliveryFee));
    $vendorClientGroupTotal = $vendorPublicSubtotal + $vendorDeliveryFee;
    $vendorTotalToDisplay = $vendorClientGroupTotal;
    $totalQuantity = (int) $shopItems->sum('quantity');
    $totalWeight = (float) $shopItems->sum(fn ($line) => (float) ($line->logistics_weight_kg ?? 0));
    $totalVolume = (float) $shopItems->sum(fn ($line) => (float) ($line->logistics_volume_m3 ?? 0));

    $client = $order->client;
    $clientName = $client->name ?? $order->customer_name ?? 'Client OVANIE';
    $clientPhone = $order->delivery_recipient_phone ?? $client->phone ?? $order->phone ?? null;
    $clientEmail = $client->email ?? null;
    $whatsappPhone = $client?->whatsapp_phone ?? $clientPhone;

    $addressLines = array_filter([
        $order->delivery_address ?? $order->address ?? null,
        trim(collect([$order->delivery_commune, $order->delivery_quartier])->filter()->implode(' - ')),
        $order->delivery_city ?: null,
        $order->delivery_site_name ? 'Site : ' . $order->delivery_site_name : null,
        $order->delivery_note ? 'Instruction : ' . $order->delivery_note : null,
    ]);

    $paymentMethodLabel = match ($order->payment_method) {
        'cash', 'cod', 'cash_on_delivery', 'pay_on_delivery' => 'Paiement à la livraison',
        'paydunya', 'online', 'online_payment', 'mobile_money' => 'PayOVANIE',
        'orange_money' => 'Orange Money',
        'mtn_money', 'mtn_momo' => 'MTN Mobile Money',
        'moov_money' => 'Moov Money',
        'wave' => 'Wave',
        'card', 'credit_card' => 'Carte bancaire',
        'bank_transfer' => 'Virement bancaire',
        default => $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : 'Non renseigné',
    };

    $paymentStatusLabel = match ($order->payment_status) {
        'paid', 'escrow_held' => 'Payé',
        'pending' => 'En attente',
        'failed' => 'Échec',
        'refunded' => 'Remboursé',
        default => $order->payment_status ? ucfirst(str_replace('_', ' ', $order->payment_status)) : 'Non confirmé',
    };

    $paymentTone = in_array($order->payment_status, ['paid', 'escrow_held'], true) ? 'green' : ($order->payment_status === 'failed' ? 'red' : 'warning');
    $latestPayment = $order->payments?->sortByDesc('created_at')->first();

    $preparationTargets = collect($nextPreparationActions ?? [])->unique()->values();
    $singlePreparationTarget = $preparationTargets->count() === 1 ? $preparationTargets->first() : null;
    $preparationLabels = [
        'accepted' => 'Accepter la commande',
        'preparing' => 'Démarrer la préparation',
        'ready' => 'Marquer prête',
    ];

    $sellerOtpRequired = $hasSellerDelivery
        && $sellerLines->isNotEmpty()
        && $sellerLines->every(fn ($line) => $line->delivery_status === OrderWorkflowService::DELIVERY_IN_TRANSIT)
        && $sellerLines->contains(fn ($line) => $line->delivery_otp_verified_at === null);

    // Le vendeur ne pilote jamais le trajet du livreur OVANIE (GPS, étapes de
    // déplacement, etc.) : cet espace n'est pas un centre de contrôle
    // logistique. Une commande purement OVANIE Logistics s'arrête donc à la
    // remise au service logistique, suivie d'un simple bloc de statut. Une
    // commande (même partiellement) en logistique vendeur garde une frise
    // plus détaillée jusqu'à "Livrée", nécessaire à la traçabilité commerciale
    // (preuve en cas de réclamation client).
    $ovanieIncidentStatuses = [
        OrderWorkflowService::DELIVERY_FAILED_STATUS,
        OrderWorkflowService::DELIVERY_FAILED,
        OrderWorkflowService::DELIVERY_RETURNED,
    ];
    $ovanieIncident = $ovanieLines->contains(fn ($line) => in_array($line->delivery_status, $ovanieIncidentStatuses, true));
    $ovanieDelivered = $ovanieLines->isNotEmpty()
        && $ovanieLines->every(fn ($line) => $line->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED);

    if ($hasSellerDelivery) {
        $timelineSteps = [
            [
                'key' => 'received',
                'label' => 'Commande reçue',
                'icon' => 'inbox',
                'date' => $orderDate,
                'done' => true,
                'active' => $displayStatus === 'pending',
            ],
            [
                'key' => 'preparing',
                'label' => 'En préparation',
                'icon' => 'package-open',
                'date' => optional($shopItems->pluck('vendor_status_updated_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($displayStatus === 'preparing' ? 'En cours' : '—'),
                'done' => in_array($displayStatus, ['preparing', 'ready', 'shipped', 'delivered'], true),
                'active' => $displayStatus === 'preparing',
            ],
            [
                'key' => 'ready',
                'label' => 'Prête',
                'icon' => 'package-check',
                'date' => optional($shopItems->pluck('vendor_prepared_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($displayStatus === 'ready' ? 'Aujourd’hui' : '—'),
                'done' => in_array($displayStatus, ['ready', 'shipped', 'delivered'], true),
                'active' => $displayStatus === 'ready' && ! $allSellerInTransit && ! $allSellerDelivered,
            ],
            [
                'key' => 'handed_to_driver',
                'label' => 'Remise au livreur',
                'icon' => 'user-check',
                'date' => optional($sellerLines->pluck('vendor_shipment_date')->filter()->sort()->last())->format('d/m/Y') ?: (($allSellerInTransit || $allSellerDelivered) ? 'Confirmée' : '—'),
                'done' => $allSellerInTransit || $allSellerDelivered,
                'active' => $allSellerPreparationReady && ! $allSellerInTransit && ! $allSellerDelivered,
            ],
            [
                'key' => 'shipped',
                'label' => 'En livraison',
                'icon' => 'truck',
                'date' => $allSellerInTransit ? 'En cours' : ($allSellerDelivered ? 'Terminée' : '—'),
                'done' => $allSellerInTransit || $allSellerDelivered,
                'active' => $allSellerInTransit && ! $allSellerDelivered,
            ],
            [
                'key' => 'delivered',
                'label' => 'Livrée',
                'icon' => 'check-circle-2',
                'date' => optional($sellerLines->pluck('delivery_completed_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($allSellerDelivered ? 'Confirmée' : '—'),
                'done' => $allSellerDelivered,
                'active' => $allSellerDelivered,
            ],
        ];
    } else {
        $timelineSteps = [
            [
                'key' => 'received',
                'label' => 'Commande reçue',
                'icon' => 'inbox',
                'date' => $orderDate,
                'done' => true,
                'active' => $displayStatus === 'pending',
            ],
            [
                'key' => 'preparing',
                'label' => 'En préparation',
                'icon' => 'package-open',
                'date' => optional($shopItems->pluck('vendor_status_updated_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($displayStatus === 'preparing' ? 'En cours' : '—'),
                'done' => in_array($displayStatus, ['preparing', 'ready', 'shipped', 'delivered'], true),
                'active' => $displayStatus === 'preparing',
            ],
            [
                'key' => 'ready',
                'label' => 'Prête',
                'icon' => 'package-check',
                'date' => optional($shopItems->pluck('vendor_prepared_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($displayStatus === 'ready' ? 'Aujourd’hui' : '—'),
                'done' => in_array($displayStatus, ['ready', 'shipped', 'delivered'], true),
                'active' => $displayStatus === 'ready' && ! $allOvaniePickedUp,
            ],
            [
                'key' => 'handed_over',
                'label' => 'Remise à OVANIE Logistics',
                'icon' => 'truck',
                'date' => optional($ovanieLines->pluck('delivery_status_updated_at')->filter()->sort()->last())->format('d/m/Y H:i') ?: ($allOvaniePickedUp ? 'Confirmée' : ($allOvanieAssigned ? 'Livreur affecté' : ($allOvanieTransferred ? 'En attente d’enlèvement' : '—'))),
                'done' => $allOvaniePickedUp,
                'active' => ! $allOvaniePickedUp && ($allOvanieTransferred || $displayStatus === 'shipped'),
            ],
        ];
    }

    $deliveryModeLabel = $hasOvanieDelivery && $hasSellerDelivery
        ? 'Livraison mixte'
        : ($hasSellerDelivery ? 'Logistique vendeur' : ($hasOvanieDelivery ? 'OVANIE Logistics' : ($order->delivery_provider_label ?? 'À définir')));

    $deliveryDelay = $shopItems->pluck('delivery_delay')->filter()->unique()->values()->implode(' / ') ?: ($order->delivery_min_date && $order->delivery_max_date ? optional($order->delivery_min_date)->format('d/m') . ' - ' . optional($order->delivery_max_date)->format('d/m') : 'À confirmer');
@endphp

@section('styles')
<style>
    .order-detail-page {
        --od-navy: #08245c;
        --od-blue: #0d5ee8;
        --od-orange: #ff5a0a;
        --od-green: #12a150;
        --od-red: #ef4444;
        --od-text: #112b5f;
        --od-muted: #63769b;
        --od-border: #dbe4f0;
        --od-soft: #f7f9fc;
        width: 100%;
        max-width: 1560px;
        margin: 0 auto;
        color: var(--od-text);
        padding: 24px 30px 34px;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .order-detail-page * { box-sizing: border-box; }

    .od-breadcrumb {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 22px;
        color: #6f82a4;
        font-size: 12px;
        font-weight: 800;
    }

    .od-breadcrumb a {
        color: var(--od-blue);
        text-decoration: none;
    }

    .od-breadcrumb i { width: 14px; height: 14px; color: #8ca0bf; }

    .od-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 18px;
    }

    .od-title-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .od-title-row h1 {
        margin: 0;
        color: var(--od-navy);
        font-size: clamp(27px, 2.2vw, 36px);
        line-height: 1.05;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .od-copy-btn {
        width: 34px;
        height: 34px;
        display: inline-grid;
        place-items: center;
        border: 1px solid #dce4ef;
        border-radius: 8px;
        background: #f7f9fc;
        color: #536b93;
        cursor: pointer;
    }

    .od-copy-btn i { width: 17px; height: 17px; }

    .od-subtitle {
        margin: 9px 0 0;
        color: #5c6e91;
        font-size: 13px;
        font-weight: 600;
    }

    .od-status-badge {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 0 22px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 950;
        white-space: nowrap;
    }

    .od-status-badge.warning,
    .od-status-badge.orange {
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #f05a0a;
    }

    .od-status-badge.blue {
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #0d5ee8;
    }

    .od-status-badge.green {
        border: 1px solid #bbf7d0;
        background: #ecfdf5;
        color: #07964b;
    }

    .od-status-badge.red {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #dc2626;
    }

    .od-status-badge i { width: 20px; height: 20px; }

    .od-main-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: start;
    }

    /* Les conteneurs intermédiaires ne créent plus de colonnes inutiles. */
    .od-left,
    .od-content-grid,
    .od-info-grid,
    .od-workflow,
    .od-right {
        display: contents;
    }

    .od-left, .od-right { min-width: 0; }

    .od-timeline-card,
    .od-products-card,
    .od-history,
    .od-full-span {
        grid-column: 1 / -1;
    }

    .od-card {
        border: 1px solid var(--od-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(18, 43, 92, .045);
        overflow: hidden;
    }

    .od-card + .od-card { margin-top: 0; }

    .od-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 18px 20px 14px;
    }

    .od-card-title {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        color: var(--od-navy);
        font-size: 16px;
        font-weight: 950;
        letter-spacing: -0.02em;
    }

    .od-card-title i { width: 20px; height: 20px; color: var(--od-blue); }

    .od-card-body { padding: 0 20px 18px; }

    .od-timeline-card { margin-bottom: 14px; }

    .od-timeline {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        padding: 26px 24px 22px;
    }

    .od-step {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-width: 0;
    }

    .od-step:not(:last-child)::after {
        content: "";
        position: absolute;
        left: calc(50% + 25px);
        right: calc(-50% + 25px);
        top: 22px;
        border-top: 2px dashed #cdd7e6;
    }

    .od-step.done:not(:last-child)::after { border-color: #f59e0b; }
    .od-step.done.green-line:not(:last-child)::after { border-color: #16a34a; }

    .od-step-icon {
        position: relative;
        z-index: 1;
        width: 46px;
        height: 46px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #f0f4fa;
        color: #5e7192;
    }

    .od-step-icon i { width: 20px; height: 20px; }

    .od-step.done .od-step-icon {
        background: #ff9800;
        color: #fff;
    }

    .od-step.active .od-step-icon {
        background: #fff7ed;
        color: var(--od-orange);
        border: 2px solid #ffb167;
    }

    .od-step.final.done .od-step-icon,
    .od-step.delivered.done .od-step-icon {
        background: #16a34a;
    }

    .od-step-label {
        display: block;
        margin-top: 13px;
        color: var(--od-navy);
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .od-step-date {
        display: block;
        margin-top: 6px;
        color: #5f7395;
        font-size: 11px;
        font-weight: 600;
        min-height: 14px;
    }

    .od-content-grid,
    .od-info-grid {
        display: contents;
    }

    .od-content-grid > .od-card .od-profile-list {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px 28px;
    }

    .od-privacy-note {
        display: none;
    }

    .od-info-grid .od-card { margin-top: 0; }

    .od-table-wrap {
        width: 100%;
        overflow: visible;
    }

    .od-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .od-table th {
        padding: 14px 16px;
        border-top: 1px solid #e8eef6;
        border-bottom: 1px solid #e8eef6;
        background: #fbfcfe;
        color: #53688c;
        font-size: 11px;
        font-weight: 900;
        text-align: left;
    }

    .od-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #edf2f7;
        color: #162f63;
        font-size: 12px;
        vertical-align: middle;
    }

    .od-table tbody tr:last-child td { border-bottom: 0; }

    .od-product-cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .od-product-img {
        width: 45px;
        height: 45px;
        flex: 0 0 45px;
        object-fit: cover;
        border: 1px solid #e1e8f2;
        border-radius: 5px;
        background: #f3f6fb;
    }

    .od-product-placeholder {
        width: 45px;
        height: 45px;
        flex: 0 0 45px;
        display: grid;
        place-items: center;
        border: 1px solid #e1e8f2;
        border-radius: 5px;
        background: #f3f6fb;
        color: #7e91b0;
    }

    .od-product-name {
        display: block;
        color: var(--od-navy);
        font-size: 13px;
        font-weight: 950;
        line-height: 1.35;
        overflow: visible;
        text-overflow: clip;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .od-money-cell {
        white-space: nowrap;
        font-weight: 900;
        color: var(--od-navy);
    }

    .od-money-cell.primary {
        color: var(--od-blue);
    }

    .od-product-sku {
        display: block;
        margin-top: 5px;
        color: #667a9d;
        font-size: 11px;
        font-weight: 600;
    }

    .od-totals {
        padding: 0 20px 18px;
    }

    .od-total-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 18px;
        padding: 12px 0;
        border-top: 1px solid #edf2f7;
        color: #243d6c;
        font-size: 13px;
        font-weight: 800;
    }

    .od-total-row small {
        display: block;
        margin-top: 3px;
        color: #7183a2;
        font-size: 11px;
        font-weight: 650;
    }

    .od-total-row strong {
        color: var(--od-navy);
        white-space: nowrap;
        text-align: right;
    }

    .od-total-row.grand {
        padding-top: 15px;
        font-size: 15px;
    }

    .od-total-row.grand strong {
        color: #0d5ee8;
        font-size: 20px;
    }

    .od-profile-list {
        display: grid;
        gap: 18px;
        padding: 0 20px 20px;
    }

    .od-label {
        display: block;
        margin-bottom: 6px;
        color: #6d7f9d;
        font-size: 11px;
        font-weight: 800;
    }

    .od-value {
        color: var(--od-navy);
        font-size: 13px;
        font-weight: 850;
        line-height: 1.45;
        word-break: break-word;
    }

    .od-value.muted { color: #6d7f9d; font-weight: 650; }

    .od-whatsapp {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
        color: #15a95b;
        text-decoration: none;
    }

    .od-whatsapp i { width: 15px; height: 15px; }

    .od-data-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 24px;
        padding: 0 20px 18px;
    }

    .od-side-stack { display: grid; gap: 14px; }

    .od-main-grid > .od-card,
    .od-main-grid section.od-card {
        height: 100%;
        margin-top: 0;
    }

    .od-actions {
        display: grid;
        gap: 10px;
        padding: 0 20px 20px;
    }

    .od-btn {
        min-height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 0 16px;
        border: 1px solid #d2ddeb;
        border-radius: 6px;
        background: #fff;
        color: var(--od-navy);
        font: inherit;
        font-size: 12px;
        font-weight: 950;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
    }

    .od-btn:hover { border-color: #afc1dc; transform: translateY(-1px); }
    .od-btn i { width: 16px; height: 16px; }
    .od-btn.blue { border-color: var(--od-blue); background: var(--od-blue); color: #fff; }
    .od-btn.navy { border-color: #08245c; background: #08245c; color: #fff; }
    .od-btn.orange { border-color: var(--od-orange); background: linear-gradient(90deg, #ff5a0a, #ff6b13); color: #fff; }
    .od-btn.red { border-color: #fca5a5; background: #fff; color: #dc2626; }
    .od-btn.green { border-color: #16a34a; background: #16a34a; color: #fff; }
    .od-btn.light { background: #f8fafc; }
    .od-btn:disabled { opacity: .58; cursor: not-allowed; transform: none; }

    .od-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 23px;
        padding: 0 9px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 950;
    }

    .od-badge.green { background: #dcfce7; color: #15803d; }
    .od-badge.warning { background: #fff7ed; color: #ea580c; }
    .od-badge.blue { background: #e8f1ff; color: #0d5ee8; }
    .od-badge.red { background: #fee2e2; color: #dc2626; }
    .od-badge.gray { background: #f1f5f9; color: #64748b; }

    .od-finance-list {
        padding: 0 20px 18px;
        display: grid;
        gap: 12px;
    }

    .od-finance-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        color: #334d7a;
        font-size: 12px;
        font-weight: 750;
    }

    .od-finance-row strong { color: var(--od-navy); }
    .od-finance-row.net strong { color: #07964b; }

    .od-workflow {
        display: contents;
    }

    .od-alert {
        display: flex;
        gap: 12px;
        padding: 14px;
        border-radius: 8px;
        font-size: 12px;
        line-height: 1.55;
        font-weight: 650;
    }

    .od-alert i { width: 20px; height: 20px; flex: 0 0 20px; }
    .od-alert.blue { border: 1px solid #bfdbfe; background: #eff6ff; color: #17427e; }
    .od-alert.orange { border: 1px solid #fed7aa; background: #fff7ed; color: #9a3412; }
    .od-alert.green { border: 1px solid #bbf7d0; background: #ecfdf5; color: #166534; }
    .od-alert.red { border: 1px solid #fecaca; background: #fff1f2; color: #991b1b; }

    .od-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .od-field { display: grid; gap: 6px; }

    .od-field label {
        color: #1e3768;
        font-size: 11px;
        font-weight: 900;
    }

    .od-input, .od-select, .od-textarea {
        width: 100%;
        border: 1px solid #cbd7e6;
        border-radius: 7px;
        background: #fff;
        color: var(--od-navy);
        font: inherit;
        font-size: 12px;
        outline: none;
        transition: .18s ease;
    }

    .od-input, .od-select { height: 42px; padding: 0 12px; }
    .od-textarea { min-height: 78px; padding: 12px; resize: vertical; }

    .od-input:focus, .od-select:focus, .od-textarea:focus {
        border-color: var(--od-blue);
        box-shadow: 0 0 0 3px rgba(13, 94, 232, .09);
    }

    .od-file {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px dashed #b9c7db;
        border-radius: 7px;
        background: #f8fafc;
        color: #536b93;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .od-file input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .od-file-name { display: block; margin-top: 5px; color: #07964b; font-size: 11px; font-weight: 800; }

    .od-history {
        margin-top: 14px;
        padding: 16px 20px;
    }

    .od-history-list { display: grid; gap: 0; }

    .od-history-row {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr) auto;
        gap: 18px;
        position: relative;
        padding: 0 0 18px 27px;
        border-left: 1px solid #dbe4f0;
    }

    .od-history-row:last-child { border-left-color: transparent; padding-bottom: 0; }

    .od-history-dot {
        position: absolute;
        left: -7px;
        top: 0;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--od-blue);
        box-shadow: 0 0 0 5px #eff6ff;
    }

    .od-history-row:first-child .od-history-dot { background: #ff9800; box-shadow: 0 0 0 5px #fff7ed; }

    .od-history-date {
        color: #40577d;
        font-size: 12px;
        font-weight: 800;
    }

    .od-history-title {
        margin: 0 0 4px;
        color: var(--od-navy);
        font-size: 13px;
        font-weight: 950;
    }

    .od-history-text {
        margin: 0;
        color: #657897;
        font-size: 12px;
        font-weight: 600;
    }

    .od-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 9px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #536b93;
        font-size: 10px;
        font-weight: 900;
    }

    .od-print-only { display: none; }

    @media (max-width: 1180px) {
        .od-main-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .od-timeline-card,
        .od-products-card,
        .od-history,
        .od-full-span { grid-column: 1 / -1; }
    }

    @media (max-width: 980px) {
        .order-detail-page { padding: 16px; }
        .od-header { flex-direction: column; }
        .od-main-grid { grid-template-columns: 1fr; }
        .od-content-grid > .od-card .od-profile-list { grid-template-columns: 1fr; }
        .od-timeline { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 24px; }
        .od-step:nth-child(3)::after { display: none; }
    }

    @media (max-width: 720px) {
        .od-table-wrap { overflow: visible; }
        .od-table, .od-table tbody, .od-table tr, .od-table td { display: block; width: 100%; }
        .od-table thead { display: none; }
        .od-table tbody { padding: 0 14px 14px; }
        .od-table tr {
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #e3eaf4;
            border-radius: 10px;
            background: #fff;
        }
        .od-table td {
            display: grid;
            grid-template-columns: minmax(120px, .8fr) minmax(0, 1fr);
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #edf2f7;
            text-align: right !important;
        }
        .od-table td:last-child { border-bottom: 0; }
        .od-table td::before {
            content: attr(data-label);
            color: #64779a;
            font-size: 11px;
            font-weight: 900;
            text-align: left;
        }
        .od-table td:first-child {
            grid-template-columns: 1fr;
            text-align: left !important;
        }
        .od-table td:first-child::before { display: none; }
        .od-product-cell { align-items: flex-start; }
        .od-money-cell { white-space: normal; }
        .od-data-grid, .od-form-grid { grid-template-columns: 1fr; }
        .od-history-row { grid-template-columns: 1fr; gap: 6px; }
        .od-timeline { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .od-step:nth-child(even)::after { display: none; }
    }

    @media print {
        .ov-sidebar, .ov-topbar, .od-actions, .od-btn, .od-breadcrumb, .od-status-badge { display: none !important; }
        .order-detail-page { padding: 0; }
        .od-main-grid { display: block; }
        .od-left, .od-content-grid, .od-info-grid, .od-workflow, .od-right { display: block; }
        .od-card { break-inside: avoid; box-shadow: none; }
        .od-print-only { display: block; }
    }

    .od-mission-card{display:grid;gap:14px;padding:16px;border:1px solid #d9e3f1;border-radius:14px;background:#fff;box-shadow:0 10px 28px rgba(15,35,70,.05)}
    .od-mission-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
    .od-mission-head h3{margin:4px 0 0;color:#0b2b63;font-size:18px;line-height:1.25}
    .od-mission-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .od-mission-grid>div{padding:12px;border:1px solid #e4eaf3;border-radius:10px;background:#f8fafc;min-width:0}
    .od-mission-grid span{display:block;margin-bottom:4px;color:#71809a;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.035em}
    .od-mission-grid strong{display:block;color:#10254b;font-size:14px;overflow-wrap:anywhere}
    .od-mission-actions{display:flex;gap:10px;flex-wrap:wrap}
    @media(max-width:760px){.od-mission-grid{grid-template-columns:1fr}.od-mission-actions .od-btn{width:100%}}
</style>
@endsection

@section('content')
<main class="order-detail-page">
    <div class="od-breadcrumb">
        <a href="{{ route('vendor.orders') }}">Commandes</a>
        <i data-lucide="chevron-right"></i>
        <span>Détail de la commande</span>
    </div>

    @if(session('success') || session('warning') || session('error') || $errors->any())
        <div style="display:grid;gap:10px;margin-bottom:14px;">
            @if(session('success'))
                <div class="od-alert green"><i data-lucide="check-circle-2"></i><div>{{ session('success') }}</div></div>
            @endif
            @if(session('warning'))
                <div class="od-alert orange"><i data-lucide="alert-triangle"></i><div>{{ session('warning') }}</div></div>
            @endif
            @if(session('error'))
                <div class="od-alert red"><i data-lucide="x-circle"></i><div>{{ session('error') }}</div></div>
            @endif
            @if($errors->any())
                <div class="od-alert red">
                    <i data-lucide="alert-triangle"></i>
                    <div>
                        <strong>Veuillez corriger les informations suivantes :</strong>
                        <ul style="margin:6px 0 0;padding-left:18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <section class="od-header">
        <div>
            <div class="od-title-row">
                <h1>Commande #{{ $orderNumber }}</h1>
                <button type="button" class="od-copy-btn" data-copy="{{ $orderNumber }}" aria-label="Copier le numéro de commande">
                    <i data-lucide="copy"></i>
                </button>
            </div>
            <p class="od-subtitle">Passée le {{ $orderDate }}</p>
        </div>

        <span class="od-status-badge {{ $statusTone }}">
            <i data-lucide="{{ $displayStatus === 'delivered' ? 'check-circle-2' : ($displayStatus === 'cancelled' ? 'x-circle' : 'clock-3') }}"></i>
            {{ $displayStatusLabel }}
        </span>
    </section>

    <div class="od-main-grid">
        <div class="od-left">
            <section class="od-card od-timeline-card">
                <div class="od-timeline">
                    @foreach($timelineSteps as $step)
                        <div class="od-step {{ $step['done'] ? 'done' : '' }} {{ $step['active'] ? 'active' : '' }} {{ $step['key'] === 'delivered' ? 'delivered' : '' }} {{ $step['key'] === 'closed' ? 'final' : '' }} {{ $displayStatus === 'delivered' ? 'green-line' : '' }}">
                            <span class="od-step-icon"><i data-lucide="{{ $step['icon'] }}"></i></span>
                            <span class="od-step-label">{{ $step['label'] }}</span>
                            <span class="od-step-date">{{ $step['date'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            @if(!$hasSellerDelivery && $hasOvanieDelivery && $allOvanieTransferred)
                <section class="od-card od-full-span" style="margin-bottom:14px;">
                    @if($ovanieIncident)
                        <div class="od-alert red">
                            <i data-lucide="alert-triangle"></i>
                            <div>
                                <strong>Incident de livraison</strong><br>
                                OVANIE Logistics a signalé un incident sur cette commande. Notre équipe vous contactera si une action de votre part est nécessaire.
                            </div>
                        </div>
                    @elseif($ovanieDelivered)
                        <div class="od-alert green">
                            <i data-lucide="check-circle-2"></i>
                            <div>
                                <strong>✅ Commande livrée</strong><br>
                                Livraison confirmée par OVANIE Logistics.
                            </div>
                        </div>
                    @elseif($allOvaniePickedUp)
                        <div class="od-alert blue">
                            <i data-lucide="truck"></i>
                            <div>
                                <strong>Livraison prise en charge par OVANIE Logistics</strong><br>
                                Votre commande a été récupérée par le service logistique.<br>
                                Statut : Livraison en cours<br>
                                Vous serez informé lorsque la livraison sera terminée.
                            </div>
                        </div>
                    @elseif($allOvanieAssigned)
                        <div class="od-alert orange">
                            <i data-lucide="truck"></i>
                            <div>
                                <strong>Livreur affecté par OVANIE Logistics</strong><br>
                                Un livreur a été désigné et se dirige vers votre boutique pour récupérer la commande.<br>
                                Statut : En route pour la collecte — pas encore récupérée<br>
                                Vous serez informé dès que le livreur aura la commande.
                            </div>
                        </div>
                    @else
                        <div class="od-alert orange">
                            <i data-lucide="clock"></i>
                            <div>
                                <strong>En attente d’enlèvement par OVANIE Logistics</strong><br>
                                Votre commande est prête et visible par le service logistique.<br>
                                Statut : En attente qu’un livreur OVANIE vienne la récupérer<br>
                                Vous serez informé dès que la commande sera prise en charge.
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            <div class="od-content-grid {{ !$canViewCustomerDeliveryData ? 'single' : '' }}">
                <section class="od-card od-products-card">
                    <div class="od-card-head">
                        <h2 class="od-card-title">Produits commandés</h2>
                    </div>

                    <div class="od-table-wrap">
                        <table class="od-table">
                            <thead>
                                <tr>
                                    <th style="width:50%;">Produit</th>
                                    <th style="width:12%;text-align:center;">Quantité</th>
                                    <th style="width:19%;">Prix unitaire</th>
                                    <th style="width:19%;">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shopItems as $item)
                                    @php
                                        $product = $item->product;
                                        $productName = $product?->name ?? 'Produit supprimé ou archivé';
                                        $productSku = $product?->sku ?? $product?->reference ?? ('Ligne #' . $item->id);
                                        $lineFinance = $vendorLineBreakdowns->get($item->id, []);
                                        $sellerUnitPrice = (float) ($lineFinance['seller_unit_price'] ?? 0);
                                        $sellerLineTotal = (float) ($lineFinance['seller_subtotal'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td data-label="Produit">
                                            <div class="od-product-cell">
                                                @if($product?->main_image_url)
                                                    <img src="{{ $product->main_image_url }}" alt="{{ $productName }}" class="od-product-img">
                                                @elseif($product?->image)
                                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $productName }}" class="od-product-img">
                                                @else
                                                    <span class="od-product-placeholder"><i data-lucide="package"></i></span>
                                                @endif
                                                <span style="min-width:0;">
                                                    <span class="od-product-name">{{ $productName }}</span>
                                                    <span class="od-product-sku">Référence : {{ $productSku }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="Quantité" style="text-align:center;font-weight:950;">{{ $item->quantity }}</td>
                                        <td data-label="Prix unitaire" class="od-money-cell">
                                            {{ number_format($sellerUnitPrice, 0, ',', ' ') }} FCFA
                                        </td>
                                        <td data-label="Montant" class="od-money-cell primary">
                                            {{ number_format($sellerLineTotal, 0, ',', ' ') }} FCFA
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align:center;padding:32px;color:#7c8daa;">Aucun produit visible pour votre boutique.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="od-totals">
                        <div class="od-total-row">
                            <span>Total produits</span>
                            <strong>{{ number_format($vendorSellerSubtotal, 0, ',', ' ') }} FCFA</strong>
                        </div>

                        @if($canViewCustomerDeliveryData)
                            <div class="od-total-row">
                                <span>Frais de livraison</span>
                                <strong>+{{ number_format($vendorDeliveryFee, 0, ',', ' ') }} FCFA</strong>
                            </div>
                            <div class="od-total-row grand">
                                <span>Montant total</span>
                                <strong>{{ number_format($vendorPayoutTotal, 0, ',', ' ') }} FCFA</strong>
                            </div>
                        @else
                            <div class="od-total-row grand">
                                <span>Montant total</span>
                                <strong>{{ number_format($vendorSellerSubtotal, 0, ',', ' ') }} FCFA</strong>
                            </div>
                        @endif
                    </div>
                </section>

                @if($canViewCustomerDeliveryData)
                <section class="od-card">
                    <div class="od-card-head">
                        <h2 class="od-card-title"><i data-lucide="user-round"></i> Client</h2>
                    </div>
                    <div class="od-profile-list">
                        <div>
                            <span class="od-label">Nom</span>
                            <div class="od-value">{{ $clientName }}</div>
                        </div>
                        <div>
                            <span class="od-label">Téléphone</span>
                            <div class="od-value">
                                {{ $clientPhone ?? '—' }}
                                @if($whatsappPhone)
                                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsappPhone) }}" target="_blank" rel="noopener" class="od-whatsapp" title="Contacter sur WhatsApp">
                                        <i data-lucide="message-circle"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div>
                            <span class="od-label">Email</span>
                            <div class="od-value">{{ $clientEmail ?? '—' }}</div>
                        </div>
                    </div>
                </section>
                @endif
            </div>

            @if($canViewCustomerDeliveryData)
                <div class="od-info-grid">
                    <section class="od-card">
                        <div class="od-card-head">
                            <h2 class="od-card-title"><i data-lucide="map-pin"></i> Adresse de livraison</h2>
                        </div>
                        <div class="od-data-grid">
                            <div style="grid-column:1/-1;">
                                <span class="od-label">Adresse / chantier</span>
                                <div class="od-value">{{ $order->delivery_address ?? $order->address ?? 'Adresse non renseignée.' }}</div>
                            </div>
                            <div>
                                <span class="od-label">Commune</span>
                                <div class="od-value">{{ $order->delivery_commune ?? '—' }}</div>
                            </div>
                            <div>
                                <span class="od-label">Quartier</span>
                                <div class="od-value">{{ $order->delivery_quartier ?? '—' }}</div>
                            </div>
                            @if(filled($order->delivery_site_name))
                                <div style="grid-column:1/-1;">
                                    <span class="od-label">Point de livraison</span>
                                    <div class="od-value">{{ $order->delivery_site_name }}</div>
                                </div>
                            @endif
                            @if(filled($order->delivery_note) || filled($order->notes))
                                <div style="grid-column:1/-1;">
                                    <span class="od-label">Instructions</span>
                                    <div class="od-value">{{ $order->delivery_note ?? $order->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="od-card">
                        <div class="od-card-head">
                            <h2 class="od-card-title"><i data-lucide="truck"></i> Livraison</h2>
                        </div>
                        <div class="od-data-grid">
                            <div>
                                <span class="od-label">Délai estimé</span>
                                <div class="od-value">{{ $deliveryDelay }}</div>
                            </div>
                            <div>
                                <span class="od-label">Poids total</span>
                                <div class="od-value">{{ number_format($totalWeight, 2, ',', ' ') }} kg</div>
                            </div>
                            <div>
                                <span class="od-label">Volume</span>
                                <div class="od-value">{{ number_format($totalVolume, 3, ',', ' ') }} m³</div>
                            </div>
                        </div>
                    </section>
                </div>
            @endif

            <section class="od-workflow" id="delivery-workflow">
                <div class="od-card">
                    <div class="od-card-head">
                        <h2 class="od-card-title"><i data-lucide="settings-2"></i> Traitement vendeur</h2>
                    </div>
                    <div class="od-card-body" style="display:grid;gap:14px;">
                        @if($singlePreparationTarget && isset($preparationLabels[$singlePreparationTarget]))
                            <div class="od-alert orange">
                                <i data-lucide="clock-3"></i>
                                <div>
                                    <strong>{{ $preparationLabels[$singlePreparationTarget] }}</strong><br>
                                    Mettez à jour l’état de préparation de vos articles pour continuer le traitement.
                                </div>
                            </div>
                            <form action="{{ route('vendor.orders.updateStatus', $order) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="vendor_status" value="{{ $singlePreparationTarget }}">
                                <button type="submit" class="od-btn orange" style="width:100%;">
                                    <i data-lucide="check-circle-2"></i>
                                    {{ $preparationLabels[$singlePreparationTarget] }}
                                </button>
                            </form>
                        @elseif($preparationTargets->count() > 1)
                            <div class="od-alert orange">
                                <i data-lucide="alert-circle"></i>
                                <div>Les lignes de la commande ne sont pas au même stade de préparation. Vérifiez les articles avant de continuer.</div>
                            </div>
                        @else
                            <div class="od-alert green">
                                <i data-lucide="check-circle-2"></i>
                                <div>La préparation de cette commande ne nécessite aucune action immédiate.</div>
                            </div>
                        @endif

                        @if($canConfirmCodPayment ?? false)
                            <form action="{{ route('vendor.orders.confirmPayment', $order) }}" method="POST">
                                @csrf
                                <input type="hidden" name="amount_claimed" value="{{ $vendorSubtotal }}">
                                <button type="submit" class="od-btn light" style="width:100%;">
                                    <i data-lucide="banknote"></i>
                                    Signaler un encaissement à la livraison
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="od-card">
                    <div class="od-card-head">
                        <h2 class="od-card-title"><i data-lucide="route"></i>
                            {{ $hasSellerDelivery ? 'Gestion de la livraison' : 'Remise à OVANIE' }}
                        </h2>
                    </div>
                    <div class="od-card-body" style="display:grid;gap:14px;">
                        @if($hasOvanieDelivery)
                            <div class="od-alert blue">
                                <i data-lucide="package-check"></i>
                                <div>
                                    <strong>Enlèvement par OVANIE</strong><br>
                                    Confirmez la disponibilité des produits lorsque la préparation est terminée.
                                </div>
                            </div>
                            <form action="{{ route('vendor.orders.ship', $order) }}" method="POST">
                                @csrf
                                <input type="hidden" name="shipment_date" value="{{ now()->toDateString() }}">
                                <input type="hidden" name="delivery_provider" value="ovanie">
                                <button type="submit" class="od-btn blue" style="width:100%;" @disabled(!$allOvaniePreparationReady || $allOvanieTransferred)>
                                    <i data-lucide="truck"></i>
                                    {{ $allOvanieTransferred ? 'Disponibilité confirmée' : ($allOvaniePreparationReady ? 'Confirmer la disponibilité' : 'Terminez la préparation') }}
                                </button>
                            </form>
                        @endif

                        @if($hasSellerDelivery)
                            @php
                                $sellerMissionStatus = $sellerTrackingSession?->mission_status ?: null;
                                $sellerMissionStatusLabel = match ($sellerMissionStatus) {
                                    'planned' => 'Chauffeur planifié',
                                    'accepted' => 'Mission acceptée',
                                    'in_transit' => 'Livraison en cours',
                                    'arrived' => 'Arrivé chez le client',
                                    'incident' => 'Incident signalé',
                                    'delivered' => 'Livraison terminée',
                                    default => 'À planifier',
                                };
                                $sellerMissionStatusTone = match ($sellerMissionStatus) {
                                    'in_transit', 'arrived' => 'blue',
                                    'delivered' => 'green',
                                    'incident' => 'red',
                                    default => 'warning',
                                };
                            @endphp

                            <div class="od-alert orange">
                                <i data-lucide="truck"></i>
                                <div>
                                    <strong>Livraison organisée par votre boutique</strong><br>
                                    Planifiez le chauffeur. La livraison passera en cours uniquement lorsque le chauffeur la démarrera depuis son téléphone.
                                </div>
                            </div>

                            @if($allSellerDelivered || $sellerMissionStatus === 'delivered')
                                <div class="od-alert green">
                                    <i data-lucide="check-circle-2"></i>
                                    <div><strong>Livraison terminée</strong><br>La remise a été confirmée avec le code du client.</div>
                                </div>
                            @elseif($sellerTrackingSession && $sellerMissionUrl)
                                <div class="od-mission-card">
                                    <div class="od-mission-head">
                                        <div>
                                            <span class="od-label">MISSION CHAUFFEUR</span>
                                            <h3>{{ $sellerMissionStatusLabel }}</h3>
                                        </div>
                                        <span class="od-badge {{ $sellerMissionStatusTone }}">{{ $sellerMissionStatusLabel }}</span>
                                    </div>
                                    <div class="od-mission-grid">
                                        <div><span>Chauffeur</span><strong>{{ $sellerTrackingSession->driver_name ?: 'À confirmer' }}</strong></div>
                                        <div><span>Téléphone</span><strong>{{ $sellerTrackingSession->driver_phone ?: 'À confirmer' }}</strong></div>
                                        <div><span>Immatriculation</span><strong>{{ $sellerTrackingSession->vehicle_plate ?: 'À confirmer' }}</strong></div>
                                        <div><span>Dernière position</span><strong>{{ $sellerTrackingSession->last_location_at ? $sellerTrackingSession->last_location_at->format('d/m/Y H:i') : 'En attente du chauffeur' }}</strong></div>
                                    </div>
                                    <div class="od-alert blue" style="margin:0;">
                                        <i data-lucide="smartphone"></i>
                                        <div>
                                            @if(in_array($sellerMissionStatus, ['in_transit', 'arrived'], true))
                                                Le chauffeur contrôle maintenant les étapes depuis la mission mobile. OVANIE supervise sa position et l’avancement.
                                            @else
                                                Envoyez ou ouvrez le lien sur le téléphone du chauffeur. Il doit accepter la mission puis démarrer la livraison.
                                            @endif
                                        </div>
                                    </div>
                                    <div class="od-mission-actions">
                                        <a href="{{ $sellerMissionUrl }}" target="_blank" rel="noopener" class="od-btn navy"><i data-lucide="external-link"></i> Ouvrir la mission chauffeur</a>
                                        <button type="button" class="od-btn light" data-copy="{{ $sellerMissionUrl }}"><i data-lucide="copy"></i> Copier le lien</button>
                                    </div>
                                </div>
                            @else
                                <form action="{{ route('vendor.orders.ship', $order) }}" method="POST" style="display:grid;gap:12px;">
                                    @csrf
                                    <input type="hidden" name="shipment_date" value="{{ now()->toDateString() }}">
                                    <input type="hidden" name="delivery_provider" value="seller">
                                    <div class="od-form-grid">
                                        <div class="od-field">
                                            <label>Nom du chauffeur</label>
                                            <input class="od-input" type="text" name="driver_name" value="{{ old('driver_name') }}" placeholder="Ex. Yao Kouassi" required @disabled(!$allSellerPreparationReady)>
                                        </div>
                                        <div class="od-field">
                                            <label>Téléphone du chauffeur</label>
                                            <input class="od-input" type="text" name="driver_phone" value="{{ old('driver_phone') }}" placeholder="Ex. 07 00 00 00 00" required @disabled(!$allSellerPreparationReady)>
                                        </div>
                                        <div class="od-field" style="grid-column:1/-1;">
                                            <label>Immatriculation</label>
                                            <input class="od-input" type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" placeholder="Ex. CI-1234-AB" required @disabled(!$allSellerPreparationReady)>
                                        </div>
                                    </div>
                                    <button type="submit" class="od-btn navy" @disabled(!$allSellerPreparationReady)>
                                        <i data-lucide="calendar-check"></i>
                                        {{ $allSellerPreparationReady ? 'Planifier le chauffeur' : 'Terminez la préparation' }}
                                    </button>
                                </form>
                            @endif
                        @endif

                        @if(!$hasOvanieDelivery && !$hasSellerDelivery)
                            <div class="od-alert blue"><i data-lucide="info"></i><div>Aucun workflow de livraison actif n’est associé à vos lignes.</div></div>
                        @endif
                    </div>
                </div>
            </section>
        <aside class="od-right">
            <section class="od-card">
                <div class="od-card-head">
                    <h2 class="od-card-title"><i data-lucide="credit-card"></i> Paiement</h2>
                </div>
                <div class="od-profile-list">
                    <div>
                        <span class="od-label">Statut</span>
                        <span class="od-badge {{ $paymentTone }}">{{ $paymentStatusLabel }}</span>
                    </div>
                    <div>
                        <span class="od-label">Montant</span>
                        <div class="od-value">{{ number_format($canViewCustomerDeliveryData ? $vendorPayoutTotal : $vendorSellerSubtotal, 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
                <div class="od-actions">
                    @if($canViewCustomerDeliveryData)
                        @if($clientPhone)
                            <a href="tel:{{ $clientPhone }}" class="od-btn"><i data-lucide="phone"></i> Contacter le client</a>
                        @elseif($clientEmail)
                            <a href="mailto:{{ $clientEmail }}" class="od-btn"><i data-lucide="mail"></i> Contacter le client</a>
                        @endif
                    @endif
                    <button type="button" class="od-btn" onclick="window.print()"><i data-lucide="printer"></i> Imprimer</button>
                    <a href="#delivery-workflow" class="od-btn red"><i data-lucide="alert-triangle"></i> Signaler un problème</a>
                </div>
            </section>
        </aside>

            @if($sellerOtpRequired)
                <section class="od-card od-full-span" style="margin-top:0;">
                    <div class="od-card-head">
                        <h2 class="od-card-title"><i data-lucide="shield-check"></i> Confirmation de remise</h2>
                    </div>
                    <div class="od-card-body">
                        <div class="od-alert green" style="margin-bottom:14px;">
                            <i data-lucide="lock-keyhole"></i>
                            <div>Saisissez le code communiqué par le client après la remise complète de la commande.</div>
                        </div>
                        <form action="{{ route('vendor.orders.verifyOtp', $order) }}" method="POST" style="display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:12px;">
                            @csrf
                            <input class="od-input" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" name="delivery_otp_code" placeholder="Saisir les 6 chiffres" required autocomplete="one-time-code" style="text-align:center;font-size:18px;font-weight:950;letter-spacing:4px;">
                            <button type="submit" class="od-btn green"><i data-lucide="check-circle-2"></i> Valider</button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="od-card od-history">
                <h2 class="od-card-title" style="margin-bottom:18px;">Historique de la commande</h2>
                @php
                    $histories = $shopItems
                        ->flatMap(fn ($item) => $item->statusHistories ?? collect())
                        ->reject(function ($history) {
                            $internalText = strtolower(trim(
                                ($history->label ?? '') . ' ' . ($history->message ?? '')
                            ));

                            $hiddenTerms = [
                                'checkout',
                                'prise en charge assign',
                                'mode de livraison initialis',
                                'delivery_provider',
                                'vendor_visible',
                                'webhook',
                                'token paydunya',
                                'libérée au vendeur',
                                'released to vendor',
                            ];

                            return collect($hiddenTerms)->contains(
                                fn ($term) => str_contains($internalText, $term)
                            );
                        })
                        ->unique(function ($history) {
                            return implode('|', [
                                optional($history->created_at)->format('Y-m-d H:i:s'),
                                $history->label ?? '',
                                $history->message ?? '',
                                $history->actor_type ?? '',
                            ]);
                        })
                        ->sortByDesc('created_at')
                        ->values();
                @endphp
                <div class="od-history-list">
                    @forelse($histories as $history)
                        <div class="od-history-row">
                            <span class="od-history-dot"></span>
                            <div class="od-history-date">{{ optional($history->created_at)->format('d/m/Y - H:i') }}</div>
                            <div>
                                <p class="od-history-title">{{ $history->label ?? 'Mise à jour commande' }}</p>
                                <p class="od-history-text">{{ $history->message ?? 'Le statut de la commande a été mis à jour.' }}</p>
                            </div>
                            <span class="od-chip">{{ $history->actor_type === 'vendor' ? 'Vous' : 'Système' }}</span>
                        </div>
                    @empty
                        <div class="od-history-row">
                            <span class="od-history-dot"></span>
                            <div class="od-history-date">{{ $orderDate }}</div>
                            <div>
                                <p class="od-history-title">Commande reçue</p>
                                <p class="od-history-text">La commande est disponible pour traitement.</p>
                            </div>
                            <span class="od-chip">Système</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</main>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.getAttribute('data-copy') || '';
                try {
                    await navigator.clipboard.writeText(value);
                    const original = button.innerHTML;
                    button.innerHTML = '<span style="font-size:11px;font-weight:900;">Copié</span>';
                    setTimeout(() => {
                        button.innerHTML = original;
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }, 1300);
                } catch (e) {
                    window.prompt('Copiez cette valeur :', value);
                }
            });
        });

        document.querySelectorAll('.od-file-input').forEach((input) => {
            input.addEventListener('change', function () {
                const display = this.closest('.od-field')?.querySelector('.od-file-name');
                if (!display) return;
                display.textContent = this.files && this.files.length ? this.files[0].name : '';
            });
        });
    });
</script>
@endsection
