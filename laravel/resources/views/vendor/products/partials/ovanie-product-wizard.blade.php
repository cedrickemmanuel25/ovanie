@php
    use Illuminate\Support\Facades\Route;

    $product = $product ?? null;
    $isEdit = (bool) ($isEdit ?? false);
    $user = auth()->user();
    $shop = $shop ?? $user?->shop;

    $storeUrl = $isEdit
        ? (Route::has('vendor.products.update') ? route('vendor.products.update', $product) : route('daniel.products.update', $product))
        : (Route::has('vendor.products.store') ? route('vendor.products.store', [], false) : route('daniel.products.store', [], false));

    $productsUrl = Route::has('vendor.products')
        ? route('vendor.products')
        : (Route::has('daniel.products') ? route('daniel.products') : url('/vendeur/products'));

    $createOptionsUrl = Route::has('vendor.add_product')
        ? route('vendor.add_product')
        : (Route::has('daniel.add_product') ? route('daniel.add_product') : url('/vendeur/products/create'));

    $categoryOptions = collect($categories ?? []);
    $mainCategories = $categoryOptions->filter(fn ($category) => blank($category->parent_id))->values();
    $childCategories = $categoryOptions->filter(fn ($category) => filled($category->parent_id))->values();

    $selectedCategoryId = (string) old('category_id', $product?->category_id ?? '');
    $selectedCategory = $categoryOptions->first(fn ($category) => (string) $category->id === $selectedCategoryId);
    $selectedMainCategoryId = (string) ($selectedCategory?->parent_id ?: $selectedCategory?->id ?: '');

    $selectedUnit = old('unit', $product?->unit ?? 'piece');
    $selectedSaleType = old('sale_type', $product?->sale_type ?? 'normal');
    $selectedPromoType = old('type', $product?->type ?? 'none');
    $selectedProductState = old('product_state', $product?->product_state ?? 'new');
    $isNegotiable = old('is_negotiable', ($product?->is_negotiable ?? false) ? '1' : '0') === '1'
        || filled(old('price_p3', $product?->price_p3));

    $shopLogisticsType = ($shop && method_exists($shop, 'usesSellerLogistics') && $shop->usesSellerLogistics())
        ? 'seller'
        : 'ovanie';

    $commissionRate = (float) config('marketplace.default_commission_rate', 0.05);
    $commissionPercentLabel = rtrim(rtrim(number_format($commissionRate * 100, 1, ',', ' '), '0'), ',');

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

    $attributes = collect($product?->product_attributes ?? []);
    $keywordAttribute = $attributes->first(function ($attribute) {
        return mb_strtolower(trim((string) ($attribute['label'] ?? ''))) === 'mots-clés';
    });
    $keywordsValue = old('product_attributes.0.value', $keywordAttribute['value'] ?? '');

    $fragileValue = (string) old('fragile', $product ? ($product->fragile ? '1' : '0') : '0');
    $unloadingValue = (string) old('requires_unloading', $product ? ($product->requires_unloading ? '1' : '0') : '0');

    $existingImages = $product?->images ? collect($product->images)->take(5) : collect();
    $existingImagesCount = $existingImages->count();
@endphp

@section('styles')
<style>
    :root {
        --pw-navy: #0a2454;
        --pw-blue: #0b5ce5;
        --pw-blue-dark: #0648c9;
        --pw-orange: #ff5a0a;
        --pw-green: #12b76a;
        --pw-text: #10234a;
        --pw-muted: #687a99;
        --pw-border: #d9e2ef;
        --pw-soft: #f7f9fc;
        --pw-shadow: 0 8px 24px rgba(16, 35, 74, .08);
    }

    .pw-page,
    .pw-page * { box-sizing: border-box; }

    .pw-page {
        width: 100%;
        max-width: 1220px;
        margin: 0 auto;
        padding: 16px 12px 26px;
        color: var(--pw-text);
    }

    .pw-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 12px;
        color: var(--pw-blue);
        font-size: 12px;
        font-weight: 600;
    }

    .pw-breadcrumb svg { width: 14px; height: 14px; color: #8da0bd; }
    .pw-breadcrumb .current { color: var(--pw-text); }

    .pw-title {
        margin: 0;
        color: var(--pw-navy);
        font-size: clamp(28px, 2.7vw, 38px);
        line-height: 1.08;
        font-weight: 900;
        letter-spacing: -.035em;
    }

    .pw-subtitle {
        margin: 6px 0 16px;
        color: #53698f;
        font-size: 13px;
        font-weight: 500;
    }

    .pw-alert {
        margin: 0 0 12px;
        padding: 11px 14px;
        border-radius: 9px;
        font-size: 12px;
        line-height: 1.45;
        font-weight: 700;
    }

    .pw-alert.danger { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
    .pw-alert.success { background: #ecfdf5; border: 1px solid #bbf7d0; color: #166534; }
    .pw-js-error { display: none; }
    .pw-js-error.is-visible { display: block; }

    .pw-stepper {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        margin: 4px 0 20px;
        padding: 0 24px;
    }

    .pw-step {
        position: relative;
        z-index: 1;
        min-width: 0;
        padding: 0;
        border: 0;
        background: transparent;
        color: #405274;
        text-align: center;
        cursor: pointer;
    }

    .pw-step:not(:last-child)::after {
        content: '';
        position: absolute;
        z-index: -1;
        top: 18px;
        left: calc(50% + 22px);
        width: calc(100% - 44px);
        height: 1px;
        background: #bcc8d9;
    }

    .pw-step.is-done:not(:last-child)::after { background: var(--pw-green); }

    .pw-step-dot {
        width: 38px;
        height: 38px;
        margin: 0 auto 8px;
        display: grid;
        place-items: center;
        border: 1.5px solid #b9c4d4;
        border-radius: 50%;
        background: #fff;
        color: #52617b;
        font-size: 13px;
        font-weight: 800;
        box-shadow: 0 0 0 7px var(--ov-soft, #f6f9fc);
    }

    .pw-step-dot svg { width: 20px; height: 20px; }
    .pw-step-label { display: block; color: #35496d; font-size: 11px; font-weight: 500; white-space: nowrap; }

    .pw-step.is-active .pw-step-dot {
        border-color: var(--pw-blue);
        background: var(--pw-blue);
        color: #fff;
        box-shadow: 0 5px 14px rgba(11, 92, 229, .24), 0 0 0 7px var(--ov-soft, #f6f9fc);
    }

    .pw-step.is-active .pw-step-label { color: var(--pw-blue); font-weight: 700; }

    .pw-step.is-done .pw-step-dot {
        border-color: var(--pw-green);
        background: #f5fffa;
        color: var(--pw-green);
    }

    .pw-step.is-done .pw-step-label { color: #04914e; }

    .pw-panel {
        display: none;
        background: #fff;
        border: 1px solid var(--pw-border);
        border-radius: 10px;
        box-shadow: var(--pw-shadow);
        overflow: hidden;
    }

    .pw-panel.is-active { display: block; }

    .pw-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px 12px;
    }

    .pw-panel-heading {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .pw-number {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: linear-gradient(145deg, #ff6a18, #f04400);
        color: #fff;
        font-size: 16px;
        font-weight: 800;
        box-shadow: 0 5px 14px rgba(255, 90, 10, .22);
    }

    .pw-panel-head h2 {
        margin: 1px 0 4px;
        color: var(--pw-text);
        font-size: 18px;
        line-height: 1.2;
        font-weight: 800;
    }

    .pw-panel-head p {
        margin: 0;
        color: #687a99;
        font-size: 11.5px;
        line-height: 1.45;
        font-weight: 500;
    }

    .pw-info-icon {
        width: 22px;
        height: 22px;
        flex: 0 0 22px;
        color: #405274;
    }

    .pw-body { padding: 10px 22px 18px; }

    .pw-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 26px; }
    .pw-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px 18px; }
    .pw-grid-5 { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
    .pw-full { grid-column: 1 / -1; }

    .pw-field label,
    .pw-field-label {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 0 0 6px;
        color: #15284d;
        font-size: 11.5px;
        line-height: 1.2;
        font-weight: 700;
    }

    .pw-required { color: #ff3028; }

    .pw-input,
    .pw-select,
    .pw-textarea {
        width: 100%;
        border: 1px solid #cbd6e5;
        border-radius: 6px;
        background: #fff;
        color: #11264e;
        outline: none;
        font-size: 12px;
        font-weight: 500;
        transition: border-color .16s ease, box-shadow .16s ease;
    }

    .pw-input,
    .pw-select { height: 40px; padding: 0 12px; }
    .pw-select { appearance: auto; }
    .pw-textarea { min-height: 72px; padding: 10px 12px; line-height: 1.5; resize: vertical; }
    .pw-textarea.tech { min-height: 92px; }

    .pw-input::placeholder,
    .pw-textarea::placeholder { color: #8d99ad; }

    .pw-input:focus,
    .pw-select:focus,
    .pw-textarea:focus {
        border-color: var(--pw-blue);
        box-shadow: 0 0 0 3px rgba(11, 92, 229, .08);
    }

    .pw-input[readonly],
    .pw-input:disabled,
    .pw-select:disabled { background: #f5f7fa; color: #6f7d94; }

    .pw-input.is-invalid,
    .pw-select.is-invalid,
    .pw-textarea.is-invalid,
    .pw-drop.is-invalid { border-color: #f43f5e !important; box-shadow: 0 0 0 3px rgba(244, 63, 94, .08); }

    .pw-help {
        margin: 5px 0 0;
        color: #7b8aa4;
        font-size: 10px;
        line-height: 1.4;
        font-weight: 500;
    }

    .pw-addon { position: relative; }
    .pw-addon .pw-input { padding-right: 62px; }
    .pw-addon > span {
        position: absolute;
        top: 0;
        right: 0;
        height: 40px;
        min-width: 54px;
        padding: 0 10px;
        display: grid;
        place-items: center;
        border-left: 1px solid #d8e1ec;
        color: #536587;
        font-size: 11px;
        font-weight: 600;
    }

    .pw-section-title {
        margin: 2px 0 14px;
        color: var(--pw-text);
        font-size: 13px;
        font-weight: 700;
    }

    .pw-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-top: 14px;
    }

    .pw-choice {
        position: relative;
        min-height: 84px;
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 14px 16px;
        border: 1.5px solid #bfcbdc;
        border-radius: 7px;
        background: #fff;
        cursor: pointer;
        transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
    }

    .pw-choice input {
        width: 19px;
        height: 19px;
        margin: 0;
        accent-color: var(--pw-blue);
    }

    .pw-choice-icon {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        display: grid;
        place-items: center;
        color: #526b96;
    }

    .pw-choice-icon svg { width: 36px; height: 36px; stroke-width: 1.7; }
    .pw-choice strong { display: block; margin-bottom: 4px; color: #183160; font-size: 13px; font-weight: 800; }
    .pw-choice small { display: block; color: #586b8e; font-size: 10.5px; line-height: 1.45; font-weight: 500; }

    .pw-choice:has(input:checked) {
        border-color: var(--pw-blue);
        background: #f8fbff;
        box-shadow: 0 0 0 1px rgba(11, 92, 229, .08);
    }

    .pw-choice:has(input:checked) .pw-choice-icon { color: var(--pw-blue); }

    .pw-calc-title {
        margin: 18px 0 10px;
        color: var(--pw-text);
        font-size: 14px;
        font-weight: 800;
    }

    .pw-calc-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .pw-calc-card {
        min-height: 88px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border: 1px solid #d9e2ef;
        border-radius: 7px;
        background: #fff;
    }

    .pw-calc-card.green { border-color: #20b95d; background: linear-gradient(90deg, #f7fff9, #fff); }

    .pw-calc-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid !important;
        place-items: center;
        padding: 0;
        border-radius: 50%;
        background: #edf4ff;
        color: #2a5fc4;
    }

    .pw-calc-card.green .pw-calc-icon { background: #dcfce7; color: #10a746; }
    .pw-calc-icon svg {
        display: block;
        width: 23px;
        height: 23px;
        margin: 0 !important;
        place-self: center;
    }
    .pw-calc-card span { display: block; color: #697b9b; font-size: 10.5px; font-weight: 500; }
    .pw-calc-card strong { display: block; margin: 4px 0 1px; color: #203b70; font-size: 18px; line-height: 1; font-weight: 800; }
    .pw-calc-card small { color: #536990; font-size: 10px; }
    .pw-calc-card.green span,
    .pw-calc-card.green strong,
    .pw-calc-card.green small { color: #07963b; }

    .pw-tip {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 6px;
        background: #eef5ff;
        color: #124aac;
        font-size: 11.5px;
        line-height: 1.45;
        font-weight: 500;
    }

    .pw-tip svg { width: 34px; height: 34px; flex: 0 0 34px; }
    .pw-tip strong { font-weight: 700; }

    .pw-logistics-grid { margin-top: 16px; }

    .pw-flag-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 40px;
        margin-top: 18px;
    }

    .pw-flag-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 8px;
    }

    .pw-flag-card {
        min-height: 118px;
        padding: 12px 14px;
        border: 1.5px solid #bfcbdc;
        border-radius: 7px;
        background: #fff;
        cursor: pointer;
        text-align: center;
    }

    .pw-flag-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .pw-flag-radio {
        width: 18px;
        height: 18px;
        display: block;
        margin: 0 0 1px;
        border: 1.5px solid #8696ae;
        border-radius: 50%;
        background: #fff;
    }

    .pw-flag-icon {
        height: 42px;
        display: grid;
        place-items: center;
        color: #536b95;
    }

    .pw-flag-icon svg { width: 32px; height: 32px; stroke-width: 1.7; }
    .pw-flag-card strong { display: block; margin-top: 2px; color: #183160; font-size: 13px; font-weight: 800; }
    .pw-flag-card small { display: block; margin-top: 4px; color: #667a9c; font-size: 10.5px; }

    .pw-flag-card:has(input:checked) { border-color: var(--pw-blue); background: #f8fbff; }
    .pw-flag-card:has(input:checked) .pw-flag-radio { border: 5px solid var(--pw-blue); }
    .pw-flag-card:has(input:checked) .pw-flag-icon { color: var(--pw-blue); }

    .pw-unloading-wrap { margin-top: 14px; }

    .pw-upload-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .pw-upload-card {
        min-width: 0;
        padding: 16px;
        border: 1px solid #d9e2ef;
        border-radius: 7px;
        background: #fff;
    }

    .pw-upload-card h3 { margin: 0 0 6px; color: #183160; font-size: 12px; font-weight: 800; }
    .pw-upload-card > p { margin: 0 0 12px; color: #7384a2; font-size: 10px; }

    .pw-drop {
        min-height: 190px;
        display: grid;
        place-items: center;
        border: 1.5px dashed #91a9d0;
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
    }

    .pw-drop-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 18px;
        text-align: center;
    }

    .pw-drop-inner svg { width: 52px; height: 52px; color: #536b95; stroke-width: 1.5; }
    .pw-drop-inner strong { color: #1b3565; font-size: 12px; font-weight: 700; }
    .pw-drop-inner span { color: var(--pw-blue); font-size: 11px; font-weight: 700; }

    .pw-thumbs {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    .pw-thumb {
        aspect-ratio: 1 / 1;
        min-height: 62px;
        display: grid;
        place-items: center;
        overflow: hidden;
        border: 1.3px dashed #a9bbd6;
        border-radius: 6px;
        background: #fff;
        color: #7890b4;
    }

    .pw-thumb svg { width: 26px; height: 26px; }
    .pw-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pw-file-count { margin-top: 10px; color: #667a9c; font-size: 10px; font-weight: 500; }

    .pw-video-preview { display: none; margin-top: 10px; overflow: hidden; border-radius: 6px; }
    .pw-video-preview.is-visible { display: block; }
    .pw-video-preview video { width: 100%; max-height: 140px; display: block; background: #0f172a; }

    .pw-field label.pw-file-drop {
        min-height: 130px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        margin: 0;
        padding: 0;
        border: 1.5px dashed #91a9d0;
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
    }

    .pw-file-drop-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: max-content;
        max-width: 100%;
        text-align: center;
        padding: 12px;
    }

    .pw-file-drop-inner svg {
        width: 38px;
        height: 38px;
        color: #61799f;
        display: block;
        margin: 0 auto;
    }

    .pw-file-drop-inner strong {
        display: block;
        margin-top: 6px;
        color: #1b3565;
        font-size: 11.5px;
    }

    .pw-file-drop-inner span {
        display: block;
        margin-top: 3px;
        color: var(--pw-blue);
        font-size: 10.5px;
        font-weight: 700;
    }

    .pw-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 22px 18px;
        border-top: 1px solid #e7edf5;
        background: #fff;
    }

    .pw-actions-left {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #405274;
        font-size: 10px;
        font-weight: 500;
    }

    .pw-actions-left svg { width: 20px; height: 20px; color: var(--pw-blue); }

    .pw-actions-right { display: flex; align-items: center; justify-content: flex-end; gap: 14px; }

    .pw-btn {
        min-width: 105px;
        height: 44px;
        padding: 0 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border: 1.5px solid var(--pw-blue);
        border-radius: 5px;
        background: #fff;
        color: var(--pw-blue-dark);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .pw-btn svg { width: 17px; height: 17px; }

    .pw-btn.primary {
        min-width: 138px;
        border-color: var(--pw-blue);
        background: linear-gradient(135deg, #1168ef, #0648c9);
        color: #fff;
        box-shadow: 0 5px 14px rgba(11, 92, 229, .2);
    }

    .pw-btn.orange {
        min-width: 210px;
        border-color: var(--pw-orange);
        background: linear-gradient(135deg, #ff6c14, #ff4b00);
        color: #fff;
        box-shadow: 0 7px 16px rgba(255, 90, 10, .24);
    }

    .pw-btn.secondary { border-color: #9cadc8; color: #223b6c; }

    .pw-final-actions { justify-content: flex-end; }

    @media (max-width: 1000px) {
        .pw-page { padding-left: 8px; padding-right: 8px; }
        .pw-grid-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pw-flag-grid { gap: 18px; }
        .pw-stepper { padding: 0 10px; }
    }

    @media (max-width: 760px) {
        .pw-page { padding: 12px 0 22px; }
        .pw-title { font-size: 28px; }
        .pw-subtitle { font-size: 12px; }
        .pw-stepper { overflow-x: auto; grid-template-columns: repeat(5, 128px); padding: 0 4px 8px; }
        .pw-step-dot { box-shadow: 0 0 0 5px var(--ov-soft, #f6f9fc); }
        .pw-grid-2,
        .pw-grid-3,
        .pw-grid-5,
        .pw-options,
        .pw-calc-grid,
        .pw-flag-grid,
        .pw-upload-grid { grid-template-columns: 1fr; }
        .pw-panel-head { padding: 18px 16px 10px; }
        .pw-body { padding: 10px 16px 16px; }
        .pw-actions { padding: 14px 16px 16px; flex-direction: column; align-items: stretch; }
        .pw-actions-left { justify-content: center; text-align: center; }
        .pw-actions-right { width: 100%; }
        .pw-btn { flex: 1; }
        .pw-final-actions { flex-wrap: wrap; }
        .pw-final-actions .pw-btn.orange { flex-basis: 100%; }
    }
</style>
@endsection

@section('content')
<section class="pw-page" data-pw-wizard>
    <nav class="pw-breadcrumb" aria-label="Fil d’Ariane">
        <span>Espace vendeur</span>
        <i data-lucide="chevron-right"></i>
        <span>Produits</span>
        <i data-lucide="chevron-right"></i>
        <span class="current">{{ $isEdit ? 'Modifier le produit' : 'Ajouter un produit' }}</span>
    </nav>

    <h1 class="pw-title">{{ $isEdit ? 'Modifier un produit' : 'Ajouter un produit' }}</h1>
    <p class="pw-subtitle">Créez une fiche produit claire, complète et prête à vendre.</p>

    @if($errors->any())
        <div class="pw-alert danger"><strong>Correction nécessaire :</strong> {{ $errors->first() }}</div>
    @endif

    @if(session('success'))
        <div class="pw-alert success">{{ session('success') }}</div>
    @endif

    <div class="pw-alert danger pw-js-error" id="pwStepError">Veuillez compléter les champs obligatoires avant de continuer.</div>

    <form id="pwProductForm" method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <input type="hidden" name="delivery_mode" value="{{ $shopLogisticsType }}">
        <input type="hidden" name="type" value="{{ $selectedPromoType }}">
        <input type="hidden" name="product_attributes[0][label]" value="Mots-clés">
        <input type="hidden" id="pwVisibilityDraft" name="save_as_draft" value="1" disabled>

        <div class="pw-stepper" aria-label="Étapes du formulaire">
            @foreach([1 => 'Informations', 2 => 'Prix & unité', 3 => 'Détail technique', 4 => 'Logistique', 5 => 'Média'] as $number => $label)
                <button type="button" class="pw-step {{ $number === 1 ? 'is-active' : '' }}" data-step="{{ $number }}">
                    <span class="pw-step-dot" data-dot>{{ $number }}</span>
                    <span class="pw-step-label">{{ $label }}</span>
                </button>
            @endforeach
        </div>

        {{-- ÉTAPE 1 --}}
        <article class="pw-panel is-active" data-panel="1">
            <div class="pw-panel-head">
                <div class="pw-panel-heading">
                    <span class="pw-number">1</span>
                    <div>
                        <h2>Étape 1 — Informations</h2>
                        <p>Renseignez les informations essentielles pour identifier clairement votre produit.</p>
                    </div>
                </div>
                <i class="pw-info-icon" data-lucide="info"></i>
            </div>

            <div class="pw-body">
                <div class="pw-grid-2">
                    <div class="pw-field">
                        <label for="pwName">Nom du produit <span class="pw-required">*</span></label>
                        <input class="pw-input" id="pwName" name="name" value="{{ old('name', $product?->name) }}" placeholder="Ex. Ciment CPA 42.5 - Sac 50 kg" data-required-step="1">
                    </div>

                    <div class="pw-field">
                        <label for="pwSubCategory">Sous-catégorie <span class="pw-required">*</span></label>
                        <select class="pw-select" id="pwSubCategory" name="category_id" data-required-step="1">
                            <option value="">Sélectionner une sous-catégorie</option>
                            @foreach($childCategories as $category)
                                <option value="{{ $category->id }}" data-parent="{{ $category->parent_id }}" @selected($selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                            @if($selectedCategory && blank($selectedCategory->parent_id))
                                <option value="{{ $selectedCategory->id }}" data-parent="{{ $selectedCategory->id }}" selected>{{ $selectedCategory->name }}</option>
                            @endif
                        </select>
                        <p class="pw-help" id="pwCategorySuggestedHint" style="display:none">Catégorie suggérée automatiquement à partir du nom du produit. Modifiable si besoin.</p>
                    </div>

                    <div class="pw-field">
                        <label for="pwBrand">Marque</label>
                        <input class="pw-input" id="pwBrand" name="brand" value="{{ old('brand', $product?->brand) }}" placeholder="Ex. Lafarge">
                    </div>

                    <div class="pw-field">
                        <label for="pwProductType">Type de produit</label>
                        <input class="pw-input" id="pwProductType" name="material_grade" value="{{ old('material_grade', $product?->material_grade) }}" placeholder="Ex. Ciment, peinture, carrelage...">
                    </div>

                    <div class="pw-field">
                        <label for="pwMainCategory">Catégorie principale <span class="pw-required">*</span></label>
                        <select class="pw-select" id="pwMainCategory" data-required-step="1">
                            <option value="">Sélectionner une catégorie</option>
                            @foreach($mainCategories as $category)
                                <option value="{{ $category->id }}" @selected($selectedMainCategoryId === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pw-field">
                        <label for="pwState">État du produit</label>
                        <select class="pw-select" id="pwState" name="product_state">
                            <option value="new" @selected($selectedProductState === 'new')>Neuf</option>
                            <option value="reconditioned" @selected($selectedProductState === 'reconditioned')>Reconditionné</option>
                            <option value="used" @selected($selectedProductState === 'used')>Occasion</option>
                        </select>
                    </div>

                    <div class="pw-field">
                        <label for="pwShortDescription">Description courte</label>
                        <input class="pw-input" id="pwShortDescription" name="short_description" maxlength="255" value="{{ old('short_description', $product?->short_description) }}" placeholder="Ex. Ciment gris haute résistance pour travaux de maçonnerie et gros œuvre">
                    </div>

                    <div class="pw-field">
                        <label for="pwKeywords">Mots-clés</label>
                        <input class="pw-input" id="pwKeywords" name="product_attributes[0][value]" value="{{ $keywordsValue }}" placeholder="Ex. ciment, gros œuvre, maçonnerie">
                    </div>

                    <div class="pw-field">
                        <label for="pwVisibility">Visibilité <span class="pw-required">*</span></label>
                        <select class="pw-select" id="pwVisibility" data-required-step="1">
                            <option value="catalogue">Visible dans le catalogue</option>
                            <option value="draft">Enregistrer comme brouillon</option>
                        </select>
                    </div>

                    <div class="pw-field pw-full">
                        <label for="pwDescription">Description détaillée <span class="pw-required">*</span></label>
                        <textarea class="pw-textarea" id="pwDescription" name="description" placeholder="Décrivez les caractéristiques, usages, avantages et informations utiles de votre produit." data-required-step="1">{{ old('description', $product?->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="pw-actions">
                <div class="pw-actions-left">
                    <i data-lucide="info"></i>
                    <span>Les champs marqués d’un astérisque sont obligatoires.</span>
                </div>
                <div class="pw-actions-right">
                    <a href="{{ $createOptionsUrl }}" class="pw-btn">Retour</a>
                    <button type="button" class="pw-btn primary" data-next>Continuer</button>
                </div>
            </div>
        </article>

        {{-- ÉTAPE 2 --}}
        <article class="pw-panel" data-panel="2">
            <div class="pw-panel-head">
                <div class="pw-panel-heading">
                    <span class="pw-number">2</span>
                    <div>
                        <h2>Étape 2 — Prix & unité</h2>
                        <p>Prix, stock, unité de vente, conditionnement et négociation.</p>
                    </div>
                </div>
                <i class="pw-info-icon" data-lucide="info"></i>
            </div>

            <div class="pw-body">
                <div class="pw-grid-3">
                    <div class="pw-field">
                        <label for="pwPrice">Prix normal <span class="pw-required">*</span></label>
                        <div class="pw-addon">
                            <input class="pw-input" type="number" id="pwPrice" name="price" value="{{ old('price', $product?->price) }}" min="1" step="1" placeholder="12 500" data-required-step="2">
                            <span>FCFA</span>
                        </div>
                    </div>

                    <div class="pw-field">
                        <label for="pwPromo">Prix promo</label>
                        <div class="pw-addon">
                            <input class="pw-input" type="number" id="pwPromo" name="promo_price" value="{{ old('promo_price', $product?->promo_price) }}" min="0" step="1" placeholder="Ex. 11 500">
                            <span>FCFA</span>
                        </div>
                    </div>

                    <div class="pw-field">
                        <label for="pwStock">Stock <span class="pw-required">*</span></label>
                        <input class="pw-input" type="number" id="pwStock" name="stock" value="{{ old('stock', $product?->stock ?? 0) }}" min="0" step="1" data-required-step="2">
                    </div>

                    <div class="pw-field">
                        <label for="pwUnit">Unité de vente <span class="pw-required">*</span></label>
                        <select class="pw-select" id="pwUnit" name="unit" data-required-step="2">
                            @foreach($unitOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedUnit === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pw-field">
                        <label for="pwUnitLabel">Libellé personnel</label>
                        <input class="pw-input" id="pwUnitLabel" name="unit_label" value="{{ old('unit_label', $product?->unit_label) }}" placeholder="Ex. Botte, paquet, rouleau...">
                    </div>

                    <div class="pw-field">
                        <label for="pwMinQty">Quantité minimum <span class="pw-required">*</span></label>
                        <input class="pw-input" type="number" id="pwMinQty" name="min_order_quantity" value="{{ old('min_order_quantity', $product?->min_order_quantity ?? 1) }}" min="1" step="1" data-required-step="2">
                    </div>

                    <div class="pw-field pw-full">
                        <label for="pwPackaging">Conditionnement</label>
                        <input class="pw-input" id="pwPackaging" name="packaging" value="{{ old('packaging', $product?->packaging) }}" placeholder="Ex. Sac 50 kg, carton 1,44 m²">
                    </div>

                    <div class="pw-field">
                        <label for="pwSaleType">Type de vente <span class="pw-required">*</span></label>
                        <select class="pw-select" id="pwSaleType" name="sale_type" data-required-step="2">
                            <option value="Vente normale" @selected($selectedSaleType === 'Vente normale' || $selectedSaleType === 'normal')>Vente normale</option>
                            <option value="Vente flash" @selected($selectedSaleType === 'Vente flash' || $selectedSaleType === 'vente flash' || $selectedSaleType === 'flash' || $selectedSaleType === 'flash_sale')>Vente flash</option>
                            <option value="Black Friday" @selected($selectedSaleType === 'Black Friday' || $selectedSaleType === 'black friday')>Black Friday</option>
                            <option value="Promo spéciale" @selected($selectedSaleType === 'Promo spéciale' || $selectedSaleType === 'promo' || $selectedSaleType === 'promo speciale')>Promo spéciale</option>
                        </select>
                    </div>
                </div>

                {{-- Promotion Dates --}}
                <div class="pw-grid-2" id="pwFlashDates" style="display: none; margin-top: 15px; margin-bottom: 15px;">
                    <div class="pw-field">
                        <label for="pwFlashStart">Date de début Flash Sale <span class="pw-required">*</span></label>
                        <input class="pw-input" type="datetime-local" id="pwFlashStart" name="flash_start_at" value="{{ old('flash_start_at', $product?->flash_start_at ? optional($product->flash_start_at)->format('Y-m-d\TH:i') : '') }}">
                    </div>
                    <div class="pw-field">
                        <label for="pwFlashEnd">Date de fin Flash Sale <span class="pw-required">*</span></label>
                        <input class="pw-input" type="datetime-local" id="pwFlashEnd" name="flash_end" value="{{ old('flash_end', $product?->flash_end ? optional($product->flash_end)->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>

                <div class="pw-grid-2" id="pwBfDates" style="display: none; margin-top: 15px; margin-bottom: 15px;">
                    <div class="pw-field">
                        <label for="pwBfStart">Date de début Black Friday <span class="pw-required">*</span></label>
                        <input class="pw-input" type="datetime-local" id="pwBfStart" name="bf_start" value="{{ old('bf_start', $product?->bf_start ? optional($product->bf_start)->format('Y-m-d\TH:i') : '') }}">
                    </div>
                    <div class="pw-field">
                        <label for="pwBfEnd">Date de fin Black Friday <span class="pw-required">*</span></label>
                        <input class="pw-input" type="datetime-local" id="pwBfEnd" name="bf_end" value="{{ old('bf_end', $product?->bf_end ? optional($product->bf_end)->format('Y-m-d\TH:i') : '') }}">
                    </div>
                </div>

                <div class="pw-options">
                    <label class="pw-choice">
                        <input type="radio" name="is_negotiable" value="1" {{ $isNegotiable ? 'checked' : '' }}>
                        <span class="pw-choice-icon"><i data-lucide="handshake"></i></span>
                        <span>
                            <strong>Produit négociable</strong>
                            <small>Le client verra plusieurs offres automatiques.</small>
                        </span>
                    </label>

                    <label class="pw-choice">
                        <input type="radio" name="is_negotiable" value="0" {{ ! $isNegotiable ? 'checked' : '' }}>
                        <span class="pw-choice-icon"><i data-lucide="tag"></i></span>
                        <span>
                            <strong>Prix fixe</strong>
                            <small>Pas de proposition de prix.</small>
                        </span>
                    </label>
                </div>

                <input type="hidden" name="price_p1" id="pwPriceP1" value="{{ old('price_p1', $product?->price_p1) }}">
                <input type="hidden" name="price_p2" id="pwPriceP2" value="{{ old('price_p2', $product?->price_p2) }}">
                <input type="hidden" name="price_p3" id="pwPriceP3" value="{{ old('price_p3', $product?->price_p3) }}">

                <div id="pwNegotiationOffers" style="display:none">
                    <h3 class="pw-calc-title">Offres automatiques proposées au client</h3>
                    <p class="pw-help" style="margin:-6px 0 10px;">Le client ne voit jamais ces montants directement : il propose un prix et OVANIE accepte automatiquement s’il atteint l’une de ces offres.</p>
                    <div class="pw-calc-grid">
                        <div class="pw-calc-card">
                            <span class="pw-calc-icon"><i data-lucide="handshake"></i></span>
                            <div>
                                <span>1ère offre (-5%)</span>
                                <strong id="pwOfferP1">0 FCFA</strong>
                            </div>
                        </div>
                        <div class="pw-calc-card">
                            <span class="pw-calc-icon"><i data-lucide="handshake"></i></span>
                            <div>
                                <span>2ème offre (-10%)</span>
                                <strong id="pwOfferP2">0 FCFA</strong>
                            </div>
                        </div>
                        <div class="pw-calc-card">
                            <span class="pw-calc-icon"><i data-lucide="handshake"></i></span>
                            <div>
                                <span>3ème offre (-15%)</span>
                                <strong id="pwOfferP3">0 FCFA</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="pw-calc-title">Récapitulatif de calcul</h3>
                <div class="pw-calc-grid">
                    <div class="pw-calc-card">
                        <span class="pw-calc-icon"><i data-lucide="receipt-text"></i></span>
                        <div>
                            <span>Commission estimée</span>
                            <strong id="pwCommission">0 FCFA</strong>
                            <small>({{ $commissionPercentLabel }}% du prix)</small>
                        </div>
                    </div>

                    <div class="pw-calc-card">
                        <span class="pw-calc-icon"><i data-lucide="circle-user-round"></i></span>
                        <div>
                            <span>Prix client estimé</span>
                            <strong id="pwClientPrice">0 FCFA</strong>
                            <small>(TTC)</small>
                        </div>
                    </div>

                    <div class="pw-calc-card green">
                        <span class="pw-calc-icon"><i data-lucide="wallet-cards"></i></span>
                        <div>
                            <span>Net vendeur</span>
                            <strong id="pwNetSeller">0 FCFA</strong>
                            <small>(Après commission)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pw-actions">
                <div></div>
                <div class="pw-actions-right">
                    <button type="button" class="pw-btn" data-prev>Retour</button>
                    <button type="button" class="pw-btn primary" data-next>Continuer</button>
                </div>
            </div>
        </article>

        {{-- ÉTAPE 3 --}}
        <article class="pw-panel" data-panel="3">
            <div class="pw-panel-head">
                <div class="pw-panel-heading">
                    <span class="pw-number">3</span>
                    <div>
                        <h2>Étape 3 — Détail technique</h2>
                        <p>Informations techniques et garanties.</p>
                    </div>
                </div>
                <i class="pw-info-icon" data-lucide="info"></i>
            </div>

            <div class="pw-body">
                <div class="pw-grid-2">
                    <div class="pw-field">
                        <label for="pwUsageArea">Usage recommandé</label>
                        <input class="pw-input" id="pwUsageArea" name="usage_area" value="{{ old('usage_area', $product?->usage_area) }}" placeholder="Ex. Fondation, mur, toiture">
                        <p class="pw-help">À quoi sert ce produit ? Laissez vide si vous ne savez pas quoi écrire.</p>
                    </div>

                    <div class="pw-field pw-full">
                        <label for="pwTechnicalDetails">Détails technique</label>
                        <textarea class="pw-textarea tech" id="pwTechnicalDetails" name="technical_details" placeholder="Ex. Résiste à l'humidité, couleur grise, sac de 50 kg...">{{ old('technical_details', $product?->technical_details) }}</textarea>
                        <p class="pw-help">Facultatif : toute information utile en plus de la description. Laissez vide si vous ne savez pas quoi écrire.</p>
                    </div>

                    <div class="pw-field">
                        <label for="pwWarranty">Garantie</label>
                        <input class="pw-input" id="pwWarranty" name="warranty" value="{{ old('warranty', $product?->warranty) }}" placeholder="Ex. 12 mois">
                    </div>

                    <div class="pw-field">
                        <label for="pwReturnPolicy">Politique de retour</label>
                        <input class="pw-input" id="pwReturnPolicy" name="return_policy" value="{{ old('return_policy', $product?->return_policy) }}" placeholder="Ex. Retour sous 7 jours si emballage intact">
                    </div>

                    <div class="pw-field pw-full">
                        <label>Fiche technique PDF</label>
                        <label class="pw-file-drop" for="pwTechnicalSheet">
                            <span class="pw-file-drop-inner">
                                <i data-lucide="file-up"></i>
                                <strong>Glissez-déposez votre fichier ici</strong>
                                <span>ou cliquez pour parcourir</span>
                            </span>
                            <input type="file" id="pwTechnicalSheet" name="technical_sheet" accept="application/pdf" hidden>
                        </label>
                        <p class="pw-help">PDF uniquement · 10 MB maximum. {{ $product?->technical_sheet_path ? 'Une fiche existe déjà.' : '' }}</p>
                        <p class="pw-help" id="pwTechnicalFileName"></p>
                    </div>
                </div>
            </div>

            <div class="pw-actions">
                <div></div>
                <div class="pw-actions-right">
                    <button type="button" class="pw-btn" data-prev>Retour</button>
                    <button type="button" class="pw-btn primary" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                </div>
            </div>
        </article>

        {{-- ÉTAPE 4 --}}
        <article class="pw-panel" data-panel="4">
            <div class="pw-panel-head">
                <div class="pw-panel-heading">
                    <span class="pw-number">4</span>
                    <div>
                        <h2>Étape 4 — Logistique</h2>
                        <p>Données physiques indispensables pour organiser la livraison.</p>
                    </div>
                </div>
                <i class="pw-info-icon" data-lucide="info"></i>
            </div>

            <div class="pw-body">
                @if($shopLogisticsType === 'seller')
                    <div class="pw-tip">
                        <i data-lucide="truck"></i>
                        <span><strong>Logistique vendeur : vous gérez vous-même la livraison.</strong><br>Renseignez quand même les données physiques pour le calcul des frais.</span>
                    </div>
                @else
                    <div class="pw-tip">
                        <i data-lucide="truck"></i>
                        <span><strong>OVANIE Logistics organise la livraison.</strong><br>Renseignez les données physiques pour le calcul automatique et le choix du véhicule adapté.</span>
                    </div>
                @endif

                <div class="pw-logistics-grid">
                    <h3 class="pw-section-title">Dimensions et poids</h3>
                    <div class="pw-grid-5">
                        <div class="pw-field">
                            <label for="pwWeight">Poids <span class="pw-required">*</span></label>
                            <div class="pw-addon">
                                <input class="pw-input" type="number" id="pwWeight" name="weight_kg" value="{{ old('weight_kg', $product?->weight_kg) }}" min="0.01" step="0.01" placeholder="0" data-required-step="4">
                                <span>kg</span>
                            </div>
                        </div>

                        <div class="pw-field">
                            <label for="pwLength">Longueur <span class="pw-required">*</span></label>
                            <div class="pw-addon">
                                <input class="pw-input" type="number" id="pwLength" name="length_cm" value="{{ old('length_cm', $product?->length_cm) }}" min="0.1" step="0.1" placeholder="0" data-required-step="4">
                                <span>cm</span>
                            </div>
                        </div>

                        <div class="pw-field">
                            <label for="pwWidth">Largeur <span class="pw-required">*</span></label>
                            <div class="pw-addon">
                                <input class="pw-input" type="number" id="pwWidth" name="width_cm" value="{{ old('width_cm', $product?->width_cm) }}" min="0.1" step="0.1" placeholder="0" data-required-step="4">
                                <span>cm</span>
                            </div>
                        </div>

                        <div class="pw-field">
                            <label for="pwHeight">Hauteur <span class="pw-required">*</span></label>
                            <div class="pw-addon">
                                <input class="pw-input" type="number" id="pwHeight" name="height_cm" value="{{ old('height_cm', $product?->height_cm) }}" min="0.1" step="0.1" placeholder="0" data-required-step="4">
                                <span>cm</span>
                            </div>
                        </div>

                        <div class="pw-field">
                            <label for="pwVolume">Volume (calculé auto)</label>
                            <div class="pw-addon">
                                <input class="pw-input" type="number" id="pwVolume" name="volume_m3" value="{{ old('volume_m3', $product?->volume_m3) }}" step="0.0001" readonly placeholder="0,000">
                                <span>m³</span>
                            </div>
                            <p class="pw-help">Calculé depuis L×l×H.</p>
                        </div>
                    </div>
                </div>

                <div class="pw-flag-grid">
                    <div>
                        <div class="pw-field-label">Produit fragile ? <span class="pw-required">*</span></div>
                        <div class="pw-flag-options">
                            <label class="pw-flag-card">
                                <input type="radio" name="fragile" value="1" data-required-step="4" {{ $fragileValue === '1' ? 'checked' : '' }}>
                                <span class="pw-flag-radio"></span>
                                <span class="pw-flag-icon"><i data-lucide="wine"></i></span>
                                <strong>Oui</strong>
                                <small>Manipulation délicate</small>
                            </label>
                            <label class="pw-flag-card">
                                <input type="radio" name="fragile" value="0" data-required-step="4" {{ $fragileValue !== '1' ? 'checked' : '' }}>
                                <span class="pw-flag-radio"></span>
                                <span class="pw-flag-icon"><i data-lucide="shield-check"></i></span>
                                <strong>Non</strong>
                                <small>Produit robuste</small>
                            </label>
                        </div>
                    </div>

                    <div>
                        <div class="pw-field-label">Déchargement requis ? <span class="pw-required">*</span></div>
                        <div class="pw-flag-options">
                            <label class="pw-flag-card">
                                <input type="radio" name="requires_unloading" value="1" data-required-step="4" {{ $unloadingValue === '1' ? 'checked' : '' }}>
                                <span class="pw-flag-radio"></span>
                                <span class="pw-flag-icon"><i data-lucide="users"></i></span>
                                <strong>Oui</strong>
                                <small>Aide sur chantier</small>
                            </label>
                            <label class="pw-flag-card">
                                <input type="radio" name="requires_unloading" value="0" data-required-step="4" {{ $unloadingValue !== '1' ? 'checked' : '' }}>
                                <span class="pw-flag-radio"></span>
                                <span class="pw-flag-icon"><i data-lucide="truck"></i></span>
                                <strong>Non</strong>
                                <small>Déchargement inclus</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pw-field pw-unloading-wrap" id="pwUnloadingWrap" style="display:none">
                    <label for="pwUnloadingInstructions">Détails de déchargement <span class="pw-required">*</span></label>
                    <textarea class="pw-textarea" id="pwUnloadingInstructions" name="unloading_instructions" maxlength="255" placeholder="Ex. prévoir 2 manutentionnaires, chariot élévateur...">{{ old('unloading_instructions', $product?->unloading_instructions) }}</textarea>
                </div>
            </div>

            <div class="pw-actions">
                <div></div>
                <div class="pw-actions-right">
                    <button type="button" class="pw-btn" data-prev>Retour</button>
                    <button type="button" class="pw-btn primary" data-next>Continuer <i data-lucide="arrow-right"></i></button>
                </div>
            </div>
        </article>

        {{-- ÉTAPE 5 --}}
        <article class="pw-panel" data-panel="5">
            <div class="pw-panel-head">
                <div class="pw-panel-heading">
                    <span class="pw-number">5</span>
                    <div>
                        <h2>Étape 5 — Média</h2>
                        <p>Images obligatoires et vidéo optionnelle.</p>
                    </div>
                </div>
                <i class="pw-info-icon" data-lucide="info"></i>
            </div>

            <div class="pw-body">
                <div class="pw-upload-grid">
                    <div class="pw-upload-card">
                        <h3>Images produit <span class="pw-required">*</span></h3>
                        <p>1 à 5 images · JPG, PNG, WEBP</p>

                        <label class="pw-drop" id="pwImagesDrop" for="pwImages">
                            <span class="pw-drop-inner">
                                <i data-lucide="cloud-upload"></i>
                                <strong>Glissez-déposez vos images</strong>
                                <span>ou cliquez pour parcourir</span>
                            </span>
                            <input type="file" name="images[]" id="pwImages" accept="image/jpeg,image/png,image/webp" multiple hidden {{ (!$isEdit || $existingImagesCount === 0) ? 'data-required-step=5' : '' }}>
                        </label>

                        <div class="pw-thumbs" id="pwThumbs">
                            @for($i = 0; $i < 5; $i++)
                                @php $existingImage = $existingImages->get($i); @endphp
                                <span class="pw-thumb">
                                    @if($existingImage)
                                        <img src="{{ $existingImage->thumb_url ?? $existingImage->public_url }}" alt="Image produit existante">
                                    @else
                                        <i data-lucide="image-plus"></i>
                                    @endif
                                </span>
                            @endfor
                        </div>

                        <div class="pw-file-count"><span id="pwImagesCount">{{ $existingImagesCount }}</span> / 5 images</div>
                    </div>

                    <div class="pw-upload-card">
                        <h3>Vidéo produit optionnelle</h3>
                        <p>MP4, MOV, WEBM ou M4V · 50 MB max</p>

                        <label class="pw-drop" for="pwVideo">
                            <span class="pw-drop-inner">
                                <i data-lucide="cloud-upload"></i>
                                <strong>Glissez-déposez votre vidéo</strong>
                                <span>ou cliquez pour parcourir</span>
                            </span>
                            <input type="file" name="product_video" id="pwVideo" accept="video/mp4,video/quicktime,video/webm,video/x-m4v" hidden>
                        </label>

                        <div class="pw-video-preview" id="pwVideoPreview"></div>
                        <div class="pw-thumbs" style="grid-template-columns:80px">
                            <span class="pw-thumb"><i data-lucide="video"></i></span>
                        </div>
                        <div class="pw-file-count" id="pwVideoFileName">{{ $product?->product_video_path ? '1 / 1 vidéo' : '0 / 1 vidéo' }}</div>
                    </div>
                </div>
            </div>

            <div class="pw-actions">
                <div></div>
                <div class="pw-actions-right pw-final-actions">
                    <button type="button" class="pw-btn secondary" data-prev>Retour</button>
                    <button type="submit" name="save_as_draft" value="1" class="pw-btn secondary"><i data-lucide="save"></i> Enregistrer brouillon</button>
                    <button type="submit" class="pw-btn orange">{{ $isEdit ? 'Mettre à jour le produit' : 'Publier le produit' }} <i data-lucide="rocket"></i></button>
                </div>
            </div>
        </article>
    </form>
</section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-pw-wizard]');
    if (!root) return;

    const form = root.querySelector('#pwProductForm');
    const steps = [...root.querySelectorAll('[data-step]')];
    const panels = [...root.querySelectorAll('[data-panel]')];
    const errorBox = root.querySelector('#pwStepError');
    const commissionRate = Number(@json($commissionRate));
    let current = 1;

    const qs = (selector) => root.querySelector(selector);
    const qsa = (selector) => [...root.querySelectorAll(selector)];
    const formatFcfa = (value) => new Intl.NumberFormat('fr-FR').format(Math.max(0, Math.round(Number(value) || 0))) + ' FCFA';

    function refreshIcons() {
        if (window.lucide) window.lucide.createIcons();
    }

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message || 'Veuillez compléter les champs obligatoires avant de continuer.';
        errorBox.classList.add('is-visible');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideError() {
        errorBox?.classList.remove('is-visible');
    }

    function isEmptyField(field) {
        if (!field) return true;
        if (field.type === 'file') return !field.files || field.files.length < 1;
        if (field.type === 'radio') return !root.querySelector(`input[name="${field.name}"]:checked`);
        return String(field.value || '').trim() === '';
    }

    function validateStep(stepNumber) {
        hideError();
        let valid = true;

        qsa('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));

        qsa(`[data-required-step="${stepNumber}"]`).forEach((field) => {
            if (isEmptyField(field)) {
                valid = false;
                field.classList.add('is-invalid');
                if (field.type === 'file') field.closest('.pw-drop')?.classList.add('is-invalid');
            }
        });

        if (stepNumber === 1) {
            const mainCategory = qs('#pwMainCategory');
            if (isEmptyField(mainCategory)) {
                valid = false;
                mainCategory?.classList.add('is-invalid');
            }
        }

        if (stepNumber === 4) {
            const unloadingRequired = root.querySelector('input[name="requires_unloading"]:checked')?.value === '1';
            const unloadingDetails = qs('#pwUnloadingInstructions');
            if (unloadingRequired && isEmptyField(unloadingDetails)) {
                valid = false;
                unloadingDetails?.classList.add('is-invalid');
            }
        }

        if (!valid) {
            showError();
            return false;
        }

        return true;
    }

    function goToStep(stepNumber, skipValidation = false) {
        const next = Math.max(1, Math.min(5, Number(stepNumber) || 1));
        if (next > current && !skipValidation && !validateStep(current)) return;

        current = next;

        steps.forEach((button, index) => {
            const number = index + 1;
            button.classList.toggle('is-active', number === current);
            button.classList.toggle('is-done', number < current);

            const dot = button.querySelector('[data-dot]');
            if (dot) dot.innerHTML = number < current ? '<i data-lucide="check"></i>' : String(number);
        });

        panels.forEach((panel) => panel.classList.toggle('is-active', Number(panel.dataset.panel) === current));
        refreshIcons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    steps.forEach((button) => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.step);
            goToStep(target, target < current);
        });
    });

    qsa('[data-next]').forEach((button) => button.addEventListener('click', () => goToStep(current + 1)));
    qsa('[data-prev]').forEach((button) => button.addEventListener('click', () => goToStep(current - 1, true)));

    let submitting = false;
    form?.addEventListener('submit', async (event) => {
        const isDraft = event.submitter?.name === 'save_as_draft' || !qs('#pwVisibilityDraft').disabled;
        for (let step = 1; !isDraft && step <= 5; step += 1) {
            if (!validateStep(step)) {
                event.preventDefault();
                goToStep(step, true);
                return;
            }
        }
        if (@json($isEdit)) return;

        event.preventDefault();
        if (submitting) return;
        const data = new FormData(form);
        if (isDraft) data.set('save_as_draft', '1');
        const buttons = qsa('button[type="submit"]');
        submitting = true;
        buttons.forEach(button => button.disabled = true);
        hideError();
        let response;
        try {
            response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                body: data,
            });
            if (response.status === 413) {
                showError('Les fichiers sont trop volumineux. Réduisez leur taille puis réessayez.');
                return;
            }
            if (response.status === 419 || response.status === 401) {
                showError('Votre session a expiré. Connectez-vous dans un autre onglet puis réessayez.');
                return;
            }
            let payload;
            try {
                payload = await response.json();
            } catch (error) {
                showError(`Le serveur a renvoyé une réponse illisible (HTTP ${response.status}). Vérifiez votre liste de produits avant de réessayer. Votre saisie est conservée ici.`);
                return;
            }
            if (response.status === 422) {
                const errors = payload.errors || {};
                let firstStep = null;
                Object.keys(errors).forEach(key => {
                    const parts = key.split('.');
                    const name = parts.shift() + parts.map(part => `[${part}]`).join('');
                    const field = [...form.elements].find(field => field.name === name
                        || field.name === `${key.split('.')[0]}[]`);
                    if (!field) return;
                    field.classList.add('is-invalid');
                    if (field.type === 'file') field.closest('.pw-drop')?.classList.add('is-invalid');
                    const panel = field.closest('[data-panel]');
                    if (firstStep === null && panel) firstStep = Number(panel.dataset.panel);
                });
                if (firstStep !== null) goToStep(firstStep, true);
                showError(Object.values(errors).flat().join(' ') || payload.message);
                return;
            }
            if (response.ok && payload.redirect) {
                window.location.assign(payload.redirect);
                return;
            }
            showError(response.status === 413
                ? 'Les fichiers sont trop volumineux. Réduisez leur taille puis réessayez.'
                : response.status === 419 || response.status === 401
                    ? 'Votre session a expiré. Connectez-vous dans un autre onglet puis réessayez.'
                    : 'La publication n’a pas pu être confirmée. Vérifiez votre liste de produits avant de réessayer.');
        } catch (error) {
            console.error('Échec de la publication du produit', { status: response?.status, error });
            showError('Connexion interrompue. Vérifiez votre liste de produits avant de réessayer. Votre saisie est conservée ici.');
        } finally {
            submitting = false;
            buttons.forEach(button => button.disabled = false);
        }
    });

    function syncCategoryFilters() {
        const mainSelect = qs('#pwMainCategory');
        const subSelect = qs('#pwSubCategory');
        if (!mainSelect || !subSelect) return;

        const mainId = String(mainSelect.value || '');
        let visibleCount = 0;

        [...subSelect.options].forEach((option, index) => {
            if (index === 0) return;
            const visible = !mainId || String(option.dataset.parent || '') === mainId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible) visibleCount += 1;
        });

        const selectedOption = subSelect.options[subSelect.selectedIndex];
        if (selectedOption && selectedOption.disabled) subSelect.value = '';

        if (mainId && visibleCount === 0) {
            const existingRootOption = [...subSelect.options].find((option) => option.value === mainId);
            if (!existingRootOption) {
                const label = mainSelect.options[mainSelect.selectedIndex]?.text || 'Catégorie';
                const option = new Option(label, mainId, false, false);
                option.dataset.parent = mainId;
                subSelect.add(option);
            }
        }
    }

    qs('#pwMainCategory')?.addEventListener('change', () => {
        syncCategoryFilters();
        qs('#pwSubCategory')?.classList.remove('is-invalid');
        markCategoryChosenManually();
    });
    syncCategoryFilters();

    // Suggestion IA de catégorie/sous-catégorie à partir du nom du produit :
    // le vendeur n'a plus besoin de la choisir lui-même, mais reste toujours
    // libre de la corriger (voir AiProductCategorySuggester côté serveur, qui
    // ne choisit jamais une catégorie hors de celles qui existent réellement).
    let categoryChosenManually = @json($isEdit && $selectedCategoryId !== '');
    let categorySuggestTimer = null;
    const suggestCategoryUrl = @json(Route::has('vendor.products.suggestCategory') ? route('vendor.products.suggestCategory') : null);

    function markCategoryChosenManually() {
        categoryChosenManually = true;
        const hint = qs('#pwCategorySuggestedHint');
        if (hint) hint.style.display = 'none';
    }

    qs('#pwSubCategory')?.addEventListener('change', markCategoryChosenManually);

    async function suggestCategoryFromName() {
        if (categoryChosenManually || !suggestCategoryUrl) return;
        const name = (qs('#pwName')?.value || '').trim();
        if (name.length < 3) return;

        try {
            const response = await fetch(suggestCategoryUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': qs('input[name="_token"]')?.value || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name }),
            });
            if (!response.ok || categoryChosenManually) return;

            const payload = await response.json();
            const suggestion = payload?.suggestion;
            if (!suggestion || !suggestion.category_id) return;

            const mainSelect = qs('#pwMainCategory');
            const subSelect = qs('#pwSubCategory');
            if (mainSelect) mainSelect.value = String(suggestion.category_id);
            syncCategoryFilters();
            if (subSelect && suggestion.subcategory_id) {
                subSelect.value = String(suggestion.subcategory_id);
            }
            qs('#pwSubCategory')?.classList.remove('is-invalid');

            const hint = qs('#pwCategorySuggestedHint');
            if (hint) hint.style.display = '';
        } catch (_) {
            // Échec silencieux : le vendeur garde la sélection manuelle du formulaire.
        }
    }

    qs('#pwName')?.addEventListener('input', () => {
        if (categoryChosenManually) return;
        window.clearTimeout(categorySuggestTimer);
        categorySuggestTimer = window.setTimeout(suggestCategoryFromName, 700);
    });

    function syncVisibility() {
        const visibility = qs('#pwVisibility');
        const draftField = qs('#pwVisibilityDraft');
        if (!visibility || !draftField) return;
        draftField.disabled = visibility.value !== 'draft';
    }

    qs('#pwVisibility')?.addEventListener('change', syncVisibility);
    syncVisibility();

    function updatePriceSummary() {
        const normalPrice = Number(qs('#pwPrice')?.value || 0);
        const promoPrice = Number(qs('#pwPromo')?.value || 0);
        const salePrice = promoPrice > 0 && promoPrice < normalPrice ? promoPrice : normalPrice;
        const commission = Math.round(salePrice * commissionRate);
        const clientPrice = salePrice + commission;
        const netSeller = salePrice;

        if (qs('#pwCommission')) qs('#pwCommission').textContent = formatFcfa(commission);
        if (qs('#pwClientPrice')) qs('#pwClientPrice').textContent = formatFcfa(clientPrice);
        if (qs('#pwNetSeller')) qs('#pwNetSeller').textContent = formatFcfa(netSeller);

        const negotiable = root.querySelector('input[name="is_negotiable"]:checked')?.value === '1';
        const offersBox = qs('#pwNegotiationOffers');
        if (offersBox) offersBox.style.display = negotiable ? '' : 'none';

        if (negotiable && normalPrice > 0) {
            // Toujours recalculés à partir du prix courant : sinon, modifier le prix
            // après avoir activé la négociation (ou lors d'une édition) laisse des
            // seuils obsolètes qui peuvent devenir >= au nouveau prix et faire
            // échouer la validation (price_p1 doit rester strictement < price).
            const p1 = qs('#pwPriceP1');
            const p2 = qs('#pwPriceP2');
            const p3 = qs('#pwPriceP3');
            const offer1 = Math.max(1, Math.round(normalPrice * .95));
            const offer2 = Math.max(1, Math.round(normalPrice * .90));
            const offer3 = Math.max(1, Math.round(normalPrice * .85));
            if (p1) p1.value = offer1;
            if (p2) p2.value = offer2;
            if (p3) p3.value = offer3;
            if (qs('#pwOfferP1')) qs('#pwOfferP1').textContent = formatFcfa(offer1);
            if (qs('#pwOfferP2')) qs('#pwOfferP2').textContent = formatFcfa(offer2);
            if (qs('#pwOfferP3')) qs('#pwOfferP3').textContent = formatFcfa(offer3);
        }
    }

    qs('#pwPrice')?.addEventListener('input', updatePriceSummary);
    qs('#pwPromo')?.addEventListener('input', updatePriceSummary);
    qsa('input[name="is_negotiable"]').forEach((radio) => radio.addEventListener('change', updatePriceSummary));
    updatePriceSummary();

    function updateVolume() {
        const length = Number(qs('#pwLength')?.value || 0);
        const width = Number(qs('#pwWidth')?.value || 0);
        const height = Number(qs('#pwHeight')?.value || 0);
        const volume = length > 0 && width > 0 && height > 0 ? (length * width * height) / 1000000 : 0;
        if (qs('#pwVolume')) qs('#pwVolume').value = volume ? volume.toFixed(4) : '';
    }

    ['#pwLength', '#pwWidth', '#pwHeight'].forEach((selector) => qs(selector)?.addEventListener('input', updateVolume));
    updateVolume();

    function toggleUnloadingDetails() {
        const required = root.querySelector('input[name="requires_unloading"]:checked')?.value === '1';
        const wrap = qs('#pwUnloadingWrap');
        if (wrap) wrap.style.display = required ? '' : 'none';
    }

    qsa('input[name="requires_unloading"]').forEach((radio) => radio.addEventListener('change', toggleUnloadingDetails));
    toggleUnloadingDetails();

    function togglePromoDates() {
        const saleTypeSelect = qs('#pwSaleType');
        if (!saleTypeSelect) return;
        
        const value = saleTypeSelect.value.toLowerCase();
        const flashDates = qs('#pwFlashDates');
        const bfDates = qs('#pwBfDates');
        
        const isFlash = value.includes('flash');
        const isBf = value.includes('black');
        
        if (flashDates) {
            flashDates.style.display = isFlash ? '' : 'none';
            const inputs = flashDates.querySelectorAll('input');
            inputs.forEach(input => {
                if (isFlash) {
                    input.setAttribute('required', 'required');
                    input.setAttribute('data-required-step', '2');
                } else {
                    input.removeAttribute('required');
                    input.removeAttribute('data-required-step');
                    input.classList.remove('is-invalid');
                }
            });
        }
        
        if (bfDates) {
            bfDates.style.display = isBf ? '' : 'none';
            const inputs = bfDates.querySelectorAll('input');
            inputs.forEach(input => {
                if (isBf) {
                    input.setAttribute('required', 'required');
                    input.setAttribute('data-required-step', '2');
                } else {
                    input.removeAttribute('required');
                    input.removeAttribute('data-required-step');
                    input.classList.remove('is-invalid');
                }
            });
        }
    }
    
    qs('#pwSaleType')?.addEventListener('change', togglePromoDates);
    togglePromoDates();

    qs('#pwImages')?.addEventListener('change', function () {
        const files = [...(this.files || [])].slice(0, 5);
        const thumbs = qsa('#pwThumbs .pw-thumb');

        if (qs('#pwImagesCount')) qs('#pwImagesCount').textContent = String(files.length);
        qs('#pwImagesDrop')?.classList.remove('is-invalid');

        thumbs.forEach((thumb, index) => {
            thumb.innerHTML = '<i data-lucide="image-plus"></i>';
            if (!files[index]) return;

            const image = document.createElement('img');
            const objectUrl = URL.createObjectURL(files[index]);
            image.src = objectUrl;
            image.alt = 'Aperçu image produit';
            image.onload = () => URL.revokeObjectURL(objectUrl);
            thumb.innerHTML = '';
            thumb.appendChild(image);
        });

        refreshIcons();
    });

    qs('#pwVideo')?.addEventListener('change', function () {
        const file = this.files?.[0];
        const preview = qs('#pwVideoPreview');
        const fileName = qs('#pwVideoFileName');

        if (fileName) fileName.textContent = file ? '1 / 1 vidéo' : '0 / 1 vidéo';
        if (!preview) return;

        preview.innerHTML = '';
        preview.classList.remove('is-visible');

        if (file) {
            const video = document.createElement('video');
            video.controls = true;
            video.muted = true;
            video.src = URL.createObjectURL(file);
            preview.appendChild(video);
            preview.classList.add('is-visible');
        }
    });

    qs('#pwTechnicalSheet')?.addEventListener('change', function () {
        const output = qs('#pwTechnicalFileName');
        if (output) output.textContent = this.files?.[0]?.name || '';
    });

    qsa('input, select, textarea').forEach((field) => {
        const clear = () => field.classList.remove('is-invalid');
        field.addEventListener('input', clear);
        field.addEventListener('change', clear);
    });

    refreshIcons();
});
</script>
@endsection
