/* ===========================================
   OVANIE HOME – AMAZON STYLE + IA
   Compatible avec les routes et containers existants
=========================================== */

const MAX_PER_SECTION = 500;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

let OVANIE_PRODUCTS_CACHE = [];
let OVANIE_LAST_QUOTE = [];

/* ================= UTILITAIRES ================= */

function formatPrice(value) {
    const n = Number(value);
    return isNaN(n) ? '—' : n.toLocaleString('fr-FR') + ' FCFA';
}

function discountPercent(item) {
    const price = Number(item.price);
    const finalPrice = Number(item.final_price || item.promo_price);

    if (!price || !finalPrice || finalPrice >= price) return null;

    return Math.round(((price - finalPrice) / price) * 100);
}

function escapeHtml(s = '') {
    return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[c]);
}

function normalizeText(txt = '') {
    return String(txt)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9 ]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function getProductTag(item) {
    const type = (item.sale_type || '').toLowerCase().trim();

    if (type === 'black friday') return 'black-friday';
    if (type === 'vente flash' || type === 'promo spéciale') return 'flash-sale';
    if (type.includes('devis') || type.includes('appel')) return 'immo-business';

    return 'normal-sale';
}

function productShopName(item) {
    return item.shop?.name || item.shop_name || 'OVANIE';
}

function productCity(item) {
    return item.shop_commune || item.shop_city || item.shop_region || 'Côte d’Ivoire';
}

function productImage(item) {
    return item.image || '/storage/products/placeholder.png';
}

function productFinalPrice(item) {
    return Number(item.final_price || item.promo_price || item.price || 0);
}

/* ================= BADGES ================= */

function generateBadges(item) {
    let html = '';
    const type = (item.sale_type || '').toLowerCase();

    if (item.tags && item.tags.includes('a-la-une')) {
        html += `<div class="badge">SPONSORISÉ</div>`;
    }

    if (type === 'black friday') {
        html += `<div class="badge black-friday">BLACK FRIDAY</div>`;
    }

    if (type === 'vente flash' || type === 'promo spéciale') {
        html += `<div class="badge flash-sale">VENTE FLASH</div>`;
    }

    if (type.includes('devis') || type.includes('appel')) {
        html += `<div class="badge immo-business">IMMO BUSINESS</div>`;
    }

    if (item.promo_price && Number(item.promo_price) < Number(item.price)) {
        if (type !== 'black friday' && type !== 'vente flash') {
            html += `<div class="badge promo-blink">PROMO</div>`;
        }
    }

    if (Number(item.sales) > 0) {
        html += `<div class="badge best-seller">BEST SELLER</div>`;
    }

    if (!html) html = `<div class="badge">OVANIE</div>`;

    return html;
}

/* ================= CREATE CARD ================= */

function createCardElement(item) {
    const card = document.createElement('article');
    card.className = 'product-card ov-prd-card';
    card.dataset.productId = item.id;
    card.dataset.productName = item.name || '';

    const hasPromo = item.promo_price && Number(item.promo_price) < Number(item.price);
    const discount = discountPercent(item);
    const imageUrl = productImage(item);
    const saleType = (item.sale_type || '').toLowerCase();
    const price = formatPrice(item.final_price || item.promo_price || item.price);
    const oldPrice = hasPromo ? `<s class="ov-c-old">${formatPrice(item.price)}</s>` : '';
    const available = item.stock > 0;

    /* badge type de vente */
    let saleBadge = '';
    if (saleType === 'black friday') saleBadge = `<span class="ov-c-badge bf">BLACK FRIDAY</span>`;
    else if (saleType === 'vente flash' || saleType === 'promo spéciale') saleBadge = `<span class="ov-c-badge vf">VENTE FLASH</span>`;

    /* countdown vente flash */
    const countdown = (saleType === 'vente flash' && item.flash_end)
        ? `<div class="ov-c-timer" data-end="${item.flash_end}"><span class="timer">--:--:--</span></div>` : '';

    card.innerHTML = `
        ${discount ? `<div class="ov-c-disc">-${discount}%</div>` : ''}
        <a href="/catalog?product=${encodeURIComponent(item.id)}" class="ov-c-link">
            <div class="ov-c-img">
                <img loading="lazy" src="${imageUrl}"
                     alt="${escapeHtml(item.name || 'produit')}" />
                ${saleBadge}
            </div>
            ${countdown}
            <div class="ov-c-body">
                <div class="ov-c-meta">
                    <h4 class="ov-c-name">${escapeHtml(item.name || '—')}</h4>
                    <p class="ov-c-loc">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="#888" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        ${escapeHtml(productCity(item))}
                    </p>
                </div>
                <div class="ov-c-footer">
                    <div class="ov-c-price">
                        ${oldPrice}
                        <strong class="ov-c-amount">${price}</strong>
                    </div>
                    <button class="ov-c-btn">Voir l'offre</button>
                </div>
            </div>
        </a>
    `;
    return card;
}

function createBusinessCard(item) {
    const card = document.createElement('article');
    card.className = 'product-card business-card';

    const image = item.imageVitrine || item.image || '/images/business.jpg';
    const itemType = String(item.type || '').toLowerCase();

    const typeBadge = itemType === 'appel' || itemType === 'appel_offre' || itemType === 'appel-offre'
        ? `<div class="badge immo-business">APPEL D'OFFRE</div>`
        : `<div class="badge immo-business">DEVIS</div>`;

    card.innerHTML = `
        ${typeBadge}

        <a href="/catalog-business" class="product-link">
            <div class="img-wrap">
                <img src="${image}" loading="lazy" alt="business">
            </div>

            <div class="product-info">
                <h4 class="product-title">${escapeHtml(item.title || 'Demande')}</h4>

                <p class="business-desc">${escapeHtml((item.description || '').substring(0, 120))}...</p>

                <div class="price-box">
                    <span class="promo-price">Budget : ${formatPrice(item.budget)}</span>
                </div>
            </div>
        </a>
    `;

    return card;
}

/* ================= INJECTION ================= */

function injectItemsArray(items, container) {
    if (!container || !Array.isArray(items)) return;

    const skeleton = container.querySelector('.skeleton-wrapper');
    if (skeleton) skeleton.remove();

    container.innerHTML = '';

    items.forEach((item, index) => {
        const card = createCardElement(item);
        container.appendChild(card);

        requestAnimationFrame(() => {
            setTimeout(() => card.classList.add('show'), index * 35);
        });
    });

    startFlashCountdowns();
    observeLazyImages();
    observeViewCounters();
}

function injectBusinessItems(items, container) {
    if (!container || !Array.isArray(items)) return;

    const skeleton = container.querySelector('.skeleton-wrapper');
    if (skeleton) skeleton.remove();

    container.innerHTML = '';

    items.forEach((item, index) => {
        const card = createBusinessCard(item);
        container.appendChild(card);

        requestAnimationFrame(() => {
            setTimeout(() => card.classList.add('show'), index * 35);
        });
    });
}

/* ================= LAZY LOAD ================= */

let lazyObserver;

function observeLazyImages() {
    const imgs = document.querySelectorAll('img.lazy[data-src]');

    if (!('IntersectionObserver' in window)) {
        imgs.forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            img.classList.remove('lazy');
        });
        return;
    }

    if (!lazyObserver) {
        lazyObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    img.classList.remove('lazy');
                    obs.unobserve(img);
                }
            });
        }, { rootMargin: '200px' });
    }

    imgs.forEach(img => {
        if (img.dataset.src) lazyObserver.observe(img);
    });
}

/* ================= VIEW COUNTER ================= */

const viewedKey = 'imoo_viewed_products';
let viewObserver;

function hasViewedClient(id) {
    try {
        const arr = JSON.parse(localStorage.getItem(viewedKey)) || [];
        return arr.includes(id);
    } catch {
        return false;
    }
}

function markViewedClient(id) {
    try {
        const arr = JSON.parse(localStorage.getItem(viewedKey)) || [];
        if (!arr.includes(id)) {
            arr.push(id);
            localStorage.setItem(viewedKey, JSON.stringify(arr));
            return true;
        }
    } catch { }
    return false;
}

function observeViewCounters() {
    const cards = document.querySelectorAll('.product-card[data-product-id]');

    if (!('IntersectionObserver' in window)) {
        cards.forEach(c => {
            if (!hasViewedClient(c.dataset.productId)) {
                markViewedClient(c.dataset.productId);
                sendView(c.dataset.productId);
            }
        });
        return;
    }

    if (!viewObserver) {
        viewObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.dataset.productId;
                    if (!hasViewedClient(id)) {
                        markViewedClient(id);
                        sendView(id);
                    }
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
    }

    cards.forEach(c => {
        if (!hasViewedClient(c.dataset.productId)) {
            viewObserver.observe(c);
        }
    });
}

async function sendView(productId) {
    try {
        await fetch(`/api/products/${productId}/view`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        });
    } catch (e) {
        console.error('View error:', e);
    }
}

/* ================= API ================= */

async function apiGetJson(url) {
    try {
        const r = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json' } });
        if (!r.ok) return [];
        const json = await r.json();
        return Array.isArray(json) ? json : (json.data || []);
    } catch {
        return [];
    }
}

async function apiGetBusiness() {
    try {
        const r = await fetch('/api/business/json', { credentials: 'include', headers: { 'Accept': 'application/json' } });
        if (!r.ok) return [];
        const json = await r.json();
        return Array.isArray(json) ? json : [];
    } catch {
        return [];
    }
}

/* ================= COUNTDOWN ================= */

function startFlashCountdowns() {
    const countdowns = document.querySelectorAll('.flash-countdown');

    countdowns.forEach(box => {
        if (box.dataset.timerStarted === '1') return;
        box.dataset.timerStarted = '1';

        const endDate = box.dataset.end;
        const timer = box.querySelector('.timer');

        if (!endDate || !timer) return;

        const end = new Date(endDate).getTime();

        function updateTimer() {
            const now = new Date().getTime();
            const diff = end - now;

            if (diff <= 0) {
                timer.textContent = 'Terminé';
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            timer.textContent =
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    });
}

/* ================= CAROUSEL HELPERS ================= */

function getTrackFromContainer(container) {
    return container?.querySelector('.carousel-track2, .carousel-track, .products-carousel, .ov-rail-track') || null;
}

function getItemWidth(track) {
    if (!track) return 220;

    const item = Array.from(track.children).find(el => !el.classList.contains('skeleton-wrapper'));
    if (!item) return 220;

    const style = window.getComputedStyle(track);
    const gap = parseFloat(style.columnGap || style.gap || '0') || 0;

    return Math.ceil(item.getBoundingClientRect().width + gap);
}

function updateArrowState(container, track, prevBtn, nextBtn) {
    if (!container || !track) return;

    const isScrollable = track.scrollWidth > track.clientWidth + 5;
    const atStart = track.scrollLeft <= 5;
    const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 5;

    if (prevBtn) {
        prevBtn.style.display = isScrollable ? 'flex' : 'none';
        prevBtn.disabled = atStart;
        prevBtn.style.opacity = atStart ? '0.55' : '1';
    }

    if (nextBtn) {
        nextBtn.style.display = isScrollable ? 'flex' : 'none';
        nextBtn.disabled = atEnd;
        nextBtn.style.opacity = atEnd ? '0.55' : '1';
    }
}

function enableTouchSnap(container) {
    if (!container || container.dataset.touchSnapReady === 'true') return;

    const track = getTrackFromContainer(container);
    if (!track) return;

    let snapTimeout = null;

    function snapToNearest() {
        const step = Math.max(getItemWidth(track), 170);
        const index = Math.round(track.scrollLeft / step);
        const target = index * step;

        track.scrollTo({ left: target, behavior: 'smooth' });
    }

    track.addEventListener('touchstart', () => {
        if (snapTimeout) {
            clearTimeout(snapTimeout);
            snapTimeout = null;
        }
    }, { passive: true });

    track.addEventListener('touchend', () => {
        snapTimeout = setTimeout(snapToNearest, 90);
    }, { passive: true });

    container.dataset.touchSnapReady = 'true';
}

function initSingleCarousel(container, options = {}) {
    if (!container || container.dataset.carouselReady === 'true') return;

    const track = getTrackFromContainer(container);
    if (!track) return;

    const prevBtn = container.querySelector('.btn-left, .carousel-btn.prev');
    const nextBtn = container.querySelector('.btn-right, .carousel-btn.next');

    let autoScroll = null;
    const auto = options.auto === true;
    const interval = options.interval || 4000;

    const goNext = () => {
        const step = Math.max(getItemWidth(track), 220);
        const maxLeft = track.scrollWidth - track.clientWidth;

        if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 10) {
            track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            track.scrollTo({ left: Math.min(track.scrollLeft + step, maxLeft), behavior: 'smooth' });
        }
    };

    const goPrev = () => {
        const step = Math.max(getItemWidth(track), 220);
        track.scrollTo({ left: Math.max(track.scrollLeft - step, 0), behavior: 'smooth' });
    };

    if (prevBtn) prevBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); goPrev(); });
    if (nextBtn) nextBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); goNext(); });

    const refreshState = () => updateArrowState(container, track, prevBtn, nextBtn);

    track.addEventListener('scroll', refreshState, { passive: true });
    window.addEventListener('resize', refreshState);

    function stopAuto() {
        if (autoScroll) {
            clearInterval(autoScroll);
            autoScroll = null;
        }
    }

    function startAuto() {
        if (!auto) return;
        stopAuto();
        autoScroll = setInterval(goNext, interval);
    }

    container.addEventListener('mouseenter', stopAuto);
    container.addEventListener('mouseleave', startAuto);
    container.addEventListener('touchstart', stopAuto, { passive: true });
    container.addEventListener('touchend', startAuto, { passive: true });

    container.dataset.carouselReady = 'true';
    refreshState();
    startAuto();
    setTimeout(refreshState, 500);
}

function initAllCarousels() {
    [
        ['black-friday-carousel', true, 4500],
        ['flash-sale-carousel', true, 4200],
        ['immo-business-carousel', true, 4300],
        ['normal-sale-carousel', true, 4300]
    ].forEach(([id, auto, interval]) => {
        const el = document.getElementById(id);
        if (el) {
            el.dataset.carouselReady = 'false';
            initSingleCarousel(el, { auto, interval });
            enableTouchSnap(el);
        }
    });

    const productsContainer = document.querySelector('.carousel-container');
    if (productsContainer) {
        productsContainer.dataset.carouselReady = 'false';
        initSingleCarousel(productsContainer, { auto: true, interval: 4000 });
        enableTouchSnap(productsContainer);
    }
}

/* ================= IA OVANIE ================= */

function speak(txt) {
    if ('speechSynthesis' in window) {
        speechSynthesis.cancel();
        speechSynthesis.speak(new SpeechSynthesisUtterance(txt));
    }
}

function findProductByText(query, source = OVANIE_PRODUCTS_CACHE) {
    const q = normalizeText(query);
    if (!q) return null;

    const parts = q.split(' ');
    const scored = source.map(p => {
        const hay = normalizeText(`${p.name || ''} ${productShopName(p)} ${p.category || ''} ${(p.tags || []).join(' ')}`);
        let score = 0;

        parts.forEach(w => {
            if (w.length > 1 && hay.includes(w)) score++;
        });

        if (hay.includes(q)) score += 5;

        return { p, score };
    }).filter(x => x.score > 0).sort((a, b) => b.score - a.score);

    return scored[0]?.p || null;
}

function findProductsByText(query, source = OVANIE_PRODUCTS_CACHE) {
    const q = normalizeText(query);
    if (!q) return [];

    const parts = q.split(' ');

    return source.map(p => {
        const hay = normalizeText(`${p.name || ''} ${productShopName(p)} ${p.category || ''} ${(p.tags || []).join(' ')}`);
        let score = 0;

        parts.forEach(w => {
            if (w.length > 1 && hay.includes(w)) score++;
        });

        if (hay.includes(q)) score += 5;

        return { p, score };
    }).filter(x => x.score > 0).sort((a, b) => b.score - a.score).map(x => x.p);
}

function aiAddProduct(productId) {
    const product = OVANIE_PRODUCTS_CACHE.find(p => String(p.id) === String(productId));
    if (!product) return;
    window.location.href = `/catalog?product=${encodeURIComponent(product.id)}`;
}

function renderAIProductSuggestion(p) {
    return `
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:10px;margin-top:8px;background:white">
            <strong>${escapeHtml(p.name)}</strong><br>
            <span style="color:#6b7280">${escapeHtml(productShopName(p))} • Stock ${p.stock} • ${formatPrice(productFinalPrice(p))}</span><br>
            <button type="button" onclick="OvanieAI.aiAddProduct('${p.id}')">Voir / Ajouter au panier</button>
        </div>
    `;
}

function parseQuoteLine(line) {
    let clean = line.trim();
    if (!clean) return null;

    let qty = 1;
    let m = clean.match(/^([0-9]+)\s+(.+)$/);

    if (m) {
        qty = parseInt(m[1], 10);
        clean = m[2].trim();
    }

    let m2 = clean.match(/(.+?)\s*[xX*]\s*([0-9]+)$/);

    if (m2) {
        clean = m2[1].trim();
        qty = parseInt(m2[2], 10);
    }

    return { name: clean, qty: Math.max(1, qty) };
}

window.OvanieAI = {
    aiAddProduct,

    checkAvailabilityByText() {
        const input = document.getElementById('availabilityInput');
        const result = document.getElementById('voiceResult');
        if (!input || !result) return;

        const q = input.value.trim();
        result.style.display = 'block';

        if (!q) {
            result.innerHTML = 'Écrivez le nom du produit recherché. Exemple : tuile lisse, PMCP4695A, tôle bac aluminium.';
            return;
        }

        const matches = findProductsByText(q).slice(0, 5);

        if (!matches.length) {
            const msg = `Aucun produit trouvé pour ${q}. Vérifiez l’orthographe ou contactez l’assistance OVANIE.`;
            result.innerHTML = `❌ ${escapeHtml(msg)}<br><button type="button" onclick="document.getElementById('openCallModal')?.click()">Demander assistance</button>`;
            speak(msg);
            return;
        }

        const main = matches[0];
        const msg = `${main.name} est disponible chez ${productShopName(main)}. Stock estimé : ${main.stock}. Prix : ${formatPrice(productFinalPrice(main))}.`;

        result.innerHTML = `✅ ${escapeHtml(msg)}<br>${matches.map(renderAIProductSuggestion).join('')}`;
        speak(`${msg} Voulez-vous l’ajouter au panier ?`);
    },

    suggestAvailableProducts() {
        const result = document.getElementById('voiceResult');
        if (!result) return;

        result.style.display = 'block';
        result.innerHTML = OVANIE_PRODUCTS_CACHE
            .filter(p => Number(p.stock) > 0)
            .slice(0, 8)
            .map(renderAIProductSuggestion)
            .join('') || 'Aucun produit disponible chargé.';
    },

    startVoice() {
        const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SR) {
            speak('Écrivez simplement le nom du produit dans le champ de recherche.');
            alert('Reconnaissance vocale non disponible sur ce navigateur.');
            return;
        }

        const rec = new SR();
        rec.lang = 'fr-FR';
        rec.onresult = e => {
            const q = e.results[0][0].transcript;
            const input = document.getElementById('availabilityInput');
            if (input) input.value = q;
            window.OvanieAI.checkAvailabilityByText();
        };
        rec.start();
    },

    fillExampleQuote() {
        const area = document.getElementById('quoteList');
        if (!area) return;
        area.value = '10 tuile lisse\n5 TOLE BAC ALUMINIUM\n2 WC à réservoir séparé\n20 PMCP4695A';
    },

    clearQuote() {
        const area = document.getElementById('quoteList');
        const result = document.getElementById('quoteResult');
        if (area) area.value = '';
        if (result) result.style.display = 'none';
    },

    makeListQuote() {
        const area = document.getElementById('quoteList');
        const levelEl = document.getElementById('quoteLevel');
        const shopEl = document.getElementById('quoteShop');
        const result = document.getElementById('quoteResult');

        if (!area || !levelEl || !shopEl || !result) return;

        const raw = area.value.trim();
        const level = levelEl.value;
        const shop = shopEl.value;

        result.style.display = 'block';

        if (!raw) {
            result.innerHTML = 'Ajoutez une liste de produits. Exemple :<br>10 tuile lisse<br>5 tôle bac aluminium<br>2 WC à réservoir séparé';
            return;
        }

        const source = shop === 'all'
            ? OVANIE_PRODUCTS_CACHE
            : OVANIE_PRODUCTS_CACHE.filter(p => productShopName(p) === shop);

        const lines = raw.split('\n').map(parseQuoteLine).filter(Boolean);
        const coef = level === 'premium' ? 1.25 : 1;

        const found = [];
        const missing = [];
        let total = 0;

        lines.forEach(item => {
            const p = findProductByText(item.name, source);

            if (p) {
                const unit = Math.round(productFinalPrice(p) * coef);
                const lineTotal = unit * item.qty;
                total += lineTotal;
                found.push({ p, qty: item.qty, unit, lineTotal });
            } else {
                missing.push(item);
            }
        });

        OVANIE_LAST_QUOTE = found;

        const rows = found.map(x => `
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee">
                    <strong>${escapeHtml(x.p.name)}</strong><br>
                    <span style="color:#6b7280">${escapeHtml(productShopName(x.p))} • Stock ${x.p.stock}</span>
                </td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:center">${x.qty}</td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:right">${formatPrice(x.unit)}</td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:right"><strong>${formatPrice(x.lineTotal)}</strong></td>
            </tr>
        `).join('');

        const missingHtml = missing.length
            ? `<div style="margin-top:12px;padding:10px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa"><strong>Produits non trouvés :</strong> ${escapeHtml(missing.map(x => x.name).join(', '))}</div>`
            : '';

        const note = level === 'premium'
            ? '<div style="margin-top:10px;color:#0797df;font-weight:800">Premium inclut une marge qualité/service estimée à +25%.</div>'
            : '<div style="margin-top:10px;color:#6b7280">Basic utilise le prix catalogue estimé.</div>';

        const msg = `Devis ${level.toUpperCase()} calculé. Total estimé ${formatPrice(total)}. L’IA propose d’ajouter les produits trouvés au panier.`;

        result.innerHTML = `
            <strong>${escapeHtml(msg)}</strong>
            ${note}
            <div style="overflow:auto;margin-top:12px">
                <table style="width:100%;border-collapse:collapse;background:white;border-radius:12px;overflow:hidden">
                    <thead>
                        <tr style="background:#eef3f8">
                            <th style="padding:8px;text-align:left">Produit</th>
                            <th style="padding:8px">Qté</th>
                            <th style="padding:8px;text-align:right">Prix</th>
                            <th style="padding:8px;text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody>${rows || '<tr><td colspan="4" style="padding:10px">Aucun produit trouvé.</td></tr>'}</tbody>
                </table>
            </div>
            ${missingHtml}
            <button type="button" onclick="OvanieAI.addQuoteToCart()">Voir / ajouter les produits trouvés</button>
        `;

        speak(msg);
    },

    addQuoteToCart() {
        if (!OVANIE_LAST_QUOTE.length) {
            alert('Aucun produit trouvé dans le devis.');
            return;
        }

        const first = OVANIE_LAST_QUOTE[0].p;
        window.location.href = `/catalog?product=${encodeURIComponent(first.id)}`;
    }
};

/* ================= INIT HOME ================= */

function hydrateQuoteShopSelect(products) {
    const select = document.getElementById('quoteShop');
    if (!select) return;

    const current = select.value || 'all';
    const shops = Array.from(new Set(products.map(productShopName).filter(Boolean))).sort();

    select.innerHTML = '<option value="all">Toutes les boutiques</option>' +
        shops.map(s => `<option>${escapeHtml(s)}</option>`).join('');

    select.value = current;
}

async function initHome() {
    const products = await apiGetJson('/api/products?per_page=500');
    const businessItems = await apiGetBusiness();

    if (!Array.isArray(products)) {
        initAllCarousels();
        return;
    }

    OVANIE_PRODUCTS_CACHE = products;
    hydrateQuoteShopSelect(products);

    const topSellers = await apiGetJson('/api/products/top-sellers?limit=500');

    const blackFridayProducts = products
        .filter(p => getProductTag(p) === 'black-friday')
        .sort((a, b) => (b.sales || 0) - (a.sales || 0))
        .slice(0, MAX_PER_SECTION);

    const flashProducts = products
        .filter(p => getProductTag(p) === 'flash-sale')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, MAX_PER_SECTION);

    const normalProducts = products
        .filter(p => getProductTag(p) === 'normal-sale')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, MAX_PER_SECTION);

    injectItemsArray(
        blackFridayProducts,
        document.querySelector('#black-friday-carousel .carousel-track')
    );

    injectItemsArray(
        flashProducts,
        document.querySelector('#flash-sale-carousel .carousel-track2')
    );

    injectItemsArray(
        normalProducts,
        document.querySelector('#normal-sale-carousel .carousel-track2')
    );

    const businessContainer = document.querySelector('#immo-business-carousel .carousel-track2');

    if (businessContainer && businessItems.length) {
        const devis = businessItems
            .filter(i => String(i.type || '').toLowerCase() === 'devis')
            .slice(0, 12);

        const appels = businessItems
            .filter(i => {
                const type = String(i.type || '').toLowerCase();
                return type === 'appel' || type === 'appel_offre' || type === 'appel-offre';
            })
            .slice(0, 12);

        injectBusinessItems([...devis, ...appels], businessContainer);
    }

    const grid = document.getElementById('products-grid');

    if (grid) {
        injectItemsArray(
            products
                .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
                .slice(0, MAX_PER_SECTION),
            grid
        );
    }

    if (topSellers.length) {
        const topContainer = document.getElementById('top-sellers');
        if (topContainer) {
            injectItemsArray(topSellers, topContainer);
        }
    }

    setTimeout(initAllCarousels, 250);
}

/* ================= DOM READY ================= */

document.addEventListener('DOMContentLoaded', () => {
    initHome();
    initAllCarousels();
});


/* ==========================================================
   OVANIE IA – VERSION ROBUSTE
   - Boutons sans inline obligatoire
   - Utilise d'abord les produits déjà rendus par Blade
   - Complète avec /api/products
   - Disponibilité par saisie libre
   - Devis par liste client + ajout/visualisation panier
========================================================== */
(function () {
    'use strict';

    function aiNormalize(txt = '') {
        return String(txt)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9 ]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function aiToast(message) {
        let old = document.querySelector('.ovanie-ai-toast');
        if (old) old.remove();

        const toast = document.createElement('div');
        toast.className = 'ovanie-ai-toast';
        toast.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:9999;background:#0b0d10;color:white;padding:14px 18px;border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.28);font-weight:800;max-width:360px;line-height:1.4';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 2800);
    }

    function aiSpeak(message) {
        try {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'fr-FR';
                utterance.rate = 0.95;
                window.speechSynthesis.speak(utterance);
            }
        } catch (e) {
            console.warn('Speech synthesis indisponible', e);
        }
    }

    function aiFormatPrice(value) {
        const n = Number(value);
        return isNaN(n) || n <= 0 ? 'Sur devis' : n.toLocaleString('fr-FR') + ' FCFA';
    }

    function aiEscape(s = '') {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[c]);
    }

    function aiShopName(product) {
        return product?.shop?.name || product?.shop_name || 'OVANIE';
    }

    function aiCity(product) {
        return product?.shop_commune || product?.shop_city || product?.shop_region || 'Côte d’Ivoire';
    }

    function aiPrice(product) {
        return Number(product?.final_price || product?.promo_price || product?.price || 0);
    }

    function aiStock(product) {
        return Number(product?.stock || 0);
    }

    function aiMergeProducts(list) {
        if (!Array.isArray(list)) return;

        const current = Array.isArray(window.OVANIE_PRODUCTS_CACHE) ? window.OVANIE_PRODUCTS_CACHE : [];
        const map = new Map();

        [...current, ...list].forEach(product => {
            if (!product) return;
            const key = String(product.id || product.name || Math.random());
            map.set(key, product);
        });

        window.OVANIE_PRODUCTS_CACHE = Array.from(map.values());
        try { OVANIE_PRODUCTS_CACHE = window.OVANIE_PRODUCTS_CACHE; } catch (e) { }
        hydrateAIShopSelect();
    }

    function aiGetProducts() {
        const existing = Array.isArray(window.OVANIE_PRODUCTS_CACHE) ? window.OVANIE_PRODUCTS_CACHE : [];
        const globalCache = (typeof OVANIE_PRODUCTS_CACHE !== 'undefined' && Array.isArray(OVANIE_PRODUCTS_CACHE)) ? OVANIE_PRODUCTS_CACHE : [];
        const boot = Array.isArray(window.OVANIE_BOOTSTRAP_PRODUCTS) ? window.OVANIE_BOOTSTRAP_PRODUCTS : [];

        if (existing.length) return existing;
        if (globalCache.length) return globalCache;
        if (boot.length) {
            aiMergeProducts(boot);
            return boot;
        }
        return [];
    }

    function aiScoreProduct(query, product) {
        const q = aiNormalize(query);
        if (!q) return 0;

        const hay = aiNormalize([
            product.name,
            product.title,
            aiShopName(product),
            product.category,
            product.sale_type,
            Array.isArray(product.tags) ? product.tags.join(' ') : ''
        ].filter(Boolean).join(' '));

        if (!hay) return 0;

        let score = 0;
        if (hay.includes(q)) score += 10;

        q.split(' ').forEach(word => {
            if (word.length > 1 && hay.includes(word)) score += 2;
        });

        const name = aiNormalize(product.name || product.title || '');
        if (name.startsWith(q)) score += 5;

        return score;
    }

    function aiFindProducts(query, source) {
        const list = Array.isArray(source) ? source : aiGetProducts();
        return list
            .map(product => ({ product, score: aiScoreProduct(query, product) }))
            .filter(row => row.score > 0)
            .sort((a, b) => b.score - a.score)
            .map(row => row.product);
    }

    function renderSuggestion(product) {
        const available = aiStock(product) > 0;
        const status = available ? 'Disponible' : 'Rupture';
        const color = available ? '#0797df' : '#ef4444';

        return `
            <div class="ai-card-result">
                <strong>${aiEscape(product.name || product.title || 'Produit')}</strong>
                <div class="ai-meta">${aiEscape(aiShopName(product))} • ${aiEscape(aiCity(product))} • Stock ${aiStock(product)} • ${aiFormatPrice(aiPrice(product))}</div>
                <div style="margin-top:6px;font-weight:900;color:${color}">${status}</div>
                <button type="button" onclick="OvanieAI.aiAddProduct('${aiEscape(product.id)}')">Voir / Ajouter au panier</button>
            </div>
        `;
    }

    function parseQuoteLine(line) {
        let clean = String(line || '').trim();
        if (!clean) return null;

        let qty = 1;

        let startQty = clean.match(/^([0-9]+)\s+(.+)$/);
        if (startQty) {
            qty = parseInt(startQty[1], 10);
            clean = startQty[2].trim();
        }

        let endQty = clean.match(/(.+?)\s*[xX*]\s*([0-9]+)$/);
        if (endQty) {
            clean = endQty[1].trim();
            qty = parseInt(endQty[2], 10);
        }

        return { name: clean, qty: Math.max(1, qty || 1) };
    }

    function hydrateAIShopSelect() {
        const select = document.getElementById('quoteShop');
        if (!select) return;

        const current = select.value || 'all';
        const shops = Array.from(new Set(aiGetProducts().map(aiShopName).filter(Boolean))).sort();

        select.innerHTML = '<option value="all">Toutes les boutiques</option>' +
            shops.map(shop => `<option value="${aiEscape(shop)}">${aiEscape(shop)}</option>`).join('');

        select.value = shops.includes(current) ? current : 'all';
    }

    function hydrateCacheFromDOM() {
        const domProducts = [];
        document.querySelectorAll('.product-card[data-product-id], .product-card').forEach((card, index) => {
            const name = card.dataset.productName ||
                card.querySelector('.product-title')?.textContent?.trim() ||
                card.querySelector('h4')?.textContent?.trim();

            if (!name) return;

            const priceText = card.querySelector('.promo-price, .featured-price')?.textContent || '';
            const price = Number(priceText.replace(/[^0-9]/g, '') || 0);
            const shop = card.querySelector('.shop, .featured-shop-name, .stock')?.textContent?.trim() || 'OVANIE';

            domProducts.push({
                id: card.dataset.productId || `dom-${index}`,
                name,
                price,
                final_price: price,
                stock: 1,
                shop: { name: shop },
                shop_city: 'Côte d’Ivoire',
                category: '',
                tags: ['page']
            });
        });

        aiMergeProducts(domProducts);
    }

    window.OvanieAI = window.OvanieAI || {};

    Object.assign(window.OvanieAI, {
        aiAddProduct(productId) {
            const product = aiGetProducts().find(p => String(p.id) === String(productId));

            if (!product) {
                aiToast('Produit introuvable dans le cache IA.');
                return;
            }

            // Préserve les routes actuelles : redirection vers le catalogue avec l’id produit.
            window.location.href = `/catalog?product=${encodeURIComponent(product.id)}`;
        },

        checkAvailabilityByText() {
            const input = document.getElementById('availabilityInput');
            const result = document.getElementById('voiceResult');
            if (!input || !result) return;

            const query = input.value.trim();
            result.style.display = 'block';

            if (!query) {
                result.innerHTML = `
                    <strong>Écrivez le nom du produit recherché.</strong><br>
                    Exemples : <b>tuile lisse</b>, <b>PMCP4695A</b>, <b>tôle</b>, <b>WC</b>.
                `;
                input.focus();
                return;
            }

            const matches = aiFindProducts(query).slice(0, 6);

            if (!matches.length) {
                const msg = `Aucun produit trouvé pour ${query}. Vérifiez l’orthographe ou contactez l’assistance OVANIE.`;
                result.innerHTML = `
                    ❌ ${aiEscape(msg)}
                    <br><button type="button" onclick="document.getElementById('openCallModal')?.click()">Demander assistance</button>
                `;
                aiSpeak(msg);
                return;
            }

            const first = matches[0];
            const available = aiStock(first) > 0;
            const msg = available
                ? `${first.name || first.title} est disponible chez ${aiShopName(first)}. Stock estimé : ${aiStock(first)}. Prix : ${aiFormatPrice(aiPrice(first))}.`
                : `${first.name || first.title} existe dans le catalogue, mais le stock semble indisponible.`;

            result.innerHTML = `
                ✅ <strong>${aiEscape(msg)}</strong>
                <div style="margin-top:10px;color:#6b7280">Produits correspondants trouvés par l’IA :</div>
                ${matches.map(renderSuggestion).join('')}
            `;

            aiSpeak(`${msg} Voulez-vous l’ajouter au panier ?`);
        },

        suggestAvailableProducts() {
            const result = document.getElementById('voiceResult');
            if (!result) return;

            const available = aiGetProducts()
                .filter(product => aiStock(product) > 0)
                .slice(0, 10);

            result.style.display = 'block';
            result.innerHTML = available.length
                ? `<strong>Produits disponibles détectés :</strong>${available.map(renderSuggestion).join('')}`
                : 'Aucun produit disponible chargé. Rechargez la page ou vérifiez l’API produits.';

            aiToast(`${available.length} produits disponibles affichés.`);
        },

        startVoice() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SR) {
                aiToast('Reconnaissance vocale non disponible sur ce navigateur.');
                aiSpeak('Écrivez simplement le nom du produit dans le champ de recherche.');
                document.getElementById('availabilityInput')?.focus();
                return;
            }

            const rec = new SR();
            rec.lang = 'fr-FR';
            rec.interimResults = false;
            rec.maxAlternatives = 1;

            rec.onstart = () => aiToast('IA OVANIE vous écoute...');
            rec.onerror = () => aiToast('Impossible d’utiliser le micro. Écrivez le produit directement.');
            rec.onresult = event => {
                const text = event.results[0][0].transcript;
                const input = document.getElementById('availabilityInput');
                if (input) input.value = text;
                window.OvanieAI.checkAvailabilityByText();
            };

            rec.start();
        },

        fillExampleQuote() {
            const area = document.getElementById('quoteList');
            if (!area) return;

            area.value = [
                '10 tuile lisse',
                '5 TOLE BAC ALUMINIUM',
                '2 WC à réservoir séparé',
                '20 PMCP4695A'
            ].join('\n');

            aiToast('Exemple de devis ajouté.');
        },

        clearQuote() {
            const area = document.getElementById('quoteList');
            const result = document.getElementById('quoteResult');

            if (area) area.value = '';
            if (result) {
                result.style.display = 'none';
                result.innerHTML = '';
            }

            window.OVANIE_LAST_QUOTE = [];
            try { OVANIE_LAST_QUOTE = []; } catch (e) { }
        },

        makeListQuote() {
            const area = document.getElementById('quoteList');
            const levelEl = document.getElementById('quoteLevel');
            const shopEl = document.getElementById('quoteShop');
            const result = document.getElementById('quoteResult');

            if (!area || !levelEl || !shopEl || !result) {
                aiToast('Zone IA devis introuvable.');
                return;
            }

            const raw = area.value.trim();
            const level = levelEl.value;
            const selectedShop = shopEl.value;

            result.style.display = 'block';

            if (!raw) {
                result.innerHTML = `
                    <strong>Ajoutez une liste de produits.</strong><br>
                    Exemple :<br>10 tuile lisse<br>5 tôle bac aluminium<br>2 WC à réservoir séparé
                `;
                area.focus();
                return;
            }

            const allProducts = aiGetProducts();
            const source = selectedShop === 'all'
                ? allProducts
                : allProducts.filter(product => aiShopName(product) === selectedShop);

            const lines = raw.split('\n').map(parseQuoteLine).filter(Boolean);
            const coef = level === 'premium' ? 1.25 : 1;

            const found = [];
            const missing = [];
            let total = 0;

            lines.forEach(item => {
                const product = aiFindProducts(item.name, source)[0];

                if (product) {
                    const unit = Math.round(aiPrice(product) * coef);
                    const lineTotal = unit * item.qty;

                    total += lineTotal;
                    found.push({ product, qty: item.qty, unit, lineTotal });
                } else {
                    missing.push(item);
                }
            });

            window.OVANIE_LAST_QUOTE = found;
            try { OVANIE_LAST_QUOTE = found.map(row => ({ p: row.product, qty: row.qty, unit: row.unit, lineTotal: row.lineTotal })); } catch (e) { }

            const rows = found.map(row => `
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #eee">
                        <strong>${aiEscape(row.product.name || row.product.title)}</strong><br>
                        <span style="color:#6b7280">${aiEscape(aiShopName(row.product))} • Stock ${aiStock(row.product)}</span>
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:center">${row.qty}</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right">${aiFormatPrice(row.unit)}</td>
                    <td style="padding:8px;border-bottom:1px solid #eee;text-align:right"><strong>${aiFormatPrice(row.lineTotal)}</strong></td>
                </tr>
            `).join('');

            const missingHtml = missing.length
                ? `<div style="margin-top:12px;padding:10px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa">
                    <strong>Produits non trouvés :</strong> ${missing.map(item => aiEscape(item.name)).join(', ')}<br>
                    L’IA recommande de corriger le nom ou de contacter l’assistance.
                </div>`
                : '';

            const note = level === 'premium'
                ? '<div style="margin-top:10px;color:#0797df;font-weight:900">Premium : estimation avec qualité/service renforcé (+25%).</div>'
                : '<div style="margin-top:10px;color:#6b7280">Basic : estimation selon les prix catalogue chargés.</div>';

            const msg = `Devis ${level.toUpperCase()} calculé. Total estimé ${aiFormatPrice(total)}. ${found.length} produit(s) trouvé(s).`;

            result.innerHTML = `
                <strong>${aiEscape(msg)}</strong>
                ${note}
                <div style="overflow:auto;margin-top:12px">
                    <table style="width:100%;border-collapse:collapse;background:white;border-radius:12px;overflow:hidden">
                        <thead>
                            <tr style="background:#eef3f8">
                                <th style="padding:8px;text-align:left">Produit</th>
                                <th style="padding:8px">Qté</th>
                                <th style="padding:8px;text-align:right">Prix</th>
                                <th style="padding:8px;text-align:right">Total</th>
                            </tr>
                        </thead>
                        <tbody>${rows || '<tr><td colspan="4" style="padding:10px">Aucun produit trouvé.</td></tr>'}</tbody>
                    </table>
                </div>
                ${missingHtml}
                <button type="button" onclick="OvanieAI.addQuoteToCart()">Ajouter les produits trouvés au panier</button>
            `;

            aiSpeak(`${msg} L’IA propose d’ajouter les produits trouvés au panier.`);
        },

        addQuoteToCart() {
            const quote = Array.isArray(window.OVANIE_LAST_QUOTE) ? window.OVANIE_LAST_QUOTE : [];

            if (!quote.length) {
                aiToast('Aucun produit trouvé dans le devis.');
                return;
            }

            const first = quote[0].product || quote[0].p;
            if (first?.id) {
                window.location.href = `/catalog?product=${encodeURIComponent(first.id)}`;
            } else {
                aiToast('Produits du devis prêts pour le panier.');
            }
        }
    });

    function bindAIButtons() {
        document.querySelector('.js-ai-availability')?.addEventListener('click', () => window.OvanieAI.checkAvailabilityByText());
        document.querySelector('.js-ai-voice')?.addEventListener('click', () => window.OvanieAI.startVoice());
        document.querySelector('.js-ai-available')?.addEventListener('click', () => window.OvanieAI.suggestAvailableProducts());
        document.querySelector('.js-ai-example')?.addEventListener('click', () => window.OvanieAI.fillExampleQuote());
        document.querySelector('.js-ai-quote')?.addEventListener('click', () => window.OvanieAI.makeListQuote());
        document.querySelector('.js-ai-clear')?.addEventListener('click', () => window.OvanieAI.clearQuote());

        document.getElementById('availabilityInput')?.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.OvanieAI.checkAvailabilityByText();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (Array.isArray(window.OVANIE_BOOTSTRAP_PRODUCTS)) {
            aiMergeProducts(window.OVANIE_BOOTSTRAP_PRODUCTS);
        }

        hydrateCacheFromDOM();
        hydrateAIShopSelect();
        bindAIButtons();

        // Rehydrate après chargement API/carrousels.
        setTimeout(() => {
            hydrateCacheFromDOM();
            hydrateAIShopSelect();
        }, 1200);
    });
})();
