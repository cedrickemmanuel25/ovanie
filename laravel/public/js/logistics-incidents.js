(() => {
    'use strict';

    function text(form, selector, value, fallback = '—') {
        const node = form?.querySelector(selector);
        if (node) node.textContent = value || fallback;
    }

    const IMPACT_BY_TYPE = {
        panne_vehicule: 'blocked',
        accident: 'blocked',
        probleme_securite: 'blocked',
        colis_perdu: 'blocked',
        livraison_echouee: 'blocked',
        retour_point_vente: 'blocked',
        client_absent: 'rescheduled',
        client_introuvable: 'rescheduled',
        refus_reception: 'rescheduled',
        livraison_reportee: 'rescheduled',
        retard_important: 'delay',
        retard_livraison: 'delay',
        blocage_route: 'delay',
        adresse_introuvable: 'delay',
        adresse_incorrecte: 'delay',
        acces_chantier_difficile: 'delay',
        vendeur_pas_pret: 'delay',
        point_vente_pas_pret: 'delay',
        probleme_chargement: 'delay',
        produit_endommage: 'delay',
        produit_incomplet: 'delay',
        quantite_incorrecte: 'delay',
        probleme_carburant: 'delay',
        otp_impossible: 'delay',
        litige_client: 'delay',
        autre_incident: 'none',
    };

    function suggestImpact(form, incidentType, force = false) {
        if (!form || !incidentType) return;
        const target = IMPACT_BY_TYPE[incidentType] || 'none';
        const currentlyChecked = form.querySelector('input[name="impact_level"]:checked');
        if (currentlyChecked && !force) return;
        const input = form.querySelector(`input[name="impact_level"][value="${target}"]`);
        if (input) {
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function updateClientNotice(form) {
        const impact = form?.querySelector('input[name="impact_level"]:checked')?.value || '';
        const node = form?.querySelector('[data-incident-client-notice]');
        if (!node) return;
        node.dataset.impact = impact;
        node.classList.toggle('is-active', impact !== '' && impact !== 'none');
    }

    function updateIncidentMap(element, latitude, longitude, labelText) {
        const map = element?._directoryMap;
        if (!map || !window.L) return;

        if (element._incidentSelectionMarker) {
            map.removeLayer(element._incidentSelectionMarker);
            element._incidentSelectionMarker = null;
        }

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

        const safeLabel = document.createElement('span');
        safeLabel.textContent = labelText || 'Dernière position connue';

        element._incidentSelectionMarker = L.circleMarker([latitude, longitude], {
            radius: 8,
            color: '#ffffff',
            weight: 3,
            fillColor: '#087aff',
            fillOpacity: 1,
        }).addTo(map).bindTooltip(safeLabel, { permanent: true, direction: 'top' });

        map.setView([latitude, longitude], 13);
    }



    function setSourceStepState(form, option) {
        const step = form?.querySelector('[data-incident-source-step]');
        if (!step) return;

        const hasMission = Boolean(option && option.value);
        step.classList.toggle('is-locked', !hasMission);

        if (!hasMission) {
            form.querySelectorAll('[data-signal-source-option] input[name="signal_source"]').forEach((input) => {
                input.checked = false;
                input.disabled = true;
            });
            return;
        }

        updateSignalSourceAvailability(form, option);
    }

    function updateSignalSourceAvailability(form, option) {
        if (!form || !option) return;

        const availability = {
            driver_phone: option.dataset.hasDriver === '1',
            client_support: option.dataset.hasClient === '1',
            shop_contact: option.dataset.hasShop === '1',
            operations_control: true,
        };

        form.querySelectorAll('[data-signal-source-option]').forEach((card) => {
            const input = card.querySelector('input[name="signal_source"]');
            if (!input) return;
            const enabled = availability[input.value] !== false;
            input.disabled = !enabled;
            if (!enabled && input.checked) input.checked = false;
        });
    }


    function updateSignalSourcePreview(form) {
        if (!form) return;
        const mission = form.querySelector('[data-incident-mission]')?.selectedOptions?.[0] || null;
        const source = form.querySelector('input[name="signal_source"]:checked')?.value || '';
        const actorNode = form.querySelector('[data-incident-source-actor]');
        const contactNode = form.querySelector('[data-incident-source-contact]');
        if (!actorNode || !contactNode) return;

        if (!source) {
            actorNode.textContent = 'Choisissez l’origine du signalement';
            contactNode.textContent = 'Les coordonnées proviennent de la mission sélectionnée.';
            return;
        }

        let actor = '';
        let contact = '';
        if (source === 'driver_phone') {
            actor = mission?.dataset.driver || form.dataset.initialDriver || 'Livreur affecté';
            contact = mission?.dataset.driverPhone || form.dataset.initialDriverPhone || 'Téléphone du livreur non renseigné';
        } else if (source === 'client_support') {
            actor = mission?.dataset.client || form.dataset.initialClient || 'Client de la commande';
            contact = mission?.dataset.clientPhone || form.dataset.initialClientPhone || 'Téléphone du client non renseigné';
        } else if (source === 'shop_contact') {
            actor = mission?.dataset.shop || form.dataset.initialShop || 'Boutique de collecte';
            contact = mission?.dataset.shopPhone || form.dataset.initialShopPhone || 'Téléphone de la boutique non renseigné';
        } else if (source === 'operations_control') {
            actor = form.dataset.operatorName || 'Responsable logistique connecté';
            contact = 'Anomalie constatée dans le centre de suivi OVANIE';
        }

        actorNode.textContent = actor || 'Signalant non renseigné';
        contactNode.textContent = contact || 'Coordonnée non renseignée';
    }

    function applyIncidentMission(select) {
        const form = select.closest('form');
        const option = select.selectedOptions[0];
        const latitudeInput = form?.querySelector('[data-incident-latitude]');
        const longitudeInput = form?.querySelector('[data-incident-longitude]');
        const mapElement = form?.querySelector('[data-incident-map]');

        if (!form || !option || !option.value) {
            if (form) {
                form.action = '#';
                setSourceStepState(form, null);
            }
            text(form, '[data-incident-mission-ref]', 'Sélectionnez une mission');
            text(form, '[data-incident-order]', '—');
            text(form, '[data-incident-resource]', 'Non affecté');
            text(form, '[data-incident-status]', '—');
            text(form, '[data-incident-payment]', '—');
            text(form, '[data-incident-client]', '—');
            text(form, '[data-incident-client-phone]', '—');
            text(form, '[data-incident-shop]', '—');
            text(form, '[data-incident-pickup]', '—');
            text(form, '[data-incident-destination]', '—');
            text(form, '[data-incident-destination-full]', '—');
            text(form, '[data-incident-driver-status]', '—');
            text(form, '[data-incident-driver-phone]', '—');
            text(form, '[data-incident-gps-at]', 'Aucune position reçue');
            text(form, '[data-incident-lat-label]', 'Non disponible');
            text(form, '[data-incident-lng-label]', 'Non disponible');
            if (latitudeInput) latitudeInput.value = '';
            if (longitudeInput) longitudeInput.value = '';
            updateIncidentMap(mapElement, NaN, NaN, '');
            return;
        }

        form.action = option.dataset.action || '#';
        setSourceStepState(form, option);
        updateSignalSourcePreview(form);
        text(form, '[data-incident-mission-ref]', option.dataset.mission);
        text(form, '[data-incident-order]', option.dataset.order);
        text(form, '[data-incident-resource]', option.dataset.resource, 'Non affecté');
        text(form, '[data-incident-status]', option.dataset.status);
        text(form, '[data-incident-payment]', option.dataset.payment);
        text(form, '[data-incident-client]', option.dataset.client);
        text(form, '[data-incident-client-phone]', option.dataset.clientPhone);
        text(form, '[data-incident-shop]', option.dataset.shop);
        text(form, '[data-incident-pickup]', option.dataset.pickup);
        text(form, '[data-incident-destination]', option.dataset.destination?.split('·').slice(-2, -1)[0]?.trim() || option.dataset.destination);
        text(form, '[data-incident-destination-full]', option.dataset.destination);
        text(form, '[data-incident-driver-status]', option.dataset.driverStatus);
        text(form, '[data-incident-driver-phone]', option.dataset.driverPhone || 'Téléphone non renseigné');
        text(form, '[data-incident-gps-at]', option.dataset.gpsAt || 'Aucune position reçue');

        const latitude = Number(option.dataset.lat);
        const longitude = Number(option.dataset.lng);
        const hasGps = option.dataset.lat !== ''
            && option.dataset.lng !== ''
            && Number.isFinite(latitude)
            && Number.isFinite(longitude);

        if (hasGps) {
            if (latitudeInput) latitudeInput.value = latitude.toFixed(7);
            if (longitudeInput) longitudeInput.value = longitude.toFixed(7);
            text(form, '[data-incident-lat-label]', latitude.toFixed(6));
            text(form, '[data-incident-lng-label]', longitude.toFixed(6));
            updateIncidentMap(mapElement, latitude, longitude, option.dataset.driver || 'Dernière position connue');
        } else {
            if (latitudeInput) latitudeInput.value = '';
            if (longitudeInput) longitudeInput.value = '';
            text(form, '[data-incident-lat-label]', 'Non disponible');
            text(form, '[data-incident-lng-label]', 'Non disponible');
            updateIncidentMap(mapElement, NaN, NaN, '');
        }
    }

    function bootTypePickers() {
        document.querySelectorAll('[data-incident-type-picker]').forEach((picker) => {
            const label = picker.querySelector('[data-incident-type-label]');
            picker.querySelectorAll('input[name="incident_type"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (label) label.textContent = radio.dataset.label || radio.value;
                    picker.open = false;
                    suggestImpact(picker.closest('form'), radio.value, true);
                });
            });
        });
    }

    function bootIncidentForms() {
        document.querySelectorAll('[data-incident-mission]').forEach((select) => {
            select.addEventListener('change', () => applyIncidentMission(select));
            applyIncidentMission(select);
        });

        document.querySelectorAll('.incident-form').forEach((form) => {
            form.querySelectorAll('input[name="signal_source"]').forEach((input) => {
                input.addEventListener('change', () => updateSignalSourcePreview(form));
            });
            form.querySelectorAll('input[name="impact_level"]').forEach((input) => {
                input.addEventListener('change', () => updateClientNotice(form));
            });
            updateSignalSourcePreview(form);
            updateClientNotice(form);
            const selectedType = form.querySelector('input[name="incident_type"]:checked')?.value;
            if (selectedType) suggestImpact(form, selectedType, false);
            form.addEventListener('submit', (event) => {
                const mission = form.querySelector('[data-incident-mission]');
                if (mission && !mission.value) {
                    event.preventDefault();
                    mission.reportValidity();
                    return;
                }

                if (!form.querySelector('input[name="signal_source"]:checked')) {
                    event.preventDefault();
                    form.querySelector('input[name="signal_source"]')?.focus();
                    return;
                }

                if (!form.querySelector('input[name="incident_type"]:checked')) {
                    event.preventDefault();
                    const picker = form.querySelector('[data-incident-type-picker]');
                    if (picker) picker.open = true;
                    return;
                }

                if (!form.querySelector('input[name="impact_level"]:checked')) {
                    event.preventDefault();
                    form.querySelector('[data-incident-impact-grid]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });

        bootTypePickers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootIncidentForms, { once: true });
    } else {
        bootIncidentForms();
    }
})();
