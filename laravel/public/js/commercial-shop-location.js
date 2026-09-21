(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        document.querySelectorAll('[data-shop-location-picker]').forEach(setupPicker);
    });

    function setupPicker(root) {
        const form = root.closest('form');
        const latitude = root.querySelector('[data-location-latitude]');
        const longitude = root.querySelector('[data-location-longitude]');
        const accuracy = root.querySelector('[data-location-accuracy]');
        const source = root.querySelector('[data-location-source]');
        const confirmed = root.querySelector('[data-location-confirmed]');
        const capture = root.querySelector('[data-capture-location]');
        const clear = root.querySelector('[data-clear-location]');
        const status = root.querySelector('[data-location-status]');
        const coordinates = root.querySelector('[data-location-coordinates]');
        const precision = root.querySelector('[data-location-precision]');
        const mapHost = root.querySelector('[data-location-map]');
        const mapFallback = root.querySelector('[data-location-map-fallback]');
        const openMap = root.querySelector('[data-open-location-map]');
        const capturePanel = root.querySelector('[data-location-capture-panel]');
        const modeInputs = [...root.querySelectorAll('input[name="location_mode"]')];
        const logisticsInputs = form ? [...form.querySelectorAll('input[name="logistics_type"]')] : [];

        let map = null;
        let marker = null;

        const selectedMode = () => root.querySelector('input[name="location_mode"]:checked')?.value || 'gps_now';
        const selectedLogistics = () => form?.querySelector('input[name="logistics_type"]:checked')?.value || root.dataset.logisticsType || 'ovanie';
        const hasCoordinates = () => Number.isFinite(Number(latitude?.value)) && Number.isFinite(Number(longitude?.value));

        function setStatus(message, tone = 'neutral') {
            if (!status) return;
            status.textContent = message;
            status.dataset.tone = tone;
        }

        function updateReadout() {
            if (!coordinates || !precision) return;

            if (!hasCoordinates()) {
                coordinates.textContent = 'Aucune position enregistrée';
                precision.textContent = 'Capturez le GPS lorsque vous êtes dans la boutique.';
                openMap?.classList.add('is-hidden');
                return;
            }

            const lat = Number(latitude.value);
            const lng = Number(longitude.value);
            const accuracyMeters = Number(accuracy?.value);

            coordinates.textContent = `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
            precision.textContent = Number.isFinite(accuracyMeters)
                ? `Précision annoncée par le téléphone : environ ${Math.round(accuracyMeters)} m.`
                : 'Position ajustée manuellement sur la carte.';

            if (openMap) {
                openMap.href = `https://www.google.com/maps?q=${lat},${lng}`;
                openMap.classList.remove('is-hidden');
            }

            showMap(lat, lng);
        }

        function writePosition(lat, lng, accuracyMeters, geoSource) {
            latitude.value = Number(lat).toFixed(7);
            longitude.value = Number(lng).toFixed(7);
            accuracy.value = Number.isFinite(Number(accuracyMeters)) ? Number(accuracyMeters).toFixed(2) : '';
            source.value = geoSource;
            confirmed.value = '1';

            const numericAccuracy = Number(accuracyMeters);
            if (geoSource === 'manual_map') {
                setStatus('Marqueur positionné manuellement. Vérifiez qu’il correspond au point d’enlèvement.', 'success');
            } else if (Number.isFinite(numericAccuracy) && numericAccuracy > 100) {
                setStatus('Position reçue, mais la précision est insuffisante. Rapprochez-vous de l’entrée et recommencez.', 'warning');
            } else {
                setStatus('Position exacte capturée. Vous pouvez déplacer le marqueur si nécessaire.', 'success');
            }

            updateReadout();
        }

        function showMap(lat, lng) {
            if (!mapHost) return;

            const token = root.dataset.mapboxToken || '';
            const style = root.dataset.mapboxStyle || 'mapbox://styles/mapbox/streets-v12';

            if (!token || !window.mapboxgl) {
                mapHost.classList.add('is-hidden');
                mapFallback?.classList.remove('is-hidden');
                return;
            }

            mapFallback?.classList.add('is-hidden');
            mapHost.classList.remove('is-hidden');

            if (!map) {
                window.mapboxgl.accessToken = token;
                map = new window.mapboxgl.Map({
                    container: mapHost,
                    style,
                    center: [lng, lat],
                    zoom: 16,
                    minZoom: 10,
                    maxZoom: 20,
                    dragRotate: false,
                    pitchWithRotate: false,
                });
                map.addControl(new window.mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

                marker = new window.mapboxgl.Marker({ color: '#f97316', draggable: true })
                    .setLngLat([lng, lat])
                    .addTo(map);

                marker.on('dragend', () => {
                    const point = marker.getLngLat();
                    writePosition(point.lat, point.lng, null, 'manual_map');
                });
            } else {
                marker?.setLngLat([lng, lat]);
                map.resize();
                map.easeTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 16) });
            }
        }

        function syncMode() {
            const active = selectedLogistics() === 'ovanie';
            root.classList.toggle('is-hidden', !active);

            if (!active) return;

            const captureNow = selectedMode() === 'gps_now';
            capturePanel?.classList.toggle('is-hidden', !captureNow);

            if (captureNow && hasCoordinates()) {
                updateReadout();
            }
        }

        capture?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                setStatus('Ce téléphone ou ce navigateur ne permet pas la géolocalisation.', 'danger');
                return;
            }

            capture.disabled = true;
            capture.dataset.originalLabel ||= capture.innerHTML;
            capture.innerHTML = '<span class="location-spinner" aria-hidden="true"></span> Recherche du signal GPS…';
            setStatus('Restez près de l’entrée de la boutique pendant la capture.', 'loading');

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    writePosition(
                        position.coords.latitude,
                        position.coords.longitude,
                        position.coords.accuracy,
                        'browser_gps',
                    );
                    capture.disabled = false;
                    capture.innerHTML = capture.dataset.originalLabel;
                },
                (error) => {
                    const message = {
                        1: 'Autorisez l’accès à la position dans les réglages du navigateur.',
                        2: 'Le signal GPS est indisponible. Déplacez-vous à l’extérieur puis recommencez.',
                        3: 'La recherche GPS a pris trop de temps. Recommencez.',
                    }[error.code] || 'La position n’a pas pu être obtenue.';

                    setStatus(message, 'danger');
                    capture.disabled = false;
                    capture.innerHTML = capture.dataset.originalLabel;
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 30000,
                },
            );
        });

        clear?.addEventListener('click', () => {
            latitude.value = '';
            longitude.value = '';
            accuracy.value = '';
            source.value = '';
            confirmed.value = '';
            mapHost?.classList.add('is-hidden');
            mapFallback?.classList.add('is-hidden');
            setStatus('La position a été effacée.', 'neutral');
            updateReadout();
        });

        modeInputs.forEach((input) => input.addEventListener('change', syncMode));
        logisticsInputs.forEach((input) => input.addEventListener('change', syncMode));

        form?.addEventListener('submit', (event) => {
            if (selectedLogistics() !== 'ovanie' || selectedMode() !== 'gps_now') return;

            if (!hasCoordinates() || confirmed.value !== '1') {
                event.preventDefault();
                setStatus('Capturez et confirmez la position avant de créer la boutique.', 'danger');
                root.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        syncMode();
        updateReadout();
    }
})();
