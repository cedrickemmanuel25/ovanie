/* Native interactions shared by the rebuilt logistics screens. */
(() => {
    'use strict';
    const all = (selector, root = document) => [...root.querySelectorAll(selector)];
    const one = (selector, root = document) => root.querySelector(selector);
    const read = id => { const el = document.getElementById(id); return el ? JSON.parse(el.textContent) : null; };
    const number = value => Number(value || 0).toLocaleString('fr-FR', {maximumFractionDigits: 2});
    const notify = message => { const el = one('.ops-toast'); el.textContent = message; el.hidden = false; clearTimeout(el.timer); el.timer = setTimeout(() => el.hidden = true, 5000); };
    const save = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); notify('Brouillon enregistré sur cet appareil.'); } catch { notify('Le navigateur ne permet pas l’enregistrement du brouillon.'); } };
    const restore = key => { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } };
    const localDate = date => { const d = new Date(date); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0,16); };
    const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const updateDates = () => all('.ops-date-styled').forEach(field => {
        const input=one('input',field),output=one('[data-date-display]',field);
        if(!input.value){output.textContent='Choisir une date';return;}
        const date=new Date(input.type==='date'?input.value+'T12:00':input.value);
        const label=date.toDateString()===new Date().toDateString()?'Aujourd’hui':date.toLocaleDateString('fr-FR');
        output.textContent=label+(input.type==='datetime-local'?', '+date.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}):'');
    });
    all('.ops-date-styled input').forEach(input=>input.addEventListener('change',updateDates));
    updateDates();
    const icon = name => { const svg = document.createElementNS('http://www.w3.org/2000/svg','svg'); svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('class','ops-icon'); svg.setAttribute('aria-hidden','true'); const use = document.createElementNS(svg.namespaceURI,'use'); use.setAttribute('href',`#ops-${name}`); svg.append(use); return svg; };
    all('[data-submit-filter]').forEach(el => el.addEventListener('change', () => el.form.requestSubmit()));
    one('[data-print]')?.addEventListener('click', () => window.print());

    // Map labels use textContent: customer and location names never become HTML.
    const maps = new Map();
    all('[data-map]').forEach(host => {
        if (!window.L) { const message = document.createElement('p'); message.className = 'ops-empty'; message.textContent = 'La carte est momentanément indisponible.'; one('.ops-map-canvas',host).append(message); return; }
        const map = L.map(one('.ops-map-canvas',host), {zoomControl:false, scrollWheelZoom:false,zoomSnap:.1}).setView([5.31,-4.015],11);
        const token=read('ops-map-config')?.token;
        const tileUrl=token?`https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}?access_token=${encodeURIComponent(token)}`:'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
        L.tileLayer(tileUrl, {maxZoom:19,attribution:(token?'&copy; Mapbox ':'')+'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
        const layer = L.layerGroup().addTo(map);
        let bounds;
        const draw = (stops, geometries=[]) => {
            layer.clearLayers();
            const points = stops.filter(p => p.lat !== null && p.lng !== null && p.lat !== '' && p.lng !== '' && Number.isFinite(Number(p.lat)) && Number.isFinite(Number(p.lng)) && Math.abs(p.lat)<=90 && Math.abs(p.lng)<=180);
            if (!points.length) return;
            points.forEach((p,i) => {
                const marker = document.createElement('span'); marker.className = `ops-map-point ${p.status || ''}`;
                if(p.kind) marker.append(icon(p.kind==='shop'?'warehouse':p.kind==='driver'?'truck':'pin')); else marker.textContent = String(i+1);
                const label = document.createElement('span'); label.textContent = p.label;
                const pin=L.marker([Number(p.lat),Number(p.lng)],{icon:L.divIcon({html:marker,className:'',iconSize:[30,30],iconAnchor:[15,15]})}).addTo(layer).bindTooltip(label,{permanent:true,direction:'right',offset:[16,0],className:'ops-map-label'});
                if(p.url){const popup=document.createElement('div'),title=document.createElement('strong'),link=document.createElement('a');title.textContent=p.label;link.textContent='Voir le suivi';link.href=p.url;link.className='ops-button ops-button-primary ops-wide';popup.append(title,link);pin.bindPopup(popup);}
                all('[data-center-mission]').filter(button=>button.dataset.centerMission===String(p.id)).forEach(button=>button.addEventListener('click',()=>{map.setView([Number(p.lat),Number(p.lng)],14);pin.openPopup();}));
            });
            // Draw only the road geometry already supplied by the shipment service.
            const coords = points.map(p=>[Number(p.lat),Number(p.lng)]);
            geometries.forEach(geometry=>{
                if(geometry?.type!=='LineString'||!Array.isArray(geometry.coordinates))return;
                L.geoJSON(geometry,{style:{color:'#fff',weight:6}}).addTo(layer);
                L.geoJSON(geometry,{style:{color:'#0877ff',weight:3,opacity:.95}}).addTo(layer);
            });
            bounds = L.latLngBounds(coords); map.fitBounds(bounds,{paddingTopLeft:[50,35],paddingBottomRight:[100,40],maxZoom:12});
        };
        draw(JSON.parse(host.dataset.mapPoints || '[]'),JSON.parse(host.dataset.mapGeometry || '[]'));
        maps.set(host,{map,draw});
        all('[data-map-zoom]',host).forEach(button => button.addEventListener('click',()=>map.setZoom(map.getZoom()+Number(button.dataset.mapZoom))));
        one('[data-map-center]',host)?.addEventListener('click',()=>bounds?map.fitBounds(bounds,{padding:[45,45]}):map.setView([5.31,-4.015],11));
        one('[data-map-fullscreen]',host)?.addEventListener('click',async()=>{ try { if(document.fullscreenElement) await document.exitFullscreen(); else await host.requestFullscreen(); } catch { notify('Le plein écran n’est pas disponible dans ce navigateur.'); } });
        document.addEventListener('fullscreenchange',()=>map.invalidateSize());
        new ResizeObserver(()=>map.invalidateSize()).observe(host);
    });

    const fullDrivers=read('ops-full-driver-data');
    if(fullDrivers){
        const form=one('#ops-full-assignment');
        const set=(key,value)=>all(`[data-selected-driver="${key}"]`).forEach(el=>el.textContent=value);
        const selectedDriver=()=>fullDrivers.find(d=>String(d.id)===form.elements.driver_id.value);
        const update=()=>{
            const driver=selectedDriver();
            if(!driver)return;
            set('name',driver.name);set('vehicle',driver.vehicle);set('availability',driver.available?'Disponible':'Occupé');set('compatibility',driver.compatible?'Conforme':'À vérifier');set('pickup',driver.pickupEta===null?'À confirmer':driver.pickupEta+' min');set('client',driver.clientEta===null?'À confirmer':driver.clientEta+' min');
            const gps=one('[data-preview-gps]');
            if(gps)gps.textContent=driver.gpsRecordedAt?new Date(driver.gpsRecordedAt).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):'Position GPS à confirmer';
            const pickupDistance=one('[data-preview-pickup-distance]');
            if(pickupDistance)pickupDistance.textContent=driver.pickupDistanceKm!=null?`${number(driver.pickupDistanceKm)} km`:'À confirmer';
            const clientDistance=one('[data-preview-client-distance]');
            if(clientDistance)clientDistance.textContent=driver.clientDistanceKm!=null?`${number(driver.clientDistanceKm)} km`:'À confirmer';
        };
        const dates=()=>{
            const driver=selectedDriver(),pickup=form.elements.pickup_scheduled_at,delivery=form.elements.estimated_delivery_at;
            delivery.min=pickup.value;
            const pickupPreview=one('[data-preview-pickup]'),deliveryPreview=one('[data-preview-delivery]');
            pickupPreview.textContent=driver?.pickupEta!=null&&pickup.value?new Date(pickup.value).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}):'À confirmer';
            deliveryPreview.textContent=driver?.pickupEta!=null&&driver?.clientEta!=null&&delivery.value?new Date(delivery.value).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}):'À confirmer';
        };
        const syncScheduleFromDriver=()=>{
            const driver=selectedDriver(),pickup=form.elements.pickup_scheduled_at,delivery=form.elements.estimated_delivery_at;
            if(!driver)return;
            if(driver.pickupEta!=null){
                const now=new Date();
                const min=pickup.min?new Date(pickup.min):now;
                let predictedPickup=new Date(now.getTime()+Number(driver.pickupEta)*60000);
                if(predictedPickup<min)predictedPickup=min;
                pickup.value=localDate(predictedPickup);
            } else {
                pickup.value='';
            }
            if(driver.clientEta!=null&&pickup.value){
                const predictedDelivery=new Date(pickup.value);
                predictedDelivery.setMinutes(predictedDelivery.getMinutes()+Number(driver.clientEta));
                delivery.value=localDate(predictedDelivery);
            } else {
                delivery.value='';
            }
        };
        const syncDeliveryEta=()=>{const driver=selectedDriver(),pickup=form.elements.pickup_scheduled_at,delivery=form.elements.estimated_delivery_at;if(!driver||driver.clientEta===null||!pickup.value)return;const date=new Date(pickup.value);date.setMinutes(date.getMinutes()+Number(driver.clientEta));delivery.value=localDate(date);};
        const plate=()=>{const driver=selectedDriver();form.elements.vehicle_plate.value=driver?.vehicle.match(/\(([A-Z0-9-]+)\)/i)?.[1]||'';};
        form.addEventListener('change',event=>{
            if(event.target.name==='driver_id'){plate();syncScheduleFromDriver();}
            if(event.target.name==='pickup_scheduled_at')syncDeliveryEta();
            update();dates();
        });
        update();if(!form.elements.vehicle_plate.value)plate();syncScheduleFromDriver();dates();
        let driverPage=0;const driverPageSize=3;
        const filter=()=>{const matching=all('[data-driver-option]').filter(row=>{const d=fullDrivers.find(d=>String(d.id)===row.dataset.driverOption);const q=normalize(one('[data-driver-search]').value),availability=one('[data-driver-availability]').value,zone=one('[data-driver-zone]').value,compatible=one('[data-driver-compatible]').value;return normalize(d.name+' '+d.vehicle+' '+d.zone).includes(q)&&(!zone||d.zone===zone)&&(!compatible||d.compatible)&&(!availability||(availability==='available'?d.available:!d.available));});driverPage=Math.min(driverPage,Math.max(0,Math.ceil(matching.length/driverPageSize)-1));const visible=new Set(matching.slice(driverPage*driverPageSize,(driverPage+1)*driverPageSize));all('[data-driver-option]').forEach(row=>row.hidden=!visible.has(row));one('[data-no-drivers]').hidden=matching.length>0;one('[data-driver-page-count]').textContent=matching.length?`${driverPage*driverPageSize+1} – ${Math.min((driverPage+1)*driverPageSize,matching.length)} sur ${matching.length} livreurs`:'0 livreur';one('[data-driver-page-previous]').disabled=driverPage===0;one('[data-driver-page-next]').disabled=(driverPage+1)*driverPageSize>=matching.length;};
        all('.ops-driver-filters input,.ops-driver-filters select').forEach(el=>el.addEventListener(el.tagName==='INPUT'?'input':'change',()=>{driverPage=0;filter();}));
        one('[data-driver-page-previous]').addEventListener('click',()=>{driverPage--;filter();});one('[data-driver-page-next]').addEventListener('click',()=>{driverPage++;filter();});filter();
    }
    const tours = read('ops-tour-data');
    if (tours) {
        const controls = all('[data-tour-filters] [data-filter]');
        const applyFilters = () => {
            const filters = Object.fromEntries(controls.map(el => [el.dataset.filter, el.value]));
            let visible = 0;
            all('[data-tour-row]').forEach(row => {
                const tour = tours.find(item => String(item.id) === row.dataset.tourRow);
                if (!tour) { row.hidden = true; return; }
                const matches = Object.entries(filters).every(([key, value]) => {
                    if (!value) return true;
                    if (key === 'search') return normalize([tour.reference, tour.driver?.name, tour.vehicle, tour.communes, tour.zone].join(' ')).includes(normalize(value));
                    if (key === 'driver') return String(tour.driver?.name ?? '') === value;
                    return String(tour[key] ?? '') === value;
                });
                row.hidden = !matches;
                if (matches) visible++;
            });
            const empty = one('[data-no-tours]');
            if (empty) empty.hidden = visible > 0 || tours.length === 0;
        };
        controls.forEach(el => el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', applyFilters));
        one('[data-reset-tours]')?.addEventListener('click', () => { controls.forEach(el => el.value = ''); applyFilters(); });

        const openTour = row => {
            const url = row?.dataset.tourSelectUrl;
            if (url && window.location.href !== url) window.location.assign(url);
        };
        all('[data-tour-row]').forEach(row => {
            row.addEventListener('click', event => {
                if (event.target.closest('a,button,input,select,textarea,form')) return;
                openTour(row);
            });
            row.addEventListener('keydown', event => {
                if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,textarea')) {
                    event.preventDefault();
                    openTour(row);
                }
            });
        });

        const editor = one('[data-route-order-editor]');
        const orderList = one('[data-route-order-list]');
        const renumberOrder = () => all('[data-order-stop]', orderList || document).forEach((row, index) => {
            const numberEl = one('[data-order-number]', row);
            if (numberEl) numberEl.textContent = String(index + 1);
        });
        one('[data-toggle-route-order]')?.addEventListener('click', () => {
            if (!editor) return;
            editor.hidden = false;
            editor.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
        one('[data-cancel-route-order]')?.addEventListener('click', () => { if (editor) editor.hidden = true; });
        orderList?.addEventListener('click', event => {
            const button = event.target.closest('[data-order-up],[data-order-down]');
            if (!button) return;
            const row = button.closest('[data-order-stop]');
            if (!row) return;
            if (button.hasAttribute('data-order-up') && row.previousElementSibling) orderList.insertBefore(row, row.previousElementSibling);
            if (button.hasAttribute('data-order-down') && row.nextElementSibling) orderList.insertBefore(row.nextElementSibling, row);
            renumberOrder();
        });

        one('[data-export-tours]')?.addEventListener('click', () => {
            const visible = new Set(all('[data-tour-row]').filter(el => !el.hidden).map(el => el.dataset.tourRow));
            const cell = value => '"' + String(value ?? '').replace(/^[=+@-]/, "'$&").replaceAll('"', '""') + '"';
            const rows = [['Tournée','Livreur','Véhicule','Livraisons','Communes','Statut','Départ'], ...tours.filter(t => visible.has(String(t.id))).map(t => [t.reference,t.driver?.name,t.vehicle,t.total,t.communes,t.status,t.departure])];
            const url = URL.createObjectURL(new Blob(['\ufeff' + rows.map(r => r.map(cell).join(';')).join('\r\n')], {type:'text/csv;charset=utf-8'}));
            const a = document.createElement('a'); a.href = url; a.download = 'tournees.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        });
    }

    const plan = read('ops-plan-data');
    if (plan) {
        const form = one('#ops-create-form');
        const selections = all('[data-select-mission]');
        const driverSelect = form.elements.driver_id;
        const vehicleSelect = form.elements.fleet_vehicle_id;
        const optimizeToggle = form.querySelector('input[type=checkbox][name=optimization_enabled]');
        const stopList = one('[data-plan-stops]');
        const stopInputs = one('[data-stop-order-inputs]');
        const routeToolbar = one('[data-manual-route-toolbar]');
        const editButton = one('[data-edit-stops]');
        const recalcButton = one('[data-recalculate-order]');
        let currentPlan = null;
        let routeDirty = false;
        let editingOrder = false;

        const selectedMissions = () => plan.missions.filter(mission => selections.some(input => input.checked && Number(input.dataset.selectMission) === Number(mission.id)));
        const totals = () => {
            const chosen = selectedMissions();
            return {
                chosen,
                weight: chosen.reduce((sum, mission) => sum + Number(mission.weight || 0), 0),
                volume: chosen.reduce((sum, mission) => sum + Number(mission.volume || 0), 0),
                products: chosen.reduce((sum, mission) => sum + Number(mission.productsCount || 0), 0),
            };
        };
        const vehicleRank = {moto:1,tricycle:2,pickup:3,camion_3t:4,camion_10t:5};
        const requiredVehicle = (weight, volume) => (plan.vehicleRules || []).find(rule => {
            const maxWeight = rule.max_weight_kg == null ? Infinity : Number(rule.max_weight_kg);
            const maxVolume = rule.max_volume_m3 == null ? Infinity : Number(rule.max_volume_m3);
            return weight <= maxWeight && volume <= maxVolume;
        }) || (plan.vehicleRules || []).at(-1) || null;
        const selectedDriver = () => (plan.drivers || []).find(driver => String(driver.id) === String(driverSelect.value));
        const selectedVehicle = () => (plan.vehicles || []).find(vehicle => String(vehicle.id) === String(vehicleSelect.value));
        const setText = (selector, value) => { const el = one(selector); if (el) el.textContent = value; };
        const setKpi = (key, value) => { const el = one(`[data-kpi="${key}"] .ops-kpi-value`); if (el) el.textContent = value; };
        const setBadge = (element, text, good = null) => {
            if (!element) return;
            element.textContent = text;
            element.classList.toggle('is-green', good === true);
            element.classList.toggle('is-red', good === false);
        };
        const invalidateRoute = () => {
            currentPlan = null;
            routeDirty = false;
            editingOrder = false;
            if (routeToolbar) routeToolbar.hidden = true;
            if (editButton) editButton.disabled = true;
            setText('[data-route-state]', 'Parcours non calculé');
            setBadge(one('[data-optimization-status]'), 'À calculer');
            setText('[data-summary="distance"]', 'À calculer');
            setText('[data-summary="duration"]', 'À calculer');
            setText('[data-summary="provider"]', '—');
            setKpi('duration', 'À calculer');
            stopInputs?.replaceChildren();
            if (stopList) {
                stopList.replaceChildren();
                const li = document.createElement('li'); li.className = 'ops-empty-route';
                const label = document.createElement('span'); label.textContent = 'Parcours à recalculer.';
                const small = document.createElement('small'); small.textContent = 'Cliquez sur « Calculer & optimiser » pour obtenir les distances et ETA réels.';
                li.append(label, small); stopList.append(li);
            }
            all('[data-map]').forEach(host => maps.get(host)?.draw([], []));
        };
        const syncMissionInputs = () => {
            const holder = one('[data-mission-inputs]');
            holder?.replaceChildren();
            [...new Set(selectedMissions().flatMap(mission => mission.itemIds || []))].forEach(id => {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = 'mission_items[]'; input.value = String(id); holder?.append(input);
            });
        };
        const rebuildVehicles = () => {
            const {weight, volume} = totals();
            const required = requiredVehicle(weight, volume);
            const driver = selectedDriver();
            vehicleSelect.replaceChildren();
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = driver ? 'Véhicule du livreur à vérifier' : 'Choisir d’abord un livreur';
            vehicleSelect.append(placeholder);

            if (!driver) {
                vehicleSelect.value = '';
                setText('[data-vehicle-help]', 'Le véhicule attribué s’affichera automatiquement après le choix du livreur. Les motos sont exclues des tournées.');
                setKpi('vehicle', required ? `${required.label} minimum` : 'Tricycle minimum');
                return;
            }

            const assignedVehicle = (plan.vehicles || []).find(vehicle => String(vehicle.id) === String(driver.tourVehicleId));
            const requiredRank = vehicleRank[required?.code] || vehicleRank.tricycle;
            const vehicleCode = assignedVehicle?.type_code;
            const capacity = assignedVehicle && Number(assignedVehicle.capacity_kg || 0) >= weight && (volume <= 0 || Number(assignedVehicle.volume_m3 || 0) <= 0 || Number(assignedVehicle.volume_m3) >= volume);
            const typeAllowed = assignedVehicle && vehicleCode !== 'moto' && (vehicleRank[vehicleCode] || 0) >= requiredRank;
            const eligible = Boolean(assignedVehicle && assignedVehicle.status === 'available' && capacity && typeAllowed);

            if (assignedVehicle) {
                const option = document.createElement('option');
                option.value = String(assignedVehicle.id);
                option.textContent = `${assignedVehicle.type} ${[assignedVehicle.brand,assignedVehicle.model].filter(Boolean).join(' ')} — ${assignedVehicle.registration} — ${number(assignedVehicle.capacity_kg)} kg`;
                option.disabled = !eligible;
                vehicleSelect.append(option);
                if (eligible) vehicleSelect.value = String(assignedVehicle.id);
            }

            if (!assignedVehicle) {
                setText('[data-vehicle-help]', 'Aucun véhicule de flotte disponible n’est réellement attribué à ce livreur.');
            } else if (vehicleCode === 'moto') {
                setText('[data-vehicle-help]', 'Ce livreur utilise une moto : il ne peut pas être affecté à une tournée.');
            } else if (!capacity || !typeAllowed) {
                setText('[data-vehicle-help]', `Le véhicule du livreur est insuffisant. Minimum requis pour cette tournée : ${required?.label || 'Tricycle'}.`);
            } else {
                setText('[data-vehicle-help]', `Véhicule réel de ${driver.name} sélectionné automatiquement.`);
            }
            setKpi('vehicle', assignedVehicle && eligible ? assignedVehicle.type : (required ? `${required.label} minimum` : 'Tricycle minimum'));
        };
        const updateDriverVehicleLoad = () => {
            const {weight, volume, products, chosen} = totals();
            const required = requiredVehicle(weight, volume);
            const driver = selectedDriver();
            const vehicle = selectedVehicle();
            setKpi('count', String(chosen.length)); setKpi('weight', `${number(weight)} kg`); setKpi('volume', `${number(volume)} m³`); setKpi('vehicle', required?.label || 'À déterminer');
            setText('[data-summary="count"]', String(chosen.length)); setText('[data-summary="products"]', String(products));
            setText('[data-plan-driver-name]', driver?.name || 'Non affecté'); setText('[data-plan-driver-rating]', driver?.rating || '—'); setText('[data-plan-driver-zone]', driver?.zone || 'Zone à confirmer');
            setBadge(one('[data-plan-driver-status]'), driver ? (driver.available ? 'Disponible' : 'Indisponible') : 'Choisir un livreur', driver ? Boolean(driver.available) : null);
            setBadge(one('[data-driver-available]'), driver ? (driver.available ? 'Livreur disponible' : 'Livreur indisponible') : 'Choisir un livreur', driver ? Boolean(driver.available) : null);
            const call = one('[data-plan-driver-call]'); if (call) { call.hidden = !driver?.phone; if (driver?.phone) call.href = `tel:${driver.phone}`; }
            const vehicleName = vehicle ? `${vehicle.type} ${[vehicle.brand,vehicle.model].filter(Boolean).join(' ')} · ${vehicle.registration}` : 'Non attribué';
            setText('[data-plan-vehicle-text]', vehicleName);
            const vehicleGood = Boolean(vehicle && (!required || vehicle.type_code === required.code) && Number(vehicle.capacity_kg || 0) >= weight);
            setBadge(one('[data-plan-vehicle-state]'), vehicle ? (vehicleGood ? 'Compatible' : 'Incompatible') : 'À sélectionner', vehicle ? vehicleGood : null);
            setBadge(one('[data-vehicle-compatible]'), vehicle ? (vehicleGood ? 'Véhicule compatible' : 'Véhicule incompatible') : 'Choisir un véhicule', vehicle ? vehicleGood : null);
            const weightPct = vehicle?.capacity_kg ? Math.min(100, Math.round(weight / Number(vehicle.capacity_kg) * 100)) : 0;
            const volumePct = vehicle?.volume_m3 ? Math.min(100, Math.round(volume / Number(vehicle.volume_m3) * 100)) : 0;
            setText('[data-plan-load="weight"]', vehicle ? `${number(weight)} / ${number(vehicle.capacity_kg)} kg` : `${number(weight)} kg`);
            setText('[data-plan-load="volume"]', vehicle ? `${number(volume)} / ${number(vehicle.volume_m3)} m³` : `${number(volume)} m³`);
            const wbar = one('[data-plan-load-bar="weight"]'); const vbar = one('[data-plan-load-bar="volume"]'); if (wbar) wbar.style.width = `${weightPct}%`; if (vbar) vbar.style.width = `${volumePct}%`;
            setText('[data-summary="fill"]', vehicle ? `${Math.max(weightPct, volumePct)} %` : '—');
        };
        const refreshInputs = ({invalidate = true, rebuild = false} = {}) => {
            syncMissionInputs();
            if (rebuild) rebuildVehicles();
            updateDriverVehicleLoad();
            const selectAll = one('[data-select-all]');
            if (selectAll) { const visible = selections.filter(input => !input.closest('tr')?.hidden); selectAll.checked = visible.length > 0 && visible.every(input => input.checked); selectAll.indeterminate = visible.some(input => input.checked) && !selectAll.checked; }
            if (invalidate) invalidateRoute();
        };
        const renderStopOrderInputs = stops => {
            stopInputs?.replaceChildren();
            (stops || []).forEach(stop => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'stop_order[]'; input.value = stop.key; stopInputs?.append(input); });
        };
        const renderRoute = data => {
            currentPlan = data;
            routeDirty = false;
            editingOrder = false;
            if (routeToolbar) routeToolbar.hidden = true;
            if (editButton) editButton.disabled = !(data.stops?.length > 1);
            setText('[data-route-state]', data.complete ? (data.optimized ? 'Parcours optimisé avec données réelles' : 'Ordre manuel recalculé') : 'Parcours incomplet');
            setBadge(one('[data-optimization-status]'), data.complete ? (data.optimized ? 'Optimisé' : 'Ordre manuel') : 'À vérifier', data.complete);
            setText('[data-summary="distance"]', data.distance != null ? `${number(data.distance)} km` : 'À confirmer');
            setText('[data-summary="duration"]', data.duration != null ? (data.duration >= 60 ? `${Math.floor(data.duration/60)} h ${String(data.duration%60).padStart(2,'0')} min` : `${data.duration} min`) : 'À confirmer');
            setText('[data-summary="provider"]', data.routingProvider || 'À confirmer');
            setText('[data-summary="fill"]', `${data.fillPercent ?? 0} %`);
            setKpi('duration', data.duration != null ? (data.duration >= 60 ? `${Math.floor(data.duration/60)} h ${String(data.duration%60).padStart(2,'0')} min` : `${data.duration} min`) : 'À confirmer');
            if (stopList) {
                stopList.replaceChildren();
                const start = document.createElement('li'); start.className = 'ops-route-stop is-start';
                const n = document.createElement('b'); n.className = 'ops-step is-green'; n.textContent = 'D';
                const text = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = data.start?.label || 'Départ livreur'; const small = document.createElement('small'); small.textContent = `Départ · ${form.elements.departure_time.value || '—'}`; text.append(strong, small); start.append(n, text); stopList.append(start);
                (data.stops || []).forEach((stop, index) => {
                    const li = document.createElement('li'); li.className = `ops-route-stop ${stop.kind === 'shop' ? 'is-pickup' : 'is-delivery'}`; li.dataset.planStopKey = stop.key;
                    const step = document.createElement('b'); step.className = `ops-step ${stop.kind === 'shop' ? 'is-green' : 'is-blue'}`; step.textContent = String(index + 1);
                    const content = document.createElement('div'); const title = document.createElement('strong'); title.textContent = stop.label; const meta = document.createElement('small'); meta.textContent = `${stop.kind === 'shop' ? 'Collecte boutique' : 'Livraison client'} · ETA ${stop.time || 'À confirmer'}${stop.distanceFromPrevious != null ? ` · ${number(stop.distanceFromPrevious)} km` : ''}`; content.append(title, meta);
                    const actions = document.createElement('div'); actions.className = 'ops-order-buttons'; actions.hidden = !editingOrder;
                    const up = document.createElement('button'); up.type = 'button'; up.dataset.planOrderUp = ''; up.textContent = '↑'; up.setAttribute('aria-label', `Monter ${stop.label}`);
                    const down = document.createElement('button'); down.type = 'button'; down.dataset.planOrderDown = ''; down.textContent = '↓'; down.setAttribute('aria-label', `Descendre ${stop.label}`); actions.append(up, down);
                    li.append(step, content, actions); stopList.append(li);
                });
            }
            renderStopOrderInputs(data.stops || []);
            const mapPoints = [data.start, ...(data.stops || [])].filter(Boolean);
            all('[data-map]').forEach(host => maps.get(host)?.draw(mapPoints, data.routeSegments || []));
        };
        const routeRequest = async (manual = false) => {
            const {chosen} = totals(); const driver = selectedDriver(); const vehicle = selectedVehicle();
            if (!chosen.length) { notify('Sélectionnez au moins une mission prête.'); return false; }
            if (!driver) { notify('Choisissez un livreur disponible.'); return false; }
            if (!vehicle) { notify('Choisissez un véhicule réel compatible dans la flotte.'); return false; }
            const body = new FormData(form); body.delete('stop_order[]');
            if (manual && currentPlan?.stops) currentPlan.stops.forEach(stop => body.append('stop_order[]', stop.key));
            const actionButton = manual ? recalcButton : one('[data-optimize]'); if (actionButton) actionButton.disabled = true;
            setText('[data-route-state]', 'Calcul du parcours routier…');
            try {
                const response = await fetch(plan.previewUrl, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, body});
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                    throw new Error(first || payload.message || 'Le parcours n’a pas pu être calculé.');
                }
                renderRoute(payload.plan); return true;
            } catch (error) {
                setText('[data-route-state]', 'Calcul impossible'); notify(error?.message || 'Le parcours routier n’a pas pu être calculé avec les données actuelles.'); return false;
            } finally { if (actionButton) actionButton.disabled = false; }
        };

        selections.forEach(input => input.addEventListener('change', () => refreshInputs({rebuild:true})));
        one('[data-select-all]')?.addEventListener('change', event => { selections.filter(input => !input.closest('tr')?.hidden).forEach(input => input.checked = event.target.checked); refreshInputs({rebuild:true}); });
        driverSelect.addEventListener('change', () => { rebuildVehicles(); updateDriverVehicleLoad(); invalidateRoute(); });
        vehicleSelect.addEventListener('change', () => { updateDriverVehicleLoad(); invalidateRoute(); });
        form.elements.tour_date.addEventListener('change', invalidateRoute); form.elements.departure_time.addEventListener('change', invalidateRoute);
        optimizeToggle?.addEventListener('change', invalidateRoute);
        const filters = all('[data-mission-filter]');
        const filterMissions = () => {
            let visible = 0;
            all('[data-mission-row]').forEach(row => {
                const mission = plan.missions.find(item => String(item.id) === row.dataset.missionRow);
                const show = mission && filters.every(el => !el.value || (el.dataset.missionFilter === 'search' ? normalize(`${mission.reference} ${mission.order} ${mission.destination} ${mission.client}`).includes(normalize(el.value)) : String(mission[el.dataset.missionFilter] ?? '') === el.value));
                row.hidden = !show; if (show) visible++;
            });
            const empty = one('[data-no-missions]'); if (empty) empty.hidden = visible > 0;
            refreshInputs({invalidate:false});
        };
        filters.forEach(el => el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', filterMissions));
        one('[data-reset-missions]')?.addEventListener('click', () => { filters.forEach(el => el.value = ''); filterMissions(); });
        one('[data-save-tour]')?.addEventListener('click', () => save('ovanie:tour-draft', {driver_id:driverSelect.value,fleet_vehicle_id:vehicleSelect.value,tour_date:form.elements.tour_date.value,departure_time:form.elements.departure_time.value,zone_label:form.elements.zone_label.value,ids:selectedMissions().map(m => Number(m.id))}));
        one('[data-optimize]')?.addEventListener('click', () => routeRequest(false));
        editButton?.addEventListener('click', () => {
            if (!currentPlan?.stops?.length) { notify('Calculez d’abord le parcours.'); return; }
            editingOrder = !editingOrder; all('.ops-order-buttons', stopList).forEach(actions => actions.hidden = !editingOrder); if (routeToolbar) routeToolbar.hidden = !editingOrder; editButton.textContent = editingOrder ? 'Terminer la modification' : 'Modifier l’ordre';
        });
        stopList?.addEventListener('click', event => {
            const button = event.target.closest('[data-plan-order-up],[data-plan-order-down]'); if (!button || !currentPlan?.stops) return;
            const row = button.closest('[data-plan-stop-key]'); const key = row?.dataset.planStopKey; const index = currentPlan.stops.findIndex(stop => stop.key === key); if (index < 0) return;
            const target = button.hasAttribute('data-plan-order-up') ? index - 1 : index + 1; if (target < 0 || target >= currentPlan.stops.length) return;
            [currentPlan.stops[index], currentPlan.stops[target]] = [currentPlan.stops[target], currentPlan.stops[index]];
            routeDirty = true; currentPlan.optimized = false; renderRoute(currentPlan); editingOrder = true; routeDirty = true; all('.ops-order-buttons', stopList).forEach(actions => actions.hidden = false); if (routeToolbar) routeToolbar.hidden = false; setText('[data-route-state]', 'Ordre modifié — ETA à recalculer');
        });
        recalcButton?.addEventListener('click', () => routeRequest(true));
        form.addEventListener('submit', event => {
            if (!selectedMissions().length) { event.preventDefault(); notify('Sélectionnez au moins une mission réelle.'); return; }
            if (!selectedDriver()) { event.preventDefault(); notify('Choisissez un livreur disponible.'); return; }
            if (!selectedVehicle()) { event.preventDefault(); notify('Choisissez un véhicule réel de la flotte.'); return; }
            if (routeDirty) { event.preventDefault(); notify('L’ordre des arrêts a changé. Cliquez sur « Recalculer les ETA » avant de créer la tournée.'); }
        });

        const draft = restore('ovanie:tour-draft');
        if (draft && !selections.some(input => input.checked) && !driverSelect.value) {
            selections.forEach(input => input.checked = (draft.ids || []).includes(Number(input.dataset.selectMission)));
            for (const key of ['driver_id','tour_date','departure_time','zone_label']) if (form.elements[key] && draft[key]) form.elements[key].value = draft[key];
        }
        syncMissionInputs(); rebuildVehicles();
        if (draft?.fleet_vehicle_id && [...vehicleSelect.options].some(option => option.value === String(draft.fleet_vehicle_id))) vehicleSelect.value = String(draft.fleet_vehicle_id);
        updateDriverVehicleLoad();
        if (selectedMissions().length) invalidateRoute();
    }

    const missionData = read('ops-mission-data');
    if (missionData) {
        const dialog=one('#ops-assignment-dialog');
        const form=one('#ops-assignment-form');
        const driverInput=one('#ops-assignment-driver');
        const driverList=one('[data-assignment-driver-list]',dialog);
        const candidateCount=one('[data-driver-candidate-count]',dialog);
        const pickerCaption=one('[data-driver-picker-caption]',dialog);
        const selectedDriverBox=one('[data-assignment-selected-driver]',dialog);
        const missionPicker=one('[data-assignment-mission-picker]',dialog);
        const missionList=one('[data-assignment-mission-list]',dialog);
        const missionDetails=one('[data-assignment-mission-details]',dialog);
        const controlsBody=one('[data-assignment-controls-body]',dialog);
        const dialogSubtitle=one('[data-assignment-dialog-subtitle]',dialog);
        const pickup=form.elements.pickup_scheduled_at;
        const delivery=form.elements.estimated_delivery_at;
        const submitButton=form.querySelector('button[type=submit]');
        const saveButton=one('[data-save-assignment]',dialog);
        const advancedLink=one('[data-advanced-assignment]',dialog);
        const assignmentMissions=Array.isArray(missionData.assignmentMissions)
            ? missionData.assignmentMissions
            : (Array.isArray(missionData.missions)?missionData.missions:[]);

        let mission=null;
        let drivers=[];
        let opener=null;
        let loading=false;

        const assignableStatuses=['pending','ready_for_pickup','delivery_failed'];

        const deliveryDisplay=()=>{
            const display=one('[data-delivery-display]',dialog);
            if(!display)return;
            display.textContent=delivery.value
                ? new Date(delivery.value).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})
                : '—';
        };

        const setBadgeState=(element,good)=>{
            if(!element)return;
            element.classList.toggle('is-green',Boolean(good));
            element.classList.toggle('is-red',good===false);
        };

        const clearSelectedDriver=()=>{
            driverInput.value='';
            selectedDriverBox.hidden=true;
            all('.ops-driver-candidate',driverList).forEach(card=>card.classList.remove('is-selected'));
            all('[data-driver]',dialog).forEach(el=>{
                const key=el.dataset.driver;
                el.textContent=(key==='pickupEtaLabel'||key==='clientEtaLabel')?'—':'—';
            });
            const availability=one('[data-driver-availability]',dialog);
            const online=one('[data-driver-online]',dialog);
            if(availability){availability.textContent='—';availability.classList.remove('is-red');availability.classList.add('is-green');}
            if(online){online.textContent='—';online.classList.remove('is-red');online.classList.add('is-green');}
            const compatibility=one('[data-assignment-compatibility]',dialog);
            const capacity=one('[data-assignment-capacity]',dialog);
            if(compatibility){
                compatibility.querySelector('span').textContent='Véhicule à confirmer';
                compatibility.classList.remove('is-red');compatibility.classList.add('is-green');
            }
            if(capacity){
                capacity.querySelector('span').textContent='Charge à confirmer';
                capacity.classList.remove('is-red');capacity.classList.add('is-green');
            }
            one('[data-assignment-plate]',dialog).value='';
            submitButton.disabled=true;
            deliveryDisplay();
        };

        const renderMission=()=>{
            const values={
                ...mission,
                weightLabel:`${number(mission?.weight)} kg`,
                referencesCount:mission?.referencesCount??mission?.products?.length??0,
            };
            all('[data-assignment]',dialog).forEach(el=>el.textContent=values[el.dataset.assignment]??'—');

            const products=one('[data-assignment-products]',dialog);
            products.replaceChildren();
            (mission?.products||[]).forEach(text=>{
                const li=document.createElement('li');
                li.textContent=text;
                products.append(li);
            });
            if(!(mission?.products||[]).length){
                const li=document.createElement('li');
                li.textContent='Aucun article dans cette mission';
                products.append(li);
            }
        };

        const missionOption=(row)=>{
            const button=document.createElement('button');
            button.type='button';
            button.className='ops-assignment-mission-option';
            button.dataset.assignmentMissionId=String(row.id);

            const head=document.createElement('span');
            head.className='ops-assignment-mission-option-head';
            const ref=document.createElement('strong');
            ref.textContent=row.reference||'Mission';
            const status=document.createElement('span');
            status.className='ops-badge is-orange';
            status.textContent='À affecter';
            head.append(ref,status);

            const order=document.createElement('small');
            order.textContent=`Commande ${row.order||'—'}`;

            const meta=document.createElement('span');
            meta.className='ops-assignment-mission-option-meta';
            const destination=document.createElement('span');
            destination.textContent=row.destination||'Destination à préciser';
            const vehicle=document.createElement('span');
            vehicle.textContent=`${row.vehicle||'Véhicule à préciser'} · ${number(row.weight)} kg`;
            meta.append(destination,vehicle);

            button.append(head,order,meta);
            button.addEventListener('click',()=>open(row.id,button));
            return button;
        };

        const showMissionPicker=(trigger)=>{
            opener=trigger||opener;
            mission=null;
            drivers=[];
            form.action='';
            form.reset();
            driverInput.value='';
            controlsBody.disabled=true;
            missionPicker.hidden=false;
            missionDetails.hidden=true;
            advancedLink.hidden=true;
            advancedLink.href='#';
            saveButton.disabled=true;
            submitButton.disabled=true;
            clearSelectedDriver();
            driverList.replaceChildren();
            const empty=document.createElement('p');
            empty.className='ops-assignment-empty';
            empty.textContent='Sélectionnez une mission pour afficher les livreurs disponibles.';
            driverList.append(empty);
            candidateCount.textContent='0';
            pickerCaption.textContent='Choisissez d’abord une mission.';
            dialogSubtitle.textContent='Aucune commande n’est sélectionnée automatiquement. Choisissez la mission à affecter.';

            missionList.replaceChildren();
            const eligible=assignmentMissions.filter(row=>assignableStatuses.includes(row.status));
            eligible.forEach(row=>missionList.append(missionOption(row)));
            if(!eligible.length){
                const emptyMission=document.createElement('p');
                emptyMission.className='ops-assignment-empty';
                emptyMission.textContent='Aucune mission réelle n’est actuellement prête à être affectée.';
                missionList.append(emptyMission);
            }

            if(!dialog.open){
                dialog.showModal();
                dialog.focus({preventScroll:true});
                document.body.style.overflow='hidden';
            }
        };

        const candidateCard=(driver,index)=>{
            const button=document.createElement('button');
            button.type='button';
            button.className='ops-driver-candidate';
            button.dataset.driverCandidate=String(driver.id);

            const top=document.createElement('span');
            top.className='ops-driver-candidate-main';

            const avatar=document.createElement('span');
            avatar.className='ops-driver-candidate-avatar';
            avatar.textContent=driver.initials||driver.name?.split(/\s+/).map(v=>v[0]).slice(0,2).join('').toUpperCase()||'LV';

            const identity=document.createElement('span');
            identity.className='ops-driver-candidate-identity';
            const name=document.createElement('strong');
            name.textContent=driver.name||'Livreur';
            const vehicle=document.createElement('small');
            vehicle.textContent=driver.vehicle||'Véhicule non renseigné';
            identity.append(name,vehicle);

            const eta=document.createElement('span');
            eta.className='ops-driver-candidate-eta';
            const etaStrong=document.createElement('strong');
            etaStrong.textContent=driver.pickupEta!=null?`${driver.pickupEta} min`:'À confirmer';
            const etaSmall=document.createElement('small');
            etaSmall.textContent='ETA collecte';
            eta.append(etaStrong,etaSmall);

            top.append(avatar,identity,eta);

            const meta=document.createElement('span');
            meta.className='ops-driver-candidate-meta';
            const zone=document.createElement('span');
            zone.textContent=driver.zoneMatch?`Dans la zone · ${driver.zone||'—'}`:`Zone ${driver.zone||'à confirmer'}`;
            const reco=document.createElement('span');
            reco.textContent=driver.recommendation||'Disponible';
            meta.append(zone,reco);

            const badges=document.createElement('span');
            badges.className='ops-driver-candidate-badges';
            const online=document.createElement('span');
            online.className=`ops-badge ${driver.online?'is-green':'is-gray'}`;
            online.textContent=driver.online?'En ligne':'Hors ligne';
            const rating=document.createElement('span');
            rating.className='ops-driver-candidate-rating';
            rating.textContent=`★ ${driver.rating||'—'}`;
            badges.append(online,rating);
            if(index===0){
                const recommended=document.createElement('span');
                recommended.className='ops-badge is-green';
                recommended.textContent='Recommandé';
                badges.append(recommended);
            }

            button.append(top,meta,badges);
            button.addEventListener('click',()=>selectDriver(driver.id));
            return button;
        };

        const renderDriverCandidates=(preferredId='')=>{
            clearSelectedDriver();
            driverList.replaceChildren();

            const selectable=drivers.filter(driver=>driver.selectable);
            candidateCount.textContent=String(selectable.length);
            pickerCaption.textContent=selectable.length
                ? 'Classés par zone de collecte, proximité GPS, disponibilité et note.'
                : 'Aucun livreur disponible et compatible pour le moment.';

            if(!selectable.length){
                const empty=document.createElement('p');
                empty.className='ops-assignment-empty';
                empty.textContent='Aucun livreur disponible avec le véhicule requis. Utilisez la planification avancée ou réessayez après actualisation.';
                driverList.append(empty);
                return;
            }

            selectable.forEach((driver,index)=>driverList.append(candidateCard(driver,index)));

            // Aucun livreur n'est choisi automatiquement. On ne restaure qu'un
            // choix explicitement sauvegardé par l'utilisateur.
            const preferred=selectable.find(driver=>String(driver.id)===String(preferredId));
            if(preferred)selectDriver(preferred.id);
        };

        const selectDriver=(id)=>{
            const driver=drivers.find(d=>String(d.id)===String(id)&&d.selectable);
            clearSelectedDriver();
            if(!driver)return;

            driverInput.value=String(driver.id);
            selectedDriverBox.hidden=false;
            const values={
                ...driver,
                pickupEtaLabel:driver.pickupEta!=null?`${driver.pickupEta} min`:'À confirmer',
                clientEtaLabel:driver.clientEta!=null?`${driver.clientEta} min`:'À confirmer',
                recommendation:driver.recommendation||'Disponible pour cette collecte',
            };
            all('[data-driver]',dialog).forEach(el=>el.textContent=values[el.dataset.driver]??'—');
            one('[data-driver-reviews]',dialog).textContent=driver.reviews!=null?`(${driver.reviews} missions)`:'';

            const availability=one('[data-driver-availability]',dialog);
            const online=one('[data-driver-online]',dialog);
            availability.textContent=driver.available?'Disponible':(driver.busyReason||'Indisponible');
            online.textContent=driver.online?'● En ligne':'● Hors ligne';
            setBadgeState(availability,driver.available);
            setBadgeState(online,driver.online);

            one('[data-assignment-plate]',dialog).value=driver.vehicleReference||driver.vehicle||'';

            const compatibility=one('[data-assignment-compatibility]',dialog);
            const capacity=one('[data-assignment-capacity]',dialog);
            compatibility.querySelector('span').textContent=driver.compatible===true?'Véhicule compatible':'Véhicule incompatible';
            capacity.querySelector('span').textContent=driver.selectable?'Charge conforme':'Charge non affectable';
            setBadgeState(compatibility,driver.compatible===true);
            setBadgeState(capacity,driver.selectable===true);

            all('.ops-driver-candidate',driverList).forEach(card=>{
                card.classList.toggle('is-selected',card.dataset.driverCandidate===String(driver.id));
            });

            submitButton.disabled=loading||!driver.selectable;
            if(driver.clientEta!=null&&pickup.value){
                const date=new Date(pickup.value);
                date.setMinutes(date.getMinutes()+Number(driver.clientEta));
                delivery.value=localDate(date);
            }
            deliveryDisplay();
            updateDates();
        };

        const open=async(id,trigger,driverId)=>{
            if(!id){
                showMissionPicker(trigger);
                return;
            }

            const source=[...(missionData.missions||[]),...assignmentMissions];
            const indexedMission=source.find(m=>String(m.id)===String(id));
            if(!indexedMission){
                notify('Cette mission n’est plus disponible. Actualisez la liste des expéditions.');
                return;
            }
            if(!indexedMission.assignmentDataUrl){
                notify('La source de données de cette mission est indisponible.');
                return;
            }

            opener=trigger||opener;
            loading=true;
            submitButton.disabled=true;
            saveButton.disabled=true;
            controlsBody.disabled=true;
            missionPicker.hidden=true;
            missionDetails.hidden=false;
            driverList.replaceChildren();
            const loadingText=document.createElement('p');
            loadingText.className='ops-assignment-empty';
            loadingText.textContent='Recherche des livreurs disponibles à proximité de la collecte…';
            driverList.append(loadingText);
            pickerCaption.textContent='Calcul en cours…';
            candidateCount.textContent='…';

            if(!dialog.open){
                dialog.showModal();
                dialog.focus({preventScroll:true});
                document.body.style.overflow='hidden';
            }

            try{
                const response=await fetch(indexedMission.assignmentDataUrl,{
                    method:'GET',
                    credentials:'same-origin',
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
                });
                const payload=await response.json().catch(()=>({}));
                if(!response.ok)throw new Error(payload.message||'Impossible de charger cette mission.');

                mission=payload.mission;
                drivers=Array.isArray(payload.drivers)?payload.drivers:[];
                form.action=mission.assignUrl;
                form.reset();
                driverInput.value='';
                controlsBody.disabled=false;
                saveButton.disabled=false;
                pickup.value=payload.pickupScheduledAt||'';
                delivery.value=payload.deliveryScheduledAt||'';
                if(payload.minimumPickupAt)pickup.min=payload.minimumPickupAt;
                renderMission();

                dialogSubtitle.textContent=`Mission ${mission.reference} · choisissez explicitement un livreur parmi les candidats disponibles.`;
                if(advancedLink){
                    advancedLink.href=mission.assignmentUrl||'#';
                    advancedLink.hidden=!mission.assignmentUrl;
                }

                const draft=restore(`ovanie:assignment:${mission.id}`);
                if(draft){
                    for(const [key,value] of Object.entries(draft)){
                        const control=form.elements[key];
                        if(key==='notify_driver'){
                            form.querySelector('input[type=checkbox][name=notify_driver]').checked=String(value)==='1';
                        }else if(!['_token','driver_id','vehicle_plate'].includes(key)&&control&&typeof control.value!=='undefined'){
                            control.value=value;
                        }
                    }
                }

                loading=false;
                renderDriverCandidates(driverId||draft?.driver_id||'');
                one('[data-note-count]',dialog).textContent=form.elements.note.value.length;
                deliveryDisplay();
                updateDates();
            }catch(error){
                loading=false;
                submitButton.disabled=true;
                saveButton.disabled=true;
                controlsBody.disabled=true;
                if(advancedLink){advancedLink.hidden=true;advancedLink.href='#';}
                notify(error?.message||'Impossible de charger les données réelles de la mission.');
                dialog.close();
            }
        };

        all('[data-open-assignment]').forEach(button=>button.addEventListener('click',()=>open(button.dataset.openAssignment||'',button)));
        all('[data-close-assignment]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
        dialog.addEventListener('click',event=>{
            if(event.target===dialog){
                const r=dialog.getBoundingClientRect();
                if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();
            }
        });
        dialog.addEventListener('close',()=>{
            document.body.style.overflow='';
            opener?.focus();
        });
        pickup.addEventListener('change',()=>{
            if(driverInput.value)selectDriver(driverInput.value);
            else deliveryDisplay();
        });
        delivery.addEventListener('change',deliveryDisplay);
        form.elements.note.addEventListener('input',event=>one('[data-note-count]',dialog).textContent=event.target.value.length);
        saveButton.addEventListener('click',()=>{
            if(!mission)return;
            save(`ovanie:assignment:${mission.id}`,Object.fromEntries([...new FormData(form)].filter(([key])=>key!=='_token')));
            dialog.close();
        });
        form.addEventListener('submit',event=>{
            const driver=drivers.find(d=>String(d.id)===driverInput.value);
            if(!driver?.selectable){
                event.preventDefault();
                notify('Choisissez un livreur réellement disponible et compatible.');
                return;
            }
            if(!pickup.value||!delivery.value||new Date(delivery.value)<=new Date(pickup.value)){
                event.preventDefault();
                delivery.setCustomValidity('La livraison doit être prévue après la collecte.');
                delivery.reportValidity();
            }else delivery.setCustomValidity('');
        });
        delivery.addEventListener('input',()=>delivery.setCustomValidity(''));

        const auto=read('ops-assignment-auto');
        if(auto?.missionId)open(auto.missionId,null,auto.driverId);
    }

})();
