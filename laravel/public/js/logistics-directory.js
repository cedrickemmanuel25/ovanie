(()=>{'use strict';

const normalise=(value='')=>value
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'')
    .toLowerCase()
    .trim();

const closeZonePicker=(picker)=>{
    if(!picker)return;
    const menu=picker.querySelector('[data-zone-menu]');
    const trigger=picker.querySelector('[data-zone-trigger]');
    if(menu)menu.hidden=true;
    if(trigger)trigger.setAttribute('aria-expanded','false');
    picker.classList.remove('is-open');
};

const updateZonePicker=(picker)=>{
    const checkboxes=[...picker.querySelectorAll('[data-zone-checkbox]')];
    const checked=checkboxes.filter(input=>input.checked);
    const triggerText=picker.querySelector('[data-zone-trigger-text]');
    const count=picker.querySelector('[data-zone-count]');
    const chips=picker.querySelector('[data-zone-chips]');
    const error=picker.querySelector('[data-zone-error]');

    checkboxes.forEach(input=>input.closest('[data-zone-option]')?.classList.toggle('is-selected',input.checked));

    if(count){
        count.textContent=checked.length===0
            ? 'Aucune sélection'
            : `${checked.length} zone${checked.length>1?'s':''} sélectionnée${checked.length>1?'s':''}`;
        count.classList.toggle('has-selection',checked.length>0);
    }

    if(triggerText){
        if(checked.length===0){
            triggerText.textContent='Sélectionner une ou plusieurs communes';
        }else{
            const names=checked.map(input=>input.closest('[data-zone-option]')?.querySelector('.driver-zone-option-copy strong')?.textContent?.trim()).filter(Boolean);
            triggerText.textContent=names.length<=2?names.join(', '):`${names.slice(0,2).join(', ')} +${names.length-2}`;
        }
    }

    if(chips){
        chips.replaceChildren();
        checked.forEach(input=>{
            const option=input.closest('[data-zone-option]');
            const name=option?.querySelector('.driver-zone-option-copy strong')?.textContent?.trim()||'Zone';
            const chip=document.createElement('button');
            chip.type='button';
            chip.className='driver-zone-chip';
            chip.dataset.zoneChip=input.value;
            chip.setAttribute('aria-label',`Retirer ${name}`);
            const label=document.createElement('span');
            label.textContent=name;
            const remove=document.createElement('b');
            remove.textContent='×';
            remove.setAttribute('aria-hidden','true');
            chip.append(label,remove);
            chips.append(chip);
        });
    }

    if(error&&checked.length>0)error.hidden=true;
};

document.querySelectorAll('[data-directory-open]').forEach(button=>button.addEventListener('click',()=>{
    const dialog=document.getElementById(button.dataset.directoryOpen);
    dialog?.showModal();
    dialog?.querySelectorAll('[data-zone-picker]').forEach(updateZonePicker);
    dialog?.querySelectorAll('.directory-map').forEach(map=>setTimeout(()=>map._directoryMap?.invalidateSize(),80));
}));

document.querySelectorAll('[data-directory-close]').forEach(button=>button.addEventListener('click',()=>{
    const dialog=button.closest('dialog');
    dialog?.querySelectorAll('[data-zone-picker]').forEach(closeZonePicker);
    dialog?.close();
}));

document.querySelectorAll('[data-avatar-input]').forEach(input=>input.addEventListener('change',()=>{
    const upload=input.closest('[data-avatar-upload]');
    const preview=upload?.querySelector('[data-avatar-preview]');
    const placeholder=upload?.querySelector('[data-avatar-placeholder]');
    const name=upload?.parentElement?.querySelector('[data-avatar-name]');
    const file=input.files?.[0];
    if(!preview||!placeholder)return;
    if(!file){preview.hidden=true;placeholder.hidden=false;if(name)name.textContent='';return;}
    if(!file.type.startsWith('image/')){input.value='';preview.hidden=true;placeholder.hidden=false;if(name)name.textContent='Le fichier choisi doit être une image.';return;}
    if(file.size>2*1024*1024){input.value='';preview.hidden=true;placeholder.hidden=false;if(name)name.textContent='Image trop volumineuse : maximum 2 Mo.';return;}
    const reader=new FileReader();
    reader.addEventListener('load',()=>{
        preview.src=reader.result;
        preview.hidden=false;
        placeholder.hidden=true;
        if(name)name.textContent=file.name;
    });
    reader.readAsDataURL(file);
}));

document.querySelectorAll('[data-identity-file-input]').forEach(input=>input.addEventListener('change',()=>{
    const label=input.closest('.driver-file-field')?.querySelector('[data-identity-file-name]');
    const file=input.files?.[0];
    if(label)label.textContent=file?.name||'Aucun fichier choisi';
}));

// Assistant de création d'un livreur. Ce code est volontairement dans l'asset
// externe déjà chargé par la page : les politiques de sécurité peuvent empêcher
// l'exécution des scripts présents directement dans la fenêtre modale.
const driverWizardForms=[...document.querySelectorAll('[data-ovanie-driver-form]')];

const renderDriverWizard=(form)=>{
    const panels=[...form.querySelectorAll('[data-driver-step]')];
    const labels=[...form.querySelectorAll('.directory-steps > div')];
    const next=form.querySelector('[data-driver-next]');
    const previous=form.querySelector('[data-driver-prev]');
    let step=Number.parseInt(form.dataset.ovanieDriverStep||'0',10);
    if(!Number.isFinite(step)||step<0)step=0;
    if(step>=panels.length)step=Math.max(0,panels.length-1);
    form.dataset.ovanieDriverStep=String(step);
    panels.forEach((panel,index)=>{panel.hidden=index!==step;});
    labels.forEach((label,index)=>{
        label.classList.toggle('active',index===step);
        label.classList.toggle('is-complete',index<step);
    });
    if(previous)previous.hidden=step===0;
    if(next)next.textContent=step===panels.length-1?'Enregistrer le livreur':'Suivant →';
};

const clearDriverWizardErrors=(form)=>{
    form.querySelectorAll('.driver-inline-error,.driver-validation-summary').forEach(error=>error.remove());
    form.querySelectorAll('.driver-invalid').forEach(field=>field.classList.remove('driver-invalid'));
};

const showDriverWizardError=(panel,field,message)=>{
    const summary=document.createElement('p');
    summary.className='driver-validation-summary';
    summary.setAttribute('role','alert');
    summary.textContent=message;
    panel.prepend(summary);
    if(field){
        field.classList.add('driver-invalid');
        const container=field.closest('label');
        if(container){
            const error=document.createElement('small');
            error.className='driver-inline-error';
            error.textContent=field.validationMessage||message;
            container.append(error);
        }
        field.focus({preventScroll:true});
    }
};

const validateDriverWizardStep=(form,panel)=>{
    clearDriverWizardErrors(form);
    const zonePicker=panel.querySelector('[data-zone-picker]');
    if(zonePicker&&!zonePicker.querySelector('[data-zone-checkbox]:checked')){
        const trigger=zonePicker.querySelector('[data-zone-trigger]');
        showDriverWizardError(panel,trigger,'Sélectionnez au moins une commune d’intervention pour continuer.');
        return false;
    }
    const fields=[...panel.querySelectorAll('input,select,textarea')].filter(field=>
        !field.disabled&&field.type!=='hidden'&&field.type!=='file'&&field.type!=='checkbox'
    );
    const invalid=fields.find(field=>!field.checkValidity());
    if(invalid){
        showDriverWizardError(panel,invalid,'Complétez les champs obligatoires avant de continuer.');
        return false;
    }
    return true;
};

driverWizardForms.forEach(form=>{
    form.dataset.ovanieDriverStep='0';
    renderDriverWizard(form);
});

document.addEventListener('click',(event)=>{
    const next=event.target.closest?.('[data-driver-next]');
    if(next){
        event.preventDefault();
        event.stopImmediatePropagation();
        const form=next.closest('[data-ovanie-driver-form]');
        const panels=form?[...form.querySelectorAll('[data-driver-step]')]:[];
        const step=Number.parseInt(form?.dataset.ovanieDriverStep||'0',10);
        const panel=panels[step];
        if(!form||!panel||!validateDriverWizardStep(form,panel))return;
        if(step<panels.length-1){
            form.dataset.ovanieDriverStep=String(step+1);
            renderDriverWizard(form);
        }else{
            panels.forEach(panel=>{panel.hidden=false;});
            form.requestSubmit?.();
        }
        return;
    }
    const previous=event.target.closest?.('[data-driver-prev]');
    if(previous){
        event.preventDefault();
        const form=previous.closest('[data-ovanie-driver-form]');
        if(!form)return;
        clearDriverWizardErrors(form);
        form.dataset.ovanieDriverStep=String(Math.max(0,Number.parseInt(form.dataset.ovanieDriverStep||'0',10)-1));
        renderDriverWizard(form);
    }
},true);

const initDirectoryWizard=(form)=>{
    if(!form||form.dataset.wizardReady==='1')return;

    const panels=[...form.querySelectorAll('[data-step]')];
    const labels=[...form.querySelectorAll('.directory-steps>div')];
    const next=form.querySelector('[data-wizard-next]');
    const prev=form.querySelector('[data-wizard-prev]');

    if(!panels.length||!next||!prev)return;

    form.dataset.wizardReady='1';
    let step=0;

    const render=()=>{
        panels.forEach((panel,index)=>{panel.hidden=index!==step;});
        labels.forEach((label,index)=>{label.classList.toggle('active',index===step);label.classList.toggle('is-complete',index<step);});
        prev.hidden=step===0;
        next.hidden=false;
        next.disabled=false;
        next.textContent=step===panels.length-1?'Enregistrer le livreur':'Suivant →';

        form.querySelectorAll('[data-zone-picker]').forEach(closeZonePicker);
        // Ne pas déplacer automatiquement le focus : le livreur peut relire l'étape avant de saisir.
    };

    const validateCurrentStep=()=>{
        const panel=panels[step];
        if(!panel)return false;

        const picker=panel.querySelector('[data-zone-picker]');
        if(picker){
            const checked=picker.querySelectorAll('[data-zone-checkbox]:checked');
            const error=picker.querySelector('[data-zone-error]');
            const trigger=picker.querySelector('[data-zone-trigger]');

            if(checked.length===0){
                if(error)error.hidden=false;
                trigger?.classList.add('has-error');
                trigger?.focus();
                return false;
            }

            if(error)error.hidden=true;
            trigger?.classList.remove('has-error');
        }

        const controls=[...panel.querySelectorAll('input,select,textarea')].filter(element=>{
            if(element.disabled)return false;
            if(element.type==='hidden')return false;
            if(element.type==='checkbox'&&element.hasAttribute('data-zone-checkbox'))return false;
            return true;
        });

        const invalid=controls.find(element=>!element.checkValidity());
        if(invalid){
            invalid.focus({preventScroll:true});
            invalid.reportValidity();
            return false;
        }

        return true;
    };

    const buildSummary=()=>{
        const summary=form.querySelector('[data-wizard-summary]');
        if(!summary)return;
        summary.replaceChildren();

        const fieldValue=(name)=>form.elements[name]?.value?.trim?.()||form.elements[name]?.value||'—';
        const checkedZones=[...form.querySelectorAll('[data-zone-checkbox]:checked')]
            .map(input=>input.closest('[data-zone-option]')?.querySelector('.driver-zone-option-copy strong')?.textContent?.trim())
            .filter(Boolean)
            .join(', ');

        [
            ['Nom',fieldValue('name')],
            ['Téléphone',fieldValue('phone')],
            ['Zones',checkedZones||'—'],
            ['Véhicule',fieldValue('vehicle')],
            ['Statut',fieldValue('status')],
        ].forEach(([label,value])=>{
            const paragraph=document.createElement('p');
            const strong=document.createElement('strong');
            strong.textContent=`${label} : `;
            paragraph.append(strong,document.createTextNode(String(value)));
            summary.append(paragraph);
        });
    };

    next.addEventListener('click',event=>{
        event.preventDefault();

        if(!validateCurrentStep())return;

        if(step<panels.length-1){
            step+=1;
            if(step===panels.length-1)buildSummary();
            render();
            return;
        }

        panels.forEach(panel=>{panel.hidden=false;});
        if(typeof form.requestSubmit==='function')form.requestSubmit();
        else form.submit();
    });

    prev.addEventListener('click',event=>{
        event.preventDefault();
        if(step===0)return;
        step-=1;
        render();
    });

    form.addEventListener('keydown',event=>{
        if(event.key!=='Enter'||event.target?.tagName==='TEXTAREA')return;
        if(event.target?.matches('[data-zone-search]'))return;
        if(step<panels.length-1){
            event.preventDefault();
            next.click();
        }
    });

    render();
};

// Initialiser le wizard avant les composants secondaires. Ainsi une erreur dans
// un sélecteur de zone ne peut pas neutraliser les boutons Suivant/Précédent.
document.querySelectorAll('[data-wizard]').forEach(initDirectoryWizard);

document.querySelectorAll('[data-zone-picker]').forEach(picker=>{
    const trigger=picker.querySelector('[data-zone-trigger]');
    const menu=picker.querySelector('[data-zone-menu]');
    const search=picker.querySelector('[data-zone-search]');
    const options=[...picker.querySelectorAll('[data-zone-option]')];
    const checkboxes=[...picker.querySelectorAll('[data-zone-checkbox]')];
    const selectAll=picker.querySelector('[data-zone-select-all]');
    const clear=picker.querySelector('[data-zone-clear]');

    const open=()=>{
        menu.hidden=false;
        trigger.setAttribute('aria-expanded','true');
        picker.classList.add('is-open');
        requestAnimationFrame(()=>search?.focus());
    };

    trigger?.addEventListener('click',()=>menu.hidden?open():closeZonePicker(picker));

    checkboxes.forEach(input=>input.addEventListener('change',()=>updateZonePicker(picker)));

    search?.addEventListener('input',()=>{
        const term=normalise(search.value);
        options.forEach(option=>{
            const haystack=normalise(option.dataset.zoneName||option.textContent);
            option.hidden=term!==''&&!haystack.includes(term);
        });
    });

    selectAll?.addEventListener('click',()=>{
        options.filter(option=>!option.hidden).forEach(option=>{
            const checkbox=option.querySelector('[data-zone-checkbox]');
            if(checkbox)checkbox.checked=true;
        });
        updateZonePicker(picker);
    });

    clear?.addEventListener('click',()=>{
        checkboxes.forEach(input=>input.checked=false);
        updateZonePicker(picker);
    });

    picker.querySelector('[data-zone-chips]')?.addEventListener('click',event=>{
        const chip=event.target.closest('[data-zone-chip]');
        if(!chip)return;
        const input=checkboxes.find(item=>item.value===chip.dataset.zoneChip);
        if(input)input.checked=false;
        updateZonePicker(picker);
    });

    picker.addEventListener('keydown',event=>{
        if(event.key==='Escape'&&!menu.hidden){
            closeZonePicker(picker);
            trigger?.focus();
        }
    });

    updateZonePicker(picker);
});

document.addEventListener('click',event=>{
    document.querySelectorAll('[data-zone-picker].is-open').forEach(picker=>{
        if(!picker.contains(event.target))closeZonePicker(picker);
    });
});

document.querySelectorAll('[data-page-size]').forEach(select=>select.addEventListener('change',()=>{
    const url=new URL(location.href);
    url.searchParams.set('per_page',select.value);
    url.searchParams.delete('page');
    location.assign(url);
}));


document.querySelectorAll('[data-draft]').forEach(button=>button.addEventListener('click',()=>{
    const form=button.closest('form');
    const key=button.dataset.draft;
    const data={};
    new FormData(form).forEach((value,name)=>{
        if(typeof value==='string'&&!['_token','password','pin'].includes(name))data[name]=value;
    });
    sessionStorage.setItem(key,JSON.stringify(data));
    button.textContent='Brouillon enregistré';
}));

document.querySelectorAll('[data-restore-draft]').forEach(form=>{
    try{
        const data=JSON.parse(sessionStorage.getItem(form.dataset.restoreDraft)||'null');
        if(data)for(const [name,value]of Object.entries(data)){
            const element=form.elements[name];
            if(element&&'value'in element){element.value=value;if(element.type==='checkbox')element.checked=true;}
        }
    }catch{}
});

document.querySelectorAll('[data-incident-mission]').forEach(select=>select.addEventListener('change',()=>{
    const option=select.selectedOptions[0];
    const form=select.closest('form');
    form.action=option.dataset.action||form.action;
    const label=form.querySelector('[data-incident-resource]');
    if(label)label.textContent=option.dataset.resource||'Aucun livreur affecté';
}));
document.querySelectorAll('[data-incident-mission]').forEach(select=>select.dispatchEvent(new Event('change')));

document.querySelectorAll('[data-current-position]').forEach(button=>button.addEventListener('click',()=>{
    const form=button.closest('form');
    if(!navigator.geolocation){button.textContent='GPS indisponible';return;}
    navigator.geolocation.getCurrentPosition(position=>{
        for(const key of ['latitude','longitude'])if(form.elements[key])form.elements[key].value=position.coords[key].toFixed(6);
        button.textContent='Position renseignée';
    },()=>button.textContent='Position indisponible — renseignez les coordonnées');
}));

document.querySelectorAll('[data-directory-map]').forEach(element=>{
    if(!window.L)return;
    let points=[];
    try{points=JSON.parse(element.dataset.directoryMap||'[]');}catch{}
    const map=L.map(element,{scrollWheelZoom:false}).setView([5.33,-4.01],11);
    element._directoryMap=map;
    const token=JSON.parse(document.getElementById('ops-map-config')?.textContent||'{}').token;
    L.tileLayer(token
        ?'https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}?access_token='+encodeURIComponent(token)
        :'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        {attribution:token?'© Mapbox © OpenStreetMap':'© OpenStreetMap',maxZoom:19}
    ).addTo(map);
    const valid=points.filter(point=>Number.isFinite(Number(point.lat))&&Number.isFinite(Number(point.lng))&&point.lat!==null&&point.lng!==null);
    valid.forEach(point=>{
        const label=document.createElement('div');
        label.textContent=point.label||'';
        L.circleMarker([point.lat,point.lng],{radius:9,color:'#fff',weight:3,fillColor:point.color||'#009c60',fillOpacity:1})
            .addTo(map)
            .bindTooltip(label,{permanent:point.permanent!==false,direction:'top'});
    });
    if(valid.length>1)map.fitBounds(valid.map(point=>[point.lat,point.lng]),{padding:[25,25],maxZoom:12});
    else if(valid.length===1)map.setView([valid[0].lat,valid[0].lng],13);
    if(element.dataset.polygon)L.polygon(JSON.parse(element.dataset.polygon),{color:'#00a96a',weight:1,fillOpacity:.15}).addTo(map);
    new ResizeObserver(()=>map.invalidateSize()).observe(element);
});

document.querySelectorAll('[data-note-focus]').forEach(button=>button.addEventListener('click',()=>{
    const element=document.querySelector(button.dataset.noteFocus);
    const details=element?.closest('details');
    if(details)details.open=true;
    element?.scrollIntoView({behavior:'smooth'});
    element?.focus();
}));

})();
