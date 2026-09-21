@extends('layouts.vendor')

@section('title', 'Commandes clients | OVANIE')

@section('styles')
<style>
.orders-controls { margin: 18px 0; display: flex; justify-content: space-between; gap: 18px; align-items: center; }
.order-tabs { margin-top: 22px; padding: 0 24px; display: flex; align-items: center; gap: 34px; overflow-x: auto; }
.order-tab { min-height: 54px; display: inline-flex; align-items: center; gap: 9px; color: #10182f; text-decoration: none; font-weight: 800; border-bottom: 3px solid transparent; white-space: nowrap; }
.order-tab.active { color: var(--ov-orange); border-bottom-color: var(--ov-orange); }
.order-tab span { min-width: 32px; height: 25px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #eef1f6; padding: 0 8px; font-size: .86rem; }
.order-card { overflow: hidden; }
.order-product { display: flex; align-items: center; gap: 14px; }
.order-product img, .client-avatar { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: #eef1f6; }
.client-line { display: flex; align-items: center; gap: 12px; }
.client-avatar { border-radius: 999px; }
.client-line strong, .order-product strong { display: block; }
.client-line small, .order-product small, .order-date small { display: block; margin-top: 5px; color: #566176; }
.order-date { line-height: 1.2; }
.orders-controls .vd-search { width: min(560px, 50vw); }
@media (max-width: 900px) { .orders-controls { flex-direction: column; align-items: stretch; } .orders-controls .vd-search { width: 100%; } }
</style>
@endsection

@section('content')
@php
    $shopId = auth()->user()->shop->id ?? null;
    $statusCounts = ['Tous' => $orders->count(), 'En attente' => 0, 'Validée' => 0, 'Payée' => 0, 'Expédiée' => 0, 'Livrée' => 0, 'Annulée' => 0];
    foreach ($orders as $order) {
        $status = strtolower($order->status ?? '');
        if (in_array($status, ['pending', 'en attente'])) $statusCounts['En attente']++;
        if (in_array($status, ['validated', 'validée'])) $statusCounts['Validée']++;
        if (in_array($status, ['paid', 'payée', 'completed'])) $statusCounts['Payée']++;
        if (in_array($status, ['shipped', 'expédiée'])) $statusCounts['Expédiée']++;
        if (in_array($status, ['delivered', 'livrée'])) $statusCounts['Livrée']++;
        if (in_array($status, ['cancelled', 'annulée'])) $statusCounts['Annulée']++;
    }
@endphp

<section class="vd-page-head">
    <div><h1>Commandes clients</h1></div>
    <button type="button" class="vd-btn" onclick="window.print()"><i data-lucide="printer"></i> Imprimer</button>
</section>

<section class="order-card vd-card">
    <nav class="order-tabs">
        @foreach($statusCounts as $label => $count)
            <a href="#" class="order-tab {{ $loop->first ? 'active' : '' }}">{{ $label }} <span>{{ $count }}</span></a>
        @endforeach
    </nav>
</section>

<div class="orders-controls">
    <label class="vd-search">
        <i data-lucide="search"></i>
        <input id="searchInput" class="vd-input" type="search" placeholder="Rechercher par n° de commande, client ou produit...">
    </label>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <button class="vd-btn" type="button"><i data-lucide="filter"></i> Filtres</button>
        <button class="vd-btn" type="button">Trier par : Plus récent <i data-lucide="chevron-down"></i></button>
    </div>
</div>

<section class="vd-card order-card">
    <div class="vd-table-wrap">
        <table class="vd-table" id="ordersTable">
            <thead>
                <tr>
                    <th>#Commande</th>
                    <th>Client</th>
                    <th>Produit(s)</th>
                    <th>Quantité</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $shopItems = $shopId ? $order->items->where('shop_id', $shopId) : $order->items;
                        $firstItem = $shopItems->first();
                        $product = $firstItem?->product;
                        $status = strtolower($order->status ?? 'pending');
                        $tone = in_array($status, ['completed', 'delivered', 'livrée', 'paid', 'payée']) ? 'green' : (in_array($status, ['cancelled', 'annulée']) ? 'red' : (in_array($status, ['shipped', 'expédiée']) ? 'blue' : 'orange'));
                        $label = $tone === 'green' ? (in_array($status, ['paid', 'payée']) ? 'Payée' : 'Livrée') : ($tone === 'red' ? 'Annulée' : ($tone === 'blue' ? 'Expédiée' : 'En attente'));
                    @endphp
                    <tr>
                        <td>{{ $order->order_number ?? '#OVN-2024-'.$order->id }}</td>
                        <td>
                            <div class="client-line">
                                <img class="client-avatar" src="https://i.pravatar.cc/96?u={{ $order->client->email ?? $order->id }}" alt="">
                                <span><strong>{{ $order->client->name ?? 'Client OVANIE' }}</strong><small>{{ $order->client->city ?? $order->client->phone ?? '—' }}</small></span>
                            </div>
                        </td>
                        <td>
                            <div class="order-product">
                                <img src="{{ $product?->mainImageUrl ?? asset('storage/products/placeholder.png') }}" alt="">
                                <span><strong>{{ $product->name ?? 'Produit supprimé' }}</strong><small>{{ $firstItem->variant ?? 'Noir' }}</small></span>
                            </div>
                        </td>
                        <td>{{ $shopItems->sum('quantity') }}</td>
                        <td>{{ number_format($shopItems->sum('subtotal'), 0, ',', ' ') }} FCFA</td>
                        <td><span class="vd-status {{ $tone }}"><i data-lucide="{{ $tone === 'blue' ? 'truck' : ($tone === 'orange' ? 'clock' : ($tone === 'red' ? 'x-circle' : 'check-circle')) }}"></i>{{ $label }}</span></td>
                        <td><span class="order-date">{{ $order->created_at->format('d M Y') }}<small>{{ $order->created_at->format('H:i') }}</small></span></td>
                        <td><a href="{{ route('daniel.orders.show', $order->id) }}" class="vd-btn ghost-orange">Voir détail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-cell">Aucune commande trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="product-footer">
        <span>Affichage de 1 à {{ $orders->count() }} sur {{ $orders->count() }} commandes</span>
        <div class="vd-pagination"><span class="vd-page-dot active">1</span><span class="vd-page-dot">2</span><span class="vd-page-dot">3</span><span>...</span></div>
    </div>
</section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const rows = Array.from(document.querySelectorAll('#ordersTable tbody tr'));
    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        rows.forEach(row => row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none');
    });
});
</script>
@endsection
