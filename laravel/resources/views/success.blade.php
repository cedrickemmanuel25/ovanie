@extends('layouts.guest')

@php
    $isCashOnDelivery = $order->payment_method === 'cash_on_delivery';
    $isPaid = in_array($order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true);
    $isPendingOnline = $order->payment_method === 'paydunya' && ! $isPaid;

    $pageTitle = $isPaid
        ? 'Commande confirmée'
        : ($isCashOnDelivery ? 'Commande confirmée' : 'Vérification du paiement');

    $paymentMethodLabels = [
        'paydunya' => 'Paiement en ligne',
        'bank_transfer' => 'Virement bancaire',
        'cash_on_delivery' => 'Paiement à la livraison',
        'mobile_money' => 'Mobile Money',
    ];
@endphp

@section('title', $pageTitle . ' | OVANIE')

@section('styles')
<style>
    .order-result-page {
        min-height: 64vh;
        padding: 52px 18px;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        background: #f4f7fb;
        color: #0b2357;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .order-result-card {
        width: min(100%, 680px);
        padding: 38px;
        border: 1px solid #dfe7f2;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 18px 45px rgba(15, 38, 77, .09);
    }

    .result-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #eaf8ef;
        color: #159447;
    }

    .result-icon.pending {
        background: #fff6e7;
        color: #e86a00;
    }

    .result-icon svg {
        width: 34px;
        height: 34px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .order-result-card h1 {
        margin: 0;
        text-align: center;
        color: #071d54;
        font-size: clamp(28px, 4vw, 38px);
        line-height: 1.15;
        letter-spacing: -.7px;
    }

    .result-subtitle {
        max-width: 500px;
        margin: 10px auto 28px;
        text-align: center;
        color: #6e7e9c;
        font-size: 15px;
        line-height: 1.55;
    }

    .order-summary {
        margin: 0 0 22px;
        padding: 5px 20px;
        border: 1px solid #e1e8f2;
        border-radius: 15px;
        background: #f9fbfe;
    }

    .summary-row {
        min-height: 58px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        border-bottom: 1px solid #e5ebf3;
    }

    .summary-row:last-child {
        border-bottom: 0;
    }

    .summary-label {
        color: #6c7d9c;
        font-size: 14px;
    }

    .summary-value {
        color: #0b2357;
        font-size: 15px;
        font-weight: 800;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .summary-value.amount {
        color: #f06400;
        font-size: 21px;
        white-space: nowrap;
    }

    .result-notice {
        margin: 0 0 25px;
        padding: 16px 18px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid #d8eadf;
        border-radius: 14px;
        background: #f2fbf5;
        color: #244b34;
        font-size: 14px;
        line-height: 1.55;
    }

    .result-notice.pending {
        border-color: #f2ddbd;
        background: #fff9ef;
        color: #624416;
    }

    .result-notice svg {
        width: 21px;
        height: 21px;
        flex: 0 0 auto;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .result-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .result-button {
        min-height: 48px;
        padding: 0 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        border: 1px solid #d4deeb;
        background: #fff;
        color: #0b2357;
        text-decoration: none;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
    }

    .result-button.primary {
        border-color: #ff6500;
        background: #ff6500;
        color: #fff;
        box-shadow: 0 10px 22px rgba(255, 101, 0, .18);
    }

    .result-button:hover {
        transform: translateY(-1px);
    }

    @media (max-width: 620px) {
        .order-result-page {
            padding: 22px 10px 34px;
        }

        .order-result-card {
            padding: 28px 18px;
            border-radius: 18px;
        }

        .summary-row {
            padding: 13px 0;
            align-items: flex-start;
            flex-direction: column;
            gap: 4px;
        }

        .summary-value {
            text-align: left;
        }

        .result-actions {
            flex-direction: column;
        }

        .result-button {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="order-result-page">
    <section class="order-result-card" aria-labelledby="resultTitle">
        <div class="result-icon {{ $isPendingOnline ? 'pending' : '' }}">
            @if($isPendingOnline)
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7v5l3 2"></path>
                </svg>
            @else
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m5 12 4 4 10-10"></path>
                </svg>
            @endif
        </div>

        <h1 id="resultTitle">{{ $pageTitle }}</h1>

        <p class="result-subtitle">
            @if($isPaid)
                Votre paiement a été validé et votre commande est prise en charge.
            @elseif($isCashOnDelivery)
                Votre commande a bien été enregistrée.
            @else
                Nous vérifions votre paiement. Cette page se mettra à jour automatiquement.
            @endif
        </p>

        <div class="order-summary">
            <div class="summary-row">
                <span class="summary-label">Commande</span>
                <span class="summary-value">#{{ $order->order_number ?: $order->id }}</span>
            </div>

            <div class="summary-row">
                <span class="summary-label">Montant</span>
                <span class="summary-value amount">
                    {{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA
                </span>
            </div>

            <div class="summary-row">
                <span class="summary-label">Mode de paiement</span>
                <span class="summary-value">
                    {{ $paymentMethodLabels[$order->payment_method] ?? 'Paiement' }}
                </span>
            </div>
        </div>

        <div class="result-notice {{ $isPendingOnline ? 'pending' : '' }}">
            @if($isPendingOnline)
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 8v4"></path>
                    <path d="M12 16h.01"></path>
                </svg>
                <div>
                    La validation peut prendre quelques instants. Ne relancez pas un second paiement pour cette commande.
                </div>
            @elseif($isCashOnDelivery)
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 7h16v10H4z"></path>
                    <path d="M8 12h8"></path>
                </svg>
                <div>
                    Le règlement sera effectué au moment de la livraison.
                </div>
            @else
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 4.8 2.8 8.1 7 10 4.2-1.9 7-5.2 7-10V6l-7-3Z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <div>
                    Vous pouvez maintenant consulter le suivi de votre commande et votre facture.
                </div>
            @endif
        </div>

        <div class="result-actions">
            <a href="{{ route('orders.index') }}" class="result-button">
                Mes commandes
            </a>

            @if($isPendingOnline)
                <a href="{{ route('order.success', $order->id) }}" class="result-button primary">
                    Actualiser
                </a>
            @else
                <a href="{{ route('receipt.show', $order->id) }}" class="result-button primary">
                    Voir ma facture
                </a>
            @endif
        </div>
    </section>
</div>

@if($isPendingOnline)
<script>
    (() => {
        const storageKey = 'ovanie-payment-check-{{ $order->id }}';
        const attempts = Number(sessionStorage.getItem(storageKey) || 0);

        if (attempts < 4) {
            sessionStorage.setItem(storageKey, String(attempts + 1));
            window.setTimeout(() => window.location.reload(), 4000);
        }
    })();
</script>
@else
<script>
    sessionStorage.removeItem('ovanie-payment-check-{{ $order->id }}');
</script>
@endif
@endsection
