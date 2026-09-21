(() => {
    const cfg = window.OVANIE_MAPBOX || {};
    const dataEl = document.getElementById('territory-map-data');
    let mapData = { zones: [], selected: null, communes: [] };
    try { mapData = JSON.parse(dataEl?.textContent || '{}') || mapData; } catch (_) {}

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const communePopupHtml = (commune) => {
        const stats = commune?.stats;
        const name = esc(commune?.name || 'Commune');
        if (!stats) return `<div class="territory-map-popup"><b>${name}</b></div>`;
        const total = Number(stats.drivers_total || 0);
        const available = Number(stats.drivers_available || 0);
        const inMission = Number(stats.drivers_in_mission || 0);
        const offline = Number(stats.drivers_offline || 0);
        return `<div class="territory-map-popup"><b>${name}</b>`
            + `<span>${total} livreur(s) partenaire(s)</span>`
            + `<span>${available} disponible(s) · ${inMission} en mission · ${offline} hors ligne</span>`
            + `</div>`;
    };
    const polygonLatLngs = (geometry) => (geometry?.coordinates?.[0] || [])
        .map(([lng, lat]) => [Number(lat), Number(lng)])
        .filter(([lat,lng]) => Number.isFinite(lat) && Number.isFinite(lng));
    const toGeoJson = (latlngs) => ({ type:'Polygon', coordinates:[latlngs.map(([lat,lng]) => [Number(lng.toFixed(6)), Number(lat.toFixed(6))])] });
    const validPoint = (lat, lng) => {
        if (lat === null || lat === undefined || lng === null || lng === undefined) return false;
        if (String(lat).trim() === '' || String(lng).trim() === '') return false;
        const y = Number(lat);
        const x = Number(lng);
        return Number.isFinite(y) && Number.isFinite(x)
            && y >= -90 && y <= 90 && x >= -180 && x <= 180
            && !(y === 0 && x === 0);
    };

    // Le module Territoire ne doit jamais afficher un point hors de l'agglomération
    // d'Abidjan comme s'il s'agissait d'une commune sélectionnée.
    const ABIDJAN_BOUNDS = { south: 5.10, north: 5.65, west: -4.45, east: -3.65 };
    const validTerritoryPoint = (lat, lng) => {
        if (!validPoint(lat, lng)) return false;
        const y = Number(lat);
        const x = Number(lng);
        return y >= ABIDJAN_BOUNDS.south && y <= ABIDJAN_BOUNDS.north
            && x >= ABIDJAN_BOUNDS.west && x <= ABIDJAN_BOUNDS.east;
    };

    // Pour la carte, on utilise uniquement une réponse géographique située dans
    // l'emprise d'Abidjan. Aucun centre approximatif n'est inventé côté interface.
    const communeFeatureCache = new Map();
    let lastNominatimRequestAt = 0;
    const communeCacheKey = (name) => String(name || '').trim().toLocaleLowerCase('fr');
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const geometryPoints = (geometry) => {
        if (!geometry || !geometry.type || !Array.isArray(geometry.coordinates)) return [];
        if (geometry.type === 'Point') {
            const [lng, lat] = geometry.coordinates;
            return validTerritoryPoint(lat, lng) ? [[Number(lat), Number(lng)]] : [];
        }
        const points = [];
        const walk = (value) => {
            if (!Array.isArray(value)) return;
            if (value.length >= 2 && Number.isFinite(Number(value[0])) && Number.isFinite(Number(value[1])) && !Array.isArray(value[0])) {
                const lng = Number(value[0]);
                const lat = Number(value[1]);
                if (validTerritoryPoint(lat, lng)) points.push([lat, lng]);
                return;
            }
            value.forEach(walk);
        };
        walk(geometry.coordinates);
        return points;
    };

    const featureCenter = (feature) => {
        const points = geometryPoints(feature?.geometry);
        if (!points.length) return null;
        const lat = points.reduce((sum, point) => sum + point[0], 0) / points.length;
        const lng = points.reduce((sum, point) => sum + point[1], 0) / points.length;
        return validTerritoryPoint(lat, lng) ? { lat, lng } : null;
    };

    async function resolveCommuneFeature(commune) {
        const name = String(commune?.name || '').trim();
        if (!name) return null;
        const key = communeCacheKey(name);
        if (communeFeatureCache.has(key)) return communeFeatureCache.get(key);

        try {
            const elapsed = Date.now() - lastNominatimRequestAt;
            if (elapsed < 1100) await wait(1100 - elapsed);
            lastNominatimRequestAt = Date.now();
            const query = encodeURIComponent(`${name}, Abidjan, Côte d'Ivoire`);
            const viewbox = `${ABIDJAN_BOUNDS.west},${ABIDJAN_BOUNDS.north},${ABIDJAN_BOUNDS.east},${ABIDJAN_BOUNDS.south}`;
            const url = `https://nominatim.openstreetmap.org/search?format=geojson&polygon_geojson=1&addressdetails=1&limit=5&countrycodes=ci&accept-language=fr&bounded=1&viewbox=${encodeURIComponent(viewbox)}&q=${query}`;
            const response = await fetch(url, { headers:{ Accept:'application/geo+json, application/json' } });
            if (response.ok) {
                const payload = await response.json();
                const features = Array.isArray(payload?.features) ? payload.features : [];
                const normalizedName = name.toLocaleLowerCase('fr');
                const feature = features.find((candidate) => {
                    const label = String(candidate?.properties?.display_name || candidate?.properties?.name || '').toLocaleLowerCase('fr');
                    const center = featureCenter(candidate);
                    return center && (label.includes(normalizedName) || normalizedName.includes(String(candidate?.properties?.name || '').toLocaleLowerCase('fr')));
                }) || features.find((candidate) => featureCenter(candidate));
                if (feature) {
                    communeFeatureCache.set(key, feature);
                    return feature;
                }
            }
        } catch (_) {}

        return null;
    }

    async function resolveCommunePosition(commune) {
        const feature = await resolveCommuneFeature(commune);
        return featureCenter(feature);
    }

    function addCommuneFeatureLayer(map, feature, name, options = {}) {
        if (!map || !feature) return null;
        const geometry = feature.geometry;
        if (geometry?.type === 'Polygon' || geometry?.type === 'MultiPolygon') {
            const layer = L.geoJSON(feature, {
                style: {
                    color: options.selected ? '#078d6b' : '#2f79c9',
                    weight: options.selected ? 3 : 2,
                    fillColor: options.selected ? '#39d0a2' : '#7db7ff',
                    fillOpacity: options.selected ? .22 : .12,
                },
            }).addTo(map);
            layer.bindTooltip(esc(name || 'Commune'), { direction:'center', className:'territory-map-label' });
            if (options.stats) layer.bindPopup(communePopupHtml({ name, stats: options.stats }));
            return layer;
        }
        const center = featureCenter(feature);
        if (!center) return null;
        return addCommuneMarker(map, { name, stats: options.stats, ...center }, options);
    }

    async function hydrateSelectedZoneCommunes(map, host, zone) {
        if (!map || !zone) return;
        host._resolvedCommuneLayers = host._resolvedCommuneLayers || [];
        host._resolvedCommuneLayers.forEach((layer) => map.removeLayer(layer));
        host._resolvedCommuneLayers = [];
        const fitLayers = [];
        for (const commune of (zone.communes || [])) {
            const feature = await resolveCommuneFeature(commune);
            const layer = addCommuneFeatureLayer(map, feature, commune.name, { selected:true, popup:true, radius:7, stats: commune.stats });
            if (layer) {
                host._resolvedCommuneLayers.push(layer);
                fitLayers.push(layer);
            }
        }
        if (fitLayers.length && !(zone.geometry?.coordinates?.[0]?.length >= 3)) {
            const group = L.featureGroup(fitLayers);
            const bounds = group.getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding:[34,34], maxZoom:13 });
        }
    }

    function styleParts(style) {
        const fallback = ['mapbox', 'streets-v12'];
        if (typeof style !== 'string') return fallback;
        const match = style.match(/^mapbox:\/\/styles\/([^/]+)\/([^/?#]+)/i);
        return match ? [match[1], match[2]] : fallback;
    }

    function addBaseLayer(map) {
        map._ovanieBaseLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
    }

    function createMap(host) {
        if (!host || !window.L) return null;
        const map = L.map(host, { zoomControl: true, scrollWheelZoom: false, attributionControl: true })
            .setView([5.359952, -4.008256], 11);
        addBaseLayer(map);
        L.control.scale({ imperial:false, position:'bottomleft' }).addTo(map);
        host._territoryMap = map;
        if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize(false)).observe(host);
        setTimeout(() => map.invalidateSize(false), 80);
        return map;
    }

    function addCommuneMarker(map, commune, options = {}) {
        if (!map || !validTerritoryPoint(commune?.lat, commune?.lng)) return null;
        const marker = L.circleMarker([Number(commune.lat), Number(commune.lng)], {
            radius: options.radius || 6,
            color: options.selected ? '#057a5f' : '#0d6dfd',
            weight: 2,
            fillColor: options.selected ? '#24c28a' : '#ffffff',
            fillOpacity: 1,
        }).addTo(map);
        marker.bindTooltip(esc(commune.name || 'Commune'), { direction:'top', offset:[0,-4] });
        if (options.popup) marker.bindPopup(communePopupHtml(commune));
        return marker;
    }

    function drawZoneCommunes(map, zone, selected = false, bounds = []) {
        (zone?.communes || []).forEach((commune) => {
            const marker = addCommuneMarker(map, commune, { selected, popup:true, radius:selected ? 7 : 5 });
            if (marker) bounds.push(marker.getLatLng());
        });
    }

    function drawZones(map, host, { selected = null, clickable = true, fitSelected = false } = {}) {
        if (!map) return;
        const allBounds = [];
        const selectedBoundsPoints = [];
        (mapData.zones || []).forEach((zone) => {
            const isSelected = Boolean(selected && zone.code === selected);
            const latlngs = polygonLatLngs(zone.geometry);
            let layer = null;

            if (latlngs.length >= 3) {
                layer = L.polygon(latlngs, {
                    color: isSelected ? '#078d6b' : (zone.stroke || '#2f79c9'),
                    weight: isSelected ? 3 : 2,
                    fillColor: isSelected ? '#39d0a2' : (zone.fill || '#7db7ff'),
                    fillOpacity: isSelected ? .40 : .20,
                    dashArray: zone.is_active ? null : '6 5',
                }).addTo(map);
                layer.bindTooltip(esc(zone.name || zone.code), { permanent:true, direction:'center', className:`territory-map-label${isSelected ? ' selected' : ''}` });
                if (clickable) {
                    layer.bindPopup(`<div class="territory-map-popup"><b>${esc(zone.code)} — ${esc(zone.name || '')}</b><span>${esc(zone.region || '')}</span><span>${esc(zone.activity_7d || 0)} livraison(s) · délai ${esc(zone.average_delay_hours || 0)}h</span>${zone.url ? `<a href="${esc(zone.url)}">Voir le détail</a>` : ''}</div>`);
                    layer.on('click', () => {
                        mapData.selected = zone.code;
                        hydrateSelectedZoneCommunes(map, host, zone);
                    });
                }
                const layerBounds = layer.getBounds();
                allBounds.push(layerBounds.getSouthWest(), layerBounds.getNorthEast());
                if (isSelected) selectedBoundsPoints.push(layerBounds.getSouthWest(), layerBounds.getNorthEast());
            } else if (validPoint(zone?.center?.lat, zone?.center?.lng)) {
                const marker = L.marker([Number(zone.center.lat), Number(zone.center.lng)]).addTo(map);
                marker.bindTooltip(esc(zone.name || zone.code), { direction:'top' });
                if (clickable) {
                    marker.bindPopup(`<div class="territory-map-popup"><b>${esc(zone.code)} — ${esc(zone.name || '')}</b><span>${esc(zone.region || '')}</span>${zone.url ? `<a href="${esc(zone.url)}">Voir le détail</a>` : ''}</div>`);
                    marker.on('click', () => {
                        mapData.selected = zone.code;
                        hydrateSelectedZoneCommunes(map, host, zone);
                    });
                }
                allBounds.push(marker.getLatLng());
                if (isSelected) selectedBoundsPoints.push(marker.getLatLng());
            }

            drawZoneCommunes(map, zone, isSelected, isSelected ? selectedBoundsPoints : allBounds);
        });

        const fitPoints = fitSelected && selectedBoundsPoints.length ? selectedBoundsPoints : allBounds;
        if (fitPoints.length) {
            const bounds = L.latLngBounds(fitPoints);
            if (bounds.isValid()) map.fitBounds(bounds, { padding:[28,28], maxZoom:13 });
        }
        host?.classList.add('map-ready');
    }

    function initIndexMap() {
        const host = document.querySelector('[data-territory-map="index"]');
        const map = createMap(host);
        drawZones(map, host, { selected: mapData.selected, clickable:true });
        const selectedZone = (mapData.zones || []).find((zone) => zone.code === mapData.selected);
        hydrateSelectedZoneCommunes(map, host, selectedZone);
    }

    function initClickableRows() {
        document.querySelectorAll('[data-zone-row-url]').forEach((row) => {
            const go = (event) => {
                if (event.target.closest('a,button,input,select,textarea,label')) return;
                window.location.href = row.dataset.zoneRowUrl;
            };
            row.addEventListener('click', go);
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = row.dataset.zoneRowUrl;
                }
            });
        });
    }

    function initDetailMap() {
        const host = document.querySelector('[data-territory-map="detail"]');
        const hidden = document.querySelector('[data-coverage-geojson]');
        const map = createMap(host);
        drawZones(map, host, { selected: mapData.selected, clickable:false, fitSelected:true });
        if (!map) return;

        host._communeMarkers = [];
        const selectedZone = (mapData.zones || []).find((zone) => zone.code === mapData.selected);
        hydrateSelectedZoneCommunes(map, host, selectedZone);
        const editBtn = document.querySelector('[data-edit-zone-coverage]');
        let editLayer = null;
        let editMarkers = [];
        let points = [];
        const resetEdit = () => {
            if (editLayer) map.removeLayer(editLayer);
            editLayer = null;
            editMarkers.forEach((marker) => map.removeLayer(marker));
            editMarkers = [];
            points = [];
        };
        editBtn?.addEventListener('click', () => {
            const editing = !host.classList.contains('is-editing');
            resetEdit();
            host.classList.toggle('is-editing', editing);
            editBtn.classList.toggle('is-active', editing);
            editBtn.innerHTML = editing ? '✓ Terminer la couverture' : '✎ Modifier la couverture';
        });
        map.on('click', (e) => {
            if (!host.classList.contains('is-editing')) return;
            points.push([e.latlng.lat, e.latlng.lng]);
            editMarkers.push(L.circleMarker(e.latlng, { radius:5, color:'#078d6b', fillColor:'#fff', fillOpacity:1 }).addTo(map));
            if (points.length >= 3) {
                const ring = [...points, points[0]];
                if (editLayer) map.removeLayer(editLayer);
                editLayer = L.polygon(ring, { color:'#078d6b', weight:3, fillColor:'#39d0a2', fillOpacity:.35 }).addTo(map);
                if (hidden) hidden.value = JSON.stringify(toGeoJson(ring));
            }
        });

        const chips = document.querySelector('[data-detail-commune-chips]');
        const select = document.querySelector('[data-detail-commune-select]');
        const focusCommune = async (data) => {
            const feature = await resolveCommuneFeature(data);
            const center = featureCenter(feature);
            if (!feature || !center) return;
            map.setView([center.lat, center.lng], 13);
            const layer = addCommuneFeatureLayer(map, feature, data.name, { selected:true, popup:true, radius:8 });
            if (layer) host._communeMarkers.push(layer);
        };
        const bindRemove = (chip) => {
            chip.querySelector('button')?.addEventListener('click', () => {
                const id = chip.dataset.id;
                const option = select?.querySelector(`option[value="${CSS.escape(id)}"]`);
                if (option) option.disabled = false;
                chip.remove();
            });
            chip.addEventListener('click', (event) => {
                if (event.target.closest('button')) return;
                focusCommune(chip.dataset);
            });
        };
        chips?.querySelectorAll('[data-id]').forEach(bindRemove);
        select?.addEventListener('change', async () => {
            const option = select.selectedOptions[0];
            if (!option?.value || chips?.querySelector(`[data-id="${CSS.escape(option.value)}"]`)) return;
            const chip = document.createElement('span');
            chip.dataset.id = option.value;
            chip.dataset.name = option.dataset.name || option.textContent.trim();
            chip.dataset.lat = option.dataset.lat || '';
            chip.dataset.lng = option.dataset.lng || '';
            chip.innerHTML = `${esc(chip.dataset.name)} <button type="button" aria-label="Retirer ${esc(chip.dataset.name)}">×</button><input type="hidden" name="commune_ids[]" value="${esc(option.value)}">`;
            chips?.appendChild(chip);
            option.disabled = true;
            bindRemove(chip);
            await focusCommune(chip.dataset);
            option.dataset.lat = chip.dataset.lat || option.dataset.lat || '';
            option.dataset.lng = chip.dataset.lng || option.dataset.lng || '';
            select.value = '';
        });
    }

    function initCreateMap(dialog) {
        const host = dialog?.querySelector('[data-territory-map="create"]');
        if (!host || host._territoryMap) return;
        const hidden = dialog.querySelector('[data-coverage-geojson]');
        if (hidden) hidden.value = '';
        const map = createMap(host);
        if (!map) return;
        host._communeLayers = [];
        setTimeout(() => map.invalidateSize(false), 150);
    }

    async function refreshCreateCommuneMap(dialog) {
        const host = dialog?.querySelector('[data-territory-map="create"]');
        const map = host?._territoryMap;
        const chips = dialog?.querySelector('[data-commune-chips]');
        if (!map || !chips) return;
        (host._communeLayers || []).forEach((layer) => map.removeLayer(layer));
        host._communeLayers = [];
        const layers = [];
        for (const chip of chips.querySelectorAll('[data-id]')) {
            const feature = await resolveCommuneFeature({
                name: chip.dataset.name,
                lat: chip.dataset.lat,
                lng: chip.dataset.lng,
            });
            const center = featureCenter(feature);
            if (center) {
                chip.dataset.lat = String(center.lat);
                chip.dataset.lng = String(center.lng);
            }
            const layer = addCommuneFeatureLayer(map, feature, chip.dataset.name, { selected:true, popup:true, radius:7 });
            if (layer) {
                host._communeLayers.push(layer);
                layers.push(layer);
            }
        }
        if (layers.length) {
            const bounds = L.featureGroup(layers).getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding:[42,42], maxZoom:13 });
        } else {
            map.setView([5.359952, -4.008256], 11);
        }
    }

    function initCreateDialog() {
        const dialog = document.getElementById('zone-create-dialog');
        document.querySelectorAll('[data-open-zone-modal]').forEach((button) => button.addEventListener('click', () => {
            dialog?.showModal();
            setTimeout(async () => {
                initCreateMap(dialog);
                await hydrateCreateCommunePositions();
            }, 40);
        }));
        document.querySelectorAll('[data-close-zone-modal]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
        if (!dialog) return;
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        });

        const chips = dialog.querySelector('[data-commune-chips]');
        const select = dialog.querySelector('[data-commune-select]');
        const count = dialog.querySelector('[data-commune-count]');
        const delay = dialog.querySelector('select[name="average_delay_hours"]');
        const delaySummary = dialog.querySelector('[data-delay-summary]');
        const note = dialog.querySelector('textarea[name="operational_note"]');
        const noteCount = dialog.querySelector('[data-note-count]');
        const refreshCount = () => { if (count && chips) count.textContent = chips.querySelectorAll('[data-id]').length; };
        const focusOption = async (option, chip = null) => {
            if (!option) return null;
            const feature = await resolveCommuneFeature({
                name: option.dataset.name || option.textContent.trim(),
                lat: option.dataset.lat,
                lng: option.dataset.lng,
            });
            const position = featureCenter(feature);
            if (!position) return null;
            option.dataset.lat = String(position.lat);
            option.dataset.lng = String(position.lng);
            if (chip) {
                chip.dataset.lat = String(position.lat);
                chip.dataset.lng = String(position.lng);
            }
            return position;
        };
        const hydrateCreateCommunePositions = async () => {
            if (!chips || !select) return;
            for (const chip of chips.querySelectorAll('[data-id]')) {
                const option = select.querySelector(`option[value="${CSS.escape(chip.dataset.id)}"]`);
                if (!option) continue;
                await focusOption(option, chip);
            }
            await refreshCreateCommuneMap(dialog);
        };
        const bindRemove = (chip) => chip.querySelector('button')?.addEventListener('click', () => {
            const option = select?.querySelector(`option[value="${CSS.escape(chip.dataset.id)}"]`);
            if (option) option.disabled = false;
            chip.remove();
            refreshCount();
            void refreshCreateCommuneMap(dialog);
        });

        chips?.querySelectorAll('[data-id]').forEach(bindRemove);

        select?.addEventListener('change', async () => {
            const option = select.selectedOptions[0];
            if (!option?.value) return;
            if (chips.querySelector(`[data-id="${CSS.escape(option.value)}"]`)) { select.value = ''; return; }
            const chip = document.createElement('span');
            chip.dataset.id = option.value;
            chip.dataset.name = option.dataset.name || option.textContent.trim();
            chip.dataset.lat = option.dataset.lat || '';
            chip.dataset.lng = option.dataset.lng || '';
            chip.innerHTML = `${esc(chip.dataset.name)} <button type="button" aria-label="Retirer ${esc(chip.dataset.name)}">×</button><input type="hidden" name="commune_ids[]" value="${esc(option.value)}">`;
            chips.appendChild(chip);
            option.disabled = true;
            bindRemove(chip);
            refreshCount();
            await focusOption(option, chip);
            await refreshCreateCommuneMap(dialog);
            select.value = '';
        });
        delay?.addEventListener('change', () => { if (delaySummary) delaySummary.textContent = `${delay.value}h`; });
        note?.addEventListener('input', () => { if (noteCount) noteCount.textContent = note.value.length; });
        refreshCount();
        if (dialog.dataset.openOnError === '1') {
            dialog.showModal();
            setTimeout(async () => {
                initCreateMap(dialog);
                await hydrateCreateCommunePositions();
            }, 40);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initClickableRows();
        initIndexMap();
        initDetailMap();
        initCreateDialog();
    });
})();
