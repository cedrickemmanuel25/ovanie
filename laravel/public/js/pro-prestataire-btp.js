(() => {
    const app = document.querySelector('[data-prestataire-page]');
    if (!app) return;
    const form = app.querySelector('[data-prestataire-form]');
    const keys = ['company','services','intervention','means','references','qualifications','pricing','verification'];
    const panels = [...app.querySelectorAll('[data-tab-panel]')];
    const tabs = [...app.querySelectorAll('[data-tab-target]')];
    const progressItems = [...app.querySelectorAll('[data-progress-item]')];
    const continueBtn = app.querySelector('[data-continue]');
    const prevBtn = app.querySelector('[data-prev]');
    const saveBtn = app.querySelector('[data-save-draft]');
    const actionField = form.querySelector('[data-action-field]');
    const saveStatus = app.querySelector('[data-save-status]');
    const heroImage = app.querySelector('[data-hero-image]');
    const tipCopy = app.querySelector('[data-tip-copy]');
    const progressPosition = app.querySelector('[data-progress-position]');
    let active = window.__PP_INITIAL__?.firstTab || app.dataset.firstTab || 'company';

    const heroFile = {company:'hero-company.png',services:'hero-services.png',intervention:'hero-intervention.png',means:'hero-means.png',references:'hero-references.png',qualifications:'hero-qualifications.png',pricing:'hero-pricing.png',verification:'hero-verification.png'};
    const tips = {
        company:'Les informations sur votre entreprise renforcent la confiance des donneurs d’ordre et facilitent votre référencement sur OVANIE PRO.',
        services:'Plus votre offre de services est claire et détaillée, plus vous avez de chances d’être contacté pour des projets pertinents.',
        intervention:"Plus votre zone d’intervention et votre disponibilité sont claires, plus vous avez de chances d’être contacté pour des projets pertinents dans votre région.",
        means:'Les moyens que vous déclarez renforcent la confiance des clients et partenaires et facilitent votre sélection sur OVANIE PRO.',
        references:'Les références avec nom de client, photos et vidéos sont visibles publiquement uniquement si vous donnez votre autorisation.',
        qualifications:'Des qualifications à jour et vérifiées augmentent votre visibilité et la confiance des partenaires sur OVANIE PRO.',
        pricing:'Une tarification claire aide vos clients à mieux comprendre vos prestations et facilite les demandes de devis.',
        verification:'La liste des documents à fournir dépend de votre activité, de votre statut et de votre pays. Tous les documents sont examinés avant validation.'
    };

    const sectionFields = {
        company: app.dataset.entity === 'organization'
            ? ['legal_name','trade_name','registration_country','legal_form','city','address','registration_number','tax_number','professional_phone','professional_whatsapp','professional_email']
            : ['trade_name','independent_status','city','professional_phone','professional_whatsapp','professional_email'],
        services:['service_category','service_subcategory','service_title','services_description','skills'],
        intervention:['intervention_zones','availability','team_capacity','team_size','indicative_lead_time'],
        means:['means_types','equipment_json'],
        references:['references_status'],
        qualifications:['qualifications_status'],
        pricing:['pricing_mode'],
        verification:[]
    };

    const esc = (s) => window.CSS?.escape ? CSS.escape(s) : String(s).replace(/["\\]/g,'\\$&');
    const input = (name) => form.querySelector(`[name="${esc(name)}"]`) || form.querySelector(`[name="${esc(name)}[]"]`);
    const val = (name) => {
        const nodes = [...form.querySelectorAll(`[name="${esc(name)}"], [name="${esc(name)}[]"]`)];
        if (!nodes.length) return '';
        const radios = nodes.filter(n => n.type === 'radio');
        if (radios.length) return radios.find(n => n.checked)?.value || '';
        const checks = nodes.filter(n => n.type === 'checkbox');
        if (checks.length) return checks.filter(n => n.checked).map(n => n.value).join(',');
        return nodes[0].value || '';
    };
    const filled = (name) => String(val(name)).trim() !== '';

    function clearClientErrors() {
        app.querySelectorAll('.pp-invalid').forEach(n => n.classList.remove('pp-invalid'));
        app.querySelectorAll('[data-client-error]').forEach(n => n.remove());
    }
    function markInvalid(name, message='Ce champ est nécessaire pour continuer.') {
        const node = input(name) || app.querySelector(`[data-phone-field="${name}"]`);
        if (!node) return;
        const field = node.closest('.pp-field,.pp-choice-grid,.pp-card,.pp-doc-row') || node;
        field.classList.add('pp-invalid');
        if (!field.querySelector('[data-client-error]')) {
            const e = document.createElement('small'); e.className='pp-error'; e.dataset.clientError='1'; e.textContent=message; field.appendChild(e);
        }
    }

    function syncPhones() {
        app.querySelectorAll('[data-phone-field]').forEach(box => {
            const code = box.querySelector('[data-phone-code]')?.value || '';
            const local = box.querySelector('[data-phone-local]')?.value || '';
            const hidden = box.querySelector('[data-phone-value]');
            if (hidden) hidden.value = local ? `${code}${local.replace(/^0+/, '').replace(/\s+/g,'')}` : '';
        });
    }
    function initPhones() {
        app.querySelectorAll('[data-phone-field]').forEach(box => {
            const hidden = box.querySelector('[data-phone-value]');
            const code = box.querySelector('[data-phone-code]');
            const local = box.querySelector('[data-phone-local]');
            let raw = hidden?.value || '';
            const codes = [...code.options].map(o=>o.value).sort((a,b)=>b.length-a.length);
            const found = codes.find(c => raw.startsWith(c));
            if (found) { code.value=found; raw=raw.slice(found.length); }
            if (local && raw) local.value=raw;
            code?.addEventListener('change',syncPhones); local?.addEventListener('input',syncPhones);
        });
        syncPhones();
    }

    function initRadioCards() {
        app.querySelectorAll('[data-radio-cards]').forEach(group => {
            const update = () => group.querySelectorAll('.pp-choice').forEach(card => card.classList.toggle('is-selected', !!card.querySelector('input')?.checked));
            group.addEventListener('change', update); update();
        });
    }

    function initTokens() {
        app.querySelectorAll('[data-token]').forEach(box => {
            const text = box.querySelector('input[type=text]');
            const hidden = box.querySelector('input[type=hidden]');
            const chips = box.querySelector('.pp-token__chips');
            const options = String(box.dataset.options||'').split('|').map(s=>s.trim()).filter(Boolean);
            let values = String(hidden.value||'').split(/[,;\n]+/).map(s=>s.trim()).filter(Boolean);
            let menu;
            const render = () => {
                chips.innerHTML=''; values.forEach(v=>{const chip=document.createElement('span');chip.className='pp-chip';chip.innerHTML=`<span>${v}</span><button type="button" aria-label="Supprimer">×</button>`;chip.querySelector('button').onclick=()=>{values=values.filter(x=>x!==v);sync();};chips.appendChild(chip);});
                hidden.value=values.join(', ');
            };
            const sync = () => { render(); form.dispatchEvent(new Event('input',{bubbles:true})); };
            const close = () => { menu?.remove(); menu=null; };
            const open = () => {
                close(); const q=(text.value||'').toLowerCase(); const remaining=options.filter(o=>!values.includes(o)&&o.toLowerCase().includes(q));
                if (!remaining.length) return;
                menu=document.createElement('div');menu.className='pp-token__menu';remaining.forEach(o=>{const b=document.createElement('button');b.type='button';b.textContent=o;b.onclick=()=>{values.push(o);text.value='';sync();open();};menu.appendChild(b);});box.appendChild(menu);
            };
            text.addEventListener('focus',open); text.addEventListener('input',open); text.addEventListener('keydown',e=>{if(e.key==='Enter'&&text.value.trim()){e.preventDefault();const x=text.value.trim();if(!values.includes(x))values.push(x);text.value='';sync();close();}});
            document.addEventListener('click',e=>{if(!box.contains(e.target))close();}); render();
        });
    }

    const subcategories = {
        installation:['Électricité','Plomberie','Climatisation','Solaire','Sécurité incendie','Réseaux / télécom','Automatisme','Autre'],
        maintenance:['Maintenance électrique','Maintenance multitechnique','Maintenance CVC','Maintenance groupes électrogènes','Maintenance plomberie','Autre'],
        etudes:['Études techniques','Bureau d’études','Ingénierie','Audit','Métré / estimation','Autre'],
        transport:['Transport matériaux','Transport engins','Livraison chantier','Manutention','Autre'],
        location:['Engins de chantier','Matériel de levage','Outillage','Échafaudage','Groupes électrogènes','Autre'],
        sous_traitance:['Gros œuvre','Second œuvre','Électricité','Plomberie','VRD','Finitions','Autre'],
        inspection:['Contrôle qualité','Inspection technique','Diagnostic','Audit HSE','Autre'],
        securite:['HSE','Sécurité chantier','Prévention incendie','EPI / formation','Autre'],
        autre:['Autre prestation BTP']
    };
    function initProfessionalSelects() {
        app.querySelectorAll('select').forEach(select => {
            select.removeAttribute('disabled');
            select.addEventListener('change', () => {
                const field = select.closest('.pp-field');
                field?.classList.remove('pp-invalid');
                field?.querySelectorAll('[data-client-error]').forEach(n => n.remove());
                form.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        const country = app.querySelector('[data-country-select]');
        const flag = app.querySelector('[data-country-flag]');
        const syncCountryFlag = () => {
            if (!country || !flag) return;
            const option = country.options[country.selectedIndex];
            flag.textContent = option?.dataset?.flag || '🌍';
        };
        country?.addEventListener('change', syncCountryFlag);
        syncCountryFlag();
    }

    function initServiceSelects() {
        const cat=form.querySelector('[data-service-category]'), sub=form.querySelector('[data-service-subcategory]'), context=app.querySelector('[data-service-context]');
        if(!cat||!sub)return;
        const refresh=()=>{const cur=sub.dataset.current||sub.value;sub.innerHTML='<option value="">Sélectionnez une sous-catégorie</option>';(subcategories[cat.value]||[]).forEach(label=>{const o=document.createElement('option');o.value=label;o.textContent=label;if(cur===label)o.selected=true;sub.appendChild(o);});sub.dataset.current='';if(context)context.textContent=cat.options[cat.selectedIndex]?.text||'Votre activité';};
        cat.addEventListener('change',()=>{sub.dataset.current='';refresh();});refresh();
    }

    let equipment = Array.isArray(window.__PP_INITIAL__?.equipment) ? window.__PP_INITIAL__.equipment : [];
    const equipmentList=app.querySelector('[data-equipment-list]'), equipmentJson=app.querySelector('[data-equipment-json]');
    function syncEquipment(){ if(equipmentJson) equipmentJson.value=JSON.stringify(equipment.filter(r=>Object.values(r).some(x=>String(x||'').trim()))); }
    function renderEquipment(){ if(!equipmentList)return; if(!equipment.length)equipment=[{name:'',type:'',quantity:'',ownership:'',description:''}]; equipmentList.innerHTML=''; equipment.forEach((row,i)=>{const el=document.createElement('div');el.className='pp-equipment-row';el.innerHTML=`<div class="pp-equipment-thumb">${iconSvg('gear')}</div><div class="pp-equipment-fields"><input data-e="name" placeholder="Nom de l’équipement" value="${attr(row.name)}"><input data-e="type" placeholder="Type" value="${attr(row.type)}"><input data-e="quantity" placeholder="Quantité" value="${attr(row.quantity)}"><select data-e="ownership"><option value="">Propriété / location</option><option value="owned" ${row.ownership==='owned'?'selected':''}>Propriété</option><option value="rented" ${row.ownership==='rented'?'selected':''}>Location</option></select><input class="wide" data-e="description" placeholder="Description (facultatif)" value="${attr(row.description)}"></div><button type="button" class="pp-iconbtn" data-remove-equipment aria-label="Supprimer">×</button>`; el.querySelectorAll('[data-e]').forEach(inp=>inp.addEventListener('input',()=>{equipment[i][inp.dataset.e]=inp.value;syncEquipment();}));el.querySelector('[data-remove-equipment]').onclick=()=>{equipment.splice(i,1);renderEquipment();};equipmentList.appendChild(el);});syncEquipment();}
    app.querySelector('[data-add-equipment]')?.addEventListener('click',()=>{equipment.push({name:'',type:'',quantity:'',ownership:'',description:''});renderEquipment();});

    let references = Array.isArray(window.__PP_INITIAL__?.references) ? window.__PP_INITIAL__.references : [];
    const referenceList=app.querySelector('[data-reference-list]'), referencesJson=app.querySelector('[data-references-json]'), referencesStatus=app.querySelector('[data-references-status]');
    function syncReferences(){ if(referencesJson) referencesJson.value=JSON.stringify(references.filter(r=>Object.values(r).some(x=>String(x||'').trim()))); }
    function renderReferences(){ if(!referenceList)return; referenceList.innerHTML=''; const rows=references.length?references:[{title:'',type:'',client:'',city:'',country:'Côte d’Ivoire',year:'',description:'',public:false}]; if(!references.length)references=rows; references.forEach((row,i)=>{const el=document.createElement('div');el.className='pp-reference-card';el.innerHTML=`<div class="pp-reference-card__head"><span>${i+1}</span><strong>Réalisation n°${i+1}${row.title?' · '+escapeHtml(row.title):''}</strong><button type="button" class="pp-iconbtn" data-remove-reference>×</button></div><div class="pp-reference-card__body"><input data-r="title" placeholder="Titre de la réalisation *" value="${attr(row.title)}"><input data-r="type" placeholder="Type de prestation *" value="${attr(row.type)}"><input data-r="client" placeholder="Client de référence" value="${attr(row.client)}"><input data-r="year" placeholder="Année" value="${attr(row.year)}"><input data-r="city" placeholder="Ville" value="${attr(row.city)}"><input data-r="country" placeholder="Pays" value="${attr(row.country||'Côte d’Ivoire')}"><textarea data-r="description" placeholder="Description de la réalisation">${escapeHtml(row.description||'')}</textarea><label class="pp-public-check"><input type="checkbox" data-r="public" ${row.public?'checked':''}> J’autorise l’affichage public du nom de ce client sur mon profil.</label></div>`;el.querySelectorAll('[data-r]').forEach(inp=>inp.addEventListener('input',()=>{references[i][inp.dataset.r]=inp.type==='checkbox'?inp.checked:inp.value;syncReferences();}));el.querySelector('[data-remove-reference]').onclick=()=>{references.splice(i,1);renderReferences();};referenceList.appendChild(el);});syncReferences();}
    app.querySelector('[data-add-reference]')?.addEventListener('click',()=>{references.push({title:'',type:'',client:'',city:'',country:'Côte d’Ivoire',year:'',description:'',public:false});renderReferences();});

    const qualificationsJson=app.querySelector('[data-qualifications-json]'), qualificationsStatus=app.querySelector('[data-qualifications-status]');
    function syncQualifications(){const rows=[...app.querySelectorAll('[data-qualification-row]')].map(row=>{const out={group:row.dataset.group};row.querySelectorAll('[data-q-field]').forEach(i=>out[i.dataset.qField]=i.value);return out;});if(qualificationsJson)qualificationsJson.value=JSON.stringify(rows.filter(r=>Object.entries(r).some(([k,v])=>k!=='group'&&String(v||'').trim())));}
    app.querySelectorAll('[data-q-field]').forEach(i=>i.addEventListener('input',syncQualifications));

    function initMeansTypes(){const hidden=app.querySelector('[data-means-types-value]'),checks=[...app.querySelectorAll('[data-means-type]')];const update=()=>{checks.forEach(c=>c.closest('.pp-choice')?.classList.toggle('is-selected',c.checked));if(hidden)hidden.value=checks.filter(c=>c.checked).map(c=>c.value).join(', ');};checks.forEach(c=>c.addEventListener('change',update));update();}

    function documentReady(type){const row=app.querySelector(`[data-doc-type="${type}"]`);if(!row)return false;if(row.dataset.docProvided==='1')return true;const file=row.querySelector('input[type=file]');return !!(file?.files?.length);}
    function sectionComplete(key){syncPhones();syncEquipment();syncReferences();syncQualifications();if(key==='verification')return documentReady('professional_documents')&&(app.dataset.entity!=='organization'||documentReady('legal_documents'));if(key==='references')return filled('references_status');if(key==='qualifications')return filled('qualifications_status');if(!(sectionFields[key]||[]).every(filled))return false;if(key==='pricing'&&val('pricing_mode')==='indicative'){return filled('indicative_rate')&&filled('pricing_currency')&&filled('pricing_unit');}if(key==='means'){const arr=JSON.parse(equipmentJson?.value||'[]');return filled('means_types')&&Array.isArray(arr)&&arr.some(r=>String(r.name||'').trim());}return true;}
    function validateSection(key){clearClientErrors();let ok=true;(sectionFields[key]||[]).forEach(name=>{if(!filled(name)){markInvalid(name);ok=false;}});if(key==='company'){const e=input('professional_email');if(e?.value&&!e.checkValidity()){markInvalid('professional_email','Saisissez une adresse e-mail valide.');ok=false;}}if(key==='means'&&!sectionComplete('means')){markInvalid('equipment_json','Ajoutez au moins un moyen ou équipement.');ok=false;}if(key==='pricing'&&val('pricing_mode')==='indicative'){['indicative_rate','pricing_currency','pricing_unit'].forEach(n=>{if(!filled(n)){markInvalid(n);ok=false;}});}if(key==='verification'){['professional_documents'].concat(app.dataset.entity==='organization'?['legal_documents']:[]).forEach(t=>{if(!documentReady(t)){const row=app.querySelector(`[data-doc-type="${t}"]`);row?.classList.add('pp-invalid');ok=false;}});}if(!ok)app.querySelector('.pp-invalid')?.scrollIntoView({behavior:'smooth',block:'center'});return ok;}

    function allowedIndex(){let last=0;for(let i=0;i<keys.length-1;i++){if(sectionComplete(keys[i]))last=i+1;else break;}return last;}
    function updateUI(){const idx=keys.indexOf(active);tabs.forEach((t,i)=>{const key=t.dataset.tabTarget;const done=sectionComplete(key);t.classList.toggle('is-active',key===active);t.classList.toggle('is-locked',i>allowedIndex());let badge=t.querySelector('i');if(done&&!badge){badge=document.createElement('i');badge.textContent='✓';t.appendChild(badge);}if(!done&&badge)badge.remove();});progressItems.forEach((item,i)=>{const key=item.dataset.progressItem,done=sectionComplete(key),cur=key===active;item.classList.toggle('is-done',done);item.classList.toggle('is-active',cur);item.querySelector('em').textContent=done?'Complétée':(cur?'En cours':'À compléter');});if(progressPosition)progressPosition.textContent=idx+1;if(heroImage)heroImage.src=`${window.__PP_INITIAL__.heroBase}/${heroFile[active]}`;if(tipCopy)tipCopy.textContent=tips[active]||'';if(continueBtn)continueBtn.innerHTML=(idx===keys.length-1?'Terminer les informations métier':'Continuer')+' '+iconSvg('arrow');const indicative=app.querySelector('[data-indicative-pricing]');if(indicative)indicative.hidden=val('pricing_mode')!=='indicative';}
    function setActive(key,scroll=true){if(!keys.includes(key))return;active=key;panels.forEach(p=>p.hidden=p.dataset.tabPanel!==key);updateUI();if(scroll)window.scrollTo({top:0,behavior:'smooth'});}

    tabs.forEach((t,i)=>t.addEventListener('click',()=>{if(i<=allowedIndex()||sectionComplete(t.dataset.tabTarget))setActive(t.dataset.tabTarget);else{saveStatus.textContent='Complétez d’abord la section précédente.';setTimeout(()=>saveStatus.textContent='',2200);}}));
    progressItems.forEach((item,i)=>item.addEventListener('click',()=>{if(i<=allowedIndex()||sectionComplete(item.dataset.progressItem))setActive(item.dataset.progressItem);}));

    async function saveDraft(){syncPhones();syncEquipment();syncReferences();syncQualifications();actionField.value='draft';saveBtn.disabled=true;if(saveStatus)saveStatus.textContent='Enregistrement…';try{const data=new FormData(form);const r=await fetch(form.action,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});if(!r.ok)throw new Error('save');if(saveStatus)saveStatus.textContent='Brouillon enregistré';setTimeout(()=>{if(saveStatus)saveStatus.textContent='';},1800);return true;}catch(e){if(saveStatus)saveStatus.textContent='Impossible d’enregistrer le brouillon';return false;}finally{saveBtn.disabled=false;}}
    saveBtn?.addEventListener('click',saveDraft);
    continueBtn?.addEventListener('click',async()=>{if(active==='references'&&!filled('references_status')){referencesStatus.value='reviewed';}if(active==='qualifications'&&!filled('qualifications_status')){qualificationsStatus.value='reviewed';}if(!validateSection(active))return;updateUI();const idx=keys.indexOf(active);if(idx<keys.length-1){const saved=await saveDraft();if(saved)setActive(keys[idx+1]);}else{actionField.value='submit';syncPhones();syncEquipment();syncReferences();syncQualifications();form.submit();}});
    prevBtn?.addEventListener('click',()=>{const idx=keys.indexOf(active);if(idx>0)setActive(keys[idx-1]);else window.location.href=app.dataset.backUrl;});

    const clearFieldError = (target) => {
        const field = target?.closest?.('.pp-field,.pp-choice-grid,.pp-doc-row');
        if (!field) return;
        field.classList.remove('pp-invalid');
        field.querySelectorAll('[data-client-error]').forEach(n => n.remove());
    };
    form.addEventListener('input',(event)=>{clearFieldError(event.target);clearTimeout(form._ppTimer);form._ppTimer=setTimeout(updateUI,120);});
    form.addEventListener('change',(event)=>{clearFieldError(event.target);clearTimeout(form._ppTimer);form._ppTimer=setTimeout(updateUI,80);});

    function attr(v){return escapeHtml(String(v??'')).replace(/"/g,'&quot;');}function escapeHtml(s){return String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}function iconSvg(name){const paths={gear:'<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 1 1-14 0 7 7 0 0 1 14 0z"/>',arrow:'<path d="M5 12h14M14 7l5 5-5 5"/>'};return `<svg class="pp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]||paths.gear}</svg>`;}

    initPhones();initRadioCards();initTokens();initProfessionalSelects();initServiceSelects();initMeansTypes();renderEquipment();renderReferences();syncQualifications();setActive(active,false);
})();
