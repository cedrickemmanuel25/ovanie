/* OVANIE PRO — parcours « Compte simple » : publier une offre ou un besoin (aucune dépendance). */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    // Drapeau synchronisé avec le pays / l'indicatif choisi.
    $$('[data-op-flag-select]').forEach(function (box) {
        var select = $('select', box), img = $('[data-flag-img]', box);
        if (!select || !img) return;
        var sync = function () {
            var flag = select.options[select.selectedIndex].getAttribute('data-flag');
            img.hidden = !flag;
            if (flag) img.src = select.getAttribute('data-flag-base') + '/' + flag + '.svg';
        };
        select.addEventListener('change', sync);
        sync();
    });

    var form = $('[data-op-form]');
    if (!form) return;

    var units = JSON.parse(form.getAttribute('data-units') || '{}');
    var isOffer = form.getAttribute('data-kind') === 'offer';
    var preview = $('[data-op-preview]');
    var pv = function (key) { return preview ? $('[data-pv="' + key + '"]', preview) : null; };
    var setText = function (key, text) { var el = pv(key); if (el) el.textContent = text; };
    var field = function (name) { return form.elements[name]; };
    var value = function (name) {
        var el = field(name);
        if (!el) return '';
        if (el instanceof RadioNodeList) return el.value;
        if (el.type === 'checkbox') return el.checked;
        return el.value;
    };
    var shortUnit = function (key) { return (units[key] || '').replace(/\s*\(.*\)/, '').toLowerCase(); };
    var number = function (raw) {
        var n = parseFloat(String(raw).replace(/\s/g, '').replace(',', '.'));
        return isNaN(n) ? null : n.toLocaleString('fr-FR', { maximumFractionDigits: 2 }).replace(/ /g, ' ');
    };

    // Catégorie → sous-catégories.
    var subMap = JSON.parse(form.getAttribute('data-subcategories') || '{}');
    var cat = $('[data-op-category]', form), sub = $('[data-op-subcategory]', form);
    var fillSub = function (keep) {
        var selected = keep ? sub.getAttribute('data-selected') : '';
        sub.innerHTML = '<option value="">Choisir…</option>';
        (subMap[cat.value] || []).forEach(function (s) {
            var o = new Option(s.name, s.id, false, String(s.id) === String(selected));
            sub.add(o);
        });
        sub.disabled = !cat.value;
    };
    if (cat && sub) { cat.addEventListener('change', function () { fillSub(false); refresh(); }); fillSub(true); }

    // Compteur de caractères de la description.
    $$('[data-op-count]', form).forEach(function (area) {
        var out = document.getElementById(area.getAttribute('data-op-count'));
        var count = function () { if (out) out.textContent = area.value.length.toLocaleString('fr-FR'); };
        area.addEventListener('input', count);
        count();
    });

    // Offre : la durée choisie fixe la date de fin de validité.
    var expires = field('expires_on');
    $$('[data-op-validity] input', form).forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!expires) return;
            if (radio.value === 'custom') { expires.focus(); if (expires.showPicker) { try { expires.showPicker(); } catch (e) {} } return; }
            var d = new Date(); d.setDate(d.getDate() + parseInt(radio.value, 10));
            expires.value = d.toISOString().slice(0, 10);
            refresh();
        });
    });
    if (expires) expires.addEventListener('change', function () {
        var custom = $('[data-op-validity] input[value="custom"]', form);
        if (custom) custom.checked = true;
        refresh();
    });

    // Besoin : date précise, quantité à définir, budget.
    var neededBy = $('[data-op-needed-by]', form);
    var syncDeadline = function () { if (neededBy) neededBy.hidden = value('deadline') !== 'date'; };
    $$('[data-op-deadline] input[type=radio]', form).forEach(function (r) { r.addEventListener('change', syncDeadline); });
    syncDeadline();

    var qtyDefine = $('[data-op-qty-define]', form);
    var syncQty = function () {
        if (!qtyDefine) return;
        ['quantity', 'unit'].forEach(function (n) { if (field(n)) field(n).disabled = qtyDefine.checked; });
    };
    if (qtyDefine) qtyDefine.addEventListener('change', function () { syncQty(); refresh(); });
    syncQty();

    var range = $('[data-op-budget-range]', form);
    var syncBudget = function () { if (range) range.hidden = value('budget_mode') !== 'range'; };
    $$('[data-op-budget] input', form).forEach(function (r) { r.addEventListener('change', syncBudget); });
    syncBudget();

    // Photos : on peut en ajouter plusieurs fois avant d'envoyer, et retirer une photo ajoutée.
    var photoInput = $('[data-op-photos]', form);
    var thumbs = $('[data-op-thumbs]', form);
    var photoStore = new DataTransfer();
    var firstPhotoUrl = function () {
        var existing = $$('.op-thumb[data-existing]', form).filter(function (f) { return !$('input', f).checked; })[0];
        if (existing) return $('img', existing).src;
        var fresh = $('.op-thumb[data-new] img', form);
        return fresh ? fresh.src : null;
    };
    var renderPhotos = function () {
        $$('.op-thumb[data-new]', thumbs).forEach(function (n) { n.remove(); });
        Array.prototype.forEach.call(photoStore.files, function (file, index) {
            var fig = document.createElement('figure');
            fig.className = 'op-thumb';
            fig.setAttribute('data-new', '');
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = '';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'op-thumb__remove';
            btn.setAttribute('aria-label', 'Retirer ' + file.name);
            btn.textContent = '×';
            btn.addEventListener('click', function () {
                var next = new DataTransfer();
                Array.prototype.forEach.call(photoStore.files, function (f, i) { if (i !== index) next.items.add(f); });
                photoStore = next;
                photoInput.files = photoStore.files;
                renderPhotos();
            });
            fig.appendChild(img);
            fig.appendChild(btn);
            thumbs.appendChild(fig);
        });
        $$('.op-thumb figcaption', thumbs).forEach(function (c) { c.remove(); });
        var first = $$('.op-thumb', thumbs).filter(function (f) { var c = $('input', f); return !c || !c.checked; })[0];
        if (first) { var cap = document.createElement('figcaption'); cap.textContent = 'Image principale'; first.appendChild(cap); }
        refresh();
    };
    if (photoInput && thumbs) {
        photoInput.addEventListener('change', function () {
            Array.prototype.forEach.call(photoInput.files, function (f) { photoStore.items.add(f); });
            photoInput.files = photoStore.files;
            renderPhotos();
        });
        $$('.op-thumb[data-existing] input', thumbs).forEach(function (c) {
            c.addEventListener('change', function () { c.closest('.op-thumb').classList.toggle('is-removed', c.checked); renderPhotos(); });
        });
    }

    var docInput = $('[data-op-docs]', form), docList = $('[data-op-doc-list]', form);
    var docStore = new DataTransfer();
    if (docInput && docList) {
        docInput.addEventListener('change', function () {
            Array.prototype.forEach.call(docInput.files, function (f) { docStore.items.add(f); });
            docInput.files = docStore.files;
            $$('.op-doc-pill[data-new]', docList).forEach(function (n) { n.remove(); });
            Array.prototype.forEach.call(docStore.files, function (f) {
                var pill = document.createElement('span');
                pill.className = 'op-doc-pill';
                pill.setAttribute('data-new', '');
                pill.textContent = f.name.length > 20 ? f.name.slice(0, 18) + '…' : f.name;
                docList.appendChild(pill);
            });
        });
        $$('.op-doc-pill input', docList).forEach(function (c) {
            c.addEventListener('change', function () { c.closest('.op-doc-pill').classList.toggle('is-removed', c.checked); });
        });
    }

    // Aperçu en direct.
    function refresh() {
        if (!preview) return;
        var title = value('title').trim();
        var titleEl = pv('title');
        if (titleEl) titleEl.textContent = title || titleEl.getAttribute('data-empty');
        setText('category', cat && cat.value ? cat.options[cat.selectedIndex].text : 'Catégorie');
        setText('subcategory', sub && sub.value ? sub.options[sub.selectedIndex].text : 'Sous-catégorie');
        var place = [isOffer ? '' : value('location_detail'), value('city'), value('country')].filter(function (x) { return x && String(x).trim(); }).join(', ');
        setText('place', place || 'Localisation');

        var qty = number(value('quantity'));
        var unitWord = shortUnit(value('unit'));
        var qtyNum = parseFloat(String(value('quantity')).replace(/\s/g, '').replace(',', '.'));
        if (unitWord && qtyNum > 1 && !/(s|x|²|³|kg)$/.test(unitWord)) unitWord += 's';
        setText('qty', qtyDefine && qtyDefine.checked ? 'À définir' : ((qty || '—') + ' ' + unitWord).trim());

        if (isOffer) {
            var price = number(value('price'));
            setText('price', value('price_on_request') ? 'Sur demande' : (price ? price + ' ' + value('currency') + ' / ' + shortUnit(value('unit')) : 'Non communiqué'));
            if (expires && expires.value) {
                var days = Math.max(1, Math.round((new Date(expires.value) - new Date().setHours(0, 0, 0, 0)) / 86400000));
                setText('days', days);
            }
        } else {
            var dl = $('[data-op-deadline] input:checked', form);
            if (dl) setText('deadline', dl.value === 'date' && neededBy && neededBy.value ? 'Avant le ' + neededBy.value.split('-').reverse().join('/') : dl.getAttribute('data-label'));
            var min = number(value('budget_min')), max = number(value('budget_max'));
            setText('budget', value('budget_mode') === 'range' && max ? (min || '0') + ' – ' + max + ' XOF' : 'Recevoir des propositions');
        }

        var url = firstPhotoUrl(), img = pv('img'), empty = pv('img-empty');
        if (img) { img.hidden = !url; if (url) img.src = url; }
        if (empty) empty.hidden = !!url;
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    if (neededBy) neededBy.addEventListener('change', refresh);
    refresh();

    // Bouton « Aperçu » : met l'aperçu en évidence.
    var focusBtn = $('[data-op-preview-focus]', form);
    if (focusBtn && preview) focusBtn.addEventListener('click', function () {
        preview.scrollIntoView({ behavior: 'smooth', block: 'center' });
        preview.classList.remove('is-flash');
        void preview.offsetWidth;
        preview.classList.add('is-flash');
    });

    // Retour arrière sur une ancre « Modifier » : ouvre la bonne section.
    if (location.hash) { var target = document.getElementById(location.hash.slice(1)); if (target) target.classList.add('is-flash'); }
})();
