(() => {
    'use strict';

    const app = document.querySelector('[data-grossiste-page]');
    if (!app) return;

    const form = app.querySelector('[data-grossiste-form]');
    const tabs = [...app.querySelectorAll('[data-tab-target]')];
    const panels = [...app.querySelectorAll('[data-tab-panel]')];
    const keys = tabs.map((tab) => tab.dataset.tabTarget);
    const progressItems = [...app.querySelectorAll('[data-progress-item]')];
    const miniBars = [...app.querySelectorAll('.pg-mini-bars i')];
    const nextButton = app.querySelector('[data-next-tab]');
    const previousButton = app.querySelector('[data-prev-tab]');
    const finalButton = app.querySelector('[data-final-submit]');
    const saveButton = app.querySelector('[data-save-draft]');
    const saveStatus = app.querySelector('[data-save-status]');
    const progressBar = app.querySelector('[data-progress-bar]');
    const progressCopy = app.querySelector('[data-progress-copy]');
    const completedCopy = app.querySelector('[data-completed-count]');
    const sideCopy = app.querySelector('[data-side-copy]');
    const tipCopy = app.querySelector('[data-tip-copy]');
    const sideImage = app.querySelector('[data-side-image]');
    const pageSubtitle = app.querySelector('[data-page-subtitle]');

    let active = app.dataset.firstTab || keys[0];
    let saveTimer = null;
    let saving = false;

    const contentBySection = {
        company: {
            hero: 'Accédez à un large réseau d’acheteurs professionnels sur OVANIE PRO.',
            tip: 'Des informations complètes et exactes améliorent votre visibilité auprès des acheteurs professionnels et renforcent la confiance dans votre entreprise.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Grossiste.',
            image: 'default',
        },
        activity: {
            hero: "Un approvisionnement fiable pour les professionnels du BTP en Afrique de l’Ouest.",
            tip: 'Des informations précises sur votre activité, vos produits et vos capacités commerciales améliorent votre visibilité auprès des acheteurs professionnels.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Grossiste.',
            image: 'default',
        },
        warehouse: {
            hero: 'Des solutions logistiques adaptées pour approvisionner les professionnels du BTP en Afrique de l’Ouest.',
            tip: 'Des informations précises sur vos entrepôts et vos délais d’expédition améliorent votre visibilité auprès des acheteurs professionnels et facilitent la gestion des commandes.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Grossiste.',
            image: 'default',
        },
        international: {
            hero: "Développez vos échanges commerciaux et votre réseau d’approvisionnement en Afrique de l’Ouest et à l’international.",
            tip: 'Des informations précises sur vos activités internationales renforcent la confiance des acheteurs professionnels et facilitent le développement de nouveaux partenariats commerciaux.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Grossiste.',
            image: 'commerce',
        },
        verification: {
            hero: 'Complétez votre dossier professionnel pour renforcer votre crédibilité sur OVANIE PRO.',
            tip: "L’ajout de documents ne signifie pas une validation automatique de votre profil. Nos équipes vérifient les informations fournies et peuvent vous demander des documents complémentaires si nécessaire.",
            subtitle: 'Téléchargez les justificatifs nécessaires à la création et à la vérification de votre profil Grossiste.',
            image: 'default',
        },
    };

    const sectionRequired = {
        company: ['legal_name', 'registration_country', 'city', 'address', 'legal_form', 'registration_number', 'tax_number', 'representative_name', 'responsible_position', 'professional_phone', 'professional_email'],
        activity: ['categories_sold', 'goods_origin', 'distribution_status', 'stock_capacity', 'stock_unit', 'sale_units', 'moq', 'moq_unit', 'sales_volume', 'sales_volume_unit', 'volume_periodicity', 'zones_served', 'countries_served'],
        warehouse: ['dispatch_country', 'dispatch_city', 'dispatch_address', 'has_warehouses', 'preparation_lead_time', 'preparation_lead_unit', 'preparation_workdays'],
        international: ['trade_flows'],
        verification: [],
    };

    const cityMap = {
        'Côte d’Ivoire': ['Abidjan', 'Yamoussoukro', 'Bouaké', 'San-Pédro', 'Korhogo', 'Daloa', 'Man', 'Gagnoa', 'Grand-Bassam', 'Abengourou', 'Bondoukou', 'Soubré', 'Divo', 'Anyama', 'Bingerville', 'Dabou'],
        'Sénégal': ['Dakar', 'Thiès', 'Saint-Louis', 'Kaolack', 'Ziguinchor', 'Touba', 'Mbour', 'Rufisque', 'Kolda', 'Tambacounda'],
        'Mali': ['Bamako', 'Sikasso', 'Ségou', 'Mopti', 'Kayes', 'Koutiala', 'Gao', 'Tombouctou'],
        'Burkina Faso': ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Banfora', 'Ouahigouya', 'Kaya', 'Fada N’Gourma'],
        'Ghana': ['Accra', 'Kumasi', 'Tema', 'Takoradi', 'Tamale', 'Cape Coast', 'Koforidua', 'Sunyani'],
        'Guinée': ['Conakry', 'Kankan', 'Kindia', 'Labé', 'Nzérékoré', 'Boké', 'Mamou'],
        'Togo': ['Lomé', 'Sokodé', 'Kara', 'Kpalimé', 'Atakpamé', 'Dapaong'],
        'Bénin': ['Cotonou', 'Porto-Novo', 'Abomey-Calavi', 'Parakou', 'Bohicon', 'Natitingou', 'Ouidah'],
        'Niger': ['Niamey', 'Zinder', 'Maradi', 'Agadez', 'Tahoua', 'Dosso'],
        'Cameroun': ['Douala', 'Yaoundé', 'Bafoussam', 'Garoua', 'Bamenda', 'Maroua', 'Kribi'],
        'Nigeria': ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt', 'Kaduna', 'Enugu', 'Benin City'],
        'Maroc': ['Casablanca', 'Rabat', 'Marrakech', 'Tanger', 'Fès', 'Agadir', 'Meknès'],
        'France': ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Bordeaux', 'Lille', 'Nantes'],
        'Chine': ['Guangzhou', 'Shanghai', 'Shenzhen', 'Beijing', 'Yiwu', 'Ningbo', 'Qingdao', 'Xiamen'],
        'Turquie': ['Istanbul', 'Ankara', 'Izmir', 'Bursa', 'Gaziantep', 'Mersin'],
        'Émirats arabes unis': ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Jebel Ali'],
        'Inde': ['Mumbai', 'Delhi', 'Ahmedabad', 'Chennai', 'Bengaluru', 'Hyderabad', 'Kolkata'],
    };

    const flagMap = {
        'Côte d’Ivoire': 'ci', Sénégal: 'sn', Mali: 'ml', 'Burkina Faso': 'bf', Ghana: 'gh', Guinée: 'gn',
        Togo: 'tg', Bénin: 'bj', Niger: 'ne', Cameroun: 'cm', Nigeria: 'ng', Maroc: 'ma', France: 'fr',
        Chine: 'cn', Turquie: 'tr', 'Émirats arabes unis': 'ae', Inde: 'in',
    };
    const dialCodes = ['+971', '+237', '+234', '+233', '+229', '+228', '+227', '+226', '+225', '+224', '+223', '+221', '+212', '+91', '+90', '+86', '+33'];
    const baseFlags = (() => {
        const img = app.querySelector('img[src*="/flags/"]');
        if (!img) return '/ovanie-pro/flags/';
        const src = img.getAttribute('src');
        return src.slice(0, src.lastIndexOf('/') + 1);
    })();

    function cssEscape(value) {
        return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/(["'\\[\]#.])/g, '\\$1');
    }

    function selectNodes(name) {
        return [...form.querySelectorAll(`[name="${cssEscape(name)}"], [name="${cssEscape(name)}[]"]`)];
    }

    function valueOf(name) {
        const nodes = selectNodes(name);
        if (!nodes.length) return '';
        const first = nodes[0];
        if (first.type === 'radio') {
            return nodes.find((node) => node.checked)?.value || '';
        }
        if (first.type === 'checkbox') {
            return nodes.filter((node) => node.checked).map((node) => node.value);
        }
        return first.value ?? '';
    }

    function filled(value) {
        if (Array.isArray(value)) return value.filter(Boolean).length > 0;
        return String(value ?? '').trim() !== '';
    }

    function fieldFilled(name) {
        return filled(valueOf(name));
    }

    function clearInvalid() {
        app.querySelectorAll('.is-invalid, .pg-invalid-group').forEach((node) => {
            node.classList.remove('is-invalid', 'pg-invalid-group');
        });
        app.querySelectorAll('[data-client-error]').forEach((node) => node.remove());
    }

    function invalidContainer(name) {
        const direct = form.querySelector(`[name="${cssEscape(name)}"]`) || form.querySelector(`[name="${cssEscape(name)}[]"]`);
        if (direct) return direct.closest('.pg-field, .pg-inline-question, .pg-docrow, .pg-card') || direct;
        const phone = form.querySelector(`[data-phone-field="${cssEscape(name)}"]`);
        if (phone) return phone.closest('.pg-field') || phone;
        if (name === 'warehouses') return app.querySelector('[data-warehouse-list]')?.closest('.pg-card') || app.querySelector('[data-warehouse-list]');
        return null;
    }

    function markInvalid(name, message) {
        const container = invalidContainer(name);
        if (!container) return;
        container.classList.add('pg-invalid-group');
        if (message && !container.querySelector('[data-client-error]')) {
            const error = document.createElement('small');
            error.className = 'pg-error pg-error--client';
            error.dataset.clientError = '1';
            error.textContent = message;
            container.appendChild(error);
        }
    }

    function documentReady(type) {
        const row = app.querySelector(`[data-doc-type="${cssEscape(type)}"]`);
        if (!row) return false;
        if (row.dataset.docProvided === '1') return true;
        const input = row.querySelector('input[type="file"]');
        return !!(input && input.files && input.files.length);
    }

    function validWarehouses() {
        if (valueOf('has_warehouses') === 'no') return true;
        if (valueOf('has_warehouses') !== 'yes') return false;
        if (!warehouses.length) return false;
        return warehouses.every((warehouse) => ['name', 'country', 'city', 'address', 'type'].every((key) => filled(warehouse[key])));
    }

    function sectionComplete(key) {
        syncPhones();
        syncWarehouses();
        if (key === 'verification') return documentReady('registry') && documentReady('tax_certificate');
        if (!sectionRequired[key].every(fieldFilled)) return false;
        if (key === 'warehouse') return validWarehouses();
        if (key === 'international') {
            const flow = valueOf('trade_flows');
            if (['import', 'both'].includes(flow) && !fieldFilled('supply_countries')) return false;
            if (['export', 'both'].includes(flow) && !fieldFilled('destination_countries')) return false;
            if (flow !== 'none' && !fieldFilled('transport_modes')) return false;
        }
        return true;
    }

    function validateSection(key) {
        clearInvalid();
        let ok = true;
        const missing = [];
        (sectionRequired[key] || []).forEach((name) => {
            if (!fieldFilled(name)) {
                ok = false;
                missing.push(name);
                markInvalid(name, 'Ce champ est nécessaire pour continuer.');
            }
        });

        if (key === 'company') {
            const email = form.querySelector('[name="professional_email"]');
            if (email && email.value && !email.checkValidity()) {
                ok = false;
                markInvalid('professional_email', 'Saisissez une adresse e-mail valide.');
            }
            const phone = valueOf('professional_phone').replace(/\D/g, '');
            if (phone && phone.length < 8) {
                ok = false;
                markInvalid('professional_phone', 'Saisissez un numéro de téléphone professionnel valide.');
            }
        }

        if (key === 'warehouse' && !validWarehouses()) {
            ok = false;
            markInvalid('warehouses', 'Complétez au moins un entrepôt ou choisissez « Non ».');
        }

        if (key === 'international') {
            const flow = valueOf('trade_flows');
            if (['import', 'both'].includes(flow) && !fieldFilled('supply_countries')) {
                ok = false;
                markInvalid('supply_countries', 'Sélectionnez au moins un pays d’approvisionnement.');
            }
            if (['export', 'both'].includes(flow) && !fieldFilled('destination_countries')) {
                ok = false;
                markInvalid('destination_countries', 'Sélectionnez au moins un pays de destination.');
            }
            if (flow && flow !== 'none' && !fieldFilled('transport_modes')) {
                ok = false;
                markInvalid('transport_modes', 'Sélectionnez au moins un mode de transport.');
            }
        }

        if (key === 'verification') {
            ['registry', 'tax_certificate'].forEach((type) => {
                if (!documentReady(type)) {
                    ok = false;
                    const row = app.querySelector(`[data-doc-type="${type}"]`);
                    if (row) {
                        row.classList.add('pg-invalid-group');
                        if (!row.querySelector('[data-client-error]')) {
                            const error = document.createElement('small');
                            error.dataset.clientError = '1';
                            error.className = 'pg-error pg-error--client';
                            error.textContent = 'Ce document est obligatoire avant la soumission.';
                            row.appendChild(error);
                        }
                    }
                }
            });
        }

        if (!ok) {
            const first = app.querySelector('.pg-invalid-group, .is-invalid');
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return ok;
    }

    function ensureDoneMarker(tab, done) {
        let marker = tab.querySelector('.pg-tab__visualdone');
        const serverMarker = tab.querySelector('.pg-tab__done');
        if (serverMarker) serverMarker.hidden = !done;
        if (done && !serverMarker && !marker) {
            marker = document.createElement('i');
            marker.className = 'pg-tab__visualdone';
            marker.textContent = '✓';
            tab.appendChild(marker);
        }
        if (!done && marker) marker.remove();
    }

    function updateProgress() {
        const states = Object.fromEntries(keys.map((key) => [key, sectionComplete(key)]));
        const completed = keys.filter((key) => states[key]).length;

        tabs.forEach((tab) => ensureDoneMarker(tab, states[tab.dataset.tabTarget]));
        miniBars.forEach((bar, index) => bar.classList.toggle('is-done', states[keys[index]]));
        if (completedCopy) completedCopy.textContent = completed;
        if (progressBar) progressBar.style.width = `${completed * 20}%`;
        if (progressCopy) progressCopy.textContent = `${completed} section${completed > 1 ? 's' : ''} complétée${completed > 1 ? 's' : ''} sur 5`;

        progressItems.forEach((item) => {
            const key = item.dataset.progressItem;
            const done = !!states[key];
            const current = key === active;
            item.classList.toggle('is-done', done);
            item.classList.toggle('is-active', current);
            item.classList.toggle('is-current', current && !done);
            const status = item.querySelector('em');
            if (status) status.textContent = done ? 'Complétée' : (current ? 'En cours' : 'À compléter');
        });

        updateNavigationAccess?.();
    }

    function setActive(key, scroll = true) {
        if (!keys.includes(key)) key = keys[0];
        active = key;
        const index = keys.indexOf(key);

        tabs.forEach((tab) => {
            const current = tab.dataset.tabTarget === key;
            tab.classList.toggle('is-active', current);
            tab.setAttribute('aria-selected', current ? 'true' : 'false');
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.tabPanel !== key; });
        if (nextButton) nextButton.hidden = index === keys.length - 1;
        if (finalButton) finalButton.hidden = index !== keys.length - 1;

        const content = contentBySection[key];
        if (content) {
            if (sideCopy) sideCopy.textContent = content.hero;
            if (tipCopy) tipCopy.textContent = content.tip;
            if (pageSubtitle) pageSubtitle.textContent = content.subtitle;
            if (sideImage) {
                const commerce = content.image === 'commerce';
                sideImage.src = commerce ? sideImage.dataset.commerceSrc : sideImage.dataset.defaultSrc;
                sideImage.closest('.pg-sidehero')?.classList.toggle('is-commerce', commerce);
            }
        }
        updateInternationalVisibility();
        updateProgress();
        if (scroll) window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function firstIncompleteBefore(targetKey) {
        const targetIndex = keys.indexOf(targetKey);
        if (targetIndex <= 0) return null;
        for (let index = 0; index < targetIndex; index += 1) {
            if (!sectionComplete(keys[index])) return keys[index];
        }
        return null;
    }

    function updateNavigationAccess() {
        const firstIncompleteIndex = keys.findIndex((key) => !sectionComplete(key));
        const maxReachableIndex = firstIncompleteIndex === -1 ? keys.length - 1 : firstIncompleteIndex;

        tabs.forEach((tab) => {
            const index = keys.indexOf(tab.dataset.tabTarget);
            const locked = index > maxReachableIndex;
            tab.classList.toggle('is-locked', locked);
            tab.setAttribute('aria-disabled', locked ? 'true' : 'false');
            tab.tabIndex = locked ? -1 : 0;
        });

        progressItems.forEach((item) => {
            const index = keys.indexOf(item.dataset.progressItem);
            const locked = index > maxReachableIndex;
            item.classList.toggle('is-locked', locked);
            item.setAttribute('aria-disabled', locked ? 'true' : 'false');
            item.tabIndex = locked ? -1 : 0;
        });
    }

    async function requestNavigation(targetKey) {
        if (!keys.includes(targetKey) || targetKey === active) return;

        const blocker = firstIncompleteBefore(targetKey);
        if (blocker) {
            setActive(blocker, false);
            validateSection(blocker);
            const blockerLabel = contentBySection[blocker] ? tabs.find((tab) => tab.dataset.tabTarget === blocker)?.textContent.trim() : blocker;
            const targetLabel = tabs.find((tab) => tab.dataset.tabTarget === targetKey)?.textContent.trim() || 'l’étape suivante';
            setSaveStatus(`Complétez l’étape ${blockerLabel || blocker} avant d’accéder à ${targetLabel}.`, 'error');
            updateNavigationAccess();
            return;
        }

        // Navigation vers l'avant : on valide et on sauvegarde l'étape courante
        // avant d'afficher l'écran suivant. La navigation arrière reste libre.
        const currentIndex = keys.indexOf(active);
        const targetIndex = keys.indexOf(targetKey);
        if (targetIndex > currentIndex) {
            if (!validateSection(active)) {
                setSaveStatus('Complétez les champs obligatoires avant de continuer.', 'error');
                updateNavigationAccess();
                return;
            }
            updateProgress();
            const saved = await saveDraft(false);
            if (!saved) return;
        }

        setActive(targetKey);
        updateNavigationAccess();
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => requestNavigation(tab.dataset.tabTarget)));
    progressItems.forEach((item) => {
        item.setAttribute('role', 'button');
        item.addEventListener('click', () => requestNavigation(item.dataset.progressItem));
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                requestNavigation(item.dataset.progressItem);
            }
        });
    });

    if (nextButton) {
        nextButton.addEventListener('click', async () => {
            if (!validateSection(active)) return;
            updateProgress();
            await saveDraft(false);
            const index = keys.indexOf(active);
            if (index < keys.length - 1) setActive(keys[index + 1]);
        });
    }

    if (previousButton) {
        previousButton.addEventListener('click', () => {
            const index = keys.indexOf(active);
            if (index > 0) setActive(keys[index - 1]);
            else history.back();
        });
    }

    function visualOption(select, option) {
        const flag = option?.dataset?.flag || '';
        let text = option ? option.textContent.trim() : 'Sélectionnez';
        if (select.dataset.smartMode === 'dial' && option) text = option.value;
        return `${flag ? `<img src="${flag}" alt="">` : ''}<span>${text}</span>`;
    }

    function makeSmartSelect(select) {
        if (select.dataset.enhanced) return;
        select.dataset.enhanced = '1';
        const wrap = document.createElement('div');
        wrap.className = 'pg-select';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pg-select__button';
        button.setAttribute('aria-expanded', 'false');
        const label = document.createElement('span');
        label.className = 'pg-select__label';
        button.appendChild(label);
        const menu = document.createElement('div');
        menu.className = 'pg-select__menu';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('pg-select__native');
        wrap.append(button, menu);

        const close = () => {
            wrap.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
        };
        const render = () => {
            const option = select.options[select.selectedIndex] || select.options[0];
            label.innerHTML = visualOption(select, option);
            [...menu.children].forEach((node, index) => node.classList.toggle('is-selected', !!select.options[index]?.selected));
        };
        const rebuild = () => {
            menu.innerHTML = '';
            [...select.options].forEach((option) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'pg-select__option';
                item.innerHTML = visualOption(select, option);
                item.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    close();
                    render();
                });
                menu.appendChild(item);
            });
            render();
        };
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            document.querySelectorAll('.pg-select.is-open').forEach((node) => { if (node !== wrap) node.classList.remove('is-open'); });
            wrap.classList.toggle('is-open');
            button.setAttribute('aria-expanded', wrap.classList.contains('is-open') ? 'true' : 'false');
        });
        select.addEventListener('change', render);
        select._pgRebuild = rebuild;
        rebuild();
    }

    app.querySelectorAll('select[data-smart-select]').forEach(makeSmartSelect);
    document.addEventListener('click', () => {
        document.querySelectorAll('.pg-select.is-open').forEach((node) => node.classList.remove('is-open'));
        document.querySelectorAll('.pg-tokenfield.is-open').forEach((node) => node.classList.remove('is-open'));
    });

    function syncCity(countrySelect) {
        const cityId = countrySelect.dataset.citySource;
        const citySelect = document.getElementById(cityId);
        if (!citySelect) return;
        const selected = citySelect.dataset.selected || citySelect.value || '';
        const list = cityMap[countrySelect.value] || [];
        citySelect.innerHTML = `<option value="">Sélectionnez une ville</option>${list.map((city) => `<option value="${city.replace(/"/g, '&quot;')}"${city === selected ? ' selected' : ''}>${city}</option>`).join('')}<option value="Autre"${selected === 'Autre' ? ' selected' : ''}>Autre ville / localité</option>`;
        citySelect.dataset.selected = '';
        citySelect._pgRebuild?.();
    }

    app.querySelectorAll('select[data-city-source]').forEach((select) => {
        syncCity(select);
        select.addEventListener('change', () => {
            syncCity(select);
            if (select.dataset.citySource === 'dispatch_city') {
                const city = document.getElementById('dispatch_city');
                const mapCity = app.querySelector('[data-map-city]');
                if (mapCity) mapCity.textContent = city?.value || select.value || 'Localisation';
            }
        });
    });
    app.querySelectorAll('select[data-city-select]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.id === 'dispatch_city') {
                const mapCity = app.querySelector('[data-map-city]');
                if (mapCity) mapCity.textContent = select.value || 'Localisation';
            }
        });
    });

    function setupPhoneField(wrapper) {
        const hidden = wrapper.querySelector('[data-phone-value]');
        const code = wrapper.querySelector('[data-phone-code]');
        const local = wrapper.querySelector('[data-phone-local]');
        if (!hidden || !code || !local) return;

        const raw = String(hidden.value || '').replace(/[\s().-]/g, '');
        const matched = dialCodes.find((dial) => raw.startsWith(dial));
        if (matched) {
            code.value = matched;
            local.value = raw.slice(matched.length);
        } else {
            code.value = '+225';
            local.value = raw.replace(/^\+/, '');
        }
        code.dispatchEvent(new Event('change', { bubbles: true }));

        const sync = () => {
            const digits = local.value.replace(/\D/g, '');
            hidden.value = digits ? `${code.value}${digits}` : '';
        };
        code.addEventListener('change', sync);
        local.addEventListener('input', sync);
        wrapper._syncPhone = sync;
        sync();
    }

    const phoneWrappers = [...app.querySelectorAll('[data-phone-field]')];
    phoneWrappers.forEach(setupPhoneField);
    function syncPhones() { phoneWrappers.forEach((wrapper) => wrapper._syncPhone?.()); }

    const copyPhone = app.querySelector('[data-copy-phone]');
    if (copyPhone) {
        copyPhone.addEventListener('change', () => {
            if (!copyPhone.checked) return;
            const phone = app.querySelector('[data-phone-field="professional_phone"]');
            const whatsapp = app.querySelector('[data-phone-field="professional_whatsapp"]');
            if (!phone || !whatsapp) return;
            whatsapp.querySelector('[data-phone-code]').value = phone.querySelector('[data-phone-code]').value;
            whatsapp.querySelector('[data-phone-local]').value = phone.querySelector('[data-phone-local]').value;
            whatsapp.querySelector('[data-phone-code]').dispatchEvent(new Event('change', { bubbles: true }));
            whatsapp._syncPhone?.();
            updateProgress();
        });
    }

    function makeTokenField(wrapper) {
        const hidden = wrapper.querySelector('input[type="hidden"]');
        const button = wrapper.querySelector('.pg-tokenfield__button');
        const menu = wrapper.querySelector('.pg-tokenfield__menu');
        const chips = wrapper.querySelector('.pg-chips');
        if (!hidden || !button || !menu || !chips) return;
        const options = (wrapper.dataset.options || '').split('|').map((x) => x.trim()).filter(Boolean);
        let selected = String(hidden.value || '').split(/[,;\n]+/).map((x) => x.trim()).filter(Boolean);

        const flagFor = (label) => flagMap[label] ? `${baseFlags}${flagMap[label]}.svg` : '';
        const sync = () => {
            hidden.value = selected.join(', ');
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            render();
        };
        const render = (query = '') => {
            chips.innerHTML = '';
            selected.forEach((value) => {
                const chip = document.createElement('span');
                chip.className = 'pg-chip';
                const flag = flagFor(value);
                chip.innerHTML = `${flag ? `<img src="${flag}" alt="">` : ''}<span>${value}</span><button type="button" aria-label="Retirer ${value}">×</button>`;
                chip.querySelector('button').addEventListener('click', (event) => {
                    event.stopPropagation();
                    selected = selected.filter((item) => item !== value);
                    sync();
                });
                chips.appendChild(chip);
            });

            const normalized = query.toLocaleLowerCase('fr');
            menu.querySelectorAll('.pg-tokenfield__option').forEach((node) => node.remove());
            options.filter((option) => option.toLocaleLowerCase('fr').includes(normalized)).forEach((option) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = `pg-tokenfield__option${selected.includes(option) ? ' is-selected' : ''}`;
                const flag = flagFor(option);
                item.innerHTML = `${flag ? `<img src="${flag}" alt="">` : ''}<span>${option}</span><b>${selected.includes(option) ? '✓' : ''}</b>`;
                item.addEventListener('click', (event) => {
                    event.stopPropagation();
                    selected = selected.includes(option) ? selected.filter((value) => value !== option) : [...selected, option];
                    sync();
                });
                menu.appendChild(item);
            });
        };

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'pg-tokenfield__search';
        search.placeholder = 'Rechercher…';
        search.autocomplete = 'off';
        search.addEventListener('input', () => render(search.value));
        search.addEventListener('click', (event) => event.stopPropagation());
        menu.prepend(search);
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            document.querySelectorAll('.pg-tokenfield.is-open').forEach((node) => { if (node !== wrapper) node.classList.remove('is-open'); });
            wrapper.classList.toggle('is-open');
            if (wrapper.classList.contains('is-open')) setTimeout(() => search.focus(), 0);
        });
        render();
    }
    app.querySelectorAll('[data-token-field]').forEach(makeTokenField);

    const warehouseList = app.querySelector('[data-warehouse-list]');
    const warehouseJson = app.querySelector('[data-warehouses-json]');
    let warehouses = [];
    try {
        const parsed = JSON.parse(warehouseJson?.value || '[]');
        warehouses = Array.isArray(parsed) ? parsed : [];
    } catch (_) {
        warehouses = [];
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    }
    function countryOptions(selected) {
        return Object.keys(cityMap).map((country) => `<option value="${escapeHtml(country)}" data-flag="${baseFlags}${flagMap[country] || ''}.svg"${country === selected ? ' selected' : ''}>${escapeHtml(country)}</option>`).join('');
    }
    function cityOptions(country, selected) {
        const list = cityMap[country] || [];
        return `<option value="">Sélectionnez une ville</option>${list.map((city) => `<option value="${escapeHtml(city)}"${city === selected ? ' selected' : ''}>${escapeHtml(city)}</option>`).join('')}<option value="Autre"${selected === 'Autre' ? ' selected' : ''}>Autre ville / localité</option>`;
    }
    function syncWarehouses() {
        if (warehouseJson) warehouseJson.value = JSON.stringify(warehouses);
    }
    function warehouseCard(data, index) {
        const card = document.createElement('article');
        card.className = 'pg-warehouse-card';
        const title = index === 0 ? 'Entrepôt principal' : `Entrepôt ${index + 1}`;
        card.innerHTML = `
            <div class="pg-warehouse-card__head">
                <svg class="pg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21V9l9-5 9 5v12M7 21v-7h10v7M7 10h10"/></svg>
                <strong>${title}</strong>
                <button type="button" class="pg-warehouse-remove" aria-label="Supprimer cet entrepôt">×</button>
            </div>
            <div class="pg-warehouse-grid">
                <div class="pg-field"><label>Nom / référence <b>*</b></label><input data-w="name" value="${escapeHtml(data.name || '')}" placeholder="Ex. Abidjan Principal"></div>
                <div class="pg-field"><label>Pays <b>*</b></label><select data-w="country" data-smart-select>${countryOptions(data.country || 'Côte d’Ivoire')}</select></div>
                <div class="pg-field"><label>Ville <b>*</b></label><select data-w="city" data-smart-select>${cityOptions(data.country || 'Côte d’Ivoire', data.city || '')}</select></div>
                <div class="pg-field pg-field--full"><label>Adresse <b>*</b></label><input data-w="address" value="${escapeHtml(data.address || '')}" placeholder="Zone industrielle, secteur, lot…"></div>
                <div class="pg-field"><label>Type d’entrepôt <b>*</b></label><select data-w="type" data-smart-select>
                    <option value="principal"${(data.type || 'principal') === 'principal' ? ' selected' : ''}>Entrepôt principal</option>
                    <option value="regional"${data.type === 'regional' ? ' selected' : ''}>Entrepôt régional</option>
                    <option value="transit"${data.type === 'transit' ? ' selected' : ''}>Entrepôt de transit</option>
                    <option value="autre"${data.type === 'autre' ? ' selected' : ''}>Autre</option>
                </select></div>
                <div class="pg-field"><label>Capacité indicative <span>(facultative)</span></label><div class="pg-warehouse-capacity"><input data-w="capacity" value="${escapeHtml(data.capacity || '')}" placeholder="5 000"><select data-w="capacity_unit"><option value="m2"${(data.capacity_unit || 'm2') === 'm2' ? ' selected' : ''}>m²</option><option value="m3"${data.capacity_unit === 'm3' ? ' selected' : ''}>m³</option><option value="tonnes"${data.capacity_unit === 'tonnes' ? ' selected' : ''}>Tonnes</option><option value="palettes"${data.capacity_unit === 'palettes' ? ' selected' : ''}>Palettes</option></select></div></div>
            </div>`;

        card.querySelector('.pg-warehouse-remove').addEventListener('click', () => {
            warehouses.splice(index, 1);
            renderWarehouses();
            updateProgress();
        });
        const country = card.querySelector('[data-w="country"]');
        const city = card.querySelector('[data-w="city"]');
        card.querySelectorAll('[data-w]').forEach((field) => {
            const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
            field.addEventListener(eventName, () => {
                const key = field.dataset.w;
                warehouses[index][key] = field.value;
                if (key === 'country') {
                    warehouses[index].city = '';
                    city.innerHTML = cityOptions(field.value, '');
                    city._pgRebuild?.();
                }
                syncWarehouses();
                updateProgress();
                setDirty();
            });
        });
        card.querySelectorAll('select[data-smart-select]').forEach(makeSmartSelect);
        return card;
    }
    function renderWarehouses() {
        if (!warehouseList) return;
        warehouseList.innerHTML = '';
        const has = valueOf('has_warehouses');
        warehouseList.hidden = has !== 'yes';
        if (has === 'yes') {
            warehouses.forEach((warehouse, index) => warehouseList.appendChild(warehouseCard(warehouse, index)));
        }
        syncWarehouses();
    }

    function ensureWarehouseForYes() {
        if (valueOf('has_warehouses') === 'yes' && !warehouses.length) {
            warehouses.push({ name: '', country: 'Côte d’Ivoire', city: '', address: '', type: 'principal', capacity: '', capacity_unit: 'm2' });
        }
        renderWarehouses();
    }

    app.querySelectorAll('input[name="has_warehouses"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (!radio.checked) return;
            if (radio.value === 'no') warehouses = [];
            ensureWarehouseForYes();
            updateProgress();
        });
    });
    const addWarehouse = app.querySelector('[data-add-warehouse]');
    addWarehouse?.addEventListener('click', () => {
        if (valueOf('has_warehouses') !== 'yes') {
            const yes = app.querySelector('input[name="has_warehouses"][value="yes"]');
            if (yes) yes.checked = true;
        }
        if (warehouses.length >= 5) return;
        warehouses.push({ name: '', country: 'Côte d’Ivoire', city: '', address: '', type: warehouses.length ? 'regional' : 'principal', capacity: '', capacity_unit: 'm2' });
        renderWarehouses();
        updateProgress();
    });
    ensureWarehouseForYes();

    const locateButton = app.querySelector('[data-locate]');
    locateButton?.addEventListener('click', () => {
        const position = app.querySelector('[data-position]');
        if (!navigator.geolocation) {
            if (saveStatus) saveStatus.textContent = 'Géolocalisation non disponible sur ce navigateur.';
            return;
        }
        const original = locateButton.innerHTML;
        locateButton.disabled = true;
        locateButton.textContent = 'Localisation…';
        navigator.geolocation.getCurrentPosition((result) => {
            const lat = result.coords.latitude.toFixed(6);
            const lng = result.coords.longitude.toFixed(6);
            if (position) position.value = `${lat},${lng}`;
            const mapCity = app.querySelector('[data-map-city]');
            if (mapCity) mapCity.textContent = `${lat}, ${lng}`;
            locateButton.innerHTML = original;
            locateButton.disabled = false;
            updateProgress();
            setDirty();
        }, () => {
            locateButton.innerHTML = original;
            locateButton.disabled = false;
            if (saveStatus) saveStatus.textContent = 'Autorisez la position ou renseignez l’adresse manuellement.';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });

    function updateInternationalVisibility() {
        const flow = valueOf('trade_flows');
        const supply = app.querySelector('[data-int-block="supply"]');
        const destination = app.querySelector('[data-int-block="destination"]');
        const transport = app.querySelector('[data-int-block="transport"]');
        if (!flow) {
            [supply, destination, transport].forEach((node) => { if (node) node.hidden = false; });
            return;
        }
        if (supply) supply.hidden = !['import', 'both'].includes(flow);
        if (destination) destination.hidden = !['export', 'both'].includes(flow);
        if (transport) transport.hidden = flow === 'none';
    }
    app.querySelectorAll('[data-trade-flow]').forEach((radio) => radio.addEventListener('change', () => {
        updateInternationalVisibility();
        updateProgress();
        setDirty();
    }));

    app.querySelectorAll('input[type="file"]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('[data-doc-row]');
            if (!row) return;
            const label = row.querySelector('[data-file-label]');
            if (label) label.textContent = input.files.length ? (input.files.length === 1 ? input.files[0].name : `${input.files.length} fichiers sélectionnés`) : 'Aucun fichier sélectionné';
            const status = row.querySelector('.pg-docstatus');
            if (status && input.files.length) {
                status.classList.add('is-ok');
                status.classList.remove('is-warn');
                status.textContent = '✓ Prêt à envoyer';
            }
            updateProgress();
            setDirty();
        });
    });

    const notes = app.querySelector('textarea[name="international_notes"]');
    const charCount = app.querySelector('[data-char-count]');
    const updateCharCount = () => { if (notes && charCount) charCount.textContent = notes.value.length; };
    notes?.addEventListener('input', updateCharCount);
    updateCharCount();

    function buildDraftData(includeFiles = false) {
        syncPhones();
        syncWarehouses();
        const data = new FormData(form);
        if (!includeFiles) {
            form.querySelectorAll('input[type="file"]').forEach((input) => data.delete(input.name));
        }
        data.set('action', 'draft');
        return data;
    }

    function setSaveStatus(message, state = '') {
        if (!saveStatus) return;
        saveStatus.textContent = message;
        saveStatus.dataset.state = state;
    }

    function setDirty() {
        if (!saving) setSaveStatus('Modifications non enregistrées', 'dirty');
    }

    async function saveDraft(showFeedback = true, includeFiles = false) {
        if (!form || saving) return true;
        saving = true;
        if (showFeedback) setSaveStatus('Enregistrement…', 'saving');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: buildDraftData(includeFiles),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            if (includeFiles) {
                form.querySelectorAll('input[type="file"]').forEach((input) => {
                    if (!input.files || !input.files.length) return;
                    const row = input.closest('[data-doc-row]');
                    if (row) {
                        row.dataset.docProvided = '1';
                        const status = row.querySelector('.pg-docstatus');
                        if (status) {
                            status.classList.add('is-ok');
                            status.classList.remove('is-warn');
                            status.textContent = '✓ Ajouté';
                        }
                        const label = row.querySelector('[data-file-label]');
                        if (label) label.textContent = 'Déjà fourni';
                    }
                    input.value = '';
                });
            }
            updateProgress();
            setSaveStatus('Brouillon enregistré', 'saved');
            return true;
        } catch (_) {
            setSaveStatus('Enregistrement impossible — utilisez le bouton à nouveau.', 'error');
            return false;
        } finally {
            saving = false;
        }
    }

    saveButton?.addEventListener('click', () => saveDraft(true, true));

    function scheduleDraftHint() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => setDirty(), 250);
    }
    form?.addEventListener('input', (event) => {
        if (event.target.matches('input[type="file"]')) return;
        updateProgress();
        scheduleDraftHint();
    });
    form?.addEventListener('change', (event) => {
        if (event.target.matches('input[type="file"]')) return;
        updateProgress();
        scheduleDraftHint();
    });

    form?.addEventListener('submit', (event) => {
        syncPhones();
        syncWarehouses();
        const submitter = event.submitter;
        if (submitter && submitter.value === 'submit') {
            const firstInvalid = keys.find((key) => !validateSection(key));
            if (firstInvalid) {
                event.preventDefault();
                setActive(firstInvalid, false);
                setTimeout(() => app.querySelector('.pg-invalid-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 0);
                setSaveStatus('Complétez les éléments obligatoires avant de continuer.', 'error');
                return;
            }
        }
    });

    updateInternationalVisibility();
    setActive(active, false);
    updateNavigationAccess();
    setSaveStatus('');
})();
