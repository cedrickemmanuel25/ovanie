(() => {
 const errors=[];window.addEventListener('error',event=>{if(event.message)errors.push(event.message);});
 window.addEventListener('load',()=>setTimeout(()=>{
  const results=[],check=(name,pass)=>results.push({name,pass:!!pass});
  check('Shared Tracking menu has three entries',document.querySelectorAll('.ops-tracking-menu .ops-dropdown a').length===3);
  check('GPS map initializes',!!document.querySelector('.ops-tracking-map .leaflet-container'));
  check('Main fits viewport',document.querySelector('main').getBoundingClientRect().right<=innerWidth+1);
  check('Mission details link',!!document.querySelector('a[href*="/expeditions/"]')||!!document.querySelector('[data-tracking-row]'));
  const rows=[...document.querySelectorAll('[data-tracking-row]')];
  if(rows.length){check('Mission list limited to six',rows.filter(row=>!row.hidden).length<=6);document.querySelector('[data-tracking-page="1"]').click();check('Next page shows next mission',rows[6]&&!rows[6].hidden&&rows[0].hidden);document.querySelector('[data-tracking-page="-1"]').click();const search=document.querySelector('[data-tracking-search]');search.value='Anyama';search.dispatchEvent(new Event('input'));check('Search filters missions',rows.filter(row=>!row.hidden).length===1);search.value='';search.dispatchEvent(new Event('input'));}
  const layers=document.querySelector('[data-tracking-layers]');layers.click();check('Route layer control works',layers.getAttribute('aria-pressed')==='false');layers.click();
  check('No JavaScript errors',errors.length===0);
  const output=document.createElement('pre');output.id='supervision-qa-results';output.hidden=true;output.textContent=JSON.stringify(results);document.body.append(output);
 },1200));
})();
