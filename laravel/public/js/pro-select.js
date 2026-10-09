/*
 * OVANIE PRO — menus déroulants des écrans d'accès.
 * Le menu natif du navigateur s'ouvre vers le haut (et décalé) quand la page est mise à l'échelle.
 * Sur ordinateur, on le remplace par une liste qui s'ouvre toujours sous le champ.
 * Le <select> reste la source de la valeur : rien ne change pour l'envoi du formulaire.
 */
(function () {
    'use strict';

    var open = null; // { select, list }

    function close() {
        if (!open) return;
        open.list.remove();
        open.select.removeAttribute('aria-expanded');
        open = null;
    }

    function choose(select, index) {
        if (select.selectedIndex !== index) {
            select.selectedIndex = index;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        close();
        select.focus();
    }

    function show(select) {
        close();
        var host = select.parentElement;
        if (getComputedStyle(host).position === 'static') host.style.position = 'relative';

        var list = document.createElement('ul');
        list.className = 'oa-menu';
        list.setAttribute('role', 'listbox');
        Array.prototype.forEach.call(select.options, function (option, index) {
            var item = document.createElement('li');
            item.setAttribute('role', 'option');
            item.setAttribute('data-index', index);
            if (option.disabled) item.setAttribute('aria-disabled', 'true');
            if (index === select.selectedIndex) item.className = 'is-selected';
            var flag = option.getAttribute('data-flag'), base = select.getAttribute('data-flag-base');
            if (flag && base) {
                var img = document.createElement('img');
                img.src = base + '/' + flag + '.svg';
                img.alt = '';
                item.appendChild(img);
            }
            item.appendChild(document.createTextNode(option.text));
            list.appendChild(item);
        });
        list.addEventListener('mousedown', function (e) { e.preventDefault(); });
        list.addEventListener('click', function (e) {
            var item = e.target.closest('li');
            if (item && !item.hasAttribute('aria-disabled')) choose(select, parseInt(item.getAttribute('data-index'), 10));
        });
        host.appendChild(list);
        select.setAttribute('aria-expanded', 'true');
        open = { select: select, list: list };

        // Toujours sous le champ ; la hauteur s'adapte à la place disponible.
        var zoom = parseFloat(document.documentElement.style.zoom) || 1;
        var room = (window.innerHeight - select.getBoundingClientRect().bottom) / zoom - 12;
        list.style.maxHeight = Math.max(140, Math.min(280, room)) + 'px';
        var current = list.querySelector('.is-selected');
        if (current) list.scrollTop = current.offsetTop - list.clientHeight / 2 + current.offsetHeight / 2;
        if (room < 140) list.scrollIntoView({ block: 'nearest' });
    }

    function move(step) {
        var items = Array.prototype.slice.call(open.list.children);
        var index = items.indexOf(open.list.querySelector('.is-active') || open.list.querySelector('.is-selected'));
        index = Math.max(0, Math.min(items.length - 1, index + step));
        items.forEach(function (item, i) { item.classList.toggle('is-active', i === index); });
        items[index].scrollIntoView({ block: 'nearest' });
    }

    function enabled(select) {
        return window.innerWidth >= 1000 && !select.multiple && !select.disabled && select.closest('.oa-body');
    }

    document.addEventListener('mousedown', function (e) {
        var select = e.target.closest ? e.target.closest('select') : null;
        if (select && enabled(select)) {
            e.preventDefault();
            select.focus();
            if (open && open.select === select) close(); else show(select);
            return;
        }
        if (open && !open.list.contains(e.target)) close();
    });

    document.addEventListener('keydown', function (e) {
        var select = e.target.tagName === 'SELECT' ? e.target : null;
        if (!select || !enabled(select)) return;
        if (!open) {
            if (e.key === ' ' || e.key === 'Enter' || e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); show(select); }
            return;
        }
        if (e.key === 'Escape' || e.key === 'Tab') { close(); return; }
        e.preventDefault();
        if (e.key === 'ArrowDown') move(1);
        else if (e.key === 'ArrowUp') move(-1);
        else if (e.key === 'Enter' || e.key === ' ') {
            var item = open.list.querySelector('.is-active') || open.list.querySelector('.is-selected');
            if (item) choose(select, parseInt(item.getAttribute('data-index'), 10));
        } else if (e.key.length === 1) {
            // Saisie d'une lettre : va au premier choix qui commence par elle.
            var letter = e.key.toLowerCase();
            var items = Array.prototype.slice.call(open.list.children);
            var match = items.filter(function (i) { return i.textContent.trim().toLowerCase().replace(/^\+/, '').indexOf(letter) === 0; })[0];
            if (match) { items.forEach(function (i) { i.classList.toggle('is-active', i === match); }); match.scrollIntoView({ block: 'nearest' }); }
        }
    });

    document.addEventListener('focusout', function (e) { if (open && e.target === open.select) setTimeout(function () { if (open && document.activeElement !== open.select) close(); }, 0); });
    window.addEventListener('resize', close);
})();
