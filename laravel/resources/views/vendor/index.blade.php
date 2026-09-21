@extends('layouts.vendor')

@section('title', 'Catalogue produits | OVANIE Seller Central')

@section('styles')
<style>
/* ============================================================
   Catalogue produits — Design premium OVANIE
   ============================================================ */

/* PAGE HEADER */
.cat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.cat-header-left h1 {
    margin: 0 0 4px;
    font-size: 1.65rem;
    font-weight: 900;
    color: var(--ov-navy);
}

.cat-header-left p {
    margin: 0;
    color: #64748b;
    font-size: .93rem;
}

.cat-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.cat-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid;
    transition: all .18s;
    white-space: nowrap;
}

.cat-btn svg { width: 17px; height: 17px; }

.cat-btn.outline {
    background: #fff;
    color: var(--ov-navy);
    border-color: #d1d9e6;
}

.cat-btn.outline:hover { background: #f8fafc; }

.cat-btn.primary {
    background: linear-gradient(135deg, #ff7a00, #ff4d00);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 18px rgba(255,77,0,.28);
}

.cat-btn.primary:hover {
    box-shadow: 0 8px 24px rgba(255,77,0,.38);
    transform: translateY(-1px);
}

/* ALERTS */
.cat-alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: .92rem;
    font-weight: 600;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.cat-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.cat-alert.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* FILTER TABS */
.cat-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 22px;
    flex-wrap: wrap;
    align-items: center;
}

.cat-tab {
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s;
}

.cat-tab:hover { background: #f8fafc; border-color: #cbd5e1; }

.cat-tab.active {
    background: var(--ov-orange);
    color: #fff;
    border-color: var(--ov-orange);
    box-shadow: 0 4px 12px rgba(255,77,0,.25);
}

.cat-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: .74rem;
    font-weight: 800;
    background: rgba(255,255,255,.25);
    margin-left: 4px;
}

.cat-tab:not(.active) .cat-tab-count {
    background: #f1f5f9;
    color: #475569;
}

/* SEARCH + FILTER BAR */
.cat-search-row {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.cat-search-wrap {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.cat-search-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #94a3b8;
    pointer-events: none;
}

.cat-search-wrap input {
    width: 100%;
    padding: 11px 14px 11px 42px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: .93rem;
    color: var(--ov-navy);
    background: #fff;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
}

.cat-search-wrap input:focus {
    border-color: var(--ov-orange);
    box-shadow: 0 0 0 3px rgba(255,77,0,.1);
}

.cat-filter-select {
    position: relative;
    min-width: 220px;
}

.cat-filter-select svg {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: #94a3b8;
    pointer-events: none;
}

.cat-filter-select select {
    width: 100%;
    padding: 11px 36px 11px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: .93rem;
    color: var(--ov-navy);
    background: #fff;
    appearance: none;
    outline: none;
    cursor: pointer;
    transition: border-color .18s;
}

.cat-filter-select select:focus { border-color: var(--ov-orange); }

/* TABLE CARD */
.cat-table-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 8px 28px rgba(7,23,57,.06);
    overflow: hidden;
}

/* TABLE TOOLBAR */
.cat-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    gap: 12px;
    flex-wrap: wrap;
}

.cat-toolbar-info {
    color: #64748b;
    font-size: .87rem;
    font-weight: 500;
}

.cat-toolbar-info strong { color: var(--ov-navy); }

.cat-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cat-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font-size: .84rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s;
}

.cat-action-btn:hover { background: #f1f5f9; border-color: #cbd5e1; color: var(--ov-navy); }
.cat-action-btn svg { width: 14px; height: 14px; }

/* PRODUCTS TABLE */
.cat-table {
    width: 100%;
    border-collapse: collapse;
}

.cat-table thead th {
    padding: 13px 16px;
    text-align: left;
    font-size: .78rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.cat-table tbody td {
    padding: 14px 16px;
    font-size: .9rem;
    color: var(--ov-navy);
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.cat-table tbody tr:last-child td { border-bottom: none; }

.cat-table tbody tr:hover { background: #fafbfc; }

/* PRODUCT NAME CELL */
.cat-prod-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cat-prod-img {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
    background: #f8fafc;
}

.cat-prod-img-placeholder {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
    flex-shrink: 0;
}

.cat-prod-img-placeholder svg { width: 22px; height: 22px; }

.cat-prod-name {
    font-weight: 700;
    color: var(--ov-navy);
    font-size: .92rem;
    line-height: 1.3;
    max-width: 200px;
}

.cat-prod-id {
    font-size: .76rem;
    color: #94a3b8;
    margin-top: 3px;
    font-weight: 500;
}

/* PRICE CELL */
.cat-price {
    font-weight: 700;
    color: var(--ov-navy);
}

.cat-promo-price {
    font-size: .8rem;
    color: #16a34a;
    font-weight: 600;
}

.cat-no-val {
    color: #cbd5e1;
    font-size: .85rem;
}

/* STOCK BADGE */
.cat-stock {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: .82rem;
    font-weight: 700;
}

.cat-stock.ok     { background: #ecfdf5; color: #065f46; }
.cat-stock.low    { background: #fffbeb; color: #92400e; }
.cat-stock.empty  { background: #fef2f2; color: #b91c1c; }

/* TOGGLE */
.cat-toggle {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}

.cat-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.cat-toggle-track {
    position: absolute;
    inset: 0;
    background: #cbd5e1;
    border-radius: 999px;
    cursor: pointer;
    transition: background .25s;
}

.cat-toggle-track::before {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform .25s;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
}

.cat-toggle input:checked + .cat-toggle-track { background: #16a34a; }
.cat-toggle input:checked + .cat-toggle-track::before { transform: translateX(18px); }

/* ROW ACTIONS */
.cat-row-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cat-row-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
}

.cat-row-btn:hover { background: #f1f5f9; color: var(--ov-orange); border-color: #fdb898; }
.cat-row-btn.boost:hover { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.cat-row-btn svg { width: 16px; height: 16px; }

/* EMPTY STATE */
.cat-empty {
    text-align: center;
    padding: 64px 20px;
    color: #94a3b8;
}

.cat-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 999px;
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.cat-empty-icon svg { width: 30px; height: 30px; color: #cbd5e1; }

.cat-empty h3 {
    margin: 0 0 8px;
    font-size: 1.1rem;
    color: #475569;
    font-weight: 700;
}

.cat-empty p {
    margin: 0 0 20px;
    font-size: .9rem;
}

/* TABLE FOOTER */
.cat-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    gap: 12px;
    flex-wrap: wrap;
}

.cat-table-footer-info {
    color: #64748b;
    font-size: .87rem;
}

/* BOOST MODAL */
.boost-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(7,23,57,.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}

.boost-overlay.open { display: flex; }

.boost-modal {
    width: 100%;
    max-width: 500px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 30px 80px rgba(7,23,57,.22);
    overflow: hidden;
    animation: modalIn .22s ease;
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.boost-modal-head {
    padding: 22px 24px 18px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.boost-modal-head h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ov-navy);
    display: flex;
    align-items: center;
    gap: 10px;
}

.boost-modal-head h3 svg { width: 22px; height: 22px; color: #1d4ed8; }

.boost-close-btn {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    color: #64748b;
    font-size: 1.2rem;
    transition: all .18s;
}

.boost-close-btn:hover { background: #f1f5f9; color: var(--ov-navy); }

.boost-modal-body { padding: 20px 24px; }

.boost-product-name {
    font-size: 1rem;
    font-weight: 800;
    color: var(--ov-navy);
    margin: 0 0 6px;
}

.boost-amount-info {
    color: #c2410c;
    font-weight: 700;
    font-size: .92rem;
    margin: 0 0 18px;
}

.boost-field { margin-bottom: 16px; }

.boost-field label {
    display: block;
    font-size: .85rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 7px;
}

.boost-field select,
.boost-field input {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: .93rem;
    color: var(--ov-navy);
    background: #fff;
    outline: none;
    box-sizing: border-box;
}

.boost-pay-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff7a00, #ff4d00);
    color: #fff;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(255,77,0,.28);
    transition: all .18s;
}

.boost-pay-btn:hover { box-shadow: 0 8px 24px rgba(255,77,0,.38); transform: translateY(-1px); }

#boostPaymentMessage {
    margin-top: 10px;
    text-align: center;
    font-size: .88rem;
    font-weight: 600;
}
</style>
@endsection

@section('content')

{{-- HEADER --}}
<div class="cat-header">
    <div class="cat-header-left">
        <h1>Catalogue des produits</h1>
        <p>{{ $products->total() }} produit(s) dans votre boutique</p>
    </div>
    <div class="cat-header-actions">
        <a href="{{ route('vendor.add_product', ['type' => 'single']) }}" class="cat-btn primary" id="btn-add-product">
            <i data-lucide="plus"></i> Ajouter un produit
        </a>
        <button class="cat-btn outline" id="btn-import">
            <i data-lucide="upload"></i> Importer
        </button>
    </div>
</div>

{{-- ALERTS --}}
@if(session('success'))
    <div class="cat-alert success">
        <i data-lucide="check-circle"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="cat-alert error">
        <i data-lucide="x-circle"></i>
        {{ session('error') }}
    </div>
@endif

{{-- FILTER TABS --}}
<div class="cat-tabs">
    <button class="cat-tab active" data-filter="all">
        Tous <span class="cat-tab-count">{{ $products->total() }}</span>
    </button>
    <button class="cat-tab" data-filter="active">Actif</button>
    <button class="cat-tab" data-filter="inactive">Inactif</button>
    <button class="cat-tab" data-filter="pending">En attente</button>
    <button class="cat-tab" data-filter="approved">Approuvé</button>
    <button class="cat-tab" data-filter="rejected">Rejeté</button>
</div>

{{-- SEARCH & FILTER --}}
<div class="cat-search-row">
    <div class="cat-search-wrap">
        <i data-lucide="search"></i>
        <input type="text" id="cat-search-input" placeholder="Rechercher par nom ou ID produit…" autocomplete="off">
    </div>
    <div class="cat-filter-select">
        <select id="cat-category-filter">
            <option value="">Toutes les catégories</option>
            @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        <i data-lucide="chevron-down"></i>
    </div>
</div>

{{-- TABLE CARD --}}
<div class="cat-table-card">

    {{-- TOOLBAR --}}
    <div class="cat-toolbar">
        <span class="cat-toolbar-info">
            <strong>{{ $products->count() }}</strong> produit(s) affichés sur <strong>{{ $products->total() }}</strong>
        </span>
        <div class="cat-toolbar-actions">
            <button class="cat-action-btn" id="bulk-activate">
                <i data-lucide="power"></i> Activer la sélection
            </button>
            <button class="cat-action-btn" id="bulk-delete">
                <i data-lucide="trash-2"></i> Supprimer
            </button>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="vd-table-wrap">
        <table class="cat-table">
            <thead>
                <tr>
                    <th style="width:44px;"><input type="checkbox" id="select-all"></th>
                    <th>Produit</th>
                    <th>Prix</th>
                    <th>Promo</th>
                    <th>Livraison</th>
                    <th>Stock</th>
                    <th>Actif</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="products-tbody">
                @forelse($products as $product)
                    @php
                        $mainImage = $product->images->where('is_main', true)->first();
                        $stock = $product->stock ?? 0;
                        $stockClass = $stock === 0 ? 'empty' : ($stock <= 5 ? 'low' : 'ok');
                        $stockLabel = $stock === 0 ? 'Rupture' : ($stock <= 5 ? 'Faible' : 'En stock');
                    @endphp
                    <tr data-product-id="{{ $product->id }}">
                        <td><input type="checkbox" class="row-check" value="{{ $product->id }}"></td>

                        {{-- NOM --}}
                        <td>
                            <div class="cat-prod-cell">
                                @if($mainImage)
                                    <img src="{{ asset('storage/'.$mainImage->path) }}"
                                         alt="{{ $product->name }}"
                                         class="cat-prod-img">
                                @else
                                    <div class="cat-prod-img-placeholder">
                                        <i data-lucide="image"></i>
                                    </div>
                                @endif
                                <div>
                                    <div class="cat-prod-name">{{ $product->name }}</div>
                                    <div class="cat-prod-id">#{{ $product->id }} · {{ $product->created_at->format('d M Y') }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- PRIX --}}
                        <td>
                            <span class="cat-price">{{ number_format($product->price, 0, ',', ' ') }} XOF</span>
                        </td>

                        {{-- PROMO --}}
                        <td>
                            @if($product->promo_price)
                                <span class="cat-promo-price">{{ number_format($product->promo_price, 0, ',', ' ') }} XOF</span>
                            @else
                                <span class="cat-no-val">—</span>
                            @endif
                        </td>

                        {{-- LIVRAISON --}}
                        <td>
                            @if($product->transport)
                                {{ number_format($product->transport, 0, ',', ' ') }} XOF
                            @else
                                <span class="cat-no-val">—</span>
                            @endif
                        </td>

                        {{-- STOCK --}}
                        <td>
                            <span class="cat-stock {{ $stockClass }}">
                                <i data-lucide="{{ $stock === 0 ? 'x' : ($stock <= 5 ? 'alert-triangle' : 'check') }}"></i>
                                {{ $stock }} · {{ $stockLabel }}
                            </span>
                        </td>

                        {{-- TOGGLE ACTIF --}}
                        <td>
                            <label class="cat-toggle">
                                <input type="checkbox"
                                    {{ $product->is_active ? 'checked' : '' }}
                                    onchange="toggleProductStatus({{ $product->id }}, this.checked)">
                                <span class="cat-toggle-track"></span>
                            </label>
                        </td>

                        {{-- ACTIONS --}}
                        <td>
                            <div class="cat-row-actions" style="justify-content:flex-end;">
                                <a href="{{ route('vendor.products.edit', $product->id) }}"
                                   class="cat-row-btn" title="Modifier">
                                    <i data-lucide="edit-2"></i>
                                </a>
                                <button class="cat-row-btn boost open-boost-modal"
                                    data-product-id="{{ $product->id }}"
                                    data-product-name="{{ $product->name }}"
                                    data-boost-price="{{ $boostPrice ?? 5000 }}"
                                    title="Booster">
                                    <i data-lucide="zap"></i>
                                </button>
                                <button class="cat-row-btn"
                                    onclick="copyProductId({{ $product->id }})"
                                    title="Copier l'ID">
                                    <i data-lucide="copy"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="cat-empty">
                                <div class="cat-empty-icon">
                                    <i data-lucide="package-open"></i>
                                </div>
                                <h3>Aucun produit dans votre catalogue</h3>
                                <p>Commencez par ajouter votre premier produit.</p>
                                <a href="{{ route('vendor.add_product') }}" class="cat-btn primary">
                                    <i data-lucide="plus"></i> Ajouter un produit
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- FOOTER + PAGINATION --}}
    <div class="cat-table-footer">
        <span class="cat-table-footer-info">
            Page {{ $products->currentPage() }} sur {{ $products->lastPage() }}
        </span>
        <div>
            {{ $products->links() }}
        </div>
    </div>

</div>{{-- end cat-table-card --}}

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- BOOST MODAL                                            --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<div id="boostOverlay" class="boost-overlay">
    <div class="boost-modal">
        <div class="boost-modal-head">
            <h3><i data-lucide="zap"></i> Booster ce produit</h3>
            <button class="boost-close-btn" id="closeBoost">&times;</button>
        </div>
        <div class="boost-modal-body">
            <p class="boost-product-name" id="boostProductName"></p>
            <p class="boost-amount-info">Montant : <span id="boostAmount"></span> FCFA</p>

            <form id="boostForm">
                @csrf
                <input type="hidden" id="boostProductId">

                <div class="boost-field">
                    <label for="boostPackage">Pack de boost</label>
                    <select id="boostPackage" required>
                        @foreach($boostPackages ?? [] as $key => $pack)
                            <option value="{{ $key }}" data-price="{{ $pack['price'] }}">
                                {{ $pack['label'] }} — {{ number_format($pack['price'],0,',',' ') }} FCFA
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="boost-field">
                    <label for="boostPhone">Numéro Mobile Money</label>
                    <input type="tel" id="boostPhone" placeholder="Ex : 07 00 00 00 00">
                </div>

                <button type="submit" class="boost-pay-btn">
                    <i data-lucide="credit-card"></i> Payer maintenant
                </button>
                <p id="boostPaymentMessage"></p>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    /* ── SELECT ALL ─────────────────────────────────────── */
    const selectAll = document.getElementById('select-all');
    const rowChecks = () => document.querySelectorAll('.row-check');

    selectAll?.addEventListener('change', () => {
        rowChecks().forEach(cb => cb.checked = selectAll.checked);
    });

    /* ── CLIENT-SIDE SEARCH (live filter) ───────────────── */
    document.getElementById('cat-search-input')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#products-tbody tr').forEach(row => {
            const name = row.querySelector('.cat-prod-name')?.textContent?.toLowerCase() ?? '';
            const id   = row.querySelector('.cat-prod-id')?.textContent?.toLowerCase()  ?? '';
            row.style.display = (name.includes(q) || id.includes(q)) ? '' : 'none';
        });
    });

    /* ── BOOST MODAL ────────────────────────────────────── */
    const overlay      = document.getElementById('boostOverlay');
    const closeBtn     = document.getElementById('closeBoost');
    const prodNameEl   = document.getElementById('boostProductName');
    const amountEl     = document.getElementById('boostAmount');
    const productIdEl  = document.getElementById('boostProductId');
    const packageSel   = document.getElementById('boostPackage');
    const msgEl        = document.getElementById('boostPaymentMessage');

    function refreshAmount() {
        const sel = packageSel?.options[packageSel.selectedIndex];
        if (sel) amountEl.textContent = Number(sel.dataset.price).toLocaleString('fr-FR');
    }

    document.querySelectorAll('.open-boost-modal').forEach(btn => {
        btn.addEventListener('click', function () {
            productIdEl.value      = this.dataset.productId;
            prodNameEl.textContent = this.dataset.productName;
            msgEl.textContent      = '';
            refreshAmount();
            overlay.classList.add('open');
        });
    });

    packageSel?.addEventListener('change', refreshAmount);

    closeBtn?.addEventListener('click', () => overlay.classList.remove('open'));
    overlay?.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });

    document.getElementById('boostForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const csrf = this.querySelector('input[name="_token"]').value;
        try {
            const res  = await fetch(`/daniel/products/${productIdEl.value}/boost/pay`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ boost_package: packageSel.value, phone: document.getElementById('boostPhone').value })
            });
            const data = await res.json();
            if (data.success && data.redirect_url) { window.location.href = data.redirect_url; return; }
            msgEl.textContent = data.message || 'Impossible de lancer le paiement.';
            msgEl.style.color = '#b91c1c';
        } catch {
            msgEl.textContent = 'Erreur réseau. Veuillez réessayer.';
            msgEl.style.color = '#b91c1c';
        }
    });

    /* ── COPY PRODUCT ID ────────────────────────────────── */
    window.copyProductId = function (id) {
        navigator.clipboard?.writeText(String(id));
    };

    /* ── TOGGLE STATUS (AJAX) ───────────────────────────── */
    window.toggleProductStatus = function (id, active) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(`/daniel/products/${id}/toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ is_active: active })
        }).catch(() => {});
    };
});
</script>
@endsection