(function(){
  const open = id => { const el=document.getElementById(id); if(el){ el.hidden=false; document.body.style.overflow='hidden'; }};
  const close = el => { const modal=el.closest('.mg-modal'); if(modal){ modal.hidden=true; document.body.style.overflow=''; }};
  document.addEventListener('click', e => {
    const opener=e.target.closest('[data-mg-open]'); if(opener){ e.preventDefault(); open(opener.dataset.mgOpen); return; }
    const closer=e.target.closest('[data-mg-close]'); if(closer){ e.preventDefault(); close(closer); return; }
    if(e.target.classList.contains('mg-modal')){ e.target.hidden=true; document.body.style.overflow=''; }
  });
  document.addEventListener('keydown',e=>{if(e.key==='Escape') document.querySelectorAll('.mg-modal:not([hidden])').forEach(m=>{m.hidden=true;document.body.style.overflow='';});});

  function initFleetCreate(){
    const root=document.querySelector('[data-fleet-create]');
    if(!root) return;
    const type=root.querySelector('[data-fleet-type]');
    const preview=root.querySelector('[data-fleet-preview]');
    const summaryImage=root.querySelector('[data-summary-image]');
    const title=root.querySelector('[data-preview-title]');
    const summaryType=root.querySelector('[data-summary-type]');
    const registration=root.querySelector('input[name="registration"]');
    const cap=root.querySelector('[data-summary-capacity]');
    const vol=root.querySelector('[data-summary-volume]');
    const zone=root.querySelector('[data-summary-zone]');
    const driver=root.querySelector('[data-summary-driver]');
    const status=root.querySelector('[data-summary-status]');
    const state=root.querySelector('[data-summary-state]');
    const statusLabels={available:'Disponible',maintenance:'Maintenance',out_of_service:'Hors service'};

    const setText=(selector,value)=>{const el=root.querySelector(selector);if(el)el.textContent=value;};
    const formatNumber=value=>{
      const n=Number(String(value||0).replace(',','.'));
      return Number.isFinite(n)?new Intl.NumberFormat('fr-FR',{maximumFractionDigits:1}).format(n):'0';
    };
    const updateType=(applyDefaults=false)=>{
      const opt=type?.selectedOptions?.[0]; if(!opt) return;
      const image=opt.dataset.image||'';
      if(preview) preview.src=image;
      if(summaryImage) summaryImage.src=image;
      if(title) title.textContent=opt.value;
      if(summaryType) summaryType.textContent=opt.value;
      if(applyDefaults){ if(cap) cap.value=opt.dataset.capacity||''; if(vol && opt.dataset.volume) vol.value=opt.dataset.volume; }
      updateSummary();
    };
    const updateSummary=()=>{
      setText('[data-summary-registration]', registration?.value?.trim()||'Immatriculation non saisie');
      setText('[data-summary-capacity-text]', `${formatNumber(cap?.value)} kg`);
      setText('[data-summary-zone-text]', zone?.selectedOptions?.[0]?.textContent||'Non définie');
      setText('[data-summary-driver-text]', driver?.value ? (driver.selectedOptions?.[0]?.textContent||'Non affecté').split(' — ')[0] : 'Non affecté');
      const statusText=root.querySelector('[data-summary-status-text]');
      if(statusText){ statusText.className=`mg-status ${status?.value||'available'}`; statusText.innerHTML=`<i></i>${statusLabels[status?.value]||'Disponible'}`; }
      const requiredFiles=[...root.querySelectorAll('input[data-fleet-file][required]')];
      const done=requiredFiles.filter(f=>f.files&&f.files.length).length;
      setText('[data-summary-documents]',`${done}/${requiredFiles.length}`);
      const ready=Boolean(type?.value && registration?.value?.trim() && cap?.value && done===requiredFiles.length);
      if(state){state.textContent=ready?'Prêt à enregistrer':'À compléter';state.classList.toggle('ready',ready);}
    };

    type?.addEventListener('change',()=>updateType(true));
    [registration,cap,vol,zone,driver,status].forEach(el=>el?.addEventListener('input',updateSummary));
    [zone,driver,status].forEach(el=>el?.addEventListener('change',updateSummary));
    root.querySelectorAll('input[data-fleet-file]').forEach(input=>{
      input.addEventListener('change',()=>{
        const label=input.closest('.fleet-upload-card');
        const name=label?.querySelector('[data-file-name]');
        if(name) name.textContent=input.files?.[0]?.name||name.textContent;
        updateSummary();
      });
    });
    updateType(false); updateSummary();
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initFleetCreate); else initFleetCreate();
})();
