@extends('layouts.staff')

@section('title', 'Suivi des commandes | Commercial OVANIE')

@section('inline_styles')
    .order-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}
    .order-filters{display:grid;grid-template-columns:minmax(260px,1fr) 200px auto auto;gap:10px;margin:16px 0}
    .order-filters input,.order-filters select{height:42px;border:1px solid #dbe3ed;border-radius:9px;background:#fff;padding:0 12px;color:#233d62;font-size:10.5px;outline:none}
    .order-filters input:focus,.order-filters select:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.08)}
    .order-list{display:grid;gap:12px}
    .order-card{display:grid;grid-template-columns:minmax(170px,.8fr) minmax(210px,1fr) minmax(230px,1.2fr) 150px 150px;gap:16px;align-items:center;padding:17px 18px;border:1px solid #dfe7f0;border-radius:13px;background:#fff;box-shadow:0 7px 24px rgba(16,38,79,.04)}
    .order-reference strong{display:block;color:#10264f;font-size:11.5px}.order-reference span{display:block;margin-top:5px;color:#8190a3;font-size:8.8px}
    .order-client strong{display:block;color:#17345f;font-size:10px}.order-client span{display:block;margin-top:4px;color:#7d899c;font-size:8.9px;line-height:1.45}
    .order-products strong{display:block;color:#243f65;font-size:9.8px;line-height:1.45}.order-products span{display:block;margin-top:4px;color:#8190a3;font-size:8.7px}
    .order-finance strong{display:block;color:#10264f;font-size:12px;white-space:nowrap}.order-finance span{display:block;margin-top:5px;color:#8190a3;font-size:8.7px}
    .order-state{display:grid;gap:7px;justify-items:start}.order-state .status{font-size:8.5px}.order-date{color:#7b889b;font-size:8.8px}
    .order-actions{display:flex;justify-content:flex-end}.order-actions .btn{min-height:36px;padding:0 11px;font-size:9px;white-space:nowrap}
    .order-empty{text-align:center;padding:58px 24px}.order-empty-icon{width:48px;height:48px;display:grid;place-items:center;margin:0 auto 12px;border-radius:13px;background:#eef4ff;color:#174b8f}.order-empty-icon svg{width:23px}.order-empty h3{margin:0;color:#10264f;font-size:13px}.order-empty p{max-width:430px;margin:6px auto 0;color:#7d899b;font-size:9.7px;line-height:1.55}
    .pagination-row{padding-top:14px}
    @media(max-width:1180px){.order-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.order-filters{grid-template-columns:1fr 190px auto}.order-filters .reset{grid-column:1/-1;justify-self:start}.order-card{grid-template-columns:1fr 1fr 1fr}.order-actions{justify-content:flex-start}.order-state{justify-items:start}}
    @media(max-width:760px){.order-kpis{grid-template-columns:1fr 1fr}.order-filters{grid-template-columns:1fr}.order-filters input,.order-filters select,.order-filters .btn{width:100%}.order-card{grid-template-columns:1fr 1fr;gap:13px;padding:15px}.order-products{grid-column:1/-1;padding-top:12px;border-top:1px solid #edf1f6}.order-actions{grid-column:1/-1}.order-actions .btn{width:100%}}
    @media(max-width:480px){.order-kpis{grid-template-columns:1fr}.order-card{grid-template-columns:1fr}.order-products,.order-actions{grid-column:auto}.order-client,.order-products,.order-finance,.order-state{padding-top:11px;border-top:1px solid #edf1f6}}
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Suivi des commandes</h1>
        <p class="page-subtitle">Consultez l’avancement des commandes liées à vos clients et aux boutiques de votre portefeuille.</p>
    </div>
</div>

<div class="grid kpi-grid order-kpis">
    <article class="kpi-card">
        <div class="kpi-head"><span class="kpi-label">Commandes suivies</span><span class="kpi-icon"><i data-lucide="clipboard-list"></i></span></div>
        <div class="kpi-value">{{ $stats['total'] }}</div>
        <div class="kpi-foot">Commandes de votre portefeuille.</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-head"><span class="kpi-label">En cours</span><span class="kpi-icon"><i data-lucide="clock-3"></i></span></div>
        <div class="kpi-value">{{ $stats['open'] }}</div>
        <div class="kpi-foot">Commandes en traitement.</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-head"><span class="kpi-label">Terminées</span><span class="kpi-icon"><i data-lucide="circle-check-big"></i></span></div>
        <div class="kpi-value">{{ $stats['completed'] }}</div>
        <div class="kpi-foot">Commandes finalisées.</div>
    </article>
    <article class="kpi-card">
        <div class="kpi-head"><span class="kpi-label">Annulées</span><span class="kpi-icon"><i data-lucide="circle-x"></i></span></div>
        <div class="kpi-value">{{ $stats['cancelled'] }}</div>
        <div class="kpi-foot">Commandes annulées.</div>
    </article>
</div>

<form class="order-filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Numéro de commande, client, téléphone ou produit…">
    <select name="status">
        <option value="">Tous les statuts</option>
        @foreach(['pending'=>'En attente','confirmed'=>'Confirmée','paid'=>'Payée','shipped'=>'Expédiée','completed'=>'Terminée','cancelled'=>'Annulée'] as $value=>$label)
            <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
    @if(request()->hasAny(['q','status']))
        <a class="btn reset" href="{{ route('commercial.orders.index') }}"><i data-lucide="rotate-ccw"></i>Réinitialiser</a>
    @endif
</form>

@if($orders->isEmpty())
    <section class="card order-empty">
        <span class="order-empty-icon"><i data-lucide="clipboard-list"></i></span>
        <h3>Aucune commande à afficher</h3>
        <p>Les commandes de vos clients et boutiques apparaîtront ici dès qu’elles seront enregistrées.</p>
    </section>
@else
    <section class="order-list">
        @foreach($orders as $order)
            <article class="order-card">
                <div class="order-reference">
                    <strong>{{ $order['order_number'] }}</strong>
                    <span>{{ optional($order['created_at'])->format('d/m/Y à H:i') }}</span>
                </div>

                <div class="order-client">
                    <strong>{{ $order['client_name'] }}</strong>
                    <span>{{ $order['client_phone'] ?: $order['client_email'] ?: 'Coordonnée non renseignée' }}</span>
                </div>

                <div class="order-products">
                    <strong>{{ $order['products']->isNotEmpty() ? $order['products']->implode(', ') : 'Produit non renseigné' }}</strong>
                    <span>{{ $order['products_count'] }} ligne(s) de commande</span>
                </div>

                <div class="order-finance">
                    <strong>{{ number_format($order['amount'], 0, ',', ' ') }} FCFA</strong>
                    <span>{{ $order['payment_status'] ? 'Paiement : '.str_replace('_', ' ', $order['payment_status']) : 'Paiement non renseigné' }}</span>
                </div>

                <div class="order-state">
                    <span class="status {{ strtolower((string) $order['status']) }}">{{ str_replace('_', ' ', (string) $order['status']) }}</span>
                    @if($order['can_follow_up'] && auth()->user()->hasStaffPermission('leads.write'))
                        <div class="order-actions">
                            <a class="btn" href="{{ route('commercial.leads.create', array_filter($order['lead_query'], fn($value) => $value !== null && $value !== '')) }}"><i data-lucide="phone-forwarded"></i>Créer une relance</a>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </section>

    @if($orders->hasPages())
        <div class="pagination-row">
            <span>{{ $orders->firstItem() }}–{{ $orders->lastItem() }} sur {{ $orders->total() }}</span>
            <div class="pagination-actions">
                @if($orders->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $orders->previousPageUrl() }}">Précédent</a>@endif
                @if($orders->hasMorePages())<a class="btn" href="{{ $orders->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif
            </div>
        </div>
    @endif
@endif
@endsection
