(() => {
    'use strict';

    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    // Afficher / masquer les mots de passe.
    qsa('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.dataset.togglePassword || '');
            if (!target) return;
            const show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    // Politique de mot de passe réellement appliquée par le backend.
    qsa('[data-password-source]').forEach((input) => {
        const scope = input.closest('form') || document;
        const rules = scope.querySelector('[data-password-rules]');
        const strength = scope.querySelector('[data-strength]');

        const evaluate = () => {
            const value = input.value || '';
            const checks = {
                length: value.length >= 8,
                lower: /[a-z]/.test(value),
                upper: /[A-Z]/.test(value),
                number: /\d/.test(value),
                symbol: /[^A-Za-z0-9]/.test(value),
            };
            if (rules) {
                Object.entries(checks).forEach(([name, ok]) => {
                    const row = rules.querySelector(`[data-rule="${name}"]`);
                    if (row) row.classList.toggle('is-valid', ok);
                });
            }
            if (strength) {
                const score = Object.values(checks).filter(Boolean).length;
                qsa('i', strength).forEach((bar, index) => bar.classList.toggle('is-on', index < Math.max(0, score - 1)));
                const label = strength.querySelector('span');
                if (label) label.textContent = score >= 5 ? 'Fort' : score >= 4 ? 'Bon' : score >= 3 ? 'Moyen' : 'Faible';
            }
        };
        input.addEventListener('input', evaluate);
        evaluate();
    });

    // Code de vérification à 6 chiffres : saisie, collage, retour arrière et synchronisation.
    qsa('[data-code-boxes]').forEach((box) => {
        const inputs = qsa('input', box);
        const form = box.closest('form');
        const hidden = form?.querySelector('[data-code-value]');
        const status = form?.querySelector('[data-code-status]');

        const sync = () => {
            const value = inputs.map((i) => i.value.replace(/\D/g, '')).join('').slice(0, 6);
            if (hidden) hidden.value = value;
            if (status) {
                const strong = status.querySelector('strong');
                const span = status.querySelector('span');
                const complete = value.length === 6;
                status.classList.toggle('is-complete', complete);
                if (strong) strong.textContent = complete ? 'Code prêt à vérifier' : 'Code de vérification';
                if (span) span.textContent = complete ? 'Les 6 chiffres sont saisis. Vous pouvez continuer.' : 'Saisissez les 6 chiffres reçus.';
            }
        };

        inputs.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                input.value = input.value.replace(/\D/g, '').slice(-1);
                if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
                sync();
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
                if (event.key === 'ArrowLeft' && index > 0) inputs[index - 1].focus();
                if (event.key === 'ArrowRight' && index < inputs.length - 1) inputs[index + 1].focus();
            });
            input.addEventListener('paste', (event) => {
                const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                event.preventDefault();
                pasted.split('').forEach((digit, i) => { if (inputs[i]) inputs[i].value = digit; });
                inputs[Math.min(pasted.length, 6) - 1]?.focus();
                sync();
            });
        });

        const old = hidden?.value?.replace(/\D/g, '').slice(0, 6) || '';
        old.split('').forEach((digit, index) => { if (inputs[index]) inputs[index].value = digit; });
        sync();
    });

    // Sélection des fonctions + récapitulatif et progression synchronisés.
    const roleForm = document.querySelector('[data-role-form]');
    if (roleForm) {
        const cards = qsa('[data-role-card]', roleForm);
        const updateRoles = () => {
            let selectedCount = 0;
            cards.forEach((card) => {
                const input = card.querySelector('input[type="checkbox"]');
                const active = Boolean(input?.checked);
                card.classList.toggle('is-selected', active);
                if (active) selectedCount += 1;
                const summary = document.querySelector(`[data-summary-role="${card.dataset.role}"]`);
                if (summary) summary.hidden = !active;
            });
            const empty = document.querySelector('[data-summary-empty]');
            if (empty) empty.hidden = selectedCount > 0;
            const progress = document.querySelector('[data-progress-label]');
            if (progress) progress.textContent = selectedCount ? '4 sections complétées sur 4' : '3 sections complétées sur 4';
            const row = document.querySelector('[data-progress-step="4"]');
            if (row) {
                const state = row.querySelector('[data-progress-status]');
                if (state) state.textContent = selectedCount ? 'Complétée' : 'En cours';
            }
        };
        cards.forEach((card) => card.querySelector('input[type="checkbox"]')?.addEventListener('change', updateRoles));
        updateRoles();
    }

    // Confirmations explicites (refus d'invitation, etc.).
    qsa('[data-confirm]').forEach((element) => {
        const handler = (event) => {
            const message = element.dataset.confirm || 'Confirmer cette action ?';
            if (!window.confirm(message)) event.preventDefault();
        };
        if (element.tagName === 'FORM') element.addEventListener('submit', handler);
        else element.addEventListener('click', handler);
    });
})();
