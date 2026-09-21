@extends('layouts.vendor')

@section('title', 'Détail d’un reversement | OVANIE')

@php
    $routePrefix = request()->routeIs('daniel.*') ? 'daniel' : 'vendor';

    $money = static fn ($amount): string =>
        number_format((float) $amount, 0, ',', ' ') . ' FCFA';

    $shortDate = static fn ($date): string =>
        $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '—';

    $dateTime = static fn ($date): string =>
        $date ? \Carbon\Carbon::parse($date)->format('d/m/Y à H:i') : '—';

    $productAmount = (float) ($payout->product_amount
        ?? data_get($payout->meta, 'financial_breakdown.product_amount')
        ?? $payout->total_amount);
    $sellerDeliveryAmount = (float) ($payout->seller_delivery_amount
        ?? data_get($payout->meta, 'financial_breakdown.seller_delivery_amount')
        ?? 0);
    $ovanieDeliveryAmount = (float) ($payout->ovanie_delivery_amount
        ?? data_get($payout->meta, 'financial_breakdown.ovanie_delivery_amount')
        ?? 0);
    $totalAmount = (float) $payout->total_amount;
    $commissionAmount = (float) $payout->commission_amount;
    $netProductAmount = (float) ($payout->net_product_amount
        ?? data_get($payout->meta, 'financial_breakdown.net_product_amount')
        ?? max(0, $productAmount - $commissionAmount));
    $processingFee = (float) $payout->processing_fee_amount;
    $netAmount = (float) $payout->payout_amount;

    $adjustmentAmount = round(
        $netAmount - ($netProductAmount + $sellerDeliveryAmount - $processingFee),
        2
    );

    $paymentPhone = $payout->phone ?: $payout->shop?->mm_number ?: 'Non défini';
    $holder = $payout->shop?->mm_holder
        ?: $payout->vendor?->name
        ?: 'Non défini';

    $transactionReference = data_get($payout->meta, 'transaction_reference')
        ?: data_get($payout->meta, 'provider_transaction_id')
        ?: $payout->batch_reference
        ?: $payout->payout_reference
        ?: ('RVS-' . $payout->id);

    $periodStart = data_get($payout->meta, 'period_start');
    $periodEnd = data_get($payout->meta, 'period_end');

    $periodLabel = ($periodStart && $periodEnd)
        ? $shortDate($periodStart) . ' — ' . $shortDate($periodEnd)
        : ($payout->payout_period_key ?: $shortDate($payout->created_at));

    $nextPaymentDate = $payout->expected_payment_date
        ?: $payout->scheduled_for;

    $payment = $payout->order?->payments?->sortByDesc('id')->first();

    $paymentStatus = $payment?->status;
    $paymentStatusLabel = match ($paymentStatus) {
        \App\Models\Payment::STATUS_PAID,
        \App\Models\Payment::STATUS_ESCROW_HELD,
        \App\Models\Payment::STATUS_RELEASED_TO_VENDOR => 'Payé',
        \App\Models\Payment::STATUS_PENDING => 'En attente',
        \App\Models\Payment::STATUS_REFUNDED => 'Remboursé',
        \App\Models\Payment::STATUS_FAILED => 'Échec',
        \App\Models\Payment::STATUS_CANCELLED => 'Annulé',
        default => $payout->isPaid() ? 'Payé' : 'En attente',
    };

    $paymentStatusClass = match ($paymentStatusLabel) {
        'Payé' => 'is-green',
        'Remboursé' => 'is-blue',
        'Échec', 'Annulé' => 'is-red',
        default => 'is-orange',
    };

    $statusClass = match ($payout->status) {
        \App\Models\VendorPayout::STATUS_PAID => 'is-green',
        \App\Models\VendorPayout::STATUS_APPROVED => 'is-blue',
        \App\Models\VendorPayout::STATUS_PROCESSING => 'is-violet',
        \App\Models\VendorPayout::STATUS_FAILED,
        \App\Models\VendorPayout::STATUS_CANCELLED,
        \App\Models\VendorPayout::STATUS_BLOCKED => 'is-red',
        default => 'is-orange',
    };

    $steps = [
        [
            'label' => 'Calculé',
            'date' => $payout->created_at,
            'done' => (bool) $payout->created_at,
        ],
        [
            'label' => 'Validé',
            'date' => $payout->approved_at ?: $payout->eligible_at,
            'done' => filled($payout->approved_at)
                || filled($payout->eligible_at)
                || in_array($payout->status, [
                    \App\Models\VendorPayout::STATUS_APPROVED,
                    \App\Models\VendorPayout::STATUS_PROCESSING,
                    \App\Models\VendorPayout::STATUS_PAID,
                ], true),
        ],
        [
            'label' => 'Programmé',
            'date' => $payout->scheduled_for,
            'done' => filled($payout->scheduled_for)
                || in_array($payout->status, [
                    \App\Models\VendorPayout::STATUS_PENDING,
                    \App\Models\VendorPayout::STATUS_APPROVED,
                    \App\Models\VendorPayout::STATUS_PROCESSING,
                    \App\Models\VendorPayout::STATUS_PAID,
                ], true),
        ],
        [
            'label' => 'Versé',
            'date' => $payout->paid_at,
            'done' => $payout->isPaid(),
        ],
    ];

    $history = collect([
        [
            'date' => $payout->created_at,
            'title' => 'Calcul du reversement',
            'description' => 'Le calcul du montant net vendeur a été effectué.',
            'tone' => 'blue',
        ],
        [
            'date' => $payout->eligible_at,
            'title' => 'Éligibilité confirmée',
            'description' => 'Le reversement a satisfait les conditions nécessaires au traitement.',
            'tone' => 'green',
        ],
        [
            'date' => $payout->approved_at,
            'title' => 'Validation',
            'description' => 'Le reversement a été validé et approuvé.',
            'tone' => 'green',
        ],
        [
            'date' => $payout->scheduled_for,
            'title' => 'Programmation',
            'description' => 'Le reversement a été programmé pour le traitement.',
            'tone' => 'blue',
        ],
        [
            'date' => $payout->processing_at,
            'title' => 'Traitement en cours',
            'description' => 'L’opération de versement est en cours de traitement.',
            'tone' => 'violet',
        ],
        [
            'date' => $payout->paid_at,
            'title' => 'Envoi effectué',
            'description' => 'Le montant a été envoyé avec succès au compte vendeur.',
            'tone' => 'green',
        ],
        [
            'date' => $payout->failed_at,
            'title' => 'Échec du versement',
            'description' => data_get($payout->meta, 'failure_reason')
                ?: 'Le versement n’a pas pu être exécuté.',
            'tone' => 'red',
        ],
        [
            'date' => $payout->cancelled_at,
            'title' => 'Reversement annulé',
            'description' => data_get($payout->meta, 'cancel_reason')
                ?: 'Le reversement a été annulé.',
            'tone' => 'red',
        ],
        [
            'date' => $payout->vendor_followup_requested_at,
            'title' => 'Demande de suivi',
            'description' => $payout->vendor_note
                ?: 'Une demande de suivi a été envoyée à OVANIE.',
            'tone' => 'orange',
        ],
    ])
        ->filter(fn ($event) => filled($event['date']))
        ->sortBy('date')
        ->values();

    $order = $payout->order;
    $orderNumber = $order?->order_number ?: ('OVN-' . $payout->order_id);
    $orderDate = $order?->created_at ?: $payout->created_at;
@endphp

@section('styles')
<style>
    :root {
        --rv-navy: #0a2a63;
        --rv-blue: #0d63ea;
        --rv-orange: #ff5a0a;
        --rv-green: #16a34a;
        --rv-red: #ef4444;
        --rv-violet: #6d4aff;
        --rv-text: #173264;
        --rv-muted: #7082a2;
        --rv-line: #dfe7f1;
        --rv-bg: #f8fbff;
        --rv-card: #ffffff;
    }

    .rv-page {
        max-width: 1280px;
        margin: 0 auto;
        padding: 26px 28px 40px;
        color: var(--rv-text);
    }

    .rv-breadcrumb {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 12px;
        color: #7890b5;
        font-size: 11px;
        font-weight: 700;
        flex-wrap: wrap;
    }

    .rv-breadcrumb a {
        color: var(--rv-blue);
        text-decoration: none;
    }

    .rv-breadcrumb svg {
        width: 13px;
        height: 13px;
    }

    .rv-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 14px;
    }

    .rv-title h1 {
        margin: 0;
        color: var(--rv-navy);
        font-size: clamp(30px, 3vw, 46px);
        line-height: 1.04;
        font-weight: 900;
        letter-spacing: -0.035em;
    }

    .rv-title p {
        margin: 8px 0 0;
        color: #657999;
        font-size: 13px;
    }

    .rv-top-actions {
        display: flex;
        gap: 10px;
        flex: 0 0 auto;
    }

    .rv-btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 17px;
        border: 1px solid #9cb2d1;
        border-radius: 7px;
        background: #fff;
        color: var(--rv-navy);
        font-size: 11.5px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
    }

    .rv-btn:hover {
        transform: translateY(-1px);
        border-color: var(--rv-blue);
    }

    .rv-btn svg {
        width: 16px;
        height: 16px;
    }

    .rv-btn-primary {
        border-color: var(--rv-blue);
        background: var(--rv-blue);
        color: #fff;
    }

    .rv-card {
        border: 1px solid var(--rv-line);
        border-radius: 12px;
        background: var(--rv-card);
        box-shadow: 0 10px 30px rgba(16, 44, 92, .035);
    }

    .rv-summary-banner {
        display: grid;
        grid-template-columns: minmax(260px, 1.25fr) minmax(0, 1.5fr) minmax(340px, 1.65fr);
        align-items: stretch;
        margin-bottom: 16px;
        overflow: hidden;
    }

    .rv-banner-block {
        min-width: 0;
        padding: 18px 20px;
    }

    .rv-banner-block + .rv-banner-block {
        border-left: 1px solid var(--rv-line);
    }

    .rv-kicker {
        display: block;
        color: #6d7f9f;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .rv-reference {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
        color: var(--rv-navy);
        font-size: 18px;
        line-height: 1.2;
        font-weight: 900;
        overflow-wrap: anywhere;
    }

    .rv-copy {
        width: 28px;
        height: 28px;
        display: grid;
        place-items: center;
        border: 1px solid #d8e1ed;
        border-radius: 6px;
        background: #f8fafc;
        color: #48638f;
        cursor: pointer;
        flex: 0 0 28px;
    }

    .rv-copy svg {
        width: 14px;
        height: 14px;
    }

    .rv-amount-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 12px;
        flex-wrap: wrap;
    }

    .rv-amount {
        color: var(--rv-green);
        font-size: 27px;
        line-height: 1;
        font-weight: 900;
        letter-spacing: -0.025em;
    }

    .rv-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 900;
        white-space: nowrap;
    }

    .is-green { background: #e4f8ea; color: #13833d; }
    .is-blue { background: #e9f1ff; color: #1762dc; }
    .is-orange { background: #fff0e5; color: #e95a0b; }
    .is-red { background: #fee9e9; color: #d93030; }
    .is-violet { background: #f0ebff; color: #6c4ae0; }

    .rv-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px 22px;
        height: 100%;
        align-content: center;
    }

    .rv-meta-item {
        min-width: 0;
    }

    .rv-meta-label {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #6d7f9f;
        font-size: 9px;
        font-weight: 800;
    }

    .rv-meta-label svg {
        width: 15px;
        height: 15px;
        color: #2f5ca7;
    }

    .rv-meta-value {
        margin-top: 5px;
        color: var(--rv-navy);
        font-size: 11.5px;
        font-weight: 800;
        line-height: 1.4;
    }

    .rv-progress {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: start;
        height: 100%;
        padding-top: 4px;
    }

    .rv-step {
        position: relative;
        min-width: 0;
        text-align: center;
    }

    .rv-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 10px;
        left: calc(50% + 13px);
        right: calc(-50% + 13px);
        height: 2px;
        background: #dce5ef;
    }

    .rv-step.is-done:not(:last-child)::after {
        background: #35a853;
    }

    .rv-step-dot {
        position: relative;
        z-index: 2;
        width: 22px;
        height: 22px;
        margin: 0 auto 7px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #e8edf4;
        color: #7485a1;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px #d6e0ec;
    }

    .rv-step.is-done .rv-step-dot {
        background: #19a446;
        color: #fff;
        box-shadow: none;
    }

    .rv-step-dot svg {
        width: 12px;
        height: 12px;
    }

    .rv-step strong {
        display: block;
        color: #27416f;
        font-size: 9.5px;
        font-weight: 900;
    }

    .rv-step span {
        display: block;
        margin-top: 3px;
        color: #7183a1;
        font-size: 8.5px;
        line-height: 1.35;
    }

    .rv-grid-main {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(330px, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }

    .rv-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px 0;
    }

    .rv-section-head h2 {
        margin: 0;
        color: var(--rv-navy);
        font-size: 15px;
        font-weight: 900;
    }

    .rv-section-head svg {
        width: 16px;
        height: 16px;
        color: #5878aa;
    }

    .rv-financial-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        padding: 18px;
    }

    .rv-fin-item {
        position: relative;
        min-width: 0;
        padding: 0 13px;
        text-align: center;
    }

    .rv-fin-item:first-child {
        padding-left: 0;
    }

    .rv-fin-item:last-child {
        padding-right: 0;
    }

    .rv-fin-item + .rv-fin-item {
        border-left: 1px solid var(--rv-line);
    }

    .rv-fin-icon {
        width: 42px;
        height: 42px;
        margin: 0 auto 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .rv-fin-icon svg {
        width: 21px;
        height: 21px;
    }

    .rv-fin-icon.blue { color: #1765ee; background: #edf4ff; }
    .rv-fin-icon.violet { color: #724ce9; background: #f1edff; }
    .rv-fin-icon.orange { color: #ff610d; background: #fff0e7; }
    .rv-fin-icon.green { color: #16a34a; background: #e7f8ed; }

    .rv-fin-label {
        min-height: 28px;
        color: #657896;
        font-size: 9px;
        font-weight: 700;
        line-height: 1.4;
    }

    .rv-fin-value {
        margin-top: 6px;
        color: var(--rv-navy);
        font-size: 14px;
        line-height: 1.25;
        font-weight: 900;
    }

    .rv-fin-value.green { color: var(--rv-green); }
    .rv-fin-value.red { color: var(--rv-red); }

    .rv-payment-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 28px;
        padding: 18px;
    }

    .rv-info-row {
        min-width: 0;
    }

    .rv-info-row dt {
        color: #7183a1;
        font-size: 9px;
        font-weight: 700;
    }

    .rv-info-row dd {
        margin: 5px 0 0;
        color: var(--rv-navy);
        font-size: 11px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .rv-info-note {
        line-height: 1.55;
    }

    .rv-warning {
        margin: 0 18px 18px;
        padding: 12px 14px;
        border: 1px solid #ffcfac;
        border-radius: 8px;
        background: #fff7ef;
        color: #a44310;
        font-size: 10.5px;
        line-height: 1.55;
    }

    .rv-admin-note {
        margin: 0 18px 18px;
        padding: 12px 14px;
        border: 1px solid #d8e4f5;
        border-radius: 8px;
        background: #f7faff;
        color: #34527f;
        font-size: 10.5px;
        line-height: 1.55;
    }

    .rv-table-card {
        margin-bottom: 16px;
        overflow: hidden;
    }

    .rv-table-title {
        padding: 14px 16px;
        border-bottom: 1px solid var(--rv-line);
        color: var(--rv-navy);
        font-size: 13px;
        font-weight: 900;
    }

    .rv-table-wrap {
        width: 100%;
        overflow: hidden;
    }

    .rv-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .rv-table th,
    .rv-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #e7edf5;
        text-align: left;
        color: #17366b;
        font-size: 9.5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rv-table th {
        color: #71829d;
        font-size: 8.5px;
        font-weight: 900;
        text-transform: uppercase;
        background: #fbfcfe;
    }

    .rv-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .rv-link {
        color: var(--rv-blue);
        font-weight: 800;
        text-decoration: none;
    }

    .rv-table-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid var(--rv-line);
        color: #7183a1;
        font-size: 9px;
    }

    .rv-grid-bottom {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.08fr);
        gap: 16px;
        margin-bottom: 16px;
    }

    .rv-history {
        padding: 12px 18px 18px;
    }

    .rv-history-list {
        position: relative;
    }

    .rv-history-list::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #dce5f0;
    }

    .rv-history-item {
        position: relative;
        display: grid;
        grid-template-columns: 20px 110px 1fr;
        gap: 10px;
        padding: 8px 0;
    }

    .rv-history-dot {
        position: relative;
        z-index: 2;
        width: 16px;
        height: 16px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        background: var(--rv-blue);
    }

    .rv-history-dot.green { background: var(--rv-green); }
    .rv-history-dot.red { background: var(--rv-red); }
    .rv-history-dot.orange { background: var(--rv-orange); }
    .rv-history-dot.violet { background: var(--rv-violet); }

    .rv-history-dot svg {
        width: 10px;
        height: 10px;
    }

    .rv-history-date {
        color: #58709b;
        font-size: 9px;
        line-height: 1.4;
    }

    .rv-history-copy strong {
        display: block;
        color: #17366d;
        font-size: 10px;
        font-weight: 900;
    }

    .rv-history-copy span {
        display: block;
        margin-top: 2px;
        color: #7585a1;
        font-size: 8.8px;
        line-height: 1.45;
    }

    .rv-docs {
        padding: 12px 18px 18px;
    }

    .rv-doc-row {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr) 88px 96px 72px;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #e7edf5;
    }

    .rv-doc-row:last-child {
        border-bottom: 0;
    }

    .rv-doc-icon {
        width: 30px;
        height: 30px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        background: #edf4ff;
        color: #2058b2;
    }

    .rv-doc-icon svg {
        width: 16px;
        height: 16px;
    }

    .rv-doc-name {
        min-width: 0;
        color: #17366d;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rv-doc-meta {
        color: #7484a0;
        font-size: 8.8px;
    }

    .rv-doc-action {
        color: var(--rv-blue);
        font-size: 9px;
        font-weight: 800;
        text-decoration: none;
        text-align: right;
        cursor: pointer;
        background: none;
        border: 0;
        padding: 0;
    }

    .rv-assistance {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 18px;
        border: 1px solid #9fc3ff;
        background: linear-gradient(90deg, #f3f8ff, #fbfdff);
    }

    .rv-assistance-main {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 0;
    }

    .rv-assistance-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: var(--rv-blue);
        background: #dbeaff;
    }

    .rv-assistance-icon svg {
        width: 20px;
        height: 20px;
    }

    .rv-assistance h2 {
        margin: 0;
        color: var(--rv-navy);
        font-size: 13px;
        font-weight: 900;
    }

    .rv-assistance p {
        margin: 4px 0 0;
        color: #61789d;
        font-size: 9.5px;
        line-height: 1.5;
    }

    .rv-followup {
        margin-top: 16px;
    }

    .rv-followup textarea {
        width: 100%;
        min-height: 100px;
        padding: 12px 13px;
        border: 1px solid #cfdbea;
        border-radius: 8px;
        color: #17366b;
        font: inherit;
        font-size: 11px;
        resize: vertical;
        outline: none;
    }

    .rv-followup textarea:focus {
        border-color: var(--rv-blue);
        box-shadow: 0 0 0 3px rgba(13, 99, 234, .08);
    }

    .rv-followup-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }

    .rv-empty-history {
        padding: 28px 18px;
        color: #7183a1;
        font-size: 10px;
        text-align: center;
    }

    @media (max-width: 1120px) {
        .rv-summary-banner {
            grid-template-columns: 1fr;
        }

        .rv-banner-block + .rv-banner-block {
            border-left: 0;
            border-top: 1px solid var(--rv-line);
        }

        .rv-grid-main,
        .rv-grid-bottom {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 820px) {
        .rv-page {
            padding: 18px 14px 30px;
        }

        .rv-title-row {
            flex-direction: column;
        }

        .rv-top-actions {
            width: 100%;
        }

        .rv-top-actions .rv-btn {
            flex: 1;
        }

        .rv-financial-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 0;
        }

        .rv-fin-item + .rv-fin-item {
            border-left: 0;
        }

        .rv-fin-item:nth-child(even) {
            border-left: 1px solid var(--rv-line);
        }

        .rv-payment-info {
            grid-template-columns: 1fr;
        }

        .rv-progress {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 0;
        }

        .rv-step::after {
            display: none;
        }

        .rv-doc-row {
            grid-template-columns: 30px 1fr 60px;
        }

        .rv-doc-meta {
            display: none;
        }

        .rv-assistance {
            align-items: flex-start;
            flex-direction: column;
        }

        .rv-assistance .rv-btn {
            width: 100%;
        }
    }

    @media (max-width: 560px) {
        .rv-meta-grid {
            grid-template-columns: 1fr;
        }

        .rv-financial-grid {
            grid-template-columns: 1fr;
        }

        .rv-fin-item:nth-child(even) {
            border-left: 0;
        }

        .rv-fin-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--rv-line);
        }

        .rv-fin-item:last-child {
            border-bottom: 0;
        }

        .rv-table th:nth-child(2),
        .rv-table td:nth-child(2),
        .rv-table th:nth-child(4),
        .rv-table td:nth-child(4) {
            display: none;
        }

        .rv-history-item {
            grid-template-columns: 20px 1fr;
        }

        .rv-history-date {
            grid-column: 2;
        }

        .rv-history-copy {
            grid-column: 2;
        }
    }

    @media print {
        .vendor-sidebar,
        .vendor-topbar,
        .rv-top-actions,
        .rv-assistance,
        .rv-followup,
        .rv-breadcrumb {
            display: none !important;
        }

        .rv-page {
            max-width: none;
            padding: 0;
        }

        .rv-card {
            box-shadow: none;
            break-inside: avoid;
        }
    }
</style>
@endsection

@section('content')
<div class="rv-page">
    <nav class="rv-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route($routePrefix . '.payouts.index') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route($routePrefix . '.payouts.index') }}">Finances</a>
        <i data-lucide="chevron-right"></i>
        <a href="{{ route($routePrefix . '.payouts.index') }}">Reversements</a>
        <i data-lucide="chevron-right"></i>
        <span>Détail d’un reversement</span>
    </nav>

    <div class="rv-title-row">
        <div class="rv-title">
            <h1>Détail d’un reversement</h1>
            <p>Consultez le détail complet du reversement, de la vente associée et du montant net versé.</p>
        </div>

        <div class="rv-top-actions">
            @if(filled($payout->transfer_receipt_path))
                <a
                    href="{{ route('vendor.private-documents.payout', $payout) }}"
                    target="_blank"
                    class="rv-btn"
                >
                    <i data-lucide="download"></i>
                    Justificatif
                </a>
            @endif

            <button class="rv-btn" type="button" onclick="window.print()">
                <i data-lucide="printer"></i>
                Imprimer
            </button>
        </div>
    </div>

    <section class="rv-card rv-summary-banner">
        <div class="rv-banner-block">
            <span class="rv-kicker">Référence du reversement</span>

            <div class="rv-reference">
                <span id="rvReference">{{ $payout->payout_reference ?: ('RVS-' . $payout->id) }}</span>
                <button class="rv-copy" type="button" id="copyReference" title="Copier la référence">
                    <i data-lucide="copy"></i>
                </button>
            </div>

            <span class="rv-kicker" style="margin-top:13px;">Montant net vendeur</span>

            <div class="rv-amount-row">
                <strong class="rv-amount">{{ $money($netAmount) }}</strong>
                <span class="rv-badge {{ $statusClass }}">{{ $payout->status_label }}</span>
            </div>
        </div>

        <div class="rv-banner-block">
            <div class="rv-meta-grid">
                <div class="rv-meta-item">
                    <div class="rv-meta-label">
                        <i data-lucide="calendar-days"></i>
                        Date du reversement
                    </div>
                    <div class="rv-meta-value">
                        {{ $shortDate($payout->paid_at ?: $payout->created_at) }}
                    </div>
                </div>

                <div class="rv-meta-item">
                    <div class="rv-meta-label">
                        <i data-lucide="calendar-range"></i>
                        Période concernée
                    </div>
                    <div class="rv-meta-value">{{ $periodLabel }}</div>
                </div>

                <div class="rv-meta-item">
                    <div class="rv-meta-label">
                        <i data-lucide="wallet-cards"></i>
                        Méthode
                    </div>
                    <div class="rv-meta-value">{{ $payout->payment_method_label }}</div>
                </div>

                <div class="rv-meta-item">
                    <div class="rv-meta-label">
                        <i data-lucide="user-round"></i>
                        Titulaire
                    </div>
                    <div class="rv-meta-value">{{ $holder }}</div>
                </div>
            </div>
        </div>

        <div class="rv-banner-block">
            <div class="rv-progress">
                @foreach($steps as $step)
                    <div class="rv-step {{ $step['done'] ? 'is-done' : '' }}">
                        <span class="rv-step-dot">
                            @if($step['done'])
                                <i data-lucide="check"></i>
                            @endif
                        </span>
                        <strong>{{ $step['label'] }}</strong>
                        <span>
                            {{ $step['date'] ? $shortDate($step['date']) : '—' }}
                            @if($step['date'])
                                <br>{{ \Carbon\Carbon::parse($step['date'])->format('H:i') }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="rv-grid-main">
        <section class="rv-card">
            <div class="rv-section-head">
                <h2>Résumé financier</h2>
                <i data-lucide="info"></i>
            </div>

            <div class="rv-financial-grid">
                <article class="rv-fin-item">
                    <span class="rv-fin-icon blue"><i data-lucide="shopping-bag"></i></span>
                    <div class="rv-fin-label">Produits</div>
                    <div class="rv-fin-value">{{ $money($productAmount) }}</div>
                </article>

                <article class="rv-fin-item">
                    <span class="rv-fin-icon violet"><i data-lucide="percent"></i></span>
                    <div class="rv-fin-label">Commission OVANIE sur produits</div>
                    <div class="rv-fin-value">-{{ $money($commissionAmount) }}</div>
                </article>

                <article class="rv-fin-item">
                    <span class="rv-fin-icon green"><i data-lucide="wallet"></i></span>
                    <div class="rv-fin-label">Net produits</div>
                    <div class="rv-fin-value green">{{ $money($netProductAmount) }}</div>
                </article>

                <article class="rv-fin-item">
                    <span class="rv-fin-icon orange"><i data-lucide="truck"></i></span>
                    <div class="rv-fin-label">Livraison vendeur</div>
                    <div class="rv-fin-value">{{ $money($sellerDeliveryAmount) }}</div>
                </article>

                @if($ovanieDeliveryAmount > 0)
                    <article class="rv-fin-item">
                        <span class="rv-fin-icon blue"><i data-lucide="warehouse"></i></span>
                        <div class="rv-fin-label">Livraison OVANIE exclue</div>
                        <div class="rv-fin-value">{{ $money($ovanieDeliveryAmount) }}</div>
                    </article>
                @endif

                <article class="rv-fin-item">
                    <span class="rv-fin-icon blue"><i data-lucide="credit-card"></i></span>
                    <div class="rv-fin-label">Frais de traitement</div>
                    <div class="rv-fin-value">-{{ $money($processingFee) }}</div>
                </article>

                @if(abs($adjustmentAmount) > 0.01)
                    <article class="rv-fin-item">
                        <span class="rv-fin-icon orange"><i data-lucide="sliders-horizontal"></i></span>
                        <div class="rv-fin-label">Ajustements</div>
                        <div class="rv-fin-value {{ $adjustmentAmount < 0 ? 'red' : 'green' }}">
                            {{ $adjustmentAmount > 0 ? '+' : '' }}{{ $money($adjustmentAmount) }}
                        </div>
                    </article>
                @endif

                <article class="rv-fin-item">
                    <span class="rv-fin-icon green"><i data-lucide="wallet-cards"></i></span>
                    <div class="rv-fin-label">Total à reverser</div>
                    <div class="rv-fin-value green">{{ $money($netAmount) }}</div>
                </article>
            </div>
        </section>

        <section class="rv-card">
            <div class="rv-section-head">
                <h2>Informations de versement</h2>
            </div>

            <dl class="rv-payment-info">
                <div class="rv-info-row">
                    <dt>Statut</dt>
                    <dd><span class="rv-badge {{ $statusClass }}">{{ $payout->status_label }}</span></dd>
                </div>

                <div class="rv-info-row">
                    <dt>Titulaire</dt>
                    <dd>{{ $holder }}</dd>
                </div>

                <div class="rv-info-row">
                    <dt>Référence transaction</dt>
                    <dd>{{ $transactionReference }}</dd>
                </div>

                <div class="rv-info-row">
                    <dt>Prochaine échéance</dt>
                    <dd>{{ $nextPaymentDate ? $shortDate($nextPaymentDate) : 'Aucune échéance programmée' }}</dd>
                </div>

                <div class="rv-info-row">
                    <dt>Opérateur</dt>
                    <dd>{{ $payout->payment_method_label }}</dd>
                </div>

                <div class="rv-info-row">
                    <dt>Numéro</dt>
                    <dd>{{ $paymentPhone }}</dd>
                </div>

                @if($payout->isPaid())
                    <div class="rv-info-row" style="grid-column:1 / -1;">
                        <dt>Note</dt>
                        <dd class="rv-info-note">
                            Le montant a été envoyé avec succès sur votre compte vendeur.
                        </dd>
                    </div>
                @endif
            </dl>

            @if(filled(data_get($payout->meta, 'blocked_reason')))
                <div class="rv-warning">
                    <strong>Pourquoi ce reversement attend :</strong><br>
                    {{ data_get($payout->meta, 'blocked_reason') }}
                </div>
            @endif

            @if($payout->admin_note)
                <div class="rv-admin-note">
                    <strong>Note de suivi OVANIE :</strong><br>
                    {{ $payout->admin_note }}
                </div>
            @endif
        </section>
    </div>

    <section class="rv-card rv-table-card">
        <div class="rv-table-title">Commande incluse dans ce reversement</div>

        <div class="rv-table-wrap">
            <table class="rv-table">
                <colgroup>
                    <col style="width:12%">
                    <col style="width:20%">
                    <col style="width:14%">
                    <col style="width:14%">
                    <col style="width:13%">
                    <col style="width:15%">
                    <col style="width:7%">
                    <col style="width:5%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commande</th>
                        <th>Produits</th>
                        <th>Livraison vendeur</th>
                        <th>Commission</th>
                        <th>Total à reverser</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $shortDate($orderDate) }}</td>
                        <td>
                            <a class="rv-link" href="{{ route($routePrefix . '.orders.show', $order) }}">
                                {{ $orderNumber }}
                            </a>
                        </td>
                        <td>{{ $money($productAmount) }}</td>
                        <td>{{ $money($sellerDeliveryAmount) }}</td>
                        <td>{{ $money($commissionAmount) }}</td>
                        <td style="color:#159447;font-weight:900;">{{ $money($netAmount) }}</td>
                        <td>
                            <span class="rv-badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span>
                        </td>
                        <td>
                            <a class="rv-link" href="{{ route($routePrefix . '.orders.show', $order) }}">Voir</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rv-table-foot">
            <span>1 commande affichée</span>
            <span>Référence : {{ $payout->payout_reference ?: ('RVS-' . $payout->id) }}</span>
        </div>
    </section>

    <div class="rv-grid-bottom">
        <section class="rv-card">
            <div class="rv-section-head">
                <h2>Historique du reversement</h2>
            </div>

            @if($history->isNotEmpty())
                <div class="rv-history">
                    <div class="rv-history-list">
                        @foreach($history as $event)
                            <div class="rv-history-item">
                                <span class="rv-history-dot {{ $event['tone'] }}">
                                    <i data-lucide="check"></i>
                                </span>

                                <div class="rv-history-date">
                                    {{ $dateTime($event['date']) }}
                                </div>

                                <div class="rv-history-copy">
                                    <strong>{{ $event['title'] }}</strong>
                                    <span>{{ $event['description'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="rv-empty-history">Aucun événement disponible pour le moment.</div>
            @endif
        </section>

        <section class="rv-card">
            <div class="rv-section-head">
                <h2>Documents & justificatifs</h2>
            </div>

            <div class="rv-docs">
                <div class="rv-doc-row">
                    <span class="rv-doc-icon"><i data-lucide="file-text"></i></span>
                    <span class="rv-doc-name">Bordereau de reversement</span>
                    <span class="rv-doc-meta">{{ $shortDate($payout->created_at) }}</span>
                    <span class="rv-doc-meta">Vue imprimable</span>
                    <button class="rv-doc-action" type="button" onclick="window.print()">Imprimer</button>
                </div>

                <div class="rv-doc-row">
                    <span class="rv-doc-icon"><i data-lucide="chart-no-axes-column-increasing"></i></span>
                    <span class="rv-doc-name">Détail de la commande</span>
                    <span class="rv-doc-meta">{{ $shortDate($orderDate) }}</span>
                    <span class="rv-doc-meta">{{ $orderNumber }}</span>
                    <a class="rv-doc-action" href="{{ route($routePrefix . '.orders.show', $order) }}">Voir</a>
                </div>

                @if(filled($payout->transfer_receipt_path))
                    <div class="rv-doc-row">
                        <span class="rv-doc-icon"><i data-lucide="receipt-text"></i></span>
                        <span class="rv-doc-name">Justificatif de paiement</span>
                        <span class="rv-doc-meta">{{ $shortDate($payout->paid_at ?: $payout->updated_at) }}</span>
                        <span class="rv-doc-meta">Document</span>
                        <a
                            class="rv-doc-action"
                            href="{{ route('vendor.private-documents.payout', $payout) }}"
                            target="_blank"
                        >
                            Voir
                        </a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    @if(! $payout->isPaid())
        <section class="rv-card rv-followup">
            <div class="rv-section-head">
                <h2>Demander un suivi</h2>
            </div>

            <form
                method="POST"
                action="{{ route($routePrefix . '.payouts.followUp', $payout) }}"
                style="padding:14px 18px 18px;"
            >
                @csrf

                <textarea
                    name="vendor_note"
                    placeholder="Message facultatif pour l’administration OVANIE"
                >{{ old('vendor_note', $payout->vendor_note) }}</textarea>

                @error('vendor_note')
                    <p style="margin:6px 0 0;color:#dc2626;font-size:10px;font-weight:700;">
                        {{ $message }}
                    </p>
                @enderror

                <div class="rv-followup-actions">
                    <button class="rv-btn rv-btn-primary" type="submit">
                        <i data-lucide="send"></i>
                        Envoyer la demande
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="rv-card rv-assistance">
        <div class="rv-assistance-main">
            <span class="rv-assistance-icon"><i data-lucide="info"></i></span>

            <div>
                <h2>Notes et assistance</h2>
                <p>
                    En cas d’écart sur un montant ou une commande, consultez le détail de la vente
                    ou contactez le support OVANIE. Notre équipe reste disponible pour toute question
                    relative à ce reversement.
                </p>
            </div>
        </div>

        <a href="{{ route('contact.index') }}" class="rv-btn">
            <i data-lucide="headphones"></i>
            Contacter le support
        </a>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('copyReference');
    const reference = document.getElementById('rvReference');

    if (button && reference && navigator.clipboard) {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(reference.textContent.trim());
                const original = button.innerHTML;
                button.innerHTML = '<i data-lucide="check"></i>';

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                setTimeout(() => {
                    button.innerHTML = original;

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }, 1200);
            } catch (error) {
                console.warn('Copie impossible', error);
            }
        });
    }
});
</script>
@endsection
