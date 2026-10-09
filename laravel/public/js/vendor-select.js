/*
 * Espace vendeur — listes de choix.
 * La liste native du navigateur déborde de l'écran et n'a pas le style du site.
 * Elle est remplacée par un panneau OVANIE : il monte du bas de l'écran sur téléphone
 * et s'ouvre sous le champ sur ordinateur. Le <select> reste la source de la valeur,
 * donc rien ne change pour l'envoi des formulaires ni pour les scripts existants.
 *
 * S'applique aux listes placées dans un `.dl-select-wrap` ou marquées `data-vendor-select`.
 */
(function () {
    'use strict';

    var SELECTOR = '.dl-select-wrap select, select[data-vendor-select]';
    var current = null; // { select, overlay }

    function labelFor(select) {
        var label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
        var text = label ? label.textContent : (select.getAttribute('aria-label') || '');
        return text.replace(/\*/g, '').replace(/\s+/g, ' ').trim() || 'Choisir';
    }

    function close() {
        if (!current) return;
        var select = current.select;
        current.overlay.remove();
        document.removeEventListener('keydown', onKey, true);
        current = null;
        select.focus({ preventScroll: true });
    }

    function choose(select, value) {
        if (select.value !== value) {
            select.value = value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        close();
    }

    function onKey(event) {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); }
    }

    function open(select) {
        if (select.disabled) return;
        if (current) close();

        var phone = window.matchMedia('(max-width: 640px)').matches;
        var options = Array.prototype.filter.call(select.options, function (option) { return !option.disabled; });

        var overlay = document.createElement('div');
        overlay.className = 'vs-overlay' + (phone ? ' is-sheet' : ' is-popover');

        var panel = document.createElement('div');
        panel.className = 'vs-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-label', labelFor(select));

        var head = document.createElement('div');
        head.className = 'vs-head';
        var title = document.createElement('strong');
        title.textContent = labelFor(select);
        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'vs-close';
        closeButton.setAttribute('aria-label', 'Fermer');
        closeButton.textContent = '×';
        closeButton.addEventListener('click', close);
        head.append(title, closeButton);
        panel.appendChild(head);

        var list = document.createElement('div');
        list.className = 'vs-list';
        list.setAttribute('role', 'listbox');

        var search = null;
        if (options.length > 8) {
            search = document.createElement('input');
            search.type = 'search';
            search.className = 'vs-search';
            search.placeholder = 'Rechercher…';
            search.setAttribute('aria-label', 'Rechercher dans la liste');
            panel.appendChild(search);
        }

        var empty = document.createElement('p');
        empty.className = 'vs-empty';
        empty.textContent = 'Aucun résultat.';
        empty.hidden = true;

        options.forEach(function (option) {
            // L'entrée vide (« Sélectionner… ») n'est pas un choix : elle sert seulement d'invite dans le champ.
            if (option.value === '') return;
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'vs-option' + (option.selected ? ' is-selected' : '');
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            item.dataset.search = option.textContent.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            var text = document.createElement('span');
            text.textContent = option.textContent.trim();
            var mark = document.createElement('i');
            mark.className = 'vs-check';
            mark.setAttribute('aria-hidden', 'true');
            item.append(text, mark);
            item.addEventListener('click', function () { choose(select, option.value); });
            list.appendChild(item);
        });
        list.appendChild(empty);
        panel.appendChild(list);

        if (search) {
            search.addEventListener('input', function () {
                var needle = search.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                var shown = 0;
                list.querySelectorAll('.vs-option').forEach(function (item) {
                    var match = item.dataset.search.indexOf(needle) !== -1;
                    item.hidden = !match;
                    if (match) shown++;
                });
                empty.hidden = shown > 0;
            });
        }

        overlay.appendChild(panel);
        overlay.addEventListener('mousedown', function (event) { if (event.target === overlay) close(); });
        document.body.appendChild(overlay);

        if (!phone) {
            // Sur ordinateur, le panneau se place sous le champ et prend sa largeur.
            var rect = select.getBoundingClientRect();
            var below = window.innerHeight - rect.bottom - 12;
            var above = rect.top - 12;
            var height = Math.min(360, Math.max(below, above));
            panel.style.width = Math.max(rect.width, 220) + 'px';
            panel.style.left = Math.min(rect.left, window.innerWidth - Math.max(rect.width, 220) - 8) + 'px';
            panel.style.maxHeight = height + 'px';
            if (below >= Math.min(260, above) || below >= above) panel.style.top = (rect.bottom + 6) + 'px';
            else panel.style.bottom = (window.innerHeight - rect.top + 6) + 'px';
        }

        current = { select: select, overlay: overlay };
        document.addEventListener('keydown', onKey, true);
        var selected = list.querySelector('.vs-option.is-selected');
        if (selected) selected.scrollIntoView({ block: 'center' });
        // Sur téléphone, on ne met pas le curseur dans la recherche : le clavier masquerait la liste.
        (phone ? (selected || closeButton) : (search || selected || closeButton)).focus({ preventScroll: true });
    }

    // Le clic et le toucher n'ouvrent plus la liste native.
    ['mousedown', 'touchend'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var select = event.target && event.target.closest ? event.target.closest(SELECTOR) : null;
            if (!select || select.multiple) return;
            event.preventDefault();
            if (current && current.select === select) return;
            select.focus({ preventScroll: true });
            open(select);
        }, { passive: false });
    });

    // Au clavier : Entrée, Espace et les flèches ouvrent le panneau.
    document.addEventListener('keydown', function (event) {
        var select = event.target && event.target.matches && event.target.matches(SELECTOR) ? event.target : null;
        if (!select || select.multiple) return;
        if ([' ', 'Enter', 'ArrowDown', 'ArrowUp'].indexOf(event.key) !== -1) {
            event.preventDefault();
            open(select);
        }
    });

    window.addEventListener('resize', close);
})();
