/* OVANIE PRO — écrans d'accès (aucune dépendance). */
(function () {
    'use strict';

    var RULES = {
        length: function (v) { return v.length >= 8; },
        lower: function (v) { return /[a-z]/.test(v); },
        upper: function (v) { return /[A-Z]/.test(v); },
        number: function (v) { return /\d/.test(v); },
        symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
    };
    var LEVELS = ['', 'Faible', 'Moyen', 'Fort', 'Très fort'];

    // Œil : afficher / masquer le mot de passe.
    document.querySelectorAll('[data-oa-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-oa-toggle'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // Exigences et robustesse du mot de passe, mises à jour à la frappe.
    document.querySelectorAll('[data-oa-rules-input]').forEach(function (input) {
        var form = input.closest('form');
        var rules = form && form.querySelector('[data-oa-rules]');
        var meter = form && form.querySelector('[data-oa-strength]');
        var label = meter && meter.querySelector('[data-oa-strength-label]');

        var refresh = function () {
            var v = input.value, passed = 0;
            Object.keys(RULES).forEach(function (key) {
                var ok = RULES[key](v);
                if (ok) passed++;
                var item = rules && rules.querySelector('[data-rule="' + key + '"]');
                if (item) item.classList.toggle('is-ok', ok);
            });
            if (meter) {
                var level = v ? Math.max(1, Math.min(4, passed - 1)) : 0;
                meter.setAttribute('data-level', String(level));
                if (label) label.textContent = LEVELS[level];
            }
        };
        input.addEventListener('input', refresh);
        refresh();
    });

    // Confirmation des deux mots de passe.
    document.querySelectorAll('form').forEach(function (form) {
        var pass = form.querySelector('input[name="password"]');
        var confirm = form.querySelector('input[name="password_confirmation"]');
        var message = form.querySelector('[data-oa-match]');
        if (!pass || !confirm || !message) return;
        var check = function () { message.hidden = !confirm.value || confirm.value === pass.value; };
        confirm.addEventListener('input', check);
        pass.addEventListener('input', check);
    });

    // Confirmation avant une action irréversible (refus d'invitation).
    document.querySelectorAll('[data-oa-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (!window.confirm(el.getAttribute('data-oa-confirm'))) event.preventDefault();
        });
    });

    // Indicatif téléphonique : drapeau synchronisé avec le pays choisi.
    document.querySelectorAll('[data-oa-phone]').forEach(function (box) {
        var select = box.querySelector('[data-oa-phone-code]');
        var flag = box.querySelector('[data-oa-flag]');
        if (!select || !flag) return;
        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            flag.src = flag.getAttribute('data-flag-base') + '/' + option.getAttribute('data-flag') + '.svg';
        });
    });

    // Code de vérification à 6 chiffres : saisie, collage, retour arrière, vérification serveur.
    var reset = document.querySelector('[data-oa-reset]');
    if (reset) {
        var digits = Array.prototype.slice.call(reset.querySelectorAll('.oa-code__digit'));
        var codeBox = reset.querySelector('[data-oa-code]');
        var hidden = reset.querySelector('[data-oa-code-value]');
        var status = reset.querySelector('[data-oa-code-status]');
        var title = reset.querySelector('[data-oa-code-title]');
        var text = reset.querySelector('[data-oa-code-text]');
        var submit = reset.querySelector('[data-oa-reset-submit]');
        var token = document.querySelector('meta[name="csrf-token"]');
        var lastChecked = '';

        var setStatus = function (state, heading, detail) {
            if (!status) return;
            status.setAttribute('data-state', state);
            title.textContent = heading;
            text.textContent = detail;
            codeBox.classList.toggle('is-error', state === 'invalid' || state === 'expired');
            codeBox.classList.toggle('is-valid', state === 'valid');
        };

        var code = function () { return digits.map(function (d) { return d.value; }).join(''); };

        var verify = function () {
            var value = code();
            hidden.value = value;
            if (value.length < 6) {
                lastChecked = '';
                setStatus('idle', 'Code à 6 chiffres', 'Saisissez le code reçu pour continuer.');
                return;
            }
            if (value === lastChecked) return;
            lastChecked = value;
            setStatus('checking', 'Vérification…', 'Nous vérifions votre code.');

            var body = new FormData();
            body.append('challenge', reset.getAttribute('data-challenge'));
            body.append('code', value);
            fetch(reset.getAttribute('data-verify-url'), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token ? token.content : '', 'Accept': 'application/json' },
                body: body,
                credentials: 'same-origin'
            }).then(function (r) {
                if (r.status === 429) return { status: 'throttled' };
                return r.json();
            }).then(function (data) {
                if (code() !== value) return;
                if (data.status === 'valid') {
                    setStatus('valid', 'Code valide', 'Vous pouvez maintenant définir votre nouveau mot de passe.');
                    var next = document.getElementById('password');
                    if (next) next.focus();
                } else if (data.status === 'expired') {
                    setStatus('expired', 'Code expiré', 'Ce code n’est plus valide. Demandez un nouveau code.');
                    if (submit) submit.disabled = true;
                } else if (data.status === 'throttled') {
                    lastChecked = '';
                    setStatus('invalid', 'Trop de tentatives', 'Patientez une minute avant de réessayer.');
                } else {
                    setStatus('invalid', 'Code incorrect', 'Vérifiez les 6 chiffres reçus.');
                }
            }).catch(function () {
                lastChecked = '';
                setStatus('idle', 'Code saisi', 'Il sera vérifié à la validation du formulaire.');
            });
        };

        var fill = function (start, text) {
            var chars = text.replace(/\D/g, '').split('');
            for (var i = start; i < digits.length && chars.length; i++) digits[i].value = chars.shift();
            var firstEmpty = digits.findIndex(function (d) { return !d.value; });
            (digits[firstEmpty === -1 ? digits.length - 1 : firstEmpty]).focus();
            verify();
        };

        digits.forEach(function (input, index) {
            input.addEventListener('input', function () {
                var v = input.value.replace(/\D/g, '');
                if (v.length > 1) { input.value = ''; fill(index, v); return; }
                input.value = v;
                if (v && digits[index + 1]) digits[index + 1].focus();
                verify();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !input.value && digits[index - 1]) { digits[index - 1].focus(); digits[index - 1].value = ''; verify(); e.preventDefault(); }
                if (e.key === 'ArrowLeft' && digits[index - 1]) digits[index - 1].focus();
                if (e.key === 'ArrowRight' && digits[index + 1]) digits[index + 1].focus();
            });
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                fill(index, (e.clipboardData || window.clipboardData).getData('text'));
            });
            input.addEventListener('focus', function () { input.select(); });
        });

        reset.addEventListener('submit', function (e) {
            hidden.value = code();
            if (hidden.value.length !== 6) {
                e.preventDefault();
                setStatus('invalid', 'Code incomplet', 'Saisissez les 6 chiffres du code reçu.');
                digits[code().length] && digits[code().length].focus();
            }
        });
    }

    // Fonctions (étape 4) : la liste des espaces activés suit les cases cochées.
    var roles = document.querySelector('[data-oa-roles]');
    if (roles) {
        var empty = roles.querySelector('[data-oa-spaces-empty]');
        var sync = function () {
            var count = 0;
            roles.querySelectorAll('.oa-role__input').forEach(function (input) {
                var space = roles.querySelector('[data-space-for="' + input.value + '"]');
                if (space) space.hidden = !input.checked;
                if (input.checked) count++;
            });
            if (empty) empty.hidden = count > 0;
        };
        roles.addEventListener('change', sync);
        sync();
    }
})();
