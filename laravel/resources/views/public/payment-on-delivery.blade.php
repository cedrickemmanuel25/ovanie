@extends('layouts.guest')

@section('title', 'Paiement à la livraison - OVANIE')

@section('content')
<style>
    .pod-page {
        background: #f5f7fb;
        padding: 18px 0 42px;
    }

    .pod-wrap {
        width: min(calc(100% - 56px), 1180px);
        margin: 0 auto;
    }

    .pod-hero {
        display: grid;
        grid-template-columns: 250px minmax(0, 1fr);
        min-height: 258px;
        overflow: hidden;
        border-radius: 16px;
        background:
            linear-gradient(90deg, rgba(3, 24, 79, .98), rgba(5, 47, 117, .96)),
            radial-gradient(circle at 90% 50%, rgba(27, 97, 204, .4), transparent 38%);
        color: #fff;
        box-shadow: 0 12px 30px rgba(6, 31, 80, .10);
        position: relative;
    }

    .pod-hero::after {
        content: "";
        position: absolute;
        inset: 0 0 0 auto;
        width: 38%;
        opacity: .12;
        background-image:
            linear-gradient(135deg, transparent 48%, #7ca6e5 49%, #7ca6e5 51%, transparent 52%),
            linear-gradient(45deg, transparent 48%, #7ca6e5 49%, #7ca6e5 51%, transparent 52%);
        background-size: 68px 68px;
    }

    .pod-hero__visual {
        position: relative;
        z-index: 1;
        display: grid;
        place-items: center;
        padding: 30px 22px;
    }

    .pod-hero__visual::after {
        content: "";
        position: absolute;
        right: 0;
        top: 44px;
        bottom: 44px;
        width: 1px;
        background: rgba(255,255,255,.45);
    }

    .pod-hero__visual svg {
        width: 148px;
        height: 148px;
        stroke-width: 1.6;
        color: #fff;
    }

    .pod-hero__copy {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 34px 42px 34px 34px;
    }

    .pod-kicker {
        margin-bottom: 10px;
        color: #ff7a00;
        font-size: 15px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .pod-hero h1 {
        margin: 0;
        max-width: 760px;
        font-size: clamp(36px, 4.6vw, 56px);
        line-height: .98;
        letter-spacing: -.04em;
        font-weight: 950;
    }

    .pod-hero p {
        margin: 14px 0 0;
        max-width: 760px;
        color: #e9eef9;
        font-size: 13.5px;
        line-height: 1.45;
        font-weight: 600;
    }

    .pod-tabs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-top: 16px;
        background: #fff;
        border: 1px solid #dfe6ef;
        border-radius: 16px 16px 0 0;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(7, 30, 72, .05);
    }

    .pod-tab {
        position: relative;
        min-height: 62px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 12px 16px;
        color: #08296f;
        font-size: 15px;
        font-weight: 850;
        border-right: 1px solid #e5ebf3;
        background: #fff;
        text-decoration: none;
    }

    .pod-tab:last-child { border-right: 0; }

    .pod-tab svg {
        width: 22px;
        height: 22px;
        color: #1149c7;
        stroke-width: 1.8;
    }

    .pod-tab.is-active {
        color: #061e5a;
    }

    .pod-tab.is-active svg { color: #ff6a00; }

    .pod-tab.is-active::after {
        content: "";
        position: absolute;
        left: 24px;
        right: 24px;
        bottom: 0;
        height: 3px;
        border-radius: 999px 999px 0 0;
        background: #ff6a00;
    }

    .pod-content {
        display: grid;
        gap: 18px;
        padding-top: 0;
    }

    .pod-section {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        gap: 16px;
        background: #fff;
        border: 1px solid #dfe6ef;
        border-radius: 0 0 16px 16px;
        padding: 24px 28px 24px;
        box-shadow: 0 8px 24px rgba(7, 30, 72, .05);
    }

    .pod-section + .pod-section {
        border-radius: 16px;
    }

    .pod-section__icon {
        align-self: start;
        width: 116px;
        height: 116px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        margin: 0 auto;
        background: linear-gradient(145deg, #f3f7ff, #e8f0fb);
    }

    .pod-section__icon svg {
        width: 58px;
        height: 58px;
        color: #0c3b9a;
        stroke-width: 1.7;
    }

    .pod-section__body {
        min-width: 0;
        max-width: 880px;
    }

    .pod-section__head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .pod-number {
        width: 42px;
        height: 38px;
        border-radius: 7px;
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, #164bb8, #0a2f84);
        color: #fff;
        font-size: 16px;
        font-weight: 900;
        box-shadow: 0 6px 14px rgba(10, 47, 132, .18);
    }

    .pod-section h2 {
        margin: 0;
        color: #082667;
        font-size: 22px;
        line-height: 1.15;
        font-weight: 950;
        letter-spacing: -.02em;
    }

    .pod-intro {
        margin: 0 0 18px;
        color: #40516f;
        font-size: 13.5px;
        line-height: 1.45;
        font-weight: 600;
    }

    .pod-section h3 {
        margin: 0 0 12px;
        color: #0a39a0;
        font-size: 16px;
        font-weight: 900;
    }

    .pod-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 8px;
    }

    .pod-list li {
        position: relative;
        padding-left: 24px;
        color: #344563;
        font-size: 13.5px;
        line-height: 1.45;
        font-weight: 600;
    }

    .pod-list li::before {
        content: "";
        position: absolute;
        left: 1px;
        top: .52em;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        border: 2px solid #ff6a00;
        background: #fff;
    }

    .pod-alerts {
        display: grid;
        gap: 10px;
        margin-top: 16px;
        max-width: 800px;
    }

    .pod-alert {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        padding: 13px 14px;
        border: 1px solid #ffc8bc;
        border-radius: 10px;
        background: linear-gradient(180deg, #fff8f6, #fff2ee);
        color: #d3281a;
        font-size: 13.5px;
        line-height: 1.45;
        font-weight: 700;
    }

    .pod-alert svg {
        width: 22px;
        height: 22px;
        stroke-width: 1.8;
    }

    .pod-help {
        margin-top: 16px;
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 16px 18px;
        background: #fff;
        border: 1px solid #d7e5fb;
        border-radius: 14px;
    }

    .pod-help__icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #eef5ff;
        color: #0f50d5;
    }

    .pod-help__icon svg {
        width: 32px;
        height: 32px;
    }

    .pod-help strong {
        display: block;
        color: #072c86;
        font-size: 16px;
        font-weight: 950;
    }

    .pod-help span {
        display: block;
        margin-top: 4px;
        color: #40516f;
        font-size: 13.5px;
        font-weight: 600;
    }

    .pod-help a {
        min-width: 148px;
        min-height: 42px;
        padding: 0 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border: 1.5px solid #1661f2;
        border-radius: 9px;
        color: #0d50d8;
        font-size: 13.5px;
        font-weight: 900;
        text-decoration: none;
        background: #fff;
    }

    .pod-help a svg {
        width: 18px;
        height: 18px;
    }

    @media (max-width: 980px) {
        .pod-hero { grid-template-columns: 200px 1fr; }
        .pod-section { grid-template-columns: 130px minmax(0, 1fr); padding-inline: 22px; }
        .pod-section__icon { width: 100px; height: 100px; }
        .pod-section__icon svg { width: 54px; height: 54px; }
    }

    @media (max-width: 760px) {
        .pod-page { padding-top: 10px; }
        .pod-wrap { width: min(calc(100% - 24px), 1280px); }
        .pod-hero { grid-template-columns: 1fr; }
        .pod-hero__visual { padding: 28px 20px 10px; }
        .pod-hero__visual::after { display: none; }
        .pod-hero__visual svg { width: 120px; height: 120px; }
        .pod-hero__copy { padding: 12px 24px 30px; text-align: center; }
        .pod-hero h1 { font-size: 42px; }
        .pod-hero p { font-size: 16px; }
        .pod-tabs { grid-template-columns: 1fr; border-radius: 14px; }
        .pod-tab { justify-content: flex-start; min-height: 58px; }
        .pod-tab { border-right: 0; border-bottom: 1px solid #e5ebf3; }
        .pod-tab:last-child { border-bottom: 0; }
        .pod-tab.is-active::after { left: 0; right: auto; top: 10px; bottom: 10px; width: 3px; height: auto; }
        .pod-section,
        .pod-section + .pod-section { grid-template-columns: 1fr; border-radius: 16px; padding: 24px 18px; }
        .pod-section__icon { margin-left: 0; }
        .pod-section h2 { font-size: 24px; }
        .pod-help { grid-template-columns: 50px 1fr; }
        .pod-help a { grid-column: 1 / -1; width: 100%; }
    }
</style>

<main class="pod-page">
    <div class="pod-wrap">
        <section class="pod-hero" aria-labelledby="podTitle">
            <div class="pod-hero__visual" aria-hidden="true">
                <i data-lucide="truck"></i>
            </div>
            <div class="pod-hero__copy">
                <span class="pod-kicker">Options de paiement et de livraison</span>
                <h1 id="podTitle">Paiement à la livraison</h1>
                <p>OVANIE vous propose des options de paiement flexibles et sécurisées ainsi que des modalités adaptées à la provenance des produits.</p>
            </div>
        </section>

        <nav class="pod-tabs" aria-label="Options de paiement et de livraison">
            <a href="#paiement-livraison" class="pod-tab is-active">
                <i data-lucide="truck"></i>
                <span>Paiement à la livraison</span>
            </a>
            <a href="#paiement-commande" class="pod-tab">
                <i data-lucide="credit-card"></i>
                <span>Paiement à la commande</span>
            </a>
            <a href="#produits-etranger" class="pod-tab">
                <i data-lucide="globe-2"></i>
                <span>Produits expédiés depuis l'étranger</span>
            </a>
        </nav>

        <div class="pod-content">
            <section id="paiement-livraison" class="pod-section">
                <div class="pod-section__icon" aria-hidden="true">
                    <i data-lucide="truck"></i>
                </div>
                <div class="pod-section__body">
                    <div class="pod-section__head">
                        <span class="pod-number">01</span>
                        <h2>Paiement à la livraison</h2>
                    </div>
                    <p class="pod-intro">Le paiement à la livraison vous permet de régler votre commande lorsque vous recevez vos produits.</p>
                    <h3>Conditions détaillées</h3>
                    <ul class="pod-list">
                        <li>Disponible uniquement pour les produits portant le badge <strong>« Paiement à la livraison »</strong>.</li>
                        <li>Le paiement s'effectue exclusivement lors de la remise de la commande.</li>
                        <li>Le client doit être présent à l'adresse indiquée pour réceptionner sa commande.</li>
                        <li>La commande peut être vérifiée, conformément à la politique du vendeur, avant le paiement lorsque ce service est proposé.</li>
                        <li>Certains produits (commandes sur mesure, produits importés, articles volumineux ou nécessitant un acompte) peuvent ne pas être éligibles au paiement à la livraison.</li>
                    </ul>
                    <div class="pod-alerts">
                        <div class="pod-alert">
                            <i data-lucide="triangle-alert"></i>
                            <span>Tout refus répété de commandes sans motif valable pourra entraîner la désactivation de l'option « Paiement à la livraison » sur votre compte.</span>
                        </div>
                        <div class="pod-alert">
                            <i data-lucide="triangle-alert"></i>
                            <span>Toute tentative de fraude ou comportement abusif entraînera l'annulation de la commande et la suspension du compte.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="paiement-commande" class="pod-section">
                <div class="pod-section__icon" aria-hidden="true">
                    <i data-lucide="credit-card"></i>
                </div>
                <div class="pod-section__body">
                    <div class="pod-section__head">
                        <span class="pod-number">02</span>
                        <h2>Paiement à la commande</h2>
                    </div>
                    <p class="pod-intro">Le paiement à la commande permet de confirmer et de traiter immédiatement votre achat. Le montant est sécurisé par OVANIE jusqu'à la validation de votre commande, conformément à notre politique de protection des acheteurs.</p>
                    <h3>Conditions du paiement à la commande</h3>
                    <ul class="pod-list">
                        <li>Le paiement doit être effectué intégralement avant l'expédition ou la mise à disposition de la commande.</li>
                        <li>La commande est prise en charge uniquement après confirmation du paiement.</li>
                        <li>Les paiements sont sécurisés via les moyens de paiement proposés par OVANIE.</li>
                        <li>En cas d'indisponibilité du produit ou d'annulation par le vendeur, le client est remboursé conformément à la politique de remboursement d'OVANIE.</li>
                        <li>Les délais de préparation et de livraison commencent à courir à compter de la validation du paiement.</li>
                    </ul>
                    <div class="pod-alerts">
                        <div class="pod-alert">
                            <i data-lucide="triangle-alert"></i>
                            <span>Toute tentative de fraude ou de paiement non autorisé entraînera l'annulation de la commande et pourra conduire à la suspension du compte concerné.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="produits-etranger" class="pod-section">
                <div class="pod-section__icon" aria-hidden="true">
                    <i data-lucide="globe-2"></i>
                </div>
                <div class="pod-section__body">
                    <div class="pod-section__head">
                        <span class="pod-number">03</span>
                        <h2>Produits expédiés depuis l'étranger</h2>
                    </div>
                    <p class="pod-intro">Les produits expédiés depuis l'étranger sont disponibles uniquement avec un paiement sécurisé à la commande. En raison des délais d'importation et des coûts logistiques internationaux, le paiement à la livraison n'est pas disponible pour ces articles.</p>
                    <h3>Conditions</h3>
                    <ul class="pod-list">
                        <li>Le paiement intégral est requis avant le traitement de la commande.</li>
                        <li>La préparation et l'expédition débutent après la confirmation du paiement.</li>
                        <li>Les délais de livraison peuvent varier selon le pays d'origine, le transporteur et les formalités douanières.</li>
                        <li>Les éventuels droits de douane, taxes ou frais d'importation sont indiqués, conformément aux conditions applicables, avant la validation de la commande lorsqu'ils sont à la charge du client.</li>
                        <li>En cas d'annulation par le vendeur ou d'impossibilité d'expédition, le client est remboursé conformément à la politique de remboursement d'OVANIE.</li>
                    </ul>
                    <div class="pod-alerts">
                        <div class="pod-alert">
                            <i data-lucide="triangle-alert"></i>
                            <span>En cas de retard dû aux formalités douanières ou aux transporteurs internationaux, OVANIE ne pourra être tenue responsable.</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="pod-help" aria-label="Assistance OVANIE">
            <span class="pod-help__icon" aria-hidden="true"><i data-lucide="headphones"></i></span>
            <div>
                <strong>Besoin d'aide ou d'informations complémentaires ?</strong>
                <span>Notre équipe est à votre disposition pour répondre à toutes vos questions.</span>
            </div>
            <a href="{{ route('contact.index') }}">Nous contacter <i data-lucide="arrow-right"></i></a>
        </section>
    </div>
</main>
@endsection
