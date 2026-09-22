document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-product-page]');
    if (!page) return;

    const refreshIcons = () => window.lucide?.createIcons();
    const number = value => {
        const parsed = Number(String(value ?? '').replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const money = value => `${Math.round(number(value)).toLocaleString('fr-FR')} FCFA`;

    /* Galerie : toutes les images restent réellement accessibles. */
    const mainImage = document.getElementById('mainProductImage');
    const thumbs = Array.from(document.querySelectorAll('[data-product-thumb]'));
    const thumbsRail = document.querySelector('.ov-product-thumbs');
    const showMoreThumbs = document.querySelector('[data-show-more-thumbs]');
    let imageIndex = Math.max(0, thumbs.findIndex(button => button.classList.contains('is-active')));

    showMoreThumbs?.addEventListener('click', () => {
        thumbsRail?.classList.add('is-expanded');
    });

    const showImage = index => {
        if (!mainImage || !thumbs.length) return;
        imageIndex = (index + thumbs.length) % thumbs.length;
        const thumb = thumbs[imageIndex];
        if (imageIndex >= 4) thumbsRail?.classList.add('is-expanded');
        if (thumb?.dataset.image) mainImage.src = thumb.dataset.image;
        thumbs.forEach(item => item.classList.remove('is-active'));
        thumb?.classList.add('is-active');
        thumb?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    };

    thumbs.forEach((button, index) => button.addEventListener('click', () => showImage(index)));
    document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => showImage(imageIndex - 1));
    document.querySelector('[data-gallery-next]')?.addEventListener('click', () => showImage(imageIndex + 1));

    /* Quantité panier. */
    const qtyInput = document.querySelector('[data-qty-input]');
    const normalizeQty = value => {
        if (!qtyInput) return 1;
        const min = Math.max(1, number(qtyInput.min) || 1);
        const max = Math.max(min, number(qtyInput.max) || 999);
        return Math.min(max, Math.max(min, Math.round(number(value) || min)));
    };
    const setQty = value => { if (qtyInput) qtyInput.value = String(normalizeQty(value)); };
    document.querySelector('[data-qty-minus]')?.addEventListener('click', () => setQty(number(qtyInput?.value) - 1));
    document.querySelector('[data-qty-plus]')?.addEventListener('click', () => setQty(number(qtyInput?.value) + 1));
    qtyInput?.addEventListener('change', () => setQty(qtyInput.value));

    /* Onglets + accès directs depuis la zone produit. */
    const activateTab = key => {
        const targetButton = document.querySelector(`[data-tab-button="${key}"]`);
        const targetPanel = document.querySelector(`[data-tab-panel="${key}"]`);
        if (!targetButton || !targetPanel) return;
        document.querySelectorAll('[data-tab-button]').forEach(item => item.classList.toggle('is-active', item === targetButton));
        document.querySelectorAll('[data-tab-panel]').forEach(panel => panel.classList.toggle('is-active', panel === targetPanel));
        refreshIcons();
    };

    document.querySelectorAll('[data-tab-button]').forEach(button => {
        button.addEventListener('click', () => activateTab(button.dataset.tabButton));
    });

    document.querySelectorAll('[data-tab-jump]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.dataset.tabJump;
            activateTab(key);
            document.querySelector('.ov-product-tabs-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelector('a[href="#productCalculator"]')?.addEventListener('click', event => {
        event.preventDefault();
        activateTab('calculator');
        document.querySelector('.ov-product-tabs-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* Calculateur produit universel : calcule des unités vendables, donc quantité entière. */
    const calcType = document.querySelector('[data-calc-type]');
    const calcLength = document.querySelector('[data-calc-length]');
    const calcWidth = document.querySelector('[data-calc-width]');
    const calcThickness = document.querySelector('[data-calc-thickness]');
    const calcPieces = document.querySelector('[data-calc-pieces]');
    const calcWaste = document.querySelector('[data-calc-waste]');
    const calcButton = document.querySelector('[data-calc-button]');
    const calcQty = document.querySelector('[data-calc-qty]');
    const calcBudget = document.querySelector('[data-calc-budget]');
    const calcStock = document.querySelector('[data-calc-stock]');
    const calcMessage = document.querySelector('[data-calc-message]');
    const calcApply = document.querySelector('[data-calc-apply]');

    const profile = {
        price: number(page.dataset.productPrice),
        unit: page.dataset.productUnit || 'unité',
        minQty: Math.max(1, number(page.dataset.productMinQty) || 1),
        stock: Math.max(0, number(page.dataset.productStock)),
        availability: page.dataset.availabilityStatus || 'out_of_stock',
        canAdd: page.dataset.canAddToCart === '1',
        coverage: Math.max(0, number(page.dataset.coveragePerUnitM2)),
        unitVolume: Math.max(0, number(page.dataset.unitVolumeM3)),
        unitWeight: Math.max(0, number(page.dataset.unitWeightKg)),
        density: Math.max(0, number(page.dataset.densityKgM3)),
        unitLength: Math.max(0, number(page.dataset.unitLengthM)),
    };

    let recommendedQty = null;

    const showCalcFields = () => {
        const type = calcType?.value || 'piece';
        document.querySelectorAll('[data-calc-field]').forEach(label => {
            const key = label.dataset.calcField;
            const visible = type === 'piece'
                ? key === 'pieces'
                : type === 'linear'
                    ? key === 'length'
                    : type === 'surface'
                        ? ['length', 'width'].includes(key)
                        : ['length', 'width', 'thickness'].includes(key);
            label.hidden = !visible;
        });
    };

    const calculateNeeds = () => {
        if (!calcQty || !calcBudget || !calcStock || !calcMessage || !calcApply) return;

        const type = calcType?.value || 'piece';
        const length = Math.max(0, number(calcLength?.value));
        const width = Math.max(0, number(calcWidth?.value));
        const thicknessM = Math.max(0, number(calcThickness?.value)) / 100;
        const pieces = Math.max(0, number(calcPieces?.value));
        const wasteRate = Math.max(0, number(calcWaste?.value)) / 100;

        let rawUnits = 0;
        let basis = '';

        if (type === 'surface') {
            const targetArea = length * width;
            if (targetArea <= 0) return showInvalid('Renseignez une longueur et une largeur supérieures à zéro.');
            rawUnits = profile.coverage > 0 ? targetArea / profile.coverage : targetArea;
            basis = profile.coverage > 0
                ? `${targetArea.toLocaleString('fr-FR')} m² ÷ ${profile.coverage.toLocaleString('fr-FR')} m² par unité`
                : `${targetArea.toLocaleString('fr-FR')} m² à couvrir`;
        } else if (type === 'volume') {
            const targetVolume = length * width * thicknessM;
            if (targetVolume <= 0) return showInvalid('Renseignez longueur, largeur et épaisseur supérieures à zéro.');
            if (profile.unitVolume > 0) {
                rawUnits = targetVolume / profile.unitVolume;
                basis = `${targetVolume.toFixed(3)} m³ ÷ ${profile.unitVolume.toFixed(3)} m³ par unité`;
            } else if (profile.density > 0 && profile.unitWeight > 0) {
                rawUnits = (targetVolume * profile.density) / profile.unitWeight;
                basis = `Volume converti en poids selon la densité du produit`;
            } else {
                rawUnits = targetVolume;
                basis = `${targetVolume.toFixed(3)} m³ estimés`;
            }
        } else if (type === 'linear') {
            if (length <= 0) return showInvalid('Renseignez une longueur supérieure à zéro.');
            rawUnits = profile.unitLength > 0 ? length / profile.unitLength : length;
            basis = profile.unitLength > 0
                ? `${length.toLocaleString('fr-FR')} m ÷ ${profile.unitLength.toLocaleString('fr-FR')} m par unité`
                : `${length.toLocaleString('fr-FR')} m linéaires`;
        } else {
            if (pieces <= 0) return showInvalid('Renseignez le nombre d’unités souhaité.');
            rawUnits = pieces;
            basis = `${pieces.toLocaleString('fr-FR')} unité(s) souhaitée(s)`;
        }

        const withWaste = rawUnits * (1 + wasteRate);
        recommendedQty = Math.max(profile.minQty, Math.ceil(withWaste));
        calcQty.textContent = `${recommendedQty.toLocaleString('fr-FR')} ${profile.unit}`;
        calcBudget.textContent = money(recommendedQty * profile.price);

        if (profile.availability === 'on_order') {
            calcStock.textContent = 'Sur commande';
        } else if (profile.availability === 'preorder') {
            calcStock.textContent = 'Précommande';
        } else if (!profile.canAdd) {
            calcStock.textContent = 'Indisponible';
        } else if (recommendedQty <= profile.stock) {
            calcStock.textContent = `Stock suffisant (${profile.stock.toLocaleString('fr-FR')})`;
        } else {
            calcStock.textContent = `Stock actuel : ${profile.stock.toLocaleString('fr-FR')}`;
        }

        calcMessage.textContent = `${basis}. Marge appliquée : ${Math.round(wasteRate * 100)} %.`;
        calcApply.disabled = !profile.canAdd;
    };

    function showInvalid(message) {
        recommendedQty = null;
        if (calcQty) calcQty.textContent = '—';
        if (calcBudget) calcBudget.textContent = '—';
        if (calcStock) calcStock.textContent = '—';
        if (calcMessage) calcMessage.textContent = message;
        if (calcApply) calcApply.disabled = true;
    }

    calcType?.addEventListener('change', showCalcFields);
    calcButton?.addEventListener('click', calculateNeeds);
    calcApply?.addEventListener('click', () => {
        if (!recommendedQty || !qtyInput || !profile.canAdd) return;
        setQty(recommendedQty);
        document.querySelector('[data-product-form]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    showCalcFields();

    /* Prix négociable : le client propose un prix, jamais les seuils vendeur. */
    const negotiateBox = document.querySelector('[data-negotiate-box]');
    if (negotiateBox && page.dataset.isNegotiable === '1') {
        const negotiateInput = negotiateBox.querySelector('[data-negotiate-input]');
        const negotiateSubmit = negotiateBox.querySelector('[data-negotiate-submit]');
        const negotiateMessage = negotiateBox.querySelector('[data-negotiate-message]');
        const negotiateAddToCart = negotiateBox.querySelector('[data-negotiate-add-to-cart]');
        const negotiateUrl = page.dataset.negotiateUrl;
        const cartAddNegotiatedUrl = page.dataset.cartAddNegotiatedUrl;
        const loginUrl = page.dataset.loginUrl;
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        let accepted = null;

        const showMessage = (text, type) => {
            if (!negotiateMessage) return;
            negotiateMessage.textContent = text;
            negotiateMessage.hidden = !text;
            negotiateMessage.classList.toggle('is-success', type === 'success');
            negotiateMessage.classList.toggle('is-error', type === 'error');
        };

        negotiateSubmit?.addEventListener('click', async () => {
            if (!negotiateUrl) return;
            const proposedPrice = number(negotiateInput?.value);
            if (proposedPrice <= 0) {
                showMessage('Indiquez le prix que vous proposez.', 'error');
                return;
            }

            negotiateSubmit.disabled = true;
            if (negotiateAddToCart) negotiateAddToCart.hidden = true;
            accepted = null;

            try {
                const response = await fetch(negotiateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                    body: JSON.stringify({ proposed_price: proposedPrice }),
                });

                if ([401, 419].includes(response.status) || (response.redirected && response.url.includes('/login'))) {
                    window.location.href = loginUrl || '/login';
                    return;
                }

                const payload = await response.json().catch(() => ({}));

                if (payload.accepted) {
                    accepted = { negotiationId: payload.negotiation_id, price: proposedPrice };
                    showMessage(payload.message || 'Proposition acceptée !', 'success');
                    if (negotiateAddToCart) negotiateAddToCart.hidden = false;
                } else {
                    showMessage(payload.message || 'Proposition refusée. Essayez un montant plus proche du prix affiché.', 'error');
                }
            } catch (error) {
                showMessage('Connexion interrompue. Réessayez.', 'error');
            } finally {
                negotiateSubmit.disabled = false;
            }
        });

        negotiateAddToCart?.addEventListener('click', async () => {
            if (!accepted || !cartAddNegotiatedUrl) return;

            negotiateAddToCart.disabled = true;
            try {
                const response = await fetch(cartAddNegotiatedUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        product_id: Number(page.dataset.productId),
                        negotiated_price: accepted.price,
                        negotiation_id: accepted.negotiationId,
                        quantity: Math.max(1, number(qtyInput?.value) || 1),
                    }),
                });

                if ([401, 419].includes(response.status)) {
                    window.location.href = loginUrl || '/login';
                    return;
                }

                const payload = await response.json().catch(() => ({}));

                if (payload.success) {
                    showMessage(payload.message || 'Produit ajouté au panier.', 'success');
                    negotiateAddToCart.hidden = true;
                    if (typeof payload.cart_count === 'number') window.OvanieCart?.updateCartCount(payload.cart_count);
                    window.OvanieCart?.toast(payload.message || 'Produit ajouté au panier.');
                } else {
                    showMessage(payload.message || 'Impossible d’ajouter ce produit au panier.', 'error');
                }
            } catch (error) {
                showMessage('Connexion interrompue. Réessayez.', 'error');
            } finally {
                negotiateAddToCart.disabled = false;
            }
        });
    }

    refreshIcons();
});
