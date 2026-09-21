document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-faq-root]');
    if (!root) return;

    const tabs = [...root.querySelectorAll('[data-audience-tab]')];
    const panels = [...root.querySelectorAll('[data-audience-panel]')];
    const search = root.querySelector('#faqSearch');
    const clearSearch = root.querySelector('#clearFaqSearch');
    const empty = root.querySelector('#faqEmpty');
    const sideContainer = root.querySelector('#faqSideCategories');
    const showAllButton = root.querySelector('#showAllFaqCategories');

    let activeAudience = tabs[0]?.dataset.audienceTab || 'buyers';
    let activeCategory = null;

    const normalize = (value) => (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const activePanel = () => root.querySelector(`[data-audience-panel="${activeAudience}"]`);

    const closeAllItems = (panel = activePanel()) => {
        panel?.querySelectorAll('[data-faq-item].is-open').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.faq-item__trigger')?.setAttribute('aria-expanded', 'false');
            const answer = item.querySelector('.faq-item__answer');
            if (answer) answer.hidden = true;
        });
    };

    const renderSideCategories = () => {
        if (!sideContainer) return;
        const panel = activePanel();
        const chips = [...(panel?.querySelectorAll('[data-category-filter]') || [])];
        sideContainer.innerHTML = chips.map((chip) => {
            const label = chip.querySelector('strong')?.textContent?.trim() || '';
            const count = chip.querySelector('small')?.textContent?.trim() || '';
            const category = chip.dataset.categoryFilter;
            const icon = chip.querySelector('svg')?.outerHTML || '';
            return `<button type="button" class="faq-side-category" data-side-category="${category}">${icon}<strong>${label}</strong><small>${count}</small><i data-lucide="chevron-right" class="faq-side-chevron"></i></button>`;
        }).join('');
        window.lucide?.createIcons();
    };

    const updateVisibility = () => {
        const panel = activePanel();
        if (!panel) return;

        const query = normalize(search?.value || '');
        let visibleCount = 0;

        panel.querySelectorAll('[data-faq-item]').forEach((item) => {
            const matchesCategory = !activeCategory || item.dataset.category === activeCategory;
            const matchesSearch = !query || normalize(item.dataset.search).includes(query);
            const visible = matchesCategory && matchesSearch;
            item.hidden = !visible;
            if (visible) visibleCount += 1;
        });

        if (empty) empty.hidden = visibleCount > 0;
        if (clearSearch) clearSearch.hidden = !query;
    };

    const setCategory = (category, { scroll = false } = {}) => {
        activeCategory = category;
        const panel = activePanel();
        panel?.querySelectorAll('[data-category-filter]').forEach((chip) => {
            chip.classList.toggle('is-active', chip.dataset.categoryFilter === category);
        });
        closeAllItems(panel);
        updateVisibility();

        if (scroll) {
            panel?.querySelector('.faq-question-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const setAudience = (audienceId) => {
        activeAudience = audienceId;
        tabs.forEach((tab) => {
            const active = tab.dataset.audienceTab === audienceId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const active = panel.dataset.audiencePanel === audienceId;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });

        const firstChip = activePanel()?.querySelector('[data-category-filter]');
        activeCategory = firstChip?.dataset.categoryFilter || null;
        if (search) search.value = '';
        closeAllItems(activePanel());
        renderSideCategories();
        updateVisibility();
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => setAudience(tab.dataset.audienceTab)));

    panels.forEach((panel) => {
        panel.querySelectorAll('[data-category-filter]').forEach((chip) => {
            chip.addEventListener('click', () => setCategory(chip.dataset.categoryFilter));
        });

        panel.querySelectorAll('.faq-item__trigger').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const item = trigger.closest('[data-faq-item]');
                const wasOpen = item.classList.contains('is-open');
                closeAllItems(panel);
                if (!wasOpen) {
                    item.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                    const answer = item.querySelector('.faq-item__answer');
                    if (answer) answer.hidden = false;
                }
            });
        });
    });

    sideContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-side-category]');
        if (!button) return;
        setCategory(button.dataset.sideCategory, { scroll: true });
    });

    showAllButton?.addEventListener('click', () => {
        activeCategory = null;
        activePanel()?.querySelectorAll('[data-category-filter]').forEach((chip) => chip.classList.remove('is-active'));
        updateVisibility();
        activePanel()?.querySelector('.faq-question-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    search?.addEventListener('input', () => {
        const query = normalize(search.value);
        if (query) {
            activeCategory = null;
            activePanel()?.querySelectorAll('[data-category-filter]').forEach((chip) => chip.classList.remove('is-active'));
        }
        updateVisibility();
    });

    clearSearch?.addEventListener('click', () => {
        if (search) search.value = '';
        const firstChip = activePanel()?.querySelector('[data-category-filter]');
        setCategory(firstChip?.dataset.categoryFilter || null);
        search?.focus();
    });

    setAudience(activeAudience);
    window.lucide?.createIcons();
});
