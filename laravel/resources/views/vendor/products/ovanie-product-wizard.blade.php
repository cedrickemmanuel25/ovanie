@php
    use Illuminate\Support\Facades\Route;

    $product = $product ?? null;
    $isEdit = (bool) ($isEdit ?? false);
    $user = auth()->user();
    $shop = $shop ?? $user?->shop;

    $storeUrl = $isEdit
        ? (Route::has('vendor.products.update') ? route('vendor.products.update', $product) : route('daniel.products.update', $product))
        : (Route::has('vendor.products.store') ? route('vendor.products.store') : route('daniel.products.store'));

    $productsUrl = Route::has('vendor.products') ? route('vendor.products') : (Route::has('daniel.products') ? route('daniel.products') : url('/vendeur/products'));
    $shopUrl = Route::has('vendor.shop.profile') ? route('vendor.shop.profile') : (Route::has('daniel.shop.profile') ? route('daniel.shop.profile') : url('/vendeur/shop-profile'));

    $shopName = $shop?->name ?? ($user?->name ?? 'Boutique OVANIE');
    $shopCity = $shop?->city ?? $shop?->commune ?? 'Abidjan';
    $shopCountry = $shop?->country ?? "Côte d'Ivoire";

    $categoryOptions = collect($categories ?? []);
    $unitOptions = [
        'sac' => 'Sac',
        'tonne' => 'Tonne',
        'piece' => 'Pièce',
        'm2' => 'm²',
        'm3' => 'm³',
        'palette' => 'Palette',
        'carton' => 'Carton',
        'rouleau' => 'Rouleau',
        'seau' => 'Seau',
        'paquet' => 'Paquet',
        'barre' => 'Barre',
        'kg' => 'Kg',
        'litre' => 'Litre',
    ];

    $selectedUnit = old('unit', $product?->unit ?? 'piece');
    $selectedDeliveryMode = 'ovanie';
    $selectedAvailability = old('availability_status', $product?->availability_status ?? 'in_stock');
    $selectedState = old('product_state', $product?->product_state ?? 'new');
    $selectedSaleType = old('sale_type', $product?->sale_type ?? 'normal');
    $isNegotiable = old('is_negotiable', $product?->is_negotiable ? '1' : '0') === '1' || (bool) old('price_p3', $product?->price_p3);

    $oldAttributes = old('product_attributes', $product?->product_attributes ?? []);
    if (empty($oldAttributes)) {
        $oldAttributes = [
            ['label' => 'Diamètre / format / puissance', 'value' => '', 'unit' => ''],
            ['label' => 'Norme / classe / indice', 'value' => '', 'unit' => ''],
            ['label' => 'Couleur / finition', 'value' => '', 'unit' => ''],
        ];

    }

    $handleOptions = old('handling_options', $product?->handling_options ?? []);
@endphp

@section('styles')
<style>
    :root {
        --sc-navy: #071a3d;
        --sc-blue: #1463ff;
        --sc-blue-dark: #0e51d8;
        --sc-orange: #ff5a14;
        --sc-green: #16a34a;
        --sc-red: #ef233c;
        --sc-bg: #f6f9fd;
        --sc-card: #ffffff;
        --sc-text: #071a3d;
        --sc-muted: #64748b;
        --sc-border: #dfe8f4;
        --sc-soft-border: #ecf1f7;
        --sc-shadow: 0 18px 42px rgba(15, 23, 42, .045);
    }

    .sc-product-page, .sc-product-page * { box-sizing: border-box; }
    .sc-product-page {
        width: 100%;
        max-width: 1120px;
        margin: 0 auto;
        padding: 26px 18px 32px;
        color: var(--sc-text);
    }

    .sc-breadcrumb {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 14px;
        color: #61728c;
        font-size: 12px;
        font-weight: 700;
    }
    .sc-breadcrumb span:last-child { color: var(--sc-text); font-weight: 900; }
    .sc-breadcrumb i { width: 13px; height: 13px; stroke-width: 2.2; color: #94a3b8; }

    .sc-page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 18px;
        margin-bottom: 22px;
    }
    .sc-title { margin: 0 0 8px; font-size: 28px; line-height: 1.1; font-weight: 900; letter-spacing: -.04em; color: var(--sc-text); }
    .sc-subtitle { margin: 0; color: #516178; font-size: 14px; line-height: 1.45; font-weight: 600; }

    .sc-layout { display: grid; grid-template-columns: minmax(0, 1fr) 250px; gap: 20px; align-items: start; }
    .sc-main { min-width: 0; }
    .sc-side { position: sticky; top: 82px; display: flex; flex-direction: column; gap: 16px; }

    .sc-alert {
        margin: 0 0 16px;
        padding: 13px 15px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.45;
    }
    .sc-alert.danger { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
    .sc-alert.success { background: #ecfdf5; border: 1px solid #bbf7d0; color: #166534; }

    .sc-stepper {
        position: relative;
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        align-items: start;
        gap: 0;
        margin-bottom: 20px;
        padding: 6px 0 12px;
    }
    .sc-stepper::before {
        content: "";
        position: absolute;
        top: 23px;
        left: 34px;
        right: 34px;
        height: 1px;
        background: #d8e3f2;
    }
    .sc-step {
        position: relative;
        z-index: 2;
        border: 0;
        background: transparent;
        color: #64748b;
        padding: 0 4px;
        cursor: pointer;
        text-align: center;
        min-width: 0;
    }
    .sc-step-dot {
        width: 36px;
        height: 36px;
        margin: 0 auto 9px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #fff;
        border: 1.5px solid #d6e1ee;
        color: #334155;
        font-size: 13px;
        font-weight: 900;
        box-shadow: 0 0 0 8px #f6f9fd;
    }
    .sc-step-label { display: block; min-height: 34px; font-size: 11px; line-height: 1.25; font-weight: 800; }
    .sc-step.is-active .sc-step-dot { background: var(--sc-blue); border-color: var(--sc-blue); color: #fff; box-shadow: 0 0 0 8px #f6f9fd, 0 8px 18px rgba(20,99,255,.22); }
    .sc-step.is-active .sc-step-label { color: var(--sc-blue); font-weight: 900; }
    .sc-step.is-done .sc-step-dot { background: var(--sc-blue); border-color: var(--sc-blue); color: #fff; }

    .sc-card {
        background: var(--sc-card);
        border: 1px solid var(--sc-border);
        border-radius: 14px;
        box-shadow: var(--sc-shadow);
        overflow: hidden;
    }
    .sc-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 23px 24px 20px;
        border-bottom: 1px solid var(--sc-soft-border);
        background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
    }
    .sc-card-title { display: flex; gap: 14px; align-items: flex-start; }
    .sc-step-number {
        width: 33px;
        height: 33px;
        flex: 0 0 33px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        color: #fff;
        background: var(--sc-orange);
        font-weight: 900;
        font-size: 17px;
        box-shadow: 0 10px 22px rgba(255, 90, 20, .22);
    }
    .sc-card h2 { margin: 0; color: var(--sc-text); font-size: 22px; line-height: 1.12; font-weight: 900; letter-spacing: -.035em; }
    .sc-card p.sc-note { margin: 9px 0 0; color: #607087; font-size: 13px; line-height: 1.45; font-weight: 650; }
    .sc-badge { display: inline-flex; align-items: center; gap: 7px; height: 31px; padding: 0 12px; border-radius: 9px; background: #f7fbff; border: 1px solid #dce8f8; color: #456179; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .sc-badge.orange { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
    .sc-badge.blue { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .sc-badge.green { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
    .sc-badge i { width: 14px; height: 14px; stroke-width: 2.3; }

    .sc-step-panel { display: none; }
    .sc-step-panel.is-active { display: block; animation: scFade .16s ease-out; }
    @keyframes scFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .sc-card-body { padding: 24px; }

    .sc-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px 22px; }
    .sc-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px 18px; }
    .sc-grid-4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .sc-full { grid-column: 1 / -1; }

    .sc-field label,
    .sc-label {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 8px;
        color: var(--sc-text);
        font-size: 12px;
        font-weight: 900;
        letter-spacing: -.01em;
    }
    .sc-required { color: var(--sc-orange); }
    .sc-info-icon { width: 14px; height: 14px; color: #8ba0ba; }
    .sc-input, .sc-select, .sc-textarea {
        width: 100%;
        border: 1px solid #d9e4f1;
        border-radius: 10px;
        background: #fff;
        color: var(--sc-text);
        outline: 0;
        transition: border-color .16s ease, box-shadow .16s ease;
        font-weight: 700;
    }
    .sc-input, .sc-select { height: 44px; padding: 0 13px; font-size: 13px; }
    .sc-input::placeholder, .sc-textarea::placeholder { color: #8b9bb0; font-weight: 650; }
    .sc-input:focus, .sc-select:focus, .sc-textarea:focus { border-color: var(--sc-blue); box-shadow: 0 0 0 3px rgba(20, 99, 255, .10); }
    .sc-textarea { min-height: 114px; resize: vertical; padding: 12px 13px; line-height: 1.55; font-size: 13px; }
    .sc-help { margin: 7px 0 0; color: #718198; font-size: 10.7px; line-height: 1.45; font-weight: 650; }
    .sc-count { float: right; color: #718198; font-size: 11px; font-weight: 800; margin-top: 5px; }

    .sc-input-addon { display: flex; align-items: stretch; width: 100%; }
    .sc-input-addon span { height: 44px; min-width: 56px; display: grid; place-items: center; border: 1px solid #d9e4f1; background: #f8fbff; color: #66758d; font-size: 11.5px; font-weight: 900; }
    .sc-input-addon span:first-child { border-radius: 10px 0 0 10px; border-right: 0; }
    .sc-input-addon span:last-child { border-radius: 0 10px 10px 0; border-left: 0; }
    .sc-input-addon input { border-radius: 0 10px 10px 0; }
    .sc-input-addon.suffix input { border-radius: 10px 0 0 10px; }

    .sc-rich-toolbar { height: 42px; display: flex; align-items: center; gap: 12px; padding: 0 12px; border: 1px solid #d9e4f1; border-bottom: 0; border-radius: 10px 10px 0 0; background: #fbfdff; color: #0b1c3d; font-size: 13px; font-weight: 800; }
    .sc-rich-toolbar i { width: 15px; height: 15px; stroke-width: 2.4; }
    .sc-rich-toolbar .sep { height: 18px; width: 1px; background: #dfe8f4; }
    .sc-rich-editor { border-radius: 0 0 10px 10px; min-height: 138px; }

    .sc-tip { display: flex; align-items: flex-start; gap: 10px; border: 1px solid #dbeafe; background: #eff6ff; color: #173b79; border-radius: 10px; padding: 12px 13px; font-size: 11.2px; line-height: 1.45; font-weight: 750; }
    .sc-tip i { width: 16px; height: 16px; flex: 0 0 16px; color: var(--sc-blue); }
    .sc-tip.green { background: #effdf4; border-color: #bbf7d0; color: #14532d; }
    .sc-tip.soft { background: #f8fbff; border-color: #e0eaf6; color: #52647d; }

    .sc-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 22px 24px 24px; border-top: 1px solid var(--sc-soft-border); background: #fff; }
    .sc-btn { min-height: 44px; border-radius: 9px; border: 1px solid #d6e1ee; background: #fff; color: var(--sc-text); display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 0 18px; font-size: 14px; font-weight: 900; cursor: pointer; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
    .sc-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 18px rgba(15, 23, 42, .06); }
    .sc-btn i { width: 18px; height: 18px; stroke-width: 2.4; }
    .sc-btn.primary { border-color: var(--sc-blue); background: var(--sc-blue); color: #fff; box-shadow: 0 10px 18px rgba(20, 99, 255, .18); }
    .sc-btn.orange { border-color: var(--sc-orange); background: var(--sc-orange); color: #fff; box-shadow: 0 12px 20px rgba(255, 90, 20, .20); }
    .sc-btn.ghost { background: #f8fbff; }
    .sc-btn.full { width: 100%; }

    .sc-summary-card, .sc-rules-card {
        background: #fff;
        border: 1px solid #dfe8f4;
        border-radius: 13px;
        box-shadow: 0 14px 32px rgba(15, 23, 42, .045);
        padding: 18px;
    }
    .sc-summary-card h3, .sc-rules-card h3 { margin: 0 0 16px; font-size: 16px; font-weight: 900; color: var(--sc-text); letter-spacing: -.025em; }
    .sc-summary-progress { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin-bottom: 14px; }
    .sc-progress-track { height: 8px; border-radius: 999px; overflow: hidden; background: #e8eef7; }
    .sc-progress-bar { height: 100%; width: 10%; background: var(--sc-blue); border-radius: inherit; transition: width .2s ease; }
    .sc-progress-value { font-size: 12px; font-weight: 900; color: var(--sc-text); }
    .sc-product-placeholder { height: 118px; margin: 14px 0; border-radius: 9px; background: linear-gradient(135deg, #f1f5f9, #fbfdff); border: 1px solid #e3ebf5; display: grid; place-items: center; color: #a1adbd; }
    .sc-product-placeholder i { width: 44px; height: 44px; }
    .sc-skeleton { display: grid; gap: 8px; margin-top: 10px; }
    .sc-skeleton span { display: block; height: 8px; border-radius: 999px; background: #e5ebf3; }
    .sc-skeleton span:nth-child(1) { width: 78%; }
    .sc-skeleton span:nth-child(2) { width: 58%; }
    .sc-summary-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-top: 1px solid #edf2f8; color: #607087; font-size: 12px; font-weight: 750; }
    .sc-summary-row strong { color: var(--sc-text); text-align: right; font-weight: 900; }
    .sc-summary-row .green { color: var(--sc-green); }
    .sc-rules-list { display: grid; gap: 13px; margin: 0 0 14px; }
    .sc-rule { display: flex; gap: 10px; align-items: flex-start; color: #52647d; font-size: 11.5px; line-height: 1.45; font-weight: 700; }
    .sc-rule i { width: 16px; height: 16px; color: var(--sc-blue); flex: 0 0 16px; }
    .sc-link { display: inline-flex; align-items: center; gap: 6px; color: var(--sc-blue); font-size: 11.5px; font-weight: 900; }
    .sc-side .sc-btn { margin-top: 12px; }

    .sc-unit-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; }
    .sc-unit-card { min-height: 64px; position: relative; display: grid; place-items: center; text-align: center; border: 1px solid #dfe8f4; border-radius: 9px; background: #fff; color: var(--sc-text); font-size: 12px; font-weight: 850; cursor: pointer; }
    .sc-unit-card input { position: absolute; opacity: 0; pointer-events: none; }
    .sc-unit-card:has(input:checked) { border-color: var(--sc-blue); box-shadow: inset 0 0 0 1px var(--sc-blue), 0 8px 16px rgba(20,99,255,.08); color: var(--sc-blue); }
    .sc-unit-card .dot { width: 12px; height: 12px; border-radius: 999px; border: 3px solid var(--sc-blue); margin-top: 7px; display: none; }
    .sc-unit-card:has(input:checked) .dot { display: block; }
    .sc-unit-custom { border-style: dashed; color: #53647b; }

    .sc-calc-box { border: 1px solid #dce8f8; border-radius: 12px; background: linear-gradient(180deg, #f8fbff, #fff); padding: 16px; }
    .sc-calc-title { margin: 0 0 7px; color: var(--sc-blue); font-size: 13px; font-weight: 900; }
    .sc-calc-sub { margin: 0 0 14px; color: #607087; font-size: 11px; font-weight: 650; }
    .sc-calc-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .sc-calc-item { min-height: 112px; border: 1px solid #e0e9f5; background: #fff; border-radius: 10px; padding: 13px 10px; text-align: center; display: flex; flex-direction: column; justify-content: center; }
    .sc-calc-item span { color: #455873; font-size: 11px; line-height: 1.25; font-weight: 900; }
    .sc-calc-item strong { display: block; margin-top: 9px; font-size: 20px; line-height: 1.1; color: var(--sc-text); font-weight: 950; letter-spacing: -.03em; }
    .sc-calc-item strong.blue { color: var(--sc-blue); }
    .sc-calc-item small { margin-top: 7px; color: #64748b; font-size: 10px; font-weight: 800; }

    .sc-choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .sc-choice { position: relative; display: block; min-height: 166px; border: 1px solid #dfe8f4; border-radius: 12px; background: #fff; cursor: pointer; padding: 18px; text-align: center; }
    .sc-choice input { position: absolute; opacity: 0; }
    .sc-choice-radio { position: absolute; top: 14px; left: 14px; width: 17px; height: 17px; border: 2px solid #a8b7ca; border-radius: 999px; background: #fff; }
    .sc-choice-icon { width: 58px; height: 58px; margin: 18px auto 14px; border-radius: 999px; display: grid; place-items: center; background: #f1f5f9; color: #94a3b8; }
    .sc-choice-icon i { width: 33px; height: 33px; stroke-width: 2.1; }
    .sc-choice strong { display: block; color: var(--sc-text); font-size: 14px; font-weight: 950; line-height: 1.25; }
    .sc-choice small { display: block; margin-top: 10px; color: #607087; font-size: 11px; line-height: 1.5; font-weight: 650; }
    .sc-choice:has(input:checked) { border-color: var(--sc-blue); box-shadow: inset 0 0 0 1px var(--sc-blue), 0 14px 26px rgba(20,99,255,.08); }
    .sc-choice:has(input:checked) .sc-choice-radio { border-color: var(--sc-blue); box-shadow: inset 0 0 0 4px #fff; background: var(--sc-blue); }
    .sc-choice:has(input:checked) .sc-choice-icon { color: var(--sc-blue); background: #eff6ff; }

    .sc-advice-box { border: 1px solid #bbf7d0; background: #f0fdf4; border-radius: 12px; padding: 16px; color: #166534; }
    .sc-advice-box h4 { margin: 0 0 9px; font-size: 13px; font-weight: 900; }
    .sc-advice-box p, .sc-advice-box li { font-size: 11px; line-height: 1.55; font-weight: 650; }
    .sc-advice-box p { margin: 0 0 10px; }
    .sc-advice-box ul { margin: 0; padding-left: 17px; }

    .sc-section-box { border: 1px solid var(--sc-soft-border); border-radius: 12px; padding: 18px; margin-bottom: 16px; background: #fff; }
    .sc-section-title { margin: 0 0 16px; color: var(--sc-blue); font-size: 15px; font-weight: 900; letter-spacing: -.02em; }

    .sc-attr-list { display: grid; gap: 10px; }
    .sc-attr-head, .sc-attr-row { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) 110px 36px; gap: 10px; align-items: center; }
    .sc-attr-head { color: var(--sc-text); font-size: 11px; font-weight: 900; margin-bottom: 3px; }
    .sc-delete-row { height: 42px; border: 1px solid #fee2e2; border-radius: 9px; background: #fff; color: #ef233c; display: grid; place-items: center; cursor: pointer; }
    .sc-delete-row i { width: 17px; height: 17px; stroke-width: 2.4; }
    .sc-add-row { width: 100%; height: 42px; border: 1px dashed #94bfff; border-radius: 9px; color: var(--sc-blue); background: #fbfdff; font-weight: 900; cursor: pointer; }

    .sc-delivery-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .sc-delivery-card { position: relative; min-height: 132px; border: 1px solid #dfe8f4; border-radius: 11px; background: #fff; padding: 14px 10px; text-align: center; cursor: pointer; }
    .sc-delivery-card input { position: absolute; opacity: 0; }
    .sc-delivery-card .radio { position: absolute; top: 10px; left: 10px; width: 14px; height: 14px; border: 2px solid #a8b7ca; border-radius: 50%; }
    .sc-delivery-card i { width: 36px; height: 36px; color: #475569; margin: 20px auto 12px; display: block; stroke-width: 1.8; }
    .sc-delivery-card strong { display: block; color: var(--sc-text); font-size: 12.2px; font-weight: 950; }
    .sc-delivery-card small { display: block; margin-top: 8px; color: #607087; font-size: 10px; line-height: 1.35; font-weight: 650; }
    .sc-delivery-card:has(input:checked) { border-color: var(--sc-blue); box-shadow: inset 0 0 0 1px var(--sc-blue); }
    .sc-delivery-card:has(input:checked) .radio { background: var(--sc-blue); border-color: var(--sc-blue); box-shadow: inset 0 0 0 4px #fff; }
    .sc-delivery-card:has(input:checked) i { color: var(--sc-blue); }

    .sc-delay-row { display: none; grid-column: 1 / -1; }
    .sc-delay-row.is-visible { display: block; }
    .sc-delay-options { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .sc-delay-options label { display: inline-flex; align-items: center; justify-content: center; height: 36px; min-width: 72px; padding: 0 14px; border: 1px solid #d9e4f1; border-radius: 999px; font-size: 12px; font-weight: 900; cursor: pointer; }
    .sc-delay-options input { position: absolute; opacity: 0; }
    .sc-delay-options label:has(input:checked) { border-color: var(--sc-blue); color: var(--sc-blue); background: #eff6ff; }

    .sc-logistics-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .sc-log-card { position: relative; display: block; min-height: 112px; border: 1px solid #dfe8f4; border-radius: 10px; background: #fff; padding: 13px 12px 12px 42px; cursor: pointer; }
    .sc-log-card input { position: absolute; top: 14px; left: 14px; width: 15px; height: 15px; }
    .sc-log-card i { width: 25px; height: 25px; color: var(--sc-orange); margin-bottom: 10px; stroke-width: 1.9; }
    .sc-log-card strong { display: block; color: var(--sc-text); font-size: 12px; font-weight: 950; }
    .sc-log-card small { display: block; margin-top: 6px; color: #607087; font-size: 10.5px; line-height: 1.35; font-weight: 650; }
    .sc-log-card:has(input:checked) { border-color: #fdba74; background: #fff7ed; }

    .sc-upload-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .sc-upload-box { border: 1px solid #dfe8f4; border-radius: 12px; background: #fff; padding: 16px; }
    .sc-upload-box h4 { margin: 0 0 5px; color: var(--sc-text); font-size: 13px; font-weight: 950; }
    .sc-upload-box p { margin: 0 0 14px; color: #64748b; font-size: 11px; font-weight: 700; }
    .sc-dropzone { min-height: 158px; border: 1.5px dashed #a9c7f5; border-radius: 11px; display: grid; place-items: center; text-align: center; background: #fbfdff; color: var(--sc-blue); cursor: pointer; }
    .sc-dropzone i { width: 40px; height: 40px; margin-bottom: 10px; stroke-width: 1.8; }
    .sc-dropzone strong { display: block; color: #314461; font-size: 12px; font-weight: 800; }
    .sc-dropzone span { display: block; margin-top: 5px; color: var(--sc-blue); font-size: 11.5px; font-weight: 900; }
    .sc-upload-meta { margin-top: 11px; color: #64748b; font-size: 10.5px; line-height: 1.45; font-weight: 700; text-align: center; }
    .sc-thumbs { display: flex; justify-content: center; gap: 8px; margin: 15px 0 6px; }
    .sc-thumb { width: 33px; height: 33px; border: 1px dashed #b7c7da; border-radius: 7px; display: grid; place-items: center; color: #64748b; background: #fff; }
    .sc-thumb i { width: 15px; height: 15px; }
    .sc-file-name { margin-top: 10px; min-height: 16px; color: #40546e; font-size: 11px; text-align: center; font-weight: 800; }

    .sc-checklist { margin-top: 18px; }
    .sc-check-row { display: grid; grid-template-columns: 26px minmax(0, 1fr) minmax(0, .8fr) 44px; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #edf2f8; color: #40546e; font-size: 12px; font-weight: 750; }
    .sc-check-row i { width: 18px; height: 18px; color: var(--sc-green); }
    .sc-check-row strong { color: var(--sc-text); font-weight: 900; }
    .sc-check-row .ok { color: var(--sc-green); font-weight: 900; text-align: right; }

    .sc-ready-preview { display: flex; gap: 12px; align-items: flex-start; margin: 12px 0 16px; }
    .sc-ready-img { width: 58px; height: 58px; border-radius: 8px; border: 1px solid #e2e8f0; object-fit: cover; background: #f1f5f9; }
    .sc-ready-title { font-weight: 900; font-size: 12px; color: var(--sc-text); line-height: 1.3; }
    .sc-ready-sub { color: #64748b; font-size: 10.5px; margin-top: 5px; font-weight: 700; }
    .sc-side-big { color: var(--sc-blue); font-size: 30px; font-weight: 950; letter-spacing: -.04em; }

    @media (max-width: 1180px) {
        .sc-product-page { max-width: 100%; padding-left: 16px; padding-right: 16px; }
        .sc-layout { grid-template-columns: 1fr; }
        .sc-side { position: static; grid-row: 1; }
        .sc-side .sc-rules-card { display: none; }
    }
    @media (max-width: 760px) {
        .sc-product-page { padding: 16px 10px; }
        .sc-page-head { display: block; }
        .sc-stepper { overflow-x: auto; grid-template-columns: repeat(6, 112px); padding-bottom: 12px; }
        .sc-grid-2, .sc-grid-3, .sc-grid-4, .sc-upload-grid, .sc-choice-grid, .sc-delivery-grid, .sc-logistics-options { grid-template-columns: 1fr; }
        .sc-card-head, .sc-card-body, .sc-actions { padding-left: 16px; padding-right: 16px; }
        .sc-unit-grid { grid-template-columns: repeat(2, 1fr); }
        .sc-calc-grid { grid-template-columns: 1fr; }
        .sc-attr-head { display: none; }
        .sc-attr-row { grid-template-columns: 1fr; }
        .sc-delete-row { width: 42px; }
    }
</style>
@endsection

@section('content')
<section class="sc-product-page" data-sc-product-wizard>
    <div class="sc-breadcrumb">
        <span>Espace vendeur</span>
        <i data-lucide="chevron-right"></i>
        <span>Produits</span>
        <i data-lucide="chevron-right"></i>
        <span>{{ $isEdit ? 'Modifier un produit' : 'Ajouter un produit' }}</span>
    </div>

    <div class="sc-page-head">
        <div>
            <h1 class="sc-title">{{ $isEdit ? 'Modifier le produit' : 'Ajouter un produit' }}</h1>
            <p class="sc-subtitle">Créez une fiche produit claire, complète et prête à vendre.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="sc-alert danger">
            <strong>Formulaire incomplet.</strong>
            Corrigez les champs signalés : {{ $errors->first() }}
        </div>
    @endif

    @if (session('success'))
        <div class="sc-alert success">{{ session('success') }}</div>
    @endif

    <form id="scProductForm" method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="sc-layout">
            <main class="sc-main">
                <nav class="sc-stepper" aria-label="Progression du formulaire">
                    @foreach ([
                        1 => ['Informations produit', 'Produit'],
                        2 => ['Offre et prix', 'Offre'],
                        3 => ['Négociation', 'Négociation'],
                        4 => ['Données techniques BTP', 'Données BTP'],
                        5 => ['Livraison et logistique', 'Livraison'],
                        6 => ['Médias', 'Médias'],
                    ] as $number => $step)
                        <button class="sc-step {{ $number === 1 ? 'is-active' : '' }}" type="button" data-step-target="{{ $number }}">
                            <span class="sc-step-dot" data-step-dot>{{ $number }}</span>
                            <span class="sc-step-label">{{ $step[0] }}</span>
                        </button>
                    @endforeach
                </nav>

                <article class="sc-card sc-step-panel is-active" data-step-panel="1">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">1</span>
                            <div>
                                <h2>Étape 1 — Informations produit</h2>
                                <p class="sc-note">Renseignez les informations essentielles de votre produit.</p>
                            </div>
                        </div>
                        <span class="sc-badge blue"><i data-lucide="shield-check"></i> Champs obligatoires <span class="sc-required">*</span></span>
                    </div>

                    <div class="sc-card-body">
                        <div class="sc-grid-2">
                            <div class="sc-field">
                                <label>Nom du produit <span class="sc-required">*</span></label>
                                <input class="sc-input" type="text" name="name" id="scName" value="{{ old('name', $product?->name) }}" placeholder="Ex. : Ciment CPA 42,5 - Sac 50 kg" required>
                                <p class="sc-help">Soyez précis et utilisez les mots clés recherchés par les acheteurs.</p>
                            </div>

                            <div class="sc-field">
                                <label>Référence vendeur / SKU <i class="sc-info-icon" data-lucide="info"></i></label>
                                <input class="sc-input" type="text" name="sku" id="scSku" value="{{ old('sku', $product?->sku) }}" placeholder="Ex. : CIM-CPA-42-5-50KG">
                                <p class="sc-help">Référence unique pour retrouver le produit dans vos commandes.</p>
                            </div>

                            <div class="sc-field">
                                <label>Catégorie <span class="sc-required">*</span></label>
                                <select class="sc-select" name="category_id" id="scCategory" required>
                                    <option value="">Rechercher une catégorie</option>
                                    @foreach($categoryOptions as $category)
                                        <option value="{{ $category->id }}" {{ (string) old('category_id', $product?->category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <p class="sc-help">Sélectionnez la catégorie la plus pertinente.</p>
                            </div>

                            <div class="sc-field">
                                <label>État du produit <i class="sc-info-icon" data-lucide="info"></i></label>
                                <select class="sc-select" name="product_state" id="scState">
                                    <option value="new" {{ $selectedState === 'new' ? 'selected' : '' }}>Neuf</option>
                                    <option value="reconditioned" {{ $selectedState === 'reconditioned' ? 'selected' : '' }}>Reconditionné</option>
                                    <option value="used" {{ $selectedState === 'used' ? 'selected' : '' }}>Occasion</option>
                                </select>
                                <p class="sc-help">Indiquez s’il s’agit d’un produit neuf ou d’occasion.</p>
                            </div>

                            <div class="sc-field">
                                <label>Marque</label>
                                <input class="sc-input" type="text" name="brand" id="scBrand" value="{{ old('brand', $product?->brand) }}" placeholder="Rechercher ou saisir une marque">
                                <p class="sc-help">Commencez à saisir pour trouver ou ajouter une marque.</p>
                            </div>

                            <div class="sc-field">
                                <label>Statut commercial <i class="sc-info-icon" data-lucide="info"></i></label>
                                <select class="sc-select" name="sale_type" id="scSaleType">
                                    <option value="normal" {{ $selectedSaleType === 'normal' ? 'selected' : '' }}>Vente normale</option>
                                    <option value="promotion" {{ $selectedSaleType === 'promotion' ? 'selected' : '' }}>Promotion</option>
                                    <option value="flash_sale" {{ $selectedSaleType === 'flash_sale' ? 'selected' : '' }}>Vente flash</option>
                                </select>
                                <p class="sc-help">Black Friday et boost produit restent pilotés depuis OVANIE ou votre catalogue vendeur.</p>
                            </div>

                            <div class="sc-field sc-full">
                                <label>Description courte</label>
                                <input class="sc-input" type="text" name="short_description" id="scShort" maxlength="200" value="{{ old('short_description', $product?->short_description) }}" placeholder="Résumé en une phrase des principaux bénéfices de votre produit.">
                                <span class="sc-count"><span data-count-for="scShort">0</span> / 200</span>
                                <p class="sc-help">Affichée dans les résultats de recherche et les listes.</p>
                            </div>

                            <div class="sc-field sc-full">
                                <label>Description détaillée <span class="sc-required">*</span></label>
                                <div class="sc-rich-toolbar">
                                    <span>Paragraphe</span><i data-lucide="chevron-down"></i><span class="sep"></span>
                                    <strong>B</strong><em>I</em><u>U</u><span class="sep"></span>
                                    <i data-lucide="list"></i><i data-lucide="list-ordered"></i><i data-lucide="align-left"></i><span class="sep"></span>
                                    <i data-lucide="link"></i><i data-lucide="image"></i>
                                </div>
                                <textarea class="sc-textarea sc-rich-editor" name="description" id="scDescription" maxlength="4000" required placeholder="Décrivez votre produit en détail : composition, avantages, usages, compatibilités, performances, conseils d’utilisation, etc.">{{ old('description', $product?->description) }}</textarea>
                                <span class="sc-count"><span data-count-for="scDescription">0</span> / 4000</span>
                            </div>
                        </div>

                        <div class="sc-tip" style="margin-top:18px;">
                            <i data-lucide="info"></i>
                            <span><strong>Conseil :</strong> Une description complète et structurée rassure les acheteurs et améliore votre référencement sur OVANIE.</span>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <a href="{{ $productsUrl }}" class="sc-btn ghost">Annuler</a>
                        <button type="button" class="sc-btn orange" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>

                <article class="sc-card sc-step-panel" data-step-panel="2">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">2</span>
                            <div>
                                <h2>Étape 2 — Offre, prix et disponibilité</h2>
                                <p class="sc-note">Définissez votre offre commerciale, vos prix et la disponibilité du produit.</p>
                            </div>
                        </div>
                        <span class="sc-badge orange">Commission estimée</span>
                    </div>

                    <div class="sc-card-body">
                        <div class="sc-grid-2">
                            <div class="sc-field">
                                <label>Prix vendeur FCFA <span class="sc-required">*</span> <i class="sc-info-icon" data-lucide="info"></i></label>
                                <div class="sc-input-addon suffix">
                                    <input class="sc-input" type="number" name="price" id="scPrice" min="1" step="1" value="{{ old('price', $product?->price) }}" required>
                                    <span>FCFA</span>
                                </div>
                            </div>

                            <div class="sc-field">
                                <label>Prix promotionnel (FCFA) <i class="sc-info-icon" data-lucide="info"></i></label>
                                <div class="sc-input-addon suffix">
                                    <input class="sc-input" type="number" name="promo_price" id="scPromo" min="0" step="1" value="{{ old('promo_price', $product?->promo_price) }}">
                                    <span>FCFA</span>
                                </div>
                            </div>

                            <div class="sc-field">
                                <label>Stock disponible <span class="sc-required">*</span> <i class="sc-info-icon" data-lucide="info"></i></label>
                                <div class="sc-input-addon suffix">
                                    <input class="sc-input" type="number" name="stock" id="scStock" min="0" step="1" value="{{ old('stock', $product?->stock ?? 0) }}" required>
                                    <span>unités</span>
                                </div>
                            </div>

                            <div class="sc-field">
                                <label>Disponibilité <span class="sc-required">*</span> <i class="sc-info-icon" data-lucide="info"></i></label>
                                <select class="sc-select" name="availability_status" id="scAvailability">
                                    <option value="in_stock" {{ $selectedAvailability === 'in_stock' ? 'selected' : '' }}>● En stock</option>
                                    <option value="on_order" {{ $selectedAvailability === 'on_order' ? 'selected' : '' }}>Disponible sous commande</option>
                                    <option value="preorder" {{ $selectedAvailability === 'preorder' ? 'selected' : '' }}>Précommande</option>
                                    <option value="out_of_stock" {{ $selectedAvailability === 'out_of_stock' ? 'selected' : '' }}>Rupture temporaire</option>
                                </select>
                            </div>

                            <div class="sc-field">
                                <label>Délai d’approvisionnement <i class="sc-info-icon" data-lucide="info"></i></label>
                                <input class="sc-input" type="text" name="supply_delay" id="scSupplyDelay" value="{{ old('supply_delay', $product?->supply_delay) }}" placeholder="Ex. : 24h, 48h, 2 à 5 jours ouvrés">
                            </div>

                            <div class="sc-field">
                                <label>Quantité minimum <span class="sc-required">*</span> <i class="sc-info-icon" data-lucide="info"></i></label>
                                <div class="sc-input-addon suffix">
                                    <input class="sc-input" type="number" name="min_order_quantity" id="scMinQty" min="1" step="1" value="{{ old('min_order_quantity', $product?->min_order_quantity ?? 1) }}" required>
                                    <span>unités</span>
                                </div>
                            </div>

                            <div class="sc-field sc-full">
                                <span class="sc-label">Unité de vente <span class="sc-required">*</span> <i class="sc-info-icon" data-lucide="info"></i></span>
                                <div class="sc-unit-grid">
                                    @foreach(['sac' => 'Sac', 'tonne' => 'Tonne', 'piece' => 'Pièce', 'm2' => 'm²', 'm3' => 'm³'] as $key => $label)
                                        <label class="sc-unit-card">
                                            <input type="radio" name="unit" value="{{ $key }}" {{ $selectedUnit === $key ? 'checked' : '' }}>
                                            <span>{{ $label }}</span>
                                            <span class="dot"></span>
                                        </label>
                                    @endforeach
                                    <label class="sc-unit-card sc-unit-custom">
                                        <input type="radio" name="unit" value="paquet" {{ $selectedUnit === 'paquet' ? 'checked' : '' }}>
                                        <span>Libellé<br>personnalisé</span>
                                        <span class="dot"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="sc-field sc-full">
                                <label>Libellé personnalisé (facultatif) <i class="sc-info-icon" data-lucide="info"></i></label>
                                <input class="sc-input" type="text" name="unit_label" id="scUnitLabel" value="{{ old('unit_label', $product?->unit_label) }}" placeholder="Ex. Botte, Paquet, Rouleau...">
                            </div>

                            <div class="sc-full sc-calc-box">
                                <p class="sc-calc-title">Récapitulatif de calcul</p>
                                <p class="sc-calc-sub">Les montants sont indicatifs et peuvent varier selon les frais applicables.</p>
                                <div class="sc-calc-grid">
                                    <div class="sc-calc-item"><span>Commission OVANIE<br>estimée</span><strong id="scCommission">0 FCFA</strong><small>HT</small></div>
                                    <div class="sc-calc-item"><span>Prix client estimé</span><strong class="blue" id="scClientPrice">0 FCFA</strong><small>TTC</small></div>
                                    <div class="sc-calc-item"><span>Net vendeur estimé</span><strong id="scNetSeller">0 FCFA</strong><small>HT</small></div>
                                </div>
                                <div class="sc-tip" style="margin-top:14px;">
                                    <i data-lucide="info"></i>
                                    <span>Ces calculs sont donnés à titre indicatif. Le prix final affiché au client peut inclure des frais de livraison, taxes et autres frais applicables.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <button type="button" class="sc-btn" data-prev><i data-lucide="arrow-left"></i> Retour</button>
                        <button type="button" class="sc-btn orange" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>

                <article class="sc-card sc-step-panel" data-step-panel="3">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">3</span>
                            <div>
                                <h2>Étape 3 — Négociation produit</h2>
                                <p class="sc-note">Activez la négociation si vous acceptez les propositions de prix.</p>
                            </div>
                        </div>
                        <span class="sc-badge orange">Option client</span>
                    </div>

                    <div class="sc-card-body">
                        <div class="sc-tip" style="margin-bottom:22px;"><i data-lucide="info"></i><span>Le client peut vous proposer un prix directement depuis la fiche produit.</span></div>

                        <h3 class="sc-section-title" style="color:var(--sc-text);">Autoriser la négociation</h3>
                        <p class="sc-help" style="margin-bottom:16px;">Choisissez si vous souhaitez recevoir des propositions de prix pour ce produit.</p>

                        <div class="sc-choice-grid">
                            <label class="sc-choice">
                                <input type="radio" name="is_negotiable" value="1" {{ $isNegotiable ? 'checked' : '' }}>
                                <span class="sc-choice-radio"></span>
                                <span class="sc-choice-icon"><i data-lucide="handshake"></i></span>
                                <strong>Oui, accepter les propositions</strong>
                                <small>Les acheteurs pourront vous proposer un prix d’achat. Vous restez libre d’accepter ou de refuser.</small>
                            </label>
                            <label class="sc-choice">
                                <input type="radio" name="is_negotiable" value="0" {{ ! $isNegotiable ? 'checked' : '' }}>
                                <span class="sc-choice-radio"></span>
                                <span class="sc-choice-icon"><i data-lucide="lock"></i></span>
                                <strong>Non, prix fixe</strong>
                                <small>Le prix affiché sera ferme et non négociable. Aucune proposition de prix ne sera possible.</small>
                            </label>
                        </div>

                        <div class="sc-grid-2" style="margin-top:26px; align-items:start;">
                            <div>
                                <h3 class="sc-section-title" style="color:var(--sc-text);">Paramètres de négociation</h3>
                                <div class="sc-field" style="margin-bottom:18px;">
                                    <label>Prix minimum accepté (FCFA) <span class="sc-required">*</span></label>
                                    <div class="sc-input-addon">
                                        <span>FCFA</span>
                                        <input class="sc-input" type="number" name="price_p3" id="scMinNegotiationPrice" min="0" step="1" value="{{ old('price_p3', $product?->price_p3) }}">
                                    </div>
                                    <p class="sc-help">Le client ne pourra pas proposer un prix inférieur à ce montant.</p>
                                    <input type="hidden" name="price_p1" id="scPriceP1" value="{{ old('price_p1', $product?->price_p1) }}">
                                    <input type="hidden" name="price_p2" id="scPriceP2" value="{{ old('price_p2', $product?->price_p2) }}">
                                </div>
                                <div class="sc-field" style="margin-bottom:18px;">
                                    <label>Quantité minimum pour négocier <span class="sc-required">*</span></label>
                                    <div class="sc-input-addon suffix">
                                        <input class="sc-input" type="number" id="scNegotiationQty" min="1" step="1" value="{{ old('negotiation_min_quantity', $product?->min_order_quantity ?? 10) }}">
                                        <span>unité(s)</span>
                                    </div>
                                    <p class="sc-help">À partir de cette quantité, les acheteurs pourront proposer un prix.</p>
                                </div>
                                <div class="sc-field">
                                    <label>Message au client (optionnel)</label>
                                    <textarea class="sc-textarea" id="scNegotiationMessage" maxlength="300" placeholder="Nous restons ouverts à vos propositions pour les commandes en volume.">{{ old('negotiation_message') }}</textarea>
                                    <span class="sc-count"><span data-count-for="scNegotiationMessage">0</span> / 300</span>
                                </div>
                            </div>
                            <aside class="sc-advice-box">
                                <h4><i data-lucide="lightbulb"></i> Conseil OVANIE</h4>
                                <p><strong>Comment définir un prix minimum accepté ?</strong></p>
                                <p>Le prix minimum accepté est le seuil en dessous duquel vous ne souhaitez pas vendre.</p>
                                <ul>
                                    <li>Prix demandé : 20 000 FCFA</li>
                                    <li>Prix minimum accepté : 17 000 FCFA</li>
                                    <li>Le client peut proposer entre 17 000 FCFA et 19 999 FCFA</li>
                                </ul>
                            </aside>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <button type="button" class="sc-btn" data-prev><i data-lucide="arrow-left"></i> Retour</button>
                        <button type="button" class="sc-btn orange" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>

                <article class="sc-card sc-step-panel" data-step-panel="4">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">4</span>
                            <div>
                                <h2>Étape 4 — Données BTP et fiche technique</h2>
                                <p class="sc-note">Renseignez les informations techniques essentielles pour vos acheteurs professionnels.</p>
                            </div>
                        </div>
                        <span class="sc-badge orange">Données techniques</span>
                    </div>

                    <div class="sc-card-body">
                        <div class="sc-section-box">
                            <h3 class="sc-section-title">Données techniques principales</h3>
                            <div class="sc-grid-3">
                                <div class="sc-field"><label>Conditionnement</label><input class="sc-input" name="packaging" value="{{ old('packaging', $product?->packaging) }}" placeholder="Ex. palette 64 sacs, carton"></div>
                                <div class="sc-field"><label>Contenu par unité</label><input class="sc-input" type="number" step="0.001" name="content_per_unit" value="{{ old('content_per_unit', $product?->content_per_unit) }}" placeholder="ex. 25 kg, 5 L, 1 m³"></div>
                                <div class="sc-field"><label>Unité du contenu</label><input class="sc-input" name="content_unit" value="{{ old('content_unit', $product?->content_unit) }}" placeholder="kg, litre, m², m³, pièce"></div>
                                <div class="sc-field"><label>Unités par paquet/carton/palette</label><input class="sc-input" type="number" name="units_per_package" value="{{ old('units_per_package', $product?->units_per_package) }}" placeholder="ex. 48, 60, 120"></div>
                                <div class="sc-field"><label>Surface couverte par unité (m²)</label><input class="sc-input" type="number" step="0.01" name="coverage_per_unit_m2" value="{{ old('coverage_per_unit_m2', $product?->coverage_per_unit_m2) }}" placeholder="ex. 2,5"></div>
                                <div class="sc-field"><label>Pays d’origine</label><input class="sc-input" name="origin_country" value="{{ old('origin_country', $product?->origin_country ?? $shopCountry) }}" placeholder="Côte d'Ivoire"></div>
                                <div class="sc-field"><label>Usage recommandé</label><input class="sc-input" name="usage_area" value="{{ old('usage_area', $product?->usage_area) }}" placeholder="Fondation, mur, toiture"></div>
                                <div class="sc-field"><label>Grade / Classe / Qualité</label><input class="sc-input" name="material_grade" value="{{ old('material_grade', $product?->material_grade) }}" placeholder="ex. CPJ 42.5, HA12, IP66"></div>
                                <div class="sc-field"><label>Garantie</label><input class="sc-input" name="warranty" value="{{ old('warranty', $product?->warranty) }}" placeholder="ex. 12 mois, garantie fabricant"></div>
                            </div>
                        </div>

                        <div class="sc-section-box">
                            <h3 class="sc-section-title">Attributs techniques structurés</h3>
                            <div class="sc-attr-list" id="scAttrList">
                                <div class="sc-attr-head"><span>Caractéristique <span class="sc-required">*</span></span><span>Valeur <span class="sc-required">*</span></span><span>Unité</span><span></span></div>
                                @foreach($oldAttributes as $index => $attribute)
                                    <div class="sc-attr-row">
                                        <input class="sc-input" name="product_attributes[{{ $index }}][label]" value="{{ $attribute['label'] ?? '' }}" placeholder="Résistance à la compression">
                                        <input class="sc-input" name="product_attributes[{{ $index }}][value]" value="{{ $attribute['value'] ?? '' }}" placeholder="42,5">
                                        <input class="sc-input" name="product_attributes[{{ $index }}][unit]" value="{{ $attribute['unit'] ?? '' }}" placeholder="MPa">
                                        <button class="sc-delete-row" type="button" data-remove-row><i data-lucide="trash-2"></i></button>
                                    </div>
                                @endforeach
                            </div>
                            <button class="sc-add-row" type="button" id="scAddAttr">+ Ajouter une caractéristique</button>
                        </div>

                        <div class="sc-grid-2">
                            <div class="sc-field"><label>Détails techniques libres</label><textarea class="sc-textarea" name="technical_details" maxlength="2000" placeholder="Ex. composition détaillée, mode d’emploi, tolérances, normes applicables, etc.">{{ old('technical_details', $product?->technical_details) }}</textarea></div>
                            <div class="sc-field"><label>Politique de retour</label><textarea class="sc-textarea" name="return_policy" maxlength="1000" placeholder="Ex. produit non ouvert, sous 14 jours, frais de retour à la charge de l’acheteur, etc.">{{ old('return_policy', $product?->return_policy) }}</textarea></div>
                        </div>

                        <div class="sc-section-box" style="margin:18px 0 0;">
                            <h3 class="sc-section-title">Fiche technique PDF</h3>
                            <label class="sc-dropzone" for="scTechnicalSheet">
                                <span><i data-lucide="cloud-upload"></i><strong>Glissez-déposez votre fichier ici</strong><span>ou cliquez pour parcourir</span></span>
                                <input type="file" name="technical_sheet" id="scTechnicalSheet" accept="application/pdf" hidden>
                            </label>
                            <p class="sc-upload-meta">PDF uniquement · Max. 20 Mo</p>
                            <div class="sc-file-name" data-file-name="scTechnicalSheet"></div>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <button type="button" class="sc-btn" data-prev><i data-lucide="arrow-left"></i> Retour</button>
                        <button type="button" class="sc-btn primary" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>

                <article class="sc-card sc-step-panel" data-step-panel="5">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">5</span>
                            <div>
                                <h2>Étape 5 — Livraison et logistique</h2>
                                <p class="sc-note">Sélectionnez votre mode de livraison et renseignez les informations logistiques.</p>
                            </div>
                        </div>
                        <span class="sc-badge orange">Règle livraison</span>
                    </div>

                    <div class="sc-card-body">
                        <input type="hidden" name="delivery_mode" value="ovanie">
                        <div class="sc-tip green" style="margin-top:16px;"><i data-lucide="info"></i><span><strong>OVANIE Logistics obligatoire</strong><br>OVANIE organise toute la livraison. Renseignez uniquement les poids, volumes, contraintes et informations de retrait utiles au calcul.</span></div>

                        <hr style="border:0;border-top:1px solid #edf2f8;margin:22px 0;">

                        <h3 class="sc-section-title" style="color:var(--sc-text);">Informations logistiques physiques</h3>
                        <div class="sc-grid-4">
                            <div class="sc-field"><label>Poids par unité (kg)</label><input class="sc-input" type="number" step="0.01" name="weight_kg" value="{{ old('weight_kg', $product?->weight_kg) }}" placeholder="ex. 150"></div>
                            <div class="sc-field"><label>Longueur (cm)</label><input class="sc-input" type="number" step="0.01" name="length_cm" id="scLength" value="{{ old('length_cm', $product?->length_cm) }}" placeholder="ex. 120"></div>
                            <div class="sc-field"><label>Largeur (cm)</label><input class="sc-input" type="number" step="0.01" name="width_cm" id="scWidth" value="{{ old('width_cm', $product?->width_cm) }}" placeholder="ex. 80"></div>
                            <div class="sc-field"><label>Hauteur (cm)</label><input class="sc-input" type="number" step="0.01" name="height_cm" id="scHeight" value="{{ old('height_cm', $product?->height_cm) }}" placeholder="ex. 75"></div>
                            <div class="sc-field"><label>Volume (m³)</label><input class="sc-input" type="number" step="0.0001" name="volume_m3" id="scVolume" value="{{ old('volume_m3', $product?->volume_m3) }}" placeholder="ex. 0,72"></div>
                            <div class="sc-field"><label>Ville retrait</label><input class="sc-input" name="pickup_city" value="{{ old('pickup_city', $product?->pickup_city ?? $shopCity) }}" placeholder="Sélectionner"></div>
                            <div class="sc-field"><label>Commune retrait</label><input class="sc-input" name="pickup_commune" value="{{ old('pickup_commune', $product?->pickup_commune ?? $shop?->commune) }}" placeholder="Sélectionner"></div>
                            <div class="sc-field"><label>Adresse / repère retrait</label><input class="sc-input" name="pickup_address" value="{{ old('pickup_address', $product?->pickup_address) }}" placeholder="Adresse, repère, info accès"></div>
                        </div>

                        <h3 class="sc-section-title" style="margin-top:24px;color:var(--sc-text);">Options logistiques</h3>
                        <p class="sc-help" style="margin-bottom:12px;">Sélectionnez les services / contraintes applicables à ce produit.</p>
                        <div class="sc-logistics-options">
                            @foreach([
                                'fragile' => ['Produit fragile', 'Manipulation délicate requise.', 'wine'],
                                'unloading' => ['Déchargement requis', 'Le déchargement n’est pas assuré par le vendeur.', 'hand-truck'],
                                'fast' => ['Prise en charge rapide', 'Enlèvement sous 2h après commande.', 'timer'],
                                'manutentionnaires' => ['Manutentionnaires', 'Besoin d’aide à la manutention.', 'users'],
                                'camion_benne' => ['Camion benne', 'Déchargement uniquement en camion benne.', 'truck'],
                                'camion_grue' => ['Camion grue', 'Levage / déchargement par camion grue.', 'truck'],
                                'chariot_elevateur' => ['Chariot élévateur', 'Nécessite un chariot élévateur à la livraison.', 'forklift'],
                                'produit_palettise' => ['Produit palettisé', 'Livré sur palette standard.', 'boxes'],
                                'produit_vrac' => ['Produit en vrac', 'Livré en vrac non conditionné.', 'mountain'],
                            ] as $key => $log)
                                <label class="sc-log-card">
                                    @if($key === 'fragile')
                                        <input type="checkbox" name="fragile" value="1" {{ old('fragile', $product?->fragile) ? 'checked' : '' }}>
                                    @elseif($key === 'unloading')
                                        <input type="checkbox" name="requires_unloading" value="1" {{ old('requires_unloading', $product?->requires_unloading) ? 'checked' : '' }}>
                                    @elseif($key === 'fast')
                                        <input type="checkbox" name="fast_delivery" value="1" {{ old('fast_delivery', $product?->fast_delivery) ? 'checked' : '' }}>
                                    @else
                                        <input type="checkbox" name="handling_options[]" value="{{ $log[0] }}" {{ in_array($log[0], $handleOptions, true) ? 'checked' : '' }}>
                                    @endif
                                    <i data-lucide="{{ $log[2] }}"></i><strong>{{ $log[0] }}</strong><small>{{ $log[1] }}</small>
                                </label>
                            @endforeach
                        </div>

                        <div class="sc-field sc-full" style="margin-top:16px;"><label>Détails de déchargement</label><textarea class="sc-textarea" name="unloading_instructions" maxlength="500" placeholder="Précisez les conditions, moyens à prévoir, accès, contraintes particulières...">{{ old('unloading_instructions', $product?->unloading_instructions) }}</textarea></div>
                    </div>
                    </div>

                    <div class="sc-actions">
                        <button type="button" class="sc-btn" data-prev><i data-lucide="arrow-left"></i> Retour</button>
                        <button type="button" class="sc-btn orange" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                    </div>
                </article>

                <article class="sc-card sc-step-panel" data-step-panel="6">
                    <div class="sc-card-head">
                        <div class="sc-card-title">
                            <span class="sc-step-number">6</span>
                            <div>
                                <h2>Étape 6 — Images, vidéo et publication</h2>
                                <p class="sc-note">Ajoutez des visuels de qualité et, si possible, une vidéo pour renforcer la confiance et améliorer la conversion.</p>
                            </div>
                        </div>
                        <span class="sc-badge orange">Médias</span>
                    </div>

                    <div class="sc-card-body">
                        <div class="sc-upload-grid">
                            <div class="sc-upload-box">
                                <h4>Images produit <span class="sc-required">*</span></h4>
                                <p>1 à 5 images · JPG, PNG, WEBP</p>
                                <label class="sc-dropzone" for="scImages">
                                    <span><i data-lucide="cloud-upload"></i><strong>Glissez-déposez vos images</strong><span>ou cliquez pour parcourir</span></span>
                                    <input type="file" name="images[]" id="scImages" accept="image/*" multiple hidden>
                                </label>
                                <p class="sc-upload-meta">Recommandé : 2000 × 1600 px<br>Format 4:3 · Max 5 Mo par image</p>
                                <div class="sc-thumbs" id="scImageThumbs">
                                    @for($i = 0; $i < 5; $i++) <span class="sc-thumb"><i data-lucide="image"></i></span> @endfor
                                </div>
                                <div class="sc-file-name"><span id="scImagesCount">0</span> / 5 images</div>
                            </div>
                            <div class="sc-upload-box">
                                <h4>Vidéo produit <span style="font-weight:700;color:#64748b;">optionnelle</span></h4>
                                <p>MP4, MOV, WEBM ou M4V · 50 MB max</p>
                                <label class="sc-dropzone" for="scVideo">
                                    <span><i data-lucide="film"></i><strong>Glissez-déposez votre vidéo</strong><span>ou cliquez pour parcourir</span></span>
                                    <input type="file" name="product_video" id="scVideo" accept="video/mp4,video/quicktime,video/webm,video/x-m4v" hidden>
                                </label>
                                <div class="sc-tip green" style="margin-top:12px;"><i data-lucide="shield-check"></i><span>Une vidéo augmente la confiance et explique mieux votre produit.</span></div>
                                <div class="sc-file-name" data-file-name="scVideo">{{ $product?->product_video_path ? 'Vidéo existante' : '0 / 1 vidéo' }}</div>
                            </div>
                        </div>

                        <div class="sc-field sc-full" style="margin-top:18px;">
                            <label>Lien vidéo externe <i class="sc-info-icon" data-lucide="info"></i></label>
                            <input class="sc-input" type="url" name="product_video_url" id="scVideoUrl" value="{{ old('product_video_url', $product?->product_video_url) }}" placeholder="Ex. : https://www.youtube.com/watch?v=abcd1234">
                            <p class="sc-help">Vimeo, YouTube ou un autre lien public. Cette vidéo apparaîtra dans l’onglet vidéo.</p>
                        </div>

                        <div class="sc-tip" style="margin-top:18px;"><i data-lucide="video"></i><span><strong>Onglet vidéo sur la fiche produit</strong><br>L’onglet “Vidéo” sera visible sur votre fiche produit uniquement si vous ajoutez une vidéo ou un lien vidéo externe.</span></div>

                        <div class="sc-section-box sc-checklist">
                            <h3 class="sc-section-title" style="color:var(--sc-text);">Checklist de publication</h3>
                            @foreach([
                                ['Titre renseigné', 'Produit'],
                                ['Prix défini', 'À partir du prix vendeur'],
                                ['Livraison configurée', '1 mode de livraison'],
                                ['Images ajoutées', '1 à 5 images'],
                                ['Données techniques BTP', 'Renseignées'],
                                ['Offre et prix', 'Complète'],
                                ['Négociation', 'Paramétrée'],
                            ] as $check)
                                <div class="sc-check-row"><i data-lucide="check-circle-2"></i><strong>{{ $check[0] }}</strong><span>{{ $check[1] }}</span><span class="ok">OK</span></div>
                            @endforeach
                            <div class="sc-tip green" style="margin-top:16px;"><i data-lucide="check-circle"></i><span><strong>Toutes les informations obligatoires sont complètes.</strong><br>Votre produit est prêt à être publié.</span></div>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <button type="button" class="sc-btn" data-prev><i data-lucide="arrow-left"></i> Retour</button>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="submit" name="save_as_draft" value="1" class="sc-btn primary"><i data-lucide="save"></i> Enregistrer comme brouillon</button>
                            <button type="submit" class="sc-btn orange"><i data-lucide="rocket"></i> {{ $isEdit ? 'Mettre à jour le produit' : 'Publier le produit' }}</button>
                        </div>
                    </div>
                </article>
            </main>

            <aside class="sc-side">
                <div class="sc-summary-card" id="scSummaryPublication">
                    <h3 id="scSummaryTitle">Aperçu de la publication</h3>
                    <div class="sc-summary-progress">
                        <div class="sc-progress-track"><div class="sc-progress-bar" id="scProgressBar"></div></div>
                        <span class="sc-progress-value" id="scProgressValue">10%</span>
                    </div>

                    <div id="scPreviewSkeleton">
                        <div class="sc-product-placeholder"><i data-lucide="package"></i></div>
                        <div class="sc-skeleton"><span></span><span></span></div>
                    </div>

                    <div id="scReadyPreview" class="sc-ready-preview" hidden>
                        <img class="sc-ready-img" id="scReadyImg" src="" alt="Aperçu produit">
                        <div><div class="sc-ready-title" id="scReadyName">Nouveau produit</div><div class="sc-ready-sub" id="scReadyDetails">SKU · Catégorie</div></div>
                    </div>

                    <div id="scSummaryRows">
                        <div class="sc-summary-row"><span>Mode de livraison</span><strong id="sumDelivery">À définir</strong></div>
                        <div class="sc-summary-row"><span>Négociation</span><strong id="sumNegotiation">Non activée</strong></div>
                        <div class="sc-summary-row"><span>Visibilité</span><strong>À définir</strong></div>
                    </div>

                    <button type="button" class="sc-btn orange full" data-next id="scSideNext">Continuer <i data-lucide="arrow-right"></i></button>
                </div>

                <div class="sc-rules-card" id="scRulesCard">
                    <h3>Règles et bonnes pratiques</h3>
                    <div class="sc-rules-list">
                        <div class="sc-rule"><i data-lucide="info"></i><span>Renseignez des informations justes et à jour.</span></div>
                        <div class="sc-rule"><i data-lucide="info"></i><span>Évitez les majuscules excessives et le langage promotionnel.</span></div>
                        <div class="sc-rule"><i data-lucide="shield-check"></i><span>Une bonne fiche produit augmente vos ventes.</span></div>
                    </div>
                    <a class="sc-link" href="#">Voir toutes les règles <i data-lucide="external-link"></i></a>
                </div>
            </aside>
        </div>
    </form>
</section>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-sc-product-wizard]');
        if (!root) return;

        const steps = Array.from(root.querySelectorAll('[data-step-target]'));
        const panels = Array.from(root.querySelectorAll('[data-step-panel]'));
        const form = document.getElementById('scProductForm');
        let currentStep = 1;

        const fmt = (num) => new Intl.NumberFormat('fr-FR').format(Math.max(0, Math.round(Number(num) || 0))) + ' FCFA';
        const qs = (selector) => root.querySelector(selector);
        const qsa = (selector) => Array.from(root.querySelectorAll(selector));

        function setIcons() { if (window.lucide) window.lucide.createIcons(); }

        function goToStep(step) {
            currentStep = Math.max(1, Math.min(6, Number(step) || 1));
            steps.forEach((btn, index) => {
                const num = index + 1;
                btn.classList.toggle('is-active', num === currentStep);
                btn.classList.toggle('is-done', num < currentStep);
                const dot = btn.querySelector('[data-step-dot]');
                if (dot) dot.innerHTML = num < currentStep ? '<i data-lucide="check"></i>' : String(num);
            });
            panels.forEach(panel => panel.classList.toggle('is-active', Number(panel.dataset.stepPanel) === currentStep));
            updateSummary();
            setIcons();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        steps.forEach(btn => btn.addEventListener('click', () => goToStep(btn.dataset.stepTarget)));
        qsa('[data-next]').forEach(btn => btn.addEventListener('click', () => currentStep < 6 ? goToStep(currentStep + 1) : form.requestSubmit()));
        qsa('[data-prev]').forEach(btn => btn.addEventListener('click', () => goToStep(currentStep - 1)));

        function updateCounters() {
            qsa('[data-count-for]').forEach(node => {
                const field = document.getElementById(node.dataset.countFor);
                node.textContent = field ? (field.value || '').length : 0;
            });
        }
        qsa('input, textarea, select').forEach(el => {
            el.addEventListener('input', () => { updateCounters(); updateCalc(); updateSummary(); });
            el.addEventListener('change', () => { updateCalc(); updateSummary(); handleDeliveryMode(); });
        });

        function updateCalc() {
            const price = Number(qs('#scPrice')?.value || 0);
            const promo = Number(qs('#scPromo')?.value || 0);
            const base = promo > 0 && promo < price ? promo : price;
            const commission = Math.round(base * 0.12);
            qs('#scCommission').textContent = fmt(commission);
            qs('#scClientPrice').textContent = fmt(base + commission);
            qs('#scNetSeller').textContent = fmt(price);
            const p1 = qs('#scPriceP1');
            const p2 = qs('#scPriceP2');
            const p3 = qs('#scMinNegotiationPrice');
            if (p1 && !p1.value && price) p1.value = price;
            if (p2 && p3 && price && p3.value) p2.value = Math.round((price + Number(p3.value)) / 2);
        }

        function handleDeliveryMode() {
            const mode = root.querySelector('input[name="delivery_mode"]:checked')?.value || 'ovanie';


            updateSummary();
        }

        ['scLength','scWidth','scHeight'].forEach(id => {
            const el = document.getElementById(id);
            el?.addEventListener('input', function () {
                const l = Number(qs('#scLength')?.value || 0);
                const w = Number(qs('#scWidth')?.value || 0);
                const h = Number(qs('#scHeight')?.value || 0);
                if (l && w && h && qs('#scVolume')) qs('#scVolume').value = ((l*w*h)/1000000).toFixed(4);
            });
        });

        function updateSummary() {
            const progressMap = {1:10,2:35,3:50,4:65,5:80,6:100};
            const progress = progressMap[currentStep] || 10;
            qs('#scProgressBar').style.width = progress + '%';
            qs('#scProgressValue').textContent = progress + '%';
            qs('#scSummaryTitle').textContent = currentStep === 6 ? 'Prêt à publier' : 'Aperçu de la publication';

            const name = qs('#scName')?.value?.trim() || 'Nouveau produit';
            const sku = qs('#scSku')?.value?.trim() || 'SKU non renseigné';
            const mode = root.querySelector('input[name="delivery_mode"]:checked')?.value || 'ovanie';
            const modeLabel = 'OVANIE Logistics';
            const nego = root.querySelector('input[name="is_negotiable"]:checked')?.value === '1';
            qs('#sumDelivery').textContent = modeLabel;
            qs('#sumNegotiation').innerHTML = nego ? '<span class="green">● Activée</span>' : 'Non activée';

            if (currentStep === 6) {
                qs('#scPreviewSkeleton').hidden = true;
                qs('#scReadyPreview').hidden = false;
                qs('#scReadyName').textContent = name;
                qs('#scReadyDetails').textContent = sku + ' · ' + modeLabel;
                if (!qs('#scReadyImg').src) {
                    qs('#scReadyImg').src = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" rx="14" fill="#f1f5f9"/><path d="M27 34l21-12 21 12v28L48 74 27 62z" fill="none" stroke="#94a3b8" stroke-width="4"/><path d="M27 34l21 12 21-12M48 46v28" fill="none" stroke="#94a3b8" stroke-width="4"/></svg>');
                }
                qs('#scSummaryRows').innerHTML = `
                    <div class="sc-summary-row"><span>Mode de livraison</span><strong>${modeLabel}</strong></div>
                    <div class="sc-summary-row"><span>Négociation</span><strong>${nego ? 'Activée' : 'Non'}</strong></div>
                    <div class="sc-summary-row"><span>Nombre d’images</span><strong>${qs('#scImagesCount')?.textContent || '0'}</strong></div>
                    <div class="sc-summary-row"><span>Vidéo ajoutée</span><strong>${(qs('#scVideo')?.files?.length || qs('#scVideoUrl')?.value) ? 'Oui' : 'Non'}</strong></div>
                    <div class="sc-summary-row"><span>Visibilité</span><strong>Publique</strong></div>`;
                const sideNext = qs('#scSideNext');
                if (sideNext) {
                    sideNext.outerHTML = '<button type="submit" class="sc-btn orange full"><i data-lucide="rocket"></i> Publier le produit</button><button type="submit" name="save_as_draft" value="1" class="sc-btn full primary"><i data-lucide="save"></i> Enregistrer comme brouillon</button>';
                }
            }
        }

        const imageInput = qs('#scImages');
        imageInput?.addEventListener('change', function () {
            const count = Math.min(this.files.length, 5);
            qs('#scImagesCount').textContent = String(count);
            const thumbs = qsa('#scImageThumbs .sc-thumb');
            thumbs.forEach((thumb, index) => thumb.style.borderStyle = index < count ? 'solid' : 'dashed');
            updateSummary();
        });
        qsa('input[type="file"]').forEach(input => {
            input.addEventListener('change', function () {
                const target = root.querySelector(`[data-file-name="${this.id}"]`);
                if (target) target.textContent = this.files?.[0]?.name || '';
                updateSummary();
            });
        });

        const addAttr = qs('#scAddAttr');
        addAttr?.addEventListener('click', function () {
            const list = qs('#scAttrList');
            const index = list.querySelectorAll('.sc-attr-row').length;
            const row = document.createElement('div');
            row.className = 'sc-attr-row';
            row.innerHTML = `<input class="sc-input" name="product_attributes[${index}][label]" placeholder="Caractéristique"><input class="sc-input" name="product_attributes[${index}][value]" placeholder="Valeur"><input class="sc-input" name="product_attributes[${index}][unit]" placeholder="Unité"><button class="sc-delete-row" type="button" data-remove-row><i data-lucide="trash-2"></i></button>`;
            list.appendChild(row);
            setIcons();
        });
        root.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-remove-row]');
            if (btn) btn.closest('.sc-attr-row')?.remove();
        });

        updateCounters();
        updateCalc();
        handleDeliveryMode();
        updateSummary();
        setIcons();
    });
</script>
@endsection
