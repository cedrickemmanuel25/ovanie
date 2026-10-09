/* Progressive mobile disclosure: original forms, links and table cells are retained. */
(() => {
    'use strict';
    const mobile = window.matchMedia('(max-width: 1023px)');
    let sequence = 0;
    const all = (selector, root = document) => [...root.querySelectorAll(selector)];

    function setOpen(panel, open) {
        panel.dataset.vmOpen = String(open);
        const toggle = panel.querySelector(':scope > .vm-toggle');
        toggle?.setAttribute('aria-expanded', String(open));
        if (toggle) toggle.querySelector('.vm-toggle-state').textContent = open ? 'Réduire' : 'Afficher';
    }

    function fold(panel, label, open = false) {
        if (!panel || panel.hasAttribute('data-vm-fold')) return;
        const heading = label ? null : panel.querySelector('h2, h3');
        label ||= heading?.textContent.trim();
        if (!label) return;
        panel.setAttribute('data-vm-fold', '');
        const children = [...panel.children];
        children.forEach(child => { child.classList.add('vm-fold-content'); child.id ||= `vm-content-${++sequence}`; });
        heading?.classList.add('vm-source-heading');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vm-toggle';
        button.setAttribute('aria-controls', children.map(child => child.id).join(' '));
        const title = document.createElement('span');
        title.textContent = label;
        const state = document.createElement('span');
        state.className = 'vm-toggle-state';
        button.append(title, state);
        button.addEventListener('click', () => setOpen(panel, panel.dataset.vmOpen !== 'true'));
        panel.prepend(button);
        setOpen(panel, open || !!panel.querySelector('[aria-invalid="true"], .invalid-feedback, .field-error, .se-error, .pm-error'));
    }

    function reveal(target) {
        if (!target || !mobile.matches) return;
        for (let node = target; node; node = node.parentElement) {
            if (node.hasAttribute('data-vm-fold')) setOpen(node, true);
        }
    }

    function tables() {
        all('.oc-table, .sales-table, .vp-table, .vr-table, .ds-table, .pm-table, .doc-history-table, .dl-table, .orders-table, .cat-table, .cp-table').forEach(table => {
            const headers = all('thead th', table).map(th => th.textContent.trim());
            if (!headers.length) return;
            table.classList.add('vm-records');
            table.closest('div')?.classList.add('vm-records-wrap');
            all('tbody tr', table).forEach(row => {
                const cells = [...row.cells];
                if (cells.some(cell => cell.colSpan > 1)) return;
                let extras = 0;
                cells.forEach((cell, index) => {
                    cell.dataset.label = headers[index] || 'Détail';
                    if (/action/i.test(headers[index])) cell.classList.add('vm-record-actions');
                    // Grille tarifaire de livraison : l'essentiel est la commune, son tarif et son délai.
                    const primary = table.matches('.cat-table, .cp-table')
                        ? index === 0 || /produit|prix|stock|action/i.test(headers[index])
                        : table.matches('.dl-table')
                            ? /commune|tarif|délai|statut|action/i.test(headers[index])
                            : (index === 0 && !/date/i.test(headers[index])) || /commande|réf|statut|net vendeur|montant|total|action/i.test(headers[index]);
                    if (!primary && cells.length > 4) { cell.classList.add('vm-record-extra'); extras++; }
                });
                if (extras) {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'vm-record-toggle';
                    button.textContent = 'Plus de détails'; button.setAttribute('aria-expanded', 'false');
                    button.addEventListener('click', () => {
                        const open = row.classList.toggle('vm-record-open');
                        button.setAttribute('aria-expanded', String(open));
                        button.textContent = open ? 'Moins de détails' : 'Plus de détails';
                    });
                    let actions = cells.find(cell => cell.classList.contains('vm-record-actions'));
                    // Grille tarifaire sans colonne d'action : le bouton a sa propre ligne en bas de la carte.
                    if (!actions && table.matches('.dl-table')) {
                        actions = document.createElement('td');
                        actions.className = 'vm-record-actions';
                        row.append(actions);
                    }
                    if (actions) actions.append(button);
                    else cells.find(cell => !cell.classList.contains('vm-record-extra')).append(button);
                }
            });
            if (table.matches('.cat-table, .cp-table')) {
                const more = document.createElement('button');
                more.type = 'button'; more.className = 'vm-more-records';
                let expanded = false;
                const update = () => {
                    const rows = all('tbody tr', table).filter(row => row.style.display !== 'none');
                    all('tbody tr', table).forEach(row => row.classList.remove('vm-beyond-limit'));
                    rows.slice(3).forEach(row => row.classList.toggle('vm-beyond-limit', !expanded));
                    more.hidden = rows.length <= 3;
                    more.textContent = expanded ? 'Réduire la liste' : `Afficher les ${rows.length - 3} autres produits de cette page`;
                    more.setAttribute('aria-expanded', String(expanded));
                };
                more.addEventListener('click', () => { expanded = !expanded; update(); });
                table.after(more); update();
                new MutationObserver(update).observe(table.tBodies[0], {subtree:true, attributes:true, attributeFilter:['style']});
            }
        });
    }

    function init() {
        // Formulaire d'ouverture de boutique : aucun repli, tous les champs restent visibles.
        all('.payment-rules').forEach(panel => fold(panel, 'Comprendre les modes de paiement'));
        const groups = [
            ['.chart-card, .sales-chart-card, .vp-chart-card, .vr-chart-panel', 'Évolution des ventes et statistiques'],
            ['.finance-card, .activity-card, .sales-collection-card, .vp-method-card, .vr-policy', null],
            ['.sales-mini-grid, .vp-status-strip, .vr-mini-metrics', 'Détail des indicateurs'],
            ['.sales-filter-card, .vp-filters-card, .vr-filters, .ds-filters', 'Rechercher et filtrer'],
            ['.cp-filter-card', 'Statuts et modération'],
            ['.vr-history, .vr-documents, .vr-support, .ds-side-card', null],
            ['.sp-password-card, .sp-block, .sp-help, .sp-overview-col', null],
            ['.se-stats', 'État de la boutique'],
            ['.se-block, .se-docs', null],
            ['.st-main-grid .st-block, .st-bottom-grid .st-block', null],
            ['.pm-info-card, .pm-support, .pm-bottom > .pm-card', null],
            ['.doc-side-card, .doc-history, #uploadDocumentPanel', null],
            ['.od-history', null],
            ['.va-module, .cgu-section', null],
        ];
        groups.forEach(([selector, label]) => all(selector).forEach(panel => fold(panel, label)));
        all('.se-block').forEach((panel, index) => { if (index === 0) setOpen(panel, true); });
        all('.sp-overview-col').forEach((panel, index) => { if (index === 0) setOpen(panel, true); });
        all('.dl-form-body > .dl-section').forEach((panel, index) => fold(panel, null, index === 0));
        all('.dl-page > .dl-panel').forEach(panel => {
            if (panel.querySelector(':scope > h2')) fold(panel);
        });
        all('.od-products-card').forEach(panel => {
            const total = panel.querySelector('.od-total-row.grand')?.textContent.trim().replace(/\s+/g, ' ');
            fold(panel, total ? `Produits · ${total}` : 'Produits commandés');
        });
        all('.dl-capacity-grid').forEach(panel => fold(panel, 'Détail des capacités de transport'));
        all('.va-aside, .cgu-toc').forEach(panel => fold(panel, 'Sommaire et recherche'));
        all('.od-right > .od-card').forEach(panel => fold(panel));
        // Keep active filters discoverable when returning from a filtered request.
        all('.sales-filter-card, .vp-filters-card, .vr-filters, .ds-filters').forEach(panel => {
            if (all('input:not([type=hidden]),select', panel).some(input => input.value && !['all', '15', '10'].includes(input.value))) setOpen(panel, true);
        });
        tables();
        // Existing dialogs use aria-hidden as their source of truth.
        const dialogs = all('.vr-modal-backdrop, .ds-modal, .doc-modal, .dl-modal');
        const syncDialogLock = () => document.body.classList.toggle('vm-dialog-open', mobile.matches && dialogs.some(dialog => dialog.getAttribute('aria-hidden') === 'false'));
        dialogs.forEach(dialog => new MutationObserver(syncDialogLock).observe(dialog, { attributes:true, attributeFilter:['aria-hidden'] }));
        mobile.addEventListener('change', syncDialogLock); syncDialogLock();
        document.addEventListener('invalid', event => reveal(event.target), true);
        const shopForm = document.getElementById('openShopForm');
        if (shopForm) new MutationObserver(records => records.forEach(record => {
            if (record.target.getAttribute('aria-invalid') === 'true') reveal(record.target);
        })).observe(shopForm, {subtree:true, attributes:true, attributeFilter:['aria-invalid']});
        document.addEventListener('focusin', event => reveal(event.target));
        document.addEventListener('click', event => {
            if (event.target.closest('[data-select-document]')) reveal(document.getElementById('uploadDocumentPanel'));
            const link = event.target.closest('a[href^="#"]');
            if (!link) return;
            const id = link.getAttribute('href').slice(1);
            if (id) reveal(document.getElementById(decodeURIComponent(id)));
        }, true);
        const revealHash = () => { if (location.hash) reveal(document.getElementById(decodeURIComponent(location.hash.slice(1)))); };
        window.addEventListener('hashchange', revealHash); revealHash();
        // Search results in the legal document must expose the matching text.
        all('.cgu-search input, #cguSearch').forEach(input => input.addEventListener('input', () => {
            const term = input.value.trim().toLocaleLowerCase('fr');
            all('.cgu-section').forEach(panel => setOpen(panel, !!term && panel.textContent.toLocaleLowerCase('fr').includes(term)));
        }));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
