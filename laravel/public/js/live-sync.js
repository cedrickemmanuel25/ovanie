/**
 * OVANIE — synchronisation web ↔ mobile.
 *
 * Interroge régulièrement GET /live-sync (versions opaques par sujet : panier,
 * commandes, boutique, missions…). Quand un sujet affiché par la page change
 * (depuis l'application mobile, un autre onglet ou un autre acteur), la page
 * se met à jour seule :
 *  - le compteur du panier est rafraîchi partout, sans rechargement ;
 *  - la page est rechargée en conservant le défilement, sauf si l'utilisateur
 *    est en train de saisir : un bandeau « Actualiser » s'affiche alors.
 *
 * Une page peut gérer elle-même la mise à jour en écoutant l'événement
 * `ovanie:live-sync` et en appelant event.preventDefault().
 */
(() => {
    if (window.__ovanieLiveSync) return;

    const configEl = document.getElementById('ovanie-live-sync-config');
    let config = {};
    try { config = JSON.parse(configEl?.textContent || '{}'); } catch (_) { config = {}; }
    if (!config.endpoint) return;

    const pageTopics = new Set([...(config.topics || []), ...(config.watch || [])]);
    const notifyOnly = config.mode === 'notify';
    const HEADER = 'X-Ovanie-Client';
    const SCROLL_KEY = 'ovanie-live-sync-scroll';
    const RELOADS_KEY = 'ovanie-live-sync-reloads';
    const MIN_DELAY = 4000;
    const MAX_DELAY = 60000;

    const storage = {
        get(key) { try { return window.sessionStorage.getItem(key); } catch (_) { return null; } },
        set(key, value) { try { window.sessionStorage.setItem(key, value); } catch (_) { /* navigation privée */ } },
    };

    // Identifiant propre à l'onglet : les changements faits par cet onglet
    // (requêtes AJAX) ne déclenchent pas son propre rechargement.
    const clientId = (() => {
        let id = storage.get('ovanie-live-sync-client');
        if (!id || !/^[A-Za-z0-9_-]{6,64}$/.test(id)) {
            id = 'web-' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
            storage.set('ovanie-live-sync-client', id);
        }
        return id;
    })();

    const sameOrigin = (url) => {
        try { return new URL(url, window.location.href).origin === window.location.origin; } catch (_) { return false; }
    };

    const nativeFetch = window.fetch ? window.fetch.bind(window) : null;
    if (nativeFetch) {
        window.fetch = (input, init = {}) => {
            try {
                const url = typeof input === 'string' ? input : (input && input.url) || '';
                if (sameOrigin(url)) {
                    const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                    if (!headers.has(HEADER)) headers.set(HEADER, clientId);
                    init = { ...init, headers };
                }
            } catch (_) { /* ne jamais bloquer une requête */ }
            return nativeFetch(input, init);
        };
    }

    const nativeOpen = XMLHttpRequest.prototype.open;
    const nativeSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this.__ovanieSameOrigin = sameOrigin(url);
        return nativeOpen.call(this, method, url, ...rest);
    };
    XMLHttpRequest.prototype.send = function (body) {
        if (this.__ovanieSameOrigin) {
            try { this.setRequestHeader(HEADER, clientId); } catch (_) { /* déjà envoyé */ }
        }
        return nativeSend.call(this, body);
    };

    // Restaure la position de défilement après un rechargement automatique.
    try {
        const saved = JSON.parse(storage.get(SCROLL_KEY) || 'null');
        if (saved && saved.path === window.location.pathname + window.location.search && Date.now() - saved.at < 15000) {
            storage.set(SCROLL_KEY, '');
            const restore = () => window.scrollTo(0, saved.y || 0);
            if (document.readyState === 'complete') restore();
            else window.addEventListener('load', restore, { once: true });
        }
    } catch (_) { /* ignore */ }

    const dirtyForms = new WeakSet();
    document.addEventListener('input', (event) => {
        const form = event.target && event.target.closest ? event.target.closest('form') : null;
        // Les formulaires GET (recherche, filtres) ne bloquent pas la mise à jour.
        if (form && String(form.method || 'get').toLowerCase() !== 'get') dirtyForms.add(form);
    }, true);
    document.addEventListener('submit', (event) => { dirtyForms.delete(event.target); }, true);

    function isBusy() {
        const active = document.activeElement;
        if (active && (active.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName))) {
            const type = (active.getAttribute('type') || '').toLowerCase();
            if (!['button', 'submit', 'checkbox', 'radio', 'reset'].includes(type)) return true;
        }
        if ([...document.forms].some((form) => dirtyForms.has(form))) return true;
        if (document.querySelector('dialog[open], [aria-modal="true"]:not([hidden]), .modal.show, .is-open[role="dialog"]')) return true;
        const selection = window.getSelection ? String(window.getSelection() || '') : '';
        return selection.trim().length > 0;
    }

    function recentReloads() {
        let list = [];
        try { list = JSON.parse(storage.get(RELOADS_KEY) || '[]'); } catch (_) { list = []; }
        return (Array.isArray(list) ? list : []).filter((t) => Date.now() - t < 60000);
    }

    let banner = null;
    function showBanner() {
        if (banner) return;
        banner = document.createElement('div');
        banner.setAttribute('role', 'status');
        banner.style.cssText = [
            'position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:7000',
            'display:flex;align-items:center;gap:12px;max-width:calc(100vw - 32px)',
            'padding:10px 12px 10px 16px;border-radius:10px;background:#0b1b33;color:#fff',
            'font:600 13px/1.35 Inter,Arial,sans-serif;box-shadow:0 16px 36px rgba(2,11,28,.28)',
        ].join(';');
        const text = document.createElement('span');
        text.textContent = 'Des changements ont été faits sur un autre appareil.';
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Actualiser';
        button.style.cssText = 'border:0;border-radius:8px;padding:7px 12px;background:#e87020;color:#fff;font:700 13px Inter,Arial,sans-serif;cursor:pointer';
        button.addEventListener('click', () => reloadNow(true));
        banner.append(text, button);
        document.body.appendChild(banner);
    }

    function reloadNow(force) {
        const reloads = recentReloads();
        if (!force && reloads.length >= 3) {
            // Garde-fou anti-boucle : on laisse l'utilisateur décider.
            showBanner();
            return;
        }
        reloads.push(Date.now());
        storage.set(RELOADS_KEY, JSON.stringify(reloads));
        storage.set(SCROLL_KEY, JSON.stringify({
            path: window.location.pathname + window.location.search,
            y: window.scrollY || 0,
            at: Date.now(),
        }));
        window.location.reload();
    }

    async function refreshCartCount() {
        try {
            const response = await nativeFetch(config.cartCountEndpoint || '/cart/count', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();
            const value = String(Number(data.count || 0));
            document.querySelectorAll('[data-cart-count], #cart-count, #cartCount, .cart-count').forEach((el) => {
                el.dataset.count = value;
                el.textContent = el.classList.contains('cart-count') && !el.hasAttribute('data-cart-count') ? `(${value})` : value;
            });
        } catch (_) { /* hors ligne */ }
    }

    let baseline = null;
    let pendingReload = false;
    let delay = Math.max(MIN_DELAY, Number(config.pollMs) || 8000);
    let timer = null;
    let inFlight = false;

    function handleChanges(changed) {
        if (changed.includes('cart')) refreshCartCount();

        const relevant = changed.filter((topic) => pageTopics.has(topic));
        const event = new CustomEvent('ovanie:live-sync', {
            cancelable: true,
            detail: { changed, relevant },
        });
        window.dispatchEvent(event);
        if (!relevant.length || event.defaultPrevented) return;

        if (notifyOnly) { showBanner(); return; }
        pendingReload = true;
    }

    function maybeReload() {
        if (!pendingReload) return;
        if (isBusy()) { showBanner(); return; }
        pendingReload = false;
        reloadNow(false);
    }

    async function poll() {
        if (inFlight || document.visibilityState === 'hidden') return;
        inFlight = true;
        try {
            const url = new URL(config.endpoint, window.location.href);
            if (config.watch && config.watch.length) url.searchParams.set('watch', config.watch.join(','));
            const response = await nativeFetch(url.toString(), {
                headers: { Accept: 'application/json', [HEADER]: clientId },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            const topics = data.topics || {};
            const origins = data.origins || {};
            if (data.poll_after_ms) delay = Math.max(MIN_DELAY, Number(data.poll_after_ms));

            if (baseline) {
                const changed = Object.keys(topics).filter((topic) =>
                    baseline[topic] !== undefined
                    && baseline[topic] !== topics[topic]
                    && origins[topic] !== clientId);
                if (changed.length) handleChanges(changed);
            }
            baseline = topics;
            maybeReload();
        } catch (_) {
            delay = Math.min(MAX_DELAY, delay * 2);
        } finally {
            inFlight = false;
        }
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(async () => {
            await poll();
            schedule();
        }, delay);
    }

    let lastPollAt = 0;
    function pollSoon() {
        if (Date.now() - lastPollAt < 2000) return;
        lastPollAt = Date.now();
        window.clearTimeout(timer);
        poll().finally(schedule);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') pollSoon();
    });
    window.addEventListener('focus', pollSoon);
    window.addEventListener('online', pollSoon);
    document.addEventListener('focusout', () => window.setTimeout(maybeReload, 300));

    window.__ovanieLiveSync = { clientId, poll: pollSoon };
    pollSoon();
})();
