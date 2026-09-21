@extends('layouts.guest')

@section('title', 'Calculateur Devis IA OVANIE')

@section('styles')
<style>
    :root {
        --dq-navy: #071d55;
        --dq-navy-deep: #031b4d;
        --dq-blue: #0f5dff;
        --dq-blue-2: #2f7bff;
        --dq-orange: #ff6500;
        --dq-orange-2: #ff7a1c;
        --dq-text: #09205e;
        --dq-muted: #60718f;
        --dq-border: #d8e4f2;
        --dq-bg: #f4f8ff;
        --dq-card: #ffffff;
        --dq-shadow: 0 12px 30px rgba(7, 29, 85, .08);
    }

    .dq-page {
        background:
            radial-gradient(circle at 92% 8%, rgba(27, 101, 255, .07), transparent 24%),
            linear-gradient(180deg, #f6f9ff 0%, #eef5ff 100%);
        padding: 14px 0 42px;
    }

    .dq-wrap {
        width: min(calc(100% - 48px), 1180px);
        margin: 0 auto;
    }

    .dq-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 9px;
        margin: 0 0 24px;
        color: #35507d;
        font-size: 14px;
        font-weight: 700;
    }

    .dq-breadcrumb a {
        color: var(--dq-blue);
        text-decoration: none;
    }

    .dq-breadcrumb svg {
        width: 14px;
        height: 14px;
        color: #8aa1c4;
    }

    .dq-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(330px, .9fr);
        gap: 34px;
        align-items: center;
        margin-bottom: 24px;
    }

    .dq-hero-copy {
        padding: 8px 4px;
    }

    .dq-chip {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        background: linear-gradient(180deg, #eef5ff, #e6f0ff);
        color: #0a57e8;
        font-size: 15px;
        font-weight: 900;
        box-shadow: inset 0 0 0 1px rgba(34, 105, 255, .08);
    }

    .dq-chip svg {
        width: 22px;
        height: 22px;
    }

    .dq-hero h1 {
        margin: 26px 0 14px;
        max-width: 720px;
        color: var(--dq-text);
        font-size: clamp(36px, 4.2vw, 58px);
        line-height: 1.04;
        letter-spacing: -.035em;
        font-weight: 950;
    }

    .dq-hero-copy > p {
        margin: 0;
        max-width: 700px;
        color: #233d70;
        font-size: 18px;
        line-height: 1.55;
        font-weight: 650;
    }

    .dq-promo {
        min-height: 248px;
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 28px 30px 24px;
        background:
            radial-gradient(circle at 15% 65%, rgba(45, 121, 255, .18), transparent 32%),
            linear-gradient(135deg, #08235e 0%, #031844 100%);
        box-shadow: var(--dq-shadow);
    }

    .dq-promo::after {
        content: "";
        position: absolute;
        inset: 0;
        opacity: .08;
        background-image:
            linear-gradient(135deg, transparent 48%, #fff 49%, #fff 51%, transparent 52%),
            linear-gradient(45deg, transparent 48%, #fff 49%, #fff 51%, transparent 52%);
        background-size: 72px 72px;
        pointer-events: none;
    }

    .dq-promo-copy {
        position: relative;
        z-index: 4;
        width: 46%;
        margin-left: auto;
        color: #fff;
    }

    .dq-promo-copy strong {
        display: block;
        font-size: 34px;
        line-height: 1;
        margin-bottom: 10px;
        letter-spacing: -.03em;
    }

    .dq-promo-copy p {
        margin: 0 0 16px;
        color: #f4f7ff;
        font-size: 15px;
        line-height: 1.45;
        font-weight: 700;
    }

    .dq-promo-list {
        display: grid;
        gap: 8px;
        font-size: 14px;
        font-weight: 750;
    }

    .dq-promo-list span {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dq-promo-list svg {
        width: 16px;
        height: 16px;
    }

    .dq-promo-product {
        position: absolute;
        z-index: 2;
        object-fit: cover;
        background: #fff;
        box-shadow: 0 16px 34px rgba(0, 0, 0, .28);
    }

    .dq-promo-product--cement {
        width: 105px;
        height: 150px;
        left: 22px;
        bottom: 26px;
        border-radius: 12px;
        object-fit: contain;
        padding: 6px;
    }

    .dq-promo-product--block {
        width: 154px;
        height: 95px;
        left: 88px;
        bottom: 24px;
        border-radius: 14px;
        object-fit: contain;
        padding: 4px;
        transform: rotate(-2deg);
    }

    .dq-hardhat {
        position: absolute;
        z-index: 3;
        left: 170px;
        bottom: 26px;
        width: 72px;
        height: 72px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: linear-gradient(145deg, #ffd64a, #ff9d00);
        color: #6b3d00;
        box-shadow: 0 12px 28px rgba(255, 166, 0, .35);
    }

    .dq-hardhat svg {
        width: 42px;
        height: 42px;
        stroke-width: 2.2;
    }

    .dq-benefits {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 28px;
        background: #fff;
        border: 1px solid var(--dq-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--dq-shadow);
    }

    .dq-benefit {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
        min-height: 128px;
        padding: 18px 20px;
        border-right: 1px solid #e5edf7;
    }

    .dq-benefit:last-child { border-right: 0; }

    .dq-benefit-icon {
        width: 62px;
        height: 62px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #0b55df;
        background: linear-gradient(145deg, #eef5ff, #e1edff);
    }

    .dq-benefit-icon.orange {
        color: #fff;
        background: linear-gradient(145deg, #ff8a2a, #ff5d00);
    }

    .dq-benefit-icon svg {
        width: 34px;
        height: 34px;
    }

    .dq-benefit strong {
        display: block;
        margin-bottom: 6px;
        color: var(--dq-text);
        font-size: 16px;
        font-weight: 900;
    }

    .dq-benefit span {
        color: #394f78;
        font-size: 13px;
        line-height: 1.45;
        font-weight: 650;
    }

    .dq-workspace {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
        gap: 70px;
        align-items: stretch;
        margin-bottom: 28px;
    }

    .dq-workspace::before {
        content: "";
        position: absolute;
        /* Centre réel du gap entre les colonnes 0.9fr / 1.1fr */
        left: 45.2%;
        top: 26%;
        bottom: 26%;
        width: 1px;
        border-left: 1px dashed #a8bce0;
        transform: translateX(-50%);
    }

    .dq-or {
        position: absolute;
        z-index: 5;
        /* Même axe que la ligne pointillée : le cercle reste dans le gap */
        left: 45.2%;
        top: 47%;
        transform: translate(-50%, -50%);
        width: 54px;
        height: 54px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fff;
        color: #173875;
        border: 1px solid #a8bce0;
        box-shadow: 0 10px 24px rgba(12, 46, 114, .1);
        font-size: 15px;
        font-weight: 950;
    }

    .dq-panel {
        border-radius: 18px;
        background: rgba(255,255,255,.96);
        box-shadow: var(--dq-shadow);
        padding: 22px 22px 24px;
    }

    .dq-panel--blue {
        border: 1.5px solid #1f64ff;
    }

    .dq-panel--orange {
        border: 1.5px solid #ff8f42;
    }

    .dq-panel-head {
        display: grid;
        grid-template-columns: 60px minmax(0, 1fr);
        gap: 14px;
        align-items: center;
        margin-bottom: 28px;
    }

    .dq-panel-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: #fff;
        background: linear-gradient(145deg, #1f74ff, #0054e9);
        box-shadow: 0 10px 22px rgba(15, 93, 255, .24);
    }

    .dq-panel-icon.orange {
        background: linear-gradient(145deg, #ff851f, #ff5b00);
        box-shadow: 0 10px 22px rgba(255, 101, 0, .24);
    }

    .dq-panel-icon svg {
        width: 30px;
        height: 30px;
    }

    .dq-panel h2 {
        margin: 0 0 5px;
        color: var(--dq-text);
        font-size: 22px;
        line-height: 1.2;
        font-weight: 950;
    }

    .dq-panel-head p {
        margin: 0;
        color: var(--dq-muted);
        font-size: 14px;
        line-height: 1.45;
        font-weight: 650;
    }

    .dq-field-label {
        display: block;
        margin: 0 0 10px;
        color: var(--dq-text);
        font-size: 14px;
        font-weight: 900;
    }

    .dq-input-wrap {
        position: relative;
    }

    .dq-input-wrap svg {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        width: 20px;
        height: 20px;
        color: #6880a9;
    }

    .dq-input,
    .dq-select,
    .dq-textarea {
        width: 100%;
        border: 1px solid #b9c9df;
        border-radius: 8px;
        background: #fff;
        color: #11244f;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    .dq-input {
        height: 58px;
        padding: 0 16px 0 46px;
        font-size: 15px;
    }

    .dq-select {
        height: 48px;
        padding: 0 14px;
        font-size: 14px;
        font-weight: 700;
    }

    .dq-textarea {
        min-height: 198px;
        padding: 14px 16px;
        resize: vertical;
        font-size: 15px;
        line-height: 1.55;
    }

    .dq-input:focus,
    .dq-select:focus,
    .dq-textarea:focus {
        border-color: var(--dq-blue);
        box-shadow: 0 0 0 4px rgba(15, 93, 255, .09);
    }

    .dq-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        border: 0;
        border-radius: 8px;
        min-height: 50px;
        padding: 0 18px;
        font-weight: 900;
        cursor: pointer;
        transition: transform .2s, box-shadow .2s, background .2s;
    }

    .dq-btn:hover { transform: translateY(-1px); }

    .dq-btn-blue {
        width: 100%;
        margin-top: 18px;
        color: #fff;
        background: linear-gradient(90deg, #0d69ff, #0c55e9);
        box-shadow: 0 12px 24px rgba(15, 93, 255, .2);
        font-size: 17px;
    }

    .dq-btn-orange {
        width: 100%;
        color: #fff;
        background: linear-gradient(90deg, #ff6c00, #ff5700);
        box-shadow: 0 12px 24px rgba(255, 101, 0, .18);
        font-size: 17px;
    }

    .dq-btn-outline {
        min-height: 48px;
        border: 1px solid #5573ae;
        background: #fff;
        color: #19356e;
        box-shadow: none;
    }

    .dq-btn-soft {
        min-height: 48px;
        border: 1px solid #4b75e9;
        background: #f8fbff;
        color: #0b52dc;
        box-shadow: none;
    }

    .dq-separator {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 22px 0 18px;
        color: #263f72;
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .dq-separator::before,
    .dq-separator::after {
        content: "";
        height: 1px;
        flex: 1;
        background: #d8e2f0;
    }

    .dq-mini-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .dq-tip {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 12px;
        align-items: start;
        margin-top: 28px;
        padding: 15px 16px;
        border-radius: 10px;
        background: linear-gradient(180deg, #eff6ff, #e8f2ff);
        color: #254574;
        font-size: 13px;
        line-height: 1.45;
        font-weight: 650;
    }

    .dq-tip svg {
        width: 26px;
        height: 26px;
        color: #0f62f2;
    }

    .dq-tip strong { color: #0b4fd4; }

    .dq-level-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 190px;
        gap: 16px;
        align-items: end;
        margin-bottom: 18px;
    }

    .dq-counter {
        display: flex;
        justify-content: flex-end;
        margin-top: 6px;
        color: #58709a;
        font-size: 12px;
        font-weight: 700;
    }

    .dq-quote-actions {
        display: grid;
        gap: 10px;
        margin-top: 16px;
    }

    .dq-result {
        display: none;
        margin-top: 18px;
        border-radius: 12px;
        padding: 16px;
        background: #f8fbff;
        border: 1px solid var(--dq-border);
        color: #243b67;
        font-size: 14px;
        line-height: 1.55;
    }

    .dq-result.is-visible { display: block; }

    .dq-result-title {
        margin-bottom: 12px;
        color: var(--dq-text);
        font-size: 18px;
        font-weight: 950;
    }

    .dq-product-result {
        padding: 12px 0;
        border-top: 1px solid #dfe8f4;
    }

    .dq-product-result:first-of-type { border-top: 0; }

    .dq-product-result strong {
        color: var(--dq-text);
    }

    .dq-result-meta {
        margin-top: 4px;
        color: var(--dq-muted);
        font-size: 13px;
    }

    .dq-table-wrap {
        overflow-x: auto;
        margin-top: 12px;
        background: #fff;
        border: 1px solid #e1eaf5;
        border-radius: 10px;
    }

    .dq-result-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 620px;
    }

    .dq-result-table th,
    .dq-result-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #edf2f8;
        vertical-align: top;
    }

    .dq-result-table th {
        color: #163872;
        background: #f7faff;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .dq-info-strip {
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 18px 24px;
        margin-bottom: 22px;
        border: 1px solid #a8c6ff;
        border-radius: 14px;
        background: linear-gradient(90deg, #f0f6ff, #eaf3ff);
        color: #173b7a;
    }

    .dq-info-strip-icon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #0b58e9;
        color: #fff;
    }

    .dq-info-strip-icon svg {
        width: 22px;
        height: 22px;
    }

    .dq-info-strip strong {
        display: block;
        margin-bottom: 3px;
        color: #0b4fd8;
        font-size: 16px;
        font-weight: 950;
    }

    .dq-info-strip span {
        font-size: 14px;
        font-weight: 650;
    }

    .dq-trust {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        background: #fff;
        border: 1px solid var(--dq-border);
        border-radius: 14px;
        box-shadow: var(--dq-shadow);
        overflow: hidden;
    }

    .dq-trust-item {
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        min-height: 100px;
        padding: 16px;
        border-right: 1px solid #e5edf7;
    }

    .dq-trust-item:last-child { border-right: 0; }

    .dq-trust-icon {
        width: 46px;
        height: 46px;
        display: grid;
        place-items: center;
        color: #0a56e5;
    }

    .dq-trust-icon svg {
        width: 38px;
        height: 38px;
        stroke-width: 1.7;
    }

    .dq-trust-item strong {
        display: block;
        margin-bottom: 4px;
        color: var(--dq-text);
        font-size: 13px;
        font-weight: 950;
    }

    .dq-trust-item span {
        display: block;
        color: #324a75;
        font-size: 12px;
        line-height: 1.35;
        font-weight: 650;
    }

    @media (max-width: 1080px) {
        .dq-hero {
            grid-template-columns: 1fr;
        }

        .dq-promo {
            min-height: 220px;
        }

        .dq-benefits {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dq-benefit:nth-child(2) { border-right: 0; }
        .dq-benefit:nth-child(-n+2) { border-bottom: 1px solid #e5edf7; }

        .dq-workspace {
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .dq-workspace::before,
        .dq-or { display: none; }

        .dq-trust {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dq-trust-item {
            border-bottom: 1px solid #e5edf7;
        }
    }

    @media (max-width: 720px) {
        .dq-page { padding-top: 8px; }
        .dq-wrap { width: min(calc(100% - 22px), 1180px); }
        .dq-breadcrumb { margin-bottom: 16px; }
        .dq-hero h1 { font-size: 38px; }
        .dq-hero-copy > p { font-size: 16px; }
        .dq-promo { min-height: 270px; padding: 24px 20px; }
        .dq-promo-copy { width: 58%; }
        .dq-promo-copy strong { font-size: 28px; }
        .dq-promo-product--block { width: 125px; left: 54px; }
        .dq-promo-product--cement { width: 86px; height: 128px; left: 16px; }
        .dq-hardhat { left: 124px; width: 58px; height: 58px; }
        .dq-benefits { grid-template-columns: 1fr; }
        .dq-benefit { border-right: 0; border-bottom: 1px solid #e5edf7; }
        .dq-panel { padding: 18px; }
        .dq-level-row { grid-template-columns: 1fr; }
        .dq-mini-actions { grid-template-columns: 1fr; }
        .dq-trust { grid-template-columns: 1fr; }
        .dq-trust-item { border-right: 0; }
    }
</style>
@endsection

@section('content')
<main class="dq-page">
    <div class="dq-wrap">
        <nav class="dq-breadcrumb" aria-label="Fil d'Ariane">
            <a href="{{ route('home') }}">Accueil</a>
            <i data-lucide="chevron-right"></i>
            <span>Outils d’achat</span>
            <i data-lucide="chevron-right"></i>
            <span>Calculateur devis IA</span>
        </nav>

        <section class="dq-hero" aria-labelledby="dqTitle">
            <div class="dq-hero-copy">
                <span class="dq-chip"><i data-lucide="calculator"></i> CALCULATEUR DEVIS IA</span>
                <h1 id="dqTitle">Préparez votre devis matériaux</h1>
                <p>Recherchez un article ou importez votre liste. L’IA estime les quantités, vérifie la disponibilité et vous aide à budgéter.</p>
            </div>

            <aside class="dq-promo" aria-label="Présentation OVANIE">
                <img class="dq-promo-product dq-promo-product--cement" src="{{ asset('storage/products/bZEUVVa0LvqFmxuGP07JTv8G0kJ6S5ZFEJKwF3GV.jpg') }}" alt="Sac de ciment">
                <img class="dq-promo-product dq-promo-product--block" src="{{ asset('storage/products/8rmeqClF3woySHBaUjUZCa7onENxn7ZWbx0ZXliy.jpg') }}" alt="Bloc béton">
                <span class="dq-hardhat" aria-hidden="true"><i data-lucide="hard-hat"></i></span>
                <div class="dq-promo-copy">
                    <strong>OVANIE</strong>
                    <p>Catalogue, prix et stock réunis dans un seul outil.</p>
                    <div class="dq-promo-list">
                        <span><i data-lucide="check"></i> Données à jour</span>
                        <span><i data-lucide="check"></i> Estimation rapide</span>
                        <span><i data-lucide="check"></i> Résultats fiables</span>
                    </div>
                </div>
            </aside>
        </section>

        <section class="dq-benefits" aria-label="Fonctionnalités du calculateur">
            <article class="dq-benefit">
                <span class="dq-benefit-icon"><i data-lucide="search"></i></span>
                <div>
                    <strong>Recherche produit</strong>
                    <span>Trouvez rapidement un produit et vérifiez sa disponibilité en un clic.</span>
                </div>
            </article>
            <article class="dq-benefit">
                <span class="dq-benefit-icon"><i data-lucide="file-badge"></i></span>
                <div>
                    <strong>Devis Basic / Premium</strong>
                    <span>Choisissez le niveau de détail adapté à votre besoin.</span>
                </div>
            </article>
            <article class="dq-benefit">
                <span class="dq-benefit-icon orange"><i data-lucide="badge-dollar-sign"></i></span>
                <div>
                    <strong>Prix en FCFA</strong>
                    <span>Des prix clairs, calculés à partir des informations disponibles.</span>
                </div>
            </article>
            <article class="dq-benefit">
                <span class="dq-benefit-icon"><i data-lucide="shopping-cart"></i></span>
                <div>
                    <strong>Panier direct</strong>
                    <span>Ajoutez vos articles au panier et finalisez votre commande.</span>
                </div>
            </article>
        </section>

        <section class="dq-workspace" aria-label="Calculateur de devis">
            <span class="dq-or" aria-hidden="true">OU</span>

            <article class="dq-panel dq-panel--blue" id="ai-availability">
                <header class="dq-panel-head">
                    <span class="dq-panel-icon"><i data-lucide="search"></i></span>
                    <div>
                        <h2>Disponibilité — Trouver un article</h2>
                        <p>Recherchez un produit précis et vérifiez sa disponibilité.</p>
                    </div>
                </header>

                <label class="dq-field-label" for="availabilityInput">Rechercher un article</label>
                <div class="dq-input-wrap">
                    <i data-lucide="search"></i>
                    <input class="dq-input" id="availabilityInput" type="search" placeholder="Ex. Ciment CPA 42.5, Fer à béton, Parpaing..." autocomplete="off">
                </div>

                <button type="button" class="dq-btn dq-btn-blue js-ai-availability">
                    <i data-lucide="search-check"></i>
                    Vérifier
                </button>

                <div class="dq-separator">ou</div>

                <div class="dq-mini-actions">
                    <button type="button" class="dq-btn dq-btn-outline js-ai-voice">
                        <i data-lucide="mic"></i>
                        Recherche vocale
                    </button>
                    <button type="button" class="dq-btn dq-btn-outline js-ai-available">
                        <i data-lucide="package-check"></i>
                        Voir disponibles
                    </button>
                </div>

                <div class="dq-tip">
                    <i data-lucide="lightbulb"></i>
                    <div><strong>Astuce :</strong> Saisissez une référence, un nom de produit ou une catégorie pour obtenir des résultats précis.</div>
                </div>

                <div class="dq-result" id="voiceResult" aria-live="polite"></div>
            </article>

            <article class="dq-panel dq-panel--orange" id="ai-quote">
                <header class="dq-panel-head">
                    <span class="dq-panel-icon orange"><i data-lucide="calculator"></i></span>
                    <div>
                        <h2>Liste de produits — Calculer une estimation</h2>
                        <p>Importez plusieurs articles et obtenez une estimation complète.</p>
                    </div>
                </header>

                <div class="dq-level-row">
                    <label>
                        <span class="dq-field-label">Niveau de devis</span>
                        <select class="dq-select" id="quoteLevel">
                            <option value="basic">Devis Basic</option>
                            <option value="premium">Devis Premium</option>
                        </select>
                    </label>

                    <button type="button" class="dq-btn dq-btn-soft js-ai-example">
                        <i data-lucide="wand-sparkles"></i>
                        Charger un exemple
                    </button>
                </div>

                <label class="dq-field-label" for="quoteList">Collez ou saisissez votre liste d’articles</label>
                <textarea class="dq-textarea" id="quoteList" maxlength="2000" placeholder="1. Ciment CPA 42.5 - Sac 50 kg : 100&#10;2. Fer à béton HA 12 mm - Barre 12 m : 50&#10;3. Parpaing plein 20x20x40 : 200&#10;4. Sable de rivière : 5 m³&#10;5. Gravillon 10/20 : 3 m³&#10;6. Peinture acrylique - 20 L : 5"></textarea>
                <div class="dq-counter"><span id="quoteCounter">0</span>&nbsp;/ 2000 caractères</div>

                <div class="dq-quote-actions">
                    <button type="button" class="dq-btn dq-btn-orange js-ai-quote">
                        <i data-lucide="calculator"></i>
                        Calculer le devis IA
                    </button>
                    <button type="button" class="dq-btn dq-btn-outline js-ai-clear">
                        <i data-lucide="trash-2"></i>
                        Effacer
                    </button>
                </div>

                <div class="dq-result" id="quoteResult" aria-live="polite"></div>
            </article>
        </section>

        <section class="dq-info-strip" aria-label="Conseil d'utilisation">
            <span class="dq-info-strip-icon"><i data-lucide="info"></i></span>
            <div>
                <strong>Cherchez un produit unique, ou importez une liste complète ci-contre.</strong>
                <span>L’IA d’OVANIE analyse vos besoins et vous propose les meilleurs résultats.</span>
            </div>
        </section>

        <section class="dq-trust" aria-label="Garanties OVANIE">
            <article class="dq-trust-item">
                <span class="dq-trust-icon"><i data-lucide="shield-check"></i></span>
                <div><strong>Données à jour</strong><span>Prix et stock vérifiés</span></div>
            </article>
            <article class="dq-trust-item">
                <span class="dq-trust-icon"><i data-lucide="clock-3"></i></span>
                <div><strong>Estimation rapide</strong><span>Résultats en quelques secondes</span></div>
            </article>
            <article class="dq-trust-item">
                <span class="dq-trust-icon"><i data-lucide="badge-check"></i></span>
                <div><strong>Contrôle qualité</strong><span>Sources fiables et vérifiées</span></div>
            </article>
            <article class="dq-trust-item">
                <span class="dq-trust-icon"><i data-lucide="headphones"></i></span>
                <div><strong>Assistance 7j/7</strong><span>Notre équipe vous accompagne</span></div>
            </article>
            <article class="dq-trust-item">
                <span class="dq-trust-icon"><i data-lucide="lock-keyhole"></i></span>
                <div><strong>Sécurité garantie</strong><span>Vos données sont protégées</span></div>
            </article>
        </section>
    </div>
</main>
@endsection

@section('scripts')
<script>
    window.OVANIE_BOOTSTRAP_PRODUCTS = @json($ovanieBootstrapProducts);
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const products = Array.isArray(window.OVANIE_BOOTSTRAP_PRODUCTS)
            ? window.OVANIE_BOOTSTRAP_PRODUCTS
            : [];

        const availabilityInput = document.getElementById('availabilityInput');
        const availabilityResult = document.getElementById('voiceResult');
        const quoteList = document.getElementById('quoteList');
        const quoteLevel = document.getElementById('quoteLevel');
        const quoteResult = document.getElementById('quoteResult');
        const quoteCounter = document.getElementById('quoteCounter');

        function refreshIcons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        }

        function normalizeText(value) {
            return String(value ?? '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function formatPrice(value) {
            const amount = Number(value || 0);
            return amount > 0
                ? amount.toLocaleString('fr-FR') + ' FCFA'
                : 'Sur devis';
        }

        function getUnitPrice(product) {
            return Number(product?.final_price || product?.promo_price || product?.price || 0);
        }

        function findProduct(query) {
            const normalizedQuery = normalizeText(query);
            if (!normalizedQuery) return null;

            const exact = products.find(product => normalizeText(product.name) === normalizedQuery);
            if (exact) return exact;

            return products.find(product => {
                const normalizedName = normalizeText(product.name);
                return normalizedName.includes(normalizedQuery) || normalizedQuery.includes(normalizedName);
            }) || null;
        }

        function showResult(container, html) {
            if (!container) return;
            container.innerHTML = html;
            container.classList.add('is-visible');
        }

        function hideResult(container) {
            if (!container) return;
            container.classList.remove('is-visible');
            container.innerHTML = '';
        }

        function productResultMarkup(product) {
            const stock = Number(product.stock || 0);
            const available = stock > 0;

            return `
                <div class="dq-result-title">${available ? 'Produit disponible' : 'Produit momentanément indisponible'}</div>
                <div class="dq-product-result">
                    <strong>${product.name}</strong>
                    <div class="dq-result-meta">Stock : ${stock} · Prix : ${formatPrice(getUnitPrice(product))}</div>
                </div>
                <a class="dq-btn dq-btn-blue" href="/catalog?product=${encodeURIComponent(product.id)}" style="text-decoration:none;margin-top:10px;">
                    Voir dans le catalogue
                </a>
            `;
        }

        document.querySelector('.js-ai-availability')?.addEventListener('click', function () {
            const product = findProduct(availabilityInput?.value);

            if (!product) {
                showResult(availabilityResult, `
                    <div class="dq-result-title">Aucun résultat exact</div>
                    <p>Essayez une référence, un nom plus court ou utilisez « Voir disponibles ».</p>
                `);
                return;
            }

            showResult(availabilityResult, productResultMarkup(product));
        });

        availabilityInput?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                document.querySelector('.js-ai-availability')?.click();
            }
        });

        document.querySelector('.js-ai-available')?.addEventListener('click', function () {
            const availableProducts = products.filter(product => Number(product.stock || 0) > 0).slice(0, 8);

            showResult(availabilityResult, `
                <div class="dq-result-title">Produits actuellement disponibles</div>
                ${availableProducts.length
                    ? availableProducts.map(product => `
                        <div class="dq-product-result">
                            <strong>${product.name}</strong>
                            <div class="dq-result-meta">Stock : ${Number(product.stock || 0)} · ${formatPrice(getUnitPrice(product))}</div>
                        </div>
                    `).join('')
                    : '<p>Aucun produit disponible pour le moment.</p>'}
            `);
        });

        document.querySelector('.js-ai-voice')?.addEventListener('click', function () {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                showResult(availabilityResult, `
                    <div class="dq-result-title">Recherche vocale indisponible</div>
                    <p>Votre navigateur ne prend pas en charge la reconnaissance vocale. Utilisez la barre de recherche.</p>
                `);
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.lang = 'fr-FR';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            recognition.onstart = function () {
                showResult(availabilityResult, '<p>Parlez maintenant…</p>');
            };

            recognition.onresult = function (event) {
                const spokenText = event.results[0][0].transcript;
                availabilityInput.value = spokenText;
                document.querySelector('.js-ai-availability')?.click();
            };

            recognition.onerror = function () {
                showResult(availabilityResult, '<p>La recherche vocale a échoué. Réessayez ou saisissez votre recherche.</p>');
            };

            recognition.start();
        });

        function updateCounter() {
            if (quoteCounter && quoteList) {
                quoteCounter.textContent = String(quoteList.value.length);
            }
        }

        quoteList?.addEventListener('input', updateCounter);
        updateCounter();

        document.querySelector('.js-ai-example')?.addEventListener('click', function () {
            if (!quoteList) return;
            quoteList.value = [
                'Ciment CPA 42.5 - Sac 50 kg : 100',
                'Fer à béton HA 12 mm - Barre 12 m : 50',
                'Parpaing plein 20x20x40 : 200',
                'Sable de rivière : 5',
                'Gravillon 10/20 : 3',
                'Peinture acrylique - 20 L : 5'
            ].join('\n');
            updateCounter();
        });

        document.querySelector('.js-ai-clear')?.addEventListener('click', function () {
            if (quoteList) quoteList.value = '';
            hideResult(quoteResult);
            updateCounter();
        });

        function parseQuoteLine(line) {
            const cleanLine = String(line || '')
                .replace(/^\s*\d+[.)-]?\s*/, '')
                .trim();

            if (!cleanLine) return null;

            const colonMatch = cleanLine.match(/^(.+?)\s*:\s*(\d+(?:[.,]\d+)?)\s*$/);
            if (colonMatch) {
                return {
                    name: colonMatch[1].trim(),
                    qty: Number(colonMatch[2].replace(',', '.'))
                };
            }

            const leadingQtyMatch = cleanLine.match(/^(\d+(?:[.,]\d+)?)\s*[xX×]?\s+(.+)$/);
            if (leadingQtyMatch) {
                return {
                    qty: Number(leadingQtyMatch[1].replace(',', '.')),
                    name: leadingQtyMatch[2].trim()
                };
            }

            return { name: cleanLine, qty: 1 };
        }

        document.querySelector('.js-ai-quote')?.addEventListener('click', function () {
            const text = quoteList?.value || '';
            const level = quoteLevel?.value || 'basic';

            if (!text.trim()) {
                showResult(quoteResult, `
                    <div class="dq-result-title">Liste vide</div>
                    <p>Saisissez au moins un article avant de calculer votre estimation.</p>
                `);
                return;
            }

            let total = 0;
            const matchedRows = [];
            const unmatchedRows = [];

            text.split(/\r?\n/).forEach(line => {
                const parsed = parseQuoteLine(line);
                if (!parsed || !parsed.name) return;

                const product = findProduct(parsed.name);
                if (!product) {
                    unmatchedRows.push(parsed.name);
                    return;
                }

                const qty = Math.max(1, Number(parsed.qty || 1));
                let unit = getUnitPrice(product);

                if (level === 'premium') {
                    unit = Math.round(unit * 1.25);
                }

                const lineTotal = unit * qty;
                total += lineTotal;

                matchedRows.push({ product, qty, unit, lineTotal });
            });

            const rowsHtml = matchedRows.map(row => `
                <tr>
                    <td>${row.product.name}</td>
                    <td>${row.qty}</td>
                    <td>${formatPrice(row.unit)}</td>
                    <td><strong>${formatPrice(row.lineTotal)}</strong></td>
                </tr>
            `).join('');

            const unmatchedHtml = unmatchedRows.length
                ? `<p style="margin-top:12px;color:#9a3412;"><strong>Articles non reconnus :</strong> ${unmatchedRows.join(', ')}</p>`
                : '';

            showResult(quoteResult, `
                <div class="dq-result-title">Devis ${level === 'premium' ? 'Premium' : 'Basic'} : ${formatPrice(total)}</div>
                <div class="dq-table-wrap">
                    <table class="dq-result-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Qté</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml || '<tr><td colspan="4">Aucun produit reconnu dans la liste.</td></tr>'}
                        </tbody>
                    </table>
                </div>
                ${unmatchedHtml}
                <a class="dq-btn dq-btn-orange" href="/catalog" style="text-decoration:none;margin-top:14px;">
                    Continuer dans le catalogue
                </a>
            `);
        });

        refreshIcons();
    });
</script>
@endsection
