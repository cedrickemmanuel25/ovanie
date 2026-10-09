(() => {
    'use strict';

    const app = document.querySelector('[data-demi-grossiste-page]');
    if (!app) return;
    const form = app.querySelector('[data-demi-grossiste-form]');
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
    const sideImage = app.querySelector('[data-side-image]');
    const pageSubtitle = app.querySelector('[data-page-subtitle]');
    const tierInput = app.querySelector('[data-tier-json]');
    const tierList = app.querySelector('[data-tier-list]');

    let active = app.dataset.firstTab || 'company';
    let dirty = false;

    const required = {
        company: ['legal_name', 'legal_form', 'registration_country', 'city', 'address', 'representative_name', 'responsible_position', 'professional_phone', 'professional_email'],
        activity: ['categories', 'origin_country', 'sale_units', 'minimum_quantity', 'minimum_quantity_unit', 'supply_frequency', 'quantity_tiers'],
        zones: ['dispatch_country', 'dispatch_city', 'cities_served', 'regions_served', 'countries_served'],
        conditions: ['availability', 'preparation_time', 'preparation_unit', 'restock_frequency', 'volume_negotiation'],
        verification: [],
    };

    const copyBySection = {
        company: {
            hero: 'Des quantités adaptées aux besoins des professionnels du BTP.',
            tip: 'Ces informations renforcent votre visibilité auprès des clients professionnels et facilitent la mise en relation sur OVANIE PRO.',
            image: 'demi-grossiste-hero.png',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Demi-grossiste.',
        },
        activity: {
            hero: 'Des produits en semi-gros avec plusieurs paliers de quantité, pour répondre aux besoins des professionnels du BTP.',
            tip: 'Des informations claires sur vos catégories de produits, vos stocks et vos paliers de quantité aident les acheteurs professionnels à mieux vous identifier.',
            image: 'demi-grossiste-hero.png',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Demi-grossiste.',
        },
        zones: {
            hero: 'Une distribution en semi-gros au plus près des chantiers en Afrique de l’Ouest.',
            tip: 'Des informations précises sur vos zones desservies aident les acheteurs à identifier plus facilement votre entreprise et à réduire les délais de livraison.',
            image: 'demi-grossiste-zones-hero.png',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Demi-grossiste.',
        },
        conditions: {
            hero: 'Des conditions commerciales claires pour une collaboration efficace.',
            tip: 'Des informations claires sur vos conditions commerciales renforcent la confiance des acheteurs et facilitent la mise en relation sur OVANIE PRO.',
            image: 'demi-grossiste-zones-hero.png',
            subtitle: 'Renseignez les informations spécifiques à votre activité de Demi-grossiste.',
        },
        verification: {
            hero: 'Des quantités adaptées aux besoins des professionnels du BTP.',
            tip: 'Ces documents renforcent la confiance des acheteurs professionnels et améliorent votre visibilité sur OVANIE PRO.',
            image: 'demi-grossiste-hero.png',
            subtitle: 'Finalisez votre dossier professionnel Demi-grossiste.',
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
    };

    const flagMap = {
        'Côte d’Ivoire': 'ci', Sénégal: 'sn', Mali: 'ml', 'Burkina Faso': 'bf', Ghana: 'gh', Guinée: 'gn', Togo: 'tg', Bénin: 'bj', Niger: 'ne', Cameroun: 'cm', Nigeria: 'ng', Maroc: 'ma', France: 'fr', Chine: 'cn', Turquie: 'tr', 'Émirats arabes unis': 'ae',
    };

    const assetBase = (() => {
        const src = sideImage?.getAttribute('src') || '/ovanie-pro/demi-grossiste-hero.png';
        return src.slice(0, src.lastIndexOf('/') + 1);
    })();

    const flagBase = (() => {
        const img = app.querySelector('option[data-flag]');
        const src = img?.dataset.flag || '/ovanie-pro/flags/ci.svg';
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
            const normalized = String(local).trim().replace(/^\+/, '');
            hidden.value = normalized ? `${code}${normalized.replace(/\s+/g, '')}` : '';
        });
    }

    function tiers() {
        try {
            const parsed = JSON.parse(tierInput?.value || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) { return []; }
    }

    function validTiers() {
        const rows = tiers();
        if (!rows.length) return false;
        return rows.every((row) => filled(row?.from) && filled(row?.unit) && (!filled(row?.to) || (!Number.isNaN(Number(String(row.to).replace(',', '.'))) && Number(String(row.to).replace(',', '.')) >= Number(String(row.from).replace(',', '.')))));
    }

    function documentReady(type) {
        const row = app.querySelector(`[data-doc-type="${cssEscape(type)}"]`);
        if (!row) return false;
        if (row.dataset.docProvided === '1') return true;
        const input = row.querySelector('input[type="file"]');
        return !!input?.files?.length;
    }

    function fieldFilled(name) {
        if (name === 'quantity_tiers') return validTiers();
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
        for (let i = 0; i < index; i += 1) if (!sectionComplete(keys[i])) return false;
        return true;
    }

    function clearClientErrors() {
        app.querySelectorAll('.pg-invalid-group').forEach((node) => node.classList.remove('pg-invalid-group'));
        app.querySelectorAll('[data-client-error]').forEach((node) => node.remove());
    }

    function invalidContainer(name) {
        if (name === 'quantity_tiers') return tierList?.closest('.dg-tier-section');
        const node = form.querySelector(`[name="${cssEscape(name)}"]`) || form.querySelector(`[name="${cssEscape(name)}[]"]`);
        if (node) return node.closest('.pg-field, .dg-negotiation, .dg-doc-row, .dg-tier-section') || node;
        return null;
    }

    function markInvalid(name, message) {
        const box = invalidContainer(name);
        if (!box) return;
        box.classList.add('pg-invalid-group');
        if (!box.querySelector('[data-client-error]')) {
            const el = document.createElement('small');
            el.className = 'pg-error pg-error--client';
            el.dataset.clientError = '1';
            el.textContent = message;
            box.appendChild(el);
        }
    }

    function validateSection(key) {
        clearClientErrors();
        syncPhones();
        let ok = true;
        (required[key] || []).forEach((name) => {
            if (!fieldFilled(name)) {
                ok = false;
                markInvalid(name, name === 'quantity_tiers' ? 'Ajoutez au moins un palier complet.' : 'Ce champ est nécessaire pour continuer.');
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
        if (key === 'activity' && !validTiers()) {
            ok = false;
            markInvalid('quantity_tiers', 'Chaque palier doit avoir une quantité de départ et une unité.');
        }
        if (key === 'verification') {
            ['registry', 'professional_proof'].forEach((type) => {
                if (!documentReady(type)) {
                    ok = false;
                    const row = app.querySelector(`[data-doc-type="${type}"]`);
                    row?.classList.add('pg-invalid-group');
                    if (row && !row.querySelector('[data-client-error]')) {
                        const el = document.createElement('small');
                        el.dataset.clientError = '1';
                        el.className = 'pg-error dg-doc-error';
                        el.textContent = 'Ce document est obligatoire avant la soumission.';
                        row.appendChild(el);
                    }
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
        miniBars.forEach((bar, i) => bar.classList.toggle('is-done', !!state[keys[i]]));
        tabs.forEach((tab, i) => {
            makeDoneMarker(tab, !!state[tab.dataset.tabTarget]);
            tab.classList.toggle('is-locked', !canAccess(i));
            tab.setAttribute('aria-disabled', canAccess(i) ? 'false' : 'true');
        });
        progressItems.forEach((item, i) => {
            const key = item.dataset.progressItem;
            const done = !!state[key];
            const current = key === active;
            const allowed = canAccess(i);
            item.classList.toggle('is-done', done);
            item.classList.toggle('is-active', current);
            item.classList.toggle('is-current', current && !done);
            item.classList.toggle('is-locked', !allowed);
            const status = item.querySelector('em');
            if (status) status.textContent = done ? 'Complétée' : (current ? 'En cours' : 'À compléter');
        });
    }

    function showTab(key, scroll = false) {
        const targetIndex = keys.indexOf(key);
        if (targetIndex < 0) return;
        if (!canAccess(targetIndex)) {
            const blockedIndex = firstIncompleteIndex();
            const blockedKey = keys[blockedIndex];
            showTab(blockedKey, true);
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
        const content = copyBySection[key] || copyBySection.company;
        if (sideCopy) sideCopy.textContent = content.hero;
        if (tipCopy) tipCopy.textContent = content.tip;
        if (pageSubtitle) pageSubtitle.textContent = content.subtitle;
        if (sideImage) sideImage.src = assetBase + content.image;
        if (nextButton) nextButton.hidden = key === 'verification';
        if (finalButton) finalButton.hidden = key !== 'verification';
        updateProgress();
        if (scroll) app.querySelector('.pg-tabsbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function dirtyState() {
        dirty = true;
        if (saveStatus) {
            saveStatus.dataset.state = 'dirty';
            saveStatus.textContent = 'Modifications non enregistrées';
        }
        updateProgress();
    }

    // ----- Smart selects -----
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
            const span = document.createElement('span');
            span.textContent = option?.textContent || 'Sélectionnez';
            label.appendChild(span);
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
    document.addEventListener('click', () => app.querySelectorAll('.pg-select.is-open').forEach((wrap) => wrap.classList.remove('is-open')));

    // ----- Country -> city -----
    function updateCitySelect(countrySelect) {
        const cityId = countrySelect.dataset.citySource;
        if (!cityId) return;
        const citySelect = form.querySelector(`#${cssEscape(cityId)}`);
        if (!citySelect) return;
        const selected = citySelect.dataset.selected || citySelect.value;
        const cities = cityMap[countrySelect.value] || [];
        citySelect.innerHTML = '<option value="">Sélectionnez une ville</option>' + cities.map((city) => `<option value="${city.replace(/"/g, '&quot;')}">${city}</option>`).join('');
        if (selected && cities.includes(selected)) citySelect.value = selected;
        citySelect.dataset.selected = citySelect.value;
        const old = citySelect.closest('.pg-select');
        if (old) {
            const parent = old.parentNode;
            old.replaceWith(citySelect);
            parent.appendChild(citySelect);
        }
        citySelect.dataset.smartReady = '0';
        initSmartSelect(citySelect);
    }
    app.querySelectorAll('[data-city-source]').forEach((select) => {
        updateCitySelect(select);
        select.addEventListener('change', () => updateCitySelect(select));
    });

    // ----- Token fields -----
    function initTokenField(root) {
        const hidden = root.querySelector('input[type="hidden"]');
        const button = root.querySelector('.pg-tokenfield__button');
        const menu = root.querySelector('.pg-tokenfield__menu');
        const chips = root.querySelector('.pg-chips');
        const options = String(root.dataset.options || '').split('|').map((v) => v.trim()).filter(Boolean);
        let selected = String(hidden.value || '').split(/[,;\n]+/).map((v) => v.trim()).filter(Boolean);

        const sync = () => { hidden.value = selected.join(', '); hidden.dispatchEvent(new Event('change', { bubbles: true })); };
        const renderChips = () => {
            chips.replaceChildren();
            selected.forEach((value) => {
                const chip = document.createElement('span');
                chip.className = 'pg-chip';
                if (root.dataset.countryTokens !== undefined && flagMap[value]) {
                    const img = document.createElement('img'); img.src = `${flagBase}${flagMap[value]}.svg`; img.alt = ''; chip.appendChild(img);
                }
                const text = document.createElement('span'); text.textContent = value; chip.appendChild(text);
                const x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', `Retirer ${value}`);
                x.addEventListener('click', () => { selected = selected.filter((item) => item !== value); sync(); renderChips(); renderMenu(''); });
                chip.appendChild(x); chips.appendChild(chip);
            });
        };
        const renderMenu = (query = '') => {
            menu.replaceChildren();
            const search = document.createElement('input'); search.type = 'search'; search.className = 'pg-tokenfield__search'; search.placeholder = 'Rechercher...'; search.value = query;
            menu.appendChild(search);
            options.filter((value) => value.toLowerCase().includes(query.toLowerCase())).forEach((value) => {
                const item = document.createElement('button'); item.type = 'button'; item.className = 'pg-tokenfield__option';
                if (root.dataset.countryTokens !== undefined && flagMap[value]) { const img=document.createElement('img'); img.src=`${flagBase}${flagMap[value]}.svg`; img.alt=''; item.appendChild(img); }
                const label=document.createElement('span'); label.textContent=value; item.appendChild(label);
                if (selected.includes(value)) item.classList.add('is-selected');
                item.addEventListener('click', () => { selected = selected.includes(value) ? selected.filter((v)=>v!==value) : [...selected,value]; sync(); renderChips(); renderMenu(search.value); });
                menu.appendChild(item);
            });
            search.addEventListener('input', () => renderMenu(search.value));
            setTimeout(() => search.focus(), 0);
        };
        button.addEventListener('click', (e) => { e.stopPropagation(); app.querySelectorAll('.pg-tokenfield.is-open').forEach((open)=>{if(open!==root) open.classList.remove('is-open');}); root.classList.toggle('is-open'); if(root.classList.contains('is-open')) renderMenu(''); });
        menu.addEventListener('click', (e) => e.stopPropagation());
        renderChips();
    }
    app.querySelectorAll('[data-token-field]').forEach(initTokenField);
    document.addEventListener('click', () => app.querySelectorAll('.pg-tokenfield.is-open').forEach((root)=>root.classList.remove('is-open')));

    // ----- Quantity tiers -----
    let tierRows = tiers();
    if (!tierRows.length) tierRows = [{from:'',to:'',unit:'unites'},{from:'',to:'',unit:'unites'},{from:'',to:'',unit:'unites'}];
    const unitLabels = {unites:'Unités',pieces:'Pièces',sacs:'Sacs',cartons:'Cartons',packs:'Packs',palettes:'Palettes',lots:'Lots',tonnes:'Tonnes',m2:'m²',m3:'m³'};
    function syncTiers() {
        if (tierInput) tierInput.value = JSON.stringify(tierRows.map(({from,to,unit})=>({from:String(from||'').trim(),to:String(to||'').trim(),unit:unit||'unites'})));
        dirtyState();
    }
    function renderTiers() {
        if (!tierList) return;
        tierList.replaceChildren();
        tierRows.forEach((tier, index) => {
            const row = document.createElement('div'); row.className='dg-tier-row';
            row.innerHTML = `<span class="dg-tier-row__badge">Palier ${index+1}</span><label>À partir de</label><input type="text" inputmode="decimal" data-tier-from value="${String(tier.from||'').replace(/"/g,'&quot;')}" placeholder="${index===0?'10':index===1?'50':'100'}"><label>Jusqu’à</label><input type="text" inputmode="decimal" data-tier-to value="${String(tier.to||'').replace(/"/g,'&quot;')}" placeholder="${index===0?'49':index===1?'99':'Ex. 9999'}"><label>Unité</label><select data-tier-unit>${Object.entries(unitLabels).map(([k,l])=>`<option value="${k}" ${tier.unit===k?'selected':''}>${l}</option>`).join('')}</select><button type="button" class="dg-tier-remove" title="Supprimer le palier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/></svg></button>`;
            const from=row.querySelector('[data-tier-from]'), to=row.querySelector('[data-tier-to]'), unit=row.querySelector('[data-tier-unit]');
            const syncRow=()=>{tierRows[index]={from:from.value,to:to.value,unit:unit.value};syncTiers();};
            from.addEventListener('input',syncRow);to.addEventListener('input',syncRow);unit.addEventListener('change',syncRow);
            row.querySelector('.dg-tier-remove').addEventListener('click',()=>{tierRows.splice(index,1);if(!tierRows.length)tierRows.push({from:'',to:'',unit:'unites'});syncTiers();renderTiers();});
            tierList.appendChild(row);
        });
        if (tierInput) tierInput.value = JSON.stringify(tierRows);
    }
    app.querySelector('[data-add-tier]')?.addEventListener('click',()=>{tierRows.push({from:'',to:'',unit:'unites'});syncTiers();renderTiers();});
    renderTiers();

    // ----- Phone interactions -----
    app.querySelectorAll('[data-phone-box]').forEach((box) => {
        box.querySelector('[data-phone-local]')?.addEventListener('input', () => { syncPhones(); dirtyState(); });
        box.querySelector('[data-phone-code]')?.addEventListener('change', () => { syncPhones(); dirtyState(); });
    });
    app.querySelector('[data-same-whatsapp]')?.addEventListener('change', (event) => {
        if (!event.target.checked) return;
        const phone = app.querySelector('[data-phone-name="professional_phone"]');
        const wa = app.querySelector('[data-phone-name="professional_whatsapp"]');
        if (!phone || !wa) return;
        const phoneCode = phone.querySelector('[data-phone-code]')?.value;
        const phoneLocal = phone.querySelector('[data-phone-local]')?.value;
        const waCode = wa.querySelector('[data-phone-code]');
        const waLocal = wa.querySelector('[data-phone-local]');
        if (waCode) { waCode.value = phoneCode; waCode.dispatchEvent(new Event('change', { bubbles: true })); }
        if (waLocal) waLocal.value = phoneLocal;
        syncPhones(); dirtyState();
    });
    syncPhones();

    // ----- Files & counters -----
    app.querySelectorAll('.dg-upload input[type="file"]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('.dg-doc-row');
            const label = row?.querySelector('[data-file-name]');
            if (label) label.textContent = input.files?.length ? [...input.files].map((f)=>f.name).join(', ') : '';
            dirtyState();
        });
    });
    app.querySelectorAll('[data-counted]').forEach((textarea) => {
        const counter = textarea.parentElement?.querySelector('[data-counter]');
        const update = () => { if (counter) counter.textContent = textarea.value.length; };
        textarea.addEventListener('input', update); update();
    });

    // ----- Navigation -----
    tabs.forEach((tab) => tab.addEventListener('click', () => showTab(tab.dataset.tabTarget, true)));
    progressItems.forEach((item) => item.querySelector('button')?.addEventListener('click', () => showTab(item.dataset.progressItem, true)));
    nextButton?.addEventListener('click', () => {
        if (!validateSection(active)) return;
        const next = Math.min(keys.indexOf(active) + 1, keys.length - 1);
        showTab(keys[next], true);
    });
    saveButton?.addEventListener('click', () => {
        syncPhones();
        if (tierInput) tierInput.value = JSON.stringify(tierRows);
        actionInput.value = 'draft';
        if (saveStatus) { saveStatus.dataset.state='saving'; saveStatus.textContent='Enregistrement…'; }
    });
    finalButton?.addEventListener('click', (event) => {
        event.preventDefault();
        for (let i=0;i<keys.length;i+=1) {
            if (!validateSection(keys[i])) { showTab(keys[i], true); return; }
        }
        syncPhones();
        if (tierInput) tierInput.value = JSON.stringify(tierRows);
        actionInput.value = 'submit';
        form.requestSubmit();
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="hidden"]')) return;
        dirtyState();
    });
    form.addEventListener('change', () => dirtyState());
    form.addEventListener('submit', () => { syncPhones(); if (tierInput) tierInput.value = JSON.stringify(tierRows); });

    showTab(active, false);
    updateProgress();
})();
