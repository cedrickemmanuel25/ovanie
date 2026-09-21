// Runs only on the local reference fixture server with ?qa=1.
document.addEventListener('DOMContentLoaded',()=>{
    if(!new URLSearchParams(location.search).has('qa'))return;
    const results=[];
    const check=(name,condition)=>results.push({name,pass:Boolean(condition)});
    const event=element=>element.dispatchEvent(new Event('change',{bubbles:true}));
    const tours=document.querySelector('[data-tour-filters]');
    if(tours){
        const search=tours.querySelector('[data-filter=search]');search.value='Diomandé';search.dispatchEvent(new Event('input',{bubbles:true}));
        check('Recherche de tournée',document.querySelectorAll('[data-tour-row]:not([hidden])').length===1);
        document.querySelector('[data-reset-tours]').click();
        check('Réinitialisation des filtres',document.querySelectorAll('[data-tour-row]:not([hidden])').length===6);
        const status=tours.querySelector('[data-filter=status]');status.value='planned';event(status);
        check('Filtre de statut',document.querySelectorAll('[data-tour-row]:not([hidden])').length===1);
        document.querySelector('[data-reset-tours]').click();
    }
    const plan=document.querySelector('#ops-create-form');
    if(plan){
        const first=plan.querySelector('[data-select-mission]');first.checked=false;event(first);
        check('Recalcul de sélection',plan.querySelector('[data-kpi=count] .ops-kpi-value').textContent==='4');
        check('Synchronisation du formulaire',plan.querySelectorAll('input[name="mission_items[]"]').length===4);
        const all=plan.querySelector('[data-select-all]');all.checked=true;event(all);
        check('Tout sélectionner',plan.querySelectorAll('[data-select-mission]:checked').length===6);
        const optimization=plan.querySelector('input[type=checkbox][name=optimization_enabled]');optimization.checked=false;event(optimization);
        check('Désactiver optimisation',plan.querySelector('[data-optimization-status]').textContent.includes('inactive'));
    }
    const modal=document.querySelector('#ops-assignment-dialog');
    if(modal){
        document.querySelector('[data-open-assignment]').click();
        check('Ouverture affectation',modal.open);
        const note=modal.querySelector('textarea');note.value='Note test';note.dispatchEvent(new Event('input',{bubbles:true}));
        check('Compteur de note',modal.querySelector('[data-note-count]').textContent==='9');
        modal.querySelector('[data-save-assignment]').click();
        check('Brouillon et fermeture',!modal.open&&Object.keys(localStorage).some(k=>k.startsWith('ovanie:assignment:')));
    }
    check('Pas de débordement horizontal',document.documentElement.scrollWidth<=innerWidth);
    const output=document.createElement('pre');output.id='ops-qa-results';output.hidden=true;output.textContent=JSON.stringify(results);document.body.append(output);
});
