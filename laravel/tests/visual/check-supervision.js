(() => {
 const results=[],check=(name,pass)=>results.push({name,pass:!!pass});
 window.addEventListener('load',()=>setTimeout(()=>{
  check('Single shared navigation',document.querySelectorAll('.ops-topbar').length===1);
  check('Four operations entries',document.querySelectorAll('.ops-nav-menu:not(.ops-tracking-menu):not(.ops-drivers-menu) .ops-dropdown a').length===4);
  check('No horizontal page overflow',document.documentElement.scrollWidth<=innerWidth+1);
  check('Main fits viewport',document.querySelector('.ops-main').getBoundingClientRect().right<=innerWidth+1);
  const toggle=document.querySelector('[data-nav-toggle]');if(innerWidth<900){toggle.click();check('Mobile navigation opens',document.querySelector('.ops-navigation').classList.contains('is-open'));toggle.click();}
  const form=document.querySelector('#ops-full-assignment');
  if(form){const options=form.querySelectorAll('[name=driver_id]');options[1].click();check('Driver selection updates summary',document.querySelector('[data-selected-driver=pickup]').textContent==='14 min');const search=document.querySelector('[data-driver-search]');search.value='Traoré';search.dispatchEvent(new Event('input'));check('Driver search filters cards',[...document.querySelectorAll('[data-driver-option]')].filter(el=>!el.hidden).length===1);search.value='';search.dispatchEvent(new Event('input'));options[0].click();check('Assignment posts to backend',form.method==='post'&&/\/expeditions\/\d+\/assigner$/.test(new URL(form.action).pathname));}
  if(form){
   const cards=[...document.querySelectorAll('[data-driver-option]')];
   check('Three drivers on first page',cards.filter(el=>!el.hidden).length===3);
   document.querySelector('[data-driver-page-next]').click();
   check('Next driver page',cards.filter(el=>!el.hidden).length===1&&cards[3].hidden===false);
   document.querySelector('[data-driver-page-previous]').click();
   check('Previous page preserves selection',cards[0].hidden===false&&form.querySelector('[name=driver_id]').checked);
  }
  check('Map initializes',!document.querySelector('[data-map]')||!!document.querySelector('.leaflet-container'));
  const output=document.createElement('pre');output.id='supervision-qa-results';output.hidden=true;output.textContent=JSON.stringify(results);document.body.append(output);
 },1200));
})();
