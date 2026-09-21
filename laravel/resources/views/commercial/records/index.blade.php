@extends('layouts.staff')
@section('title', $title . ' | Commercial OVANIE')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        <p class="page-subtitle">Données synchronisées avec les modules clients, vendeurs, produits, commandes et OVANIE Pro.</p>
    </div>
    <div class="page-actions">
        @if($type === 'client')<a class="btn btn-orange" href="{{ route('commercial.clients.create') }}"><i data-lucide="user-plus"></i>Créer un client</a>@endif
        @if($type === 'vendor')<a class="btn btn-orange" href="{{ route('commercial.vendors.create') }}"><i data-lucide="store"></i>Créer un vendeur</a>@endif
        @if($type === 'product')<a class="btn btn-orange" href="#boutiques"><i data-lucide="store"></i>Choisir une boutique</a>@endif
        @if(auth()->user()->hasStaffPermission('leads.write'))<a class="btn btn-orange" href="{{ route('commercial.leads.create') }}"><i data-lucide="circle-plus"></i>Nouvelle opportunité</a>@endif
    </div>
</div>
@if($type === 'product')
<style>
    .shop-picker{margin-bottom:16px}.shop-picker-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}.shop-picker-head h2{margin:0;font-size:15px}.shop-picker-head p{margin:4px 0 0;color:var(--muted);font-size:10px}.shop-picker-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}.shop-choice{display:flex;align-items:center;gap:11px;padding:13px;border:1px solid #e0e7f0;border-radius:9px;background:#fff}.shop-choice-icon{width:38px;height:38px;flex:0 0 38px;border-radius:9px;display:grid;place-items:center;background:#fff3e9;color:var(--orange)}.shop-choice-icon svg{width:19px}.shop-choice-copy{min-width:0;flex:1}.shop-choice-copy strong,.shop-choice-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.shop-choice-copy strong{font-size:11px}.shop-choice-copy span{margin-top:3px;color:#8490a2;font-size:9px}.shop-choice .btn{flex:0 0 auto}@media(max-width:1050px){.shop-picker-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.shop-picker-grid{grid-template-columns:1fr}.shop-choice{align-items:flex-start;flex-wrap:wrap}.shop-choice-copy{min-width:170px}.shop-choice .btn{width:100%}}
</style>
<section class="card shop-picker" id="boutiques">
    <div class="shop-picker-head"><div><h2>Choisir une boutique</h2><p>Sélectionnez la boutique dans laquelle vous souhaitez ajouter un produit.</p></div><span class="status active">{{ $shops->count() }} boutique(s)</span></div>
    <div class="shop-picker-grid">
        @forelse($shops as $shop)
            <article class="shop-choice">
                <span class="shop-choice-icon"><i data-lucide="store"></i></span>
                <div class="shop-choice-copy"><strong>{{ $shop->name }}</strong><span>{{ $shop->user?->name ?: $shop->user?->email }} · {{ $shop->products_count }} produit(s)</span></div>
                <a class="btn btn-orange" href="{{ route('commercial.products.quick.create', ['shop_id' => $shop->id]) }}"><i data-lucide="package-plus"></i>Ajouter</a>
            </article>
        @empty
            <div class="empty" style="grid-column:1/-1"><i data-lucide="store"></i><div>Aucune boutique ne vous est attribuée.</div><a class="btn btn-orange" style="margin-top:12px" href="{{ route('commercial.vendors.create') }}">Créer un vendeur et sa boutique</a></div>
        @endforelse
    </div>
</section>
@endif
<form class="filters" method="GET">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un nom, un produit, une référence…">
    <button class="btn" type="submit"><i data-lucide="search"></i>Rechercher</button>
    @if(request('q'))<a class="btn" href="{{ url()->current() }}">Réinitialiser</a>@endif
</form>
<section class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Dossier</th><th>Contact / source</th><th>Informations</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><span class="record-title">{{ $record['primary'] ?: 'Sans libellé' }}</span></td>
                    <td>{{ $record['secondary'] ?: 'Non renseigné' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string)($record['detail'] ?? ''), 90) }}</td>
                    <td><span class="status {{ strtolower((string)$record['status']) }}">{{ str_replace('_',' ',(string)$record['status']) }}</span></td>
                    <td>{{ optional($record['created_at'])->format('d/m/Y H:i') }}</td>
                    <td>@if(auth()->user()->hasStaffPermission('leads.write'))<a class="btn" href="{{ route('commercial.leads.create', array_filter($record['lead_query'] ?? [], fn($v) => $v !== null && $v !== '')) }}"><i data-lucide="target"></i>Créer une opportunité</a>@else<span class="record-sub">Lecture seule</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i data-lucide="database"></i><div>Aucune donnée réelle disponible pour ce module.</div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
        <div class="pagination-row">
            <span>{{ $records->firstItem() }}–{{ $records->lastItem() }} sur {{ $records->total() }}</span>
            <div class="pagination-actions">
                @if($records->onFirstPage())<span class="btn" style="opacity:.45">Précédent</span>@else<a class="btn" href="{{ $records->previousPageUrl() }}">Précédent</a>@endif
                @if($records->hasMorePages())<a class="btn" href="{{ $records->nextPageUrl() }}">Suivant</a>@else<span class="btn" style="opacity:.45">Suivant</span>@endif
            </div>
        </div>
    @endif
</section>
@endsection
