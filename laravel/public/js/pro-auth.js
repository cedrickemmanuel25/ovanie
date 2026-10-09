document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-password-toggle'));
            if (!target) return;
            var show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            button.textContent = show ? 'Masquer' : 'Voir';
        });
    });

    document.querySelectorAll('[data-password-strength]').forEach(function (input) {
        var meter = document.querySelector('[data-password-meter="' + input.id + '"]');
        if (!meter) return;
        var update = function () {
            var value = input.value || '';
            var score = 0;
            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;
            meter.setAttribute('data-strength', String(score));
        };
        input.addEventListener('input', update);
        update();
    });

    document.querySelectorAll('[data-pro-wizard]').forEach(function (form) {
        var steps = Array.from(form.querySelectorAll('[data-wizard-step]'));
        var progress = Array.from(form.querySelectorAll('[data-wizard-progress]'));
        var current = Math.max(1, Math.min(steps.length, parseInt(form.getAttribute('data-initial-step') || '1', 10)));

        var render = function () {
            steps.forEach(function (step, index) {
                step.classList.toggle('is-active', index + 1 === current);
            });
            progress.forEach(function (item, index) {
                item.classList.toggle('is-active', index + 1 === current);
                item.classList.toggle('is-done', index + 1 < current);
            });
            window.scrollTo({ top: Math.max(0, form.getBoundingClientRect().top + window.scrollY - 18), behavior: 'smooth' });
        };

        var validateCurrent = function () {
            var step = steps[current - 1];
            if (!step) return true;
            var controls = Array.from(step.querySelectorAll('input, select, textarea'));
            for (var i = 0; i < controls.length; i++) {
                var control = controls[i];
                if (control.disabled) continue;
                if (!control.checkValidity()) {
                    control.reportValidity();
                    return false;
                }
            }
            return true;
        };

        form.querySelectorAll('[data-wizard-next]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!validateCurrent()) return;
                if (current < steps.length) current++;
                render();
            });
        });

        form.querySelectorAll('[data-wizard-prev]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (current > 1) current--;
                render();
            });
        });

        form.addEventListener('submit', function (event) {
            if (event.submitter && event.submitter.value === 'draft') {
                return;
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                for (var i = 0; i < steps.length; i++) {
                    var invalid = steps[i].querySelector(':invalid');
                    if (invalid) {
                        current = i + 1;
                        render();
                        invalid.reportValidity();
                        return;
                    }
                }
                return;
            }
            var submit = form.querySelector('[data-submit-button]');
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Enregistrement du profil…';
            }
        });

        render();
    });
});
