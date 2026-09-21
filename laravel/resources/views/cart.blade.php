@extends('layouts.guest')

@section('title', 'Mon panier - OVANIE')
@section('meta_description', 'Votre panier OVANIE : vérifiez vos articles, ajustez les quantités et finalisez votre commande en toute sécurité.')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/cart.css') }}?v={{ file_exists(public_path('css/cart.css')) ? filemtime(public_path('css/cart.css')) : time() }}">
@endsection

@php
    $cart = $cart ?? null;
    $cartItems = $cart ? $cart->items->filter(fn($item) => !empty($item->product))->values() : collect();
    $itemCount = (int) $cartItems->sum('quantity');

    $subtotal = isset($subtotal)
        ? (float) $subtotal
        : (float) $cartItems->sum(fn($item) => (float) $item->price * (int) $item->quantity);

    $deliveryPending = $deliveryPending ?? true;
    $deliveryFee = isset($deliveryFee) && $deliveryFee !== null ? (float) $deliveryFee : null;
    $grandTotal = isset($grandTotal) ? (float) $grandTotal : $subtotal + (float) ($deliveryFee ?? 0);

    $homeUrl = Route::has('home') ? route('home') : url('/');
    $catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $favoritesUrl = Route::has('client.favorites') ? route('client.favorites') : '#';

    $paymentLogos = [
        ['name' => 'Orange Money', 'src' => asset('storage/logo paiement/orange money.png')],
        ['name' => 'MTN Money', 'src' => asset('storage/logo paiement/mtn money.png')],
        ['name' => 'Visa', 'src' => asset('storage/logo paiement/visa.png')],
        ['name' => 'Mastercard', 'src' => asset('storage/logo paiement/mastercard.png')],
        ['name' => 'Moov Money', 'src' => asset('storage/logo paiement/moov africa.png')],
    ];

    $formatMoney = fn($amount) => number_format((float) $amount, 0, ',', ' ') . ' FCFA';
@endphp

@section('content')
<main class="ov-cart-page">
    <div class="ov-cart-container">
        <nav class="ov-cart-breadcrumb" aria-label="Fil d’Ariane">
            <a href="{{ $homeUrl }}">Accueil</a>
            <i data-lucide="chevron-right"></i>
            <span>Mon panier</span>
        </nav>

        <header class="ov-cart-titlebar">
            <div class="ov-cart-titlebar__main">
                <h1>Mon panier <span id="ovCartTitleCount">({{ $itemCount }} article{{ $itemCount > 1 ? 's' : '' }})</span></h1>
                <p>Vérifiez vos articles, modifiez les quantités et finalisez votre commande.</p>
            </div>

            <a href="{{ $catalogUrl }}" class="ov-cart-continue">
                <i data-lucide="arrow-left"></i>
                Continuer mes achats
            </a>
        </header>

        <div id="cart-messages" class="ov-cart-messages"></div>

        @if(session('success'))
            <div class="ov-alert ov-alert--success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="ov-alert ov-alert--danger">{{ session('error') }}</div>
        @endif

        @if(!empty($messages))
            @foreach($messages as $message)
                <div class="ov-alert ov-alert--warning">{{ $message }}</div>
            @endforeach
        @endif

        @if($cartItems->isEmpty())
            <section class="ov-cart-empty">
                <div class="ov-cart-empty__icon">
                    <i data-lucide="shopping-cart"></i>
                </div>
                <h2>Votre panier est vide</h2>
                <p>Ajoutez vos matériaux, équipements ou produits BTP afin de préparer votre commande.</p>
                <a href="{{ $catalogUrl }}" class="ov-cart-empty__btn">Découvrir le catalogue</a>
            </section>
        @else
            <section class="ov-cart-layout">
                <div class="ov-cart-left">
                    <section class="ov-cart-card ov-cart-items-card">
                        <div class="ov-cart-toolbar">
                            <label class="ov-checkline">
                                <input type="checkbox" id="selectAllCheckbox" checked>
                                <span>Tout sélectionner <strong>({{ $itemCount }})</strong></span>
                            </label>

                            <a href="{{ $favoritesUrl }}" class="ov-toolbar-btn ov-toolbar-btn--soft">
                                <i data-lucide="heart"></i>
                                Voir mes favoris
                            </a>

                            <button type="button" class="ov-toolbar-btn ov-toolbar-btn--danger" id="deleteSelectedBtn">
                                <i data-lucide="trash-2"></i>
                                Supprimer la sélection <span>({{ $itemCount }})</span>
                            </button>
                        </div>

                        <div class="ov-cart-table-head" aria-hidden="true">
                            <span>Image</span>
                            <span>Produit</span>
                            <span>Quantité</span>
                            <span>Prix unitaire</span>
                            <span>Sous-total</span>
                            <span>Actions</span>
                        </div>

                        <div class="ov-cart-items" id="cartItemsList">
                            @foreach($cartItems as $item)
                                @php
                                    $product = $item->product;
                                    $price = (float) ($item->price ?: ($product->final_price ?? $product->price ?? 0));
                                    $quantity = max((int) $item->quantity, 1);
                                    $subtotalLine = $price * $quantity;
                                    $stock = max((int) ($product->stock ?? 0), 0);
                                    $unit = $product->display_unit ?? $product->unit_label ?? $product->unit ?? 'pièce';
                                    $productUrl = Route::has('product.show') ? route('product.show', $product->slug) : '#';
                                    $imageUrl = $product->mainImageUrl ?? asset('storage/products/placeholder.png');
                                @endphp

                                <article class="ov-cart-item"
                                         data-row
                                         data-item-id="{{ $item->id }}"
                                         data-product-id="{{ $product->id }}"
                                         data-price="{{ $price }}"
                                         data-stock="{{ $stock }}"
                                         data-quantity="{{ $quantity }}">
                                    <div class="ov-cart-item__media">
                                        <div class="ov-cart-item__check">
                                            <input type="checkbox" class="cart-item-checkbox" checked aria-label="Sélectionner {{ $product->name }}">
                                        </div>

                                        <a href="{{ $productUrl }}" class="ov-cart-item__image">
                                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy">
                                        </a>
                                    </div>

                                    <div class="ov-cart-item__details">
                                        <a href="{{ $productUrl }}" class="ov-cart-item__name">{{ $product->name }}</a>

                                        @if($stock <= 0)
                                            <div class="ov-cart-item__meta">
                                                <span class="ov-stock ov-stock--out"><i data-lucide="circle-alert"></i>Rupture de stock</span>
                                            </div>
                                        @endif

                                        <div class="ov-cart-item__price-inline">{{ $formatMoney($price) }} / {{ $unit }}</div>
                                    </div>

                                    <div class="ov-cart-item__qty">
                                        <div class="ov-qty-stepper">
                                            <button type="button" data-qty-minus aria-label="Diminuer la quantité">−</button>
                                            <input type="number"
                                                   class="quantity-input"
                                                   value="{{ $quantity }}"
                                                   min="1"
                                                   max="{{ max($stock, $quantity, 1) }}"
                                                   data-product-id="{{ $product->id }}"
                                                   data-stock="{{ $stock }}">
                                            <button type="button" data-qty-plus aria-label="Augmenter la quantité">+</button>
                                        </div>
                                    </div>

                                    <div class="ov-cart-item__unitprice">
                                        <strong>{{ $formatMoney($price) }}</strong>
                                    </div>

                                    <div class="ov-cart-item__total">
                                        <strong class="line-total">{{ $formatMoney($subtotalLine) }}</strong>
                                    </div>

                                    <div class="ov-cart-item__actions">
                                        <form method="POST" action="{{ route('cart.remove', $product->id) }}" class="ov-remove-form">
                                            @csrf
                                            <button type="submit" class="ov-item-link ov-item-link--danger" data-remove-item>
                                                <i data-lucide="trash-2"></i>
                                                Supprimer
                                            </button>
                                        </form>

                                        <a href="{{ $favoritesUrl }}" class="ov-item-link">
                                            <i data-lucide="heart"></i>
                                            Déplacer vers mes favoris
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>

                <aside class="ov-cart-summary" aria-label="Sous-total du panier">
                    <div class="ov-cart-card ov-summary-card">
                        <div class="ov-summary-heading">
                            <h2>Sous-total <small id="summaryItemCount">({{ $itemCount }} article{{ $itemCount > 1 ? 's' : '' }})</small></h2>
                            <strong id="subtotal">{{ $formatMoney($subtotal) }}</strong>
                        </div>

                        <form method="POST" action="{{ route('checkout.selection') }}" id="checkoutSelectionForm" class="ov-checkout-form">
                            @csrf
                            <div id="checkoutSelectionInputs"></div>
                            <button type="submit" class="ov-checkout-btn checkout-btn">
                            <i class="fa-solid fa-lock" style="margin-right:8px;"></i>
                            <span class="checkout-btn-text-desktop">PASSER LA COMMANDE</span>
                            <span class="checkout-btn-text-mobile">PASSER LA COMMANDE</span>
                        </button>
                        </form>

                        <div class="ov-secure-line">
                            <span></span>
                            <p><i data-lucide="lock-keyhole"></i>Transactions sécurisées sur Ovanie</p>
                            <span></span>
                        </div>

                        <div class="ov-payments" aria-label="Moyens de paiement acceptés">
                            @foreach($paymentLogos as $logo)
                                <span><img src="{{ $logo['src'] }}" alt="{{ $logo['name'] }}" loading="lazy"></span>
                            @endforeach
                        </div>
                    </div>

                    <div class="ov-cart-card ov-summary-note-card">
                        <div class="ov-summary-guarantee">
                            <h3>Garantie Acheteur OVANIE</h3>
                            <ul>
                                <li>
                                    <i data-lucide="shield-check"></i>
                                    <div>
                                        <strong>Paiement sécurisé</strong>
                                        <span>Vos paiements sont protégés et cryptés</span>
                                    </div>
                                </li>
                                <li>
                                    <i data-lucide="badge-check"></i>
                                    <div>
                                        <strong>Produit vérifié</strong>
                                        <span>Produits authentiques et contrôlés</span>
                                    </div>
                                </li>
                                <li>
                                    <i data-lucide="headphones"></i>
                                    <div>
                                        <strong>Assistance 7j/7</strong>
                                        <span>Notre équipe reste à votre écoute</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="ov-cart-trust-strip">
                <div>
                    <i data-lucide="shield-check"></i>
                    <p><strong>Paiement 100% sécurisé</strong><span>Vos transactions sont protégées par un cryptage de pointe</span></p>
                </div>
                <div>
                    <i data-lucide="truck"></i>
                    <p><strong>Livraison rapide</strong><span>Livraison à Abidjan et dans toute la Côte d’Ivoire</span></p>
                </div>
                <div>
                    <i data-lucide="badge-check"></i>
                    <p><strong>Partenaires vérifiés</strong><span>Fournisseurs certifiés et de confiance</span></p>
                </div>
                <div>
                    <i data-lucide="rotate-ccw"></i>
                    <p><strong>Retour facile</strong><span>Retour sous 7 jours si éligible</span></p>
                </div>
                <div>
                    <i data-lucide="headphones"></i>
                    <p><strong>Support 7j/7</strong><span>Notre équipe vous répond à tout moment</span></p>
                </div>
            </section>
        @endif
    </div>


</main>
@endsection

@section('scripts')
    <script src="{{ asset('js/cart.js') }}?v={{ file_exists(public_path('js/cart.js')) ? filemtime(public_path('js/cart.js')) : time() }}" defer></script>
@endsection
