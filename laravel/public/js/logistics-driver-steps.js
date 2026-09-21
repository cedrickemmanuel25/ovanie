(function(){
    'use strict';

    function fieldsFor(panel){
        return Array.from(panel.querySelectorAll('input,select,textarea')).filter(function(el){
            if(el.disabled || el.type === 'hidden') return false;
            if(el.type === 'checkbox' && el.hasAttribute('data-zone-checkbox')) return false;
            if(el.type === 'file') return false;
            return true;
        });
    }

    function clearErrors(form){
        form.querySelectorAll('.driver-inline-error').forEach(function(el){ el.remove(); });
        form.querySelectorAll('.driver-invalid').forEach(function(el){ el.classList.remove('driver-invalid'); });
        form.querySelectorAll('.driver-validation-summary').forEach(function(el){ el.remove(); });
    }

    function showStepError(panel, message){
        var error = document.createElement('p');
        error.className = 'driver-validation-summary';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        panel.insertBefore(error, panel.firstChild);
    }

    function showFieldError(field, message){
        field.classList.add('driver-invalid');
        var label = field.closest('label');
        if(!label) return;
        var error = document.createElement('span');
        error.className = 'driver-inline-error';
        error.textContent = message;
        label.appendChild(error);
    }

    function validateStep(form, index){
        clearErrors(form);
        var panels = Array.from(form.querySelectorAll('[data-driver-step]'));
        var panel = panels[index];
        console.warn('[driver-wizard] validateStep', {index: index, panelsFound: panels.length, panelExists: !!panel});
        if(!panel) return false;

        if(index === 1){
            var checked = panel.querySelectorAll('[data-zone-checkbox]:checked');
            var zoneError = panel.querySelector('[data-zone-error]');
            var trigger = panel.querySelector('[data-zone-trigger]');
            if(checked.length === 0){
                if(zoneError) zoneError.hidden = false;
                if(trigger){ trigger.classList.add('driver-invalid'); trigger.focus(); }
                showStepError(panel, "Sélectionnez au moins une commune d'intervention pour continuer.");
                return false;
            }
            if(zoneError) zoneError.hidden = true;
            if(trigger) trigger.classList.remove('driver-invalid');
        }

        var controls = fieldsFor(panel);
        console.warn('[driver-wizard] controls in this step:', controls.map(function(el){ return el.name + ' = valid:' + el.checkValidity(); }));
        for(var i=0;i<controls.length;i++){
            var el = controls[i];
            if(!el.checkValidity()){
                var message = el.validationMessage || 'Vérifiez ce champ.';
                console.warn('[driver-wizard] BLOCKED by field:', el.name, message);
                showFieldError(el, message);
                showStepError(panel, 'Le formulaire contient un champ à corriger avant de continuer.');
                try{ el.focus({preventScroll:true}); }catch(e){ el.focus(); }
                return false;
            }
        }
        console.warn('[driver-wizard] step valid, advancing');
        return true;
    }

    function buildSummary(form){
        var summary = form.querySelector('[data-driver-summary]');
        if(!summary) return;
        summary.innerHTML = '';
        var get = function(name){
            var el = form.elements[name];
            return el && String(el.value || '').trim() ? String(el.value).trim() : '—';
        };
        var zones = Array.from(form.querySelectorAll('[data-zone-checkbox]:checked')).map(function(input){
            var option = input.closest('[data-zone-option]');
            var strong = option ? option.querySelector('.driver-zone-option-copy strong') : null;
            return strong ? strong.textContent.trim() : '';
        }).filter(Boolean).join(', ');
        [
            ['Nom', get('name')],
            ['Téléphone', get('phone')],
            ['Adresse e-mail', get('email')],
            ['Zones d’intervention', zones || '—'],
            ['Véhicule', get('vehicle')],
            ['Statut', get('status')]
        ].forEach(function(row){
            var line = document.createElement('div');
            line.className = 'driver-summary-row';
            var label = document.createElement('span');
            label.textContent = row[0];
            var value = document.createElement('strong');
            value.textContent = row[1];
            line.append(label,value);
            summary.appendChild(line);
        });
    }

    window.ovanieDriverRender = function(form){
        if(!form) return;
        var panels = Array.from(form.querySelectorAll('[data-driver-step]'));
        var labels = Array.from(form.querySelectorAll('.directory-steps>div'));
        var next = form.querySelector('[data-driver-next]');
        var prev = form.querySelector('[data-driver-prev]');
        var step = parseInt(form.dataset.ovanieDriverStep || '0',10);
        if(!Number.isFinite(step) || step < 0) step = 0;
        if(step >= panels.length) step = panels.length - 1;
        form.dataset.ovanieDriverStep = String(step);
        panels.forEach(function(panel,i){ panel.hidden = i !== step; });
        labels.forEach(function(label,i){
            label.classList.toggle('active',i === step);
            label.classList.toggle('is-complete',i < step);
        });
        if(prev) prev.hidden = step === 0;
        if(next){
            next.disabled = false;
            next.textContent = step === panels.length - 1 ? 'Enregistrer le livreur' : 'Suivant →';
        }
        if(step === panels.length - 1) buildSummary(form);
    };

    window.ovanieDriverNext = function(button){
        var form = button && button.closest ? button.closest('[data-ovanie-driver-form]') : null;
        if(!form) return false;
        var panels = Array.from(form.querySelectorAll('[data-driver-step]'));
        var step = parseInt(form.dataset.ovanieDriverStep || '0',10);
        if(!validateStep(form,step)) return false;
        if(step < panels.length - 1){
            form.dataset.ovanieDriverStep = String(step + 1);
            window.ovanieDriverRender(form);
            return false;
        }
        // Avant l'envoi, rendre tous les panneaux visibles pour que le navigateur
        // ne bloque pas un champ requis caché dans une étape précédente.
        panels.forEach(function(panel){ panel.hidden = false; });
        if(typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
        return false;
    };

    window.ovanieDriverPrev = function(button){
        var form = button && button.closest ? button.closest('[data-ovanie-driver-form]') : null;
        if(!form) return false;
        clearErrors(form);
        var step = parseInt(form.dataset.ovanieDriverStep || '0',10);
        form.dataset.ovanieDriverStep = String(Math.max(0,step - 1));
        window.ovanieDriverRender(form);
        return false;
    };

    function init(){
        document.querySelectorAll('[data-ovanie-driver-form]').forEach(function(form){
            if(form.dataset.ovanieDriverStep === undefined) form.dataset.ovanieDriverStep = '0';
            window.ovanieDriverRender(form);
        });
    }

    document.addEventListener('click', function(event){
        var nextBtn = event.target.closest('[data-driver-next]');
        if(nextBtn){
            event.preventDefault();
            window.ovanieDriverNext(nextBtn);
            return;
        }
        var prevBtn = event.target.closest('[data-driver-prev]');
        if(prevBtn){
            event.preventDefault();
            window.ovanieDriverPrev(prevBtn);
        }
    }, true);

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init,{once:true});
    else init();
})();
