@extends('layouts.vendor')

@section('title', 'Vendeurs actes | OVANIE')

@section('inline_styles')
    :root {
        --va-navy: #09265b;
        --va-navy-deep: #041a40;
        --va-blue: #0d5ee8;
        --va-blue-soft: #edf4ff;
        --va-orange: #ff5a0a;
        --va-orange-soft: #fff7f1;
        --va-green: #12b76a;
        --va-green-soft: #eefcf4;
        --va-red: #ef4444;
        --va-red-soft: #fff2f2;
        --va-text: #102a5a;
        --va-muted: #64789a;
        --va-border: #dbe5f1;
        --va-soft: #f7f9fc;
        --va-white: #ffffff;
        --va-shadow: 0 8px 26px rgba(13, 42, 91, .07);
    }

    .va-page {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
        padding: 0 0 36px;
        color: var(--va-text);
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .va-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 265px;
        gap: 28px;
        align-items: stretch;
        min-height: 260px;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 8px;
        background:
            radial-gradient(circle at 78% 12%, rgba(22, 91, 207, .28), transparent 31%),
            linear-gradient(118deg, #08234f 0%, #031936 58%, #061a3e 100%);
        box-shadow: 0 15px 34px rgba(7, 29, 66, .16);
    }

    .va-hero::after {
        content: '';
        position: absolute;
        inset: auto -110px -210px auto;
        width: 440px;
        height: 440px;
        border-radius: 50%;
        background: rgba(21, 96, 225, .09);
        pointer-events: none;
    }

    .va-hero-main {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
    }

    .va-kicker {
        width: fit-content;
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        margin-bottom: 14px;
        border: 1px solid var(--va-orange);
        border-radius: 999px;
        color: #ff8a45;
        font-size: 10.5px;
        font-weight: 900;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .va-hero h1 {
        max-width: 720px;
        margin: 0;
        color: #fff;
        font-size: clamp(30px, 3.35vw, 48px);
        line-height: 1.06;
        font-weight: 900;
        letter-spacing: -.035em;
        text-transform: uppercase;
    }

    .va-hero-main > p {
        max-width: 680px;
        margin: 18px 0 0;
        color: #e2ebf8;
        font-size: 13px;
        line-height: 1.65;
    }

    .va-training-badge {
        width: fit-content;
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 0 12px;
        margin-top: 20px;
        border-radius: 5px;
        background: linear-gradient(90deg, #0c4fc2, #0b63ef);
        color: #fff;
        font-size: 10.5px;
        font-weight: 900;
        box-shadow: 0 8px 18px rgba(13, 94, 232, .24);
    }

    .va-progress-card {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 100%;
        padding: 22px 20px;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 8px;
        background: rgba(5, 24, 55, .48);
        box-shadow: inset 0 0 30px rgba(30, 103, 226, .06);
    }

    .va-progress-card > span {
        color: #fff;
        font-size: 10.5px;
        font-weight: 800;
    }

    .va-progress-ring {
        --progress: 0deg;
        width: 86px;
        height: 86px;
        display: grid;
        place-items: center;
        margin: 18px 0 12px;
        border-radius: 50%;
        background: conic-gradient(#0d6cf2 var(--progress), rgba(255,255,255,.08) var(--progress));
        position: relative;
    }

    .va-progress-ring::before {
        content: '';
        position: absolute;
        inset: 8px;
        border-radius: 50%;
        background: #0a214a;
    }

    .va-progress-ring strong {
        position: relative;
        z-index: 1;
        color: #fff;
        font-size: 24px;
        font-weight: 900;
    }

    .va-progress-meta {
        color: #e5eefb;
        font-size: 11px;
        font-weight: 700;
    }

    .va-progress-dots {
        display: grid;
        grid-template-columns: repeat(10, 1fr);
        gap: 8px;
        margin-top: 20px;
    }

    .va-progress-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background: #d8e0ea;
        transition: .2s ease;
    }

    .va-progress-dot.is-active {
        background: #0d6cf2;
        box-shadow: 0 0 0 3px rgba(13, 108, 242, .13);
    }

    .va-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px;
        gap: 22px;
        align-items: start;
    }

    .va-content {
        min-width: 0;
    }

    .va-module {
        position: relative;
        padding: 24px 20px 28px 22px;
        border-left: 4px solid var(--va-blue);
        border-bottom: 1px solid #e8eef6;
        background: #fff;
        scroll-margin-top: 86px;
    }

    .va-module + .va-module {
        margin-top: 14px;
    }

    .va-module-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .va-module-num {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--va-navy);
        color: #fff;
        font-size: 14px;
        font-weight: 900;
        box-shadow: 0 5px 14px rgba(9, 38, 91, .18);
    }

    .va-module h2 {
        margin: 0;
        color: var(--va-navy);
        font-size: 20px;
        font-weight: 900;
        letter-spacing: -.018em;
        text-transform: uppercase;
    }

    .va-prose {
        color: #3d5278;
        font-size: 12px;
        line-height: 1.68;
    }

    .va-prose p { margin: 0 0 8px; }
    .va-prose p:last-child { margin-bottom: 0; }

    .va-subtitle {
        margin: 18px 0 8px;
        color: var(--va-navy);
        font-size: 11.5px;
        font-weight: 900;
    }

    .va-callout {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: 12px;
        margin-top: 14px;
        padding: 16px 16px;
        border: 1px solid var(--callout-border);
        border-radius: 9px;
        background: var(--callout-bg);
    }

    .va-callout.orange {
        --callout-border: #ffd6bd;
        --callout-bg: linear-gradient(90deg, #fffaf6, #fff6ef);
        --callout-color: #d94a00;
    }

    .va-callout.blue {
        --callout-border: #cfe0ff;
        --callout-bg: linear-gradient(90deg, #f4f8ff, #eef5ff);
        --callout-color: #1261df;
    }

    .va-callout.green {
        --callout-border: #caefd9;
        --callout-bg: linear-gradient(90deg, #f3fff7, #ecfbf3);
        --callout-color: #0d9d52;
    }

    .va-callout.red {
        --callout-border: #ffd2d2;
        --callout-bg: linear-gradient(90deg, #fff6f6, #fff0f0);
        --callout-color: #e02d2d;
    }

    .va-callout-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        color: var(--callout-color);
    }

    .va-callout-icon svg {
        width: 29px;
        height: 29px;
        stroke-width: 1.75;
    }

    .va-callout strong {
        display: block;
        margin-bottom: 5px;
        color: var(--callout-color);
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .va-callout p {
        margin: 0;
        color: #3f5375;
        font-size: 11.5px;
        line-height: 1.6;
    }

    .va-callout.green p { color: #25704a; }
    .va-callout.red p { color: #a42c2c; }
    .va-callout.blue p { color: #244c8d; }

    .va-checklist {
        display: grid;
        gap: 6px;
        margin: 10px 0 0;
        padding: 0;
        list-style: none;
    }

    .va-checklist li {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        color: #526583;
        font-size: 11.5px;
        line-height: 1.45;
    }

    .va-checklist .check {
        width: 15px;
        height: 15px;
        flex: 0 0 15px;
        margin-top: 1px;
        display: grid;
        place-items: center;
        border: 1.5px solid #14b866;
        border-radius: 50%;
        color: #14b866;
    }

    .va-checklist .check svg {
        width: 10px;
        height: 10px;
        stroke-width: 3;
    }

    .va-resource-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .va-resource {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 60px;
        padding: 12px;
        border: 1px solid #dbe5f1;
        border-radius: 8px;
        background: #fff;
        color: var(--va-navy);
        text-decoration: none;
        transition: .18s ease;
    }

    .va-resource:hover {
        border-color: #a9c5f3;
        background: #f8fbff;
        transform: translateY(-1px);
    }

    .va-resource-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--va-blue-soft);
        color: var(--va-blue);
    }

    .va-resource-icon svg { width: 18px; height: 18px; }
    .va-resource strong { display: block; font-size: 11px; font-weight: 900; }
    .va-resource small { display: block; margin-top: 2px; color: #6d7f9e; font-size: 9.5px; line-height: 1.35; }

    .va-aside {
        position: sticky;
        top: calc(var(--ov-topbar-height, 64px) + 16px);
        padding: 16px 14px;
        border: 1px solid var(--va-border);
        border-radius: 8px;
        background: #fff;
        box-shadow: var(--va-shadow);
    }

    .va-aside h3 {
        margin: 0 0 12px;
        color: var(--va-navy);
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .va-toc {
        display: grid;
        gap: 3px;
    }

    .va-toc a {
        display: grid;
        grid-template-columns: 23px minmax(0, 1fr);
        gap: 4px;
        align-items: start;
        min-height: 42px;
        padding: 8px 8px;
        border: 1px solid transparent;
        border-radius: 6px;
        color: #31507f;
        font-size: 10.5px;
        line-height: 1.35;
        text-decoration: none;
        transition: .18s ease;
    }

    .va-toc a span:first-child {
        color: #6d82a7;
        font-weight: 900;
    }

    .va-toc a:hover,
    .va-toc a.is-active {
        border-color: #cfe0fb;
        background: #eef5ff;
        color: var(--va-blue);
    }

    .va-toc a.is-active span:first-child { color: var(--va-blue); }

    .va-help {
        margin-top: 14px;
        padding: 13px 12px;
        border: 1px solid #cfdbea;
        border-radius: 7px;
        background: #fbfdff;
        text-align: center;
    }

    .va-help-head {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--va-navy);
        font-size: 10.5px;
        font-weight: 900;
    }

    .va-help-head svg { width: 18px; height: 18px; }
    .va-help p { margin: 7px 0 0; color: #5f7396; font-size: 9.5px; line-height: 1.5; }
    .va-help a { color: var(--va-blue); font-size: 11.5px; font-weight: 900; text-decoration: none; }

    @media (max-width: 1080px) {
        .va-hero { grid-template-columns: minmax(0, 1fr) 235px; }
        .va-layout { grid-template-columns: minmax(0, 1fr) 200px; }
        .va-hero h1 { font-size: 38px; }
    }

    @media (max-width: 880px) {
        .va-hero {
            grid-template-columns: 1fr;
            min-height: auto;
            padding: 28px;
        }

        .va-progress-card {
            min-height: auto;
            display: grid;
            grid-template-columns: auto auto 1fr;
            gap: 16px;
            align-items: center;
        }

        .va-progress-ring { width: 70px; height: 70px; margin: 0; }
        .va-progress-dots { margin: 0; }
        .va-layout { grid-template-columns: 1fr; }
        .va-aside { position: static; order: -1; }
        .va-toc { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .va-help { display: none; }
    }

    @media (max-width: 620px) {
        .va-hero { padding: 22px 18px; border-radius: 6px; }
        .va-hero h1 { font-size: 30px; }
        .va-progress-card { grid-template-columns: auto 1fr; }
        .va-progress-dots { grid-column: 1 / -1; }
        .va-module { padding: 20px 14px 24px 16px; }
        .va-module h2 { font-size: 16px; }
        .va-callout { grid-template-columns: 30px minmax(0, 1fr); padding: 14px 12px; }
        .va-callout-icon { width: 28px; height: 28px; }
        .va-callout-icon svg { width: 24px; height: 24px; }
        .va-toc { grid-template-columns: 1fr; }
        .va-resource-grid { grid-template-columns: 1fr; }
    }
@endsection

@section('content')
<div class="va-page">
    <section class="va-hero">
        <div class="va-hero-main">
            <span class="va-kicker">Vendeurs actes</span>
            <h1>Guide de formation<br>à la vente sur OVANIE</h1>
            <p>
                Apprenez les bonnes pratiques pour présenter vos produits, rassurer vos clients
                et augmenter vos ventes sur la marketplace OVANIE.
            </p>
            <span class="va-training-badge">Formation destinée aux vendeurs d’OVANIE</span>
        </div>

        <aside class="va-progress-card" aria-label="Progression de la formation">
            <span>Votre progression</span>
            <div class="va-progress-ring" id="vaProgressRing">
                <strong id="vaProgressPercent">0%</strong>
            </div>
            <div>
                <div class="va-progress-meta"><span id="vaProgressCount">0</span> / 10 modules</div>
                <div class="va-progress-dots" id="vaProgressDots" aria-hidden="true">
                    @for($i = 1; $i <= 10; $i++)
                        <span class="va-progress-dot {{ $i === 1 ? 'is-active' : '' }}"></span>
                    @endfor
                </div>
            </div>
        </aside>
    </section>

    <div class="va-layout">
        <main class="va-content">
            <section class="va-module" id="module-1" data-module="1">
                <header class="va-module-head">
                    <span class="va-module-num">01</span>
                    <h2>Introduction</h2>
                </header>

                <div class="va-prose">
                    <p>Bienvenue dans le guide de formation à la vente sur OVANIE.</p>
                    <p>Ce guide vous accompagne pas à pas pour optimiser votre boutique, présenter vos produits de façon professionnelle et offrir la meilleure expérience possible à vos clients.</p>
                </div>

                <div class="va-callout orange">
                    <span class="va-callout-icon"><i data-lucide="scale"></i></span>
                    <div>
                        <strong>Règle n°1 : la confiance avant tout</strong>
                        <p>Un client n’achète que s’il a confiance. La clarté, la transparence et le respect des engagements sont les bases d’une relation durable et rentable.</p>
                    </div>
                </div>

                <div class="va-callout orange">
                    <span class="va-callout-icon"><i data-lucide="lightbulb"></i></span>
                    <div>
                        <strong>Exemple</strong>
                        <p>Un client qui reçoit exactement ce qu’il a vu et dans les délais annoncés reviendra et recommandera votre boutique à d’autres professionnels.</p>
                    </div>
                </div>

                <div class="va-callout blue">
                    <span class="va-callout-icon"><i data-lucide="quote"></i></span>
                    <div>
                        <strong>Citation</strong>
                        <p>« La qualité attire le client, mais le service le fidélise. »<br>— Proverbe africain</p>
                    </div>
                </div>

                <div class="va-callout green">
                    <span class="va-callout-icon"><i data-lucide="circle-check-big"></i></span>
                    <div>
                        <strong>Bon exemple</strong>
                        <p>Vous répondez rapidement aux questions, fournissez des informations précises et respectez vos délais de livraison.</p>
                    </div>
                </div>

                <div class="va-callout red">
                    <span class="va-callout-icon"><i data-lucide="circle-x"></i></span>
                    <div>
                        <strong>Mauvais exemple</strong>
                        <p>Vous ignorez les messages, fournissez des informations incomplètes et livrez en retard sans prévenir le client.</p>
                    </div>
                </div>

                <div class="va-callout blue">
                    <span class="va-callout-icon"><i data-lucide="star"></i></span>
                    <div>
                        <strong>Astuce</strong>
                        <p>Mettez-vous toujours à la place de votre client : quelles informations aimeriez-vous recevoir avant d’acheter un matériau pour votre chantier ?</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-2" data-module="2">
                <header class="va-module-head">
                    <span class="va-module-num">02</span>
                    <h2>Présentation de vos produits</h2>
                </header>

                <div class="va-prose">
                    <p>Une bonne présentation de vos produits augmente la confiance et améliore votre taux de conversion.</p>
                </div>

                <h3 class="va-subtitle">Les éléments indispensables</h3>
                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Photos réelles, nettes et de bonne qualité.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Titre clair avec les caractéristiques principales du produit.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Description détaillée, honnête et orientée vers l’usage client.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Prix juste et informations promotionnelles exactes.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Informations logistiques complètes : poids, dimensions et conditionnement.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Disponibilité du stock mise à jour régulièrement.</li>
                </ul>

                <div class="va-callout orange">
                    <span class="va-callout-icon"><i data-lucide="lightbulb"></i></span>
                    <div>
                        <strong>Exemple</strong>
                        <p>Pour un sac de ciment 50 kg, indiquez la marque, le type, la norme, l’usage recommandé, le conditionnement et, lorsque l’information existe, le nombre de sacs par palette.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-3" data-module="3">
                <header class="va-module-head">
                    <span class="va-module-num">03</span>
                    <h2>Réponse aux clients</h2>
                </header>

                <div class="va-prose">
                    <p>La réactivité et la qualité des réponses sont déterminantes pour conclure une vente.</p>
                </div>

                <h3 class="va-subtitle">Bonnes pratiques</h3>
                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Répondez rapidement pendant vos heures ouvrables.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Soyez courtois, professionnel et précis.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Proposez une solution adaptée au besoin réel du client.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Confirmez les informations importantes : prix, quantité, disponibilité et délai.</li>
                </ul>

                <div class="va-callout blue">
                    <span class="va-callout-icon"><i data-lucide="message-circle"></i></span>
                    <div>
                        <strong>Réponse professionnelle</strong>
                        <p>« Bonjour, ce produit est disponible. Pour la quantité demandée, le délai estimé est indiqué au moment de la commande. Je reste disponible pour vous aider à vérifier les caractéristiques techniques. »</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-4" data-module="4">
                <header class="va-module-head">
                    <span class="va-module-num">04</span>
                    <h2>Fixation des prix</h2>
                </header>

                <div class="va-prose">
                    <p>Un prix professionnel doit être cohérent avec votre coût, votre marge, la qualité du produit et les conditions du marché.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Gardez un prix normal réaliste et stable.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Utilisez les promotions uniquement lorsqu’elles sont réelles.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Vérifiez régulièrement vos prix face aux coûts d’approvisionnement.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Expliquez la valeur : qualité, disponibilité, garantie et service.</li>
                </ul>

                <div class="va-callout orange">
                    <span class="va-callout-icon"><i data-lucide="badge-percent"></i></span>
                    <div>
                        <strong>Conseil</strong>
                        <p>Ne créez pas de fausse remise en augmentant artificiellement le prix normal avant une promotion. La confiance du client vaut plus qu’une vente ponctuelle.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-5" data-module="5">
                <header class="va-module-head">
                    <span class="va-module-num">05</span>
                    <h2>Gestion des stocks</h2>
                </header>

                <div class="va-prose">
                    <p>Un stock incorrect provoque des annulations, retarde les chantiers et détériore votre réputation vendeur.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Mettez les quantités à jour dès chaque réception ou sortie importante.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Désactivez temporairement un produit réellement indisponible.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Anticipez le réapprovisionnement des produits les plus vendus.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Contrôlez les commandes plusieurs fois par jour.</li>
                </ul>

                <div class="va-callout red">
                    <span class="va-callout-icon"><i data-lucide="triangle-alert"></i></span>
                    <div>
                        <strong>À éviter</strong>
                        <p>Ne laissez jamais un produit affiché comme disponible lorsqu’il n’est plus réellement en stock.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-6" data-module="6">
                <header class="va-module-head">
                    <span class="va-module-num">06</span>
                    <h2>Livraison et délais</h2>
                </header>

                <div class="va-prose">
                    <p>La livraison est une partie essentielle de l’expérience client. Le délai annoncé doit être réaliste et respecté.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Préparez la commande dès sa validation.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Contrôlez la conformité et l’état du produit avant départ.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Utilisez un emballage adapté au poids et à la fragilité.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Signalez rapidement tout incident qui pourrait modifier le délai.</li>
                </ul>

                <div class="va-callout green">
                    <span class="va-callout-icon"><i data-lucide="truck"></i></span>
                    <div>
                        <strong>Bonne pratique</strong>
                        <p>Préparez vos produits selon le véhicule nécessaire, les dimensions, le poids et les besoins éventuels de déchargement sur chantier.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-7" data-module="7">
                <header class="va-module-head">
                    <span class="va-module-num">07</span>
                    <h2>Service après-vente</h2>
                </header>

                <div class="va-prose">
                    <p>Un client qui rencontre un problème n’est pas forcément un client perdu. Une résolution rapide peut créer une relation durable.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Écoutez les faits avant de répondre.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Vérifiez la commande et les preuves disponibles.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Restez calme et factuel, même en cas de désaccord.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Proposez une solution conforme aux règles OVANIE.</li>
                </ul>

                <div class="va-callout orange">
                    <span class="va-callout-icon"><i data-lucide="handshake"></i></span>
                    <div>
                        <strong>Objectif</strong>
                        <p>Résoudre le problème sans créer un second problème. Un bon service après-vente protège votre réputation et la confiance dans toute la marketplace.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-8" data-module="8">
                <header class="va-module-head">
                    <span class="va-module-num">08</span>
                    <h2>Communication professionnelle</h2>
                </header>

                <div class="va-prose">
                    <p>Votre manière de communiquer représente votre boutique. Chaque message doit être clair, utile et respectueux.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Saluez le client et identifiez clairement sa demande.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Utilisez des phrases simples et évitez les réponses ambiguës.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>N’annoncez jamais une disponibilité ou un délai que vous ne pouvez pas tenir.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Gardez les échanges importants dans les canaux prévus par OVANIE.</li>
                </ul>

                <div class="va-callout blue">
                    <span class="va-callout-icon"><i data-lucide="messages-square"></i></span>
                    <div>
                        <strong>Astuce</strong>
                        <p>Avant d’envoyer un message, relisez-le comme si vous étiez le client. Est-il clair ? Donne-t-il une réponse précise ? Indique-t-il la prochaine étape ?</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-9" data-module="9">
                <header class="va-module-head">
                    <span class="va-module-num">09</span>
                    <h2>Règles et politiques OVANIE</h2>
                </header>

                <div class="va-prose">
                    <p>La plateforme repose sur la fiabilité des informations et le respect des engagements pris envers les clients.</p>
                </div>

                <ul class="va-checklist">
                    <li><span class="check"><i data-lucide="check"></i></span>Publiez uniquement des produits dont vous pouvez assurer la disponibilité et la conformité.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Utilisez des photos fidèles au produit réellement vendu.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Respectez les règles de prix, de promotion, de traitement de commande et de livraison.</li>
                    <li><span class="check"><i data-lucide="check"></i></span>Coopérez rapidement lors d’un retour, d’une réclamation ou d’un litige.</li>
                </ul>

                <div class="va-callout red">
                    <span class="va-callout-icon"><i data-lucide="shield-alert"></i></span>
                    <div>
                        <strong>Important</strong>
                        <p>Les informations fausses, les retards répétés, les commandes non honorées ou le non-respect des règles peuvent dégrader les performances de votre boutique et entraîner des mesures de protection de la marketplace.</p>
                    </div>
                </div>
            </section>

            <section class="va-module" id="module-10" data-module="10">
                <header class="va-module-head">
                    <span class="va-module-num">10</span>
                    <h2>Ressources utiles</h2>
                </header>

                <div class="va-prose">
                    <p>Utilisez les principaux outils de votre espace vendeur pour appliquer immédiatement les bonnes pratiques de ce guide.</p>
                </div>

                <div class="va-resource-grid">
                    <a class="va-resource" href="{{ route('vendor.products') }}">
                        <span class="va-resource-icon"><i data-lucide="package-search"></i></span>
                        <span><strong>Catalogue produits</strong><small>Mettre à jour les produits, prix et stocks.</small></span>
                    </a>
                    <a class="va-resource" href="{{ route('vendor.orders') }}">
                        <span class="va-resource-icon"><i data-lucide="clipboard-list"></i></span>
                        <span><strong>Commandes clients</strong><small>Suivre et traiter les commandes actives.</small></span>
                    </a>
                    <a class="va-resource" href="{{ route('vendor.delivery.index') }}">
                        <span class="va-resource-icon"><i data-lucide="truck"></i></span>
                        <span><strong>Livraison</strong><small>Consulter vos paramètres et capacités logistiques.</small></span>
                    </a>
                    <a class="va-resource" href="{{ route('vendor.payouts.index') }}">
                        <span class="va-resource-icon"><i data-lucide="wallet-cards"></i></span>
                        <span><strong>Reversements</strong><small>Suivre vos ventes, commissions et reversements.</small></span>
                    </a>
                </div>

                <div class="va-callout green">
                    <span class="va-callout-icon"><i data-lucide="badge-check"></i></span>
                    <div>
                        <strong>Conseil final</strong>
                        <p>La réussite sur OVANIE repose sur quatre habitudes : une information produit fiable, une réponse rapide, un traitement sérieux des commandes et le respect des engagements.</p>
                    </div>
                </div>
            </section>
        </main>

        <aside class="va-aside" aria-label="Sommaire du guide">
            <h3>Sommaire</h3>
            <nav class="va-toc" id="vaToc">
                <a class="is-active" href="#module-1" data-toc="1"><span>01.</span><span>Introduction</span></a>
                <a href="#module-2" data-toc="2"><span>02.</span><span>Présentation de vos produits</span></a>
                <a href="#module-3" data-toc="3"><span>03.</span><span>Réponse aux clients</span></a>
                <a href="#module-4" data-toc="4"><span>04.</span><span>Fixation des prix</span></a>
                <a href="#module-5" data-toc="5"><span>05.</span><span>Gestion des stocks</span></a>
                <a href="#module-6" data-toc="6"><span>06.</span><span>Livraison et délais</span></a>
                <a href="#module-7" data-toc="7"><span>07.</span><span>Service après-vente</span></a>
                <a href="#module-8" data-toc="8"><span>08.</span><span>Communication professionnelle</span></a>
                <a href="#module-9" data-toc="9"><span>09.</span><span>Règles et politiques OVANIE</span></a>
                <a href="#module-10" data-toc="10"><span>10.</span><span>Ressources utiles</span></a>
            </nav>

            <div class="va-help">
                <span class="va-help-head"><i data-lucide="circle-help"></i>Besoin d’aide ?</span>
                <p>Contactez notre support au</p>
                <a href="tel:+2250161780000">01 61 78 00 00</a>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modules = Array.from(document.querySelectorAll('.va-module'));
    const tocLinks = Array.from(document.querySelectorAll('[data-toc]'));
    const dots = Array.from(document.querySelectorAll('.va-progress-dot'));

    const setActiveModule = (index) => {
        tocLinks.forEach((link) => {
            link.classList.toggle('is-active', Number(link.dataset.toc) === index);
        });

        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === index - 1);
        });
    };

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visible) {
            setActiveModule(Number(visible.target.dataset.module));
        }
    }, {
        rootMargin: '-20% 0px -60% 0px',
        threshold: [0, .1, .25, .5]
    });

    modules.forEach((module) => observer.observe(module));

    tocLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const target = document.querySelector(link.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (window.lucide) window.lucide.createIcons();
});
</script>
@endpush
