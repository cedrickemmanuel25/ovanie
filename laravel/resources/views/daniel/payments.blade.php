@extends('layouts.vendor')

@section('title', 'Mes paiements | OVANIE')

@section('styles')
<style>
.payment-summary { display: grid; grid-template-columns: 300px 290px minmax(420px, 1fr); gap: 18px; margin-bottom: 18px; }
.balance-card { min-height: 230px; padding: 32px 26px 24px; position: relative; }
.balance-card h2 { margin: 0 0 28px; font-size: 1.05rem; color: var(--ov-navy); }
.balance-card strong { display: block; margin-bottom: 18px; font-size: 2rem; color: #0aa65b; line-height: 1.1; }
.balance-card.pending strong { color: var(--ov-orange); }
.balance-card p { margin: 0 0 26px; color: #536078; }
.balance-icon { position: absolute; right: 24px; top: 34px; width: 64px; height: 64px; display: grid; place-items: center; border-radius: 999px; color: #0aa65b; background: #e6f7ed; }
.balance-card.pending .balance-icon { color: var(--ov-orange); background: #fff0e5; }
.balance-icon svg { width: 32px; height: 32px; }
.revenue-chart { padding: 24px; min-height: 230px; }
.chart-head { display: flex; justify-content: space-between; gap: 14px; align-items: center; margin-bottom: 8px; }
.chart-head h2 { margin: 0; font-size: 1rem; }
.chart-box { width: 100%; height: 154px; }
.payment-history { overflow: hidden; }
.history-head { padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; gap: 18px; border-bottom: 1px solid var(--ov-line); }
.history-head h2 { margin: 0; color: var(--ov-navy); }
.payment-method { display: inline-flex; align-items: center; gap: 10px; }
.method-logo { width: 30px; height: 30px; border-radius: 6px; display: grid; place-items: center; color: #fff; background: #06164a; font-weight: 900; }
.amount-green { color: #0aa65b !important; font-weight: 850; }
.amount-orange { color: var(--ov-orange) !important; font-weight: 850; }
@media (max-width: 1200px) { .payment-summary { grid-template-columns: 1fr 1fr; } .revenue-chart { grid-column: 1 / -1; } }
@media (max-width: 760px) { .payment-summary { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
<section class="vd-page-head">
    <div>
        <h1>Mes paiements</h1>
        <p>Suivez vos revenus, vos soldes et vos transactions.</p>
    </div>
</section>

<section class="payment-summary">
    <article class="balance-card vd-card">
        <span class="balance-icon"><i data-lucide="wallet"></i></span>
        <h2>Solde disponible <i data-lucide="info" style="width:16px;height:16px;"></i></h2>
        <strong>{{ number_format($balanceAvailable, 0, ',', ' ') }} FCFA</strong>
        <p>Argent disponible pour retrait</p>
        <button class="vd-btn" style="width:100%; color:#fff; border:0; background:#10a557;">Demander un retrait <i data-lucide="arrow-right"></i></button>
    </article>

    <article class="balance-card pending vd-card">
        <span class="balance-icon"><i data-lucide="clock"></i></span>
        <h2>En attente <i data-lucide="info" style="width:16px;height:16px;"></i></h2>
        <strong>{{ number_format($balancePending, 0, ',', ' ') }} FCFA</strong>
        <p>Montants en cours de traitement</p>
    </article>

    <article class="revenue-chart vd-card">
        <div class="chart-head">
            <h2>Évolution des revenus <span style="font-weight:500;">(30 derniers jours)</span></h2>
            <button type="button" class="vd-btn" style="min-height:34px;">30 derniers jours <i data-lucide="chevron-down"></i></button>
        </div>
        <svg class="chart-box" viewBox="0 0 760 180" preserveAspectRatio="none" aria-label="Courbe des revenus">
            <defs>
                <linearGradient id="chartFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#115fd6" stop-opacity=".22"/><stop offset="1" stop-color="#115fd6" stop-opacity="0"/></linearGradient>
            </defs>
            <g stroke="#e5ebf3" stroke-width="1">
                <line x1="0" y1="25" x2="760" y2="25"/><line x1="0" y1="65" x2="760" y2="65"/><line x1="0" y1="105" x2="760" y2="105"/><line x1="0" y1="145" x2="760" y2="145"/>
            </g>
            <path d="M0 150 C35 132 50 95 84 86 S128 20 166 46 S199 132 232 110 S267 104 292 66 S345 42 372 72 S420 74 442 96 S489 54 540 86 S588 153 634 128 S684 94 716 68 S744 28 760 24 L760 180 L0 180Z" fill="url(#chartFill)"/>
            <path d="M0 150 C35 132 50 95 84 86 S128 20 166 46 S199 132 232 110 S267 104 292 66 S345 42 372 72 S420 74 442 96 S489 54 540 86 S588 153 634 128 S684 94 716 68 S744 28 760 24" fill="none" stroke="#082c8a" stroke-width="4"/>
            <circle cx="760" cy="24" r="7" fill="#082c8a"/>
        </svg>
    </article>
</section>

<section class="payment-history vd-card">
    <div class="history-head">
        <h2>Historique des paiements</h2>
        <div style="display:flex; gap:12px; flex-wrap:wrap;"><button class="vd-btn"><i data-lucide="filter"></i> Filtres</button><button class="vd-btn"><i data-lucide="calendar-days"></i> 22 avr. - 22 mai 2024 <i data-lucide="chevron-down"></i></button></div>
    </div>
    <div class="vd-table-wrap">
        <table class="vd-table">
            <thead><tr><th>Date</th><th>Commande</th><th>Montant</th><th>Méthode</th><th>Statut</th></tr></thead>
            <tbody>
                @forelse($payments as $payment)
                    @php
                        $status = strtolower($payment->status ?? 'pending');
                        $tone = in_array($status, ['completed', 'paid', 'success']) ? 'green' : (in_array($status, ['failed', 'cancelled']) ? 'red' : (in_array($status, ['shipped']) ? 'blue' : 'orange'));
                        $label = $tone === 'green' ? 'Payé' : ($tone === 'red' ? 'Annulé' : ($tone === 'blue' ? 'Expédié' : 'En attente'));
                        $method = $payment->method ?? $payment->operator ?? 'PayDunya';
                        $date = \Carbon\Carbon::parse($payment->payment_date ?? $payment->created_at);
                    @endphp
                    <tr>
                        <td>{{ $date->format('d M Y, H:i') }}</td>
                        <td><strong>{{ $payment->order_reference ?? $payment->order?->order_number ?? '#OVN-'.$payment->id }}</strong><small style="display:block;color:#566176;margin-top:4px;">{{ $payment->order?->client?->name ?? 'Client OVANIE' }}</small></td>
                        <td class="{{ $tone === 'orange' || $tone === 'red' ? 'amount-orange' : 'amount-green' }}">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td>
                        <td><span class="payment-method"><span class="method-logo">{{ strtoupper(substr($method, 0, 1)) }}</span>{{ ucfirst($method) }}</span></td>
                        <td><span class="vd-status {{ $tone }}"><i data-lucide="{{ $tone === 'green' ? 'check-circle' : ($tone === 'red' ? 'x-circle' : ($tone === 'blue' ? 'truck' : 'clock')) }}"></i>{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">Aucun paiement pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="product-footer">
        <span>Affichage de 1 à {{ $payments->count() }} sur {{ $payments->count() }} transactions</span>
        <div class="vd-pagination"><span class="vd-page-dot active">1</span><span class="vd-page-dot">2</span><span class="vd-page-dot">3</span><span>...</span></div>
    </div>
</section>
@endsection
