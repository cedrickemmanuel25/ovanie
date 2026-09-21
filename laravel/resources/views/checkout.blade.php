<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Commande sécurisée - OVANIE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/checkout-v2.css') }}">
</head>
<body>
@php
    $subtotalAmount = (float) ($subtotal ?? 0);
    $displaySubtotal = number_format($subtotalAmount, 0, ',', ' ') . ' FCFA';
    $deliveryTotalAmount = 0.0;
    $deliveryIsKnown = false;
    $addressBookDefault = $defaultSavedAddress ?? null;
    $addressBookDefaultSnapshot = $defaultSavedAddressSnapshot ?? null;
    $defaultName = old('full_name', $addressBookDefaultSnapshot['recipient_name'] ?? auth()->user()->name ?? '');
    $defaultPhone = old('whatsapp_phone', $addressBookDefaultSnapshot['phone'] ?? auth()->user()->whatsapp_phone ?? auth()->user()->phone ?? '');
    $defaultZone = old('delivery_zone', $addressBookDefaultSnapshot['delivery_zone'] ?? 'abidjan');
    $defaultCommune = old('delivery_commune', $addressBookDefaultSnapshot['delivery_commune'] ?? '');
    $defaultQuartier = old('delivery_quartier', $addressBookDefaultSnapshot['delivery_quartier'] ?? '');
    $defaultCity = old('delivery_city', $addressBookDefaultSnapshot['delivery_city'] ?? '');
    $defaultAddress = old('address', $addressBookDefaultSnapshot['address'] ?? '');
    $defaultLatitude = old('delivery_latitude', $addressBookDefaultSnapshot['delivery_latitude'] ?? '');
    $defaultLongitude = old('delivery_longitude', $addressBookDefaultSnapshot['delivery_longitude'] ?? '');
    $defaultSavedAddressId = old('saved_address_id', $addressBookDefaultSnapshot['saved_address_id'] ?? '');
    $loyaltyPointsAvailable = (int) ($loyaltyPoints ?? 0);
    $loyaltyPointsMaximum = (int) ($maxLoyaltyPoints ?? 0);
    $loyaltyPointUnitValue = (int) ($loyaltyPointValue ?? 10);
    $checkoutPaymentMethods = collect($paymentOptions['methods'] ?? []);
    $onlinePaymentOption = $checkoutPaymentMethods->firstWhere('code', 'paydunya');
    $cashOnDeliveryOption = $checkoutPaymentMethods->firstWhere('code', 'cash_on_delivery');
    $cashOnDeliveryRequired = (bool) ($paymentOptions['cash_on_delivery_required'] ?? false);
    $selectedPaymentMethod = old('payment_method', $cashOnDeliveryRequired ? 'cash_on_delivery' : '');
@endphp

<header class="checkout-header">
    <a href="{{ route('home') }}" class="brand" aria-label="Accueil OVANIE">
        <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
    </a>

    <div class="checkout-title-card" aria-label="Paiement sécurisé">
        <div class="checkout-title-copy">
            <strong>Paiement sécurisé</strong>
        </div>
    </div>

    <div class="checkout-header-spacer" aria-hidden="true"></div>
</header>

<main class="checkout-page">
    <form action="{{ route('checkout.store') }}" method="POST" enctype="multipart/form-data" id="checkoutForm">
        @csrf
        <input type="hidden" name="commission_amount" value="{{ $commission ?? 0 }}">
        <input type="hidden" name="phone" id="checkoutPhoneInput" value="{{ old('phone', $defaultPhone) }}">
        <input type="hidden" name="delivery_latitude" id="deliveryLatitude" value="{{ $defaultLatitude }}">
        <input type="hidden" name="delivery_longitude" id="deliveryLongitude" value="{{ $defaultLongitude }}">
        <input type="hidden" name="delivery_geo_accuracy" id="deliveryGeoAccuracy" value="{{ old('delivery_geo_accuracy') }}">
        <input type="hidden" name="delivery_geo_source" id="deliveryGeoSource" value="{{ old('delivery_geo_source') }}">
        <input type="hidden" name="saved_address_id" id="savedAddressId" value="{{ $defaultSavedAddressId }}">
        @foreach(session('checkout_cart_item_ids', []) as $checkoutCartItemId)
            <input type="hidden" name="checkout_cart_item_ids[]" value="{{ (int) $checkoutCartItemId }}">
        @endforeach

        {{-- La stratégie logistique reste entièrement résolue côté serveur. --}}

        <div class="checkout-layout">
            <div class="checkout-main">
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <section class="checkout-section address-card-section" id="addressStep">
                    <div class="section-title-row no-margin">
                        <span class="section-number">1</span>
                        <div class="section-copy">
                            <h1>Adresse de livraison</h1>
                            <p>Ajoutez l’adresse où votre commande doit être livrée.</p>
                        </div>
                    </div>

                    <div class="address-display-card">
                        <div>
                            <strong id="addressPreviewTitle">{{ $defaultCommune ?: 'Adresse non renseignée' }}</strong>
                            <span id="addressPreviewText">
                                @if($defaultAddress || $defaultQuartier)
                                    {{ trim(($defaultQuartier ? $defaultQuartier . ', ' : '') . $defaultAddress) }}
                                @else
                                    Aucune adresse détaillée renseignée.
                                @endif
                            </span>
                            <small id="addressPreviewPhone">WhatsApp : {{ $defaultPhone ?: 'à renseigner' }}</small>
                        </div>
                        <button type="button" class="btn-secondary btn-address" data-open-address-modal>
                            Adresse de livraison
                        </button>
                    </div>
                </section>

                <section class="checkout-section payment-section is-hidden" id="paymentSection">
                    <div class="payment-step-shell">
                        <div class="section-title-row compact-title">
                        <span class="section-number muted-number">2</span>
                        <div class="section-copy">
                            <h2>Mode de paiement</h2>
                            <p>Choisissez comment finaliser votre commande.</p>
                        </div>
                    </div>

                        <div class="payment-step-content">
                            <div class="payment-list-simple">
                        @if($onlinePaymentOption)
                        <label class="payment-choice {{ $selectedPaymentMethod === 'paydunya' ? 'active' : '' }}">
                            <input
                                type="radio"
                                name="payment_method"
                                value="paydunya"
                                {{ ($onlinePaymentOption['enabled'] ?? false) ? '' : 'disabled' }}
                                @checked($selectedPaymentMethod === 'paydunya')
                                required
                            >
                            <span>
                                <strong>{{ $onlinePaymentOption['label'] ?? 'Paiement en ligne' }}</strong>
                                <small>{{ $onlinePaymentOption['description'] ?? 'Wave, Orange Money, MTN MoMo, Moov Money ou carte bancaire' }}</small>
                            </span>
                        </label>
                        @endif

                        @if($cashOnDeliveryOption)
                        <label class="payment-choice {{ $selectedPaymentMethod === 'cash_on_delivery' ? 'active' : '' }}">
                            <input
                                type="radio"
                                name="payment_method"
                                value="cash_on_delivery"
                                {{ ($cashOnDeliveryOption['enabled'] ?? false) ? '' : 'disabled' }}
                                @checked($selectedPaymentMethod === 'cash_on_delivery')
                                required
                            >
                            <span>
                                <strong>{{ $cashOnDeliveryOption['label'] ?? 'Paiement à la livraison' }}</strong>
                                <small>{{ $cashOnDeliveryRequired ? 'Ce mode de paiement est requis pour cette commande.' : ($cashOnDeliveryOption['description'] ?? 'Réglez votre commande au moment de la livraison') }}</small>
                            </span>
                        </label>
                        @endif
                    </div>

                            <p id="paymentAvailabilityNote" class="payment-inline-note" {{ ($onlinePaymentOption && ! ($onlinePaymentOption['enabled'] ?? false) && ! empty($onlinePaymentOption['reason'])) ? '' : 'hidden' }}>
                                {{ $onlinePaymentOption['reason'] ?? '' }}
                            </p>

                            @if($loyaltyPointsAvailable > 0)
                        <div style="margin-top:16px;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;">
                            <label for="loyaltyPointsInput" style="display:block;font-weight:700;color:#0f172a;">
                                Utiliser mes points fidélité
                                <small style="display:block;font-weight:500;color:#64748b;margin-top:4px;">
                                    Solde : {{ number_format($loyaltyPointsAvailable, 0, ',', ' ') }} points · Maximum sur cette commande : {{ number_format($loyaltyPointsMaximum, 0, ',', ' ') }} points
                                </small>
                            </label>
                            <input
                                id="loyaltyPointsInput"
                                type="number"
                                name="loyalty_points"
                                value="{{ old('loyalty_points', 0) }}"
                                min="0"
                                max="{{ $loyaltyPointsMaximum }}"
                                step="1"
                                style="width:100%;margin-top:10px;border:1px solid #cbd5e1;border-radius:10px;padding:12px;"
                            >
                            <small style="display:block;margin-top:6px;color:#64748b;">1 point = {{ number_format($loyaltyPointUnitValue, 0, ',', ' ') }} FCFA de réduction.</small>
                        </div>
                            @endif

                        </div>
                    </div>

                </section>
            </div>

            <aside class="checkout-summary">
                <div class="summary-box"
                    data-subtotal="{{ $subtotalAmount }}"
                    data-delivery-total="{{ $deliveryTotalAmount }}"
                    data-delivery-known="{{ $deliveryIsKnown ? '1' : '0' }}">
                    <button type="button" class="btn-primary" id="summaryActionBtn">Ajouter une adresse</button>
                    <div class="summary-separator"></div>
                    <div class="summary-row"><span>Total produits</span><strong>{{ $displaySubtotal }}</strong></div>
                    <div class="summary-row"><span>Livraison</span><strong class="pending-text" id="summaryDeliveryAmount"></strong></div>
                    
                    @if($loyaltyPointsAvailable > 0)
                        <div class="summary-row" id="summaryLoyaltyRow" hidden><span>Réduction fidélité</span><strong id="summaryLoyaltyAmount">0 FCFA</strong></div>
                    @endif
                    <div class="summary-total"><span id="summaryTotalLabel">Total provisoire</span><strong class="is-pending" id="summaryTotalAmount">{{ $displaySubtotal }}</strong></div>
                    <a href="{{ route('cart.index') }}" class="cart-link">Modifier mon panier</a>
                </div>
            </aside>
        </div>

        <div class="address-modal" id="addressModal" aria-hidden="true">
            <div class="address-modal-backdrop" data-close-address-modal></div>
            <div class="address-modal-panel" role="dialog" aria-modal="true" aria-labelledby="addressModalTitle">
                <div class="modal-head compact-modal-head">
                    <h2 id="addressModalTitle">Adresse de livraison</h2>
                    <button type="button" class="modal-close" data-close-address-modal aria-label="Fermer">×</button>
                </div>

                <p class="modal-error" id="addressValidationError" hidden>Choisissez une adresse proposée ou utilisez votre position.</p>

                <input type="hidden" name="delivery_destination_type" id="deliveryDestinationType" value="home">
                <input type="hidden" name="delivery_zone" id="deliveryZone" value="{{ $defaultZone }}">
                <input type="hidden" name="delivery_commune" id="deliveryCommune" value="{{ $defaultCommune }}">
                <input type="hidden" name="delivery_quartier" id="deliveryQuartier" value="{{ $defaultQuartier }}">
                <input type="hidden" name="delivery_city" id="deliveryCity" value="{{ $defaultCity }}">
                <input type="hidden" name="address" id="addressInput" value="{{ $defaultAddress }}">

                <div class="delivery-address-flow" id="deliveryAddressFields">
                    @if(($savedAddresses ?? collect())->isNotEmpty())
                        <section class="modal-form-section">
                            <div class="modal-section-heading">
                                <span class="modal-section-icon" aria-hidden="true">✓</span>
                                <div>
                                    <strong>Mes adresses enregistrées</strong>
                                    <small>Choisissez une adresse de votre carnet ou recherchez une nouvelle adresse.</small>
                                </div>
                            </div>
                            <label for="savedAddressSelect" style="display:block;font-weight:700;color:#334155;font-size:13px;">
                                Adresse enregistrée
                                <select id="savedAddressSelect" style="width:100%;margin-top:8px;border:1px solid #cbd5e1;border-radius:10px;padding:12px;background:#fff;">
                                    <option value="">Choisir une adresse</option>
                                    @foreach($savedAddresses as $savedAddress)
                                        <option value="{{ $savedAddress->id }}" @selected($addressBookDefault?->id === $savedAddress->id)>
                                            {{ $savedAddress->label }} — {{ $savedAddress->commune ?: $savedAddress->city }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </section>
                    @endif

                    <section class="modal-form-section location-section">
                        <div class="modal-section-heading">
                            <span class="modal-section-icon" aria-hidden="true">1</span>
                            <div>
                                <strong>Lieu de livraison</strong>
                                <small>Recherchez un quartier, une rue, une résidence ou un lieu connu.</small>
                            </div>
                        </div>

                        <div class="location-search-card">
                            <label class="location-search-label" for="addressSearchInput">Adresse ou lieu</label>
                            <div class="location-search-row">
                                <div class="location-search-input-wrap">
                                    <span class="location-pin" aria-hidden="true">⌖</span>
                                    <input type="search" id="addressSearchInput" value="{{ $defaultAddress }}" placeholder="Ex. Riviera 3, Angré, Zone 4, Bouaké..." autocomplete="off">
                                </div>
                                <button type="button" class="btn-location" id="useLocationBtn">
                                    <span aria-hidden="true">◎</span>
                                    Ma position
                                </button>
                            </div>
                            <span class="geo-status" id="geoStatus" hidden></span>
                            <div id="addressSearchResults" class="address-search-results" hidden aria-live="polite"></div>
                        </div>

                        <div class="selected-location-card" id="selectedLocationCard" {{ $defaultAddress ? '' : 'hidden' }}>
                            <div class="selected-location-marker" aria-hidden="true">✓</div>
                            <div class="selected-location-copy">
                                <strong id="selectedLocationTitle">{{ $defaultCommune ?: ($defaultCity ?: 'Adresse sélectionnée') }}</strong>
                                <span id="selectedLocationText">{{ $defaultAddress }}</span>
                            </div>
                            <button type="button" class="selected-location-edit" id="editSelectedLocationBtn">Modifier</button>
                        </div>

                    </section>

                    <section class="modal-form-section contact-section">
                        <div class="modal-section-heading">
                            <span class="modal-section-icon" aria-hidden="true">2</span>
                            <div>
                                <strong>Contact de livraison</strong>
                                <small>Ces coordonnées servent au suivi et à la remise de la commande.</small>
                            </div>
                        </div>

                        <div class="contact-grid">
                            <label>Nom complet
                                <input type="text" name="full_name" id="fullNameInput" value="{{ $defaultName }}" autocomplete="name" required>
                            </label>
                            <label>Numéro WhatsApp
                                <input type="tel" name="whatsapp_phone" id="whatsappInput" value="{{ $defaultPhone }}" autocomplete="tel" inputmode="tel" required>
                            </label>
                        </div>
                    </section>

                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" data-close-address-modal>Annuler</button>
                    <button type="button" class="btn-save-address" id="saveAddressBtn">Confirmer cette adresse</button>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
    const form = document.getElementById('checkoutForm');
    const addressModal = document.getElementById('addressModal');
    const addressPreviewTitle = document.getElementById('addressPreviewTitle');
    const addressPreviewText = document.getElementById('addressPreviewText');
    const addressPreviewPhone = document.getElementById('addressPreviewPhone');
    const addressStep = document.getElementById('addressStep');
    const paymentSection = document.getElementById('paymentSection');
    const summaryActionBtn = document.getElementById('summaryActionBtn');
    const summaryBox = document.querySelector('.summary-box');
    const summaryDeliveryAmount = document.getElementById('summaryDeliveryAmount');
    const summaryTotalLabel = document.getElementById('summaryTotalLabel');
    const summaryTotalAmount = document.getElementById('summaryTotalAmount');
    const summaryLoyaltyRow = document.getElementById('summaryLoyaltyRow');
    const summaryLoyaltyAmount = document.getElementById('summaryLoyaltyAmount');
    const loyaltyPointsInput = document.getElementById('loyaltyPointsInput');
    const loyaltyPointValue = Number(@js($loyaltyPointUnitValue));
    const loyaltyPointMaximum = Number(@js($loyaltyPointsMaximum));
    const addressValidationError = document.getElementById('addressValidationError');
    const savedAddresses = @js($savedAddressSnapshots ?? collect());

    let checkoutStage = 'address';
    let deliveryQuoteRequired = false;
    let deliveryCalculated = false;
    let deliveryAvailable = false;
    let deliveryMessage = null;
    let deliveryPreviewPending = false;
    let deliveryPreviewController = null;
    let deliveryPreviewRequestId = 0;

    // Géolocalisation checkout V70 : restauration de la détection Web fiable, sans carte.
    // Le premier callback du navigateur peut provenir du réseau/IP et annoncer
    // plusieurs kilomètres de précision. OVANIE ne reverse-géocode JAMAIS ce
    // point grossier : il garde watchPosition actif jusqu'au vrai fix GPS.
    // Sur téléphone, un cold start GNSS peut demander plusieurs secondes. Le navigateur reste en écoute jusqu’au meilleur fix disponible.
    let geoWatchId = null;
    let geoLastLatitude = null;
    let geoLastLongitude = null;
    let geoLastReverseAt = 0;
    let geoReverseRequestId = 0;
    let geoWatchStartedAt = 0;
    let geoAcquireTimer = null;
    let geoBestPosition = null;
    let geoPrecisePositionApplied = false;
    let geoAppliedAccuracy = Number.POSITIVE_INFINITY;
    const GEO_MIN_MOVE_METERS = 8;
    const GEO_REVERSE_INTERVAL_MS = 2500;
    const GEO_ACQUIRE_WINDOW_MS = 12000;
    const GEO_TARGET_ACCURACY_METERS = 5;
    const GEO_MAX_AUTO_CONFIRM_ACCURACY_METERS = 30;
    // V65 : la géolocalisation sert d'abord à proposer/remplir l'adresse.
    // Une position jusqu’à 500 m peut servir à identifier et afficher l’adresse
    // du navigateur, comme dans la version Web qui fonctionnait auparavant.
    // La coordonnée reste conservée telle quelle et watchPosition continue à
    // améliorer silencieusement la mesure lorsqu’un meilleur fix arrive.
    const GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS = 500;

    function formatFcfa(amount) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount) + ' FCFA';
    }

    function formatWeightKg(amount) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(amount || 0)) + ' kg';
    }

    function formatVolumeM3(amount) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(Number(amount || 0)) + ' m3';
    }

    function openAddressModal() {
        addressModal.classList.add('is-open');
        addressModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeAddressModal() {
        stopLiveGeolocation();
        addressModal.classList.remove('is-open');
        addressModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function isGenericLocationLabel(value) {
        const normalized = normalizeLocationText(String(value || ''));
        return !normalized
            || normalized === 'position actuelle detectee'
            || normalized === 'position actuelle'
            || normalized === 'adresse selectionnee';
    }

    function hasDetailedDeliveryAddress({ address = '', district = '', road = '', landmark = '' } = {}) {
        const cleanAddress = String(address || '').trim();
        if (isGenericLocationLabel(cleanAddress)) return false;

        if (String(district || '').trim() || String(road || '').trim() || String(landmark || '').trim()) {
            return true;
        }

        // Un libellé ville/commune/pays seul n’est pas une adresse chantier.
        // Une adresse composée d’au moins 4 segments contient généralement un
        // quartier, une rue, une cité ou un repère exploitable.
        const segments = cleanAddress.split(',').map((part) => part.trim()).filter(Boolean);
        return segments.length >= 4;
    }

    function addressIsComplete() {
        const fullName = document.getElementById('fullNameInput')?.value.trim();
        const phone = document.getElementById('whatsappInput')?.value.trim();
        if (!fullName || !phone) return false;

        const zone = document.getElementById('deliveryZone')?.value;
        const commune = document.getElementById('deliveryCommune')?.value.trim();
        const city = document.getElementById('deliveryCity')?.value.trim();
        const district = document.getElementById('deliveryQuartier')?.value.trim();
        const address = document.getElementById('addressInput')?.value.trim();
        const source = document.getElementById('deliveryGeoSource')?.value || '';

        if (!zone || !address || isGenericLocationLabel(address)) return false;
        if (zone === 'abidjan') {
            if (!commune || normalizeLocationText(commune) === 'abidjan') return false;
        } else if (!city) {
            return false;
        }

        // V65 : deux niveaux sont séparés.
        // - browser_live = vrai fix GPS fin (<=15 m) ;
        // - browser_location_assist = la position du navigateur a servi à
        //   retrouver une adresse lisible, mais reste une aide de saisie.
        if (source === 'browser_live' || source === 'browser_location_assist') {
            const accuracy = Number(document.getElementById('deliveryGeoAccuracy')?.value || 0);
            const lat = Number(document.getElementById('deliveryLatitude')?.value || NaN);
            const lng = Number(document.getElementById('deliveryLongitude')?.value || NaN);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;
            if (!Number.isFinite(accuracy) || accuracy <= 0) return false;
            if (source === 'browser_live' && accuracy > GEO_MAX_AUTO_CONFIRM_ACCURACY_METERS) return false;
            if (source === 'browser_location_assist' && accuracy > GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS) return false;
            return Boolean(address);
        }

        return true;
    }

    function showAddressError() {
        if (addressValidationError) {
            const fullName = document.getElementById('fullNameInput')?.value.trim();
            const phone = document.getElementById('whatsappInput')?.value.trim();
            const source = document.getElementById('deliveryGeoSource')?.value || '';
            addressValidationError.textContent = (!fullName || !phone)
                ? 'Renseignez le nom et le numéro WhatsApp du contact de livraison.'
                : source === 'browser_live'
                    ? 'Impossible de confirmer votre position. Réessayez ou recherchez votre adresse.'
                    : 'Choisissez une adresse proposée ou utilisez « Ma position ».';
            addressValidationError.hidden = false;
        }
        openAddressModal();
    }

    function updateAddressPreview() {
        const zone = document.getElementById('deliveryZone')?.value || 'abidjan';
        const commune = document.getElementById('deliveryCommune')?.value || '';
        const city = document.getElementById('deliveryCity')?.value || '';
        const quartier = document.getElementById('deliveryQuartier')?.value || '';
        const address = document.getElementById('addressInput')?.value || '';
        const phone = document.getElementById('whatsappInput')?.value || 'à renseigner';

        addressPreviewTitle.textContent = zone === 'abidjan'
            ? (commune || 'Adresse à compléter')
            : (city || 'Ville à compléter');
        addressPreviewText.textContent = [quartier, address].filter(Boolean).join(', ') || 'Aucune adresse détaillée renseignée.';

        addressPreviewPhone.textContent = 'WhatsApp : ' + phone;

        const hiddenPhone = document.getElementById('checkoutPhoneInput');
        if (hiddenPhone) {
            hiddenPhone.value = phone === 'à renseigner' ? '' : phone;
        }

        setSummaryActionState();
    }

    function applyPaymentOptions(paymentOptions) {
        if (!paymentOptions || !Array.isArray(paymentOptions.methods)) return;

        const methods = new Map(paymentOptions.methods.map((item) => [item.code, item]));
        const online = methods.get('paydunya');
        const cod = methods.get('cash_on_delivery');
        const onlineInput = document.querySelector('input[name="payment_method"][value="paydunya"]');
        const codInput = document.querySelector('input[name="payment_method"][value="cash_on_delivery"]');
        const note = document.getElementById('paymentAvailabilityNote');

        if (onlineInput && online) onlineInput.disabled = !Boolean(online.enabled);
        if (codInput && cod) codInput.disabled = !Boolean(cod.enabled);

        if (paymentOptions.cash_on_delivery_required && codInput && !codInput.disabled) {
            document.querySelectorAll('.payment-choice').forEach((el) => el.classList.remove('active'));
            codInput.checked = true;
            codInput.closest('.payment-choice')?.classList.add('active');
            if (onlineInput) onlineInput.checked = false;
        } else {
            const checked = document.querySelector('input[name="payment_method"]:checked');
            if (checked?.disabled) {
                checked.checked = false;
                checked.closest('.payment-choice')?.classList.remove('active');
            }
        }

        if (note) {
            const reason = online && !online.enabled ? (online.reason || '') : '';
            note.textContent = reason;
            note.hidden = !reason;
        }

        setSummaryActionState();
    }

    function setSummaryActionState() {
        if (!summaryActionBtn) return;

        if (checkoutStage === 'payment') {
            const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || '';

            if (selectedPaymentMethod === 'paydunya') {
                summaryActionBtn.textContent = 'Payer maintenant';
                summaryActionBtn.disabled = false;
                return;
            }

            if (selectedPaymentMethod === 'cash_on_delivery') {
                summaryActionBtn.textContent = 'Confirmer la commande';
                summaryActionBtn.disabled = false;
                return;
            }

            summaryActionBtn.textContent = 'Choisir un mode de paiement';
            summaryActionBtn.disabled = true;
            return;
        }

        summaryActionBtn.disabled = false;
        summaryActionBtn.textContent = 'Ajouter une adresse';
    }

    function setSummaryPendingState() {
        const subtotal = Number(summaryBox?.dataset.subtotal || 0);
        summaryDeliveryAmount.textContent = '';
        summaryDeliveryAmount.classList.add('pending-text');
        summaryTotalLabel.textContent = 'Total provisoire';
        const provisionalTotal = Math.max(0, subtotal - currentLoyaltyDiscount());
        summaryTotalAmount.textContent = formatFcfa(provisionalTotal);
        summaryTotalAmount.classList.add('is-pending');
    }

    function currentLoyaltyDiscount() {
        if (!loyaltyPointsInput) return 0;

        const requested = Math.max(0, Math.min(loyaltyPointMaximum, Number.parseInt(loyaltyPointsInput.value || '0', 10) || 0));
        if (Number(loyaltyPointsInput.value || 0) !== requested) {
            loyaltyPointsInput.value = String(requested);
        }

        const discount = requested * loyaltyPointValue;
        if (summaryLoyaltyRow && summaryLoyaltyAmount) {
            summaryLoyaltyRow.hidden = discount <= 0;
            summaryLoyaltyAmount.textContent = discount > 0 ? '-' + formatFcfa(discount) : formatFcfa(0);
        }

        return discount;
    }

    function calculateOrderTotalAfterAddress() {
        const subtotal = Number(summaryBox?.dataset.subtotal || 0);
        const deliveryTotal = Number(summaryBox?.dataset.deliveryTotal || 0);
        const deliveryKnown = summaryBox?.dataset.deliveryKnown === '1';

        if (deliveryKnown) {
            summaryDeliveryAmount.textContent = formatFcfa(deliveryTotal);
            summaryDeliveryAmount.classList.remove('pending-text');
            summaryTotalLabel.textContent = 'Total de la commande';
            const finalTotal = Math.max(0, subtotal + deliveryTotal - currentLoyaltyDiscount());
            summaryTotalAmount.textContent = formatFcfa(finalTotal);
            summaryTotalAmount.classList.remove('is-pending');
            return;
        }

        setSummaryPendingState();
    }

    function resetDeliveryPreview() {
        deliveryCalculated = false;
        deliveryQuoteRequired = false;
        deliveryAvailable = false;
        deliveryMessage = null;
        summaryBox.dataset.deliveryKnown = '0';
        summaryBox.dataset.deliveryTotal = '0';
        setSummaryPendingState();
        setSummaryActionState();
    }

    async function refreshDeliveryPreview() {
        if (!summaryBox || !addressIsComplete()) {
            resetDeliveryPreview();
            return false;
        }

        if (deliveryPreviewController) {
            deliveryPreviewController.abort();
        }

        deliveryPreviewController = new AbortController();
        const requestId = ++deliveryPreviewRequestId;
        deliveryPreviewPending = true;
        summaryActionBtn.disabled = true;
        summaryDeliveryAmount.textContent = 'Calcul en cours…';
        summaryDeliveryAmount.classList.add('pending-text');
        summaryTotalLabel.textContent = 'Total provisoire';
        summaryTotalAmount.textContent = formatFcfa(Math.max(0, Number(summaryBox?.dataset.subtotal || 0) - currentLoyaltyDiscount()));
        summaryTotalAmount.classList.add('is-pending');

        try {
            const response = await fetch('{{ route('checkout.deliveryFeePreview') }}', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: new FormData(form),
                signal: deliveryPreviewController.signal,
            });

            if (!response.ok) {
                throw new Error('delivery_preview_failed');
            }

            const data = await response.json();

            if (requestId !== deliveryPreviewRequestId) {
                return false;
            }

            applyPaymentOptions(data.payment_options);

            const subtotal = Number(data.subtotal ?? summaryBox.dataset.subtotal ?? 0);
            const delivery = Number(data.delivery_fee ?? 0);
            const total = Number(data.total ?? subtotal + delivery);

            deliveryCalculated = Boolean(data.delivery_calculated);
            deliveryAvailable = Boolean(data.delivery_available);
            deliveryQuoteRequired = Boolean(data.delivery_quote_required);
            deliveryMessage = data.delivery_message || null;
            summaryBox.dataset.subtotal = String(subtotal);
            summaryBox.dataset.deliveryTotal = String(delivery);
            summaryBox.dataset.deliveryKnown = deliveryCalculated && deliveryAvailable && !deliveryQuoteRequired ? '1' : '0';

            if (Array.isArray(data.delivery_debug) && data.delivery_debug.length > 0) {
                console.warn('OVANIE delivery diagnostics', data.delivery_debug);
            }

            if (!deliveryCalculated) {
                resetDeliveryPreview();
                return false;
            }

            if (deliveryQuoteRequired || !deliveryAvailable) {
                deliveryCalculated = false;
                setSummaryPendingState();
                return false;
            }

            summaryDeliveryAmount.textContent = formatFcfa(delivery);
            summaryDeliveryAmount.classList.remove('pending-text');
            summaryTotalLabel.textContent = 'Total de la commande';
            const finalTotal = Math.max(0, total - currentLoyaltyDiscount());
            summaryTotalAmount.textContent = formatFcfa(finalTotal);
            summaryTotalAmount.classList.remove('is-pending');

            // Le checkout public n'expose ni boutique, ni vendeur, ni partenaire logistique.

            return true;
        } catch (error) {
            if (error.name !== 'AbortError' && requestId === deliveryPreviewRequestId) {
                resetDeliveryPreview();
            }
            return false;
        } finally {
            if (requestId === deliveryPreviewRequestId) {
                deliveryPreviewPending = false;
                summaryActionBtn.disabled = false;
                setSummaryActionState();
            }
        }
    }

    function revealPaymentStep(refresh = true) {
        addressStep?.classList.add('is-hidden');
        paymentSection.classList.remove('is-hidden');
        checkoutStage = 'payment';
        setSummaryActionState();
        updatePaymentDetailsVisibility();
        paymentSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (refresh) refreshDeliveryPreview();
    }

    const abidjanCommunes = @js(config('client_space.abidjan_communes', []));

    function normalizeLocationText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    // Aide de reconnaissance lorsque le géocodeur retourne uniquement un
    // quartier. Cette liste n'est jamais une condition d'éligibilité : le serveur
    // autorise tous les quartiers dès que leur commune est desservie.
    const quartierCommuneMap = @js(app(\App\Services\Geo\AbidjanLocalityRegistry::class)->clientLocationMap());

    function detectAbidjanCommune(value) {
        const raw = String(value || '');
        const haystack = normalizeLocationText(raw);
        const segments = raw
            .split(/[,;|]+/)
            .map((segment) => normalizeLocationText(segment))
            .filter(Boolean);

        // Les quartiers connus sont testés du plus précis au plus court.
        const mappedLocations = Object.entries(quartierCommuneMap)
            .sort(([a], [b]) => normalizeLocationText(b).length - normalizeLocationText(a).length);
        for (const [quartier, commune] of mappedLocations) {
            if (haystack.includes(normalizeLocationText(quartier))) {
                return commune;
            }
        }

        // Une commune doit correspondre à un segment complet. Ainsi « Plateau
        // Dokui » n'est pas confondu avec la commune du Plateau.
        const direct = abidjanCommunes.find((commune) => {
            const normalized = normalizeLocationText(commune);
            return haystack === normalized || segments.includes(normalized);
        });

        return direct || '';
    }

    function setHiddenField(id, value) {
        const field = document.getElementById(id);
        if (field) field.value = value || '';
    }

    function setLocationData({ zone, commune = '', city = '', district = '', address = '', source = '' }) {
        if (zone === 'abidjan' && (!commune || normalizeLocationText(commune) === 'abidjan')) {
            commune = detectAbidjanCommune([district, address].filter(Boolean).join(' ')) || commune;
        }

        setHiddenField('deliveryZone', zone || 'abidjan');
        setHiddenField('deliveryCommune', commune);
        setHiddenField('deliveryQuartier', district);
        setHiddenField('deliveryCity', city);
        setHiddenField('addressInput', address);
        if (source) setHiddenField('deliveryGeoSource', source);
        if (source && source !== 'address_book') setHiddenField('savedAddressId', '');
        deliveryCalculated = false;
        updateSelectedLocationCard();
        updateAddressPreview();
    }

    function updateSelectedLocationCard() {
        const card = document.getElementById('selectedLocationCard');
        const title = document.getElementById('selectedLocationTitle');
        const text = document.getElementById('selectedLocationText');
        const address = document.getElementById('addressInput')?.value.trim() || '';
        const zone = document.getElementById('deliveryZone')?.value || 'abidjan';
        const locality = zone === 'abidjan'
            ? (document.getElementById('deliveryCommune')?.value || '')
            : (document.getElementById('deliveryCity')?.value || '');

        if (!card) return;

        const usable = Boolean(address) && !isGenericLocationLabel(address);
        card.hidden = !usable;
        if (title) title.textContent = locality ? `Adresse détectée · ${locality}` : 'Adresse détectée';
        if (text) text.textContent = usable ? address : '';
    }

    function clearSelectedLocation({ keepSearch = false } = {}) {
        setHiddenField('deliveryCommune', '');
        setHiddenField('deliveryQuartier', '');
        setHiddenField('deliveryCity', '');
        setHiddenField('addressInput', '');
        setHiddenField('deliveryLatitude', '');
        setHiddenField('deliveryLongitude', '');
        setHiddenField('deliveryGeoAccuracy', '');
        setHiddenField('deliveryGeoSource', '');
        setHiddenField('savedAddressId', '');
        if (!keepSearch) setHiddenField('addressSearchInput', '');
        updateSelectedLocationCard();
        resetDeliveryPreview();
    }

    function updateZoneFields() {
        const addressFields = document.getElementById('deliveryAddressFields');
        const saveButton = document.getElementById('saveAddressBtn');

        if (addressFields) addressFields.hidden = false;
        if (saveButton) saveButton.textContent = 'Confirmer cette adresse';
        updateSelectedLocationCard();
    }

    function clearResolvedAddressKeepCoordinates() {
        setHiddenField('deliveryCommune', '');
        setHiddenField('deliveryQuartier', '');
        setHiddenField('deliveryCity', '');
        setHiddenField('addressInput', '');
        setHiddenField('savedAddressId', '');
        deliveryCalculated = false;

        const searchInput = document.getElementById('addressSearchInput');
        if (searchInput && isGenericLocationLabel(searchInput.value)) {
            searchInput.value = '';
        }

        updateSelectedLocationCard();
        updateAddressPreview();
    }

    async function reverseGeocode(lat, lng, { fresh = false } = {}) {
        const params = new URLSearchParams({
            lat: String(lat),
            lng: String(lng),
        });
        if (fresh) {
            params.set('fresh', '1');
            params.set('_ts', String(Date.now()));
        }

        const response = await fetch(`/geo/reverse?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache',
            },
            cache: fresh ? 'no-store' : 'default',
        });

        if (!response.ok) {
            throw new Error('reverse_geocoding_failed');
        }

        const payload = await response.json();
        return payload.result || {};
    }

    function fillAddressFromGeoData(data, lat, lng, fallbackDisplayName = '') {
        const geoAddress = data.address || {};
        const resolved = data.resolved_location || {};

        const normalize = (value) => normalizeLocationText(String(value || ''));
        const firstNonEmpty = (...values) => values
            .map((value) => String(value || '').trim())
            .find((value) => value.length > 0) || '';
        const distinct = (candidate, ...others) => {
            const clean = String(candidate || '').trim();
            if (!clean) return '';
            const key = normalize(clean);
            return others.some((other) => key && key === normalize(other)) ? '' : clean;
        };

        const rawCity = firstNonEmpty(
            geoAddress.city,
            geoAddress.town,
            geoAddress.village,
            geoAddress.county,
        );
        const rawAdministrativeCommune = firstNonEmpty(
            geoAddress.city_district,
            geoAddress.municipality,
            geoAddress.district,
        );

        const registryCommune = detectAbidjanCommune([
            rawAdministrativeCommune,
            rawCity,
            data.display_name || '',
        ].join(' '));
        const adminCommuneFallback = normalize(rawAdministrativeCommune) !== 'abidjan'
            ? rawAdministrativeCommune
            : '';
        const detectedCommune = firstNonEmpty(
            resolved.commune,
            registryCommune,
            adminCommuneFallback,
        );

        const isAbidjan = resolved.zone === 'abidjan'
            || resolved.is_abidjan === true
            || Boolean(detectedCommune)
            || normalize(rawCity).includes('abidjan')
            || normalize(data.display_name || fallbackDisplayName).includes('abidjan');

        const city = isAbidjan ? 'Abidjan' : firstNonEmpty(resolved.city, rawCity);
        const commune = isAbidjan
            ? firstNonEmpty(detectedCommune, 'Abidjan')
            : firstNonEmpty(detectedCommune, rawAdministrativeCommune, city);

        const quarterCandidate = firstNonEmpty(
            resolved.quartier,
            geoAddress.neighbourhood,
            geoAddress.neighborhood,
            geoAddress.quarter,
            geoAddress.residential,
            geoAddress.locality,
            geoAddress.suburb,
        );
        const quarter = distinct(
            quarterCandidate,
            commune,
            city,
            'Abidjan',
            "Côte d'Ivoire",
            "Cote d'Ivoire",
        );

        const road = firstNonEmpty(geoAddress.road, geoAddress.pedestrian);
        const landmarkCandidate = firstNonEmpty(
            resolved.repere,
            geoAddress.amenity,
            geoAddress.building,
            geoAddress.shop,
            geoAddress.office,
            geoAddress.tourism,
            data.feature_name,
        );
        const landmark = distinct(landmarkCandidate, commune, city, quarter, road, 'Abidjan');
        const district = quarter || landmark || distinct(road, commune, city, 'Abidjan');

        const detailedParts = [
            [geoAddress.house_number, road].filter(Boolean).join(' ').trim(),
            landmark,
            quarter,
            commune,
            city,
            geoAddress.country,
        ].filter(Boolean).filter((value, index, values) => {
            const key = normalize(value);
            return values.findIndex((other) => normalize(other) === key) === index;
        });

        const detailedAddress = detailedParts.join(', ');
        const providerDisplay = firstNonEmpty(data.display_name, fallbackDisplayName);
        const providerSegments = providerDisplay.split(',').map((part) => part.trim()).filter(Boolean).length;
        const detailedSegments = detailedAddress.split(',').map((part) => part.trim()).filter(Boolean).length;
        // V69 : le backend OVANIE est le référentiel unique de libellé sur Web et mobile.
        // À coordonnées identiques, les deux clients doivent afficher le même display_name.
        // On ne recompose plus un libellé différent côté navigateur.
        const normalizedAddress = firstNonEmpty(providerDisplay, detailedAddress);

        // IMPORTANT V52 : les coordonnées seules ou une simple commune ne sont
        // plus présentées comme une « adresse confirmée ». C'est exactement ce
        // qui produisait « Position actuelle détectée » sur Web/émulateur.
        const detailedEnough = hasDetailedDeliveryAddress({
            address: normalizedAddress,
            district,
            road,
            landmark,
        });
        const preciseCommune = !isAbidjan || (commune && normalize(commune) !== 'abidjan');
        const hasUsefulLabel = Boolean(
            normalizedAddress &&
            !isGenericLocationLabel(normalizedAddress) &&
            (district || road || landmark || commune || city)
        );
        // V65 : le reverse-géocodage sert à proposer l'adresse au client. On ne
        // vide plus le champ uniquement parce que le fournisseur n'a pas quatre
        // segments. Une commune/quartier/rue lisible est déjà une suggestion
        // utile que le client peut confirmer ou corriger.
        if ((!detailedEnough && !hasUsefulLabel) || !preciseCommune) {
            clearResolvedAddressKeepCoordinates();
            return false;
        }

        if (isAbidjan) {
            setLocationData({
                zone: 'abidjan',
                commune,
                city: 'Abidjan',
                district,
                address: normalizedAddress,
                source: document.getElementById('deliveryGeoSource')?.value || 'geocoding',
            });
        } else {
            setLocationData({
                zone: 'interieur',
                commune,
                city,
                district,
                address: normalizedAddress,
                source: document.getElementById('deliveryGeoSource')?.value || 'geocoding',
            });
        }

        const searchInput = document.getElementById('addressSearchInput');
        if (searchInput) searchInput.value = normalizedAddress;
        return true;
    }

    function applyDisplayNameFallback(displayName) {
        const commune = detectAbidjanCommune(displayName);
        const isAbidjan = Boolean(commune) || normalizeLocationText(displayName).includes('abidjan');

        if (isAbidjan) {
            setLocationData({
                zone: 'abidjan',
                commune: commune || 'Abidjan',
                city: '',
                address: displayName,
                source: 'geocoding',
            });
        } else {
            setLocationData({
                zone: 'interieur',
                commune: (displayName.split(',')[0] || '').trim(),
                city: (displayName.split(',')[0] || '').trim(),
                address: displayName,
                source: 'geocoding',
            });
        }
    }

    async function searchAddress(query) {
        const response = await fetch(`/geo/search?q=${encodeURIComponent(query)}`, {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) return [];
        const payload = await response.json();
        return payload.results || [];
    }

    function renderAddressResults(results) {
        const container = document.getElementById('addressSearchResults');
        if (!container) return;

        container.replaceChildren();
        container.hidden = false;

        if (!results.length) {
            const empty = document.createElement('div');
            empty.className = 'address-search-empty';
            empty.textContent = 'Aucune adresse trouvée. Essayez une autre recherche ou utilisez Ma position.';
            container.appendChild(empty);
            return;
        }

        results.slice(0, 5).forEach((result) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'address-result-item';

            const marker = document.createElement('span');
            marker.className = 'address-result-marker';
            marker.textContent = '⌖';

            const copy = document.createElement('span');
            copy.textContent = result.display_name || 'Adresse trouvée';

            button.append(marker, copy);

            button.addEventListener('click', async () => {
                stopLiveGeolocation();
                const lat = Number(result.latitude).toFixed(7);
                const lng = Number(result.longitude).toFixed(7);
                setHiddenField('deliveryLatitude', lat);
                setHiddenField('deliveryLongitude', lng);
                setHiddenField('deliveryGeoSource', 'geocoding');
                setHiddenField('addressInput', result.display_name || '');

                const searchInput = document.getElementById('addressSearchInput');
                if (searchInput) searchInput.value = result.display_name || '';

                try {
                    const geoData = await reverseGeocode(result.latitude, result.longitude);
                    fillAddressFromGeoData(geoData, result.latitude, result.longitude, result.display_name || '');
                } catch (error) {
                    applyDisplayNameFallback(result.display_name || '');
                }

                if (addressValidationError) addressValidationError.hidden = true;
                container.hidden = true;
                updateSelectedLocationCard();
                updateAddressPreview();

                if (addressIsComplete()) {
                    await refreshDeliveryPreview();
                }
            });

            container.appendChild(button);
        });
    }

    async function applySavedAddress(savedAddress) {
        if (!savedAddress) {
            setHiddenField('savedAddressId', '');
            return;
        }

        setHiddenField('savedAddressId', savedAddress.saved_address_id);
        const zone = savedAddress.delivery_zone || 'interieur';

        setHiddenField('deliveryLatitude', savedAddress.delivery_latitude || '');
        setHiddenField('deliveryLongitude', savedAddress.delivery_longitude || '');
        setHiddenField('deliveryGeoAccuracy', '');
        setHiddenField('deliveryGeoSource', 'address_book');

        const fullNameInput = document.getElementById('fullNameInput');
        const whatsappInput = document.getElementById('whatsappInput');
        const addressSearchInput = document.getElementById('addressSearchInput');

        if (fullNameInput && savedAddress.recipient_name) fullNameInput.value = savedAddress.recipient_name;
        if (whatsappInput && savedAddress.phone) whatsappInput.value = savedAddress.phone;
        if (addressSearchInput) addressSearchInput.value = savedAddress.address || '';

        setLocationData({
            zone,
            // La commune est conservée même hors Abidjan afin que la grille
            // tarifaire du vendeur puisse sélectionner le bon prix.
            commune: savedAddress.delivery_commune || '',
            city: zone === 'interieur' ? (savedAddress.delivery_city || '') : '',
            district: savedAddress.delivery_quartier || '',
            address: savedAddress.address || '',
            source: 'address_book',
        });

        if (addressValidationError) addressValidationError.hidden = true;
        if (addressIsComplete()) await refreshDeliveryPreview();
    }

    document.getElementById('savedAddressSelect')?.addEventListener('change', async (event) => {
        stopLiveGeolocation();
        const selectedId = Number(event.target.value || 0);
        const savedAddress = savedAddresses.find((item) => Number(item.saved_address_id) === selectedId);
        await applySavedAddress(savedAddress);
    });

    document.querySelectorAll('[data-open-address-modal]').forEach((button) => {
        button.addEventListener('click', openAddressModal);
    });

    document.querySelectorAll('[data-close-address-modal]').forEach((button) => {
        button.addEventListener('click', closeAddressModal);
    });

    document.getElementById('saveAddressBtn')?.addEventListener('click', async () => {
        updateAddressPreview();

        if (!addressIsComplete()) {
            if (addressValidationError) {
                const fullName = document.getElementById('fullNameInput')?.value.trim();
                const phone = document.getElementById('whatsappInput')?.value.trim();
                addressValidationError.textContent = (!fullName || !phone)
                    ? 'Renseignez le nom et le numéro WhatsApp du contact de livraison.'
                    : 'Choisissez une adresse proposée ou utilisez votre position.';
                addressValidationError.hidden = false;
            }
            return;
        }

        if (addressValidationError) addressValidationError.hidden = true;
        const deliveryReady = await refreshDeliveryPreview();

        if (!deliveryReady) {
            if (addressValidationError) {
                addressValidationError.textContent = deliveryMessage
                    || 'La livraison n’est pas disponible pour l’ensemble de ce panier à cette adresse.';
                addressValidationError.hidden = false;
            }
            return;
        }

        closeAddressModal();
        revealPaymentStep();
    });

    document.getElementById('editSelectedLocationBtn')?.addEventListener('click', () => {
        document.getElementById('addressSearchInput')?.focus();
    });

    function distanceMeters(lat1, lng1, lat2, lng2) {
        const toRad = (value) => value * Math.PI / 180;
        const earthRadius = 6371000;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return 2 * earthRadius * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function stopLiveGeolocation() {
        if (geoWatchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(geoWatchId);
        }
        if (geoAcquireTimer !== null) {
            clearTimeout(geoAcquireTimer);
        }
        geoWatchId = null;
        geoAcquireTimer = null;
        geoBestPosition = null;
        geoPrecisePositionApplied = false;
        geoReverseRequestId += 1;
        geoLastLatitude = null;
        geoLastLongitude = null;
        geoLastReverseAt = 0;
        geoWatchStartedAt = 0;
    }

    function geolocationErrorMessage(error) {
        if (error?.code === error?.PERMISSION_DENIED) {
            return 'Localisation refusée. Autorisez la position dans le navigateur ou recherchez votre adresse.';
        }
        if (error?.code === error?.TIMEOUT) {
            return 'La localisation prend trop de temps. Réessayez ou recherchez votre adresse.';
        }
        return 'Impossible d’obtenir une position récente. Recherchez votre adresse ou réessayez.';
    }

    // V65 : l’interface client ne montre jamais les métriques GPS brutes.
    // Les valeurs latitude/longitude/accuracy restent disponibles en console
    // pour le diagnostic, sans dégrader l’expérience du checkout.
    function setGeoStatus(message = '') {
        const status = document.getElementById('geoStatus');
        if (!status) return;
        const text = String(message || '').trim();
        status.textContent = text;
        status.hidden = text === '';
    }

    function setLocationButtonLoading(loading) {
        const button = document.getElementById('useLocationBtn');
        if (!button) return;
        button.disabled = Boolean(loading);
        button.setAttribute('aria-busy', loading ? 'true' : 'false');
        button.innerHTML = loading
            ? '<span class="location-spinner" aria-hidden="true"></span><span>Localisation…</span>'
            : '<span aria-hidden="true">◎</span><span>Ma position</span>';
    }

    function debugBrowserPosition(position, label = 'sample') {
        try {
            console.debug('[OVANIE GEO]', label, {
                latitude: Number(position?.coords?.latitude),
                longitude: Number(position?.coords?.longitude),
                accuracy: Number(position?.coords?.accuracy),
                timestamp: Number(position?.timestamp || 0),
            });
        } catch (_) {}
    }


    function showApproximateBrowserLocation(_data, _accuracy) {
        // Aucun détail technique n'est affiché au client. Le watcher reste
        // actif en arrière-plan et une adresse sera proposée dès qu'une
        // position utile au reverse-géocodage est disponible.
        setGeoStatus('');
    }

    function applyLocationAddressFallback(data, lat, lng, accuracy) {
        if (!Number.isFinite(accuracy) || accuracy <= 0 || accuracy > GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS) {
            return false;
        }

        const geoAddress = data?.address || {};
        const resolved = data?.resolved_location || {};
        const display = String(data?.display_name || '').trim();
        const rawAdministrativeCommune = String(
            geoAddress.city_district
            || geoAddress.municipality
            || geoAddress.district
            || ''
        ).trim();
        const detectedCommune = String(resolved.commune || '').trim()
            || detectAbidjanCommune([
                rawAdministrativeCommune,
                geoAddress.city,
                geoAddress.town,
                display,
            ].filter(Boolean).join(' '))
            || (normalizeLocationText(rawAdministrativeCommune) !== 'abidjan'
                ? rawAdministrativeCommune
                : '');
        const isAbidjan = String(resolved.zone || '').toLowerCase() === 'abidjan'
            || resolved.is_abidjan === true
            || Boolean(detectedCommune)
            || normalizeLocationText(display).includes('abidjan');
        const city = isAbidjan
            ? 'Abidjan'
            : String(resolved.city || geoAddress.city || geoAddress.town || geoAddress.village || '').trim();
        const commune = isAbidjan
            ? detectedCommune
            : String(resolved.commune || geoAddress.municipality || geoAddress.city_district || city || '').trim();
        const district = String(
            resolved.quartier
            || geoAddress.neighbourhood
            || geoAddress.neighborhood
            || geoAddress.quarter
            || geoAddress.suburb
            || geoAddress.locality
            || ''
        ).trim();
        const road = String(geoAddress.road || geoAddress.pedestrian || '').trim();
        const landmark = String(
            resolved.repere
            || geoAddress.amenity
            || geoAddress.building
            || geoAddress.shop
            || geoAddress.office
            || geoAddress.tourism
            || data?.feature_name
            || ''
        ).trim();

        if (isAbidjan && (!commune || normalizeLocationText(commune) === 'abidjan')) return false;
        if (!isAbidjan && !city) return false;

        const generatedLabel = [
            [geoAddress.house_number, road].filter(Boolean).join(' ').trim(),
            landmark,
            district,
            commune,
            city,
            geoAddress.country,
        ].filter(Boolean).filter((value, index, values) => {
            const key = normalizeLocationText(value);
            return values.findIndex(other => normalizeLocationText(other) === key) === index;
        }).join(', ');

        const label = display && !isGenericLocationLabel(display) ? display : generatedLabel;
        if (!label || isGenericLocationLabel(label)) return false;

        const source = accuracy <= GEO_MAX_AUTO_CONFIRM_ACCURACY_METERS
            ? 'browser_live'
            : 'browser_location_assist';

        setHiddenField('deliveryGeoSource', source);
        setLocationData({
            zone: isAbidjan ? 'abidjan' : 'interieur',
            commune,
            city,
            district,
            address: label,
            source,
        });
        const searchInput = document.getElementById('addressSearchInput');
        if (searchInput) searchInput.value = label;
        return true;
    }

    async function applyLiveBrowserPosition(position, { force = false } = {}) {
        const latNumber = Number(position?.coords?.latitude);
        const lngNumber = Number(position?.coords?.longitude);
        const accuracy = Number(position?.coords?.accuracy || 0);
        if (!Number.isFinite(latNumber) || !Number.isFinite(lngNumber)) return false;
        if (!Number.isFinite(accuracy) || accuracy <= 0) return false;

        const positionTimestamp = Number(position?.timestamp || 0);
        if (geoWatchStartedAt > 0 && positionTimestamp > 0
            && positionTimestamp < geoWatchStartedAt - 1000) {
            return false;
        }

        if (accuracy > GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS) {
            showApproximateBrowserLocation({}, accuracy);
            return false;
        }

        const source = accuracy <= GEO_MAX_AUTO_CONFIRM_ACCURACY_METERS
            ? 'browser_live'
            : 'browser_location_assist';

        setHiddenField('deliveryLatitude', latNumber.toFixed(7));
        setHiddenField('deliveryLongitude', lngNumber.toFixed(7));
        setHiddenField('deliveryGeoAccuracy', accuracy);
        setHiddenField('deliveryGeoSource', source);
        setHiddenField('savedAddressId', '');

        const moved = geoLastLatitude === null || geoLastLongitude === null
            ? Number.POSITIVE_INFINITY
            : distanceMeters(geoLastLatitude, geoLastLongitude, latNumber, lngNumber);
        const now = Date.now();
        const improvedEnough = accuracy + Math.max(5, geoAppliedAccuracy * 0.15) < geoAppliedAccuracy;

        if (!force
            && geoPrecisePositionApplied
            && !improvedEnough
            && moved < GEO_MIN_MOVE_METERS
            && now - geoLastReverseAt < GEO_REVERSE_INTERVAL_MS) {
            return true;
        }

        geoLastLatitude = latNumber;
        geoLastLongitude = lngNumber;
        geoLastReverseAt = now;
        const requestId = ++geoReverseRequestId;

        try {
            const data = await reverseGeocode(latNumber, lngNumber, { fresh: true });
            if (requestId !== geoReverseRequestId) return false;

            const resolved = fillAddressFromGeoData(data, latNumber, lngNumber);
            if (!resolved) {
                const fallbackApplied = applyLocationAddressFallback(
                    data,
                    latNumber,
                    lngNumber,
                    accuracy,
                );
                if (!fallbackApplied) {
                    setGeoStatus('');
                    return false;
                }
            } else {
                setHiddenField('deliveryGeoSource', source);
            }

            geoPrecisePositionApplied = true;
            geoAppliedAccuracy = Math.min(geoAppliedAccuracy, accuracy);
            setGeoStatus('');
            setLocationButtonLoading(false);
            if (addressValidationError) addressValidationError.hidden = true;
            updateSelectedLocationCard();
            updateAddressPreview();
            if (addressIsComplete()) await refreshDeliveryPreview();
            return true;
        } catch (_) {
            if (requestId !== geoReverseRequestId) return false;
            if (!document.getElementById('addressInput')?.value?.trim()) {
                setGeoStatus('');
            }
            return false;
        }
    }

    function rememberBestBrowserPosition(position) {
        const accuracy = Number(position?.coords?.accuracy || 0);
        if (!Number.isFinite(accuracy) || accuracy <= 0) return;
        if (!geoBestPosition) {
            geoBestPosition = position;
            return;
        }
        const currentAccuracy = Number(geoBestPosition.coords.accuracy || Number.POSITIVE_INFINITY);
        if (accuracy < currentAccuracy) geoBestPosition = position;
    }


    function startLiveGeolocation() {
        if (!window.isSecureContext) {
            setGeoStatus('Autorisez la localisation ou saisissez votre adresse.');
            return;
        }

        if (!navigator.geolocation) {
            setGeoStatus('Saisissez votre adresse.');
            return;
        }

        stopLiveGeolocation();
        clearSelectedLocation({ keepSearch: false });
        geoWatchStartedAt = Date.now();
        geoBestPosition = null;
        geoPrecisePositionApplied = false;
        geoAppliedAccuracy = Number.POSITIVE_INFINITY;
        setGeoStatus('');
        setLocationButtonLoading(true);

        const options = {
            enableHighAccuracy: true,
            timeout: 60000,
            maximumAge: 0,
        };

        const handlePosition = async (position) => {
            debugBrowserPosition(position);
            const timestamp = Number(position?.timestamp || 0);
            if (timestamp > 0 && timestamp < geoWatchStartedAt - 1000) return;

            rememberBestBrowserPosition(position);
            const accuracy = Number(position?.coords?.accuracy || 0);
            if (!Number.isFinite(accuracy) || accuracy <= 0) return;

            if (accuracy <= GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS) {
                const significantlyBetter =
                    accuracy + Math.max(3, geoAppliedAccuracy * 0.12) < geoAppliedAccuracy;

                // V69 : un nouveau fix peut être plus juste sans annoncer une meilleure
                // accuracy. Sur cold-start, Chrome peut déplacer le point de plusieurs
                // dizaines de mètres avec une accuracy comparable. L'ancienne logique
                // ignorait ce correctif et gardait le premier quartier reçu.
                const movedFromApplied = (geoLastLatitude === null || geoLastLongitude === null)
                    ? Number.POSITIVE_INFINITY
                    : distanceMeters(
                        geoLastLatitude,
                        geoLastLongitude,
                        Number(position.coords.latitude),
                        Number(position.coords.longitude),
                    );
                const comparableAccuracy = !Number.isFinite(geoAppliedAccuracy)
                    || accuracy <= (geoAppliedAccuracy * 1.25 + 5);
                const meaningfulCorrection = geoPrecisePositionApplied
                    && comparableAccuracy
                    && movedFromApplied >= Math.max(12, Math.min(40, geoAppliedAccuracy * 0.5));

                if (!geoPrecisePositionApplied || significantlyBetter || meaningfulCorrection) {
                    await applyLiveBrowserPosition(position, {
                        force: !geoPrecisePositionApplied || significantlyBetter || meaningfulCorrection,
                    });
                }
            }
        };

        navigator.geolocation.getCurrentPosition(
            (position) => { void handlePosition(position); },
            () => {},
            options,
        );

        geoWatchId = navigator.geolocation.watchPosition(
            (position) => { void handlePosition(position); },
            (error) => {
                if (!geoPrecisePositionApplied) {
                    setGeoStatus(geolocationErrorMessage(error));
                    setLocationButtonLoading(false);
                }
            },
            options,
        );

        geoAcquireTimer = setTimeout(async () => {
            geoAcquireTimer = null;
            const bestAccuracy = Number(geoBestPosition?.coords?.accuracy || 0);

            if (!geoPrecisePositionApplied
                && geoBestPosition
                && Number.isFinite(bestAccuracy)
                && bestAccuracy > 0
                && bestAccuracy <= GEO_ADDRESS_LOOKUP_MAX_ACCURACY_METERS) {
                await applyLiveBrowserPosition(geoBestPosition, { force: true });
            }

            if (!geoPrecisePositionApplied) {
                setGeoStatus('Nous n’avons pas pu détecter votre adresse. Saisissez-la manuellement.');
                setLocationButtonLoading(false);
            }
        }, GEO_ACQUIRE_WINDOW_MS);
    }

    document.getElementById('useLocationBtn')?.addEventListener('click', startLiveGeolocation);

    document.getElementById('whatsappInput')?.addEventListener('input', () => {
        if (addressValidationError) addressValidationError.hidden = true;
        updateAddressPreview();
    });

    document.getElementById('fullNameInput')?.addEventListener('input', () => {
        if (addressValidationError) addressValidationError.hidden = true;
    });

    let addressSearchTimer = null;
    let addressSearchRequestId = 0;
    document.getElementById('addressSearchInput')?.addEventListener('input', (event) => {
        stopLiveGeolocation();
        clearTimeout(addressSearchTimer);
        const query = event.target.value.trim();
        const currentAddress = document.getElementById('addressInput')?.value.trim() || '';
        const resultsContainer = document.getElementById('addressSearchResults');

        if (query !== currentAddress) {
            clearSelectedLocation({ keepSearch: true });
        }

        if (query.length < 4) {
            if (resultsContainer) resultsContainer.hidden = true;
            return;
        }

        const requestId = ++addressSearchRequestId;
        addressSearchTimer = setTimeout(async () => {
            const results = await searchAddress(query);
            if (requestId === addressSearchRequestId) renderAddressResults(results);
        }, 350);
    });

    loyaltyPointsInput?.addEventListener('input', () => {
        calculateOrderTotalAfterAddress();
    });

    function updatePaymentDetailsVisibility() {
        setSummaryActionState();
    }

    document.querySelectorAll('.payment-choice').forEach((option) => {
        option.addEventListener('click', () => {
            const input = option.querySelector('input[name="payment_method"]');
            if (!input || input.disabled) {
                return;
            }

            document.querySelectorAll('.payment-choice').forEach((el) => el.classList.remove('active'));
            option.classList.add('active');
            input.checked = true;
            updatePaymentDetailsVisibility();
        });
    });

    summaryActionBtn?.addEventListener('click', async () => {
        if (checkoutStage === 'address') {
            openAddressModal();
            return;
        }

        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || '';
        if (!selectedPaymentMethod) {
            setSummaryActionState();
            return;
        }

        if (deliveryPreviewPending) {
            return;
        }

        if (!deliveryCalculated || !deliveryAvailable || deliveryQuoteRequired) {
            const calculated = await refreshDeliveryPreview();
            if (!calculated) {
                return;
            }
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    updateZoneFields();
    updateAddressPreview();
    updatePaymentDetailsVisibility();
    resetDeliveryPreview();

    // Si une adresse enregistrée est déjà complète, le serveur valide
    // automatiquement la livraison puis ouvre l'étape paiement. Le client
    // n'a plus à cliquer sur « Ajouter une adresse » pour confirmer une
    // information déjà connue et valide.
    if (addressIsComplete()) {
        refreshDeliveryPreview().then((ready) => {
            if (ready) revealPaymentStep(false);
        });
    }
</script>
</body>
</html>
