(() => {
    'use strict';

    const form = document.querySelector('[data-smart-search]');
    if (!form) return;

    const input = form.querySelector('[data-search-input]');
    const category = form.querySelector('[data-search-category]');
    const clearButton = form.querySelector('[data-search-clear]');
    const panel = form.querySelector('[data-search-panel]');
    const content = form.querySelector('[data-search-content]');
    const loading = form.querySelector('[data-search-loading]');
    const empty = form.querySelector('[data-search-empty]');
    const footer = form.querySelector('[data-search-footer]');
    const suggestionsUrl = form.dataset.suggestionsUrl;

    if (!input || !panel || !content || !suggestionsUrl) return;

    const RECENT_KEY = 'ovanie.search.recent.v1';
    const MAX_RECENT = 6;
    let debounceTimer = null;
    let controller = null;
    let activeIndex = -1;
    let interactiveItems = [];
    let lastQuery = null;

    const icon = (name) => {
        const icons = {
            arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
            category: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h6v6H4zM14 4h6v6h-6zM14 14h6v6h-6zM4 17h6"/></svg>',
            clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
            trend: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 16 5-5 4 4 7-8"/><path d="M15 7h5v5"/></svg>',
        };
        return icons[name] || '';
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const money = (value) => new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(Number(value || 0)) + ' FCFA';

    const getRecent = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            return Array.isArray(parsed) ? parsed.filter(Boolean).slice(0, MAX_RECENT) : [];
        } catch (_) {
            return [];
        }
    };

    const saveRecent = (term) => {
        const clean = String(term || '').trim();
        if (clean.length < 2) return;
        const next = [clean, ...getRecent().filter((item) => item.toLowerCase() !== clean.toLowerCase())]
            .slice(0, MAX_RECENT);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    };

    const openPanel = () => {
        panel.hidden = false;
        form.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
    };

    const closePanel = () => {
        panel.hidden = true;
        form.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        interactiveItems = [];
    };

    const setState = ({ isLoading = false, isEmpty = false, showFooter = false } = {}) => {
        loading.hidden = !isLoading;
        empty.hidden = !isEmpty;
        footer.hidden = !showFooter;
        content.hidden = isLoading || isEmpty;
    };

    const refreshInteractiveItems = () => {
        interactiveItems = [...panel.querySelectorAll('[data-search-item]')];
        activeIndex = -1;
    };

    const activateItem = (index) => {
        if (!interactiveItems.length) return;
        interactiveItems.forEach((item) => item.classList.remove('is-active'));
        activeIndex = (index + interactiveItems.length) % interactiveItems.length;
        const item = interactiveItems[activeIndex];
        item.classList.add('is-active');
        item.scrollIntoView({ block: 'nearest' });
    };

    const renderChips = (title, items, type) => {
        if (!items?.length) return '';
        const iconName = type === 'recent' ? 'clock' : 'trend';
        return `
            <section class="ovanie-search-group">
                <h3 class="ovanie-search-group__title">${escapeHtml(title)}</h3>
                <div class="ovanie-search-chips">
                    ${items.map((term) => `
                        <button type="button" class="ovanie-search-chip" data-search-item data-search-term="${escapeHtml(term)}">
                            ${icon(iconName)}
                            <span>${escapeHtml(term)}</span>
                        </button>
                    `).join('')}
                </div>
            </section>
        `;
    };

    const renderInitial = (popular = []) => {
        const recent = getRecent();
        content.innerHTML = [
            renderChips('Recherches récentes', recent, 'recent'),
            renderChips('Recherches populaires', popular, 'popular'),
        ].join('');

        setState({ isLoading: false, isEmpty: !recent.length && !popular.length, showFooter: false });
        refreshInteractiveItems();
        bindDynamicActions();
        openPanel();
    };

    const renderResults = (payload) => {
        const products = Array.isArray(payload.products) ? payload.products : [];
        const categories = Array.isArray(payload.categories) ? payload.categories : [];
        const hasResults = products.length || categories.length;

        let html = '';

        if (products.length) {
            html += `
                <section class="ovanie-search-group">
                    <h3 class="ovanie-search-group__title">
                        <span>Produits</span>
                        <span>${products.length} suggestion${products.length > 1 ? 's' : ''}</span>
                    </h3>
                    <div class="ovanie-search-products">
                        ${products.map((product) => `
                            <a class="ovanie-search-result" href="${escapeHtml(product.url)}" data-search-item data-product-result>
                                <span class="ovanie-search-result__image">
                                    ${product.image_url ? `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}" loading="lazy">` : ''}
                                </span>
                                <span class="ovanie-search-result__copy">
                                    <span class="ovanie-search-result__category">${escapeHtml(product.category?.name || 'Produit OVANIE')}</span>
                                    <span class="ovanie-search-result__name">${escapeHtml(product.name)}</span>
                                    <span class="ovanie-search-result__meta">
                                        <strong class="ovanie-search-result__price">${money(product.price)}</strong>
                                        <span class="ovanie-search-result__unit">/ ${escapeHtml(product.unit || 'unité')}</span>
                                    </span>
                                </span>
                                <span class="ovanie-search-result__arrow">${icon('arrow')}</span>
                            </a>
                        `).join('')}
                    </div>
                </section>
            `;
        }

        if (categories.length) {
            html += `
                <section class="ovanie-search-group">
                    <h3 class="ovanie-search-group__title">Catégories correspondantes</h3>
                    <div class="ovanie-search-category-list">
                        ${categories.map((item) => `
                            <a class="ovanie-search-category" href="${escapeHtml(item.url)}" data-search-item data-category-result data-category-slug="${escapeHtml(item.slug)}">
                                <span class="ovanie-search-category__icon">${icon('category')}</span>
                                <strong>${escapeHtml(item.name)}</strong>
                            </a>
                        `).join('')}
                    </div>
                </section>
            `;
        }

        content.innerHTML = html;
        setState({ isLoading: false, isEmpty: !hasResults, showFooter: Boolean(hasResults) });
        refreshInteractiveItems();
        bindDynamicActions();
        openPanel();
    };

    const bindDynamicActions = () => {
        panel.querySelectorAll('[data-search-term]').forEach((button) => {
            button.addEventListener('click', () => {
                input.value = button.dataset.searchTerm || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            });
        });

        panel.querySelectorAll('[data-product-result]').forEach((link) => {
            link.addEventListener('click', () => saveRecent(input.value));
        });
    };

    const fetchSuggestions = async (query) => {
        controller?.abort();
        controller = new AbortController();
        lastQuery = query;

        setState({ isLoading: true, isEmpty: false, showFooter: false });
        openPanel();

        const url = new URL(suggestionsUrl, window.location.origin);
        url.searchParams.set('q', query);
        if (category?.value) url.searchParams.set('category', category.value);
        url.searchParams.set('limit', '6');

        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Search request failed');
            const payload = await response.json();
            if (lastQuery !== query) return;
            renderResults(payload);
        } catch (error) {
            if (error.name === 'AbortError') return;
            content.innerHTML = '';
            setState({ isLoading: false, isEmpty: true, showFooter: false });
            openPanel();
        }
    };

    const fetchInitial = async () => {
        controller?.abort();
        controller = new AbortController();
        try {
            const url = new URL(suggestionsUrl, window.location.origin);
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = response.ok ? await response.json() : {};
            renderInitial(Array.isArray(payload.popular) ? payload.popular : []);
        } catch (error) {
            if (error.name !== 'AbortError') renderInitial([]);
        }
    };

    const scheduleSearch = () => {
        const query = input.value.trim();
        clearButton.hidden = query === '';
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            debounceTimer = setTimeout(fetchInitial, 80);
            return;
        }

        debounceTimer = setTimeout(() => fetchSuggestions(query), 220);
    };

    input.addEventListener('focus', scheduleSearch);
    input.addEventListener('input', scheduleSearch);

    category?.addEventListener('change', () => {
        if (input.value.trim().length >= 2) fetchSuggestions(input.value.trim());
    });

    clearButton?.addEventListener('click', () => {
        input.value = '';
        clearButton.hidden = true;
        fetchInitial();
        input.focus();
    });

    form.addEventListener('submit', () => {
        saveRecent(input.value);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (panel.hidden) scheduleSearch();
            activateItem(activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            activateItem(activeIndex - 1);
            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0 && interactiveItems[activeIndex]) {
            event.preventDefault();
            interactiveItems[activeIndex].click();
            return;
        }

        if (event.key === 'Escape') {
            closePanel();
            input.blur();
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) closePanel();
    });

    window.addEventListener('resize', () => {
        if (!panel.hidden) panel.scrollTop = 0;
    }, { passive: true });

    clearButton.hidden = input.value.trim() === '';
})();
