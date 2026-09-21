(function(){
    const q=(s,r=document)=>r.querySelector(s), qa=(s,r=document)=>Array.from(r.querySelectorAll(s));
    function openModal(id){const el=q('#'+id);if(!el)return;el.hidden=false;document.documentElement.style.overflow='hidden';const first=q('select,input:not([type="hidden"]),textarea,button',el);if(first)setTimeout(()=>first.focus(),40)}
    function closeModal(el){if(!el)return;el.hidden=true;document.documentElement.style.overflow='';returnFocus?.focus();}
    let returnFocus=null;
    function populate(form, values, prefix='') {
        Object.entries(values).forEach(([key,value])=>{
            const name=prefix?prefix+'['+key+']':key;
            if(value!==null&&typeof value==='object'){populate(form,value,name);return;}
            const input=Array.from(form.elements).find(el=>el.name===name);
            if(!input)return;
            if(input.type==='checkbox')input.checked=Boolean(value);
            else input.value=typeof value==='boolean'?(value?'1':'0'):(value??'');
            input.dispatchEvent(new Event('change',{bubbles:true}));
            input.dispatchEvent(new Event('input',{bubbles:true}));
        });
    }
    qa('[data-pricing-open]').forEach(btn=>btn.addEventListener('click',()=>{
        const modal=document.getElementById(btn.dataset.pricingOpen), form=modal?.querySelector('form');
        returnFocus=btn;
        if(form){
            form.reset();
            const title=q('[data-editor-title]',modal);
            if(title){title.dataset.original ||= title.textContent;title.textContent=btn.dataset.pricingValues?'Modifier la configuration':title.dataset.original;}
            if(btn.dataset.pricingValues)populate(form,JSON.parse(btn.dataset.pricingValues));
        }
        openModal(btn.dataset.pricingOpen);
        q('select,input:not([type="hidden"]),textarea,button',modal)?.focus();
    }));
    document.addEventListener('keydown',event=>{
        const modal=qa('.pricing-modal').find(el=>!el.hidden);
        if(event.key==='Escape'){returnFocus?.focus();return;}
        if(!modal||event.key!=='Tab')return;
        const items=qa('button,a[href],input:not([type="hidden"]),select,textarea',modal).filter(el=>!el.disabled&&el.getClientRects().length);
        const first=items[0],last=items[items.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
    });
    qa('[data-pricing-auto-open]').forEach(modal=>{modal.hidden=false;document.documentElement.style.overflow='hidden'});
    qa('[data-pricing-close]').forEach(btn=>btn.addEventListener('click',()=>closeModal(btn.closest('.pricing-modal'))));
    qa('.pricing-modal').forEach(modal=>modal.addEventListener('mousedown',e=>{if(e.target===modal)closeModal(modal)}));
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){const modal=qa('.pricing-modal').find(x=>!x.hidden);if(modal)closeModal(modal)}});
    qa('[data-action-button]').forEach(btn=>btn.addEventListener('click',()=>{const form=btn.closest('form');if(!form)return;const target=q('input[name="action"]',form);if(target)target.value=btn.dataset.actionButton;typeof form.requestSubmit==='function'?form.requestSubmit():form.submit()}));
    qa('[data-table-search]').forEach(input=>input.addEventListener('input',()=>{const table=q(input.dataset.tableSearch);if(!table)return;const term=input.value.trim().toLowerCase();qa('tbody tr',table).forEach(row=>row.hidden=term!==''&&!row.textContent.toLowerCase().includes(term))}));
    qa('textarea[data-counter]').forEach(area=>{const target=q(area.dataset.counter);const update=()=>{if(target)target.textContent=area.value.length+'/'+(area.maxLength||500)};area.addEventListener('input',update);update()});
    qa('[data-mirror]').forEach(input=>{const targets=qa('[data-mirror-target="'+input.dataset.mirror+'"]');const update=()=>{let value=input.value;if(input.tagName==='SELECT'&&input.selectedIndex>=0)value=input.options[input.selectedIndex]?.textContent?.trim()||input.value;targets.forEach(t=>t.textContent=value||t.dataset.empty||'—')};input.addEventListener('input',update);input.addEventListener('change',update);update()});
    qa('[data-vehicle-select]').forEach(select=>{const update=()=>{const option=select.options[select.selectedIndex];const label=option&&option.value?option.textContent.trim():'À sélectionner';qa('[data-selected-vehicle-label]',select.closest('form')).forEach(x=>x.textContent=label);const img=q('[data-selected-vehicle-image]',select.closest('form'));if(img&&option?.dataset.image)img.src=option.dataset.image};select.addEventListener('change',update);update()});
    qa('input[name="base_fee"]').forEach(input=>{const form=input.closest('form');const update=()=>{const target=q('[data-pricing-summary-base]',form);if(!target)return;const value=Number(input.value||0);target.textContent=value>0?new Intl.NumberFormat('fr-FR').format(value)+' FCFA':'À renseigner'};input.addEventListener('input',update);update()});

    qa('[data-surcharge-row]').forEach(row=>{
        const toggle=q('[data-surcharge-enable]',row), input=q('[data-surcharge-value]',row);
        if(!toggle||!input)return;
        const sync=()=>{
            const enabled=toggle.checked;
            input.disabled=!enabled;
            row.classList.toggle('is-disabled',!enabled);
        };
        if(Number(input.value||0)>0)toggle.checked=true;
        toggle.addEventListener('change',sync);
        sync();
    });

    qa('[data-supplement-amount]').forEach(input=>{
        const form=input.closest('form'), type=q('select[name="calculation_type"]',form), target=q('[data-supplement-amount-preview]',form);
        const update=()=>{
            if(!target)return;
            const value=Number(input.value||0);
            if(value<=0){target.textContent='À renseigner';return;}
            const formatted=new Intl.NumberFormat('fr-FR',{maximumFractionDigits:2}).format(value);
            target.textContent=(type?.value==='percentage'?formatted+' %':formatted+' FCFA');
        };
        input.addEventListener('input',update);
        type?.addEventListener('change',update);
        update();
    });
    qa('[data-status-toggle]').forEach(toggle=>{
        const form=toggle.closest('form');
        const sync=()=>{
            const active=toggle.checked;
            const copy=q('[data-status-copy]',toggle.closest('.pricing-status-control'));
            if(copy)copy.textContent=active?'Actif':'Inactif';
            qa('[data-status-summary]',form).forEach(card=>{
                card.classList.toggle('green',active);
                const strong=q('strong',card); if(strong)strong.textContent=active?'Actif':'Inactif';
            });
            qa('[data-status-pill]',form).forEach(pill=>pill.textContent=active?'Actif':'Inactif');
        };
        toggle.addEventListener('change',sync);
        sync();
    });

    qa('[data-matrix-form]').forEach(form=>{
        const origin=q('select[name="origin_commune"]',form), destination=q('select[name="destination_commune"]',form), reverse=q('[data-apply-reverse]',form);
        if(!origin||!destination)return;
        const sync=()=>{
            const same=!!origin.value&&origin.value===destination.value;
            if(reverse){reverse.disabled=same;if(same)reverse.checked=false;}
        };
        origin.addEventListener('change',sync);destination.addEventListener('change',sync);sync();
    });

    // Tarifs par communes : sélection du départ et saisie en masse.
    const originForm=q('[data-origin-selector-form]');
    const originZone=q('[data-origin-zone-select]',originForm||document);
    const originCommune=q('[data-origin-commune-select]',originForm||document);
    if(originForm){
        // Force l'affichage de la commune réellement chargée côté serveur. Cela évite
        // qu'un ancien état restauré par le navigateur affiche Attécoubé alors que
        // la grille chargée est encore celle d'Abobo.
        if(originCommune&&originCommune.dataset.serverSelected){
            originCommune.value=originCommune.dataset.serverSelected;
        }
        if(originZone){
            originZone.addEventListener('change',()=>{
                // La liste des communes dépend de la zone Territoire sélectionnée ;
                // on recharge la page sans conserver une ancienne commune incompatible.
                if(originCommune)originCommune.disabled=true;
                originForm.submit();
            });
        }
        if(originCommune){
            originCommune.addEventListener('change',()=>{
                // Le changement de commune devient immédiatement effectif : le sélecteur,
                // le résumé et la grille ne peuvent plus représenter deux communes différentes.
                if(typeof originForm.requestSubmit==='function')originForm.requestSubmit();
                else originForm.submit();
            });
        }
    }

    qa('[data-commune-bulk-form]').forEach(form=>{
        const rows=qa('[data-bulk-row]',form);
        const search=q('[data-bulk-search]',form);
        const zone=q('[data-bulk-zone-filter]',form);
        const missingOnly=q('[data-bulk-missing-only]',form);
        const mirrorAll=q('[data-bulk-mirror-all]',form);
        const vehicleSelect=q('[data-bulk-fill-vehicle]',form);
        const fillValue=q('[data-bulk-fill-value]',form);
        const fillButton=q('[data-bulk-fill-apply]',form);

        const normalize=value=>(value||'').toString().trim().toLowerCase();
        const refreshMissing=row=>{
            const activeInputs=qa('input[data-bulk-price]:not([readonly])',row);
            const missing=activeInputs.some(input=>Number(input.value||0)<=0);
            row.dataset.missing=missing?'1':'0';
            row.classList.toggle('is-missing',missing);
            row.classList.toggle('is-complete',!missing);
            return missing;
        };
        const applyFilters=()=>{
            const term=normalize(search?.value);
            const zoneCode=(zone?.value||'').trim();
            const onlyMissing=!!missingOnly?.checked;
            rows.forEach(row=>{
                const zoneCodes=(row.dataset.zoneCodes||'').split('|').filter(Boolean);
                const destination=normalize(row.dataset.destination||row.textContent);
                const matchesTerm=!term||destination.includes(term)||normalize(row.textContent).includes(term);
                const matchesZone=!zoneCode||zoneCodes.includes(zoneCode);
                const matchesMissing=!onlyMissing||row.dataset.missing==='1';
                row.hidden=!(matchesTerm&&matchesZone&&matchesMissing);
            });
        };

        rows.forEach(row=>{
            refreshMissing(row);
            qa('input[data-bulk-price]',row).forEach(input=>input.addEventListener('input',()=>{
                refreshMissing(row);
                applyFilters();
            }));
            const active=q('[data-route-active]',row);
            active?.addEventListener('change',()=>row.classList.toggle('route-inactive',!active.checked));
            if(active)row.classList.toggle('route-inactive',!active.checked);
        });

        search?.addEventListener('input',applyFilters);
        zone?.addEventListener('change',applyFilters);
        missingOnly?.addEventListener('change',applyFilters);

        fillButton?.addEventListener('click',()=>{
            const code=vehicleSelect?.value;
            const amount=Number(fillValue?.value||0);
            if(!code||!Number.isFinite(amount)||amount<0)return;
            rows.filter(row=>!row.hidden).forEach(row=>{
                const input=q('input[data-bulk-price="'+code+'"]',row);
                if(!input||input.readOnly)return;
                input.value=amount>0?String(Math.round(amount)):'';
                input.dispatchEvent(new Event('input',{bubbles:true}));
            });
        });

        mirrorAll?.addEventListener('change',()=>{
            rows.filter(row=>!row.hidden).forEach(row=>{
                const checkbox=q('[data-mirror-row]',row);
                if(checkbox&&!checkbox.disabled)checkbox.checked=mirrorAll.checked;
            });
        });

        applyFilters();
    });

    const highlightedBulkRow=q('[data-bulk-row].is-highlighted');
    if(highlightedBulkRow){
        setTimeout(()=>{
            try{highlightedBulkRow.scrollIntoView({behavior:'smooth',block:'center'});}catch(e){highlightedBulkRow.scrollIntoView();}
        },120);
    }

    function initSimulatorMap(){
        const el=q('[data-pricing-route-map]'); if(!el||typeof L==='undefined')return;
        let points=[];try{points=JSON.parse(el.dataset.points||'[]')}catch(e){}
        if(!points.length)return;
        const map=L.map(el,{zoomControl:true,attributionControl:true,scrollWheelZoom:false});
        const cfg=window.OVANIE_MAPBOX||{};
        const mapbox=cfg.token?L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}@2x?access_token='+encodeURIComponent(cfg.token),{maxZoom:19,tileSize:256,attribution:'© Mapbox © OpenStreetMap'}):null;
        const osm=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'});
        let switched=false;
        if(mapbox){mapbox.on('tileerror',()=>{if(!switched){switched=true;try{map.removeLayer(mapbox)}catch(e){}osm.addTo(map)}});mapbox.addTo(map)}else osm.addTo(map);
        const line=L.polyline(points,{color:'#0a66d7',weight:5,opacity:.95}).addTo(map);
        L.circleMarker(points[0],{radius:9,color:'#fff',weight:3,fillColor:'#08a45f',fillOpacity:1}).addTo(map);
        L.circleMarker(points[points.length-1],{radius:9,color:'#fff',weight:3,fillColor:'#e82c41',fillOpacity:1}).addTo(map);
        map.fitBounds(line.getBounds(),{padding:[30,30]});
        setTimeout(()=>map.invalidateSize(),80);
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initSimulatorMap);else initSimulatorMap();
})();
