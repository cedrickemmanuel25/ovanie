(() => {
    'use strict';
    const form = document.getElementById('logistics-location-form');
    if (!form) return;
    const button = document.getElementById('capture-shop-gps');
    const feedback = document.getElementById('gps-feedback');
    const confirmation = form.querySelector('[name="location_confirmed"]');
    const submit = form.querySelector('[type="submit"]');
    let ready = form.dataset.locationReady === '1';
    let watch = null, timer = null, capturing = false, resolving = false, sequence = 0;
    let request = null;
    const field = name => document.getElementById(name);
    const update = () => {
        confirmation.disabled = !ready || capturing || resolving;
        submit.disabled = !ready || capturing || resolving;
        button.disabled = capturing || resolving;
    };
    const stop = () => {
        if (watch !== null) navigator.geolocation.clearWatch(watch);
        if (timer !== null) clearTimeout(timer);
        watch = timer = null;
        capturing = false;
    };
    const invalidate = () => {
        ready = false;
        confirmation.checked = false;
        sequence++;
        request?.abort();
        resolving = false;
        field('detected-location').hidden = true;
        ['latitude', 'longitude', 'geo_accuracy', 'geo_source', 'geo_captured_at', 'location_token'].forEach(name => field(name).value = '');
    };
    if (ready) feedback.textContent = 'La localisation précédemment enregistrée est disponible. Recapturez-la si le point d’enlèvement a changé.';
    update();

    async function resolveCapture(capture) {
        stop(); resolving = true; update();
        const current = sequence;
        feedback.textContent = 'Position reçue. Identification de son adresse actuelle…';
        const activeRequest = new AbortController();
        request = activeRequest;
        const timeout = setTimeout(() => activeRequest.abort(), 40000);
        try {
            const response = await fetch(form.dataset.resolveUrl, {
                method: 'POST', credentials: 'same-origin', signal: activeRequest.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify(capture),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Adresse non identifiée.');
            if (sequence !== current) return;
            if ((!result.location_token && !capture.preview_only) || !result.address || !result.commune) throw new Error('Adresse non identifiée pour cette position. Réessayez.');
            for (const name of ['latitude', 'longitude', 'geo_accuracy', 'geo_source', 'geo_captured_at']) field(name).value = capture[name] ?? '';
            field('location_token').value = result.location_token || '';
            ['address', 'commune', 'district', 'landmark'].forEach(name => field(name).value = result[name] || '');
            ['address', 'commune', 'district'].forEach(name => field(name).readOnly = Boolean(result[name]));
            field('location-address-status').textContent = 'Informations issues de la nouvelle position. Complétez le point de repère et les détails manquants, puis vérifiez le point sur la carte.';
            field('detected-location-address').textContent = result.display_name || result.address;
            field('detected-location-map').href = `https://www.openstreetmap.org/?mlat=${capture.latitude}&mlon=${capture.longitude}#map=19/${capture.latitude}/${capture.longitude}`;
            field('detected-location').hidden = false;
            ready = Boolean(result.location_token) && !capture.preview_only;
            if (!ready) {
                field('location-address-status').textContent = 'Adresse indicative issue d’une position approximative : elle ne constitue pas un point d’enlèvement validé.';
                feedback.textContent = 'Votre position n’est pas assez précise pour identifier automatiquement l’adresse. Rapprochez-vous du point d’enlèvement, activez le GPS de précision sur votre téléphone, puis réessayez.';
                return;
            }
            feedback.textContent = 'Position et adresse détectées. Vérifiez qu’elles correspondent à votre boutique avant de confirmer.';
        } catch (error) {
            if (sequence !== current) return;
            invalidate();
            field('location-address-status').textContent = 'Aucune nouvelle adresse validée. Les données d’ouverture ne sont pas utilisées comme résultat.';
            feedback.textContent = error.name === 'AbortError' ? 'Identification de l’adresse indisponible. Réessayez.' : error.message;
        } finally {
            clearTimeout(timeout);
            if (sequence === current) resolving = false;
            update();
        }
    }

    button.addEventListener('click', () => {
        stop();
        invalidate();
        ['address', 'commune', 'district', 'landmark'].forEach(name => {
            field(name).readOnly = false;
        });
        field('location-address-status').textContent = 'Recherche en cours. Les champs affichent encore les informations précédentes ; ils seront remplacés après identification de la nouvelle adresse.';
        if (!navigator.geolocation) {
            feedback.textContent = 'La localisation est indisponible sur cet appareil. Ouvrez cette page sur votre téléphone à la boutique.';
            update();
            return;
        }
        capturing = true;
        update();
        feedback.textContent = 'Recherche d’une position suffisamment précise… Restez au point d’enlèvement.';
        const fail = message => {
            stop(); invalidate(); update(); feedback.textContent = message;
        };
        let bestPosition = null;
        const captureData = position => ({ latitude: position.coords.latitude.toFixed(7),
            longitude: position.coords.longitude.toFixed(7), geo_accuracy: String(position.coords.accuracy),
            geo_source: 'browser_gps', geo_captured_at: new Date(position.timestamp).toISOString() });
        timer = setTimeout(() => {
            if (bestPosition && Date.now() - bestPosition.timestamp <= 60000) {
                return resolveCapture({ ...captureData(bestPosition), preview_only: true });
            }
            fail('Aucune position actuelle exploitable reçue. Les champs précédents sont conservés mais ne sont pas validés. Autorisez la localisation précise sur votre téléphone à la boutique puis réessayez.');
        }, 45000);
        watch = navigator.geolocation.watchPosition(async position => {
            if (!capturing) return;
            const { latitude, longitude, accuracy } = position.coords;
            const age = Date.now() - position.timestamp;
            if (![latitude, longitude, accuracy, age].every(Number.isFinite)
                || Math.abs(latitude) > 90 || Math.abs(longitude) > 180
                || accuracy <= 0 || age < -10000 || age > 30000) {
                feedback.textContent = 'La position reçue est encore trop imprécise ou trop ancienne. Recherche en cours…';
                return;
            }
            if (!bestPosition || accuracy < bestPosition.coords.accuracy) bestPosition = position;
            if (accuracy > 50) {
                feedback.textContent = 'Position reçue, mais elle manque encore de précision. Recherche d’une meilleure position en cours ; une adresse indicative sera proposée si aucune position précise n’est trouvée.';
                return;
            }
            await resolveCapture(captureData(position));
        }, error => {
            if (!capturing) return;
            if (error.code === 1) fail('Autorisez la localisation précise, puis réessayez depuis la boutique.');
            // Transient failures may recover before the overall deadline.
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    });

    form.addEventListener('input', event => {
        if (ready && field('location_token').value && ['address', 'landmark', 'district'].includes(event.target.name)) {
            confirmation.checked = false;
            return;
        }
        if (['address', 'commune', 'district', 'landmark'].includes(event.target.name)) {
            stop(); invalidate(); update();
            feedback.textContent = 'Adresse modifiée. Localisez à nouveau le point d’enlèvement depuis la boutique.';
        }
    });
    form.addEventListener('submit', event => {
        if (!ready || capturing || resolving || !confirmation.checked) {
            event.preventDefault();
            feedback.textContent = 'Localisez la boutique et confirmez le point d’enlèvement avant de continuer.';
        } else if (form.dataset.changing === '1'
            && !window.confirm('Confirmer le passage à OVANIE Logistics ? Ce changement s’appliquera uniquement aux nouvelles commandes.')) {
            event.preventDefault();
        }
    });
    window.addEventListener('pagehide', () => { stop(); request?.abort(); });
})();
