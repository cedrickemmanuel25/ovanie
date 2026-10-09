(() => {
    'use strict';

    const app = document.querySelector('[data-revendeur-page]');
    if (!app) return;
    const form = app.querySelector('[data-revendeur-form]');
    if (!form) return;

    const tabs = [...app.querySelectorAll('[data-tab-target]')];
    const panels = [...app.querySelectorAll('[data-tab-panel]')];
    const keys = tabs.map((tab) => tab.dataset.tabTarget);
    const progressItems = [...app.querySelectorAll('[data-progress-item]')];
    const miniBars = [...app.querySelectorAll('.pg-mini-bars i')];
    const nextButton = app.querySelector('[data-next-tab]');
    const finalButton = app.querySelector('[data-final-submit]');
    const saveButton = app.querySelector('[data-save-draft]');
    const actionInput = app.querySelector('[data-action-input]');
    const saveStatus = app.querySelector('[data-save-status]');
    const progressBar = app.querySelector('[data-progress-bar]');
    const progressCopy = app.querySelector('[data-progress-copy]');
    const completedCopy = app.querySelector('[data-completed-count]');
    const sideCopy = app.querySelector('[data-side-copy]');
    const tipCopy = app.querySelector('[data-tip-copy]');
    const pageSubtitle = app.querySelector('[data-page-subtitle]');

    let active = app.dataset.firstTab || 'company';

    const required = {
        company: ['legal_name', 'registration_country', 'city', 'address', 'representative_name', 'responsible_position', 'professional_phone', 'professional_email'],
        sourcing: ['purchase_categories', 'resale_categories', 'sourcing_countries'],
        commercial: ['sales_zones', 'purchase_frequency', 'importer', 'commercial_supply_countries'],
        needs: ['needs'],
        verification: [],
    };

    const copyBySection = {
        company: {
            hero: 'Développez votre activité de revente auprès des professionnels du BTP.',
            tip: 'Des informations complètes et à jour renforcent votre crédibilité auprès des fournisseurs et des acheteurs, et améliorent votre visibilité sur OVANIE PRO.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Revendeur professionnel.',
        },
        sourcing: {
            hero: 'Accédez à de nouvelles opportunités d’approvisionnement auprès de fournisseurs du BTP.',
            tip: 'Des informations complètes sur vos produits et fournisseurs permettent de proposer des opportunités d’approvisionnement adaptées.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Revendeur professionnel.',
        },
        commercial: {
            hero: 'Développez votre activité de revente auprès des fournisseurs du BTP.',
            tip: 'Des informations complètes sur votre activité commerciale nous permettent de vous mettre en relation avec des fournisseurs pertinents.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Revendeur professionnel.',
        },
        needs: {
            hero: 'Choisissez les modes d’approvisionnement les plus adaptés à votre activité de revente.',
            tip: 'Vos préférences d’approvisionnement nous aideront à mieux orienter les recommandations de fournisseurs, produits et services.',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Revendeur professionnel.',
        },
        verification: {
            hero: 'Finalisez la vérification de votre profil pour renforcer votre crédibilité auprès des fournisseurs.',
            tip: 'Des documents complets et à jour renforcent votre crédibilité auprès des fournisseurs et accélèrent la validation de votre profil.',
            subtitle: 'Finalisez les pièces professionnelles applicables à votre activité de Revendeur professionnel.',
        },
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
        'Inde': ['Mumbai', 'Delhi', 'Ahmedabad', 'Chennai', 'Bengaluru', 'Hyderabad', 'Pune', 'Kolkata'],
    };

    const flagMap = {
        'Côte d’Ivoire': 'ci', Sénégal: 'sn', Mali: 'ml', 'Burkina Faso': 'bf', Ghana: 'gh', Guinée: 'gn', Togo: 'tg', Bénin: 'bj', Niger: 'ne', Cameroun: 'cm', Nigeria: 'ng', Maroc: 'ma', France: 'fr', Chine: 'cn', Turquie: 'tr', 'Émirats arabes unis': 'ae', Inde: 'in',
    };

    const flagBase = (() => {
        const option = app.querySelector('option[data-flag]');
        const src = option?.dataset.flag || '/ovanie-pro/flags/ci.svg';
        return src.slice(0, src.lastIndexOf('/') + 1);
    })();

    const cssEscape = (value) => window.CSS?.escape ? CSS.escape(value) : String(value).replace(/(["'\\[\]#.])/g, '\\$1');
    const nodesFor = (name) => [...form.querySelectorAll(`[name="${cssEscape(name)}"], [name="${cssEscape(name)}[]"]`)];
    const valueOf = (name) => {
        const nodes = nodesFor(name);
        if (!nodes.length) return '';
        const first = nodes[0];
        if (first.type === 'radio') return nodes.find((node) => node.checked)?.value || '';
        if (first.type === 'checkbox') return nodes.filter((node) => node.checked).map((node) => node.value);
        return first.value ?? '';
    };
    const filled = (value) => Array.isArray(value) ? value.filter(Boolean).length > 0 : String(value ?? '').trim() !== '';

    function syncPhones() {
        app.querySelectorAll('[data-phone-box]').forEach((box) => {
            const code = box.querySelector('[data-phone-code]')?.value || '';
            const local = box.querySelector('[data-phone-local]')?.value || '';
            const hidden = box.querySelector(`input[type="hidden"][name="${box.dataset.phoneName}"]`);
            if (!hidden) return;
            const normalized = String(local).trim().replace(/^\+/, '').replace(/[\s().-]+/g, '');
            hidden.value = normalized ? `${code}${normalized}` : '';
        });
    }

    function documentReady(type) {
        const row = app.querySelector(`[data-doc-type="${cssEscape(type)}"]`);
        if (!row) return false;
        if (row.dataset.docProvided === '1') return true;
        return !!row.querySelector('input[type="file"]')?.files?.length;
    }

    function fieldFilled(name) {
        return filled(valueOf(name));
    }

    function sectionComplete(key) {
        syncPhones();
        if (key === 'verification') return documentReady('registry') && documentReady('professional_proof');
        return (required[key] || []).every(fieldFilled);
    }

    function states() {
        return Object.fromEntries(keys.map((key) => [key, sectionComplete(key)]));
    }

    function firstIncompleteIndex() {
        const state = states();
        const i = keys.findIndex((key) => !state[key]);
        return i < 0 ? keys.length - 1 : i;
    }

    function canAccess(index) {
        if (index <= 0) return true;
        for (let i = 0; i < index; i += 1) {
            if (!sectionComplete(keys[i])) return false;
        }
        return true;
    }

    function clearClientErrors() {
        app.querySelectorAll('.pg-invalid-group').forEach((node) => node.classList.remove('pg-invalid-group'));
        app.querySelectorAll('[data-client-error]').forEach((node) => node.remove());
    }

    function invalidContainer(name) {
        const node = form.querySelector(`[name="${cssEscape(name)}"]`) || form.querySelector(`[name="${cssEscape(name)}[]"]`);
        if (!node) return null;
        if (name === 'needs') return node.closest('.rv-needs-grid') || node.closest('.pg-field');
        return node.closest('.pg-field, .rv-doc-row, .rv-needs-grid') || node;
    }

    function markInvalid(name, message) {
        const box = invalidContainer(name);
        if (!box) return;
        box.classList.add('pg-invalid-group');
        if (!box.querySelector('[data-client-error]')) {
            const error = document.createElement('small');
            error.className = 'pg-error pg-error--client';
            error.dataset.clientError = '1';
            error.textContent = message;
            box.appendChild(error);
        }
    }

    function validateSection(key) {
        clearClientErrors();
        syncPhones();
        let ok = true;

        (required[key] || []).forEach((name) => {
            if (!fieldFilled(name)) {
                ok = false;
                markInvalid(name, name === 'needs' ? 'Sélectionnez au moins un mode d’approvisionnement.' : 'Ce champ est nécessaire pour continuer.');
            }
        });

        if (key === 'company') {
            const email = form.querySelector('[name="professional_email"]');
            if (email?.value && !email.checkValidity()) {
                ok = false;
                markInvalid('professional_email', 'Saisissez une adresse e-mail professionnelle valide.');
            }
            const phone = String(valueOf('professional_phone')).replace(/\D/g, '');
            if (phone && phone.length < 10) {
                ok = false;
                markInvalid('professional_phone', 'Saisissez un numéro de téléphone valide.');
            }
        }

        if (key === 'verification') {
            ['registry', 'professional_proof'].forEach((type) => {
                if (documentReady(type)) return;
                ok = false;
                const row = app.querySelector(`[data-doc-type="${type}"]`);
                row?.classList.add('pg-invalid-group');
                if (row && !row.querySelector('[data-client-error]')) {
                    const error = document.createElement('small');
                    error.className = 'pg-error rv-doc-error';
                    error.dataset.clientError = '1';
                    error.textContent = 'Ce document est obligatoire avant la soumission.';
                    row.appendChild(error);
                }
            });
        }

        if (!ok) app.querySelector('.pg-invalid-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return ok;
    }

    function makeDoneMarker(tab, done) {
        let visual = tab.querySelector('.pg-tab__visualdone');
        const server = tab.querySelector('.pg-tab__done');
        if (server) server.hidden = !done;
        if (done && !server && !visual) {
            visual = document.createElement('i');
            visual.className = 'pg-tab__visualdone';
            visual.textContent = '✓';
            tab.appendChild(visual);
        }
        if (!done && visual) visual.remove();
    }

    function updateProgress() {
        const state = states();
        const completed = keys.filter((key) => state[key]).length;
        if (completedCopy) completedCopy.textContent = completed;
        if (progressCopy) progressCopy.textContent = `${completed} section${completed > 1 ? 's' : ''} complétée${completed > 1 ? 's' : ''} sur 5`;
        if (progressBar) progressBar.style.width = `${completed * 20}%`;
        miniBars.forEach((bar, index) => bar.classList.toggle('is-done', !!state[keys[index]]));

        tabs.forEach((tab, index) => {
            makeDoneMarker(tab, !!state[tab.dataset.tabTarget]);
            const allowed = canAccess(index);
            tab.classList.toggle('is-locked', !allowed);
            tab.setAttribute('aria-disabled', allowed ? 'false' : 'true');
        });

        progressItems.forEach((item, index) => {
            const key = item.dataset.progressItem;
            const done = !!state[key];
            const current = key === active;
            const allowed = canAccess(index);
            item.classList.toggle('is-done', done);
            item.classList.toggle('is-active', current);
            item.classList.toggle('is-locked', !allowed);
            const status = item.querySelector('em');
            if (status) status.textContent = done ? 'Complétée' : (current ? 'En cours' : 'À compléter');
        });
    }

    function showTab(key, scroll = false) {
        const targetIndex = keys.indexOf(key);
        if (targetIndex < 0) return;
        if (!canAccess(targetIndex)) {
            const blockedKey = keys[firstIncompleteIndex()];
            if (blockedKey !== active) showTab(blockedKey, true);
            validateSection(blockedKey);
            return;
        }

        active = key;
        tabs.forEach((tab) => {
            const selected = tab.dataset.tabTarget === key;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.tabPanel !== key; });

        const copy = copyBySection[key] || copyBySection.company;
        if (sideCopy) sideCopy.textContent = copy.hero;
        if (tipCopy) tipCopy.textContent = copy.tip;
        if (pageSubtitle) pageSubtitle.textContent = copy.subtitle;
        if (nextButton) nextButton.hidden = key === 'verification';
        if (finalButton) finalButton.hidden = key !== 'verification';
        updateProgress();
        if (scroll) app.querySelector('.pg-tabsbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function dirtyState() {
        if (saveStatus) {
            saveStatus.dataset.state = 'dirty';
            saveStatus.textContent = 'Modifications non enregistrées';
        }
        updateProgress();
    }

    function initSmartSelect(select) {
        if (!select || select.dataset.smartReady === '1') return;
        select.dataset.smartReady = '1';
        const wrap = document.createElement('div');
        wrap.className = 'pg-select';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('pg-select__native');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pg-select__button';
        button.innerHTML = '<span class="pg-select__label"></span><span class="pg-select__chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>';
        const menu = document.createElement('div');
        menu.className = 'pg-select__menu';
        wrap.append(button, menu);

        const renderButton = () => {
            const option = select.selectedOptions[0];
            const label = button.querySelector('.pg-select__label');
            label.replaceChildren();
            if (option?.dataset.flag) {
                const img = document.createElement('img');
                img.src = option.dataset.flag;
                img.alt = '';
                label.appendChild(img);
            }
            const text = document.createElement('span');
            text.textContent = option?.textContent || 'Sélectionnez';
            label.appendChild(text);
        };

        const renderMenu = () => {
            menu.replaceChildren();
            [...select.options].forEach((option) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'pg-select__option';
                item.classList.toggle('is-selected', option.selected);
                if (option.dataset.flag) {
                    const img = document.createElement('img');
                    img.src = option.dataset.flag;
                    img.alt = '';
                    item.appendChild(img);
                }
                const text = document.createElement('span');
                text.textContent = option.textContent;
                item.appendChild(text);
                item.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    wrap.classList.remove('is-open');
                    renderButton();
                    renderMenu();
                });
                menu.appendChild(item);
            });
        };

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            app.querySelectorAll('.pg-select.is-open').forEach((open) => { if (open !== wrap) open.classList.remove('is-open'); });
            wrap.classList.toggle('is-open');
        });
        select.addEventListener('change', () => { renderButton(); renderMenu(); dirtyState(); });
        renderButton();
        renderMenu();
    }

    app.querySelectorAll('select[data-smart-select]').forEach(initSmartSelect);
    document.addEventListener('click', () => app.querySelectorAll('.pg-select.is-open, .pg-tokenfield.is-open').forEach((root) => root.classList.remove('is-open')));

    function updateCitySelect(countrySelect) {
        const cityId = countrySelect.dataset.citySource;
        if (!cityId) return;
        let citySelect = form.querySelector(`#${cssEscape(cityId)}`);
        if (!citySelect) return;
        const selected = citySelect.dataset.selected || citySelect.value;
        const cities = cityMap[countrySelect.value] || [];

        const oldWrap = citySelect.closest('.pg-select');
        if (oldWrap) {
            oldWrap.parentNode.insertBefore(citySelect, oldWrap);
            oldWrap.remove();
        }
        citySelect.classList.remove('pg-select__native');
        citySelect.dataset.smartReady = '0';
        citySelect.innerHTML = '<option value="">Sélectionnez une ville</option>' + cities.map((city) => `<option value="${city.replace(/"/g, '&quot;')}">${city}</option>`).join('');
        if (selected && cities.includes(selected)) citySelect.value = selected;
        citySelect.dataset.selected = citySelect.value;
        initSmartSelect(citySelect);
    }

    app.querySelectorAll('[data-city-source]').forEach((select) => {
        updateCitySelect(select);
        select.addEventListener('change', () => updateCitySelect(select));
    });

    function initTokenField(root) {
        const hidden = root.querySelector('input[type="hidden"]');
        const button = root.querySelector('.pg-tokenfield__button');
        const menu = root.querySelector('.pg-tokenfield__menu');
        const chips = root.querySelector('.pg-chips');
        if (!hidden || !button || !menu || !chips) return;
        const options = String(root.dataset.options || '').split('|').map((value) => value.trim()).filter(Boolean);
        let selected = String(hidden.value || '').split(/[,;\n]+/).map((value) => value.trim()).filter(Boolean);

        const sync = () => {
            hidden.value = selected.join(', ');
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const renderChips = () => {
            chips.replaceChildren();
            selected.forEach((value) => {
                const chip = document.createElement('span');
                chip.className = 'pg-chip';
                if (root.dataset.countryTokens !== undefined && flagMap[value]) {
                    const img = document.createElement('img');
                    img.src = `${flagBase}${flagMap[value]}.svg`;
                    img.alt = '';
                    chip.appendChild(img);
                }
                const text = document.createElement('span');
                text.textContent = value;
                chip.appendChild(text);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '×';
                remove.setAttribute('aria-label', `Retirer ${value}`);
                remove.addEventListener('click', () => {
                    selected = selected.filter((item) => item !== value);
                    sync(); renderChips(); renderMenu('');
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        };
        const renderMenu = (query = '') => {
            menu.replaceChildren();
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'pg-tokenfield__search';
            search.placeholder = 'Rechercher...';
            search.value = query;
            menu.appendChild(search);
            options.filter((value) => value.toLowerCase().includes(query.toLowerCase())).forEach((value) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'pg-tokenfield__option';
                item.classList.toggle('is-selected', selected.includes(value));
                if (root.dataset.countryTokens !== undefined && flagMap[value]) {
                    const img = document.createElement('img');
                    img.src = `${flagBase}${flagMap[value]}.svg`;
                    img.alt = '';
                    item.appendChild(img);
                }
                const label = document.createElement('span');
                label.textContent = value;
                item.appendChild(label);
                item.addEventListener('click', () => {
                    selected = selected.includes(value) ? selected.filter((item) => item !== value) : [...selected, value];
                    sync(); renderChips(); renderMenu(search.value);
                });
                menu.appendChild(item);
            });
            search.addEventListener('input', () => renderMenu(search.value));
            setTimeout(() => search.focus(), 0);
        };

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            app.querySelectorAll('.pg-tokenfield.is-open').forEach((open) => { if (open !== root) open.classList.remove('is-open'); });
            root.classList.toggle('is-open');
            if (root.classList.contains('is-open')) renderMenu('');
        });
        menu.addEventListener('click', (event) => event.stopPropagation());
        renderChips();
    }

    app.querySelectorAll('[data-token-field]').forEach(initTokenField);

    app.querySelectorAll('[data-phone-box]').forEach((box) => {
        box.querySelector('[data-phone-local]')?.addEventListener('input', () => { syncPhones(); dirtyState(); });
        box.querySelector('[data-phone-code]')?.addEventListener('change', () => { syncPhones(); dirtyState(); });
    });

    app.querySelector('[data-same-whatsapp]')?.addEventListener('change', (event) => {
        if (!event.target.checked) return;
        const phone = app.querySelector('[data-phone-name="professional_phone"]');
        const whatsapp = app.querySelector('[data-phone-name="professional_whatsapp"]');
        if (!phone || !whatsapp) return;
        const phoneCode = phone.querySelector('[data-phone-code]')?.value;
        const phoneLocal = phone.querySelector('[data-phone-local]')?.value;
        const waCode = whatsapp.querySelector('[data-phone-code]');
        const waLocal = whatsapp.querySelector('[data-phone-local]');
        if (waCode) {
            waCode.value = phoneCode;
            waCode.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (waLocal) waLocal.value = phoneLocal;
        syncPhones(); dirtyState();
    });
    syncPhones();

    app.querySelectorAll('.rv-upload input[type="file"]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('.rv-doc-row');
            const fileName = row?.querySelector('[data-file-name]');
            if (fileName) fileName.textContent = input.files?.length ? [...input.files].map((file) => file.name).join(', ') : '';
            dirtyState();
        });
    });

    tabs.forEach((tab) => tab.addEventListener('click', () => showTab(tab.dataset.tabTarget, true)));
    progressItems.forEach((item) => item.querySelector('button')?.addEventListener('click', () => showTab(item.dataset.progressItem, true)));

    nextButton?.addEventListener('click', () => {
        if (!validateSection(active)) return;
        const next = Math.min(keys.indexOf(active) + 1, keys.length - 1);
        showTab(keys[next], true);
    });

    saveButton?.addEventListener('click', () => {
        syncPhones();
        actionInput.value = 'draft';
        if (saveStatus) {
            saveStatus.dataset.state = 'saving';
            saveStatus.textContent = 'Enregistrement…';
        }
    });

    finalButton?.addEventListener('click', (event) => {
        event.preventDefault();
        for (let index = 0; index < keys.length; index += 1) {
            if (!validateSection(keys[index])) {
                showTab(keys[index], true);
                return;
            }
        }
        syncPhones();
        actionInput.value = 'submit';
        form.requestSubmit();
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="hidden"]')) return;
        dirtyState();
    });
    form.addEventListener('change', () => dirtyState());
    form.addEventListener('submit', () => syncPhones());

    showTab(active, false);
    updateProgress();
})();
