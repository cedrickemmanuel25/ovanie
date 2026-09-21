@extends('layouts.vendor')

@section('title', 'Ajouter un produit | OVANIE Seller Central')

@section('styles')
<style>
    /*
    |--------------------------------------------------------------------------
    | OVANIE Seller Central — Ajouter un produit
    |--------------------------------------------------------------------------
    | Cette page utilise volontairement le layout vendeur commun.
    | Aucun style de sidebar ou de topbar n'est redéfini ici.
    */

    .ov-content {
        padding: 0 !important;
    }

    .ov-product-start-page {
        min-height: calc(100vh - var(--ov-topbar-height, 66px));
        width: 100%;
        padding: 26px 64px 24px;
        color: #0b234d;
        background:
            radial-gradient(circle at 66% 38%, rgba(37, 99, 235, .025), transparent 31%),
            linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .ov-product-start-inner {
        width: 100%;
        max-width: 1190px;
        margin: 0 auto;
    }

    .ov-product-breadcrumb {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 0 0 18px;
        color: #18325e;
        font-size: 14px;
        line-height: 1.2;
        font-weight: 600;
    }

    .ov-product-breadcrumb a {
        color: #0562e7;
        text-decoration: none;
        transition: color .18s ease;
    }

    .ov-product-breadcrumb a:hover {
        color: #034bb6;
    }

    .ov-product-breadcrumb svg {
        width: 15px;
        height: 15px;
        color: #8fa0b8;
        stroke-width: 2.4;
    }

    .ov-product-heading {
        margin-bottom: 22px;
    }

    .ov-product-title {
        margin: 0;
        color: #0a234d;
        font-size: clamp(32px, 2.5vw, 40px);
        line-height: 1.04;
        font-weight: 800;
        letter-spacing: -.045em;
    }

    .ov-product-subtitle {
        margin: 9px 0 0;
        color: #566b91;
        font-size: 14px;
        line-height: 1.55;
        font-weight: 500;
    }

    .ov-product-choice-wrap {
        display: flex;
        justify-content: center;
        width: 100%;
    }

    .ov-product-choice-card {
        width: min(100%, 540px);
        min-height: 0;
        padding: 24px 36px 28px;
        border: 1px solid #dfe6ef;
        border-radius: 12px;
        background: #ffffff;
        box-shadow:
            0 18px 46px rgba(14, 35, 72, .075),
            0 2px 8px rgba(14, 35, 72, .035);
    }

    .ov-product-choice-icon {
        width: 112px;
        height: 112px;
        margin: 0 auto 12px;
        display: grid;
        place-items: center;
        border-radius: 999px;
        background:
            radial-gradient(circle at 35% 30%, rgba(255,255,255,.9), rgba(255,255,255,0) 40%),
            linear-gradient(145deg, #eff5ff 0%, #e6efff 100%);
    }

    .ov-product-choice-icon svg {
        width: 70px;
        height: 70px;
        overflow: visible;
    }

    .ov-product-choice-title {
        margin: 0;
        text-align: center;
        color: #0b2a5a;
        font-size: 30px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.035em;
    }

    .ov-product-choice-description {
        max-width: 410px;
        margin: 8px auto 16px;
        text-align: center;
        color: #526b94;
        font-size: 14px;
        line-height: 1.55;
        font-weight: 500;
    }

    .ov-product-benefits {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid #e4e9f1;
    }

    .ov-product-benefit {
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 14px;
        border-bottom: 1px solid #e4e9f1;
        color: #17335f;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
    }

    .ov-product-check {
        width: 19px;
        height: 19px;
        flex: 0 0 19px;
        display: grid;
        place-items: center;
        border-radius: 999px;
        color: #ffffff;
        background: #19b978;
        box-shadow: 0 2px 5px rgba(25, 185, 120, .22);
    }

    .ov-product-check svg {
        width: 12px;
        height: 12px;
        stroke-width: 3.2;
    }

    .ov-product-start-btn {
        width: 100%;
        min-height: 56px;
        margin-top: 18px;
        padding: 0 28px;
        border: 0;
        border-radius: 6px;
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        color: #ffffff;
        background: linear-gradient(180deg, #ff7b05 0%, #ff5900 100%);
        box-shadow: 0 12px 24px rgba(255, 98, 0, .18);
        text-decoration: none;
        font-size: 18px;
        line-height: 1;
        font-weight: 800;
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }

    .ov-product-start-btn span {
        grid-column: 2;
        white-space: nowrap;
    }

    .ov-product-start-btn svg {
        grid-column: 3;
        justify-self: end;
        width: 26px;
        height: 26px;
        stroke-width: 1.65;
    }

    .ov-product-start-btn:hover {
        color: #ffffff;
        transform: translateY(-1px);
        filter: saturate(1.04);
        box-shadow: 0 16px 30px rgba(255, 98, 0, .24);
    }

    .ov-product-start-btn:focus-visible {
        outline: 3px solid rgba(37, 99, 235, .2);
        outline-offset: 3px;
    }

    /* Ajustement desktop : tout le contenu reste visible sans défilement */
    @media (min-width: 769px) and (min-height: 700px) {
        .ov-product-start-page {
            height: calc(100vh - var(--ov-topbar-height, 58px));
            min-height: 0;
            overflow: hidden;
        }

        .ov-product-start-inner {
            height: 100%;
        }
    }

    @media (max-width: 1180px) {
        .ov-product-start-page {
            padding: 24px 38px 22px;
        }
    }

    @media (max-width: 768px) {
        .ov-product-start-page {
            min-height: calc(100vh - var(--ov-topbar-height, 58px));
            height: auto;
            overflow: visible;
            padding: 28px 18px 44px;
        }

        .ov-product-heading {
            margin-bottom: 28px;
        }

        .ov-product-title {
            font-size: 34px;
        }

        .ov-product-subtitle {
            font-size: 14px;
        }

        .ov-product-choice-card {
            min-height: auto;
            padding: 30px 24px 30px;
        }

        .ov-product-choice-icon {
            width: 128px;
            height: 128px;
        }

        .ov-product-choice-icon svg {
            width: 78px;
            height: 78px;
        }

        .ov-product-choice-title {
            font-size: 30px;
        }

        .ov-product-choice-description {
            font-size: 14px;
        }

        .ov-product-benefit {
            min-height: 58px;
            padding: 0 8px;
            gap: 14px;
            font-size: 14px;
        }

        .ov-product-start-btn {
            min-height: 60px;
            font-size: 18px;
        }
    }

    @media (max-width: 480px) {
        .ov-product-breadcrumb {
            margin-bottom: 21px;
            font-size: 14px;
        }

        .ov-product-title {
            font-size: 30px;
        }

        .ov-product-choice-card {
            padding-inline: 18px;
        }

        .ov-product-choice-title {
            font-size: 28px;
        }

        .ov-product-choice-icon {
            width: 112px;
            height: 112px;
        }

        .ov-product-choice-icon svg {
            width: 68px;
            height: 68px;
        }
    }
</style>
@endsection

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $singleAddUrl = Route::has('vendor.add_product')
        ? route('vendor.add_product', ['type' => 'single'])
        : (Route::has('daniel.add_product')
            ? route('daniel.add_product', ['type' => 'single'])
            : url('/vendeur/products/create?type=single'));

    $productsUrl = Route::has('vendor.products')
        ? route('vendor.products')
        : (Route::has('daniel.products')
            ? route('daniel.products')
            : url('/vendeur/products'));
@endphp

<section class="ov-product-start-page">
    <div class="ov-product-start-inner">
        <nav class="ov-product-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ $productsUrl }}">Produits</a>
            <i data-lucide="chevron-right" aria-hidden="true"></i>
            <span>Ajouter des produits</span>
        </nav>

        <header class="ov-product-heading">
            <h1 class="ov-product-title">Ajouter un produit</h1>
            <p class="ov-product-subtitle">
                Renseignez les informations de votre produit pour le publier sur OVANIE.
            </p>
        </header>

        <div class="ov-product-choice-wrap">
            <article class="ov-product-choice-card">
                <div class="ov-product-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 104 104" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 31.5L51.5 15L85 31.5L51.5 48L18 31.5Z"
                              stroke="#0B63E6" stroke-width="3.8" stroke-linejoin="round"/>
                        <path d="M18 31.5V69L51.5 87V48L18 31.5Z"
                              stroke="#0B63E6" stroke-width="3.8" stroke-linejoin="round"/>
                        <path d="M85 31.5V55.5"
                              stroke="#0B63E6" stroke-width="3.8" stroke-linecap="round"/>
                        <path d="M34 23.5L67.7 40"
                              stroke="#0B63E6" stroke-width="3.8" stroke-linecap="round"/>
                        <path d="M67.5 40V52"
                              stroke="#0B63E6" stroke-width="3.8" stroke-linecap="round"/>

                        <g transform="translate(60 57) rotate(-1)">
                            <path d="M5 28L9 16L27 0L37 10L19 27L5 28Z"
                                  fill="#FF6B00" stroke="#0B63E6" stroke-width="3.3" stroke-linejoin="round"/>
                            <path d="M9 16L19 27"
                                  stroke="#0B63E6" stroke-width="3.3"/>
                            <path d="M27 0L37 10"
                                  stroke="#0B63E6" stroke-width="3.3"/>
                            <path d="M5 28L11.8 25.6"
                                  stroke="#0B63E6" stroke-width="3.3" stroke-linecap="round"/>
                            <path d="M13.5 13L24 23"
                                  stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
                        </g>
                    </svg>
                </div>

                <h2 class="ov-product-choice-title">Ajout manuel</h2>

                <p class="ov-product-choice-description">
                    Mode recommandé pour un contrôle complet<br>
                    de votre fiche produit.
                </p>

                <ul class="ov-product-benefits">
                    <li class="ov-product-benefit">
                        <span class="ov-product-check" aria-hidden="true">
                            <i data-lucide="check"></i>
                        </span>
                        <span>Saisie guidée étape par étape</span>
                    </li>

                    <li class="ov-product-benefit">
                        <span class="ov-product-check" aria-hidden="true">
                            <i data-lucide="check"></i>
                        </span>
                        <span>Photos, prix et stock</span>
                    </li>

                    <li class="ov-product-benefit">
                        <span class="ov-product-check" aria-hidden="true">
                            <i data-lucide="check"></i>
                        </span>
                        <span>Publication rapide</span>
                    </li>

                    <li class="ov-product-benefit">
                        <span class="ov-product-check" aria-hidden="true">
                            <i data-lucide="check"></i>
                        </span>
                        <span>Catégories et attributs techniques du BTP</span>
                    </li>
                </ul>

                <a href="{{ $singleAddUrl }}" class="ov-product-start-btn">
                    <span>Commencer</span>
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                </a>
            </article>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
</script>
@endsection
