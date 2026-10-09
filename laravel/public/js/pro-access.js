/* OVANIE PRO — comportements des écrans d'accès (aucune dépendance). */
(function () {
    'use strict';

    var CHECK = '<svg class="pa-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    var DOT = '<svg class="pa-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/></svg>';

    // Afficher / masquer un mot de passe (bouton œil).
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // Jauge de robustesse du mot de passe.
    document.querySelectorAll('[data-meter-for]').forEach(function (meter) {
        var input = document.getElementById(meter.getAttribute('data-meter-for'));
        if (!input) return;
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 8) score++;
            if (/[a-zA-Z]/.test(v) && /\d/.test(v)) score++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) score++;
            meter.setAttribute('data-level', v ? String(Math.max(1, score)) : '0');
        });
    });

    // Nom du fichier choisi pour chaque justificatif.
    document.querySelectorAll('[data-doc]').forEach(function (doc) {
        var input = doc.querySelector('input[type=file]');
        var meta = doc.querySelector('[data-doc-meta]');
        if (!input || !meta) return;
        input.addEventListener('change', function () {
            if (!input.files.length) return;
            var names = Array.prototype.map.call(input.files, function (f) { return f.name; });
            meta.textContent = names.length > 1 ? names.length + ' fichiers : ' + names.join(', ') : names[0];
            meta.classList.add('is-ok');
            doc.classList.remove('has-error');
            var err = doc.querySelector('.pa-error[data-client]');
            if (err) err.remove();
        });
    });

    // Copier un lien d'invitation.
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-copy'));
            if (!input) return;
            input.select();
            (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject())
                .catch(function () { document.execCommand('copy'); })
                .finally(function () { button.textContent = 'Copié'; setTimeout(function () { button.textContent = 'Copier'; }, 1800); });
        });
    });

    // Confirmation avant une action sensible.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
    });

    // ACC-03 : profils compatibles avec le statut, pays d'enregistrement et justificatifs attendus.
    var qualification = document.querySelector('[data-qualification]');
    if (qualification) {
        var compatibility = JSON.parse(qualification.getAttribute('data-compatibility') || '{}');
        var documents = JSON.parse(qualification.getAttribute('data-documents') || '{}');
        var summary = qualification.querySelector('[data-doc-summary]');
        var registration = qualification.querySelector('[data-registration]');

        var refresh = function () {
            var entity = (qualification.querySelector('input[name=entity_type]:checked') || {}).value;
            qualification.querySelectorAll('input[name=professional_profile]').forEach(function (radio) {
                var allowed = !entity || (compatibility[radio.value] || []).indexOf(entity) !== -1;
                radio.disabled = !allowed;
                radio.closest('.pa-option').classList.toggle('is-disabled', !allowed);
                if (!allowed && radio.checked) radio.checked = false;
            });
            if (registration) registration.hidden = entity === 'independent';

            var profile = (qualification.querySelector('input[name=professional_profile]:checked') || {}).value;
            if (!summary) return;
            var list = profile && entity && documents[profile] ? documents[profile][entity] : null;
            summary.innerHTML = '';
            if (!list) {
                summary.innerHTML = '<p class="pa-help">Choisissez votre statut et votre profil pour afficher la liste.</p>';
                return;
            }
            var ul = document.createElement('ul');
            ul.className = 'pa-list';
            list.forEach(function (doc) {
                var li = document.createElement('li');
                if (!doc.required) li.className = 'is-optional';
                li.innerHTML = doc.required ? CHECK : DOT;
                var text = document.createElement('span');
                text.textContent = doc.label + (doc.required ? '' : ' (facultatif)');
                li.appendChild(text);
                ul.appendChild(li);
            });
            summary.appendChild(ul);
        };
        qualification.addEventListener('change', refresh);
        refresh();
    }

    // ACC-04 à ACC-11 : formulaire présenté bloc par bloc, avec messages d'erreur sous les champs.
    var wizard = document.querySelector('[data-wizard]');
    if (wizard) {
        var form = wizard.closest('form');
        var steps = Array.prototype.slice.call(wizard.querySelectorAll('.pa-step'));
        var navItems = Array.prototype.slice.call(document.querySelectorAll('[data-step-nav] li'));
        var prev = wizard.querySelector('[data-prev]');
        var next = wizard.querySelector('[data-next]');
        var submit = wizard.querySelector('[data-submit]');
        var current = 0;

        var clearClientError = function (container) {
            container.classList.remove('has-error');
            var err = container.querySelector('.pa-error[data-client]');
            if (err) err.remove();
        };

        var messageFor = function (field) {
            if (field.validity.valueMissing) {
                if (field.type === 'file') return 'Ce justificatif est obligatoire.';
                if (field.type === 'radio' || field.tagName === 'SELECT') return 'Veuillez faire un choix.';
                return 'Ce champ est obligatoire.';
            }
            if (field.validity.typeMismatch) return field.type === 'email' ? 'Saisissez une adresse e-mail valide.' : 'Saisissez une adresse web valide (https://…).';
            if (field.validity.rangeUnderflow || field.validity.rangeOverflow) return 'Valeur hors limites.';
            return field.validationMessage || 'Valeur invalide.';
        };

        // Valide un bloc : chaque champ invalide reçoit un message en rouge ; renvoie le premier champ fautif.
        var validate = function (step) {
            var firstInvalid = null;
            var seen = {};
            step.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field.disabled || field.type === 'hidden') return;
                var container = field.closest('.pa-field, .pa-doc');
                if (!container) return;
                if (field.type === 'radio') {
                    if (seen[field.name]) return;
                    seen[field.name] = true;
                }
                clearClientError(container);
                if (field.checkValidity()) return;
                container.classList.add('has-error');
                var p = document.createElement('p');
                p.className = 'pa-error';
                p.setAttribute('data-client', '');
                p.textContent = messageFor(field);
                var anchor = container.classList.contains('pa-doc') ? container.querySelector('.pa-doc__body') : container;
                anchor.appendChild(p);
                if (!firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                var target = firstInvalid.closest('.pa-field, .pa-doc');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (firstInvalid.type !== 'file' && firstInvalid.type !== 'radio') firstInvalid.focus({ preventScroll: true });
            }
            return !firstInvalid;
        };

        var show = function (index, scroll) {
            current = Math.max(0, Math.min(steps.length - 1, index));
            steps.forEach(function (step, i) { step.classList.toggle('is-current', i === current); });
            navItems.forEach(function (item, i) {
                item.classList.toggle('is-current', i === current);
                item.classList.toggle('is-done', i < current && !item.classList.contains('has-error'));
            });
            prev.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            submit.hidden = current !== steps.length - 1;
            if (scroll) wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        // Retirer l'erreur dès que l'utilisateur corrige le champ.
        wizard.addEventListener('input', function (e) {
            var container = e.target.closest('.pa-field');
            if (container && e.target.checkValidity()) clearClientError(container);
        });
        wizard.addEventListener('change', function (e) {
            var container = e.target.closest('.pa-field');
            if (container && e.target.checkValidity()) clearClientError(container);
        });

        steps.forEach(function (step, i) {
            if (step.querySelector('.has-error') && navItems[i]) navItems[i].classList.add('has-error');
        });
        var firstError = steps.findIndex(function (step) { return step.querySelector('.has-error'); });
        if (firstError > -1) current = firstError;

        form.setAttribute('novalidate', 'novalidate');
        prev.addEventListener('click', function () { show(current - 1, true); });
        next.addEventListener('click', function () {
            if (!validate(steps[current])) { if (navItems[current]) navItems[current].classList.add('has-error'); return; }
            if (navItems[current]) navItems[current].classList.remove('has-error');
            show(current + 1, true);
        });
        navItems.forEach(function (item, i) {
            item.querySelector('button').addEventListener('click', function () {
                if (i <= current) { show(i, true); return; }
                for (var s = current; s < i; s++) {
                    if (!validate(steps[s])) { show(s, false); return; }
                }
                show(i, true);
            });
        });
        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            if (!submitter || submitter.value !== 'submit') return;
            for (var i = 0; i < steps.length; i++) {
                show(i, false);
                if (!validate(steps[i])) { event.preventDefault(); if (navItems[i]) navItems[i].classList.add('has-error'); return; }
            }
        });

        document.documentElement.classList.add('js-wizard');
        show(current, false);
    }

    // Sélecteurs personnalisés avec drapeau (pays / indicatif).
    document.querySelectorAll('[data-flag-select]').forEach(function (select) {
        var input = select.querySelector('[data-flag-select-input]');
        var trigger = select.querySelector('.pa-flag-select__trigger');
        var menu = select.querySelector('.pa-flag-select__menu');
        var value = select.querySelector('.pa-flag-select__value');
        if (!input || !trigger || !menu || !value) return;

        var render = function (option) {
            var img = option.querySelector('img');
            var text = option.querySelector('span:last-of-type');
            value.innerHTML = '';
            if (img) {
                var clone = img.cloneNode(true);
                clone.alt = '';
                value.appendChild(clone);
            }
            var label = document.createElement('span');
            label.textContent = (option.getAttribute('data-display') || (text ? text.textContent : option.textContent)).trim();
            value.appendChild(label);
        };

        trigger.addEventListener('click', function () {
            var open = !select.classList.contains('is-open');
            document.querySelectorAll('.pa-flag-select.is-open').forEach(function (opened) {
                if (opened !== select) {
                    opened.classList.remove('is-open');
                    var btn = opened.querySelector('.pa-flag-select__trigger');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });
            select.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        menu.querySelectorAll('.pa-flag-select__option').forEach(function (option) {
            option.addEventListener('click', function () {
                menu.querySelectorAll('.pa-flag-select__option').forEach(function (item) {
                    item.classList.remove('is-selected');
                    item.setAttribute('aria-selected', 'false');
                });
                option.classList.add('is-selected');
                option.setAttribute('aria-selected', 'true');
                input.value = option.getAttribute('data-value') || '';
                render(option);
                select.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.pa-flag-select.is-open').forEach(function (select) {
            if (!select.contains(event.target)) {
                select.classList.remove('is-open');
                var trigger = select.querySelector('.pa-flag-select__trigger');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Écran d'inscription PRO : règles et force du mot de passe.
    var passwordRules = document.querySelector('[data-password-rules]');
    if (passwordRules) {
        var pwdId = passwordRules.getAttribute('data-password-rules');
        var pwdInput = document.getElementById(pwdId);
        var strength = document.querySelector('[data-strength-for="' + pwdId + '"]');
        var strengthLabel = strength ? strength.querySelector('[data-strength-label]') : null;
        var ruleMap = {
            length: function (v) { return v.length >= 8; },
            lower: function (v) { return /[a-z]/.test(v); },
            upper: function (v) { return /[A-Z]/.test(v); },
            number: function (v) { return /\d/.test(v); },
            special: function (v) { return /[^A-Za-z0-9]/.test(v); }
        };
        var labels = ['Faible', 'Moyen', 'Bon', 'Fort'];
        if (pwdInput) {
            var refreshPasswordUi = function () {
                var value = pwdInput.value || '';
                passwordRules.hidden = value.length === 0;
                var score = 0;
                Object.keys(ruleMap).forEach(function (key) {
                    var ok = ruleMap[key](value);
                    var item = passwordRules.querySelector('[data-rule="' + key + '"]');
                    if (item) item.classList.toggle('is-ok', ok);
                    if (ok) score++;
                });
                if (strength) {
                    var level = value ? Math.min(4, Math.max(1, Math.round((score / 5) * 4))) : 0;
                    strength.setAttribute('data-level', String(level));
                    if (strengthLabel) strengthLabel.textContent = level ? labels[level - 1] : '';
                }
            };
            pwdInput.addEventListener('input', refreshPasswordUi);
            refreshPasswordUi();
        }
    }

    // Sauvegarde locale du brouillon de l'étape 1 PRO.
    var registerForm = document.querySelector('[data-pro-register-form]');
    if (registerForm && window.localStorage) {
        var storageKey = registerForm.getAttribute('data-draft-key') || 'ovanie-pro-register';
        var exitUrl = registerForm.getAttribute('data-exit-url') || '';
        var saveDraft = function () {
            var draft = {};
            registerForm.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (!field.name || field.type === 'password' || field.type === 'hidden' || field.name === '_token') return;
                if (field.type === 'checkbox') {
                    draft[field.name] = field.checked ? '1' : '';
                    return;
                }
                draft[field.name] = field.value || '';
            });
            localStorage.setItem(storageKey, JSON.stringify(draft));
        };

        if (!registerForm.querySelector('.has-error')) {
            try {
                var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (saved && typeof saved === 'object') {
                    Object.keys(saved).forEach(function (name) {
                        var field = registerForm.querySelector('[name="' + CSS.escape(name) + '"]');
                        if (!field || field.value) return;
                        if (field.type === 'checkbox') field.checked = saved[name] === '1';
                        else field.value = saved[name];
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
            } catch (e) {}
        }

        registerForm.addEventListener('submit', function () {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        });

        registerForm.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.type === 'password') return;
            field.addEventListener('change', saveDraft);
            field.addEventListener('blur', saveDraft);
        });

        var saveButton = registerForm.querySelector('[data-save-draft]');
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                saveDraft();
                saveButton.textContent = 'Brouillon enregistré';
                window.setTimeout(function () {
                    if (exitUrl) window.location.href = exitUrl;
                }, 350);
            });
        }
    }
})();
