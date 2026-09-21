(function () {
  const qs=(s,r=document)=>r.querySelector(s);
  const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));

  function openModal(id){
    const modal=document.getElementById(id);
    if(!modal) return;
    modal.hidden=false;
    document.body.style.overflow='hidden';
  }
  function closeModal(modal){
    if(!modal) return;
    modal.hidden=true;
    if(!document.querySelector('.partner-modal:not([hidden])')) document.body.style.overflow='';
  }

  document.addEventListener('click',function(e){
    const opener=e.target.closest('[data-partner-open]');
    if(opener){e.preventDefault();openModal(opener.dataset.partnerOpen);return;}
    const closer=e.target.closest('[data-partner-close]');
    if(closer){e.preventDefault();closeModal(closer.closest('.partner-modal'));return;}
    if(e.target.classList.contains('partner-modal')) closeModal(e.target);
  });
  document.addEventListener('keydown',function(e){
    if(e.key==='Escape') qsa('.partner-modal:not([hidden])').forEach(closeModal);
  });

  function initFileInputs(){
    qsa('.partner-file-input input[type=file]').forEach(input=>{
      input.addEventListener('change',()=>{
        const label=qs('[data-file-label]',input.closest('.partner-file-input'));
        if(label) label.textContent=input.files && input.files[0] ? input.files[0].name : 'Sélectionner un fichier';
      });
    });
  }

  function initAssignment(){
    const form=qs('[data-partner-assignment]');
    if(!form) return;
    const checks=qsa('[data-mission-check]',form);
    const count=qs('[data-selected-count]',form);
    const weight=qs('[data-selected-weight]',form);
    const vehicles=qs('[data-selected-vehicles]',form);
    const list=qs('[data-selected-list]',form);
    const submit=qs('[data-assign-submit]',form);

    const update=()=>{
      const selected=checks.filter(c=>c.checked && !c.disabled);
      const totalWeight=selected.reduce((sum,c)=>sum+(Number(c.dataset.weight)||0),0);
      const vehicleCounts={};
      selected.forEach(c=>{const key=c.dataset.vehicle||'Autre';vehicleCounts[key]=(vehicleCounts[key]||0)+1;});
      if(count) count.textContent=String(selected.length);
      if(weight) weight.textContent=new Intl.NumberFormat('fr-FR',{maximumFractionDigits:1}).format(totalWeight)+' kg';
      if(submit) submit.disabled=selected.length===0;
      if(vehicles){
        vehicles.innerHTML=selected.length
          ? Object.entries(vehicleCounts).map(([k,v])=>`<div><strong>${escapeHtml(k)}</strong> : ${v}</div>`).join('')
          : '<span>Aucune mission sélectionnée.</span>';
      }
      if(list){
        list.innerHTML=selected.length
          ? selected.map(c=>`<div><strong>${escapeHtml(c.dataset.reference||'Mission')}</strong>${escapeHtml(c.dataset.destination||'')}</div>`).join('')
          : '<span>Aucune mission sélectionnée.</span>';
      }
    };
    checks.forEach(c=>c.addEventListener('change',update));
    update();
  }

  function escapeHtml(value){
    return String(value||'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
  }

  function init(){
    initFileInputs();
    initAssignment();
    if(qs('[data-partner-open-on-error]')) openModal(qs('[data-partner-open-on-error]').dataset.partnerOpenOnError);
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
