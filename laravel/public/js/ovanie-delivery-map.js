(() => {
    const VERSION = '5.0.0';
    const MARKER_ASSETS = {
        moto: '/marqueurs/moto.png', tricycle: '/marqueurs/Tricycle.png', pickup: '/marqueurs/Pickup.png',
        camion_3t: '/marqueurs/Camion%203T.png', camion_10t: '/marqueurs/Camion%2010T.png', vehicle: '/marqueurs/Pickup.png',
        pickup_point: '/marqueurs/Boutique.png', shop: '/marqueurs/Boutique.png', destination: '/marqueurs/Position%20client.png',
    };
    const VEHICLES = {
        moto:       { color: '#f97316', label: 'Moto' },
        tricycle:   { color: '#d97706', label: 'Tricycle' },
        pickup:     { color: '#1769e8', label: 'Pickup' },
        camion_3t:  { color: '#6554c0', label: 'Camion 3T' },
        camion_10t: { color: '#0f766e', label: 'Camion 10T' },
        vehicle:    { color: '#1769e8', label: 'Véhicule' },
    };

    function normalizeVehicleCode(value) {
        const code = String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
        if (!code) return 'vehicle';
        if (code.includes('moto') || code.includes('scooter')) return 'moto';
        if (code.includes('tricycle') || code.includes('triporteur') || code.includes('rickshaw')) return 'tricycle';
        if (code.includes('10t') || code.includes('10_t') || code.includes('heavy')) return 'camion_10t';
        if (code.includes('3t') || code.includes('3_t') || code === 'truck_3t') return 'camion_3t';
        if (code.includes('pickup') || code.includes('pick_up')) return 'pickup';
        if (code.includes('camion') || code.includes('truck')) return 'camion_3t';
        return VEHICLES[code] ? code : 'vehicle';
    }

    function themeFor(value) {
        return VEHICLES[normalizeVehicleCode(value)] || VEHICLES.vehicle;
    }

    function vehicleSvg(code) {
        code = normalizeVehicleCode(code);
        const svgs = {
            moto: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="32" cy="11" r="6" fill="currentColor" stroke="none" opacity=".96"/>
                    <path d="M24 20h16l5 13-4 18H23l-4-18 5-13Z" fill="currentColor" stroke="none"/>
                    <path d="M26 24h12l3 9H23l3-9Z" fill="#fff" stroke="none" opacity=".9"/>
                    <path d="M19 25 10 19M45 25l9-6M12 19H7M52 19h5" stroke-width="3.2"/>
                    <circle cx="32" cy="56" r="5" fill="currentColor" stroke="none"/>
                    <path d="M32 17v3M32 51v1" stroke-width="3"/>
                </g></svg>`,
            tricycle: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="currentColor">
                    <circle cx="32" cy="9" r="5"/>
                    <path d="M24 16h16l5 12H19l5-12Z"/>
                    <rect x="13" y="29" width="38" height="25" rx="7"/>
                    <rect x="18" y="34" width="12" height="15" rx="2" fill="#fff" opacity=".88"/>
                    <rect x="34" y="34" width="12" height="15" rx="2" fill="#fff" opacity=".88"/>
                    <circle cx="20" cy="57" r="5"/><circle cx="44" cy="57" r="5"/>
                </g></svg>`,
            pickup: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="currentColor">
                    <rect x="17" y="5" width="30" height="54" rx="10"/>
                    <path d="M21 12h22l3 14H18l3-14Z" fill="#fff" opacity=".9"/>
                    <path d="M20 31h24v21H20z" fill="#fff" opacity=".78"/>
                    <path d="M24 35h16v13H24z" fill="currentColor" opacity=".2"/>
                    <rect x="12" y="14" width="5" height="11" rx="2"/><rect x="47" y="14" width="5" height="11" rx="2"/>
                    <rect x="12" y="40" width="5" height="11" rx="2"/><rect x="47" y="40" width="5" height="11" rx="2"/>
                </g></svg>`,
            camion_3t: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="currentColor">
                    <rect x="15" y="4" width="34" height="56" rx="8"/>
                    <path d="M19 9h26v15H19z" fill="#fff" opacity=".9"/>
                    <path d="M19 29h26v25H19z" fill="#fff" opacity=".72"/>
                    <path d="M23 29v25M41 29v25" stroke="currentColor" stroke-width="2" opacity=".25"/>
                    <rect x="10" y="12" width="5" height="12" rx="2"/><rect x="49" y="12" width="5" height="12" rx="2"/>
                    <rect x="10" y="43" width="5" height="11" rx="2"/><rect x="49" y="43" width="5" height="11" rx="2"/>
                </g></svg>`,
            camion_10t: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="currentColor">
                    <rect x="13" y="3" width="38" height="58" rx="8"/>
                    <path d="M18 8h28v14H18z" fill="#fff" opacity=".9"/>
                    <path d="M18 27h28v29H18z" fill="#fff" opacity=".68"/>
                    <path d="M25 27v29M39 27v29" stroke="currentColor" stroke-width="2.5" opacity=".25"/>
                    <rect x="8" y="11" width="5" height="13" rx="2"/><rect x="51" y="11" width="5" height="13" rx="2"/>
                    <rect x="8" y="43" width="5" height="12" rx="2"/><rect x="51" y="43" width="5" height="12" rx="2"/>
                </g></svg>`,
            vehicle: `<svg viewBox="0 0 64 64" aria-hidden="true">
                <g fill="currentColor">
                    <rect x="17" y="5" width="30" height="54" rx="10"/>
                    <path d="M21 12h22l3 15H18l3-15Z" fill="#fff" opacity=".9"/>
                    <path d="M21 32h22v19H21z" fill="#fff" opacity=".76"/>
                    <rect x="12" y="15" width="5" height="10" rx="2"/><rect x="47" y="15" width="5" height="10" rx="2"/>
                    <rect x="12" y="41" width="5" height="10" rx="2"/><rect x="47" y="41" width="5" height="10" rx="2"/>
                </g></svg>`,
        };
        return svgs[code] || svgs.vehicle;
    }

    function pinSvg(type) {
        if (type === 'pickup' || type === 'shop') {
            return `<svg viewBox="0 0 48 48" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20h26M14 20v17h20V20M16 11h16l4 9H12l4-9Z"/><path d="M20 37v-9h8v9M16 24h4M28 24h4"/></g></svg>`;
        }
        return `<svg viewBox="0 0 48 48" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 23 24 12l12 11v14H12V23Z"/><path d="M20 37V27h8v10M9 23l15-14 15 14"/></g></svg>`;
    }

    function markerElement(type, options = {}) {
        const isPin = ['destination', 'pickup', 'shop'].includes(type);
        const code = normalizeVehicleCode(options.vehicleCode);
        const theme = isPin
            ? { color: options.color || (type === 'destination' ? '#082b5c' : '#087a63'), label: type === 'destination' ? 'Adresse de livraison' : 'Point de collecte' }
            : { ...themeFor(code), ...(options.color ? { color: options.color } : {}) };

        const el = document.createElement('button');
        el.type = 'button';
        el.className = `ovd-marker ovd-marker--${isPin ? 'pin' : 'vehicle'} ovd-marker--${type} ovd-marker--${code}`;
        el.style.setProperty('--ovd-color', theme.color);
        el.setAttribute('aria-label', options.label || theme.label);
        const asset = MARKER_ASSETS[isPin ? (type === 'pickup' ? 'pickup_point' : type) : code] || MARKER_ASSETS.vehicle;
        el.innerHTML = `
            <span class="ovd-marker__pulse" aria-hidden="true"></span>
            <span class="ovd-marker__shell">
                <span class="ovd-marker__direction" aria-hidden="true"></span>
                <span class="ovd-marker__rotator"><img src="${asset}" alt="" draggable="false"></span>
            </span>
            ${!isPin ? `<span class="ovd-marker__number" aria-hidden="true"></span><span class="ovd-marker__type">${escapeHtml(options.badgeText || theme.label)}</span>` : ''}
            <span class="ovd-marker__signal" aria-hidden="true"></span>`;
        applyMeta(el, options);
        return el;
    }

    function applyMeta(el, options = {}) {
        if (!el) return;
        const isPin = el.classList.contains('ovd-marker--pin');
        if (!isPin) {
            const code = normalizeVehicleCode(options.vehicleCode || el.dataset.vehicleCode);
            const theme = { ...themeFor(code), ...(options.color ? { color: options.color } : {}) };
            el.dataset.vehicleCode = code;
            el.style.setProperty('--ovd-color', theme.color);
            Object.keys(VEHICLES).forEach(key => el.classList.remove(`ovd-marker--${key}`));
            el.classList.add(`ovd-marker--${code}`);
            const rotator = el.querySelector('.ovd-marker__rotator');
            if (rotator && options.vehicleCode) {
                const image = rotator.querySelector('img');
                if (image) image.src = MARKER_ASSETS[code] || MARKER_ASSETS.vehicle;
            }
            const type = el.querySelector('.ovd-marker__type');
            if (type) type.textContent = options.badgeText || theme.label;
            const number = el.querySelector('.ovd-marker__number');
            const numberText = String(options.number ?? '').trim();
            if (number) {
                number.textContent = numberText;
                number.hidden = numberText === '';
            }
            const heading = Number(options.heading);
            if (Number.isFinite(heading)) {
                const normalized = ((heading % 360) + 360) % 360;
                el.style.setProperty('--ovd-heading', `${normalized}deg`);
            }
        }
        el.classList.toggle('is-stale', options.stale === true);
        el.classList.toggle('is-offline', options.offline === true);
        el.classList.toggle('is-selected', options.selected === true);
        el.classList.toggle('is-muted', options.muted === true);
        el.classList.toggle('is-live', options.stale !== true && options.offline !== true && options.live !== false);
        el.classList.toggle('hide-type', options.showBadge === false);
        if (options.label) el.setAttribute('aria-label', options.label);
    }

    function create(map, point, type, options = {}) {
        const el = markerElement(type, options);
        const isPin = ['destination', 'pickup', 'shop'].includes(type);
        const marker = new mapboxgl.Marker({
            element: el,
            anchor: isPin ? 'bottom' : 'center',
            offset: isPin ? [0, 0] : [0, 7],
        }).setLngLat(point).addTo(map);

        if (options.label) {
            marker.setPopup(new mapboxgl.Popup({
                offset: ['destination', 'pickup', 'shop'].includes(type) ? 28 : 24,
                closeButton: false,
                closeOnClick: true,
                maxWidth: '260px',
            }).setText(options.label));
        }
        marker.__ovdElement = el;
        marker.__ovdType = type;
        marker.__ovdOptions = { ...options };
        return marker;
    }

    function update(marker, point, options = {}) {
        if (!marker) return marker;
        const current = marker.getLngLat();
        const destination = { lng: Number(point?.[0]), lat: Number(point?.[1]) };
        const nextOptions = { ...(marker.__ovdOptions || {}), ...options };
        if (!Number.isFinite(Number(nextOptions.heading))
            && Number.isFinite(destination.lng)
            && Number.isFinite(destination.lat)
            && distanceMeters(current.lat, current.lng, destination.lat, destination.lng) >= 4) {
            nextOptions.heading = bearing(current.lat, current.lng, destination.lat, destination.lng);
        }
        marker.__ovdOptions = nextOptions;
        applyMeta(marker.__ovdElement || marker.getElement?.(), nextOptions);
        if (nextOptions.label) {
            marker.setPopup(new mapboxgl.Popup({
                offset: ['destination', 'pickup', 'shop'].includes(marker.__ovdType) ? 28 : 24,
                closeButton: false,
                closeOnClick: true,
                maxWidth: '260px',
            }).setText(nextOptions.label));
        }
        move(marker, point, nextOptions.animate !== false ? Number(nextOptions.duration || 650) : 0);
        return marker;
    }

    function upsert(marker, map, point, type, options = {}) {
        return marker ? update(marker, point, options) : create(map, point, type, options);
    }

    function move(marker, point, duration = 650) {
        const longitude = Number(point?.[0]);
        const latitude = Number(point?.[1]);
        if (!Number.isFinite(longitude) || !Number.isFinite(latitude)) return;
        if (marker.__ovdAnimationFrame) cancelAnimationFrame(marker.__ovdAnimationFrame);
        const from = marker.getLngLat();
        if (distanceMeters(Number(from.lat), Number(from.lng), latitude, longitude) > 1500) duration = 0;
        const deltaLng = longitude - Number(from.lng);
        const deltaLat = latitude - Number(from.lat);
        if (Math.abs(deltaLng) < 0.000001 && Math.abs(deltaLat) < 0.000001) return;
        if (!duration) { marker.setLngLat([longitude, latitude]); return; }
        const start = performance.now();
        const frame = now => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            marker.setLngLat([Number(from.lng) + deltaLng * eased, Number(from.lat) + deltaLat * eased]);
            if (progress < 1) marker.__ovdAnimationFrame = requestAnimationFrame(frame);
            else marker.__ovdAnimationFrame = null;
        };
        marker.__ovdAnimationFrame = requestAnimationFrame(frame);
    }

    function applyMapConstraints(map, options = {}) {
        if (!map) return;
        const minZoom = Number(options.minZoom ?? 10.5);
        const maxZoom = Number(options.maxZoom ?? 18.5);
        map.setMinZoom(minZoom);
        map.setMaxZoom(maxZoom);
        const operationalBounds = options.maxBounds || [[-9.2, 4.0], [-2.0, 11.3]];
        if (Array.isArray(operationalBounds) && operationalBounds.length === 2) {
            map.setMaxBounds(operationalBounds);
        }
        map.setPitch(0);
        map.dragRotate?.disable();
        map.touchZoomRotate?.disableRotation();
        map.on('zoomend', () => {
            if (map.getZoom() < minZoom) map.easeTo({ zoom: minZoom, duration: 180 });
        });
    }

    function fitOperationalBounds(map, points, options = {}) {
        if (!map || !Array.isArray(points)) return false;
        const valid = points
            .map(point => Array.isArray(point) ? point : [point?.longitude ?? point?.lng, point?.latitude ?? point?.lat])
            .filter(point => Number.isFinite(Number(point?.[0])) && Number.isFinite(Number(point?.[1])));
        if (!valid.length) return false;
        if (valid.length === 1) {
            map.easeTo({ center: valid[0], zoom: Number(options.singleZoom ?? 14), duration: Number(options.duration ?? 450) });
            return true;
        }
        const maxSpanKm = Number(options.maxSpanKm ?? 35);
        let largest = 0;
        for (let i = 0; i < valid.length; i++) {
            for (let j = i + 1; j < valid.length; j++) {
                largest = Math.max(largest, distanceMeters(valid[i][1], valid[i][0], valid[j][1], valid[j][0]) / 1000);
            }
        }
        if (largest > maxSpanKm) {
            map.easeTo({ center: valid[0], zoom: Number(options.longTripZoom ?? 12), duration: Number(options.duration ?? 450) });
            return true;
        }
        const bounds = new mapboxgl.LngLatBounds();
        valid.forEach(point => bounds.extend(point));
        map.fitBounds(bounds, {
            padding: options.padding || { top: 80, right: 60, bottom: 150, left: 60 },
            maxZoom: Number(options.maxFitZoom ?? 15.5),
            duration: Number(options.duration ?? 500),
        });
        return true;
    }

    function bearing(lat1, lng1, lat2, lng2) {
        const phi1 = lat1 * Math.PI / 180;
        const phi2 = lat2 * Math.PI / 180;
        const delta = (lng2 - lng1) * Math.PI / 180;
        const y = Math.sin(delta) * Math.cos(phi2);
        const x = Math.cos(phi1) * Math.sin(phi2) - Math.sin(phi1) * Math.cos(phi2) * Math.cos(delta);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    function distanceMeters(lat1, lng1, lat2, lng2) {
        const earth = 6371000;
        const p1 = lat1 * Math.PI / 180;
        const p2 = lat2 * Math.PI / 180;
        const dp = (lat2 - lat1) * Math.PI / 180;
        const dl = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dp / 2) ** 2 + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
    }

    function projectOnSegment(point, start, end) {
        const latitudeScale = Math.cos(Number(point[1]) * Math.PI / 180);
        const px = Number(point[0]) * latitudeScale, py = Number(point[1]);
        const ax = Number(start[0]) * latitudeScale, ay = Number(start[1]);
        const bx = Number(end[0]) * latitudeScale, by = Number(end[1]);
        const dx = bx - ax, dy = by - ay, lengthSquared = dx * dx + dy * dy;
        const ratio = lengthSquared > 0 ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lengthSquared)) : 0;
        return [Number(start[0]) + (Number(end[0]) - Number(start[0])) * ratio, Number(start[1]) + (Number(end[1]) - Number(start[1])) * ratio];
    }

    function remainingRoute(geometry, point, maxSnapMeters = 250) {
        const coordinates = geometry?.type === 'LineString' && Array.isArray(geometry.coordinates) ? geometry.coordinates : null;
        if (!coordinates || coordinates.length < 2 || !Array.isArray(point)) return { geometry, point, snapped: false };
        let nearest = null;
        for (let index = 0; index < coordinates.length - 1; index += 1) {
            const candidate = projectOnSegment(point, coordinates[index], coordinates[index + 1]);
            const distance = distanceMeters(Number(point[1]), Number(point[0]), candidate[1], candidate[0]);
            if (!nearest || distance < nearest.distance) nearest = { point: candidate, index, distance };
        }
        if (!nearest || nearest.distance > Number(maxSnapMeters)) return { geometry, point, snapped: false };
        return {
            point: nearest.point,
            snapped: true,
            distanceMeters: nearest.distance,
            geometry: { ...geometry, coordinates: [nearest.point, ...coordinates.slice(nearest.index + 1)] },
        };
    }

    function setState(marker, state = {}) {
        if (!marker) return;
        marker.__ovdOptions = { ...(marker.__ovdOptions || {}), ...state };
        applyMeta(marker.__ovdElement || marker.getElement?.(), marker.__ovdOptions);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[char]));
    }

    window.OVANIE_DELIVERY_MAP_VERSION = VERSION;
    window.OvanieDeliveryMap = {
        version: VERSION,
        normalizeVehicleCode,
        themeFor,
        createMarker: create,
        updateMarker: update,
        upsertMarker: upsert,
        setMarkerState: setState,
        applyMapConstraints,
        fitOperationalBounds,
        bearing,
        distanceMeters,
        remainingRoute,
    };
})();
