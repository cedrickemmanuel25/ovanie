/* OVANIE PRO — parcours professionnel : choix du profil et formulaire « Informations métier » (aucune dépendance). */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    // Drapeau synchronisé avec le pays / l'indicatif choisi.
    $$('[data-pb-flag]').forEach(function (box) {
        var select = $('select', box), img = $('[data-flag-img]', box);
        if (!select || !img) return;
        var sync = function () {
            var option = select.options[select.selectedIndex];
            var flag = option ? option.getAttribute('data-flag') : '';
            img.hidden = !flag;
            if (flag) img.src = select.getAttribute('data-flag-base') + '/' + flag + '.svg';
        };
        select.addEventListener('change', sync);
        sync();
    });

    // ---------------------------------------------------------------
    // Écran « Parlez-nous de votre activité »
    // ---------------------------------------------------------------
    var qualification = $('[data-pb-qualification]');
    if (qualification) {
        var compatibility = JSON.parse(qualification.getAttribute('data-compatibility') || '{}');
        var entity = function () { var r = $('input[name="entity_type"]:checked', qualification); return r ? r.value : ''; };
        var syncProfiles = function () {
            var current = entity();
            $$('.pb-profile', qualification).forEach(function (card) {
                var allowed = !current || (compatibility[card.getAttribute('data-profile')] || []).indexOf(current) !== -1;
                var input = $('input', card);
                card.classList.toggle('is-locked', !allowed);
                input.disabled = !allowed;
                if (!allowed && input.checked) input.checked = false;
            });
            if (!$('input[name="professional_profile"]:checked', qualification)) {
                var first = $('.pb-profile:not(.is-locked) input', qualification);
                if (first) first.checked = true;
            }
            syncSide();
        };
        var syncSide = function () {
            var chosen = $('input[name="professional_profile"]:checked', qualification);
            $$('[data-presentation]', qualification).forEach(function (block) {
                block.hidden = !chosen || block.getAttribute('data-presentation') !== chosen.value;
            });
        };
        $$('input[name="entity_type"]', qualification).forEach(function (r) { r.addEventListener('change', syncProfiles); });
        $$('input[name="professional_profile"]', qualification).forEach(function (r) { r.addEventListener('change', syncSide); });
        syncProfiles();

        var quit = $('[data-quit]', qualification), quitInput = $('[data-quit-input]', qualification);
        if (quit && quitInput) quit.addEventListener('click', function () { quitInput.value = '1'; });
        return;
    }

    // ---------------------------------------------------------------
    // Formulaire « Informations métier »
    // ---------------------------------------------------------------
    // Étape 1 : le téléphone ne garde que les chiffres (l'indicatif est ajouté côté serveur).
    var register = $('[data-pb-register]');
    if (register) {
        var tel = $('#phone', register);
        tel.addEventListener('input', function () { tel.value = tel.value.replace(/[^0-9 ]/g, ''); });
        return;
    }

    var form = $('[data-pb-form]');
    if (!form) return;

    var order = $$('[data-pb-panel]', form).map(function (p) { return p.getAttribute('data-pb-panel'); });
    var panel = function (key) { return $('[data-pb-panel="' + key + '"]', form); };
    var active = order[0];
    var storeKey = 'pb-tab:' + location.pathname;

    // Téléphone : valeur complète (+225…) dans le champ caché.
    $$('[data-pb-phone]', form).forEach(function (box) {
        var hidden = $('[data-pb-phone-value]', box), code = $('[data-pb-phone-code]', box), number = $('[data-pb-phone-number]', box);
        var sync = function () {
            var digits = number.value.replace(/[^0-9]/g, '');
            hidden.value = digits ? code.value + digits : '';
        };
        box._sync = sync;
        code.addEventListener('change', sync);
        number.addEventListener('input', sync);
        if (number.value) sync();
    });

    // WhatsApp identique au téléphone professionnel.
    var same = $('[data-pb-whatsapp-same]', form);
    if (same) {
        var phoneBox = $('#professional_phone', form).closest('[data-pb-phone]');
        var waBox = $('#professional_whatsapp', form).closest('[data-pb-phone]');
        var syncWhatsapp = function () {
            var on = same.checked;
            waBox.classList.toggle('is-disabled', on);
            $('[data-pb-phone-number]', waBox).readOnly = on;
            $('[data-pb-phone-code]', waBox).disabled = on;
            if (on) {
                $('[data-pb-phone-code]', waBox).value = $('[data-pb-phone-code]', phoneBox).value;
                $('[data-pb-phone-code]', waBox).dispatchEvent(new Event('change'));
                $('[data-pb-phone-number]', waBox).value = $('[data-pb-phone-number]', phoneBox).value;
                $('[data-pb-phone-value]', waBox).value = $('[data-pb-phone-value]', phoneBox).value;
            }
        };
        same.addEventListener('change', syncWhatsapp);
        phoneBox.addEventListener('input', syncWhatsapp);
        phoneBox.addEventListener('change', syncWhatsapp);
        syncWhatsapp();
    }

    // Identité : déverrouille le nom et les prénoms.
    var identityEdit = $('[data-pb-identity-edit]', form);
    if (identityEdit) identityEdit.addEventListener('click', function () {
        $$('[data-pb-identity] .pb-input', form).forEach(function (i) { i.readOnly = false; });
        $('#identity_last_name', form).focus();
        identityEdit.hidden = true;
    });

    // Nombres entiers (superficie, lignes de production).
    $$('[data-pb-integer]', form).forEach(function (input) {
        input.addEventListener('input', function () { input.value = input.value.replace(/[^0-9]/g, ''); });
    });

    // Position GPS du site. Le bouton « Ma position » utilise le GPS du téléphone ou de l'ordinateur ;
    // si le navigateur le refuse (autorisation, connexion non sécurisée, délai dépassé), la position est
    // estimée à partir de l'adresse, de la ville et du pays saisis, pour ne jamais bloquer l'utilisateur.
    var locate = $('[data-pb-locate]', form);
    if (locate) {
        var countryCodes = {
            'Côte d’Ivoire': 'CI', 'Sénégal': 'SN', 'Mali': 'ML', 'Burkina Faso': 'BF', 'Ghana': 'GH', 'Guinée': 'GN', 'Togo': 'TG',
            'Bénin': 'BJ', 'Niger': 'NE', 'Cameroun': 'CM', 'Nigeria': 'NG', 'Maroc': 'MA', 'France': 'FR', 'Chine': 'CN',
            'Turquie': 'TR', 'Émirats arabes unis': 'AE'
        };
        var locateMsg = $('[data-pb-locate-msg]', form), locateTarget = $('[data-pb-position]', form);
        var locateScope = locate.closest('.fm-card') || form;
        var siteValue = function (suffix) {
            var field = $('[name$="' + suffix + '"]', locateScope);
            return field ? String(field.value || '').trim() : '';
        };
        var setPosition = function (latitude, longitude, message) {
            locateTarget.value = Number(latitude).toFixed(5) + ', ' + Number(longitude).toFixed(5);
            locateTarget.dispatchEvent(new Event('input', { bubbles: true }));
            locateMsg.textContent = message;
            refresh();
        };
        var locateFromAddress = function (reason) {
            var address = siteValue('_address'), city = siteValue('_city'), country = siteValue('_country');
            if (!address && !city) {
                locateMsg.textContent = reason + ' Renseignez l’adresse et la ville : la position sera calculée à partir de celles-ci.';
                return;
            }
            locateMsg.textContent = 'Recherche de la position à partir de l’adresse…';
            var query = [address, city, country].filter(Boolean).join(', ');
            var url = '/geo/search?q=' + encodeURIComponent(query) + (countryCodes[country] ? '&country_code=' + countryCodes[country] : '');
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (data) {
                    var found = data && data.results && data.results[0];
                    if (!found) return Promise.reject();
                    setPosition(found.latitude, found.longitude, 'Position estimée d’après l’adresse (' + (found.display_name || query) + '). Corrigez-la si nécessaire.');
                })
                .catch(function () {
                    locateMsg.textContent = reason + ' L’adresse saisie n’a pas été trouvée : saisissez les coordonnées manuellement.';
                });
        };
        locate.addEventListener('click', function () {
            if (!navigator.geolocation || window.isSecureContext === false) {
                locateFromAddress('La localisation automatique n’est pas disponible ici.');
                return;
            }
            locateMsg.textContent = 'Localisation en cours…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                setPosition(pos.coords.latitude, pos.coords.longitude, 'Position actuelle enregistrée. Corrigez-la si vous n’êtes pas sur le site.');
            }, function (error) {
                locateFromAddress(error && error.code === 1
                    ? 'Votre navigateur a refusé l’accès à votre position.'
                    : 'Votre position n’a pas pu être obtenue.');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
    }

    // Étiquettes (catégories, pays, incoterms, équipements).
    var addTag = function (box, text) {
        text = String(text || '').replace(/,/g, ' ').replace(/\s+/g, ' ').trim();
        if (!text) return;
        var hidden = $('[data-pb-tags-value]', box), list = $('[data-pb-tags-list]', box);
        var current = hidden.value ? hidden.value.split(/\s*,\s*/) : [];
        if (current.some(function (c) { return c.toLowerCase() === text.toLowerCase(); })) return;
        current.push(text);
        hidden.value = current.join(', ');
        var chip = document.createElement('span');
        chip.className = 'pb-chip';
        chip.appendChild(document.createTextNode(text));
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.setAttribute('aria-label', 'Retirer ' + text);
        remove.textContent = '×';
        chip.appendChild(remove);
        list.appendChild(chip);
        refresh();
    };
    $$('[data-pb-tags]', form).forEach(function (box) {
        var input = $('[data-pb-tags-input]', box), hidden = $('[data-pb-tags-value]', box), list = $('[data-pb-tags-list]', box);
        var commit = function () { if (input.value.trim()) { addTag(box, input.value); input.value = ''; } };
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); commit(); }
        });
        input.addEventListener('change', commit);
        input.addEventListener('blur', commit);
        list.addEventListener('click', function (e) {
            if (e.target.tagName !== 'BUTTON') return;
            var chip = e.target.closest('.pb-chip');
            var text = chip.firstChild.textContent;
            chip.remove();
            hidden.value = hidden.value.split(/\s*,\s*/).filter(function (c) { return c !== text; }).join(', ');
            refresh();
        });
    });

    // Suggestions : la liste native du navigateur (fond noir, ouverte vers le haut) est remplacée
    // par une liste claire affichée sous le champ.
    $$('input[list]', form).forEach(function (input) {
        var source = document.getElementById(input.getAttribute('list'));
        if (!source) return;
        var choices = Array.prototype.map.call(source.options, function (o) { return o.value; });
        input.removeAttribute('list');
        var host = input.parentElement, tags = input.closest('[data-pb-tags]'), menu = null;
        host.style.position = 'relative';

        var closeMenu = function () { if (menu) { menu.remove(); menu = null; } };
        var pick = function (text) {
            if (tags) { addTag(tags, text); input.value = ''; } else { input.value = text; refresh(); }
            closeMenu();
        };
        var openMenu = function () {
            closeMenu();
            var typed = input.value.trim().toLowerCase();
            var taken = tags ? $('[data-pb-tags-value]', tags).value.toLowerCase().split(/\s*,\s*/) : [];
            var list = choices.filter(function (c) {
                return taken.indexOf(c.toLowerCase()) === -1 && (!typed || c.toLowerCase().indexOf(typed) !== -1);
            });
            if (!list.length) return;
            menu = document.createElement('ul');
            menu.className = 'oa-menu oa-menu--wide';
            menu.setAttribute('role', 'listbox');
            list.forEach(function (text) {
                var item = document.createElement('li');
                item.setAttribute('role', 'option');
                item.textContent = text;
                menu.appendChild(item);
            });
            menu.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var item = e.target.closest('li');
                if (item) pick(item.textContent);
            });
            host.appendChild(menu);
        };
        var moveActive = function (step) {
            if (!menu) { openMenu(); return; }
            var items = Array.prototype.slice.call(menu.children);
            var index = items.indexOf($('.is-active', menu)) + step;
            index = Math.max(0, Math.min(items.length - 1, index));
            items.forEach(function (item, i) { item.classList.toggle('is-active', i === index); });
            items[index].scrollIntoView({ block: 'nearest' });
        };

        // Un clic sur le champ ouvre la liste, un second clic la referme.
        input.addEventListener('mousedown', function () { input._wasOpen = !!menu; });
        input.addEventListener('click', function () { if (input._wasOpen) closeMenu(); else openMenu(); });
        input.addEventListener('input', openMenu);
        input.addEventListener('blur', closeMenu);
        // Capture : passe avant la validation « Entrée » du champ à étiquettes.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
            else if (e.key === 'Escape') { closeMenu(); }
            else if (e.key === 'Enter' && menu && $('.is-active', menu)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                pick($('.is-active', menu).textContent);
            }
        }, true);
    });

    // Équipements : nom, marque, quantité et année réunis dans une étiquette.
    var equipment = $('[data-pb-equipment]', form);
    if (equipment) {
        var eq = function (key) { return $('[data-eq="' + key + '"]', equipment); };
        var addEquipment = function () {
            var name = eq('name').value.trim();
            if (!name) { eq('name').focus(); return; }
            var parts = [name];
            if (eq('brand').value.trim()) parts.push(eq('brand').value.trim());
            if (eq('qty').value.trim()) parts.push('×' + eq('qty').value.replace(/[^0-9]/g, ''));
            if (eq('year').value) parts.push(eq('year').value);
            addTag(equipment.nextElementSibling, parts.join(' · '));
            ['name', 'brand', 'qty', 'year'].forEach(function (k) { eq(k).value = ''; });
            eq('name').focus();
        };
        $('[data-eq-add]', equipment).addEventListener('click', addEquipment);
        $$('input', equipment).forEach(function (i) {
            i.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addEquipment(); } });
        });
    }

    // Ville : menu des villes du pays choisi, avec « Autre ville… » pour une saisie libre.
    var cityData = JSON.parse(form.getAttribute('data-cities') || '{}');
    var cityFields = {};
    $$('[data-pb-city]', form).forEach(function (box) {
        var hidden = $('[data-pb-city-value]', box), select = $('[data-pb-city-select]', box);
        var wrap = $('[data-pb-city-wrap]', box), other = $('[data-pb-city-other]', box);
        var country = form.elements[box.getAttribute('data-country-field')];
        var OTHER = '__other';
        var build = function () {
            var list = (country && cityData[country.value]) || [];
            var value = hidden.value;
            select.innerHTML = '';
            select.add(new Option('Sélectionnez une ville', ''));
            list.forEach(function (name) { select.add(new Option(name, name)); });
            select.add(new Option('Autre ville…', OTHER));
            // Pays sans liste (« Autre ») : saisie libre uniquement.
            wrap.hidden = !list.length;
            var known = list.indexOf(value) !== -1;
            select.value = known ? value : (value ? OTHER : '');
            other.hidden = list.length > 0 && select.value !== OTHER;
            other.value = known ? '' : value;
        };
        select.addEventListener('change', function () {
            other.hidden = select.value !== OTHER;
            hidden.value = select.value === OTHER ? other.value.trim() : select.value;
            if (select.value === OTHER) other.focus();
            refresh();
        });
        other.addEventListener('input', function () { hidden.value = other.value.trim(); });
        if (country) country.addEventListener('change', function () {
            // La ville ne peut pas rester celle d'un autre pays.
            hidden.value = '';
            build();
            refresh();
        });
        build();
        cityFields[hidden.name] = { set: function (value) { hidden.value = value; build(); } };
    });

    // « Même adresse que le siège » : reprend pays, ville et adresse de l'onglet Entreprise.
    var copyCompany = $('[data-pb-copy-company]', form);
    if (copyCompany) copyCompany.addEventListener('click', function () {
        var from = form.elements, prefix = copyCompany.getAttribute('data-prefix') || 'factory', country = from[prefix + '_country'];
        if (from.registration_country.value) {
            country.value = from.registration_country.value;
            country.dispatchEvent(new Event('change', { bubbles: true }));
        }
        cityFields[prefix + '_city'].set(from.city.value);
        from[prefix + '_address'].value = from.address.value;
        refresh();
    });

    // Blocs conditionnels : data-pb-show-if="champ=valeur1|valeur2" (affichés selon un choix radio).
    var showIfBlocks = $$('[data-pb-show-if]', form);
    var syncShowIf = function () {
        showIfBlocks.forEach(function (block) {
            var rule = block.getAttribute('data-pb-show-if').split('=');
            var checked = $('input[name="' + rule[0] + '"]:checked', form);
            block.hidden = !checked || rule[1].split('|').indexOf(checked.value) === -1;
        });
    };
    if (showIfBlocks.length) {
        form.addEventListener('change', function (e) { if (e.target.type === 'radio') { syncShowIf(); refresh(); } });
        syncShowIf();
    }

    // Entrepôts : liste enregistrée en JSON dans le champ « warehouses ».
    var warehouses = $('[data-pb-warehouses]', form);
    if (warehouses) {
        var whValue = $('[data-pb-warehouses-value]', warehouses), whList = $('[data-pb-warehouses-list]', warehouses);
        var whCountries = JSON.parse(warehouses.getAttribute('data-countries') || '[]');
        var whTypes = JSON.parse(warehouses.getAttribute('data-types') || '{}');
        var whUnits = JSON.parse(warehouses.getAttribute('data-units') || '{}');
        var whDefaultCountry = warehouses.getAttribute('data-default-country') || '';
        var saveWarehouses = function () {
            var rows = $$('.fm-wh', whList).map(function (row) {
                var data = {};
                $$('[data-wh]', row).forEach(function (el) { data[el.getAttribute('data-wh')] = el.value.trim(); });
                return data;
            });
            whValue.value = rows.length ? JSON.stringify(rows) : '';
            $$('.fm-wh__title', whList).forEach(function (title, i) { title.textContent = 'Entrepôt ' + (i + 1); });
        };
        var whField = function (label, control, required) {
            var field = document.createElement('div');
            field.className = 'pb-field';
            var l = document.createElement('label');
            l.textContent = label + (required ? ' *' : '');
            field.appendChild(l);
            field.appendChild(control);
            return field;
        };
        var whSelect = function (key, options, value, empty) {
            var wrap = document.createElement('div');
            wrap.className = 'pb-select';
            var select = document.createElement('select');
            select.setAttribute('data-wh', key);
            if (empty) select.add(new Option(empty, ''));
            (Array.isArray(options) ? options.map(function (o) { return [o, o]; }) : Object.keys(options).map(function (k) { return [k, options[k]]; }))
                .forEach(function (o) { select.add(new Option(o[1], o[0])); });
            if (value && !Array.prototype.some.call(select.options, function (o) { return o.value === value; })) select.add(new Option(value, value));
            select.value = value || '';
            wrap.appendChild(select);
            return wrap;
        };
        var whInput = function (key, value, placeholder) {
            var input = document.createElement('input');
            input.className = 'pb-input';
            input.setAttribute('data-wh', key);
            input.value = value || '';
            input.placeholder = placeholder || '';
            return input;
        };
        var addWarehouse = function (data) {
            data = data || { country: whDefaultCountry, capacity_unit: 'm2' };
            var row = document.createElement('div');
            row.className = 'fm-wh';
            var head = document.createElement('div');
            head.className = 'fm-wh__head';
            var title = document.createElement('b');
            title.className = 'fm-wh__title';
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'fm-wh__remove';
            remove.textContent = 'Retirer';
            remove.addEventListener('click', function () { row.remove(); saveWarehouses(); refresh(); });
            head.appendChild(title);
            head.appendChild(remove);
            row.appendChild(head);

            var grid = document.createElement('div');
            grid.className = 'pb-grid pb-grid--3';
            var countryWrap = whSelect('country', whCountries, data.country, 'Sélectionnez');
            var cityHolder = document.createElement('div');
            var buildCity = function (value) {
                var list = cityData[$('select', countryWrap).value] || [];
                cityHolder.innerHTML = '';
                cityHolder.appendChild(list.length ? whSelect('city', list, value, 'Sélectionnez une ville') : whInput('city', value, 'Ex. Abidjan'));
            };
            buildCity(data.city);
            $('select', countryWrap).addEventListener('change', function () { buildCity(''); saveWarehouses(); });
            grid.appendChild(whField('Nom / référence', whInput('name', data.name, 'Ex. Entrepôt principal'), true));
            grid.appendChild(whField('Pays', countryWrap, true));
            grid.appendChild(whField('Ville', cityHolder, true));
            row.appendChild(grid);

            var grid2 = document.createElement('div');
            grid2.className = 'pb-grid pb-grid--wh';
            grid2.appendChild(whField('Adresse', whInput('address', data.address, 'Ex. Zone industrielle, rue des Entrepôts'), true));
            grid2.appendChild(whField('Type', whSelect('type', whTypes, data.type, 'Sélectionnez'), true));
            var combo = document.createElement('div');
            combo.className = 'pb-combo';
            combo.appendChild(whInput('capacity', data.capacity, 'Ex. 5000'));
            combo.appendChild(whSelect('capacity_unit', whUnits, data.capacity_unit || 'm2'));
            grid2.appendChild(whField('Capacité (facultatif)', combo, false));
            row.appendChild(grid2);
            whList.appendChild(row);
        };
        try { (JSON.parse(whValue.value || '[]') || []).forEach(addWarehouse); } catch (e) { whValue.value = ''; }
        saveWarehouses();
        whList.addEventListener('input', saveWarehouses);
        whList.addEventListener('change', function () { saveWarehouses(); refresh(); });
        $('[data-pb-warehouse-add]', form).addEventListener('click', function () {
            addWarehouse(null);
            saveWarehouses();
            refresh();
            $('.fm-wh:last-child input', whList).focus();
        });
        // « Oui » sans entrepôt saisi : on en prépare un.
        form.addEventListener('change', function (e) {
            if (e.target.name === 'has_warehouses' && e.target.value === 'yes' && !$('.fm-wh', whList)) { addWarehouse(null); saveWarehouses(); refresh(); }
        });
        warehouses._missing = function () {
            var rows = $$('.fm-wh', whList), bad = [];
            if (!rows.length) return [warehouses];
            rows.forEach(function (row) {
                ['name', 'country', 'city', 'address', 'type'].forEach(function (key) {
                    var el = $('[data-wh="' + key + '"]', row);
                    if (!el || !el.value.trim()) bad.push(el ? el.closest('.pb-field') : row);
                });
            });
            return bad;
        };
    }

    // Paliers de quantité : liste enregistrée en JSON dans le champ « quantity_tiers ».
    var tiers = $('[data-pb-tiers]', form);
    if (tiers) {
        var tierValue = $('[data-pb-tiers-value]', tiers), tierList = $('[data-pb-tiers-list]', tiers);
        var tierUnits = JSON.parse(tiers.getAttribute('data-units') || '[]');
        var saveTiers = function () {
            var rows = $$('.fm-tier', tierList).map(function (row) {
                var data = {};
                $$('[data-tier]', row).forEach(function (el) { data[el.getAttribute('data-tier')] = el.value.trim(); });
                return data;
            });
            tierValue.value = rows.length ? JSON.stringify(rows) : '';
            $$('.fm-tier__name', tierList).forEach(function (name, i) { name.textContent = 'Palier ' + (i + 1); });
        };
        var tierInput = function (key, value, placeholder) {
            var input = document.createElement('input');
            input.className = 'pb-input';
            input.setAttribute('data-tier', key);
            input.setAttribute('inputmode', 'decimal');
            input.setAttribute('aria-label', key === 'from' ? 'À partir de' : 'Jusqu’à');
            input.placeholder = placeholder;
            input.value = value || '';
            return input;
        };
        var addTier = function (data) {
            data = data || {};
            var row = document.createElement('div');
            row.className = 'fm-tier';
            var name = document.createElement('b');
            name.className = 'fm-tier__name';
            row.appendChild(name);
            var from = document.createElement('span');
            from.textContent = 'À partir de';
            row.appendChild(from);
            row.appendChild(tierInput('from', data.from, 'Ex. 10'));
            var to = document.createElement('span');
            to.textContent = 'jusqu’à';
            row.appendChild(to);
            row.appendChild(tierInput('to', data.to, 'Sans limite'));
            var wrap = document.createElement('div');
            wrap.className = 'pb-select';
            var select = document.createElement('select');
            select.setAttribute('data-tier', 'unit');
            select.setAttribute('aria-label', 'Unité');
            tierUnits.forEach(function (u) { select.add(new Option(u, u)); });
            if (data.unit && tierUnits.indexOf(data.unit) === -1) select.add(new Option(data.unit, data.unit));
            select.value = data.unit || (form.elements.minimum_quantity_unit ? form.elements.minimum_quantity_unit.value : '') || tierUnits[0];
            wrap.appendChild(select);
            row.appendChild(wrap);
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'pb-cert-remove';
            remove.setAttribute('aria-label', 'Retirer ce palier');
            remove.textContent = '×';
            remove.addEventListener('click', function () { row.remove(); saveTiers(); refresh(); });
            row.appendChild(remove);
            tierList.appendChild(row);
        };
        var storedTiers = [];
        try { storedTiers = JSON.parse(tierValue.value || '[]') || []; } catch (e) {}
        if (!storedTiers.length) storedTiers = [{}];
        storedTiers.forEach(addTier);
        saveTiers();
        tierList.addEventListener('input', saveTiers);
        tierList.addEventListener('change', function () { saveTiers(); refresh(); });
        $('[data-pb-tier-add]', form).addEventListener('click', function () {
            addTier(null);
            saveTiers();
            refresh();
            $('.fm-tier:last-child input', tierList).focus();
        });
        // Chaque palier doit avoir une quantité de départ ; il en faut au moins un.
        tiers._missing = function () {
            var rows = $$('.fm-tier', tierList);
            if (!rows.length) return [tiers];
            return rows.filter(function (row) { return !$('[data-tier="from"]', row).value.trim(); });
        };
    }

    // Liste répétable générique (réalisations, références…) enregistrée en JSON.
    // data-fields : [{key, label, type: text|select|year|textarea, options, placeholder, required, wide}]
    $$('[data-pb-repeater]', form).forEach(function (box) {
        var value = $('[data-pb-repeater-value]', box), list = $('[data-pb-repeater-list]', box);
        var defs = JSON.parse(box.getAttribute('data-fields') || '[]');
        var noun = box.getAttribute('data-noun') || 'Élément';
        var save = function () {
            var rows = $$('.fm-wh', list).map(function (row) {
                var data = {};
                $$('[data-rep]', row).forEach(function (el) { data[el.getAttribute('data-rep')] = el.value.trim(); });
                return data;
            });
            value.value = rows.length ? JSON.stringify(rows) : '[]';
            $$('.fm-wh__title', list).forEach(function (title, i) { title.textContent = noun + ' ' + (i + 1); });
        };
        var add = function (data) {
            data = data || {};
            var row = document.createElement('div');
            row.className = 'fm-wh';
            var head = document.createElement('div');
            head.className = 'fm-wh__head';
            var title = document.createElement('b');
            title.className = 'fm-wh__title';
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'fm-wh__remove';
            remove.textContent = 'Retirer';
            remove.addEventListener('click', function () { row.remove(); save(); refresh(); });
            head.appendChild(title);
            head.appendChild(remove);
            row.appendChild(head);
            var grid = document.createElement('div');
            grid.className = 'pb-grid pb-grid--2';
            defs.forEach(function (def) {
                var field = document.createElement('div');
                field.className = 'pb-field' + (def.wide ? ' pb-span-2' : '');
                var label = document.createElement('label');
                label.textContent = def.label + (def.required ? ' *' : '');
                field.appendChild(label);
                var control;
                if (def.type === 'select' || def.type === 'year') {
                    var wrap = document.createElement('div');
                    wrap.className = 'pb-select';
                    control = document.createElement('select');
                    control.add(new Option('Sélectionnez', ''));
                    var options = def.options || [];
                    if (def.type === 'year') { options = []; for (var y = new Date().getFullYear(); y >= 1990; y--) options.push(String(y)); }
                    options.forEach(function (o) { control.add(new Option(o, o)); });
                    if (data[def.key] && options.indexOf(data[def.key]) === -1) control.add(new Option(data[def.key], data[def.key]));
                    wrap.appendChild(control);
                    field.appendChild(wrap);
                } else if (def.type === 'textarea') {
                    control = document.createElement('textarea');
                    control.className = 'pb-input pb-textarea';
                    control.rows = 2;
                    control.maxLength = 500;
                    field.appendChild(control);
                } else {
                    control = document.createElement('input');
                    control.className = 'pb-input';
                    control.maxLength = 160;
                    if (def.type === 'date') control.type = 'date';
                    field.appendChild(control);
                }
                control.setAttribute('data-rep', def.key);
                control.setAttribute('aria-label', def.label);
                if (def.required) control.setAttribute('data-rep-required', '1');
                if (def.placeholder) control.placeholder = def.placeholder;
                control.value = data[def.key] || '';
                grid.appendChild(field);
            });
            row.appendChild(grid);
            list.appendChild(row);
        };
        var stored = [];
        try { stored = JSON.parse(value.value || '[]') || []; } catch (e) {}
        stored.forEach(add);
        save();
        list.addEventListener('input', save);
        list.addEventListener('change', function () { save(); refresh(); });
        $('[data-pb-repeater-add]', box).addEventListener('click', function () {
            add(null);
            save();
            refresh();
            $('.fm-wh:last-child input', list).focus();
        });
        // La liste est facultative, mais une ligne commencée doit être complète.
        box._missing = function () {
            if ($$('.fm-wh', list).length < parseInt(box.getAttribute('data-min') || '0', 10)) return [box];
            return $$('[data-rep-required]', list).filter(function (el) { return !el.value.trim(); }).map(function (el) { return el.closest('.pb-field'); });
        };
    });

    // Certifications supplémentaires : enregistrées dans le champ « certifications_json ».
    var certs = $('[data-pb-certs]', form);
    if (certs) {
        var certValue = $('[data-pb-certs-value]', certs), certRows = $('[data-pb-certs-rows]', certs);
        var certTypes = JSON.parse(certs.getAttribute('data-types') || '[]');
        var certFields = [['type', 'Type'], ['body', 'Organisme'], ['number', 'Numéro'], ['issue_date', 'Émise le'], ['expiry_date', 'Expire le']];
        var saveCerts = function () {
            var rows = $$('.pb-cert-row', certRows).map(function (row) {
                var data = {};
                certFields.forEach(function (f) { data[f[0]] = $('[data-cert="' + f[0] + '"]', row).value; });
                return data;
            });
            certValue.value = rows.length ? JSON.stringify(rows) : '';
        };
        var addCert = function (data) {
            var row = document.createElement('div');
            row.className = 'pb-grid pb-grid--cert pb-cert-row';
            certFields.forEach(function (f) {
                var field = document.createElement('div');
                field.className = 'pb-field';
                var label = document.createElement('label');
                label.textContent = f[1];
                field.appendChild(label);
                var control;
                if (f[0] === 'type') {
                    var wrap = document.createElement('div');
                    wrap.className = 'pb-select';
                    control = document.createElement('select');
                    control.add(new Option('Sélectionnez', ''));
                    certTypes.forEach(function (t) { control.add(new Option(t, t)); });
                    wrap.appendChild(control);
                    field.appendChild(wrap);
                } else {
                    control = document.createElement('input');
                    control.className = 'pb-input';
                    control.type = f[0].indexOf('date') !== -1 ? 'date' : 'text';
                    field.appendChild(control);
                }
                control.setAttribute('data-cert', f[0]);
                control.setAttribute('aria-label', f[1]);
                control.value = (data && data[f[0]]) || '';
                row.appendChild(field);
            });
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'pb-cert-remove';
            remove.setAttribute('aria-label', 'Retirer cette certification');
            remove.textContent = '×';
            remove.addEventListener('click', function () { row.remove(); saveCerts(); });
            row.appendChild(remove);
            certRows.appendChild(row);
        };
        try { (JSON.parse(certValue.value || '[]') || []).forEach(addCert); } catch (e) { certValue.value = ''; }
        certRows.addEventListener('input', saveCerts);
        certRows.addEventListener('change', saveCerts);
        $('[data-pb-cert-add]', form).addEventListener('click', function () {
            addCert(null);
            saveCerts();
            $('.pb-cert-row:last-child select', certRows).focus();
        });
    }

    // MOQ / délai « Sur demande » : la valeur chiffrée n'est plus demandée.
    $$('[data-pb-mode-field]', form).forEach(function (field) {
        var input = $('[data-pb-mode-input]', field), select = $('select', field);
        var sync = function () {
            var mode = $('input[type=radio]:checked', field);
            var onRequest = mode && mode.value === 'on_request';
            input.disabled = onRequest;
            select.disabled = onRequest;
            if (onRequest) input.value = '';
            input.placeholder = onRequest ? 'Sur demande' : input.getAttribute('data-placeholder');
        };
        input.setAttribute('data-placeholder', input.placeholder);
        $$('input[type=radio]', field).forEach(function (r) { r.addEventListener('change', function () { sync(); refresh(); }); });
        sync();
    });

    // Export : les champs logistiques n'apparaissent que si « Oui ».
    var exportValue = function () { var r = $('input[name="export_enabled"]:checked', form); return r ? r.value : ''; };
    var syncExport = function () {
        var on = exportValue() === 'yes';
        $$('[data-pb-export-only]', form).forEach(function (el) { el.hidden = !on; });
    };
    $$('[data-pb-export-radio]', form).forEach(function (r) { r.addEventListener('change', function () { syncExport(); refresh(); }); });
    syncExport();

    // Dépôt de fichiers.
    $$('[data-pb-drop]', form).forEach(function (drop) {
        var input = $('[data-pb-file]', drop), label = $('[data-pb-file-label]', drop), zone = $('.pb-drop__zone', drop);
        input.addEventListener('change', function () {
            var names = Array.prototype.map.call(input.files, function (f) { return f.name; });
            if (names.length) {
                label.textContent = names.length > 2 ? names.slice(0, 2).join(', ') + ' +' + (names.length - 2) : names.join(', ');
                drop.classList.add('has-files');
                drop.classList.remove('is-missing');
            }
            refresh();
        });
        ['dragenter', 'dragover'].forEach(function (n) { zone.addEventListener(n, function () { drop.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (n) { zone.addEventListener(n, function () { drop.classList.remove('is-over'); }); });
    });
    var dropFilled = function (drop) { return drop.classList.contains('is-provided') || $('[data-pb-file]', drop).files.length > 0; };

    // ---------- État des sections ----------
    var missingIn = function (key) {
        var root = panel(key), missing = [];
        var skipped = function (el) { return !!el.closest('[data-pb-show-if][hidden]'); };
        $$('[data-required="1"]', root).forEach(function (el) {
            if (skipped(el)) return;
            if (el.hasAttribute('data-pb-tags')) {
                if (!$('[data-pb-tags-value]', el).value) missing.push(el.closest('.pb-field') || el);
            } else if (el.hasAttribute('data-pb-drop')) {
                if (!dropFilled(el)) missing.push(el);
            } else if (!el.disabled && !String(el.value).trim()) {
                missing.push(el.closest('.pb-field') || el);
            }
        });
        $$('[data-required-group]', root).forEach(function (group) {
            if (!skipped(group) && !$('input:checked', group)) missing.push(group.closest('.pb-field'));
        });
        $$('[data-pb-tiers]', root).forEach(function (box) { missing = missing.concat(box._missing()); });
        $$('[data-pb-repeater]', root).forEach(function (box) { missing = missing.concat(box._missing()); });
        $$('[data-pb-warehouses]', root).forEach(function (box) {
            if (!skipped(box)) missing = missing.concat(box._missing());
        });
        if (key === 'commerce' && exportValue() === 'yes') {
            if (!$('input[name="shipping_modes[]"]:checked', root)) missing.push($('.pb-ship', root).closest('.pb-field'));
            if (!$('input[name="incoterms"]', root).value) missing.push($('input[name="incoterms"]', root).closest('.pb-field'));
        }
        return missing;
    };

    var docStates = {
        'company-docs': function (card) { return $$('[data-pb-drop][data-required="1"]', card).every(dropFilled) ? 'Fourni' : 'À fournir'; },
        'representative': function (card) { return $$('[data-pb-drop]', card).every(dropFilled) ? 'Fourni' : 'À fournir'; },
        'certifications': function (card) { return $$('[data-pb-drop]', card).some(dropFilled) ? 'Fourni' : 'Non renseigné'; },
        'proofs': function (card) { return $$('[data-pb-drop][data-required="1"]', card).every(dropFilled) ? 'Fourni' : 'À fournir'; }
    };

    // Rang de la première section incomplète (ou de la dernière section si tout est complet).
    function firstIncomplete() {
        for (var i = 0; i < order.length; i++) { if (missingIn(order[i]).length) return i; }
        return order.length - 1;
    }

    function refresh() {
        var done = 0, limit = firstIncomplete();
        order.forEach(function (key, index) {
            var complete = missingIn(key).length === 0;
            if (complete) done++;
            var tab = $('[data-pb-tab="' + key + '"]', form);
            $('.pb-tab__ok', tab).hidden = !complete || key === active;
            // Sections pas encore accessibles : grisées dans les onglets et dans la progression.
            tab.classList.toggle('is-locked', index > limit);
            tab.setAttribute('aria-disabled', index > limit ? 'true' : 'false');
            var gotoBtn = $('[data-pb-goto="' + key + '"]', form);
            if (gotoBtn) gotoBtn.closest('li').classList.toggle('is-locked', index > limit);
            var state = $('[data-pb-state="' + key + '"]', form);
            if (state) {
                var li = state.closest('li');
                li.classList.toggle('is-done', complete);
                li.classList.toggle('is-current', key === active);
                li.classList.toggle('is-todo', !complete && key !== active);
                state.textContent = complete ? 'Complété' : (key === active ? 'En cours' : 'À compléter');
            }
            var segment = $$('[data-pb-segments] i', form)[index];
            if (segment) segment.classList.toggle('is-on', complete);
        });
        $$('[data-pb-done]', form).forEach(function (el) { el.textContent = done; });
        var bar = $('[data-pb-bar]', form);
        if (bar) bar.style.width = (done / order.length * 100) + '%';
        $$('[data-pb-status]', form).forEach(function (card) {
            var key = card.getAttribute('data-pb-status'), out = $('[data-pb-doc-state="' + key + '"]', form);
            if (!out) return;
            var required = $$('[data-pb-drop][data-required="1"]', card), any = $$('[data-pb-drop]', card).some(dropFilled);
            var text = docStates[key] ? docStates[key](card) : (required.length ? (required.every(dropFilled) ? 'Fourni' : 'À fournir') : (any ? 'Fourni' : 'Facultatif'));
            out.textContent = text;
            out.closest('li').classList.toggle('is-done', text === 'Fourni');
            out.closest('li').classList.toggle('is-todo', text === 'À fournir');
        });
    }

    // ---------- Navigation entre onglets ----------
    var prev = $('[data-pb-prev]', form), next = $('[data-pb-next]', form), submit = $('[data-pb-submit]', form), lead = $('[data-pb-lead]', form);

    function show(key, scroll) {
        if (order.indexOf(key) === -1) key = order[0];
        active = key;
        order.forEach(function (k) {
            panel(k).hidden = k !== key;
            var tab = $('[data-pb-tab="' + k + '"]', form);
            tab.classList.toggle('is-active', k === key);
            tab.setAttribute('aria-selected', k === key ? 'true' : 'false');
        });
        $$('[data-pb-side]', form).forEach(function (el) { el.hidden = el.getAttribute('data-pb-side') !== key; });
        $$('[data-pb-side-not]', form).forEach(function (el) { el.hidden = el.getAttribute('data-pb-side-not') === key; });
        var last = key === order[order.length - 1];
        next.hidden = last;
        submit.hidden = !last;
        if (lead) lead.textContent = lead.getAttribute(last ? 'data-verification' : 'data-default');
        try { sessionStorage.setItem(storeKey, key); } catch (e) {}
        refresh();
        if (scroll) window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearMissing() {
        $$('.is-missing', form).forEach(function (el) { el.classList.remove('is-missing'); });
        $$('.pb-missing-msg', form).forEach(function (el) { el.remove(); });
    }

    function flagMissing(key) {
        clearMissing();
        var missing = missingIn(key).filter(Boolean);
        if (!missing.length) return true;
        missing.forEach(function (el) { el.classList.add('is-missing'); });
        var msg = document.createElement('p');
        msg.className = 'pb-missing-msg';
        msg.setAttribute('role', 'alert');
        msg.textContent = missing.length === 1
            ? 'Il reste 1 information obligatoire à renseigner dans cette section (signalée en rouge).'
            : 'Il reste ' + missing.length + ' informations obligatoires à renseigner dans cette section (signalées en rouge).';
        panel(key).insertBefore(msg, panel(key).firstChild);
        missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // On peut toujours revenir en arrière, mais pas sauter une section incomplète :
    // la première section à compléter s'ouvre et signale ce qui manque.
    function goTo(key, scroll) {
        var limit = firstIncomplete();
        if (order.indexOf(key) > limit) {
            show(order[limit], scroll);
            flagMissing(order[limit]);
            return;
        }
        clearMissing();
        show(key, scroll);
    }
    $$('[data-pb-tab]', form).forEach(function (tab) {
        tab.addEventListener('click', function () { goTo(tab.getAttribute('data-pb-tab'), false); });
    });
    $$('[data-pb-goto]', form).forEach(function (btn) {
        btn.addEventListener('click', function () { goTo(btn.getAttribute('data-pb-goto'), true); });
    });
    next.addEventListener('click', function () {
        if (!flagMissing(active)) return;
        show(order[order.indexOf(active) + 1], true);
    });
    prev.addEventListener('click', function () {
        var index = order.indexOf(active);
        if (index === 0) { location.href = prev.getAttribute('data-back'); return; }
        clearMissing();
        show(order[index - 1], true);
    });

    // Soumission finale : ouvre la première section incomplète au lieu d'envoyer un dossier partiel.
    form.addEventListener('submit', function (e) {
        var button = e.submitter;
        if (!button || button.value !== 'submit') return;
        for (var i = 0; i < order.length; i++) {
            if (missingIn(order[i]).length) {
                e.preventDefault();
                show(order[i], false);
                flagMissing(order[i]);
                return;
            }
        }
    });

    form.addEventListener('input', function (e) {
        var field = e.target.closest('.is-missing');
        if (field) field.classList.remove('is-missing');
        refresh();
    });
    form.addEventListener('change', refresh);

    var stored = null;
    try { stored = sessionStorage.getItem(storeKey); } catch (e) {}
    var start = form.getAttribute('data-error-tab') || stored || order[0];
    // Au chargement non plus, on n'ouvre pas une section située après une section incomplète.
    if (order.indexOf(start) > firstIncomplete()) start = order[firstIncomplete()];
    show(start, false);
})();
