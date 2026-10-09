(function(){
'use strict';

var root=document.querySelector('[data-qpage]');
if(!root)return;

var form=root.querySelector('[data-qform]');
var compatibility=JSON.parse(root.getAttribute('data-compatibility')||'{}');
var profiles=JSON.parse(root.getAttribute('data-profiles')||'{}');
var iconSprite=root.getAttribute('data-icon-sprite')||'';
var sideImage=root.querySelector('[data-side-image]');
var sideTitle=root.querySelector('[data-side-title]');
var sideDesc=root.querySelector('[data-side-desc]');
var sideIconUse=root.querySelector('[data-side-icon-use]');
var sideRequirements=root.querySelector('[data-side-requirements]');
var registration=root.querySelector('[data-registration-field]');

function selectedEntity(){
    var x=form.querySelector('input[name="entity_type"]:checked');
    return x?x.value:'';
}

function selectedProfile(){
    var x=form.querySelector('input[name="professional_profile"]:checked');
    return x?x.value:'';
}

function svgMarkup(icon){
    return '<svg class="qsvg" aria-hidden="true"><use href="'+iconSprite+'#'+icon+'"></use></svg>';
}

function syncRequirements(items){
    if(!sideRequirements)return;
    sideRequirements.innerHTML='';
    (items||[]).forEach(function(item){
        var li=document.createElement('li');
        li.innerHTML='<span>'+svgMarkup(item.icon||'file-text')+'</span><div><strong></strong><small></small></div>';
        li.querySelector('strong').textContent=item.title||'';
        li.querySelector('small').textContent=item.text||'';
        sideRequirements.appendChild(li);
    });
}

function syncProfile(){
    var key=selectedProfile();
    var p=profiles[key];
    if(!p)return;

    if(sideImage){
        sideImage.src=p.imgUrl;
        sideImage.alt=p.label||'';
    }
    if(sideTitle)sideTitle.textContent=p.label||'';
    if(sideDesc)sideDesc.textContent=p.desc||'';
    if(sideIconUse)sideIconUse.setAttribute('href',iconSprite+'#'+(p.heroIcon||'briefcase'));
    syncRequirements(p.requirements||[]);
}

function syncCompatibility(){
    var entity=selectedEntity();
    root.querySelectorAll('[data-profile-card]').forEach(function(card){
        var key=card.getAttribute('data-profile-card');
        var input=card.querySelector('input[name="professional_profile"]');
        var ok=!entity||(compatibility[key]||[]).indexOf(entity)!==-1;
        card.classList.toggle('is-disabled',!ok);
        if(input)input.disabled=!ok;
        if(!ok&&input&&input.checked)input.checked=false;
    });

    if(registration)registration.style.display=entity==='independent'?'none':'';

    if(!selectedProfile()){
        var first=form.querySelector('input[name="professional_profile"]:not(:disabled)');
        if(first)first.checked=true;
    }
    syncProfile();
}

form.addEventListener('change',function(e){
    if(e.target.name==='entity_type')syncCompatibility();
    if(e.target.name==='professional_profile')syncProfile();
});

function initCountryMenus(){
    root.querySelectorAll('[data-qcountry]').forEach(function(wrapper){
        var native=wrapper.querySelector('[data-qcountry-native]');
        var trigger=wrapper.querySelector('[data-qcountry-trigger]');
        var menu=wrapper.querySelector('[data-qcountry-menu]');
        var label=wrapper.querySelector('[data-qcountry-label]');
        var flag=wrapper.querySelector('[data-qcountry-flag]');
        if(!native||!trigger||!menu)return;

        function close(){
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded','false');
        }
        function open(){
            root.querySelectorAll('[data-qcountry].is-open').forEach(function(other){
                if(other!==wrapper){
                    other.classList.remove('is-open');
                    var b=other.querySelector('[data-qcountry-trigger]');
                    if(b)b.setAttribute('aria-expanded','false');
                }
            });
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded','true');
        }
        function choose(option,emit){
            var value=option.getAttribute('data-value')||'';
            var text=option.getAttribute('data-label')||option.textContent.trim();
            var src=option.getAttribute('data-flag')||'';
            native.value=value;
            if(label)label.textContent=text;
            if(flag){flag.src=src;flag.alt='';}
            menu.querySelectorAll('.qcountry__option').forEach(function(item){
                var selected=item===option;
                item.classList.toggle('is-selected',selected);
                item.setAttribute('aria-selected',selected?'true':'false');
            });
            close();
            if(emit)native.dispatchEvent(new Event('change',{bubbles:true}));
        }

        trigger.addEventListener('click',function(e){
            e.preventDefault();
            wrapper.classList.contains('is-open')?close():open();
        });
        menu.querySelectorAll('.qcountry__option').forEach(function(option){
            option.addEventListener('click',function(e){
                e.preventDefault();
                choose(option,true);
            });
        });
        wrapper.addEventListener('keydown',function(e){
            if(e.key==='Escape')close();
        });
        document.addEventListener('click',function(e){
            if(!wrapper.contains(e.target))close();
        });

        var initial=menu.querySelector('.qcountry__option[data-value="'+CSS.escape(native.value||'')+'"]')||menu.querySelector('.qcountry__option[data-value=""]');
        if(initial)choose(initial,false);
    });
}

initCountryMenus();

syncCompatibility();
})();
