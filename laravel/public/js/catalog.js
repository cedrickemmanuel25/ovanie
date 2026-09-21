import { apiRoutes } from './routes.js';

const DEFAULTS = {
    perPage: 12,
    sort: 'popular',
    debounce: 420,
};

const state = {
    page: 1,
    lastPage: 1,
    total: 0,
    perPage: DEFAULTS.perPage,
    sort: DEFAULTS.sort,
    loading: false,
    lazyObserver: null,
    filters: {
        search: '',
        category: [],
        offer: [],
        stock: [],
        unit: [],
        rating: [],
        min_price: '',
        max_price: '',
    },
};

const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));

const refreshIcons = () => window.lucide?.createIcons();

function debounce(fn, wait = DEFAULTS.debounce) {
    let timer;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
}

function normalize(value = '') {
    return String(value ?? '').trim();
}

function normalizeToken(value = '') {
    return normalize(value)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function escapeHtml(value = '') {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function cssEscape(value) {
    return window.CSS?.escape ? window.CSS.escape(value) : String(value).replace(/"/g, '\\"');
}

function formatPrice(value) {
    const number = Number(value);
    if (!Number.isFinite(number) || number <= 0) return 'Prix à confirmer';
    return `${Math.ceil(number).toLocaleString('fr-FR')} FCFA`;
}

function formatUnit(unit = '') {
    const key = normalizeToken(unit);
    const labels = {
        sac: 'sac', tonne: 'tonne', t: 'tonne', m2: 'm²', 'm²': 'm²',
        m3: 'm³', 'm³': 'm³', piece: 'pièce', pieces: 'pièce',
        unite: 'unité', rouleau: 'rouleau', seau: 'seau', palette: 'palette',
        carton: 'carton', paquet: 'paquet', barre: 'barre', bidon: 'bidon',
        kg: 'kg', litre: 'litre',
    };
    return labels[key] || unit || 'unité';
}

function productUrl(product) {
    return normalize(product.url) || `/product/${encodeURIComponent(product.slug || product.id)}`;
}

function imageUrl(product) {
    const gallery = Array.isArray(product.gallery) ? product.gallery[0] : null;
    const images = Array.isArray(product.images) ? product.images : [];
    const relation = images.find(item => item?.is_main || item?.is_primary) || images[0];

    return product.card_image_url
        || product.main_image_url
        || product.image_url
        || product.image
        || relation?.card_url
        || relation?.public_url
        || relation?.url
        || gallery
        || '/images/product-placeholder.svg';
}

function publicPrice(product) {
    const finalPrice = Number(product.final_price);
    if (Number.isFinite(finalPrice) && finalPrice > 0) return finalPrice;

    const promo = Number(product.promo_price);
    if (Number.isFinite(promo) && promo > 0) return promo;

    const price = Number(product.price);
    return Number.isFinite(price) ? price : 0;
}

function originalPublicPrice(product) {
    const original = Number(product.original_public_price);
    const current = publicPrice(product);
    return Number.isFinite(original) && original > current ? original : null;
}

function discountPercent(product) {
    const oldPrice = originalPublicPrice(product);
    const current = publicPrice(product);
    if (!oldPrice || !current || current >= oldPrice) return null;
    return Math.max(1, Math.round(((oldPrice - current) / oldPrice) * 100));
}

function isNew(product) {
    if (!product.created_at) return false;
    const created = new Date(product.created_at);
    if (Number.isNaN(created.getTime())) return false;
    return (Date.now() - created.getTime()) / 86400000 <= 14;
}

function productBadge(product) {
    const discount = discountPercent(product);

    if (product.is_gift_card === true) {
        const label = product.gift_card_type || 'Carte OVANIE';
        return `<span class="ovcat-product-badge ovcat-badge--promo">${escapeHtml(label)}</span>`;
    }

    if (product.is_black_friday_active) {
        return '<span class="ovcat-product-badge ovcat-badge--black">Black Friday</span>';
    }
    if (product.is_flash_active) {
        return '<span class="ovcat-product-badge ovcat-badge--flash">Vente flash</span>';
    }
    if (product.is_promo_active && discount) {
        return `<span class="ovcat-product-badge ovcat-badge--promo">Promo -${discount}%</span>`;
    }
    if (Number(product.sales || 0) >= 5) {
        return '<span class="ovcat-product-badge ovcat-badge--top">Meilleure vente</span>';
    }
    if (product.is_boost_active || product.free_boosted_by_ovanie) {
        return '<span class="ovcat-product-badge ovcat-badge--boost">À la une</span>';
    }
    if (product.is_verified === true || product.verified === true) {
        return '<span class="ovcat-product-badge ovcat-badge--verified">Vérifié</span>';
    }
    if (isNew(product)) {
        return '<span class="ovcat-product-badge ovcat-badge--new">Nouveau</span>';
    }

    return '';
}

function ratingHtml(product) {
    const count = Number(product.reviews_count || 0);
    const rating = Number(product.average_rating || product.rating || 0);

    if (!count || !rating) {
        return '<span class="ovcat-product-rating ovcat-product-rating--empty"><i data-lucide="star"></i> Nouveau</span>';
    }

    return `
        <span class="ovcat-product-rating">
            <i data-lucide="star"></i>
            <strong>${rating.toFixed(1)}</strong>
            <small>(${count})</small>
        </span>
    `;
}

function availabilityMeta(product) {
    const status = normalize(product.availability_status || (Number(product.stock || 0) > 0 ? 'in_stock' : 'out_of_stock'));
    const label = product.availability_label || ({
        in_stock: 'En stock',
        on_order: 'Sur commande',
        preorder: 'Précommande',
        out_of_stock: 'Rupture temporaire',
    }[status] || 'Disponibilité à confirmer');

    const order = status === 'on_order' || status === 'preorder';

    return {
        status,
        label,
        className: order ? 'is-order' : status === 'out_of_stock' ? 'is-out' : 'is-stock',
        icon: order ? 'clock-3' : status === 'out_of_stock' ? 'circle-x' : 'circle-check-big',
    };
}

function catalogBasePath() {
    const pathname = window.location.pathname;
    if (pathname.startsWith('/categories/')) return pathname;
    if (pathname === '/meilleures-ventes' || pathname === '/nouveautes') return pathname;
    const marker = '/catalog';
    const index = pathname.indexOf(marker);
    return index >= 0 ? `${pathname.slice(0, index)}${marker}` : marker;
}

function multiParam(params, key) {
    const values = params.getAll(key);
    if (values.length > 1) {
        return values.flatMap(value => value.split(',')).map(normalize).filter(Boolean);
    }
    const value = params.get(key);
    return value ? value.split(',').map(normalize).filter(Boolean) : [];
}

function checkedValues(name) {
    return $$(`input[name="${name}"]:checked`).map(input => input.value);
}

function setChecked(name, values) {
    const normalized = values.map(normalizeToken);
    $$(`input[name="${name}"]`).forEach(input => {
        input.checked = normalized.includes(normalizeToken(input.value));
    });
}

function dedupeFilters() {
    ['category', 'offer', 'stock', 'unit', 'rating'].forEach(key => {
        state.filters[key] = Array.from(new Set((state.filters[key] || []).map(normalize).filter(Boolean)));
    });
}

function initStateFromUrl() {
    const params = new URLSearchParams(window.location.search);

    state.page = Number(params.get('page')) || 1;
    state.sort = params.get('sort') || $('#sortSelect')?.value || DEFAULTS.sort;
    state.filters.search = params.get('search') || $('#searchInput')?.value || '';
    state.filters.min_price = params.get('min_price') || $('#priceMin')?.value || '';
    state.filters.max_price = params.get('max_price') || $('#priceMax')?.value || '';

    // Catégorie / sous-catégorie = un seul choix.
    // Les anciennes URL contenant plusieurs ?category=... sont nettoyées en
    // conservant uniquement la dernière sélection explicite.
    const requestedCategories = multiParam(params, 'category');
    const checkedCategories = checkedValues('category');
    const explicitCategory = normalize(
        requestedCategories.at(-1)
        || checkedCategories.at(-1)
        || ''
    );
    const requiredCategory = normalize($('.catalog-page')?.dataset.requiredCategory || '');

    state.filters.category = explicitCategory
        ? [explicitCategory]
        : (requiredCategory ? [requiredCategory] : []);

    state.filters.offer = multiParam(params, 'offer').concat(multiParam(params, 'type')).concat(checkedValues('offer'));
    state.filters.stock = multiParam(params, 'stock').concat(checkedValues('stock'));
    state.filters.unit = multiParam(params, 'unit').concat(checkedValues('unit'));
    state.filters.rating = multiParam(params, 'rating').concat(checkedValues('rating'));

    const requiredOffer = normalize($('.catalog-page')?.dataset.requiredOffer || '');
    if (requiredOffer) state.filters.offer.push(requiredOffer);

    if (!window.location.pathname.startsWith('/categories/')) {
        const base = catalogBasePath();
        const routeCategory = decodeURIComponent(window.location.pathname.slice(base.length).replace(/^\//, '').split('/')[0] || '');
        if (!explicitCategory && routeCategory) {
            state.filters.category = [routeCategory];
        }
    }

    dedupeFilters();

    if (state.filters.category.length > 1) {
        state.filters.category = [state.filters.category.at(-1)];
    }
}

function hydrateControls() {
    if ($('#searchInput')) $('#searchInput').value = state.filters.search;
    if ($('#priceMin')) $('#priceMin').value = state.filters.min_price;
    if ($('#priceMax')) $('#priceMax').value = state.filters.max_price;
    if ($('#sortSelect')) $('#sortSelect').value = state.sort;

    setChecked('category', state.filters.category);
    setChecked('offer', state.filters.offer);
    setChecked('stock', state.filters.stock);
    setChecked('unit', state.filters.unit);
    setChecked('rating', state.filters.rating);
}

function collectFiltersFromDom() {
    state.filters.search = normalize($('#searchInput')?.value || '');
    state.filters.min_price = normalize($('#priceMin')?.value || '');
    state.filters.max_price = normalize($('#priceMax')?.value || '');

    const selectedCategory = checkedValues('category').at(-1) || '';
    const requiredCategory = normalize($('.catalog-page')?.dataset.requiredCategory || '');

    state.filters.category = selectedCategory
        ? [selectedCategory]
        : (requiredCategory ? [requiredCategory] : []);

    state.filters.offer = checkedValues('offer');
    const requiredOffer = normalize($('.catalog-page')?.dataset.requiredOffer || '');
    if (requiredOffer) state.filters.offer.push(requiredOffer);
    state.filters.stock = checkedValues('stock');
    state.filters.unit = checkedValues('unit');
    state.filters.rating = checkedValues('rating');
    state.sort = $('#sortSelect')?.value || DEFAULTS.sort;
    dedupeFilters();

    if (state.filters.category.length > 1) {
        state.filters.category = [state.filters.category.at(-1)];
    }
}

function appendFilterParams(params) {
    if (state.filters.search) params.set('search', state.filters.search);
    if (state.filters.min_price) params.set('min_price', state.filters.min_price);
    if (state.filters.max_price) params.set('max_price', state.filters.max_price);

    const selectedCategory = state.filters.category.at(-1);
    if (selectedCategory) {
        params.set('category', selectedCategory);
    }

    ['offer', 'stock', 'unit', 'rating'].forEach(key => {
        state.filters[key].forEach(value => params.append(key, value));
    });
}

function buildApiUrl(page = 1) {
    const url = new URL(apiRoutes.products || '/api/products', window.location.origin);
    url.searchParams.set('page', String(page));
    url.searchParams.set('per_page', String(state.perPage));
    url.searchParams.set('sort', state.sort || DEFAULTS.sort);
    appendFilterParams(url.searchParams);
    return url;
}

function syncUrl() {
    const url = new URL(window.location.href);
    const params = new URLSearchParams();
    const requiredCategory = normalize($('.catalog-page')?.dataset.requiredCategory || '');

    url.pathname = catalogBasePath();
    if (state.page > 1) params.set('page', String(state.page));
    if (state.sort !== DEFAULTS.sort) params.set('sort', state.sort);

    if (state.filters.search) params.set('search', state.filters.search);
    if (state.filters.min_price) params.set('min_price', state.filters.min_price);
    if (state.filters.max_price) params.set('max_price', state.filters.max_price);

    const selectedCategory = normalize(state.filters.category.at(-1) || '');
    // Sur /categories/{parent}, le parent est déjà porté par le chemin.
    // On n'ajoute ?category=... que lorsqu'une sous-catégorie est sélectionnée.
    if (selectedCategory && (!requiredCategory || normalizeToken(selectedCategory) !== normalizeToken(requiredCategory))) {
        params.set('category', selectedCategory);
    }

    ['offer', 'stock', 'unit', 'rating'].forEach(key => {
        state.filters[key].forEach(value => params.append(key, value));
    });

    url.search = params.toString();
    window.history.replaceState({}, '', url.toString());
}

function reloadCatalog({ scroll = false } = {}) {
    state.page = 1;
    syncUrl();
    fetchProducts(1, { scroll });
}

function resetFilters() {
    const categoryPage = $('.catalog-page');
    const requiredCategory = normalize(categoryPage?.dataset.requiredCategory || '');
    const requiredOffer = normalize(categoryPage?.dataset.requiredOffer || '');
    const listingPage = normalize(categoryPage?.dataset.listingPage || '');
    if (requiredCategory || requiredOffer || listingPage) {
        window.location.assign(categoryPage?.dataset.catalogUrl || '/catalog');
        return;
    }

    state.page = 1;
    state.sort = DEFAULTS.sort;
    state.filters = {
        search: '', category: [], offer: [], stock: [], unit: [], rating: [],
        min_price: '', max_price: '',
    };
    hydrateControls();
    syncUrl();
    fetchProducts(1, { scroll: false });
}

function removeChip(key, value) {
    const categoryPage = $('.catalog-page');
    const requiredCategory = normalize(categoryPage?.dataset.requiredCategory || '');
    const requiredOffer = normalize(categoryPage?.dataset.requiredOffer || '');

    if (key === 'category' && requiredCategory) {
        // Retirer une sous-catégorie depuis une page catégorie revient à la
        // catégorie principale. Retirer le parent lui-même revient au catalogue.
        if (normalizeToken(value) !== normalizeToken(requiredCategory)) {
            state.filters.category = [requiredCategory];
            hydrateControls();
            reloadCatalog();
            return;
        }

        window.location.assign(categoryPage?.dataset.catalogUrl || '/catalog');
        return;
    }

    if (key === 'offer' && requiredOffer) {
        window.location.assign(categoryPage?.dataset.catalogUrl || '/catalog');
        return;
    }

    if (key === 'price') {
        state.filters.min_price = '';
        state.filters.max_price = '';
    } else if (Array.isArray(state.filters[key])) {
        state.filters[key] = state.filters[key].filter(item => item !== value);
    } else if (Object.hasOwn(state.filters, key)) {
        state.filters[key] = '';
    }

    hydrateControls();
    reloadCatalog();
}

function bindControls() {
    const searchReload = debounce(() => {
        collectFiltersFromDom();
        reloadCatalog();
    });

    $('#searchInput')?.addEventListener('input', searchReload);

    $$('input.filter-control[type="checkbox"], input.filter-control[type="radio"]').forEach(control => {
        control.addEventListener('change', () => {
            if (control.name === 'category' && control.checked) {
                // Une seule catégorie/sous-catégorie peut être active.
                $$('input[name="category"]').forEach(other => {
                    if (other !== control) other.checked = false;
                });

                if (control.dataset.categoryUrl) {
                    window.location.assign(control.dataset.categoryUrl);
                    return;
                }
            }

            collectFiltersFromDom();
            reloadCatalog();
        });
    });

    $('#sortSelect')?.addEventListener('change', () => {
        collectFiltersFromDom();
        reloadCatalog({ scroll: true });
    });

    $('#applyPrice')?.addEventListener('click', () => {
        collectFiltersFromDom();
        reloadCatalog();
    });

    [$('#priceMin'), $('#priceMax')].filter(Boolean).forEach(input => {
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                collectFiltersFromDom();
                reloadCatalog();
            }
        });
    });

    $('#filterReset')?.addEventListener('click', resetFilters);
    $$('.catalog-clear-link').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        resetFilters();
    }));

    $$('[data-category-search]').forEach(button => {
        button.addEventListener('click', () => {
            $$('[data-category-search]').forEach(item => item.classList.remove('is-active'));
            button.classList.add('is-active');
            const input = $('#searchInput');
            if (input) input.value = button.dataset.categorySearch || '';
            collectFiltersFromDom();
            reloadCatalog({ scroll: true });
        });
    });
}

function bindDelegatedActions() {
    document.addEventListener('click', event => {
        const pageButton = event.target.closest('[data-catalog-page]');
        if (pageButton) {
            const page = Number(pageButton.dataset.catalogPage);
            if (page && page !== state.page) fetchProducts(page, { scroll: true });
            return;
        }

        const chipButton = event.target.closest('[data-chip-remove]');
        if (chipButton) {
            removeChip(chipButton.dataset.chipRemove, chipButton.dataset.chipValue);
            return;
        }

        const minus = event.target.closest('[data-qty-minus]');
        if (minus) {
            changeQuantity(minus.closest('.ovcat-product-card'), -1);
            return;
        }

        const plus = event.target.closest('[data-qty-plus]');
        if (plus) {
            changeQuantity(plus.closest('.ovcat-product-card'), 1);
            return;
        }

        const cart = event.target.closest('[data-add-to-cart]');
        if (cart) {
            event.preventDefault();
            addToCart(cart);
        }
    });
}

function changeQuantity(card, delta) {
    const valueEl = $('[data-qty-value]', card || document);
    if (!valueEl) return;

    const min = Math.max(1, Number(card?.dataset.minQty || 1));
    const current = Number(valueEl.textContent) || min;
    const max = Math.max(min, Number(card?.dataset.stock || min));
    valueEl.textContent = String(Math.min(max, Math.max(min, current + delta)));
}

async function fetchProducts(page = 1, { scroll = false } = {}) {
    if (state.loading) return;

    state.loading = true;
    state.page = page;
    renderLoading();

    try {
        const response = await fetch(buildApiUrl(page).toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error(`Catalogue API ${response.status}`);

        const payload = await response.json();
        state.total = Number(payload.total || 0);
        state.lastPage = Number(payload.last_page || 1);
        state.page = Number(payload.current_page || page);
        state.perPage = Number(payload.per_page || DEFAULTS.perPage);

        renderProducts(payload.data || []);
        renderCount();
        renderActiveFilters();
        renderPagination();
        syncUrl();
        observeImages();
        refreshIcons();

        if (scroll) scrollToProducts();
    } catch (error) {
        console.error(error);
        renderError();
    } finally {
        state.loading = false;
    }
}

function renderLoading() {
    const grid = $('#productsGrid');
    if (!grid) return;

    grid.innerHTML = Array.from({ length: 8 }, () => `
        <article class="ovcat-product-card ovcat-product-card--skeleton" aria-hidden="true">
            <div class="ovcat-skeleton ovcat-skeleton--image"></div>
            <div class="ovcat-product-body">
                <div class="ovcat-skeleton ovcat-skeleton--line"></div>
                <div class="ovcat-skeleton ovcat-skeleton--line short"></div>
                <div class="ovcat-skeleton ovcat-skeleton--price"></div>
                <div class="ovcat-skeleton ovcat-skeleton--button"></div>
            </div>
        </article>
    `).join('');
}

function renderError() {
    const grid = $('#productsGrid');
    if (!grid) return;

    grid.innerHTML = `
        <div class="catalog-empty">
            <span><i data-lucide="triangle-alert"></i></span>
            <h3>Le catalogue ne peut pas être chargé</h3>
            <p>Vérifiez votre connexion puis rechargez la page.</p>
            <button type="button" onclick="window.location.reload()">Réessayer</button>
        </div>
    `;
    refreshIcons();
}

function renderProducts(products) {
    const grid = $('#productsGrid');
    if (!grid) return;

    if (!products.length) {
        grid.innerHTML = `
            <div class="catalog-empty">
                <span><i data-lucide="package-search"></i></span>
                <h3>Aucun produit ne correspond à ces filtres</h3>
                <p>Essayez d’élargir votre budget, votre catégorie ou la disponibilité.</p>
                <button type="button" id="emptyReset">Réinitialiser les filtres</button>
            </div>
        `;
        $('#emptyReset')?.addEventListener('click', resetFilters);
        refreshIcons();
        return;
    }

    grid.innerHTML = products.map(productCard).join('');
}

function productCard(product, index = 0) {
    const url = productUrl(product);
    const name = product.name || 'Produit OVANIE';
    const price = publicPrice(product);
    const oldPrice = originalPublicPrice(product);
    const isGiftCard = product.is_gift_card === true;
    const unit = isGiftCard
        ? 'Carte digitale OVANIE'
        : formatUnit(product.unit_label || product.display_unit || product.unit || 'pièce');
    const canAddToCart = product.can_add_to_cart === true;
    const orderable = product.is_orderable === true;
    const stock = Number(product.stock || 0);
    const minQty = Math.max(1, Number(product.min_order_quantity || 1));
    const badge = productBadge(product);
    const listingType = $('.catalog-page')?.dataset.listingPage || '';
    const rankBadge = listingType === 'best-sellers'
        ? `<span class="ovcat-product-rank">${((state.page - 1) * state.perPage) + index + 1}</span>`
        : '';

    return `
        <article class="ovcat-product-card" data-product-id="${escapeHtml(product.id)}" data-stock="${stock}" data-min-qty="${minQty}">
            <a href="${escapeHtml(url)}" class="ovcat-product-media" aria-label="Voir ${escapeHtml(name)}">
                ${rankBadge}
                ${badge ? `<div class="ovcat-product-badges">${badge}</div>` : ''}
                <img
                    class="catalog-lazy ovcat-product-img"
                    loading="lazy"
                    data-src="${escapeHtml(imageUrl(product))}"
                    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='600'%3E%3Crect width='600' height='600' fill='%23f8fafc'/%3E%3C/svg%3E"
                    alt="${escapeHtml(name)}"
                    width="600"
                    height="600"
                >
            </a>

            <div class="ovcat-product-body">
                <a href="${escapeHtml(url)}" class="ovcat-product-name">${escapeHtml(name)}</a>

                <div class="ovcat-product-price-row">
                    <strong>${formatPrice(price)}</strong>
                    ${oldPrice ? `<del>${formatPrice(oldPrice)}</del>` : ''}
                </div>

                <div class="ovcat-product-bottom-row">
                    ${isGiftCard
                        ? '<span class="ovcat-product-rating"><i data-lucide="shield-check"></i><strong>OVANIE</strong><small> sécurisé</small></span>'
                        : ratingHtml(product)}

                    ${isGiftCard ? `
                        <a
                            href="${escapeHtml(url)}"
                            class="ovcat-cart-button"
                            aria-label="Acheter ${escapeHtml(name)}"
                            title="Acheter"
                            style="width:auto!important;min-width:112px!important;height:42px!important;padding:0 14px!important;gap:7px!important;font-weight:800!important;display:inline-flex!important;flex-direction:row!important;align-items:center!important;justify-content:center!important;line-height:1!important;white-space:nowrap!important;"
                        >
                            <i data-lucide="shopping-bag"></i>
                            <span style="display:inline!important;line-height:1!important;">Acheter</span>
                        </a>
                    ` : canAddToCart ? `
                        <button
                            type="button"
                            class="ovcat-cart-button"
                            data-add-to-cart
                            data-product-id="${escapeHtml(product.id)}"
                            aria-label="Ajouter ${escapeHtml(name)} au panier"
                            title="Ajouter au panier"
                        >
                            <i data-lucide="shopping-cart"></i><span>Ajouter au panier</span>
                        </button>
                    ` : `
                        <a
                            href="${escapeHtml(url)}"
                            class="ovcat-cart-button"
                            aria-label="${orderable ? 'Voir les conditions' : 'Voir le produit'}"
                            title="${orderable ? 'Voir les conditions' : 'Voir le produit'}"
                        >
                            <i data-lucide="${orderable ? 'clipboard-list' : 'eye'}"></i><span>${orderable ? 'Voir les conditions' : 'Voir le produit'}</span>
                        </a>
                    `}
                </div>

                <span class="ovcat-unit-label">${isGiftCard ? escapeHtml(unit) : `Vendu par ${escapeHtml(unit)}`}</span>
            </div>
        </article>
    `;
}

function observeImages() {
    const images = $$('img.catalog-lazy[data-src]');
    if (!images.length) return;

    const load = img => {
        const src = img.dataset.src;
        if (!src) return;
        img.src = src;
        img.removeAttribute('data-src');
        img.classList.remove('catalog-lazy');
        img.addEventListener('error', () => { img.src = '/images/product-placeholder.svg'; }, { once: true });
    };

    if (!('IntersectionObserver' in window)) {
        images.forEach(load);
        return;
    }

    if (!state.lazyObserver) {
        state.lazyObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                load(entry.target);
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '220px' });
    }

    images.forEach(img => state.lazyObserver.observe(img));
}

function renderCount() {
    const element = $('#resultCountText');
    if (!element) return;
    element.textContent = `${state.total.toLocaleString('fr-FR')} produit${state.total > 1 ? 's' : ''}`;
}

function renderActiveFilters() {
    const wrapper = $('#activeFilters');
    if (!wrapper) return;

    const chips = [];
    if (state.filters.search) chips.push(chip('search', state.filters.search, state.filters.search));
    state.filters.category.slice(-1).forEach(value => chips.push(chip('category', value, labelFromInput('category', value))));
    state.filters.offer.forEach(value => chips.push(chip('offer', value, offerLabel(value))));
    state.filters.stock.forEach(value => chips.push(chip('stock', value, stockLabel(value))));
    state.filters.unit.forEach(value => chips.push(chip('unit', value, formatUnit(value))));
    state.filters.rating.forEach(value => chips.push(chip('rating', value, `${value}★ & plus`)));

    if (state.filters.min_price || state.filters.max_price) {
        chips.push(chip('price', 'price', `${state.filters.min_price || '0'} – ${state.filters.max_price || '∞'} FCFA`));
    }

    if (!chips.length) {
        wrapper.innerHTML = '<span class="catalog-chip catalog-chip--neutral"><i data-lucide="package"></i> Tous les produits</span>';
        refreshIcons();
        return;
    }

    wrapper.innerHTML = `${chips.join('')}<button type="button" class="catalog-clear-link" id="activeReset">Tout effacer</button>`;
    $('#activeReset')?.addEventListener('click', resetFilters);
    refreshIcons();
}

function chip(type, value, label) {
    return `
        <span class="catalog-chip">
            ${escapeHtml(label)}
            <button type="button" data-chip-remove="${escapeHtml(type)}" data-chip-value="${escapeHtml(value)}" aria-label="Retirer le filtre">
                <i data-lucide="x"></i>
            </button>
        </span>
    `;
}

function labelFromInput(name, value) {
    const input = $(`input[name="${name}"][value="${cssEscape(value)}"]`);
    return input?.closest('.catalog-check-row')?.querySelector('.catalog-check-label')?.textContent?.trim()
        || value.replace(/-/g, ' ');
}

function offerLabel(value) {
    return ({
        promo: 'Promotions',
        'vente-flash': 'Vente flash',
        'black-friday': 'Black Friday',
        top: 'Top ventes',
        new: 'Nouveautés',
        boosted: 'À la une',
    }[value] || value.replace(/-/g, ' '));
}

function stockLabel(value) {
    return ({
        'in-stock': 'En stock',
        'on-order': 'Sur commande',
        'out-of-stock': 'Sur commande',
        'fast-delivery': 'Livraison rapide',
    }[value] || value);
}

function renderPagination() {
    const wrapper = $('#paginationWrapper');
    if (!wrapper) return;

    if (state.lastPage <= 1) {
        wrapper.innerHTML = '';
        return;
    }

    const current = Math.max(1, state.page);
    const last = Math.max(1, state.lastPage);
    wrapper.innerHTML = `
        <button type="button" class="catalog-page-nav catalog-page-nav--previous" data-catalog-page="${current - 1}" ${current <= 1 ? 'disabled' : ''} aria-label="Page précédente">
            <i data-lucide="arrow-left"></i><span>Précédent</span>
        </button>
        <div class="catalog-page-status" aria-live="polite">
            <span>Page</span><strong>${current}</strong><span>sur ${last}</span>
        </div>
        <button type="button" class="catalog-page-nav catalog-page-nav--next" data-catalog-page="${current + 1}" ${current >= last ? 'disabled' : ''} aria-label="Page suivante">
            <span>Suivant</span><i data-lucide="arrow-right"></i>
        </button>
    `;

    refreshIcons();
}

function scrollToProducts() {
    ($('.catalog-toolbar') || $('#catalogMain'))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function getCsrfToken() {
    return $('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function addToCart(button) {
    const productId = button.dataset.productId;
    const card = button.closest('.ovcat-product-card');
    const quantity = Math.max(1, Number(card?.dataset.minQty || 1));
    if (!productId) return;

    const oldHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-circle"></i>';
    refreshIcons();

    try {
        const form = new FormData();
        form.append('quantity', String(quantity));

        const response = await fetch(apiRoutes.cartAdd ? apiRoutes.cartAdd(productId) : `/cart/add/${productId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: form,
            credentials: 'same-origin',
        });

        if (response.redirected && response.url.includes('/login')) {
            window.location.href = response.url;
            return;
        }

        if ([401, 419].includes(response.status)) {
            window.location.href = '/login';
            return;
        }

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) throw new Error(data.message || 'Ajout impossible');

        button.innerHTML = '<i data-lucide="check"></i>';
        refreshIcons();

        if (typeof data.cart_count !== 'undefined') {
            $$('[data-cart-count], .cart-count, #cartCount, #cart-count').forEach(el => { el.textContent = String(data.cart_count); el.dataset.count = String(data.cart_count); });
        } else {
            await updateCartCount();
        }

        window.OvanieCart?.toast(data.message || 'Produit ajouté au panier');

        window.setTimeout(() => {
            button.disabled = false;
            button.innerHTML = oldHtml;
            refreshIcons();
        }, 1200);
    } catch (error) {
        console.error(error);
        button.innerHTML = '<i data-lucide="triangle-alert"></i>';
        refreshIcons();
        window.setTimeout(() => {
            button.disabled = false;
            button.innerHTML = oldHtml;
            refreshIcons();
        }, 1500);
    }
}

async function updateCartCount() {
    try {
        const response = await fetch(apiRoutes.cartCount || '/cart/count', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        const count = Number(data.count || 0);
        $$('[data-cart-count], .cart-count, #cartCount, #cart-count').forEach(el => { el.textContent = String(count); el.dataset.count = String(count); });
    } catch (_) {
        // Le compteur n'empêche pas l'ajout au panier.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initStateFromUrl();
    hydrateControls();
    bindControls();
    bindDelegatedActions();
    fetchProducts(state.page, { scroll: false });
    refreshIcons();
});
