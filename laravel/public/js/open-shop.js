(() => {
    'use strict';

    const body = document.body;
    const form = document.getElementById('openShopForm');

    if (!form) {
        return;
    }

    const MAX_STEPS = 5;
    let currentStep = Math.min(MAX_STEPS, Math.max(1, Number(body.dataset.initialStep || 1)));
    let map = null;
    let marker = null;
    let mapReady = false;
    let isSubmitting = false;
    let draftSaveTimer = null;
    let backgroundGeoTimer = null;
    let isApplyingGeoResult = false;
    let lastGeoSignature = '';
    let geoRequestSequence = 0;
    let modalStartPosition = null;
    let backgroundOvanieGpsPromise = null;

    const DRAFT_KEY = 'ovanie.open-shop.draft.v3';
    const DRAFT_TTL_MS = 7 * 24 * 60 * 60 * 1000;
    const DRAFT_EXCLUDED_FIELDS = new Set([
        '_token',
        'password',
        'password_confirmation',
        'identityNumber',
        'mmNumber',
    ]);
    const DRAFT_INCLUDED_HIDDEN_FIELDS = new Set([
        'commune',
        'commune_id',
        'district',
        'quarter_id',
        'address',
        'landmark',
        'landmark_id',
        'landmark_source',
        'landmark_latitude',
        'landmark_longitude',
        'latitude',
        'longitude',
        'geo_accuracy',
        'geo_source',
        'geo_precision',
        'geo_precision_score',
    ]);

    const mobileSelectMedia = window.matchMedia('(max-width: 720px)');
    let mobileSelectOverlay = null;
    let mobileSelectPanel = null;
    let mobileSelectTitle = null;
    let mobileSelectOptions = null;
    let activeMobileSelect = null;

    const stepSections = [...form.querySelectorAll('.wizard-step')];
    const progressSteps = [...document.querySelectorAll('[data-progress-step]')];
    const previousButton = document.getElementById('previousStep');
    const nextButton = document.getElementById('nextStep');
    const submitButton = document.getElementById('submitShop');
    const currentStepNumber = document.getElementById('currentStepNumber');
    const currentStepLabel = document.getElementById('currentStepLabel');
    const stepLabels = {
        1: 'Informations personnelles',
        2: 'Informations boutique',
        3: 'Vérification identité',
        4: 'Paiement vendeur',
        5: 'Conditions vendeur',
    };
    const passwordToggleButtons = [...document.querySelectorAll('[data-password-toggle]')];

    const companyFields = document.getElementById('companyFields');
    const sellerType = document.getElementById('sellerType');
    const sellerLogisticsNotice = document.getElementById('sellerLogisticsNotice');
    const identityPdfFields = document.getElementById('identityPdfFields');
    const identityScanFields = document.getElementById('identityScanFields');

    const communeInput = document.getElementById('commune');
    const communeSearchInput = document.getElementById('communeSearch');
    const communeCombobox = document.querySelector('[data-geo-combobox="commune"]');
    const communeOptions = document.getElementById('communeOptions');
    const communeToggle = communeCombobox?.querySelector('.geo-combobox__toggle') || null;

    const quarterInput = document.getElementById('district');
    const quarterSearchInput = document.getElementById('districtSearch');
    const quarterCombobox = document.querySelector('[data-geo-combobox="locality"]');
    const quarterOptions = document.getElementById('districtOptions');
    const quarterToggle = quarterCombobox?.querySelector('.geo-combobox__toggle') || null;

    const communeIdInput = document.getElementById('commune_id');
    const quarterIdInput = document.getElementById('quarter_id');
    const landmarkSearchState = document.getElementById('landmarkSearchState');
    const localityFeedback = document.getElementById('localityFeedback');
    const localityRetryButton = document.getElementById('localityRetryButton');
    const landmarkInput = document.getElementById('landmark');
    const landmarkIdInput = document.getElementById('landmark_id');
    const landmarkSourceInput = document.getElementById('landmark_source');
    const landmarkLatitudeInput = document.getElementById('landmark_latitude');
    const landmarkLongitudeInput = document.getElementById('landmark_longitude');
    const addressInput = document.getElementById('address');
    let localityRequestSequence = 0;

    const useLocationButton = document.getElementById('useLocationButton');
    const verifyLocationButton = document.getElementById('verifyLocationButton');
    const retryLocationButton = document.getElementById('retryLocationButton');
    const geoStatus = document.getElementById('geoStatus');
    const locationLayout = document.querySelector('.shop-location-card');

    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const accuracyInput = document.getElementById('geo_accuracy');
    const geoSourceInput = document.getElementById('geo_source');
    const geoPrecisionInput = document.getElementById('geo_precision');
    const geoPrecisionScoreInput = document.getElementById('geo_precision_score');

    const locationSummaryCard = document.getElementById('locationSummaryCard');
    const locationSummaryTitle = document.getElementById('locationSummaryTitle');
    const locationSummaryText = document.getElementById('locationSummaryText');
    const locationPrecisionBadge = document.getElementById('locationPrecisionBadge');
    const locationResolvedAddress = document.getElementById('locationResolvedAddress');
    const locationSourceLabel = document.getElementById('locationSourceLabel');

    const locationMapModal = document.getElementById('locationMapModal');
    const closeLocationMapButton = document.getElementById('closeLocationMapButton');
    const cancelLocationMapButton = document.getElementById('cancelLocationMapButton');
    const confirmLocationButton = document.getElementById('confirmLocationButton');

    const mapAddressLabel = document.getElementById('mapAddressLabel');
    const latitudeLabel = document.getElementById('latitudeLabel');
    const longitudeLabel = document.getElementById('longitudeLabel');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const communesUrl = body.dataset.geoCommunesUrl;
    const quartersUrl = body.dataset.geoQuartersUrl;
    const landmarksUrl = body.dataset.geoLandmarksUrl;
    const reverseUrl = body.dataset.geoReverseUrl;
    const hasServerErrors = body.dataset.hasServerErrors === '1';
    const resolveUrl = body.dataset.geoResolveUrl;
    const localityFallbackCatalog = (() => {
        const template = document.getElementById('openShopLocalityFallback');
        const raw = template?.content?.textContent || template?.textContent || '{}';

        try {
            const parsed = JSON.parse(raw.trim() || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_) {
            return {};
        }
    })();
    let serverCorrectionMode = hasServerErrors;

    const submitLabel = submitButton?.querySelector('span:nth-last-child(2)');
    const defaultSubmitLabel = submitLabel?.textContent?.trim() || 'Ouvrir ma boutique';
    const addressFieldIds = ['region', 'city', 'commune', 'district', 'address', 'landmark'];
    const geocoderWritableFieldIds = ['region', 'city', 'commune', 'district', 'address'];

    function selectedLogisticsType() {
        return form.querySelector('input[name="logistics_type"]:checked')?.value || 'ovanie';
    }

    function hasExactOvanieGps() {
        const source = String(geoSourceInput?.value || '').toLowerCase();
        const lat = Number(latitudeInput?.value);
        const lng = Number(longitudeInput?.value);

        return Number.isFinite(lat)
            && Number.isFinite(lng)
            && ['browser_gps', 'device_gps', 'manual_map', 'logistics_verified'].includes(source);
    }

    function captureOvanieGpsInBackground({ required = false, force = false } = {}) {
        if (selectedLogisticsType() !== 'ovanie') {
            return Promise.resolve(true);
        }

        if (!force && hasExactOvanieGps()) {
            return Promise.resolve(true);
        }

        if (backgroundOvanieGpsPromise) {
            return backgroundOvanieGpsPromise;
        }

        if (!navigator.geolocation) {
            if (required) {
                showAjaxAlert(
                    'Localisation requise',
                    'Votre navigateur ne permet pas la localisation. OVANIE Logistics a besoin de la position GPS exacte de la boutique.'
                );
            }
            return Promise.resolve(false);
        }

        backgroundOvanieGpsPromise = new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition((position) => {
                const lat = Number(position.coords.latitude);
                const lng = Number(position.coords.longitude);
                const accuracy = Number(position.coords.accuracy || 0);

                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    resolve(false);
                    return;
                }

                const precision = gpsPrecision(accuracy);
                storeCoordinates(lng, lat, 'browser_gps', accuracy, precision, null);
                saveDraftNow();
                resolve(true);
            }, (error) => {
                if (required) {
                    const messages = {
                        1: 'Autorisez la localisation dans le navigateur afin qu’OVANIE enregistre la position GPS exacte de la boutique.',
                        2: 'La position GPS est indisponible. Activez la localisation de l’appareil puis réessayez.',
                        3: 'La recherche GPS a expiré. Vérifiez que la localisation est activée puis réessayez.',
                    };
                    showAjaxAlert('Localisation GPS requise', messages[error.code] || 'Impossible d’obtenir la position GPS exacte de la boutique.');
                }
                resolve(false);
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 15000,
            });
        }).finally(() => {
            backgroundOvanieGpsPromise = null;
        });

        return backgroundOvanieGpsPromise;
    }

    function renderStep() {
        stepSections.forEach((section) => {
            section.hidden = Number(section.dataset.step) !== currentStep;
        });

        progressSteps.forEach((button) => {
            const step = Number(button.dataset.progressStep);
            button.classList.toggle('is-active', step === currentStep);
            button.classList.toggle('is-complete', step < currentStep);
            button.setAttribute('aria-current', step === currentStep ? 'step' : 'false');
        });

        const canSubmitCorrectionHere = serverCorrectionMode && currentStep !== MAX_STEPS;

        previousButton.hidden = currentStep === 1;
        nextButton.hidden = currentStep === MAX_STEPS || canSubmitCorrectionHere;
        submitButton.hidden = currentStep !== MAX_STEPS && !canSubmitCorrectionHere;
        updateSubmitLabel();
        currentStepNumber.textContent = String(currentStep);
        if (currentStepLabel) {
            currentStepLabel.textContent = stepLabels[currentStep] || '';
        }
        body.dataset.currentStep = String(currentStep);

        if (currentStep === 2 && selectedLogisticsType() === 'ovanie' && !hasExactOvanieGps()) {
            window.setTimeout(() => captureOvanieGpsInBackground({ required: false }), 250);
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function visibleFields(step = currentStep) {
        const section = stepSections.find((item) => Number(item.dataset.step) === step);
        if (!section) {
            return [];
        }

        return [...section.querySelectorAll('input, select, textarea')]
            .filter((field) => !field.disabled)
            .filter((field) => field.type !== 'hidden')
            .filter((field) => isFieldApplicable(field, section));
    }

    // Le panneau d'une étape non active est lui-même masqué. Pour valider tout le
    // formulaire avant envoi, on ignore donc le `hidden` du panneau racine, mais
    // on respecte les sous-blocs conditionnels (entreprise, KYC, etc.).
    function isFieldApplicable(element, section) {
        let node = element.parentElement;
        while (node && node !== section) {
            if (node.hidden || window.getComputedStyle(node).display === 'none') {
                return false;
            }
            node = node.parentElement;
        }
        return true;
    }

    function validateCurrentStep() {
        const fields = visibleFields();
        let firstInvalid = null;

        fields.forEach((field) => {
            field.classList.add('is-touched');
            const wrapper = field.closest('.field');
            wrapper?.classList.toggle('has-client-error', !field.checkValidity());

            if (!field.checkValidity() && !firstInvalid) {
                firstInvalid = field;
            }
        });

        if (firstInvalid) {
            presentInvalidField(firstInvalid);
            return false;
        }

        return true;
    }

    function goToStep(step) {
        currentStep = Math.min(MAX_STEPS, Math.max(1, step));
        saveDraftNow();
        renderStep();
    }

    function draftFields() {
        return [...form.querySelectorAll('input[name], select[name], textarea[name]')]
            .filter((field) => !DRAFT_EXCLUDED_FIELDS.has(field.name))
            .filter((field) => {
                if (field.type === 'hidden') {
                    return DRAFT_INCLUDED_HIDDEN_FIELDS.has(field.name);
                }

                return !['password', 'file', 'submit', 'button'].includes(field.type);
            });
    }

    function collectDraftData() {
        const values = {};

        draftFields().forEach((field) => {
            if (field.type === 'radio') {
                if (field.checked) {
                    values[field.name] = field.value;
                }
                return;
            }

            if (field.type === 'checkbox') {
                values[field.name] = Boolean(field.checked);
                return;
            }

            values[field.name] = field.value;
        });

        return {
            savedAt: Date.now(),
            step: currentStep,
            values,
        };
    }

    function saveDraftNow() {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify(collectDraftData()));
        } catch (_) {
            // Le formulaire reste pleinement utilisable si le stockage local est indisponible.
        }
    }

    function scheduleDraftSave() {
        window.clearTimeout(draftSaveTimer);
        draftSaveTimer = window.setTimeout(saveDraftNow, 300);
    }

    function restoreDraft() {
        if (hasServerErrors) {
            return;
        }

        try {
            const raw = window.localStorage.getItem(DRAFT_KEY);
            if (!raw) {
                return;
            }

            const draft = JSON.parse(raw);
            if (!draft?.savedAt || Date.now() - draft.savedAt > DRAFT_TTL_MS) {
                window.localStorage.removeItem(DRAFT_KEY);
                return;
            }

            Object.entries(draft.values || {}).forEach(([name, value]) => {
                const fields = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
                if (!fields.length) {
                    return;
                }

                fields.forEach((field) => {
                    if (field.type === 'radio') {
                        field.checked = field.value === value;
                    } else if (field.type === 'checkbox') {
                        field.checked = Boolean(value);
                    } else if (!field.readOnly) {
                        field.value = String(value ?? '');
                    }
                });
            });

            const savedStep = Number(draft.step || 1);
            if (Number.isFinite(savedStep)) {
                currentStep = Math.min(MAX_STEPS, Math.max(1, savedStep));
            }
        } catch (_) {
            window.localStorage.removeItem(DRAFT_KEY);
        }
    }

    nextButton?.addEventListener('click', async () => {
        if (!validateCurrentStep()) {
            return;
        }

        if (currentStep === 2 && selectedLogisticsType() === 'ovanie') {
            const gpsReady = await captureOvanieGpsInBackground({ required: true, force: true });
            if (!gpsReady) {
                return;
            }
        }

        goToStep(currentStep + 1);
    });

    previousButton?.addEventListener('click', () => goToStep(currentStep - 1));

    progressSteps.forEach((button) => {
        button.addEventListener('click', () => {
            const requestedStep = Number(button.dataset.progressStep);
            if (requestedStep < currentStep) {
                goToStep(requestedStep);
            }
        });
    });

    form.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
            return;
        }

        scheduleDraftSave();
        clearDynamicErrorForField(target);

        if (target.classList.contains('is-touched')) {
            target.closest('.field')?.classList.toggle('has-client-error', !target.checkValidity());
        }
    });

    form.addEventListener('change', (event) => {
        scheduleDraftSave();
        const target = event.target;
        if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement) {
            clearDynamicErrorForField(target);
        }
    });
    window.addEventListener('pagehide', saveDraftNow);
    window.addEventListener('pageshow', () => setSubmittingState(false));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        for (let step = 1; step <= MAX_STEPS; step += 1) {
            const fields = visibleFields(step);
            const invalid = fields.find((field) => !field.checkValidity());

            if (invalid) {
                goToStep(step);
                window.setTimeout(() => {
                    invalid.classList.add('is-touched');
                    presentInvalidField(invalid);
                }, 80);
                return;
            }
        }

        if (selectedLogisticsType() === 'ovanie') {
            const gpsReady = await captureOvanieGpsInBackground({ required: true });
            if (!gpsReady) {
                return;
            }
        }

        saveDraftNow();
        clearAjaxAlert();
        setSubmittingState(true);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (_) {
                payload = {};
            }

            if (response.ok) {
                try {
                    window.localStorage.removeItem(DRAFT_KEY);
                } catch (_) {
                    // Le succès ne dépend pas du stockage local.
                }

                window.location.assign(payload.redirect_url || '/vendor/dashboard');
                return;
            }

            if (response.status === 422 && payload.errors) {
                renderServerErrors(payload.errors);
                return;
            }

            const message = response.status === 419
                ? 'Votre session a expiré. Actualisez la page puis validez à nouveau : les informations saisies restent enregistrées dans votre navigateur.'
                : (response.status === 413
                    ? 'Un fichier dépasse la taille autorisée par le serveur. Réduisez sa taille puis validez à nouveau.'
                    : (payload.message || payload.error || 'La boutique n’a pas pu être créée. Corrigez le problème indiqué puis réessayez.'));

            showAjaxAlert('Validation impossible', message);
        } catch (_) {
            showAjaxAlert(
                'Connexion interrompue',
                'La requête n’a pas abouti. Vos informations et vos fichiers restent dans le formulaire ; vérifiez votre connexion puis validez à nouveau.'
            );
        } finally {
            setSubmittingState(false);
        }
    });


    function updateSubmitLabel() {
        if (!submitLabel || isSubmitting) {
            return;
        }

        submitLabel.textContent = serverCorrectionMode
            ? 'Valider les corrections'
            : defaultSubmitLabel;
    }

    function setSubmittingState(active) {
        isSubmitting = active;
        submitButton.disabled = active;
        submitButton.classList.toggle('is-loading', active);

        if (submitLabel) {
            submitLabel.textContent = active
                ? (serverCorrectionMode ? 'Validation en cours...' : 'Création en cours...')
                : (serverCorrectionMode ? 'Valider les corrections' : defaultSubmitLabel);
        }
    }

    function ajaxAlert() {
        let alert = document.getElementById('openShopAjaxAlert');

        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'openShopAjaxAlert';
            alert.className = 'alert alert-error';
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'assertive');
            alert.hidden = true;
            form.before(alert);
        }

        return alert;
    }

    function showAjaxAlert(title, message) {
        const alert = ajaxAlert();
        alert.innerHTML = `
            <div class="alert-icon">!</div>
            <div>
                <strong>${escapeHtml(title)}</strong>
                <p>${escapeHtml(message)}</p>
            </div>`;
        alert.hidden = false;
        alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearAjaxAlert() {
        const alert = document.getElementById('openShopAjaxAlert');
        if (alert) {
            alert.hidden = true;
            alert.innerHTML = '';
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function errorField(name) {
        const normalized = String(name || '').split('.')[0];
        if (!normalized) {
            return null;
        }

        const aliases = {
            commune: communeSearchInput,
            commune_id: communeSearchInput,
            district: quarterSearchInput,
            quarter_id: quarterSearchInput,
            landmark: landmarkInput,
            landmark_id: landmarkInput,
            address: landmarkInput,
            geo_source: verifyLocationButton || useLocationButton,
            latitude: verifyLocationButton || useLocationButton,
            longitude: verifyLocationButton || useLocationButton,
        };

        return aliases[normalized] || form.querySelector(`[name="${CSS.escape(normalized)}"]`);
    }

    function dynamicErrorHost(field) {
        const choiceGrid = field.closest('.choice-grid');

        return field.closest('.field')
            || choiceGrid?.parentElement
            || field.closest('.terms-card')
            || field.parentElement;
    }

    function clearDynamicServerErrors() {
        form.querySelectorAll('.js-server-error').forEach((error) => error.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
    }

    function clearDynamicErrorForField(field) {
        const name = field.name || field.dataset.validationName;
        if (!name) {
            return;
        }

        form.querySelectorAll(`.js-server-error[data-error-for="${CSS.escape(name)}"]`)
            .forEach((error) => error.remove());
        field.removeAttribute('aria-invalid');

        const host = dynamicErrorHost(field);
        if (host && !host.querySelector('.field-error')) {
            host.classList.remove('has-client-error');
        }
    }

    function renderServerErrors(errors) {
        clearDynamicServerErrors();
        serverCorrectionMode = true;
        updateSubmitLabel();

        let firstField = null;
        let firstMessage = '';

        Object.entries(errors || {}).forEach(([name, messages]) => {
            const field = errorField(name);
            const message = Array.isArray(messages) ? messages[0] : messages;

            if (!firstMessage && message) {
                firstMessage = String(message);
            }

            if (!field) {
                return;
            }

            const host = dynamicErrorHost(field);
            if (!host) {
                return;
            }

            const error = document.createElement('p');
            error.className = 'field-error js-server-error';
            error.dataset.errorFor = String(name || '').split('.')[0];
            error.textContent = String(message || 'Cette information doit être corrigée.');
            host.appendChild(error);
            host.classList.add('has-client-error');
            field.setAttribute('aria-invalid', 'true');

            if (!firstField) {
                firstField = field;
            }
        });

        showAjaxAlert(
            'Le formulaire contient une information à corriger.',
            firstMessage || 'Corrigez le champ indiqué puis cliquez sur « Valider les corrections ». Le formulaire ne sera pas recommencé.'
        );

        if (firstField) {
            const step = Number(firstField.closest('.wizard-step')?.dataset.step || currentStep);
            goToStep(step);
            window.setTimeout(() => presentServerField(firstField), 100);
        }
    }

    function presentServerField(field) {
        const target = field instanceof HTMLSelectElement && mobileSelectMedia.matches
            ? field.closest('.field')?.querySelector('.mobile-select-trigger') || field
            : field;

        target.focus({ preventScroll: true });
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function focusInitialServerError() {
        const section = stepSections.find((item) => Number(item.dataset.step) === currentStep);
        const error = section?.querySelector('.field-error');

        if (!error) {
            return;
        }

        const field = error.closest('.field')?.querySelector('input, select, textarea')
            || section.querySelector('input[aria-invalid="true"], select[aria-invalid="true"], textarea[aria-invalid="true"]');

        error.scrollIntoView({ behavior: 'smooth', block: 'center' });
        field?.focus({ preventScroll: true });
    }

    function presentInvalidField(field) {
        field.closest('.field')?.classList.add('has-client-error');

        if (field instanceof HTMLSelectElement && mobileSelectMedia.matches) {
            const trigger = field.closest('.field')?.querySelector('.mobile-select-trigger');
            trigger?.focus({ preventScroll: true });
            trigger?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => openMobileSelect(field), 180);
            return;
        }

        field.reportValidity();
        field.focus({ preventScroll: true });
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function lucideChevronDown() {
        return `
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
            </svg>`;
    }

    function lucideX() {
        return `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>`;
    }

    function lucideCheck() {
        return `
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m20 6-11 11-5-5"/>
            </svg>`;
    }

    function ensureMobileSelectSheet() {
        if (mobileSelectOverlay) {
            return;
        }

        mobileSelectOverlay = document.createElement('div');
        mobileSelectOverlay.className = 'mobile-select-overlay';
        mobileSelectOverlay.hidden = true;
        mobileSelectOverlay.innerHTML = `
            <button type="button" class="mobile-select-backdrop" aria-label="Fermer la liste"></button>
            <section class="mobile-select-panel" role="dialog" aria-modal="true" aria-labelledby="mobileSelectTitle">
                <header class="mobile-select-panel__head">
                    <div>
                        <span>Choisir une option</span>
                        <h3 id="mobileSelectTitle"></h3>
                    </div>
                    <button type="button" class="mobile-select-close" aria-label="Fermer">${lucideX()}</button>
                </header>
                <div class="mobile-select-options" role="listbox"></div>
            </section>`;

        document.body.appendChild(mobileSelectOverlay);
        mobileSelectPanel = mobileSelectOverlay.querySelector('.mobile-select-panel');
        mobileSelectTitle = mobileSelectOverlay.querySelector('#mobileSelectTitle');
        mobileSelectOptions = mobileSelectOverlay.querySelector('.mobile-select-options');

        mobileSelectOverlay.querySelector('.mobile-select-backdrop')?.addEventListener('click', closeMobileSelect);
        mobileSelectOverlay.querySelector('.mobile-select-close')?.addEventListener('click', closeMobileSelect);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !mobileSelectOverlay.hidden) {
                closeMobileSelect();
            }
        });
    }

    function selectLabel(select) {
        const label = select.closest('.field')?.querySelector(`label[for="${CSS.escape(select.id)}"]`);
        if (!label) {
            return 'Sélectionner';
        }

        return label.textContent.replace('*', '').trim();
    }

    function updateMobileSelectTrigger(select) {
        const trigger = select.closest('.field')?.querySelector('.mobile-select-trigger');
        if (!trigger) {
            return;
        }

        const selected = select.options[select.selectedIndex];
        const valueLabel = trigger.querySelector('.mobile-select-trigger__value');
        if (valueLabel) {
            valueLabel.textContent = selected?.textContent?.trim() || 'Sélectionner';
        }

        trigger.classList.toggle('is-placeholder', !select.value);
        trigger.setAttribute('aria-invalid', select.checkValidity() ? 'false' : 'true');
    }

    function openMobileSelect(select) {
        if (!mobileSelectMedia.matches || select.disabled) {
            return;
        }

        ensureMobileSelectSheet();
        activeMobileSelect = select;
        mobileSelectTitle.textContent = selectLabel(select);
        mobileSelectOptions.innerHTML = '';

        [...select.options].forEach((option) => {
            if (option.hidden) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mobile-select-option';
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            button.disabled = option.disabled;

            const label = document.createElement('span');
            label.textContent = option.textContent.trim();
            button.appendChild(label);

            const indicator = document.createElement('span');
            indicator.className = 'mobile-select-option__check';
            indicator.innerHTML = option.selected ? lucideCheck() : '';
            button.appendChild(indicator);

            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.classList.add('is-touched');
                select.closest('.field')?.classList.toggle('has-client-error', !select.checkValidity());
                updateMobileSelectTrigger(select);
                closeMobileSelect();
            });

            mobileSelectOptions.appendChild(button);
        });

        mobileSelectOverlay.hidden = false;
        document.body.classList.add('mobile-select-open');
        requestAnimationFrame(() => mobileSelectOverlay.classList.add('is-open'));
        window.setTimeout(() => {
            const selected = mobileSelectOptions.querySelector('[aria-selected="true"]');
            selected?.scrollIntoView({ block: 'nearest' });
        }, 80);
    }

    function closeMobileSelect() {
        if (!mobileSelectOverlay || mobileSelectOverlay.hidden) {
            return;
        }

        mobileSelectOverlay.classList.remove('is-open');
        document.body.classList.remove('mobile-select-open');
        const trigger = activeMobileSelect?.closest('.field')?.querySelector('.mobile-select-trigger');

        window.setTimeout(() => {
            mobileSelectOverlay.hidden = true;
            activeMobileSelect = null;
            trigger?.focus({ preventScroll: true });
        }, 180);
    }

    function enhanceMobileSelect(select) {
        if (!select.id || select.dataset.mobileEnhanced === '1') {
            return;
        }

        select.dataset.mobileEnhanced = '1';
        select.classList.add('mobile-select-source');

        const control = document.createElement('div');
        control.className = 'mobile-select-control';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'mobile-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-controls', select.id);
        trigger.innerHTML = `
            <span class="mobile-select-trigger__value"></span>
            <span class="mobile-select-trigger__icon">${lucideChevronDown()}</span>`;

        trigger.addEventListener('click', () => openMobileSelect(select));
        control.appendChild(trigger);
        select.insertAdjacentElement('afterend', control);

        const label = select.closest('.field')?.querySelector(`label[for="${CSS.escape(select.id)}"]`);
        label?.addEventListener('click', (event) => {
            if (!mobileSelectMedia.matches) {
                return;
            }
            event.preventDefault();
            trigger.focus();
            openMobileSelect(select);
        });

        select.addEventListener('change', () => updateMobileSelectTrigger(select));
        updateMobileSelectTrigger(select);
    }

    function initMobileSelects() {
        form.querySelectorAll('select').forEach(enhanceMobileSelect);
        ensureMobileSelectSheet();
    }

    function setRequired(element, required) {
        if (!element) {
            return;
        }
        element.required = required;
        element.disabled = !required && Boolean(element.closest('[hidden]'));
    }

    function syncSellerTypeFields() {
        const isCompany = sellerType?.value === 'entreprise';
        companyFields.hidden = !isCompany;

        ['companyName', 'legalForm', 'rccm', 'taxpayerNumber', 'rccmFile', 'taxFile'].forEach((id) => {
            const field = document.getElementById(id);
            if (!field) return;
            field.required = isCompany;
            field.disabled = !isCompany;
        });
    }

    sellerType?.addEventListener('change', syncSellerTypeFields);

    function syncLogisticsNotice() {
        if (!sellerLogisticsNotice) {
            return;
        }

        const selected = form.querySelector('input[name="logistics_type"]:checked')?.value;
        sellerLogisticsNotice.hidden = selected !== 'seller';
    }

    form.querySelectorAll('input[name="logistics_type"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            syncLogisticsNotice();
            if (radio.checked && radio.value === 'ovanie') {
                captureOvanieGpsInBackground({ required: false, force: true });
            }
        });
    });

    function syncIdentityUploadFields() {
        const mode = form.querySelector('input[name="identityUploadMode"]:checked')?.value || 'pdf';
        const pdf = mode === 'pdf';

        identityPdfFields.hidden = !pdf;
        identityScanFields.hidden = pdf;

        const pdfInput = document.getElementById('identityFile');
        const frontInput = document.getElementById('identityFileFront');
        const backInput = document.getElementById('identityFileBack');

        pdfInput.required = pdf;
        pdfInput.disabled = !pdf;
        frontInput.required = !pdf;
        backInput.required = !pdf;
        frontInput.disabled = pdf;
        backInput.disabled = pdf;
    }

    form.querySelectorAll('input[name="identityUploadMode"]').forEach((radio) => {
        radio.addEventListener('change', syncIdentityUploadFields);
    });

    const description = document.getElementById('description');
    const descriptionCounter = document.getElementById('descriptionCounter');

    function updateDescriptionCounter() {
        if (!description || !descriptionCounter) return;
        descriptionCounter.textContent = `${description.value.length}/700`;
    }

    description?.addEventListener('input', updateDescriptionCounter);

    function formatCiPhoneValue(value) {
        let digits = String(value || '').replace(/\D+/g, '');

        if (digits.startsWith('225') && digits.length >= 13) {
            digits = digits.slice(-10);
        } else {
            digits = digits.slice(0, 10);
        }

        return digits.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
    }

    function bindCiPhoneFormatter(id) {
        const input = document.getElementById(id);
        if (!input) {
            return;
        }

        const applyFormatting = () => {
            input.value = formatCiPhoneValue(input.value);
        };

        input.addEventListener('input', applyFormatting);
        input.addEventListener('blur', applyFormatting);
        applyFormatting();
    }

    ['sellerPhone', 'whatsapp', 'mmNumber'].forEach(bindCiPhoneFormatter);

    const lucideEyeIcon = `
        <svg class="lucide lucide-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>`;

    const lucideEyeOffIcon = `
        <svg class="lucide lucide-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m2 2 20 20"/>
            <path d="M6.71 6.71C4.82 8.04 3.43 9.91 2.62 11.91a1 1 0 0 0 0 .18C4.2 16.08 7.78 18.5 12 18.5c1.34 0 2.62-.25 3.79-.71"/>
            <path d="M10.73 5.08A11 11 0 0 1 12 5c4.22 0 7.8 2.42 9.38 6.41a1 1 0 0 1 0 .18 11 11 0 0 1-1.11 2.08"/>
            <path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"/>
        </svg>`;

    passwordToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('aria-controls');
            const input = targetId ? document.getElementById(targetId) : null;
            const icon = button.querySelector('.password-toggle__icon');

            if (!input || !icon) {
                return;
            }

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.setAttribute('aria-label', willShow ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            button.classList.toggle('is-active', willShow);
            icon.innerHTML = willShow ? lucideEyeOffIcon : lucideEyeIcon;
        });
    });

    function setLocalityStatus(message, tone = 'neutral') {
        if (!landmarkSearchState) {
            return;
        }

        landmarkSearchState.textContent = message;
        landmarkSearchState.classList.remove('is-loading', 'is-error', 'is-success');
        localityFeedback?.classList.remove('is-loading', 'is-error', 'is-success');

        if (tone !== 'neutral') {
            landmarkSearchState.classList.add(`is-${tone}`);
            localityFeedback?.classList.add(`is-${tone}`);
        }

        if (localityRetryButton) {
            localityRetryButton.hidden = tone !== 'error';
        }
    }

    function draftValue(name) {
        try {
            const raw = window.localStorage.getItem(DRAFT_KEY);
            if (!raw) return '';
            const draft = JSON.parse(raw);
            if (!draft?.savedAt || Date.now() - draft.savedAt > DRAFT_TTL_MS) return '';
            return String(draft.values?.[name] ?? '');
        } catch (_) {
            return '';
        }
    }

    async function fetchJson(url, params = {}) {
        if (!url) {
            throw new Error('Service de localisation indisponible.');
        }
        const requestUrl = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value) !== '') {
                requestUrl.searchParams.set(key, String(value));
            }
        });

        const response = await fetch(requestUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'Impossible de charger les données de localisation.');
        }
        return payload;
    }

    function normalizeLocalitySearch(value) {
        return String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .replace(/\s+/g, ' ');
    }

    function optionButtons(menu) {
        return menu ? [...menu.querySelectorAll('.geo-combobox__option')] : [];
    }

    function closeCombobox(input, menu) {
        if (!input || !menu) return;
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        optionButtons(menu).forEach((option) => option.classList.remove('is-active'));
    }

    function visibleOptions(menu) {
        return optionButtons(menu).filter((option) => !option.hidden);
    }

    function setActiveOption(menu, option) {
        optionButtons(menu).forEach((item) => item.classList.toggle('is-active', item === option));
        option?.scrollIntoView({ block: 'nearest' });
    }

    function filterCombobox(input, menu, { search = true } = {}) {
        if (!input || !menu) return [];

        const query = search ? normalizeLocalitySearch(input.value) : '';
        const options = optionButtons(menu);
        let visibleCount = 0;

        options.forEach((option) => {
            const haystack = normalizeLocalitySearch(option.dataset.search || option.dataset.value || option.textContent);
            const matches = query === '' || haystack.includes(query);
            option.hidden = !matches;
            option.classList.remove('is-active');
            if (matches) visibleCount += 1;
        });

        const emptyState = menu.querySelector('[data-empty]');
        if (emptyState) emptyState.hidden = visibleCount !== 0;

        const visible = visibleOptions(menu);
        if (visible.length) setActiveOption(menu, visible[0]);
        return visible;
    }

    function openCombobox(input, menu, { search = false } = {}) {
        if (!input || !menu || input.disabled) return;
        document.querySelectorAll('.geo-combobox__menu').forEach((otherMenu) => {
            if (otherMenu !== menu) {
                const otherInput = document.querySelector(`[aria-controls="${otherMenu.id}"]`);
                closeCombobox(otherInput, otherMenu);
            }
        });
        // Opening a selector shows the whole catalogue. Only typing filters it:
        // a saved value or browser autofill (e.g. "Abidjan") is not a search.
        filterCombobox(input, menu, { search });
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function moveActiveOption(menu, direction) {
        const options = visibleOptions(menu);
        if (!options.length) return;
        const currentIndex = options.findIndex((option) => option.classList.contains('is-active'));
        const nextIndex = currentIndex < 0
            ? 0
            : (currentIndex + direction + options.length) % options.length;
        setActiveOption(menu, options[nextIndex]);
    }

    function bindCombobox({ input, menu, toggle, onSelect, onFreeInput }) {
        if (!input || !menu) return;

        input.addEventListener('focus', () => openCombobox(input, menu));
        input.addEventListener('click', () => openCombobox(input, menu));
        input.addEventListener('input', () => {
            onFreeInput?.();
            openCombobox(input, menu, { search: true });
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (menu.hidden) openCombobox(input, menu);
                else moveActiveOption(menu, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (menu.hidden) openCombobox(input, menu);
                else moveActiveOption(menu, -1);
            } else if (event.key === 'Enter' && !menu.hidden) {
                const option = menu.querySelector('.geo-combobox__option.is-active:not([hidden])');
                if (option) {
                    event.preventDefault();
                    onSelect(option);
                }
            } else if (event.key === 'Escape') {
                closeCombobox(input, menu);
            }
        });

        menu.addEventListener('mousedown', (event) => event.preventDefault());
        menu.addEventListener('click', (event) => {
            const option = event.target.closest('.geo-combobox__option');
            if (option && !option.hidden) onSelect(option);
        });

        toggle?.addEventListener('click', () => {
            const wasOpen = !menu.hidden;
            input.focus();
            if (wasOpen) closeCombobox(input, menu);
            else openCombobox(input, menu);
        });
    }

    function updateOptionSelection(menu, selectedValue) {
        optionButtons(menu).forEach((option) => {
            option.setAttribute('aria-selected', option.dataset.value === selectedValue ? 'true' : 'false');
        });
    }

    function dispatchFieldInput(field) {
        field?.dispatchEvent(new Event('input', { bubbles: true }));
        field?.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncAddressField({ dispatch = true } = {}) {
        if (!addressInput) return;
        const parts = [
            landmarkInput?.value.trim(),
            quarterInput?.value.trim(),
            communeInput?.value.trim(),
            'Abidjan',
        ].filter(Boolean);
        const nextAddress = parts.join(', ');
        if (addressInput.value === nextAddress) return;
        addressInput.value = nextAddress;
        if (dispatch) dispatchFieldInput(addressInput);
    }

    function resetLandmarkMetadata() {
        if (landmarkIdInput) landmarkIdInput.value = '';
        if (landmarkSourceInput) landmarkSourceInput.value = 'manual';
        if (landmarkLatitudeInput) landmarkLatitudeInput.value = '';
        if (landmarkLongitudeInput) landmarkLongitudeInput.value = '';
    }

    function clearDependentLocationDetails({ dispatch = true } = {}) {
        window.clearTimeout(backgroundGeoTimer);
        geoRequestSequence += 1;

        clearLocalitySelection({ disable: false, dispatch });

        if (landmarkInput) landmarkInput.value = '';
        resetLandmarkMetadata();
        if (addressInput) addressInput.value = '';

        clearStoredCoordinates();
        lastGeoSignature = '';
        setGeoStatus('Commune modifiée. Sélectionnez le quartier et indiquez un nouveau point de repère.', 'neutral');
        setLocationSummary();

        if (dispatch) {
            dispatchFieldInput(landmarkInput);
            dispatchFieldInput(addressInput);
        }
    }

    function clearLocalitySelection({ keepSearch = false, disable = false, dispatch = true } = {}) {
        if (!keepSearch && quarterSearchInput) quarterSearchInput.value = '';
        if (quarterInput) quarterInput.value = '';
        if (quarterIdInput) quarterIdInput.value = '';
        if (quarterSearchInput) {
            quarterSearchInput.disabled = disable;
            quarterSearchInput.setCustomValidity('');
        }
        if (quarterToggle) quarterToggle.disabled = disable;
        updateOptionSelection(quarterOptions, '');
        if (dispatch) dispatchFieldInput(quarterInput);
        syncAddressField({ dispatch });
    }

    function fallbackLocalitiesForCommune(commune) {
        const canonical = Object.keys(localityFallbackCatalog).find(
            (name) => normalizeLocalitySearch(name) === normalizeLocalitySearch(commune)
        );
        const rows = canonical ? localityFallbackCatalog[canonical] : [];
        return Array.isArray(rows) ? rows : [];
    }

    function localityOptionElement(item) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'geo-combobox__option';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.dataset.value = String(item.name || '');
        option.dataset.id = String(item.id || '');
        option.dataset.localityId = String(item.locality_id || '');
        option.dataset.type = String(item.type || 'quartier');
        option.dataset.typeLabel = String(item.type_label || 'Quartier');
        option.dataset.latitude = item.latitude ?? '';
        option.dataset.longitude = item.longitude ?? '';
        const aliases = Array.isArray(item.aliases) ? item.aliases.filter(Boolean) : [];
        option.dataset.search = normalizeLocalitySearch([
            item.name,
            item.type_label,
            ...aliases,
        ].filter(Boolean).join(' '));

        const main = document.createElement('span');
        main.className = 'geo-combobox__option-main';
        main.textContent = String(item.name || '');
        option.appendChild(main);
        return option;
    }

    function renderLocalityOptions(items, selectedName = '') {
        if (!quarterOptions) return;
        quarterOptions.innerHTML = '';
        items.forEach((item) => quarterOptions.appendChild(localityOptionElement(item)));

        const empty = document.createElement('div');
        empty.className = 'geo-combobox__empty';
        empty.dataset.empty = '';
        empty.textContent = 'Aucune localité trouvée.';
        empty.hidden = items.length > 0;
        quarterOptions.appendChild(empty);

        if (selectedName) {
            const selected = optionButtons(quarterOptions).find(
                (option) => normalizeLocalitySearch(option.dataset.value) === normalizeLocalitySearch(selectedName)
            );
            if (selected) selectLocalityOption(selected, { preserveCoordinates: true, close: true });
        }
    }

    async function loadLocalities(selectedName = '', { preserveCoordinates = false } = {}) {
        const commune = communeInput?.value.trim() || '';
        if (!commune) {
            if (quarterOptions) quarterOptions.innerHTML = '<div class="geo-combobox__empty" data-empty>Sélectionnez une commune.</div>';
            clearLocalitySelection({ disable: true, dispatch: false });
            setLocalityStatus('Sélectionnez une commune, puis recherchez la localité de la boutique.');
            return [];
        }

        const sequence = ++localityRequestSequence;
        if (quarterSearchInput) {
            quarterSearchInput.disabled = true;
            quarterSearchInput.placeholder = 'Chargement des localités…';
        }
        if (quarterToggle) quarterToggle.disabled = true;
        setLocalityStatus('Chargement des localités d’Abidjan…', 'loading');

        let items = [];
        try {
            const payload = await fetchJson(quartersUrl, { commune, limit: 500 });
            if (sequence !== localityRequestSequence) return [];
            items = Array.isArray(payload.localities)
                ? payload.localities
                : (Array.isArray(payload.quarters) ? payload.quarters : []);
        } catch (_) {
            if (sequence !== localityRequestSequence) return [];
            items = fallbackLocalitiesForCommune(commune);
        }

        renderLocalityOptions(items, selectedName);
        if (quarterSearchInput) {
            quarterSearchInput.disabled = items.length === 0;
            quarterSearchInput.placeholder = items.length
                ? 'Sélectionner un quartier'
                : 'Aucune localité disponible';
        }
        if (quarterToggle) quarterToggle.disabled = items.length === 0;

        if (!items.length) {
            setLocalityStatus('Aucune localité n’est enregistrée pour cette commune. Réessayez après vérification.', 'error');
            return [];
        }

        const selectedWasFound = Boolean(selectedName && quarterInput?.value);
        if (!selectedName || !selectedWasFound) {
            clearLocalitySelection({ keepSearch: Boolean(selectedName), disable: false, dispatch: false });
            if (selectedName && quarterSearchInput) {
                quarterSearchInput.setCustomValidity('Sélectionnez une localité dans la liste.');
            }
        }

        if (selectedWasFound) {
            const selected = optionButtons(quarterOptions).find((option) => option.dataset.value === quarterInput.value);
            setLocalityStatus(
                `${selected?.dataset.typeLabel || 'Quartier'} sélectionné : ${quarterInput.value}. Ajoutez maintenant un point de repère.`,
                'success'
            );
        } else {
            setLocalityStatus(
                `${items.length} localité${items.length > 1 ? 's' : ''} disponible${items.length > 1 ? 's' : ''}. Recherchez puis sélectionnez le quartier de la boutique.`,
                'success'
            );
        }

        if (!preserveCoordinates && selectedName && quarterInput?.value) {
            clearStoredCoordinates();
        }
        return items;
    }

    async function selectCommuneOption(option, {
        load = true,
        desiredLocality = '',
        preserveCoordinates = false,
    } = {}) {
        if (!option || !communeInput || !communeSearchInput) return;
        const previous = communeInput.value;
        const value = String(option.dataset.value || '').trim();

        communeSearchInput.value = value;
        communeSearchInput.setCustomValidity('');
        communeInput.value = value;
        if (communeIdInput) communeIdInput.value = option.dataset.id || '';
        updateOptionSelection(communeOptions, value);
        closeCombobox(communeSearchInput, communeOptions);
        clearDynamicErrorForField(communeSearchInput);

        if (previous !== value) {
            if (!preserveCoordinates && !isApplyingGeoResult) {
                clearDependentLocationDetails({ dispatch: true });
            } else {
                clearLocalitySelection({ disable: false, dispatch: false });
            }
        }

        if (!isApplyingGeoResult && !preserveCoordinates) dispatchFieldInput(communeInput);
        scheduleDraftSave();
        if (load) await loadLocalities(desiredLocality, { preserveCoordinates });
    }

    function selectLocalityOption(option, { preserveCoordinates = false, close = true } = {}) {
        if (!option || !quarterInput || !quarterSearchInput) return;
        const value = String(option.dataset.value || '').trim();
        quarterSearchInput.value = value;
        quarterSearchInput.setCustomValidity('');
        quarterInput.value = value;
        if (quarterIdInput) quarterIdInput.value = option.dataset.id || '';
        updateOptionSelection(quarterOptions, value);
        if (close) closeCombobox(quarterSearchInput, quarterOptions);
        clearDynamicErrorForField(quarterSearchInput);
        resetLandmarkMetadata();
        syncAddressField({ dispatch: !isApplyingGeoResult && !preserveCoordinates });

        if (!preserveCoordinates && !isApplyingGeoResult) {
            clearStoredCoordinates();
            lastGeoSignature = '';
        }
        if (!isApplyingGeoResult && !preserveCoordinates) dispatchFieldInput(quarterInput);

        const typeLabel = option.dataset.typeLabel || 'Quartier';
        setLocalityStatus(`${typeLabel} sélectionné : ${value}. Ajoutez maintenant un point de repère.`, 'success');
        scheduleDraftSave();
        if (!preserveCoordinates) scheduleBackgroundGeocoding(650);
    }

    async function applyResolvedStructuredFields(payload, { overwriteAddress = false } = {}) {
        const communeValue = String(payload?.commune || '').trim();
        const localityValue = String(payload?.district || payload?.quarter || '').trim();
        if (!communeValue) return;

        const currentCommune = communeInput?.value.trim() || '';
        const currentQuarter = quarterInput?.value.trim() || '';

        // Si l'utilisateur a déjà sélectionné une commune, et qu'on n'est pas en mode
        // d'écrasement automatique (ex: détection GPS), on refuse de changer la commune
        // et le quartier si la commune résolue est différente de celle sélectionnée.
        if (!overwriteAddress && currentCommune !== '') {
            const matchesResolved = normalizeLocalitySearch(currentCommune).includes(normalizeLocalitySearch(communeValue))
                || normalizeLocalitySearch(communeValue).includes(normalizeLocalitySearch(currentCommune));
            if (!matchesResolved) {
                return;
            }
        }

        const communeOption = optionButtons(communeOptions).find((option) => {
            const candidates = [option.dataset.value, option.dataset.search];
            return candidates.some((candidate) => normalizeLocalitySearch(candidate).includes(normalizeLocalitySearch(communeValue)));
        });
        if (!communeOption) return;

        const isDifferentCommune = normalizeLocalitySearch(currentCommune) !== normalizeLocalitySearch(communeOption.dataset.value);
        if (overwriteAddress || !currentCommune || isDifferentCommune) {
            await selectCommuneOption(communeOption, {
                load: true,
                desiredLocality: localityValue,
                preserveCoordinates: true,
            });
        }

        if (localityValue && (overwriteAddress || !currentQuarter)) {
            const localityOption = optionButtons(quarterOptions).find((option) => {
                const search = normalizeLocalitySearch(`${option.dataset.value} ${option.dataset.search || ''}`);
                const target = normalizeLocalitySearch(localityValue);
                return search === target || search.includes(target) || target.includes(normalizeLocalitySearch(option.dataset.value));
            });
            if (localityOption) selectLocalityOption(localityOption, { preserveCoordinates: true });
        }
    }

    bindCombobox({
        input: communeSearchInput,
        menu: communeOptions,
        toggle: communeToggle,
        onSelect: (option) => { void selectCommuneOption(option); },
        onFreeInput: () => {
            if (communeInput) communeInput.value = '';
            if (communeIdInput) communeIdInput.value = '';
            communeSearchInput?.setCustomValidity('Sélectionnez une commune dans la liste.');
            clearDependentLocationDetails({ dispatch: true });
            if (quarterSearchInput) quarterSearchInput.disabled = true;
            if (quarterToggle) quarterToggle.disabled = true;
            setLocalityStatus('Sélectionnez une commune dans les résultats proposés.');
        },
    });

    bindCombobox({
        input: quarterSearchInput,
        menu: quarterOptions,
        toggle: quarterToggle,
        onSelect: (option) => selectLocalityOption(option),
        onFreeInput: () => {
            if (quarterInput) quarterInput.value = '';
            if (quarterIdInput) quarterIdInput.value = '';
            quarterSearchInput?.setCustomValidity('Sélectionnez une localité dans la liste.');
            syncAddressField();
        },
    });

    document.addEventListener('click', (event) => {
        if (!communeCombobox?.contains(event.target)) closeCombobox(communeSearchInput, communeOptions);
        if (!quarterCombobox?.contains(event.target)) closeCombobox(quarterSearchInput, quarterOptions);
    });

    localityRetryButton?.addEventListener('click', () => {
        if (communeInput?.value) {
            void loadLocalities(quarterInput?.value || draftValue('district'));
        } else {
            setLocalityStatus('Sélectionnez une commune, puis recherchez la localité de la boutique.');
        }
    });

    landmarkInput?.addEventListener('input', () => {
        resetLandmarkMetadata();
        syncAddressField();
        scheduleDraftSave();
        scheduleBackgroundGeocoding(650);
    });

    async function initializeStructuredLocation() {
        const desiredCommune = communeInput?.value || draftValue('commune');
        const desiredLocality = quarterInput?.value || draftValue('district');

        if (!desiredCommune) {
            clearLocalitySelection({ disable: true, dispatch: false });
            setLocalityStatus('Sélectionnez une commune, puis recherchez la localité de la boutique.');
            syncAddressField({ dispatch: false });
            return;
        }

        const communeOption = optionButtons(communeOptions).find(
            (option) => normalizeLocalitySearch(option.dataset.value) === normalizeLocalitySearch(desiredCommune)
        );

        if (!communeOption) {
            communeSearchInput.value = '';
            communeSearchInput.setCustomValidity('');
            if (communeInput) communeInput.value = '';
            if (communeIdInput) communeIdInput.value = '';
            clearLocalitySelection({ disable: true, dispatch: false });
            setLocalityStatus('Sélectionnez une commune dans la liste pour retrouver ses quartiers.');
            scheduleDraftSave();
            return;
        }

        await selectCommuneOption(communeOption, {
            load: true,
            desiredLocality,
            preserveCoordinates: true,
        });
        syncAddressField({ dispatch: false });
    }

    function removeGenericLandmarkValue() {
        if (!landmarkInput) return;

        const landmark = normalizeLocalitySearch(landmarkInput.value);
        const genericValues = [
            'abidjan',
            normalizeLocalitySearch(document.getElementById('city')?.value),
            normalizeLocalitySearch(document.getElementById('region')?.value),
        ]
            .filter(Boolean);

        if (!genericValues.includes(landmark)) return;

        landmarkInput.value = '';
        resetLandmarkMetadata();
        syncAddressField({ dispatch: false });
    }

    function buildAddressPayload() {
        return {
            address: document.getElementById('address')?.value.trim() || '',
            region: document.getElementById('region')?.value.trim() || '',
            commune: document.getElementById('commune')?.value.trim() || '',
            district: document.getElementById('district')?.value.trim() || '',
            landmark: document.getElementById('landmark')?.value.trim() || '',
            city: document.getElementById('city')?.value.trim() || '',
            country: "Côte d'Ivoire",
        };
    }

    function addressSignature(payload = buildAddressPayload()) {
        return JSON.stringify([
            payload.address,
            payload.landmark,
            payload.district,
            payload.commune,
            payload.city,
            payload.region,
        ].map((value) => value.toLocaleLowerCase('fr').replace(/\s+/g, ' ').trim()));
    }

    function hasEnoughAddressDetail(payload = buildAddressPayload()) {
        const localityCount = [payload.region, payload.city, payload.commune, payload.district]
            .filter(Boolean)
            .length;
        const specificCount = [payload.address, payload.landmark].filter(Boolean).length;

        return localityCount >= 3 && specificCount >= 1;
    }

    function sourceLabel(source) {
        const normalized = String(source || '').toLowerCase();

        if (normalized === 'browser_gps') return 'Source : GPS';
        if (normalized === 'manual_map') return 'Source : carte confirmée';
        if (normalized === 'landmark_selection') return 'Source : point de repère';
        if (normalized.includes('mapbox')) return 'Source : géocodage Mapbox';
        if (normalized.includes('nominatim')) return 'Source : géocodage OpenStreetMap';
        if (normalized.includes('cache')) return 'Source : géocodage vérifié';

        return 'Source : adresse';
    }

    function precisionLabel(precision) {
        return {
            high: 'Élevée',
            medium: 'Moyenne',
            low: 'À vérifier',
            confirmed: 'Confirmée',
            pending: 'En attente',
        }[precision] || 'À vérifier';
    }

    function setLocationSummary({
        state = 'pending',
        title = 'Localisation en attente',
        text = 'Complétez l’adresse. OVANIE recherchera automatiquement les coordonnées de la boutique.',
        address = 'Aucune position confirmée',
        precision = 'pending',
        source = 'address',
        canVerify = false,
        canRetry = false,
    } = {}) {
        if (!locationSummaryCard) {
            return;
        }

        locationSummaryCard.classList.remove('is-pending', 'is-loading', 'is-success', 'is-warning', 'is-error');
        locationSummaryCard.classList.add(`is-${state}`);

        locationSummaryTitle.textContent = title;
        locationSummaryText.textContent = text;
        locationResolvedAddress.textContent = address;
        locationSourceLabel.textContent = sourceLabel(source);

        locationPrecisionBadge.className = `location-precision is-${precision}`;
        locationPrecisionBadge.textContent = precisionLabel(precision);

        verifyLocationButton.hidden = !canVerify;
        retryLocationButton.hidden = !canRetry;
    }

    function setGeoStatus(message, tone = 'neutral') {
        if (!geoStatus) {
            return;
        }

        geoStatus.classList.remove('is-success', 'is-error', 'is-loading', 'is-warning');
        if (tone !== 'neutral') {
            geoStatus.classList.add(`is-${tone}`);
        }

        const textNode = geoStatus.querySelector('span:last-child');
        if (textNode) {
            textNode.textContent = message;
        }
    }

    function updateCoordinateLabels(lng, lat) {
        if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
            return;
        }

        if (latitudeLabel) {
            latitudeLabel.textContent = `Lat. ${lat.toFixed(6)}`;
        }
        if (longitudeLabel) {
            longitudeLabel.textContent = `Lng. ${lng.toFixed(6)}`;
        }
    }

    function storeCoordinates(lng, lat, source, accuracy = null, precision = null, precisionScore = null) {
        if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
            return;
        }

        latitudeInput.value = lat.toFixed(7);
        longitudeInput.value = lng.toFixed(7);
        geoSourceInput.value = source || 'address_geocoding';
        if (geoPrecisionInput) {
            geoPrecisionInput.value = precision || '';
        }
        if (geoPrecisionScoreInput) {
            geoPrecisionScoreInput.value = Number.isFinite(Number(precisionScore)) ? String(Math.round(Number(precisionScore))) : '';
        }

        if (['browser_gps', 'manual_map', 'logistics_verified'].includes(String(source || '').toLowerCase())) {
            [geoSourceInput, latitudeInput, longitudeInput].forEach((field) => {
                if (field) clearDynamicErrorForField(field);
            });
        }

        if (accuracy !== null && Number.isFinite(Number(accuracy))) {
            accuracyInput.value = Number(accuracy).toFixed(1);
        } else if (source !== 'browser_gps') {
            accuracyInput.value = '';
        }

        updateCoordinateLabels(lng, lat);
    }

    function clearStoredCoordinates() {
        latitudeInput.value = '';
        longitudeInput.value = '';
        accuracyInput.value = '';
        geoSourceInput.value = '';
        if (geoPrecisionInput) geoPrecisionInput.value = '';
        if (geoPrecisionScoreInput) geoPrecisionScoreInput.value = '';
        if (latitudeLabel) {
            latitudeLabel.textContent = 'Lat. —';
        }
        if (longitudeLabel) {
            longitudeLabel.textContent = 'Lng. —';
        }
    }

    function initializeMap() {
        if (mapReady || !window.maplibregl || !document.getElementById('shopMap')) {
            return;
        }

        const lat = parseFloat(latitudeInput.value || '5.359952');
        const lng = parseFloat(longitudeInput.value || '-4.008256');

        map = new window.maplibregl.Map({
            container: 'shopMap',
            center: [lng, lat],
            zoom: latitudeInput.value && longitudeInput.value ? 16 : 12,
            minZoom: 10.5,
            maxZoom: 18.5,
            maxBounds: [[-9.2, 4.0], [-2.0, 11.3]],
            renderWorldCopies: false,
            dragRotate: false,
            pitchWithRotate: false,
            attributionControl: true,
            cooperativeGestures: true,
            style: {
                version: 8,
                sources: {
                    osm: {
                        type: 'raster',
                        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                        tileSize: 256,
                        attribution: '© OpenStreetMap contributors',
                    },
                },
                layers: [{
                    id: 'osm-tiles',
                    type: 'raster',
                    source: 'osm',
                    minzoom: 0,
                    maxzoom: 19,
                }],
            },
        });

        map.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        map.touchZoomRotate?.disableRotation();
        mapReady = true;
        map.on('load', () => map?.resize());

        if (latitudeInput.value && longitudeInput.value) {
            updateMapPosition(Number(longitudeInput.value), Number(latitudeInput.value), false);
        }
    }

    function ensureMarker(lng, lat) {
        if (!map) {
            return;
        }

        if (!marker) {
            const markerElement = document.createElement('button');
            markerElement.type = 'button';
            markerElement.className = 'open-shop-map-marker';
            markerElement.setAttribute('aria-label', 'Emplacement de la boutique');
            markerElement.innerHTML = `
                <span class="open-shop-map-marker__pin">
                    <svg viewBox="0 0 48 48" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20h26M14 20v17h20V20M16 11h16l4 9H12l4-9Z"/><path d="M20 37v-9h8v9M16 24h4M28 24h4"/></g></svg>
                </span>`;
            marker = new window.maplibregl.Marker({
                element: markerElement,
                anchor: 'bottom',
                draggable: true,
            })
                .setLngLat([lng, lat])
                .addTo(map);

            marker.on('dragend', () => {
                const point = marker.getLngLat();
                updateCoordinateLabels(point.lng, point.lat);
                mapAddressLabel.textContent = 'Position ajustée manuellement — confirmez pour enregistrer';
            });
        } else {
            marker.setLngLat([lng, lat]);
        }
    }

    function updateMapPosition(lng, lat, fly = true) {
        if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
            return;
        }

        initializeMap();
        if (!map) {
            return;
        }

        ensureMarker(lng, lat);
        updateCoordinateLabels(lng, lat);

        if (fly) {
            map.flyTo({ center: [lng, lat], zoom: 16, essential: true });
        } else {
            map.jumpTo({ center: [lng, lat], zoom: 16 });
        }
    }

    function gpsPrecision(accuracy) {
        const meters = Number(accuracy || 0);

        if (meters > 0 && meters <= 30) return 'high';
        if (meters > 0 && meters <= 100) return 'medium';

        return meters > 0 ? 'low' : 'medium';
    }

    function applyLocationResult(payload, {
        overwriteAddress = false,
        precisionOverride = null,
        sourceOverride = null,
        accuracy = null,
    } = {}) {
        const lat = Number(payload.latitude);
        const lng = Number(payload.longitude);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            throw new Error('Le service de localisation n’a pas retourné de coordonnées valides.');
        }

        isApplyingGeoResult = true;
        try {
            geocoderWritableFieldIds.forEach((id) => {
                const field = document.getElementById(id);
                const value = payload[id];

                if (!field || !value) {
                    return;
                }

                if (overwriteAddress || !field.value.trim()) {
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        } finally {
            isApplyingGeoResult = false;
        }

        void applyResolvedStructuredFields(payload, { overwriteAddress });

        const source = sourceOverride || payload.source || 'address_geocoding';
        const precision = precisionOverride || payload.precision || 'medium';
        const displayAddress = payload.display_name
            || payload.address
            || document.getElementById('address')?.value
            || 'Position enregistrée';

        storeCoordinates(lng, lat, source, accuracy, precision, payload.precision_score ?? null);
        lastGeoSignature = addressSignature();
        if (mapAddressLabel) {
            mapAddressLabel.textContent = displayAddress;
        }

        if (mapReady && locationMapModal?.open) {
            updateMapPosition(lng, lat);
        }

        const summaryByPrecision = {
            high: {
                state: 'success',
                title: 'Position localisée',
                text: 'La position a été trouvée avec une précision élevée. Vous pouvez continuer.',
                status: 'Position de la boutique trouvée automatiquement.',
                tone: 'success',
            },
            medium: {
                state: 'warning',
                title: 'Position trouvée',
                text: 'La position est exploitable. Une vérification sur la carte reste recommandée.',
                status: 'Position trouvée. Vérifiez-la sur la carte si nécessaire.',
                tone: 'warning',
            },
            low: {
                state: 'warning',
                title: 'Position à vérifier',
                text: 'La zone a été trouvée, mais la position exacte de la boutique doit être vérifiée.',
                status: 'Position approximative. Vérifiez le marqueur avant de continuer.',
                tone: 'warning',
            },
            confirmed: {
                state: 'success',
                title: 'Position confirmée',
                text: 'Le point d’enlèvement de la boutique a été confirmé manuellement.',
                status: 'Position de la boutique confirmée sur la carte.',
                tone: 'success',
            },
        };

        const summary = summaryByPrecision[precision] || summaryByPrecision.medium;

        setLocationSummary({
            ...summary,
            address: displayAddress,
            precision,
            source,
            canVerify: true,
            canRetry: precision === 'low',
        });
        setGeoStatus(summary.status, summary.tone);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(options.method && options.method !== 'GET' ? {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (_) {
            payload = {};
        }

        if (!response.ok) {
            const message = payload.message
                || Object.values(payload.errors || {}).flat()[0]
                || 'Une erreur est survenue pendant la localisation.';
            throw new Error(message);
        }

        return payload;
    }

    async function reverseGeocode(lat, lng) {
        const url = new URL(reverseUrl, window.location.origin);
        url.searchParams.set('lat', String(lat));
        url.searchParams.set('lng', String(lng));

        return fetchJson(url.toString());
    }

    async function resolveAddress({ manual = false } = {}) {
        const payload = buildAddressPayload();

        if (!hasEnoughAddressDetail(payload)) {
            if (manual) {
                setGeoStatus('Complétez la ville, la commune, le quartier et une adresse ou un repère.', 'error');
                setLocationSummary({
                    state: 'error',
                    title: 'Adresse incomplète',
                    text: 'Ajoutez davantage de détails pour permettre une localisation fiable.',
                    address: 'Position non recherchée',
                    precision: 'pending',
                    source: 'address',
                    canRetry: true,
                });
            }
            return false;
        }

        const signature = addressSignature(payload);
        if (!manual && signature === lastGeoSignature && latitudeInput.value && longitudeInput.value) {
            return true;
        }

        const requestSequence = ++geoRequestSequence;

        if (retryLocationButton) {
            retryLocationButton.disabled = true;
        }
        setGeoStatus('Localisation automatique de la boutique en cours...', 'loading');
        setLocationSummary({
            state: 'loading',
            title: 'Localisation en cours',
            text: 'OVANIE recherche les coordonnées correspondant à l’adresse saisie.',
            address: payload.address || payload.district || payload.commune,
            precision: 'pending',
            source: 'address',
        });

        try {
            const result = await fetchJson(resolveUrl, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (requestSequence !== geoRequestSequence || signature !== addressSignature()) {
                return false;
            }

            applyLocationResult(result, {
                overwriteAddress: false,
                sourceOverride: result.source || 'address_geocoding',
            });

            return true;
        } catch (error) {
            if (requestSequence !== geoRequestSequence) {
                return false;
            }
            clearStoredCoordinates();
            setGeoStatus(error.message || 'La position n’a pas pu être confirmée automatiquement.', 'error');
            setLocationSummary({
                state: 'error',
                title: 'Position non confirmée',
                text: 'Précisez l’adresse ou utilisez votre position actuelle. Vous pouvez aussi relancer la recherche.',
                address: payload.address || payload.district || payload.commune || 'Adresse à préciser',
                precision: 'low',
                source: 'address',
                canRetry: true,
            });

            return false;
        } finally {
            if (retryLocationButton) {
                retryLocationButton.disabled = false;
            }
        }
    }

    function scheduleBackgroundGeocoding(delay = 1000) {
        window.clearTimeout(backgroundGeoTimer);

        if (!hasEnoughAddressDetail()) {
            return;
        }

        backgroundGeoTimer = window.setTimeout(() => {
            resolveAddress({ manual: false });
        }, delay);
    }

    addressFieldIds.forEach((id) => {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }

        field.addEventListener('input', () => {
            if (isApplyingGeoResult) {
                return;
            }

            clearStoredCoordinates();
            lastGeoSignature = '';
            setGeoStatus('Adresse modifiée. La position sera recalculée automatiquement.', 'loading');
            setLocationSummary({
                state: 'pending',
                title: 'Mise à jour de la position',
                text: 'OVANIE recalculera les coordonnées quelques instants après la fin de la saisie.',
                address: field.value.trim() || 'Adresse en cours de saisie',
                precision: 'pending',
                source: 'address',
            });

            scheduleBackgroundGeocoding();
        });

        field.addEventListener('blur', () => scheduleBackgroundGeocoding(350));
    });

    useLocationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setGeoStatus('La géolocalisation n’est pas prise en charge par ce navigateur.', 'error');
            return;
        }

        useLocationButton.disabled = true;
        useLocationButton.classList.add('is-loading');
        setGeoStatus('Recherche de votre position GPS...', 'loading');
        setLocationSummary({
            state: 'loading',
            title: 'Recherche GPS',
            text: 'Autorisez le navigateur à utiliser votre position pour localiser la boutique.',
            address: 'Position GPS en cours',
            precision: 'pending',
            source: 'browser_gps',
        });

        navigator.geolocation.getCurrentPosition(async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = Number(position.coords.accuracy || 0);
            const precision = gpsPrecision(accuracy);

            storeCoordinates(lng, lat, 'browser_gps', accuracy, precision, null);

            try {
                setGeoStatus('Position obtenue. Identification automatique de l’adresse...', 'loading');
                const payload = await reverseGeocode(lat, lng);

                applyLocationResult({
                    ...payload,
                    latitude: lat,
                    longitude: lng,
                }, {
                    overwriteAddress: true,
                    precisionOverride: precision,
                    sourceOverride: 'browser_gps',
                    accuracy,
                });
            } catch (error) {
                const fallbackPayload = {
                    latitude: lat,
                    longitude: lng,
                    display_name: 'Position GPS obtenue — complétez l’adresse manuellement',
                };

                applyLocationResult(fallbackPayload, {
                    overwriteAddress: false,
                    precisionOverride: precision,
                    sourceOverride: 'browser_gps',
                    accuracy,
                });

                setGeoStatus('Position GPS obtenue, mais l’adresse doit être complétée manuellement.', 'warning');
            } finally {
                useLocationButton.disabled = false;
                useLocationButton.classList.remove('is-loading');
            }
        }, (error) => {
            const messages = {
                1: 'Autorisation GPS refusée. Renseignez l’adresse : OVANIE la localisera automatiquement.',
                2: 'Position GPS indisponible. Renseignez l’adresse pour utiliser la localisation automatique.',
                3: 'La recherche GPS a expiré. Réessayez ou renseignez l’adresse manuellement.',
            };

            setGeoStatus(messages[error.code] || 'Impossible d’obtenir la position GPS.', 'error');
            setLocationSummary({
                state: 'error',
                title: 'GPS indisponible',
                text: 'Vous pouvez continuer en saisissant l’adresse. La localisation automatique en arrière-plan reste disponible.',
                address: 'Position GPS non obtenue',
                precision: 'pending',
                source: 'browser_gps',
                canRetry: hasEnoughAddressDetail(),
            });
            useLocationButton.disabled = false;
            useLocationButton.classList.remove('is-loading');
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 60000,
        });
    });

    retryLocationButton?.addEventListener('click', () => resolveAddress({ manual: true }));

    async function openLocationMap() {
        if (!window.maplibregl) {
            setGeoStatus('La carte n’a pas pu être chargée. Utilisez le GPS ou réessayez après avoir vérifié votre connexion.', 'error');
            setLocationSummary({
                state: 'error',
                title: 'Carte momentanément indisponible',
                text: 'La bibliothèque cartographique n’a pas été chargée. La position GPS reste disponible.',
                address: locationResolvedAddress?.textContent || 'Position non confirmée',
                precision: geoPrecisionInput?.value || 'pending',
                source: geoSourceInput?.value || 'address',
                canRetry: true,
            });
            return;
        }

        if (!latitudeInput.value || !longitudeInput.value) {
            const resolved = await resolveAddress({ manual: true });
            if (!resolved) {
                return;
            }
        }

        const lat = Number(latitudeInput.value);
        const lng = Number(longitudeInput.value);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        modalStartPosition = { lat, lng };

        if (typeof locationMapModal?.showModal === 'function') {
            locationMapModal.showModal();
        } else {
            locationMapModal?.setAttribute('open', 'open');
        }

        window.setTimeout(() => {
            initializeMap();
            map?.resize();
            updateMapPosition(lng, lat, false);
        }, 80);
    }

    function closeLocationMap() {
        if (typeof locationMapModal?.close === 'function') {
            locationMapModal.close();
        } else {
            locationMapModal?.removeAttribute('open');
        }

        if (modalStartPosition) {
            updateCoordinateLabels(modalStartPosition.lng, modalStartPosition.lat);
        }
    }

    verifyLocationButton?.addEventListener('click', openLocationMap);
    closeLocationMapButton?.addEventListener('click', closeLocationMap);
    cancelLocationMapButton?.addEventListener('click', closeLocationMap);

    locationMapModal?.addEventListener('click', (event) => {
        if (event.target === locationMapModal) {
            closeLocationMap();
        }
    });

    confirmLocationButton?.addEventListener('click', () => {
        if (!marker) {
            closeLocationMap();
            return;
        }

        const point = marker.getLngLat();
        storeCoordinates(point.lng, point.lat, 'manual_map', null, 'confirmed', 100);
        modalStartPosition = { lat: point.lat, lng: point.lng };

        applyLocationResult({
            latitude: point.lat,
            longitude: point.lng,
            display_name: document.getElementById('address')?.value || 'Position confirmée sur la carte',
            precision: 'confirmed',
            source: 'manual_map',
        }, {
            overwriteAddress: false,
            precisionOverride: 'confirmed',
            sourceOverride: 'manual_map',
        });

        locationMapModal?.close();
    });

    const termsModal = document.getElementById('termsModal');
    document.querySelectorAll('[data-open-terms]').forEach((button) => {
        button.addEventListener('click', (event) => {
            // Consulter le document est facultatif et ne doit ni cocher ni
            // décocher implicitement la case d'acceptation qui l'entoure.
            event.stopPropagation();
            termsModal?.showModal();
        });
    });

    document.querySelectorAll('[data-close-terms]').forEach((button) => {
        button.addEventListener('click', () => termsModal?.close());
    });

    termsModal?.addEventListener('click', (event) => {
        const rect = termsModal.getBoundingClientRect();
        const outside = event.clientX < rect.left
            || event.clientX > rect.right
            || event.clientY < rect.top
            || event.clientY > rect.bottom;

        if (outside) {
            termsModal.close();
        }
    });

    function hydrateExistingGeo() {
        if (!latitudeInput.value || !longitudeInput.value) {
            return false;
        }

        const lat = Number(latitudeInput.value);
        const lng = Number(longitudeInput.value);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            clearStoredCoordinates();
            return false;
        }

        const source = geoSourceInput.value || 'address_geocoding';
        const precision = source === 'manual_map'
            ? 'confirmed'
            : (source === 'browser_gps'
                ? gpsPrecision(accuracyInput.value)
                : (source === 'landmark_selection' ? 'low' : (geoPrecisionInput?.value || 'medium')));
        const restoredAddress = document.getElementById('address')?.value || 'Position enregistrée';

        updateCoordinateLabels(lng, lat);
        if (mapAddressLabel) {
            mapAddressLabel.textContent = restoredAddress;
        }
        lastGeoSignature = addressSignature();

        const restoredFromLandmark = source === 'landmark_selection';
        setGeoStatus(
            restoredFromLandmark
                ? 'Le repère a été restauré. Confirmez encore l’entrée exacte.'
                : 'Une position enregistrée a été restaurée.',
            restoredFromLandmark ? 'warning' : 'success'
        );
        setLocationSummary({
            state: restoredFromLandmark ? 'warning' : 'success',
            title: source === 'manual_map' ? 'Position confirmée' : (restoredFromLandmark ? 'Repère restauré' : 'Position enregistrée'),
            text: restoredFromLandmark
                ? 'Le repère localise la zone. Utilisez le GPS ou la carte pour confirmer l’entrée exacte.'
                : 'La localisation est déjà disponible. Vous pouvez la vérifier sur la carte si nécessaire.',
            address: restoredAddress,
            precision,
            source,
            canVerify: true,
            canRetry: precision === 'low',
        });

        return true;
    }

    locationMapModal?.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeLocationMap();
    });

    restoreDraft();
    removeGenericLandmarkValue();
    initMobileSelects();
    syncSellerTypeFields();
    syncLogisticsNotice();
    syncIdentityUploadFields();
    updateDescriptionCounter();

    const hasRestoredGeo = hydrateExistingGeo();
    initializeStructuredLocation().finally(() => {
        if (!hasRestoredGeo && !latitudeInput?.value && hasEnoughAddressDetail()) {
            scheduleBackgroundGeocoding(500);
        }
    });

    renderStep();

    if (hasServerErrors) {
        window.setTimeout(focusInitialServerError, 120);
    }
})();
