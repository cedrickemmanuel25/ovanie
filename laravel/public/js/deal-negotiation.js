document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('dealModal');
    if (!modal) return;

    const closeBtn = modal.querySelector('.deal-close');
    const productNameEl = document.getElementById('dealProductName');
    const labelEl = document.getElementById('dealLabel');
    const priceEl = document.getElementById('dealPrice');
    const dealActions = document.getElementById('dealActions');
    const offerZone = document.getElementById('offerZone');
    const lastOfferZone = document.getElementById('lastOfferZone');
    const yesBtn = document.getElementById('dealYes');
    const noBtn = document.getElementById('dealNo');
    const submitOfferBtn = document.getElementById('submitOffer');
    const acceptLastOfferBtn = document.getElementById('acceptLastOffer');
    const clientOfferInput = document.getElementById('clientOffer');
    const timerEl = document.getElementById('timer');
    const lastOfferPriceEl = document.getElementById('lastOfferPrice');
    const expiredMsg = document.getElementById('expiredMsg');

    const dot1 = document.getElementById('step-dot-1');
    const dot2 = document.getElementById('step-dot-2');
    const dot3 = document.getElementById('step-dot-3');

    let currentProduct = null;
    let currentStep = 1;
    let currentPrice = 0;
    let timerInterval = null;
    let remainingSeconds = 120;
    let offerExpired = false;

    document.querySelectorAll('.deal-surprise-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const wrapper = btn.closest('.ovanie-price-deal');
            if (!wrapper) return;

            currentProduct = {
                id: parseInt(wrapper.dataset.productId || 0),
                name: wrapper.dataset.productName || 'Produit OVANIE',
                normalPrice: parseInt(wrapper.dataset.normalPrice || 0),
                price1: parseInt(wrapper.dataset.priceExpensive || 0),
                price2: parseInt(wrapper.dataset.priceCheaper || 0),
                price3: parseInt(wrapper.dataset.priceFloor || 0)
            };

            if (!currentProduct.id || !currentProduct.price1 || !currentProduct.price2 || !currentProduct.price3) {
                alert('Configuration de négociation invalide.');
                return;
            }

            openDealModal();
        });
    });

    function openDealModal() {
        currentStep = 1;
        offerExpired = false;
        currentPrice = currentProduct.price1;

        resetButtons();

        if (productNameEl) productNameEl.textContent = currentProduct.name;
        if (labelEl) labelEl.textContent = '⬆️ Étape 1 — Première offre du vendeur';
        if (priceEl) priceEl.textContent = formatPrice(currentPrice);
        if (noBtn) noBtn.textContent = '🔄 Continuer la négociation';

        if (dealActions) dealActions.style.display = 'flex';
        if (offerZone) offerZone.style.display = 'none';
        if (lastOfferZone) lastOfferZone.style.display = 'none';
        if (expiredMsg) expiredMsg.style.display = 'none';
        if (clientOfferInput) {
            clientOfferInput.value = '';
            clientOfferInput.style.borderColor = '';
        }

        setStep(1);
        clearInterval(timerInterval);
        modal.classList.add('active');
    }

    function setStep(step) {
        [dot1, dot2, dot3].forEach(function (dot, index) {
            if (!dot) return;

            const active = index + 1 <= step;
            dot.style.background = active ? '#10b981' : '#e5e7eb';
            dot.style.color = active ? '#fff' : '#9ca3af';
        });
    }

    if (yesBtn) {
        yesBtn.addEventListener('click', function () {
            if (!offerExpired && currentProduct) {
                addToCart(currentProduct.id, currentPrice);
            }
        });
    }

    if (noBtn) {
        noBtn.addEventListener('click', function () {
            if (!currentProduct) return;

            if (currentStep === 1) {
                currentStep = 2;
                currentPrice = currentProduct.price2;

                setStep(2);
                if (labelEl) labelEl.textContent = '↔️ Étape 2 — Deuxième offre';
                if (priceEl) priceEl.textContent = formatPrice(currentPrice);
                if (noBtn) noBtn.textContent = '💬 Faire ma propre offre';
            } else if (currentStep === 2) {
                currentStep = 3;

                setStep(3);
                if (dealActions) dealActions.style.display = 'none';
                if (offerZone) offerZone.style.display = 'flex';
                if (labelEl) labelEl.textContent = '💬 Étape 3 — Votre offre';
                if (priceEl) priceEl.textContent = 'À vous de proposer';
            }
        });
    }

    if (submitOfferBtn) {
        submitOfferBtn.addEventListener('click', function () {
            if (!currentProduct || !clientOfferInput) return;

            const offer = parseInt(clientOfferInput.value || 0);

            if (!offer || offer <= 0) {
                clientOfferInput.style.borderColor = '#ef4444';
                clientOfferInput.placeholder = 'Saisissez un montant valide !';
                return;
            }

            clientOfferInput.style.borderColor = '';

            if (offer >= currentProduct.price2) {
                currentPrice = offer;
                addToCart(currentProduct.id, currentPrice);
            } else {
                currentPrice = currentProduct.price3;

                if (offerZone) offerZone.style.display = 'none';
                if (lastOfferZone) lastOfferZone.style.display = 'flex';
                if (labelEl) labelEl.textContent = '🔥 Dernière offre — Décidez vite !';
                if (priceEl) priceEl.textContent = formatPrice(currentPrice);
                if (lastOfferPriceEl) lastOfferPriceEl.textContent = formatPrice(currentPrice);

                startCountdown();
            }
        });
    }

    if (acceptLastOfferBtn) {
        acceptLastOfferBtn.addEventListener('click', function () {
            if (!offerExpired && currentProduct) {
                addToCart(currentProduct.id, currentPrice);
            }
        });
    }

    function startCountdown() {
        clearInterval(timerInterval);

        remainingSeconds = 120;
        offerExpired = false;

        if (timerEl) {
            timerEl.style.color = '#ef4444';
            timerEl.style.animation = '';
        }

        updateTimer();

        timerInterval = setInterval(function () {
            remainingSeconds--;
            updateTimer();

            if (timerEl) {
                timerEl.style.color = remainingSeconds <= 30 ? '#dc2626' : '#ef4444';

                if (remainingSeconds <= 10) {
                    timerEl.style.animation = 'pulse 0.5s infinite';
                }
            }

            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                offerExpired = true;
                showExpiredUI();
            }
        }, 1000);
    }

    function updateTimer() {
        const minutes = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
        const seconds = String(remainingSeconds % 60).padStart(2, '0');

        if (timerEl) {
            timerEl.textContent = minutes + ':' + seconds;
        }
    }

    function showExpiredUI() {
        if (timerEl) {
            timerEl.textContent = '00:00';
            timerEl.style.color = '#dc2626';
            timerEl.style.animation = '';
        }

        if (acceptLastOfferBtn) acceptLastOfferBtn.style.display = 'none';
        if (expiredMsg) expiredMsg.style.display = 'block';

        setTimeout(function () {
            closeDealModal();
        }, 5000);
    }

    function addToCart(productId, negotiatedPrice) {
        clearInterval(timerInterval);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');

        if (!csrfMeta) {
            alert('Erreur CSRF. Veuillez recharger la page.');
            return;
        }

        setButtonsDisabled(true);

        fetch('/cart/add-negotiated', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfMeta.content
            },
            body: JSON.stringify({
                product_id: productId,
                negotiated_price: negotiatedPrice
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    closeDealModal();
                    showToast('✅ Produit ajouté au panier à ' + formatPrice(negotiatedPrice) + ' !');

                    setTimeout(function () {
                        window.location.href = '/cart';
                    }, 1200);
                } else {
                    alert(data.message || "Impossible d'ajouter au panier.");
                    setButtonsDisabled(false);
                }
            })
            .catch(function () {
                alert('Erreur réseau. Veuillez réessayer.');
                setButtonsDisabled(false);
            });
    }

    function resetButtons() {
        setButtonsDisabled(false);

        if (yesBtn) yesBtn.style.display = '';
        if (acceptLastOfferBtn) acceptLastOfferBtn.style.display = 'block';
    }

    function setButtonsDisabled(disabled) {
        if (yesBtn) yesBtn.disabled = disabled;
        if (noBtn) noBtn.disabled = disabled;
        if (submitOfferBtn) submitOfferBtn.disabled = disabled;
        if (acceptLastOfferBtn) acceptLastOfferBtn.disabled = disabled;
    }

    function closeDealModal() {
        clearInterval(timerInterval);
        offerExpired = false;

        if (timerEl) {
            timerEl.style.animation = '';
        }

        modal.classList.remove('active');
        resetButtons();
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeDealModal);
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeDealModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            closeDealModal();
        }
    });

    function showToast(message) {
        const oldToast = document.querySelector('.ovanie-deal-toast');
        if (oldToast) oldToast.remove();

        const toast = document.createElement('div');
        toast.className = 'ovanie-deal-toast';
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:14px 28px;border-radius:12px;font-weight:700;font-size:15px;z-index:999999;box-shadow:0 8px 30px rgba(0,0,0,.2);';

        document.body.appendChild(toast);

        setTimeout(function () {
            toast.remove();
        }, 3000);
    }

    function formatPrice(price) {
        return new Intl.NumberFormat('fr-FR').format(Number(price || 0)) + ' FCFA';
    }
});