/**
 * OVANIE — jeton de sécurité toujours à jour.
 *
 * Une page (connexion, inscription, panier…) restée ouverte longtemps garde
 * un jeton CSRF qui peut ne plus correspondre à la session : l'envoi du
 * formulaire échouait alors avec l'erreur 419. Ce script récupère un jeton
 * frais quand l'utilisateur revient sur l'onglet, périodiquement, et juste
 * avant l'envoi d'un formulaire si le jeton date de plus de 10 minutes.
 */
(() => {
    if (window.__ovanieSessionKeepalive) return;
    window.__ovanieSessionKeepalive = true;

    const endpoint = document.querySelector('meta[name="ovanie-csrf-endpoint"]')?.content || '/csrf-token';
    const REFRESH_EVERY = 15 * 60 * 1000;
    const STALE_AFTER = 10 * 60 * 1000;
    let lastRefresh = Date.now();
    let pending = null;

    function applyToken(token) {
        if (!token) return;
        document.querySelectorAll('meta[name="csrf-token"]').forEach((meta) => meta.setAttribute('content', token));
        document.querySelectorAll('input[name="_token"]').forEach((input) => { input.value = token; });
        if (window.axios?.defaults?.headers?.common) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }

    function refresh() {
        if (pending) return pending;
        pending = fetch(endpoint, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (data && data.token) {
                    applyToken(data.token);
                    lastRefresh = Date.now();
                }
            })
            .catch(() => { /* hors ligne : le jeton actuel reste utilisé */ })
            .finally(() => { pending = null; });
        return pending;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.ovanieTokenFresh === '1') return;
        if (String(form.method || 'get').toLowerCase() === 'get' || !form.querySelector('input[name="_token"]')) return;
        if (Date.now() - lastRefresh < STALE_AFTER) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter || null;
        refresh().finally(() => {
            form.dataset.ovanieTokenFresh = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
            else form.submit();
            window.setTimeout(() => { delete form.dataset.ovanieTokenFresh; }, 1000);
        });
    }, true);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && Date.now() - lastRefresh > 60 * 1000) refresh();
    });
    window.addEventListener('pageshow', (event) => { if (event.persisted) refresh(); });
    window.setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, REFRESH_EVERY);
})();
