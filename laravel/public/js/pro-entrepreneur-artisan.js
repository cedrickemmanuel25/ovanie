(() => {
  const root = document.querySelector('[data-artisan-page]');
  if (!root) return;

  const form = document.getElementById('ea-form');
  const config = window.OVANIE_ARTISAN || {};
  const steps = ['identity', 'trade', 'intervention', 'references', 'qualifications', 'verification'];
  const state = Object.assign({identity:false,trade:false,intervention:false,references:false,qualifications:false,verification:false}, config.done || {});
  let current = root.dataset.firstTab && steps.includes(root.dataset.firstTab) ? root.dataset.firstTab : 'identity';
  let dirty = false;

  const qs = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const panel = key => qs(`[data-tab-panel="${key}"]`, root);
  const indexOf = key => steps.indexOf(key);

  function setDirty(value = true) {
    dirty = value;
    const label = qs('[data-unsaved]', root);
    if (label) label.textContent = dirty ? 'Modifications non enregistrées' : 'Brouillon enregistré';
  }

  function syncPhones() {
    const phoneCode = qs('[data-phone-code]', root)?.value || '';
    const phoneLocal = qs('[data-phone-local]', root)?.value || '';
    const phoneFull = qs('[data-phone-full]', root);
    if (phoneFull) phoneFull.value = `${phoneCode}${phoneLocal.replace(/^\s+|\s+$/g,'').replace(/^0+(?=\d)/,'')}`;

    const waCode = qs('[data-wa-code]', root)?.value || '';
    const waLocal = qs('[data-wa-local]', root)?.value || '';
    const waFull = qs('[data-wa-full]', root);
    if (waFull) waFull.value = waLocal.trim() ? `${waCode}${waLocal.trim().replace(/^0+(?=\d)/,'')}` : '';
  }

  function syncEntity() {
    const selected = qs('input[name="entity_visual"]:checked', root)?.value || root.dataset.currentEntity || 'independent';
    const hidden = qs('[data-entity-input]', root);
    if (hidden) hidden.value = selected;
    const independentFields = qs('[data-independent-fields]', root);
    const organizationFields = qs('[data-organization-fields]', root);
    if (independentFields) independentFields.hidden = selected !== 'independent';
    if (organizationFields) organizationFields.hidden = selected !== 'organization';

    const tradeName = qs('input[name="trade_name"]', root);
    const orgTradeName = qs('[data-mirror-trade-name]', root);
    if (selected === 'organization' && orgTradeName && tradeName) tradeName.value = orgTradeName.value;
    return selected;
  }

  function filled(el) {
    if (!el) return false;
    if (el.type === 'radio' || el.type === 'checkbox') return el.checked;
    return String(el.value || '').trim() !== '';
  }

  function groupChecked(name) {
    return qsa(`[name="${CSS.escape(name)}"]`, form).some(el => el.checked);
  }

  function arrayChecked(name) {
    return qsa(`[name="${CSS.escape(name)}[]"]`, form).some(el => el.checked);
  }

  function clearClientErrors(key) {
    qsa('.is-client-error', panel(key)).forEach(el => el.classList.remove('is-client-error'));
    qsa('.ea-client-error', panel(key)).forEach(el => el.remove());
  }

  function flag(el, message) {
    if (!el) return;
    const holder = el.closest('.ea-field,.ea-choice-grid,.ea-sectionlabel,.ea-tokenfield,.ea-radio-grid,.ea-check-grid,.ea-verification-row') || el.parentElement;
    holder?.classList.add('is-client-error');
    if (holder && !qs('.ea-client-error', holder)) {
      const error = document.createElement('small');
      error.className = 'ea-error ea-client-error';
      error.textContent = message || 'Ce champ est nécessaire pour continuer.';
      holder.appendChild(error);
    }
  }

  function validateIdentity(showErrors = false) {
    const entity = syncEntity();
    syncPhones();
    const required = entity === 'organization'
      ? ['legal_name','legal_form','registration_country','city','professional_phone','professional_email']
      : ['independent_status','registration_country','city','professional_phone','professional_email'];
    let ok = true;
    if (showErrors) clearClientErrors('identity');
    required.forEach(name => {
      const el = qs(`[name="${name}"]`, form);
      if (!filled(el)) { ok = false; if (showErrors) flag(el); }
    });
    return ok;
  }

  function validateTrade(showErrors = false) {
    if (showErrors) clearClientErrors('trade');
    let ok = true;
    const main = qs('[name="main_trade"]', form);
    if (!filled(main)) { ok=false; if (showErrors) flag(main); }
    if (!arrayChecked('specialties')) { ok=false; if (showErrors) flag(qs('[name="specialties[]"]', form)); }
    if (!groupChecked('experience_level')) { ok=false; if (showErrors) flag(qs('[name="experience_level"]', form)); }
    if (!arrayChecked('services')) { ok=false; if (showErrors) flag(qs('[name="services[]"]', form)); }
    return ok;
  }

  function validateIntervention(showErrors = false) {
    if (showErrors) clearClientErrors('intervention');
    let ok = true;
    const zones = qs('[name="intervention_zones"]', form);
    if (!filled(zones)) { ok=false; if (showErrors) flag(zones?.closest('.ea-tokenfield') || zones); }
    ['availability','team_size','intervention_capacity'].forEach(name => {
      if (!groupChecked(name)) { ok=false; if (showErrors) flag(qs(`[name="${name}"]`, form)); }
    });
    return ok;
  }

  function requiredVerificationRows() {
    return ['representative_id','professional_document','activity_proof'].map(type => qs(`#v_${type}`, form)).filter(Boolean);
  }

  function validateVerification(showErrors = false) {
    if (showErrors) clearClientErrors('verification');
    let ok = true;
    requiredVerificationRows().forEach(input => {
      const row = input.closest('.ea-verification-row');
      const already = !!qs('.ea-status--ok', row);
      const selected = input.files && input.files.length > 0;
      if (!already && !selected) { ok=false; if (showErrors) flag(row, 'Ajoutez ce document requis pour terminer votre profil.'); }
    });
    return ok;
  }

  function validateStep(key, showErrors = false) {
    if (key === 'identity') return validateIdentity(showErrors);
    if (key === 'trade') return validateTrade(showErrors);
    if (key === 'intervention') return validateIntervention(showErrors);
    if (key === 'references') return qs('[data-reference-completed]', form)?.value === '1';
    if (key === 'qualifications') return qs('[data-qualifications-completed]', form)?.value === '1';
    if (key === 'verification') return validateVerification(showErrors);
    return true;
  }

  function accessible(target) {
    const idx = indexOf(target);
    if (idx <= indexOf(current)) return true;
    for (let i = 0; i < idx; i++) if (!state[steps[i]]) return false;
    return true;
  }

  function updateProgress() {
    const count = steps.filter(key => !!state[key]).length;
    const countTop = qs('[data-completed-count]', root);
    if (countTop) countTop.textContent = count;
    const countSide = qs('[data-sidebar-count]', root);
    if (countSide) countSide.textContent = count;

    qsa('.ea-mini-progress i', root).forEach((bar, i) => bar.classList.toggle('is-done', !!state[steps[i]]));
    steps.forEach((key, i) => {
      const tab = qs(`[data-tab="${key}"]`, root);
      if (tab) {
        const dot = qs('[data-tab-dot]', tab);
        tab.classList.toggle('is-active', key === current);
        tab.classList.toggle('is-done', !!state[key] && key !== current);
        tab.setAttribute('aria-disabled', accessible(key) ? 'false' : 'true');
        if (dot) dot.textContent = state[key] ? '✓' : String(i + 1);
      }
      const p = qs(`[data-progress-step="${key}"]`, root);
      if (p) {
        p.classList.toggle('is-current', key === current);
        p.classList.toggle('is-done', !!state[key] && key !== current);
        p.setAttribute('aria-disabled', accessible(key) ? 'false' : 'true');
        const n = qs('.ea-progress-number', p);
        const e = qs('em', p);
        if (n) n.textContent = state[key] ? '✓' : String(i + 1);
        if (e) e.textContent = state[key] ? 'Complétée' : (key === current ? 'En cours' : 'À compléter');
      }
    });
  }

  function updateHero() {
    const hero = config.hero?.[current];
    if (!hero) return;
    const copy = qs('[data-hero-copy]', root);
    const tip = qs('[data-tip-copy]', root);
    if (copy) copy.textContent = hero.copy;
    if (tip) tip.textContent = hero.tip;
  }

  function updateActions() {
    const idx = indexOf(current);
    const next = qs('[data-next]', root);
    const submit = qs('[data-submit]', root);
    const skip = qs('[data-skip]', root);
    if (next) next.hidden = idx === steps.length - 1;
    if (submit) submit.hidden = idx !== steps.length - 1;
    if (skip) skip.hidden = !['references','qualifications'].includes(current);
  }

  function showStep(key, {scroll = true} = {}) {
    if (!steps.includes(key) || !accessible(key)) return;
    current = key;
    steps.forEach(step => {
      const section = panel(step);
      if (section) section.hidden = step !== key;
    });
    updateProgress();
    updateHero();
    updateActions();
    if (scroll) {
      const nav = qs('.ea-subnav', root);
      const y = nav ? nav.getBoundingClientRect().top + window.scrollY - 78 : 0;
      window.scrollTo({top: Math.max(0,y), behavior:'smooth'});
    }
  }

  function completeAndNext() {
    if (current === 'references') {
      const input = qs('[data-reference-completed]', form);
      if (input) input.value = '1';
    }
    if (current === 'qualifications') {
      const input = qs('[data-qualifications-completed]', form);
      if (input) input.value = '1';
    }
    if (!validateStep(current, true)) {
      const error = qs('.is-client-error', panel(current));
      error?.scrollIntoView({behavior:'smooth',block:'center'});
      return;
    }
    state[current] = true;
    const idx = indexOf(current);
    if (idx < steps.length - 1) showStep(steps[idx+1]);
    else updateProgress();
  }

  function back() {
    const idx = indexOf(current);
    if (idx === 0) {
      window.location.href = config.qualificationUrl || '/compte/qualification';
      return;
    }
    showStep(steps[idx-1]);
  }

  function skipCurrent() {
    if (current === 'references') {
      const input = qs('[data-reference-completed]', form);
      if (input) input.value = '1';
      state.references = true;
    }
    if (current === 'qualifications') {
      const input = qs('[data-qualifications-completed]', form);
      if (input) input.value = '1';
      state.qualifications = true;
    }
    setDirty(true);
    showStep(steps[indexOf(current)+1]);
  }

  function initTokenFields() {
    qsa('[data-token-field]', root).forEach(widget => {
      const hidden = qs('input[type="hidden"]', widget);
      const menu = qs('.ea-tokenfield__menu', widget);
      const chips = qs('.ea-chips', widget);
      const button = qs('.ea-tokenfield__button', widget);
      const options = String(widget.dataset.options || '').split('|').map(x=>x.trim()).filter(Boolean);
      let values = String(hidden?.value || '').split(',').map(x=>x.trim()).filter(Boolean);

      const sync = () => {
        if (hidden) hidden.value = values.join(', ');
        chips.innerHTML = '';
        values.forEach(value => {
          const chip = document.createElement('span');
          chip.className = 'ea-chip';
          chip.innerHTML = `<span>${value}</span><button type="button" aria-label="Supprimer ${value}">×</button>`;
          qs('button', chip).addEventListener('click', () => { values = values.filter(x=>x!==value); sync(); setDirty(); });
          chips.appendChild(chip);
        });
        menu.innerHTML = '';
        options.filter(x=>!values.includes(x)).forEach(option => {
          const item = document.createElement('button');
          item.type = 'button';
          item.textContent = option;
          item.addEventListener('click', () => { values.push(option); sync(); widget.classList.remove('is-open'); setDirty(); });
          menu.appendChild(item);
        });
      };
      button?.addEventListener('click', () => widget.classList.toggle('is-open'));
      document.addEventListener('click', e => { if (!widget.contains(e.target)) widget.classList.remove('is-open'); });
      sync();
    });
  }

  function initReferences() {
    const hidden = qs('[data-references-json]', form);
    const list = qs('[data-reference-list]', form);
    if (!hidden || !list) return;
    let rows = Array.isArray(config.references) ? config.references.slice() : [];
    const fields = {
      title: qs('[data-ref-title]', form), type: qs('[data-ref-type]', form), location: qs('[data-ref-location]', form),
      year: qs('[data-ref-year]', form), description: qs('[data-ref-description]', form), client: qs('[data-ref-client]', form)
    };
    const sync = () => {
      hidden.value = JSON.stringify(rows);
      list.innerHTML = '';
      rows.forEach((row, index) => {
        const card = document.createElement('article');
        card.className = 'ea-reference-card';
        card.innerHTML = `<button type="button" aria-label="Supprimer">${'<svg class="ea-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14"/></svg>'}</button><strong>${escapeHtml(row.title || 'Réalisation')}</strong><small>${escapeHtml([row.type,row.location,row.year].filter(Boolean).join(' · '))}</small><small>${escapeHtml(row.description || '')}</small>${row.client ? `<small>Client : ${escapeHtml(row.client)}</small>` : ''}`;
        qs('button', card).addEventListener('click',()=>{rows.splice(index,1);sync();setDirty();});
        list.appendChild(card);
      });
    };
    qs('[data-add-reference]', form)?.addEventListener('click', () => {
      const row = Object.fromEntries(Object.entries(fields).map(([k,el])=>[k, String(el?.value || '').trim()]));
      if (!row.title || !row.type || !row.location || !row.year || !row.description) {
        Object.entries(fields).forEach(([k,el]) => { if (['title','type','location','year','description'].includes(k) && !String(el?.value||'').trim()) flag(el, 'Complétez ce champ avant d’ajouter la réalisation.'); });
        return;
      }
      rows.push(row);
      Object.values(fields).forEach(el => { if (el) el.value = ''; });
      sync(); setDirty();
    });
    sync();
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  }

  function initFileStates() {
    qsa('input[type="file"]', form).forEach(input => {
      input.addEventListener('change', () => {
        setDirty();
        const row = input.closest('.ea-verification-row');
        if (row) {
          const box = qs('[data-file-state]', row);
          if (box && input.files?.length) {
            const names = Array.from(input.files).map(f=>f.name).join(', ');
            box.innerHTML = `<span class="ea-file-state__file">${escapeHtml(names)}</span><span class="ea-status ea-status--ok">✓ Prêt à téléverser</span>`;
          }
        }
        const qrow = input.closest('.ea-qualification-row');
        if (qrow) {
          const count = qs('.ea-doc-count', qrow);
          if (count && input.files?.length) { count.textContent = `${input.files.length} document${input.files.length>1?'s':''}`; count.classList.add('has-files'); }
        }
      });
    });
  }

  qsa('[data-tab]', root).forEach(btn => btn.addEventListener('click', () => {
    const key = btn.dataset.tab;
    if (accessible(key)) showStep(key);
  }));
  qsa('[data-progress-step]', root).forEach(btn => btn.addEventListener('click', () => {
    const key = btn.dataset.progressStep;
    if (accessible(key)) showStep(key);
  }));
  qs('[data-next]', root)?.addEventListener('click', completeAndNext);
  qs('[data-back]', root)?.addEventListener('click', back);
  qs('[data-skip]', root)?.addEventListener('click', skipCurrent);

  qsa('input[name="entity_visual"]', root).forEach(radio => radio.addEventListener('change', () => { syncEntity(); state.identity = validateIdentity(false); updateProgress(); setDirty(); }));
  qs('[data-mirror-trade-name]', root)?.addEventListener('input', e => { const main=qs('input[name="trade_name"]',root); if(main) main.value=e.target.value; });
  ['[data-phone-code]','[data-phone-local]','[data-wa-code]','[data-wa-local]'].forEach(sel => qs(sel,root)?.addEventListener('input',()=>{syncPhones();setDirty();}));

  form?.addEventListener('input', e => {
    if (e.target.matches('input[type="file"]')) return;
    setDirty();
    if (['identity','trade','intervention'].includes(current)) {
      state[current] = validateStep(current, false);
      updateProgress();
    }
  });
  form?.addEventListener('change', e => {
    if (e.target.matches('select,input[type="checkbox"],input[type="radio"]')) {
      setDirty();
      if (['identity','trade','intervention'].includes(current)) {
        state[current] = validateStep(current, false);
        updateProgress();
      }
    }
  });

  form?.addEventListener('submit', e => {
    syncPhones();
    syncEntity();
    const submitter = e.submitter;
    if (submitter?.value === 'submit') {
      if (!steps.slice(0,5).every(step => state[step])) {
        e.preventDefault();
        const missing = steps.slice(0,5).find(step=>!state[step]);
        showStep(missing || 'identity');
        validateStep(missing || 'identity', true);
        return;
      }
      if (!validateVerification(true)) {
        e.preventDefault();
        state.verification = false;
        updateProgress();
        return;
      }
      state.verification = true;
    }
  });

  initTokenFields();
  initReferences();
  initFileStates();
  syncEntity();
  syncPhones();
  showStep(current, {scroll:false});
})();
