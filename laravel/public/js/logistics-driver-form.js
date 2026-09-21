(()=>{
    'use strict';

    const FIELD_SELECTOR='input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select, textarea';

    const fieldContainer=(control)=>control?.closest('.driver-field, .directory-form-grid > label, label') || control?.parentElement;

    const removeFieldError=(control)=>{
        if(!control)return;
        control.classList.remove('is-invalid');
        control.removeAttribute('aria-invalid');
        const container=fieldContainer(control);
        container?.querySelector('[data-driver-field-error]')?.remove();
    };

    const showFieldError=(control,message)=>{
        if(!control)return;
        removeFieldError(control);
        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid','true');

        const container=fieldContainer(control);
        if(container){
            const error=document.createElement('small');
            error.className='driver-field-error';
            error.dataset.driverFieldError='1';
            error.textContent=message;
            container.append(error);
        }

        try{control.focus({preventScroll:true});}catch{control.focus();}
        control.scrollIntoView?.({behavior:'smooth',block:'center'});
    };

    const validationMessage=(control)=>{
        if(control.validity?.valueMissing)return 'Ce champ est obligatoire.';
        if(control.validity?.typeMismatch && control.type==='email')return 'Saisissez une adresse e-mail valide, par exemple nom@domaine.com.';
        if(control.validity?.tooShort)return `Saisissez au moins ${control.minLength} caractères.`;
        if(control.validity?.tooLong)return `Ce champ ne doit pas dépasser ${control.maxLength} caractères.`;
        if(control.validity?.rangeUnderflow)return `La valeur minimale autorisée est ${control.min}.`;
        if(control.validity?.rangeOverflow)return `La valeur maximale autorisée est ${control.max}.`;
        if(control.validity?.patternMismatch)return 'Le format saisi n’est pas valide.';
        return 'Vérifiez la valeur saisie.';
    };

    const initDriverWizard=(form)=>{
        if(!form || form.dataset.driverWizardReady==='1')return;

        const panels=[...form.querySelectorAll('[data-step]')];
        const stepLabels=[...form.querySelectorAll('.directory-steps > div')];
        const next=form.querySelector('[data-wizard-next]');
        const prev=form.querySelector('[data-wizard-prev]');
        if(!panels.length || !next || !prev)return;

        form.dataset.driverWizardReady='1';
        form.noValidate=true;
        let step=0;

        const clearPanelErrors=(panel)=>{
            panel?.querySelectorAll('.is-invalid').forEach(removeFieldError);
            const zoneError=panel?.querySelector('[data-zone-error]');
            const zoneTrigger=panel?.querySelector('[data-zone-trigger]');
            if(zoneError)zoneError.hidden=true;
            zoneTrigger?.classList.remove('has-error');
        };

        const render=()=>{
            panels.forEach((panel,index)=>{panel.hidden=index!==step;});
            stepLabels.forEach((label,index)=>{
                label.classList.toggle('active',index===step);
                label.classList.toggle('is-complete',index<step);
            });
            prev.hidden=step===0;
            prev.disabled=step===0;
            next.disabled=false;
            next.hidden=false;
            next.textContent=step===panels.length-1?'Enregistrer le livreur':'Suivant →';
            next.setAttribute('aria-label',step===panels.length-1?'Enregistrer le livreur':'Passer à l’étape suivante');

            form.querySelectorAll('[data-zone-picker].is-open').forEach(picker=>{
                const menu=picker.querySelector('[data-zone-menu]');
                const trigger=picker.querySelector('[data-zone-trigger]');
                if(menu)menu.hidden=true;
                trigger?.setAttribute('aria-expanded','false');
                picker.classList.remove('is-open');
            });
        };

        const validateCurrentStep=()=>{
            const panel=panels[step];
            if(!panel)return false;
            clearPanelErrors(panel);

            const zonePicker=panel.querySelector('[data-zone-picker]');
            if(zonePicker){
                const selected=zonePicker.querySelectorAll('[data-zone-checkbox]:checked');
                if(selected.length===0){
                    const error=zonePicker.querySelector('[data-zone-error]');
                    const trigger=zonePicker.querySelector('[data-zone-trigger]');
                    if(error){
                        error.hidden=false;
                        error.textContent='Sélectionnez au moins une commune d’intervention.';
                    }
                    trigger?.classList.add('has-error');
                    try{trigger?.focus({preventScroll:true});}catch{trigger?.focus();}
                    trigger?.scrollIntoView?.({behavior:'smooth',block:'center'});
                    return false;
                }
            }

            const controls=[...panel.querySelectorAll(FIELD_SELECTOR)].filter(control=>!control.disabled);
            for(const control of controls){
                // Les champs optionnels vides ne bloquent jamais le passage à l’étape suivante.
                if(!control.required && String(control.value ?? '').trim()==='')continue;
                if(!control.checkValidity()){
                    showFieldError(control,validationMessage(control));
                    return false;
                }
            }

            return true;
        };

        const buildSummary=()=>{
            const summary=form.querySelector('[data-wizard-summary]');
            if(!summary)return;
            summary.replaceChildren();

            const value=(name)=>{
                const element=form.elements[name];
                if(!element)return '—';
                const raw=String(element.value ?? '').trim();
                return raw || '—';
            };

            const zones=[...form.querySelectorAll('[data-zone-checkbox]:checked')]
                .map(input=>input.closest('[data-zone-option]')?.querySelector('.driver-zone-option-copy strong')?.textContent?.trim())
                .filter(Boolean)
                .join(', ');

            const rows=[
                ['Nom complet',value('name')],
                ['Téléphone',value('phone')],
                ['E-mail',value('email')],
                ['Zones d’intervention',zones || '—'],
                ['Véhicule',value('vehicle')],
                ['Statut',value('status')],
            ];

            rows.forEach(([label,text])=>{
                const row=document.createElement('div');
                row.className='driver-summary-row';
                const key=document.createElement('span');
                key.textContent=label;
                const val=document.createElement('strong');
                val.textContent=text;
                row.append(key,val);
                summary.append(row);
            });
        };

        next.addEventListener('click',(event)=>{
            event.preventDefault();
            event.stopPropagation();

            if(!validateCurrentStep())return;

            if(step<panels.length-1){
                step+=1;
                if(step===panels.length-1)buildSummary();
                render();
                panels[step]?.scrollIntoView?.({behavior:'smooth',block:'nearest'});
                return;
            }

            next.disabled=true;
            next.textContent='Enregistrement…';
            form.submit();
        });

        prev.addEventListener('click',(event)=>{
            event.preventDefault();
            event.stopPropagation();
            if(step===0)return;
            step-=1;
            render();
        });

        form.addEventListener('input',(event)=>{
            const control=event.target.closest?.(FIELD_SELECTOR);
            if(control)removeFieldError(control);
        });
        form.addEventListener('change',(event)=>{
            const control=event.target.closest?.(FIELD_SELECTOR);
            if(control)removeFieldError(control);
        });

        form.addEventListener('keydown',(event)=>{
            if(event.key!=='Enter' || event.target?.tagName==='TEXTAREA')return;
            if(event.target?.matches('[data-zone-search]'))return;
            if(step<panels.length-1){
                event.preventDefault();
                next.click();
            }
        });

        // Accessible reset used when the modal is reopened.
        form._resetDriverWizard=()=>{
            step=0;
            clearPanelErrors(panels[0]);
            render();
        };

        render();
    };

    const initAll=()=>document.querySelectorAll('[data-driver-wizard]').forEach(initDriverWizard);

    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initAll,{once:true});
    else initAll();

    // Toujours rouvrir l’assistant à la première étape, sans effacer les valeurs saisies.
    document.addEventListener('click',(event)=>{
        const opener=event.target.closest?.('[data-directory-open]');
        if(!opener)return;
        const dialog=document.getElementById(opener.dataset.directoryOpen);
        const form=dialog?.querySelector('[data-driver-wizard]');
        setTimeout(()=>form?._resetDriverWizard?.(),0);
    },true);
})();
