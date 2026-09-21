@php
    $deliveryInfoUrl = Route::has('payment.delivery.info') ? route('payment.delivery.info') : url('/paiement-a-la-livraison');

    $product = $product ?? null;
    $size = $size ?? 'card';

    $stock = (int) data_get($product, 'stock', 0);
    $deliveryMode = \Illuminate\Support\Str::of((string) data_get($product, 'delivery_mode', ''))->lower()->ascii()->toString();
    $saleType = \Illuminate\Support\Str::of((string) data_get($product, 'sale_type', ''))->lower()->ascii()->toString();
    $type = \Illuminate\Support\Str::of((string) data_get($product, 'type', ''))->lower()->ascii()->toString();
    $productState = \Illuminate\Support\Str::of((string) data_get($product, 'product_state', ''))->lower()->ascii()->toString();
    $originCountry = \Illuminate\Support\Str::of((string) data_get($product, 'origin_country', ''))->lower()->ascii()->toString();

    $explicitEligibility = data_get($product, 'cash_on_delivery_eligible')
        ?? data_get($product, 'payment_on_delivery_eligible')
        ?? data_get($product, 'pay_on_delivery')
        ?? data_get($product, 'allow_cash_on_delivery')
        ?? data_get($product, 'cod_enabled');

    $isImported = str_contains($deliveryMode, 'import')
        || str_contains($deliveryMode, 'international')
        || str_contains($saleType, 'import')
        || str_contains($type, 'import')
        || ($originCountry !== ''
            && ! str_contains($originCountry, 'cote')
            && ! str_contains($originCountry, 'ivoire')
            && ! str_contains($originCountry, 'ci')
            && ! str_contains($originCountry, 'abidjan'));

    $requiresDeposit = str_contains($productState, 'sur-mesure')
        || str_contains($productState, 'sur mesure')
        || str_contains($productState, 'custom')
        || str_contains($productState, 'commande')
        || str_contains($saleType, 'sur-mesure')
        || str_contains($saleType, 'sur mesure');

    $isEligible = $explicitEligibility !== null
        ? filter_var($explicitEligibility, FILTER_VALIDATE_BOOLEAN)
        : ($stock > 0 && ! $isImported && ! $requiresDeposit);
@endphp

@if($isEligible)
    @once
        <style>
            .ovanie-pod-badge-wrap {
                position: relative;
                display: inline-flex;
                align-items: center;
                width: fit-content;
                max-width: 100%;
                z-index: 12;
            }

            .ovanie-pod-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                min-height: 28px;
                padding: 6px 10px;
                border-radius: 999px;
                border: 1px solid rgba(255, 106, 0, .26);
                background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
                color: #0b1b35;
                font-size: 11px;
                line-height: 1;
                font-weight: 950;
                box-shadow: 0 10px 22px rgba(255, 106, 0, .10);
                white-space: nowrap;
            }

            .ovanie-pod-badge--large {
                min-height: 36px;
                padding: 8px 12px;
                font-size: 13px;
            }

            .ovanie-pod-badge svg,
            .ovanie-pod-badge i {
                width: 15px;
                height: 15px;
                color: #ff6a00;
                stroke-width: 2.8;
                flex: 0 0 auto;
            }

            .ovanie-pod-star {
                appearance: none;
                border: 0;
                background: #ff6a00;
                color: #fff;
                width: 18px;
                height: 18px;
                border-radius: 999px;
                display: inline-grid;
                place-items: center;
                cursor: pointer;
                font-size: 12px;
                line-height: 1;
                font-weight: 950;
                padding: 0;
                box-shadow: 0 7px 14px rgba(255, 106, 0, .28);
            }

            .ovanie-pod-tooltip {
                position: absolute;
                left: 0;
                top: calc(100% + 10px);
                width: min(370px, calc(100vw - 32px));
                padding: 14px;
                border-radius: 18px;
                background: #ffffff;
                border: 1px solid rgba(226, 232, 240, .96);
                box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
                color: #0b1b35;
                display: none;
                white-space: normal;
                z-index: 9999;
            }

            .ovanie-pod-tooltip::before {
                content: "";
                position: absolute;
                left: 24px;
                top: -7px;
                width: 14px;
                height: 14px;
                background: #ffffff;
                border-left: 1px solid rgba(226, 232, 240, .96);
                border-top: 1px solid rgba(226, 232, 240, .96);
                transform: rotate(45deg);
            }

            .ovanie-pod-badge-wrap.is-open .ovanie-pod-tooltip {
                display: block;
            }

            .ovanie-pod-tooltip h4 {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0 0 8px;
                color: #ff5a00;
                font-size: 14px;
                line-height: 1.2;
                font-weight: 950;
            }

            .ovanie-pod-tooltip p {
                margin: 0 0 9px;
                color: #334155;
                font-size: 12px;
                line-height: 1.5;
                font-weight: 700;
            }

            .ovanie-pod-tooltip .ovanie-pod-note {
                color: #0b1b35;
                font-weight: 950;
            }

            .ovanie-pod-more {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 30px;
                padding: 7px 10px;
                border-radius: 999px;
                background: #0b1b35;
                color: #ffffff !important;
                text-decoration: none;
                font-size: 11px;
                font-weight: 950;
                box-shadow: 0 10px 22px rgba(11, 27, 53, .18);
            }

            .hm-card .ovanie-pod-badge-wrap {
                margin: 8px 0 0;
            }

            .hm-card .ovanie-pod-tooltip {
                left: 50%;
                transform: translateX(-50%);
            }

            .hm-card .ovanie-pod-tooltip::before {
                left: calc(50% - 7px);
            }

            .ov-product-buy-card .ovanie-pod-badge-wrap {
                margin: 10px 0 8px;
            }

            @media (max-width: 640px) {
                .ovanie-pod-tooltip {
                    width: min(320px, calc(100vw - 24px));
                    left: 0;
                    transform: none;
                }
            }
        </style>

        <script>
            document.addEventListener('click', function (event) {
                const toggle = event.target.closest('[data-pod-toggle]');

                document.querySelectorAll('.ovanie-pod-badge-wrap.is-open').forEach(function (node) {
                    if (!toggle || !node.contains(toggle)) {
                        node.classList.remove('is-open');
                    }
                });

                if (toggle) {
                    event.preventDefault();
                    event.stopPropagation();
                    const root = toggle.closest('.ovanie-pod-badge-wrap');
                    if (root) {
                        root.classList.toggle('is-open');
                    }
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('.ovanie-pod-badge-wrap.is-open').forEach(function (node) {
                        node.classList.remove('is-open');
                    });
                }
            });
        </script>
    @endonce

    <span class="ovanie-pod-badge-wrap">
        <span class="ovanie-pod-badge {{ $size === 'large' ? 'ovanie-pod-badge--large' : '' }}">
            <i data-lucide="badge-check"></i>
            <span>Paiement à la livraison</span><button type="button" class="ovanie-pod-star" data-pod-toggle aria-label="Conditions du paiement à la livraison">*</button>
        </span>

        <span class="ovanie-pod-tooltip" role="tooltip">
            <h4>🚚 Paiement à la livraison</h4>
            <p>Le paiement à la livraison est disponible uniquement sur les produits éligibles et dans les zones couvertes par nos partenaires de livraison. Le client est tenu de régler l’intégralité du montant de sa commande au moment de la réception. Tout refus injustifié de la commande après expédition peut entraîner la suspension temporaire ou définitive de l’accès à ce mode de paiement</p>
            <p class="ovanie-pod-note">🚚 Paiement à la livraison disponible sur les produits éligibles. Conditions applicables.</p>
            <a href="{{ $deliveryInfoUrl }}" class="ovanie-pod-more">Plus d’infos</a>
        </span>
    </span>
@endif
