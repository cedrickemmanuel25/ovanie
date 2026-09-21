@php
    $activeMode = $activeMode ?? 'quick';
    $workspaceShopId = $workspaceShopId ?? request('shop_id');
    $workspaceShop = $workspaceShop ?? null;
@endphp

@once
<style>
.product-workspace-nav{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 18px;padding:10px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 8px 26px rgba(15,23,42,.04)}
.product-workspace-tabs{display:flex;align-items:center;gap:7px;min-width:0}
.product-workspace-tab{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 13px;border:1px solid transparent;border-radius:10px;color:#53657f;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;transition:.16s ease}
.product-workspace-tab:hover{background:#f8fafc;color:#173e79}
.product-workspace-tab.active{background:#fff4eb;border-color:#fed7aa;color:#d95d0b}
.product-workspace-tab svg{width:17px;height:17px}
.product-workspace-back{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 13px;border:1px solid #dce4ed;border-radius:10px;background:#fff;color:#17345f;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap}
.product-workspace-back:hover{border-color:#f97316;color:#d95d0b}
.product-workspace-context{display:flex;align-items:center;gap:7px;min-width:0;margin-left:auto;color:#718096;font-size:11px}
.product-workspace-context strong{max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#17345f}
@media(max-width:760px){.product-workspace-nav{align-items:stretch;flex-direction:column}.product-workspace-tabs{display:grid;grid-template-columns:1fr 1fr;width:100%}.product-workspace-tab{justify-content:center}.product-workspace-context{order:-1;margin:0;padding:1px 4px}.product-workspace-back{justify-content:center;width:100%}}
@media(max-width:430px){.product-workspace-tab{padding:0 9px;font-size:11px}.product-workspace-context strong{max-width:180px}}
</style>
@endonce

<div class="product-workspace-nav">
    <div class="product-workspace-tabs" role="navigation" aria-label="Modes d’ajout de produit">
        <a class="product-workspace-tab {{ $activeMode === 'quick' ? 'active' : '' }}"
           href="{{ route('commercial.products.quick.create', ['shop_id' => $workspaceShopId]) }}"
           @if($activeMode === 'quick') aria-current="page" @endif>
            <i data-lucide="scan-search"></i>
            Ajout terrain
        </a>
        <a class="product-workspace-tab {{ $activeMode === 'full' ? 'active' : '' }}"
           href="{{ route('commercial.products.create', ['shop_id' => $workspaceShopId]) }}"
           @if($activeMode === 'full') aria-current="page" @endif>
            <i data-lucide="clipboard-list"></i>
            Formulaire complet
        </a>
    </div>

    <div class="product-workspace-context">
        <i data-lucide="store"></i>
        <span>Boutique :</span>
        <strong>{{ $workspaceShop?->name ?: 'à sélectionner' }}</strong>
    </div>

    <a class="product-workspace-back" href="{{ route('commercial.products.index', ['shop_id' => $workspaceShopId]) }}">
        <i data-lucide="arrow-left"></i>
        Retour aux produits
    </a>
</div>
