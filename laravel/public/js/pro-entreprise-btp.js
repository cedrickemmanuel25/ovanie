(() => {
  const root = document.querySelector('[data-eb-page]');
  if (!root) return;
  const form = root.querySelector('[data-eb-form]');
  const cfg = window.OVANIE_PRO_EB || {};
  const order = ['company','activity','purchasing','organisation','functions','verification'];
  const labels = {company:'Entreprise',activity:'Activité BTP',purchasing:'Achats',organisation:'Organisation',functions:'Fonctions',verification:'Vérification'};
  const heroCopies = {
    company:'Donnez de la visibilité à vos projets et trouvez des partenaires fiables en Afrique de l’Ouest.',
    activity:'Accédez à des fournisseurs fiables et développez vos projets de construction en Afrique de l’Ouest.',
    purchasing:'Construisez plus loin avec les bons partenaires en Afrique de l’Ouest.',
    organisation:'Des solutions B2B pour réaliser et piloter vos projets en Afrique de l’Ouest.',
    functions:'Des solutions B2B pour réaliser et piloter vos projets en Afrique de l’Ouest.',
    verification:'Des projets plus ambitieux grâce à un réseau de fournisseurs fiables en Afrique de l’Ouest.'
  };
  const tipCopies = {
    company:'Les informations de votre entreprise permettront aux fournisseurs et partenaires de mieux comprendre vos besoins et de vous proposer des offres adaptées.',
    activity:'Plus vos spécialités et zones d’intervention sont précises, plus vous recevrez des propositions adaptées de fournisseurs et de sous-traitants sur OVANIE PRO.',
    purchasing:'Plus vous précisez vos besoins d’achat, plus nous pourrons vous proposer des fournisseurs pertinents et des conditions adaptées.',
    organisation:'Ces contacts nous aident à mieux comprendre votre organisation afin de vous proposer des fournisseurs et services adaptés.',
    functions:'Vous pouvez sélectionner plusieurs usages selon vos besoins. Les fonctionnalités seront adaptées à votre profil et aux rôles que vous activerez à l’étape suivante.',
    verification:'Vos documents seront vérifiés par notre équipe. Une fois validés, vous pourrez accéder à l’ensemble des fonctionnalités d’OVANIE PRO.'
  };
  const completed = Object.assign({}, cfg.completed || {});
  let current = cfg.firstTab || root.dataset.firstTab || 'company';
  const tabs = [...root.querySelectorAll('[data-tab]')];
  const panels = [...root.querySelectorAll('[data-panel]')];
  const nextBtn = root.querySelector('[data-next]');
  const prevBtn = root.querySelector('[data-prev]');
  const unsaved = root.querySelector('[data-unsaved]');
  const topCount = root.querySelector('[data-top-count]');
  const sideCount = root.querySelector('[data-side-count]');
  const sideProgress = root.querySelector('[data-side-progress]');
  const heroCopy = root.querySelector('[data-hero-copy]');
  const tipCopy = root.querySelector('[data-tip-copy]');

  const textValue = name => {
    const nodes = form.querySelectorAll(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
    if (!nodes.length) return '';
    if ([...nodes].some(n => n.type === 'checkbox' || n.type === 'radio')) return [...nodes].filter(n => n.checked).map(n => n.value).join(',');
    return (nodes[0].value || '').trim();
  };
  const requiredFor = tab => (cfg.required && cfg.required[tab]) || [];
  const isFieldFilled = name => {
    if (name === 'professional_phone') {
      const local = (form.querySelector('[data-phone-local]')?.value || '').replace(/\D/g,'');
      return local.length >= 6;
    }
    return !!textValue(name);
  };
  const providedOrSelectedDoc = type => {
    const input = form.querySelector(`[data-doc-input="${CSS.escape(type)}"]`);
    const status = form.querySelector(`[data-doc-status="${CSS.escape(type)}"]`);
    return !!(input?.files?.length || status?.classList.contains('is-ok'));
  };
  const calcDone = tab => {
    if (tab === 'verification') return requiredFor(tab).every(providedOrSelectedDoc);
    return requiredFor(tab).every(isFieldFilled);
  };
  const firstIncompleteIndex = () => {
    for (let i=0;i<order.length;i++) if (!completed[order[i]]) return i;
    return order.length - 1;
  };
  const canOpen = tab => {
    const idx = order.indexOf(tab);
    if (idx <= firstIncompleteIndex()) return true;
    return order.slice(0,idx).every(k => completed[k]);
  };
  const render = () => {
    tabs.forEach(btn => {
      const key = btn.dataset.tab;
      btn.classList.toggle('is-active', key === current);
      btn.classList.toggle('is-locked', !canOpen(key));
      btn.setAttribute('aria-selected', key === current ? 'true' : 'false');
      const done = btn.querySelector('.eb-tab__done');
      if (done) done.classList.toggle('is-visible', !!completed[key]);
    });
    panels.forEach(p => p.hidden = p.dataset.panel !== current);
    const count = order.filter(k => completed[k]).length;
    if (topCount) topCount.textContent = count;
    if (sideCount) sideCount.textContent = count;
    root.querySelectorAll('[data-top-bars] span').forEach((bar,i)=>bar.classList.toggle('is-done',!!completed[order[i]]));
    if (sideProgress) sideProgress.style.width = `${(count/order.length)*100}%`;
    root.querySelectorAll('[data-side-step]').forEach(li => {
      const key=li.dataset.sideStep; li.classList.toggle('is-done',!!completed[key]); li.classList.toggle('is-current',key===current);
      const em=li.querySelector('em'); if (em) em.textContent = key===current && !completed[key] ? 'En cours' : (completed[key] ? 'Complétée' : 'À compléter');
    });
    if (heroCopy) heroCopy.textContent = heroCopies[current] || heroCopies.company;
    if (tipCopy) tipCopy.textContent = tipCopies[current] || tipCopies.company;
    if (nextBtn) nextBtn.querySelector('span').textContent = current === 'verification' ? 'Terminer les informations métier' : 'Continuer';
    if (prevBtn) prevBtn.disabled = false;
    try { sessionStorage.setItem('ovanie_pro_entreprise_btp_tab', current); } catch(e) {}
  };

  const clearClientErrors = panel => {
    panel.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
    panel.querySelectorAll('.eb-error').forEach(p=>{ if (!p.dataset.serverError) p.textContent=''; });
  };
  const markError = (name, message) => {
    const field = form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
    if (field) {
      const wrap = field.closest('.eb-select,.eb-phone') || field;
      wrap.classList.add('is-invalid');
      field.scrollIntoView({behavior:'smooth',block:'center'});
    }
    const p = form.querySelector(`[data-error-for="${CSS.escape(name)}"]`);
    if (p && !p.textContent.trim()) p.textContent = message;
  };
  const validateCurrent = () => {
    const panel = form.querySelector(`[data-panel="${current}"]`);
    clearClientErrors(panel);
    const missing=[];
    if (current === 'organisation') {
      const later = form.querySelector('[data-collaborators-later]')?.checked;
      const anyContact = ['purchasing_manager_first_name','purchasing_manager_last_name','purchasing_manager_email','site_manager_first_name','site_manager_last_name','site_manager_email'].some(isFieldFilled);
      form.querySelector('[data-org-completed]').value = (later || anyContact) ? 'yes' : 'yes';
    }
    requiredFor(current).forEach(name => {
      const ok = current === 'verification' ? providedOrSelectedDoc(name) : isFieldFilled(name);
      if (!ok) missing.push(name);
    });
    if (missing.length) {
      missing.forEach(name => {
        const label = name === 'verification' ? 'document' : 'champ';
        markError(current === 'verification' ? `documents.${name}` : name, current === 'verification' ? 'Ce document est nécessaire pour continuer.' : 'Ce champ est nécessaire pour continuer.');
      });
      return false;
    }
    completed[current] = true;
    return true;
  };

  tabs.forEach(btn => btn.addEventListener('click', () => {
    const key = btn.dataset.tab;
    if (!canOpen(key)) return;
    const targetIndex = order.indexOf(key), currentIndex = order.indexOf(current);
    if (targetIndex > currentIndex && !validateCurrent()) return;
    current = key; render(); window.scrollTo({top:0,behavior:'smooth'});
  }));
  nextBtn?.addEventListener('click', () => {
    if (!validateCurrent()) return;
    const idx = order.indexOf(current);
    if (idx < order.length-1) { current=order[idx+1]; render(); window.scrollTo({top:0,behavior:'smooth'}); return; }
    const action = document.createElement('input'); action.type='hidden'; action.name='action'; action.value='submit'; form.appendChild(action); form.submit();
  });
  prevBtn?.addEventListener('click', () => {
    const idx = order.indexOf(current);
    if (idx > 0) {
      current = order[idx - 1];
      render();
      window.scrollTo({top:0,behavior:'smooth'});
      return;
    }
    window.location.assign(cfg.backUrl || '/compte/qualification');
  });

  form.addEventListener('input', () => { if (unsaved) unsaved.textContent='Modifications non enregistrées'; completed[current]=calcDone(current); render(); });
  form.addEventListener('change', () => { completed[current]=calcDone(current); render(); });

  root.querySelectorAll('.eb-specialty,.eb-usage-card').forEach(card => {
    const input=card.querySelector('input'); if(!input)return;
    input.addEventListener('change',()=>card.classList.toggle('is-selected',input.checked));
  });

  root.querySelectorAll('[data-tagpicker]').forEach(picker => {
    const name=picker.dataset.name; const hidden=form.querySelector(`input[type="hidden"][name="${CSS.escape(name)}"]`);
    let options=[]; let selected=[];
    try{options=JSON.parse(picker.dataset.options||'[]');selected=JSON.parse(picker.dataset.selected||'[]');}catch(e){}
    const sync=()=>{if(hidden)hidden.value=selected.join(', ');};
    const paint=()=>{
      picker.querySelectorAll('.eb-tag').forEach(n=>n.remove());
      selected.forEach(value=>{
        const tag=document.createElement('span'); tag.className='eb-tag';
        const metaFlag = {'Côte d’Ivoire':'ci','Sénégal':'sn','Mali':'ml','Burkina Faso':'bf','Ghana':'gh','Guinée':'gn','Togo':'tg','Bénin':'bj','Niger':'ne','Cameroun':'cm','Nigeria':'ng','Maroc':'ma','France':'fr','Chine':'cn','Turquie':'tr','Émirats arabes unis':'ae','Inde':'in'}[value];
        if(metaFlag){const img=document.createElement('img');img.src=`${cfg.flagsBase}/${metaFlag}.svg`;img.alt='';tag.appendChild(img);}
        const txt=document.createTextNode(value);tag.appendChild(txt);const x=document.createElement('button');x.type='button';x.textContent='×';x.addEventListener('click',()=>{selected=selected.filter(v=>v!==value);sync();paint();form.dispatchEvent(new Event('change',{bubbles:true}));});tag.appendChild(x);picker.insertBefore(tag,picker.querySelector('.eb-tagpicker__add'));
      });
    };
    picker.querySelector('.eb-tagpicker__add')?.addEventListener('click',()=>{
      picker.querySelector('.eb-tagpicker__menu')?.remove();
      const menu=document.createElement('div');menu.className='eb-tagpicker__menu';
      options.filter(o=>!selected.includes(o)).forEach(o=>{const b=document.createElement('button');b.type='button';b.textContent=o;b.addEventListener('click',()=>{selected.push(o);sync();paint();menu.remove();form.dispatchEvent(new Event('change',{bubbles:true}));});menu.appendChild(b);});
      picker.appendChild(menu); const close=e=>{if(!picker.contains(e.target)){menu.remove();document.removeEventListener('click',close);}}; setTimeout(()=>document.addEventListener('click',close),0);
    });
    sync();paint();
  });

  const syncPhone = (prefix) => {
    const code=form.querySelector(`[data-${prefix}-code]`), local=form.querySelector(`[data-${prefix}-local]`), hidden=form.querySelector(`[data-${prefix}-hidden]`);
    if(!code||!local||!hidden)return;
    const doSync=()=>{hidden.value=`${code.value}${local.value.replace(/^0+/, '').replace(/\s+/g,'')}`; hidden.dispatchEvent(new Event('input',{bubbles:true})); const flag=code.options[code.selectedIndex]?.dataset.flag; const slot=code.closest('.eb-phone__country')?.querySelector('.eb-flag-slot img'); if(slot&&flag)slot.src=`${cfg.flagsBase}/${flag}.svg`;};
    code.addEventListener('change',doSync);local.addEventListener('input',doSync);doSync();
  };
  syncPhone('phone');syncPhone('whatsapp');
  form.querySelector('[data-same-phone]')?.addEventListener('change',e=>{if(!e.target.checked)return; const pc=form.querySelector('[data-phone-code]'),pl=form.querySelector('[data-phone-local]'),wc=form.querySelector('[data-whatsapp-code]'),wl=form.querySelector('[data-whatsapp-local]'); if(pc&&wc)wc.value=pc.value;if(pl&&wl)wl.value=pl.value;wc?.dispatchEvent(new Event('change',{bubbles:true}));wl?.dispatchEvent(new Event('input',{bubbles:true}));});
  form.querySelector('[data-collaborators-later]')?.addEventListener('change',e=>{if(e.target.checked)form.querySelector('[data-org-completed]').value='yes';});

  root.querySelectorAll('[data-doc-input]').forEach(input=>input.addEventListener('change',()=>{const type=input.dataset.docInput,status=form.querySelector(`[data-doc-status="${CSS.escape(type)}"]`),label=input.closest('.eb-upload')?.querySelector('strong');if(input.files?.length){if(label)label.textContent=input.files[0].name;if(status){status.textContent='✓ Prêt à envoyer';status.classList.add('is-ok');}completed.verification=calcDone('verification');render();}}));

  const regCountry=form.querySelector('[data-registration-country]');
  regCountry?.addEventListener('change',()=>{const meta={'Côte d’Ivoire':'ci','Sénégal':'sn','Mali':'ml','Burkina Faso':'bf','Ghana':'gh','Guinée':'gn','Togo':'tg','Bénin':'bj','Niger':'ne','Cameroun':'cm','Nigeria':'ng','Maroc':'ma','France':'fr','Chine':'cn','Turquie':'tr','Émirats arabes unis':'ae','Inde':'in'}[regCountry.value];const img=form.querySelector('[data-registration-flag] img');if(img&&meta)img.src=`${cfg.flagsBase}/${meta}.svg`;});

  try{const stored=sessionStorage.getItem('ovanie_pro_entreprise_btp_tab');if(stored&&order.includes(stored)&&canOpen(stored)&&!document.querySelector('.eb-error:not(:empty)'))current=stored;}catch(e){}
  completed[current]=calcDone(current) || completed[current]; render();
})();
