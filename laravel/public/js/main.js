(() => {
    const handledForms = new WeakSet();
    const handledButtons = new WeakSet();

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function updateCartCount(count) {
        const value = String(Number(count || 0));
        document.querySelectorAll('[data-cart-count], #cart-count, #cartCount, .cart-count').forEach((el) => {
            el.dataset.count = value;
            el.textContent = el.classList.contains('cart-count') && !el.hasAttribute('data-cart-count')
                ? `(${value})`
                : value;
        });

        document.querySelectorAll('.ovanie-cart, .cart-link').forEach((el) => {
            el.classList.add('highlight');
            window.setTimeout(() => el.classList.remove('highlight'), 900);
        });
    }

    function toast(message, type = 'success') {
        let box = document.querySelector('[data-ovanie-toast]');
        if (!box) {
            box = document.createElement('div');
            box.setAttribute('data-ovanie-toast', '');
            box.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:6000;display:grid;gap:10px;max-width:min(360px,calc(100vw - 36px));';
            document.body.appendChild(box);
        }

        const item = document.createElement('div');
        item.textContent = message || 'Action effectuee.';
        item.style.cssText = [
            'padding:12px 14px;border-radius:8px;color:#fff;font:800 13px/1.35 Inter,Arial,sans-serif',
            'box-shadow:0 16px 36px rgba(2,11,28,.22);border:1px solid rgba(255,255,255,.18)',
            `background:${type === 'error' ? '#dc2626' : type === 'warning' ? '#e87020' : '#009e60'}`
        ].join(';');
        box.appendChild(item);
        window.setTimeout(() => item.remove(), 3600);
    }

    function isCartAddAction(action) {
        try {
            const url = new URL(action, window.location.origin);
            return /^\/cart\/add\/[^/]+$/.test(url.pathname);
        } catch (_) {
            return false;
        }
    }

    function setButtonLabel(button, html) {
        if (!button) return;
        if (button.dataset.originalHtml === undefined) {
            button.dataset.originalHtml = button.innerHTML;
        }
        button.innerHTML = html;
        if (window.lucide) window.lucide.createIcons();
    }

    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(text.includes('<html') ? 'Le serveur a retourne une page HTML au lieu du JSON attendu.' : 'Reponse panier invalide.');
        }

        return response.json();
    }

    async function submitCart(action, formData, button) {
        if (!action || !isCartAddAction(action)) return;
        if (button && handledButtons.has(button)) return;
        if (button) handledButtons.add(button);

        setButtonLabel(button, 'Ajout...');
        if (button) button.disabled = true;

        try {
            const response = await fetch(action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: formData,
            });

            if (response.redirected && response.url.includes('/login')) {
                window.location.href = response.url;
                return;
            }

            if ([401, 419].includes(response.status)) {
                window.location.href = '/login';
                return;
            }

            const data = await parseJsonResponse(response);
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Impossible d’ajouter ce produit au panier.');
            }

            updateCartCount(data.cart_count ?? data.item_count ?? 0);
            document.querySelectorAll('[data-cart-total]').forEach((el) => {
                el.textContent = data.cart_total ?? data.grand_total ?? '';
            });
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }
            setButtonLabel(button, 'Ajouté');
            toast(data.message || 'Produit ajouté au panier');
        } catch (error) {
            setButtonLabel(button, 'Erreur');
            toast(error.message || 'Erreur lors de l’ajout au panier.', 'error');
        } finally {
            window.setTimeout(() => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                    handledButtons.delete(button);
                    if (window.lucide) window.lucide.createIcons();
                }
            }, 1200);
        }
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !isCartAddAction(form.action) || handledForms.has(form)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        handledForms.add(form);

        const button = event.submitter || form.querySelector('button[type="submit"], [data-cart-button]');
        const formData = event.submitter ? new FormData(form, event.submitter) : new FormData(form);
        if (button?.dataset.buyNow === '1' && !formData.has('action_type')) {
            formData.append('action_type', 'buy_now');
        }
        submitCart(form.action, formData, button).finally(() => handledForms.delete(form));
    }, true);

    document.addEventListener('click', (event) => {
        const favoriteButton = event.target.closest('[data-favorite-button], [data-favorite]');
        if (favoriteButton) {
            const url = favoriteButton.dataset.favoriteUrl;
            if (!url) return;

            event.preventDefault();

            if (url.includes('/login')) {
                window.location.href = url;
                return;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
            }).then(async (response) => {
                if (response.redirected && response.url.includes('/login')) {
                    window.location.href = response.url;
                    return;
                }

                if ([401, 419].includes(response.status)) {
                    window.location.href = '/login';
                    return;
                }

                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'Impossible de mettre a jour les favoris.');
                }

                const active = Boolean(data.added ?? data.is_favorite ?? data.favorite);
                favoriteButton.classList.toggle('is-active', active);
                favoriteButton.setAttribute('aria-label', active ? 'Retirer des favoris' : 'Ajouter aux favoris');
                toast(data.message || (active ? 'Produit ajoute aux favoris' : 'Produit retire des favoris'));
            }).catch((error) => {
                toast(error.message || 'Erreur favoris.', 'error');
            });

            return;
        }

        const button = event.target.closest('[data-cart-button]');
        if (!button || button.closest('form')) return;

        const action = button.dataset.cartUrl;
        if (!action || !isCartAddAction(action)) return;

        event.preventDefault();
        const formData = new FormData();
        formData.append('quantity', button.dataset.quantity || '1');
        submitCart(action, formData, button);
    });

    // Le panier est partagé avec l'application mobile. Actualiser le badge
    // régulièrement permet de refléter un ajout effectué sur un autre appareil
    // sans obliger l'utilisateur à recharger la page Web.
    async function refreshRemoteCartCount() {
        if (document.hidden) return;
        try {
            const response = await fetch('/cart/count', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();
            const count = Number(data.count || 0);
            updateCartCount(count);
        } catch (_) {
            // Conserver silencieusement la dernière valeur en cas de coupure.
        }
    }

    window.setInterval(refreshRemoteCartCount, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshRemoteCartCount();
    });

    window.OvanieCart = { updateCartCount, toast };
})();
