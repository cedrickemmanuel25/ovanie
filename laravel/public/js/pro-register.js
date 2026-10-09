/* OVANIE PRO — écrans "Choisir son parcours" et "Compte". */
(function () {
    'use strict';

    function q(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function renderFlag(slot, isCoteDIvoire, url) {
        if (!slot) return;
        slot.textContent = '';

        if (isCoteDIvoire && url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            slot.appendChild(img);
            return;
        }

        var fallback = document.createElement('span');
        fallback.className = 'opr-globe-fallback';
        fallback.textContent = '◎';
        slot.appendChild(fallback);
    }

    /* Côte d'Ivoire et +225 sont les valeurs par défaut. Le vrai drapeau SVG
       est utilisé afin d'éviter l'affichage "CI" de certains navigateurs Windows. */
    var countrySelect = q('[data-country-select]');
    if (countrySelect) {
        var countryFlag = q('[data-country-flag]');
        var countryFlagUrl = countrySelect.getAttribute('data-ci-flag') || '';

        var syncCountryFlag = function () {
            renderFlag(countryFlag, countrySelect.value === 'Côte d’Ivoire', countryFlagUrl);
        };

        countrySelect.addEventListener('change', syncCountryFlag);
        syncCountryFlag();
    }

    var phoneCode = q('[data-phone-code]');
    if (phoneCode) {
        var phoneFlag = q('[data-phone-flag]');
        var phoneFlagUrl = phoneCode.getAttribute('data-ci-flag') || '';

        var syncPhoneFlag = function () {
            renderFlag(phoneFlag, phoneCode.value === '+225', phoneFlagUrl);
        };

        phoneCode.addEventListener('change', syncPhoneFlag);
        syncPhoneFlag();
    }

    qa('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) return;

            var shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            button.setAttribute('aria-label', shouldShow ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    var accountForm = q('[data-account-form]');
    if (!accountForm) return;

    var password = q('[data-password]', accountForm);
    var confirmation = q('[data-password-confirmation]', accountForm);
    var strength = q('[data-strength]', accountForm);
    var strengthLabel = q('[data-strength-label]', accountForm);
    var rules = q('[data-password-rules]', accountForm);
    var match = q('[data-password-match]', accountForm);
    var draftStatus = q('[data-draft-status]', accountForm);
    var draftKey = 'ovanie_pro_account_draft_v2';

    var tests = {
        length: function (value) { return value.length >= 8; },
        letter: function (value) { return /[A-Za-zÀ-ÿ]/.test(value); },
        number: function (value) { return /\d/.test(value); },
        uppercase: function (value) { return /[A-ZÀ-Ý]/.test(value); },
        symbol: function (value) { return /[^A-Za-zÀ-ÿ0-9\s]/.test(value); }
    };

    function updatePasswordUi() {
        if (!password) return;

        var value = password.value || '';
        var hasValue = value.length > 0;

        /* Rien d'inutile n'est affiché tant que le visiteur n'a pas commencé
           à saisir son mot de passe. */
        if (rules) rules.hidden = !hasValue;
        if (strength) strength.hidden = !hasValue;

        Object.keys(tests).forEach(function (name) {
            var item = q('[data-rule="' + name + '"]', accountForm);
            if (!item) return;
            item.classList.toggle('is-ok', hasValue && tests[name](value));
        });

        password.setCustomValidity(
            hasValue && !(tests.length(value) && tests.letter(value) && tests.number(value))
                ? 'Le mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.'
                : ''
        );

        if (!hasValue || !strength) {
            if (strength) strength.removeAttribute('data-level');
            if (strengthLabel) strengthLabel.textContent = '';
            return;
        }

        var score = 0;
        if (tests.length(value)) score++;
        if (tests.letter(value) && tests.number(value)) score++;
        if (tests.uppercase(value)) score++;
        if (tests.symbol(value) || value.length >= 12) score++;
        score = Math.max(1, Math.min(4, score));

        strength.setAttribute('data-level', String(score));
        if (strengthLabel) {
            strengthLabel.textContent = ['Faible', 'Moyen', 'Bon', 'Fort'][score - 1];
        }
    }

    function updateMatchUi() {
        if (!confirmation || !password || !match) return;

        var value = confirmation.value || '';
        if (!value) {
            match.hidden = true;
            match.textContent = '';
            match.className = 'opr-match';
            confirmation.setCustomValidity('');
            return;
        }

        var ok = password.value === value;
        confirmation.setCustomValidity(ok ? '' : 'Les mots de passe ne correspondent pas.');
        match.hidden = false;
        match.textContent = ok
            ? 'Les mots de passe correspondent.'
            : 'Les mots de passe ne correspondent pas.';
        match.className = 'opr-match ' + (ok ? 'is-ok' : 'is-bad');
    }

    if (password) {
        password.addEventListener('input', function () {
            updatePasswordUi();
            updateMatchUi();
        });
        updatePasswordUi();
    }

    if (confirmation) {
        confirmation.addEventListener('input', updateMatchUi);
        updateMatchUi();
    }

    function removeClientError(field) {
        var wrapper = field.closest('.opr-field');
        if (!wrapper) return;

        var error = q('.opr-error[data-client]', wrapper);
        if (error) error.remove();
        wrapper.classList.remove('has-error');
    }

    function addClientError(field, message) {
        var wrapper = field.closest('.opr-field');
        if (!wrapper) return;

        removeClientError(field);
        wrapper.classList.add('has-error');

        var error = document.createElement('span');
        error.className = 'opr-error';
        error.setAttribute('data-client', '');
        error.textContent = message;
        wrapper.appendChild(error);
    }

    function validationMessage(field) {
        if (field.name === 'password') {
            return 'Utilisez au moins 8 caractères avec une lettre et un chiffre.';
        }

        if (field.name === 'password_confirmation') {
            return 'Confirmez exactement le même mot de passe.';
        }

        if (field.type === 'email') {
            return 'Saisissez une adresse e-mail valide.';
        }

        if (field.type === 'checkbox') {
            return 'Vous devez accepter les conditions d’utilisation.';
        }

        if (field.tagName === 'SELECT') {
            return 'Veuillez faire un choix.';
        }

        return field.validity.valueMissing
            ? 'Ce champ est obligatoire.'
            : 'Vérifiez cette information.';
    }

    qa('input, select', accountForm).forEach(function (field) {
        if (field.type === 'hidden') return;

        field.addEventListener('input', function () {
            if (field.checkValidity()) removeClientError(field);
        });

        field.addEventListener('change', function () {
            if (field.checkValidity()) removeClientError(field);
        });
    });

    accountForm.addEventListener('submit', function (event) {
        updatePasswordUi();
        updateMatchUi();

        var firstInvalid = null;

        qa('input, select', accountForm).forEach(function (field) {
            if (field.type === 'hidden' || field.disabled) return;

            if (!field.checkValidity()) {
                addClientError(field, validationMessage(field));
                if (!firstInvalid) firstInvalid = field;
            }
        });

        if (firstInvalid) {
            event.preventDefault();

            if (firstInvalid.name === 'password' && rules) {
                rules.hidden = false;
            }

            firstInvalid.focus({ preventScroll: true });

            var wrapper = firstInvalid.closest('.opr-field');
            if (wrapper && typeof wrapper.scrollIntoView === 'function') {
                wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        try {
            localStorage.removeItem(draftKey);
        } catch (e) {}
    });

    /* Brouillon local : les mots de passe et le consentement ne sont jamais sauvegardés. */
    var draftFields = [
        'last_name',
        'first_name',
        'residence_country',
        'phone_country',
        'phone',
        'email',
        'preferred_language'
    ];

    function saveDraft() {
        var draft = {};

        draftFields.forEach(function (name) {
            var field = accountForm.elements[name];
            if (field) draft[name] = field.value;
        });

        try {
            localStorage.setItem(draftKey, JSON.stringify(draft));
            if (draftStatus) {
                draftStatus.textContent = 'Brouillon enregistré sur cet appareil. Les mots de passe ne sont jamais sauvegardés.';
            }
            return true;
        } catch (e) {
            if (draftStatus) {
                draftStatus.textContent = 'Le brouillon local n’a pas pu être enregistré sur cet appareil.';
            }
            return false;
        }
    }

    function restoreDraft() {
        var raw = null;

        try {
            raw = localStorage.getItem(draftKey);
        } catch (e) {
            return;
        }

        if (!raw) return;

        var draft;
        try {
            draft = JSON.parse(raw);
        } catch (e) {
            return;
        }

        draftFields.forEach(function (name) {
            var field = accountForm.elements[name];
            if (!field || field.value) return;
            if (draft[name]) field.value = draft[name];
        });

        if (countrySelect) countrySelect.dispatchEvent(new Event('change'));
        if (phoneCode) phoneCode.dispatchEvent(new Event('change'));

        if (draftStatus) {
            draftStatus.textContent = 'Brouillon local restauré. Les mots de passe restent à saisir.';
        }
    }

    var saveButton = q('[data-save-draft]', accountForm);
    if (saveButton) {
        saveButton.addEventListener('click', function () {
            if (!saveDraft()) return;

            var backLink = q('.opr-action-btn--back', accountForm);
            if (backLink) {
                window.setTimeout(function () {
                    window.location.href = backLink.href;
                }, 140);
            }
        });
    }

    restoreDraft();
})();
