(() => {
  'use strict';
  const root = document.querySelector('[data-pi-page]');
  if (!root) return;

  const form = root.querySelector('[data-pi-form]');
  const tabs = [...root.querySelectorAll('[data-tab]')];
  const panels = [...root.querySelectorAll('[data-panel]')];
  const order = tabs.map(t => t.dataset.tab);
  const nextBtn = root.querySelector('[data-next]');
  const submitBtn = root.querySelector('[data-submit]');
  const prevBtn = root.querySelector('[data-prev]');
  const saveState = root.querySelector('[data-save-state]');
  const backUrl = root.dataset.backUrl;
  let active = root.dataset.firstTab || order[0];

  const heroCopy = {
    company: {
      title: 'Structurez vos projets, développez vos programmes et gagnez en crédibilité sur OVANIE PRO.',
      tip: 'Un profil complet et des informations vérifiées renforcent la confiance des partenaires et facilitent l’accès aux opportunités de projets immobiliers sur OVANIE PRO.',
      benefits: ['Présentez vos projets immobiliers','Trouvez des partenaires et fournisseurs','Gérez vos achats et consultations']
    },
    activity: {
      title: 'Structurez vos projets, développez vos programmes et gagnez en crédibilité sur OVANIE PRO.',
      tip: 'Une description claire de vos activités aide les partenaires et fournisseurs à mieux comprendre votre positionnement et à vous proposer des opportunités pertinentes.',
      benefits: ['Présentez vos projets immobiliers','Trouvez des partenaires et fournisseurs','Gérez vos achats et consultations']
    },
    projects: {
      title: 'Structurez vos projets, développez vos programmes et gagnez en crédibilité sur OVANIE PRO.',
      tip: 'Les informations sur vos projets permettent aux partenaires et fournisseurs de mieux comprendre votre positionnement et d’identifier des opportunités pertinentes.',
      benefits: ['Présentez vos projets immobiliers','Trouvez des partenaires et fournisseurs','Gérez vos achats et consultations']
    },
    purchasing: {
      title: 'Structurez vos projets, développez vos programmes et gagnez en crédibilité sur OVANIE PRO.',
      tip: 'Des informations précises sur vos achats nous permettent de recommander des fournisseurs et partenaires plus pertinents, locaux ou internationaux, et de vous proposer des opportunités adaptées à vos besoins.',
      benefits: ['Présentez vos projets immobiliers','Trouvez des partenaires et fournisseurs','Gérez vos achats et consultations']
    },
    verification: {
      title: 'Donnez vie à vos projets immobiliers en Afrique de l’Ouest.',
      tip: 'La vérification de votre profil renforce votre crédibilité auprès des partenaires, investisseurs, banques et prestataires du BTP.',
      benefits: ['Trouvez des partenaires et prestataires qualifiés','Recevez des offres adaptées à vos projets','Développez votre réseau dans le secteur immobilier']
    }
  };

  function setActive(key, scroll = false) {
    if (!order.includes(key)) return;
    active = key;
    tabs.forEach(tab => {
      const on = tab.dataset.tab === key;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(panel => panel.hidden = panel.dataset.panel !== key);
    root.querySelectorAll('[data-progress-item]').forEach(item => item.classList.toggle('is-active', item.dataset.progressItem === key));
    nextBtn.hidden = key === order[order.length - 1];
    submitBtn.hidden = key !== order[order.length - 1];
    updateHero(key);
    updateProgress();
    updateLocks();
    if (scroll) root.querySelector('.pi-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function updateHero(key) {
    const copy = heroCopy[key] || heroCopy.company;
    const heading = root.querySelector('[data-hero-heading]');
    const title = root.querySelector('[data-hero-title]');
    const tip = root.querySelector('[data-tip]');
    if (heading) heading.innerHTML = key === 'verification' ? 'Promoteur<br><span>Immobilier</span>' : 'Promoteur / Immobilier';
    if (title) title.textContent = copy.title;
    if (tip) tip.textContent = copy.tip;
    copy.benefits.forEach((txt, i) => {
      const el = root.querySelector(`[data-benefit-${i+1}]`);
      if (el) el.textContent = txt;
    });
    const img = root.querySelector('[data-hero-img]');
    if (img) img.src = key === 'verification' ? img.dataset.verificationSrc : img.dataset.defaultSrc;
  }

  function standardRequiredComplete(panel) {
    let ok = true;
    panel.querySelectorAll('[data-required]').forEach(el => {
      const value = String(el.value || '').trim();
      const field = el.closest('.pi-field') || el.closest('.pi-token') || el.parentElement;
      field?.classList.remove('is-invalid');
      if (!value) {
        ok = false;
        field?.classList.add('is-invalid');
      }
    });
    panel.querySelectorAll('[data-required-group]').forEach(group => {
      const name = group.dataset.requiredGroup;
      const checked = group.querySelector(`input[name="${CSS.escape(name)}"], input[name="${CSS.escape(name)}[]"]`)
        ? [...group.querySelectorAll(`input[name="${CSS.escape(name)}"], input[name="${CSS.escape(name)}[]"]`)].some(i => i.checked)
        : true;
      group.classList.remove('is-invalid');
      if (!checked) { group.classList.add('is-invalid'); ok = false; }
    });
    return ok;
  }

  function verificationComplete(showErrors = false) {
    let ok = true;
    root.querySelectorAll('[data-doc-row][data-required-doc="1"]').forEach(row => {
      const provided = row.dataset.provided === '1';
      const input = row.querySelector('input[type=file]');
      const selected = input && input.files && input.files.length > 0;
      row.classList.remove('is-invalid');
      if (!provided && !selected) {
        ok = false;
        if (showErrors) row.classList.add('is-invalid');
      }
    });
    return ok;
  }

  function isComplete(key, showErrors = false) {
    const panel = root.querySelector(`[data-panel="${key}"]`);
    if (!panel) return false;
    if (key === 'verification') return verificationComplete(showErrors);
    const ok = standardRequiredComplete(panel);
    if (!showErrors) panel.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    return ok;
  }

  function currentIndex() { return Math.max(0, order.indexOf(active)); }

  function updateLocks() {
    let previousComplete = true;
    tabs.forEach((tab, index) => {
      const key = tab.dataset.tab;
      const activeIndex = currentIndex();
      const permitted = index <= activeIndex || previousComplete;
      tab.setAttribute('aria-disabled', permitted ? 'false' : 'true');
      tab.disabled = !permitted;
      previousComplete = previousComplete && isComplete(key, false);
    });
  }

  function updateProgress() {
    const completed = order.filter(k => isComplete(k, false));
    const count = completed.length;
    root.querySelectorAll('[data-progress-item]').forEach(item => {
      const key = item.dataset.progressItem;
      const done = completed.includes(key);
      item.classList.toggle('is-done', done);
      item.classList.toggle('is-active', key === active);
      item.classList.toggle('is-locked', tabs.find(t => t.dataset.tab === key)?.disabled || false);
      const status = item.querySelector('em');
      const num = item.querySelector('.pi-progress-num');
      if (status) status.textContent = done ? 'Complétée' : (key === active ? 'En cours' : 'À compléter');
      if (num && done) num.textContent = '✓';
      else if (num) num.textContent = String(order.indexOf(key)+1);
    });
    tabs.forEach(tab => tab.classList.toggle('is-done', completed.includes(tab.dataset.tab)));
    root.querySelectorAll('[data-mini-segment]').forEach(seg => seg.classList.toggle('is-done', completed.includes(seg.dataset.miniSegment)));
    const mini = root.querySelector('[data-mini-copy]');
    if (mini) mini.textContent = `${count} section${count>1?'s':''} complétée${count>1?'s':''} sur 5`;
    const countEl = root.querySelector('[data-progress-count]');
    if (countEl) countEl.textContent = String(count);
    const bar = root.querySelector('[data-progress-bar]');
    if (bar) bar.style.width = `${count*20}%`;
  }

  tabs.forEach(tab => tab.addEventListener('click', () => {
    if (tab.disabled || tab.getAttribute('aria-disabled') === 'true') return;
    const targetIndex = order.indexOf(tab.dataset.tab);
    const activeIndex = currentIndex();
    if (targetIndex > activeIndex && !isComplete(active, true)) {
      root.querySelector(`[data-panel="${active}"] .is-invalid`)?.scrollIntoView({behavior:'smooth', block:'center'});
      return;
    }
    setActive(tab.dataset.tab, false);
  }));

  nextBtn?.addEventListener('click', () => {
    if (!isComplete(active, true)) {
      root.querySelector(`[data-panel="${active}"] .is-invalid`)?.scrollIntoView({behavior:'smooth', block:'center'});
      return;
    }
    const idx = currentIndex();
    if (idx < order.length - 1) setActive(order[idx+1], true);
  });

  prevBtn?.addEventListener('click', () => {
    const idx = currentIndex();
    if (idx > 0) setActive(order[idx-1], true);
    else if (backUrl) window.location.href = backUrl;
  });

  form?.addEventListener('input', () => {
    if (saveState) saveState.textContent = 'Modifications non enregistrées';
    updateProgress();
    updateLocks();
  });
  form?.addEventListener('change', () => {
    if (saveState) saveState.textContent = 'Modifications non enregistrées';
    updateProgress();
    updateLocks();
  });

  form?.addEventListener('submit', e => {
    const submitter = e.submitter;
    if (submitter && submitter.value === 'submit') {
      let firstBad = null;
      for (const key of order) {
        if (!isComplete(key, true)) { firstBad = key; break; }
      }
      if (firstBad) {
        e.preventDefault();
        setActive(firstBad, true);
        setTimeout(() => root.querySelector(`[data-panel="${firstBad}"] .is-invalid`)?.scrollIntoView({behavior:'smooth',block:'center'}), 50);
      }
    }
  });

  // Professional token fields: hidden CSV value + chips + keyboard entry.
  root.querySelectorAll('[data-token-field]').forEach(field => {
    const hidden = field.querySelector('input[type=hidden]');
    const chips = field.querySelector('.pi-token__chips');
    const input = field.querySelector('[data-token-input]');
    if (!hidden || !chips || !input) return;
    let values = String(hidden.value || '').split(',').map(v => v.trim()).filter(Boolean);
    const sync = () => {
      hidden.value = values.join(', ');
      chips.innerHTML = '';
      values.forEach((value, index) => {
        const chip = document.createElement('span');
        chip.className = 'pi-token__chip';
        chip.innerHTML = `<span></span><button type="button" aria-label="Supprimer">×</button>`;
        chip.querySelector('span').textContent = value;
        chip.querySelector('button').addEventListener('click', () => { values.splice(index,1); sync(); hidden.dispatchEvent(new Event('input',{bubbles:true})); });
        chips.appendChild(chip);
      });
    };
    const add = raw => {
      const parts = String(raw || '').split(/[,;]+/).map(v => v.trim()).filter(Boolean);
      parts.forEach(v => { if (!values.some(x => x.toLowerCase() === v.toLowerCase())) values.push(v); });
      input.value = '';
      sync();
      hidden.dispatchEvent(new Event('input',{bubbles:true}));
    };
    input.addEventListener('keydown', e => {
      if ((e.key === 'Enter' || e.key === ',') && input.value.trim()) { e.preventDefault(); add(input.value); }
      if (e.key === 'Backspace' && !input.value && values.length) { values.pop(); sync(); hidden.dispatchEvent(new Event('input',{bubbles:true})); }
    });
    input.addEventListener('blur', () => { if (input.value.trim()) add(input.value); });
    sync();
  });

  // Project cards backed by current_projects JSON.
  const projectsField = root.querySelector('[data-projects-json]');
  const projectList = root.querySelector('[data-project-list]');
  const projectEmpty = root.querySelector('[data-project-empty]');
  const modal = root.querySelector('[data-project-modal]');
  let projects = [];
  if (projectsField) {
    try { const parsed = JSON.parse(projectsField.value || '[]'); if (Array.isArray(parsed)) projects = parsed.filter(p => p && typeof p === 'object'); } catch (_) {}
  }
  const renderProjects = () => {
    if (!projectList || !projectsField) return;
    projectList.innerHTML = '';
    projects.forEach((p, index) => {
      const card = document.createElement('article');
      card.className = 'pi-project';
      card.innerHTML = `<div class="pi-project__visual"><svg class="pi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 11l9-8 9 8v10H3z"/><path d="M9 21v-6h6v6"/></svg></div><div class="pi-project__body"><strong></strong><small></small><div class="pi-project__tags"><span></span><span></span></div><button type="button" class="pi-btn" style="height:26px;margin-top:6px;padding:0 8px;font-size:9px">Supprimer</button></div>`;
      card.querySelector('strong').textContent = p.name || 'Projet immobilier';
      card.querySelector('small').textContent = p.description || p.city || '';
      const tags = card.querySelectorAll('.pi-project__tags span');
      tags[0].textContent = p.type || 'Projet';
      tags[1].textContent = p.status || 'En cours';
      card.querySelector('button').addEventListener('click', () => { projects.splice(index,1); renderProjects(); });
      projectList.appendChild(card);
    });
    projectsField.value = JSON.stringify(projects);
    if (projectEmpty) projectEmpty.hidden = projects.length > 0;
    projectsField.dispatchEvent(new Event('input',{bubbles:true}));
  };
  root.querySelector('[data-open-project]')?.addEventListener('click', () => { if (modal) modal.hidden = false; });
  root.querySelectorAll('[data-close-project]').forEach(b => b.addEventListener('click', () => { if (modal) modal.hidden = true; }));
  root.querySelector('[data-add-project]')?.addEventListener('click', () => {
    if (!modal) return;
    const name = modal.querySelector('[data-project-name]')?.value.trim();
    const type = modal.querySelector('[data-project-type]')?.value;
    const city = modal.querySelector('[data-project-city]')?.value.trim();
    const status = modal.querySelector('[data-project-status]')?.value;
    const description = modal.querySelector('[data-project-description]')?.value.trim();
    if (!name) { modal.querySelector('[data-project-name]')?.focus(); return; }
    projects.push({name,type,city,status,description});
    modal.querySelectorAll('input,textarea').forEach(i => i.value='');
    modal.hidden = true;
    renderProjects();
  });
  modal?.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });
  renderProjects();

  // Upload labels / validation state.
  root.querySelectorAll('[data-doc-row] input[type=file]').forEach(input => {
    input.addEventListener('change', () => {
      const row = input.closest('[data-doc-row]');
      const name = row?.querySelector('[data-file-name]');
      const status = row?.querySelector('[data-doc-status]');
      if (input.files?.length) {
        if (name) name.textContent = input.files.length > 1 ? `${input.files.length} fichiers sélectionnés` : input.files[0].name;
        if (status) { status.textContent = '✓ Prêt à envoyer'; status.classList.add('is-ok'); status.classList.remove('is-warn'); }
        row?.classList.remove('is-invalid');
      }
      updateProgress(); updateLocks();
    });
  });

  setActive(active, false);
})();
