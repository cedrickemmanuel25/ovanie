(function(){
  'use strict';
  var app=document.querySelector('[data-fabricant-page]');
  if(!app)return;

  var tabs=Array.prototype.slice.call(app.querySelectorAll('[data-tab-target]'));
  var panels=Array.prototype.slice.call(app.querySelectorAll('[data-tab-panel]'));
  var next=app.querySelector('[data-next-tab]');
  var prev=app.querySelector('[data-prev-tab]');
  var finalSubmit=app.querySelector('[data-final-submit]');
  var sideCopy=app.querySelector('[data-side-copy]');
  var tipCopy=app.querySelector('[data-tip-copy]');
  var sideCount=app.querySelector('[data-side-count]');
  var sideProgress=app.querySelector('[data-side-progress]');
  var progressCopy=app.querySelector('[data-progress-copy]');
  var progressItems=Array.prototype.slice.call(app.querySelectorAll('[data-progress-item]'));
  var sideCards=Array.prototype.slice.call(app.querySelectorAll('[data-side-card]'));
  var declaredCopy=app.querySelector('[data-declared-copy]');
  var returnUrl=app.getAttribute('data-return-url')||'';
  var keys=tabs.map(function(t){return t.getAttribute('data-tab-target');});
  var activeKey=app.getAttribute('data-first-tab')||keys[0];

  var copy={
    responsible:{hero:'Transformez votre production en opportunités sur OVANIE PRO.',tip:'Plus vos informations sont complètes, plus votre profil sera visible auprès des acheteurs, des entreprises BTP et des partenaires.'},
    company:{hero:'Transformez votre production en opportunités sur OVANIE PRO.',tip:'Les informations de votre entreprise renforcent la confiance et facilitent les opportunités d’affaires avec les professionnels du BTP.'},
    factory:{hero:'Valorisez votre outil de production et vos capacités industrielles.',tip:'Une usine bien renseignée améliore la visibilité de votre entreprise auprès des acheteurs, donneurs d’ordres et partenaires potentiels.'},
    commerce:{hero:'Développez vos débouchés commerciaux en Afrique de l’Ouest et à l’international.',tip:'Des informations commerciales claires et précises améliorent votre visibilité sur la plateforme et renforcent la confiance des acheteurs professionnels.'},
    verification:{hero:'Des produits de qualité pour bâtir l’Afrique de demain.',tip:'La soumission de vos documents ne signifie pas que votre entreprise est déjà vérifiée. Nos équipes contrôlent les informations avant validation.'}
  };

  var form=app.querySelector('[data-fabricant-form]');
  function parseDataJson(name,fallback){
    try{return JSON.parse(app.getAttribute(name)||'');}catch(e){return fallback;}
  }
  var requiredFieldsByTab=parseDataJson('data-required-fields',{});
  var requiredDocuments=parseDataJson('data-required-documents',[]);
  var providedDocuments=parseDataJson('data-provided-documents',[]);

  function valueForField(panel,name){
    var escaped=(window.CSS&&CSS.escape)?CSS.escape(name):name.replace(/([^\w-])/g,'\\$1');
    var exact=panel.querySelector('[name="'+escaped+'"]');
    if(exact){
      if(exact.type==='radio'){
        var checked=panel.querySelector('[name="'+escaped+'"]:checked');
        return checked?checked.value:'';
      }
      if(exact.type==='checkbox')return exact.checked?exact.value:'';
      return String(exact.value||'').trim();
    }
    var checks=Array.prototype.slice.call(panel.querySelectorAll('[name="'+escaped+'[]"]'));
    if(checks.length){
      return checks.some(function(el){return el.checked;}) ? '__checked__' : '';
    }
    return '';
  }

  function fieldContainer(panel,name){
    var controls=Array.prototype.slice.call(panel.querySelectorAll('[name]'));
    var control=controls.find(function(el){
      return el.name===name || el.name===name+'[]';
    });
    return control ? (control.closest('.pf-field') || control.closest('.pf-docbox') || control.closest('.pf-subsection') || control.parentElement) : null;
  }

  function clearClientError(container){
    if(!container)return;
    container.classList.remove('is-client-error');
    Array.prototype.slice.call(container.querySelectorAll('.pf-error[data-client-error]')).forEach(function(node){node.remove();});
    if(!container.querySelector('.pf-error'))container.classList.remove('is-error');
  }

  function addClientError(container,message){
    if(!container)return;
    clearClientError(container);
    container.classList.add('is-error','is-client-error');
    var error=document.createElement('p');
    error.className='pf-error';
    error.setAttribute('data-client-error','1');
    error.textContent=message||'Ce champ est obligatoire pour continuer.';
    container.appendChild(error);
  }

  function fileInputForType(panel,type){
    var wantedOne='documents['+type+']';
    var wantedMany=wantedOne+'[]';
    return Array.prototype.slice.call(panel.querySelectorAll('input[type=file]')).find(function(input){
      return input.name===wantedOne || input.name===wantedMany;
    })||null;
  }

  function documentProvided(panel,type){
    if(providedDocuments.indexOf(type)!==-1)return true;
    var input=fileInputForType(panel,type);
    return !!(input && input.files && input.files.length);
  }

  function documentContainer(input){
    if(!input)return null;
    return input.closest('.pf-docbox') || input.closest('.pf-subsection') || input.closest('.pf-field') || input.parentElement;
  }

  function validateEmailOrUrl(control){
    if(!control || !control.value)return true;
    if(control.type==='email' || control.type==='url'){
      return typeof control.checkValidity==='function' ? control.checkValidity() : true;
    }
    return true;
  }

  function certificationStarted(panel){
    var names=['certification_type','certification_body','certification_number','certification_issue_date','certification_expiry_date'];
    var started=names.some(function(name){return !!valueForField(panel,name);});
    var file=fileInputForType(panel,'certifications');
    if(file&&file.files&&file.files.length)started=true;
    var json=panel.querySelector('[name="certifications_json"]');
    if(json&&json.value){
      try{
        var rows=JSON.parse(json.value);
        if(Array.isArray(rows)&&rows.some(function(row){
          return row&&Object.keys(row).some(function(k){return String(row[k]||'').trim()!=='';});
        }))started=true;
      }catch(e){}
    }
    return started;
  }

  function conditionalRequirements(panel,key,invalid){
    if(key==='commerce' && valueForField(panel,'export_enabled')==='yes'){
      if(!valueForField(panel,'shipping_modes')){
        var c1=fieldContainer(panel,'shipping_modes');
        addClientError(c1,'Sélectionnez au moins un mode d’expédition pour continuer.');
        invalid.push(c1);
      }
      if(!valueForField(panel,'incoterms')){
        var c2=fieldContainer(panel,'incoterms');
        addClientError(c2,'Sélectionnez au moins un Incoterm pour continuer.');
        invalid.push(c2);
      }
    }

    if(key==='verification' && certificationStarted(panel)){
      ['certification_type','certification_body','certification_number','certification_issue_date'].forEach(function(name){
        if(!valueForField(panel,name)){
          var c=fieldContainer(panel,name);
          addClientError(c,'Complétez cette information de certification.');
          invalid.push(c);
        }
      });
      if(!documentProvided(panel,'certifications')){
        var certInput=fileInputForType(panel,'certifications');
        var certContainer=documentContainer(certInput);
        addClientError(certContainer,'Ajoutez le document correspondant à la certification.');
        invalid.push(certContainer);
      }
    }
  }

  function validatePanel(key,options){
    options=options||{};
    var panel=panels.find(function(p){return p.getAttribute('data-tab-panel')===key;});
    if(!panel)return true;

    // Synchronise les champs composites avant toute validation.
    if(typeof syncPhone==='function'){syncPhone();syncWa();}
    panel.querySelectorAll('[data-compose-field]').forEach(function(root){
      var hidden=root.querySelector('[data-compose-hidden]');
      var val=root.querySelector('[data-compose-value]');
      var unit=root.querySelector('[data-compose-unit]');
      if(hidden&&val&&unit){
        hidden.value=String(val.value||'').trim()
          ? String(val.value).trim()+' '+unit.options[unit.selectedIndex].textContent.trim()
          : '';
      }
    });
    if(typeof syncCerts==='function')syncCerts();

    Array.prototype.slice.call(panel.querySelectorAll('.is-client-error')).forEach(clearClientError);
    var invalid=[];
    (requiredFieldsByTab[key]||[]).forEach(function(name){
      var container=fieldContainer(panel,name);
      var value=valueForField(panel,name);
      if(!value){
        addClientError(container,'Ce champ est obligatoire pour continuer.');
        invalid.push(container);
        return;
      }
      var control=container&&container.querySelector('[name="'+name+'"]');
      if(control && !validateEmailOrUrl(control)){
        addClientError(container,control.type==='email'?'Saisissez une adresse e-mail valide.':'Saisissez une adresse URL valide.');
        invalid.push(container);
      }
    });

    if(key==='verification'){
      requiredDocuments.forEach(function(type){
        if(!documentProvided(panel,type)){
          var input=fileInputForType(panel,type);
          var container=documentContainer(input);
          addClientError(container,'Ce document est obligatoire pour continuer.');
          invalid.push(container);
        }
      });
    }

    conditionalRequirements(panel,key,invalid);

    invalid=invalid.filter(Boolean);
    if(invalid.length){
      if(options.focus!==false){
        var firstControl=invalid[0].querySelector('input:not([type=hidden]),select,button,textarea');
        invalid[0].scrollIntoView({behavior:'smooth',block:'center'});
        if(firstControl && typeof firstControl.focus==='function')setTimeout(function(){firstControl.focus();},250);
      }
      return false;
    }
    return true;
  }

  function setTabClientComplete(key,complete){
    var tab=tabs[keys.indexOf(key)];
    if(!tab)return;
    var done=tab.querySelector('.pf-tab__done');
    if(complete && !done){
      done=document.createElement('i');
      done.className='pf-tab__done';
      done.textContent='✓';
      tab.appendChild(done);
    }else if(!complete && done && done.getAttribute('data-server-done')!=='1'){
      done.remove();
    }
    updateProgress();
  }

  // Les coches rendues par Laravel correspondent aux sections déjà persistées.
  tabs.forEach(function(tab){
    var done=tab.querySelector('.pf-tab__done');
    if(done)done.setAttribute('data-server-done','1');
  });

  function updateProgress(){
    var doneCount=tabs.filter(function(t){return !!t.querySelector('.pf-tab__done');}).length;
    var bars=Array.prototype.slice.call(app.querySelectorAll('.pf-tabs-progress__bars span'));
    bars.forEach(function(bar,index){bar.classList.toggle('is-done',index<doneCount);});
    progressItems.forEach(function(item){
      var itemKey=item.getAttribute('data-progress-item');
      var tab=tabs[keys.indexOf(itemKey)];
      var done=tab&&!!tab.querySelector('.pf-tab__done');
      item.classList.toggle('is-done',done);
      var state=item.querySelector('.pf-progresslist__state');
      if(state)state.textContent=done?'Complété':(itemKey===activeKey?'En cours':'À compléter');
    });
    if(sideCount)sideCount.textContent=String(doneCount);
    if(sideProgress)sideProgress.style.width=(doneCount/5*100)+'%';
    if(progressCopy)progressCopy.textContent=doneCount+' section'+(doneCount>1?'s':'')+' complétée'+(doneCount>1?'s':'')+' sur 5';
  }

  function panelCompleteSilently(key){
    var panel=panels.find(function(p){return p.getAttribute('data-tab-panel')===key;});
    if(!panel)return false;

    // Synchronise uniquement les champs composites avant le contrôle silencieux.
    if(typeof syncPhone==='function'){syncPhone();syncWa();}
    panel.querySelectorAll('[data-compose-field]').forEach(function(root){
      var hidden=root.querySelector('[data-compose-hidden]');
      var val=root.querySelector('[data-compose-value]');
      var unit=root.querySelector('[data-compose-unit]');
      if(hidden&&val&&unit){
        hidden.value=String(val.value||'').trim()
          ? String(val.value).trim()+' '+unit.options[unit.selectedIndex].textContent.trim()
          : '';
      }
    });
    if(typeof syncCerts==='function')syncCerts();

    var required=(requiredFieldsByTab[key]||[]);
    for(var i=0;i<required.length;i++){
      var name=required[i];
      if(!valueForField(panel,name))return false;
      var container=fieldContainer(panel,name);
      var control=container&&container.querySelector('[name="'+name+'"]');
      if(control && !validateEmailOrUrl(control))return false;
    }

    if(key==='commerce' && valueForField(panel,'export_enabled')==='yes'){
      if(!valueForField(panel,'shipping_modes') || !valueForField(panel,'incoterms'))return false;
    }

    if(key==='verification'){
      for(var d=0;d<requiredDocuments.length;d++){
        if(!documentProvided(panel,requiredDocuments[d]))return false;
      }
      if(certificationStarted(panel)){
        var certRequired=['certification_type','certification_body','certification_number','certification_issue_date'];
        for(var c=0;c<certRequired.length;c++){
          if(!valueForField(panel,certRequired[c]))return false;
        }
        if(!documentProvided(panel,'certifications'))return false;
      }
    }

    return true;
  }

  function firstIncompleteBefore(targetKey){
    var targetIndex=keys.indexOf(targetKey);
    if(targetIndex<=0)return null;
    for(var i=0;i<targetIndex;i++){
      if(!panelCompleteSilently(keys[i]))return keys[i];
    }
    return null;
  }

  function updateNavigationAccess(){
    var firstIncompleteIndex=keys.findIndex(function(key){return !panelCompleteSilently(key);});
    var maxReachableIndex=firstIncompleteIndex===-1 ? keys.length-1 : firstIncompleteIndex;

    tabs.forEach(function(tab){
      var index=keys.indexOf(tab.getAttribute('data-tab-target'));
      var locked=index>maxReachableIndex;
      tab.classList.toggle('is-locked',locked);
      tab.setAttribute('aria-disabled',locked?'true':'false');
      tab.tabIndex=locked?-1:0;
    });

    progressItems.forEach(function(item){
      var index=keys.indexOf(item.getAttribute('data-progress-item'));
      var locked=index>maxReachableIndex;
      item.classList.toggle('is-locked',locked);
      item.setAttribute('aria-disabled',locked?'true':'false');
      item.tabIndex=locked?-1:0;
    });
  }

  function requestNavigation(targetKey){
    if(keys.indexOf(targetKey)===-1 || targetKey===activeKey)return;

    var currentIndex=keys.indexOf(activeKey);
    var targetIndex=keys.indexOf(targetKey);

    // Revenir vers une étape précédente reste toujours possible.
    if(targetIndex<currentIndex){
      setActive(targetKey);
      return;
    }

    // Une étape future n'est accessible que si TOUTES les étapes précédentes
    // sont réellement complètes. On renvoie l'utilisateur sur le premier bloc incomplet.
    var blocker=firstIncompleteBefore(targetKey);
    if(blocker){
      setActive(blocker);
      validatePanel(blocker,{focus:true});
      updateNavigationAccess();
      return;
    }

    // Pour une navigation vers l'avant, l'étape courante doit elle-même être valide.
    if(!validatePanel(activeKey,{focus:true})){
      updateNavigationAccess();
      return;
    }

    setTabClientComplete(activeKey,true);
    setActive(targetKey);
  }

  function setActive(key){
    if(keys.indexOf(key)===-1)key=keys[0];
    activeKey=key;
    var idx=keys.indexOf(key);
    tabs.forEach(function(t){
      var on=t.getAttribute('data-tab-target')===key;
      t.classList.toggle('is-active',on);
      t.setAttribute('aria-selected',on?'true':'false');
    });
    panels.forEach(function(p){p.hidden=p.getAttribute('data-tab-panel')!==key;});
    progressItems.forEach(function(item){
      var itemKey=item.getAttribute('data-progress-item');
      item.classList.toggle('is-active',itemKey===key);
      var tab=tabs[keys.indexOf(itemKey)];
      var done=tab&&!!tab.querySelector('.pf-tab__done');
      item.classList.toggle('is-done',done);
      var state=item.querySelector('.pf-progresslist__state');
      if(state)state.textContent=done?'Complété':(itemKey===key?'En cours':'À compléter');
    });
    if(next)next.hidden=idx===keys.length-1;
    if(finalSubmit)finalSubmit.hidden=idx!==keys.length-1;
    if(sideCopy&&copy[key])sideCopy.textContent=copy[key].hero;
    if(tipCopy&&copy[key])tipCopy.textContent=copy[key].tip;
    sideCards.forEach(function(card){
      var type=card.getAttribute('data-side-card');
      var show=false;
      if(type==='tip') show=true;
      if(type==='progress') show=(key==='responsible'||key==='company'||key==='commerce');
      if(type==='declared') show=(key==='factory'||key==='commerce');
      if(type==='site') show=(key==='factory');
      if(type==='verification-status'||type==='secure') show=(key==='verification');
      card.hidden=!show;
    });
    if(declaredCopy){
      declaredCopy.textContent = key==='commerce'
        ? "Les conditions commerciales et les capacités d’exportation renseignées sont fournies à titre informatif. Elles seront vérifiées lors de l’activation du compte."
        : "Les informations que vous renseignez ici décrivent votre outil de production. Une capacité déclarée ne devient pas automatiquement une capacité vérifiée.";
    }
    updateProgress();
    updateNavigationAccess();
    window.scrollTo({top:0,behavior:'smooth'});
  }

  tabs.forEach(function(tab){
    tab.addEventListener('click',function(){
      requestNavigation(tab.getAttribute('data-tab-target'));
    });
  });

  progressItems.forEach(function(item){
    item.setAttribute('role','button');
    item.addEventListener('click',function(){
      requestNavigation(item.getAttribute('data-progress-item'));
    });
    item.addEventListener('keydown',function(event){
      if(event.key==='Enter' || event.key===' '){
        event.preventDefault();
        requestNavigation(item.getAttribute('data-progress-item'));
      }
    });
  });

  if(next)next.addEventListener('click',function(){
    var i=keys.indexOf(activeKey);
    if(i>=keys.length-1)return;
    if(!validatePanel(activeKey,{focus:true})){
      updateNavigationAccess();
      return;
    }
    setTabClientComplete(activeKey,true);
    setActive(keys[i+1]);
  });
  if(prev)prev.addEventListener('click',function(){
    var i=keys.indexOf(activeKey);
    if(i>0)setActive(keys[i-1]);else if(returnUrl)window.location.href=returnUrl;
  });

  /* Country -> city lists for the supported OVANIE PRO countries.
     The city selector always opens downward and includes an "Autre ville / localité" fallback. */
  var cityMap={
    cotedivoire:['Abidjan','Abengourou','Abobo','Aboisso','Adzopé','Agboville','Anyama','Bingerville','Bondoukou','Bongouanou','Bouaflé','Bouaké','Boundiali','Dabou','Daloa','Danané','Daoukro','Dimbokro','Duékoué','Ferkessédougou','Gagnoa','Grand-Bassam','Grand-Lahou','Guiglo','Issia','Jacqueville','Katiola','Korhogo','Lakota','Man','Odienné','San-Pédro','Sassandra','Séguéla','Sinfra','Soubré','Tabou','Tiassalé','Toumodi','Yamoussoukro'],
    senegal:['Dakar','Pikine','Guédiawaye','Rufisque','Thiès','Saint-Louis','Kaolack','Ziguinchor','Diourbel','Louga','Tambacounda','Kolda','Mbour','Fatick','Kaffrine','Kédougou','Matam','Sédhiou','Richard-Toll','Tivaouane','Touba','Joal-Fadiouth','Bargny','Kayar','Podor'],
    mali:['Bamako','Sikasso','Ségou','Mopti','Kayes','Koutiala','Gao','Tombouctou','Koulikoro','Kita','San','Bougouni','Niono','Markala','Kati','Kolokani','Bandiagara','Douentza','Nioro du Sahel','Yanfolila'],
    burkinafaso:['Ouagadougou','Bobo-Dioulasso','Koudougou','Ouahigouya','Banfora','Kaya','Tenkodogo','Fada N’Gourma','Dédougou','Gaoua','Ziniaré','Manga','Dori','Réo','Kombissiri','Houndé','Koupéla','Pouytenga','Boromo','Diébougou'],
    ghana:['Accra','Kumasi','Tamale','Sekondi-Takoradi','Tema','Cape Coast','Koforidua','Ho','Sunyani','Bolgatanga','Wa','Techiman','Obuasi','Tarkwa','Kasoa','Madina','Ashaiman','Nkawkaw','Winneba','Elmina'],
    guinee:['Conakry','Nzérékoré','Kankan','Kindia','Labé','Boké','Mamou','Faranah','Kissidougou','Guéckédou','Macenta','Siguiri','Kamsar','Fria','Dabola','Pita','Dalaba','Coyah','Dubréka','Forécariah'],
    togo:['Lomé','Sokodé','Kara','Kpalimé','Atakpamé','Dapaong','Tsévié','Aného','Notsé','Mango','Bassar','Bafilo','Badou','Vogan','Tabligbo','Tchamba','Sotouboua','Amlamé','Kandé','Niamtougou'],
    benin:['Cotonou','Porto-Novo','Abomey-Calavi','Parakou','Djougou','Bohicon','Natitingou','Ouidah','Lokossa','Abomey','Kandi','Malanville','Savalou','Pobè','Allada','Comè','Dassa-Zoumé','Savè','Dogbo','Banikoara'],
    niger:['Niamey','Zinder','Maradi','Agadez','Tahoua','Dosso','Diffa','Tillabéri','Arlit','Birni N’Konni','Gaya','Tessaoua','Dogondoutchi','Madaoua','Magaria','Mirriah','Nguigmi','Téra','Filingué','Ayorou'],
    cameroun:['Douala','Yaoundé','Garoua','Bamenda','Maroua','Bafoussam','Ngaoundéré','Bertoua','Ebolowa','Kribi','Limbé','Buea','Kumba','Edéa','Nkongsamba','Dschang','Foumban','Bafang','Mbalmayo','Sangmélima','Yagoua','Meiganga'],
    nigeria:['Lagos','Abuja','Kano','Ibadan','Port Harcourt','Benin City','Kaduna','Enugu','Aba','Jos','Ilorin','Maiduguri','Warri','Calabar','Onitsha','Owerri','Akure','Abeokuta','Uyo','Sokoto','Bauchi','Minna','Makurdi','Osogbo','Ado-Ekiti'],
    maroc:['Casablanca','Rabat','Marrakech','Fès','Tanger','Agadir','Meknès','Oujda','Kénitra','Tétouan','Safi','El Jadida','Béni Mellal','Nador','Khouribga','Settat','Taza','Larache','Essaouira','Mohammédia'],
    france:['Paris','Marseille','Lyon','Toulouse','Nice','Nantes','Montpellier','Strasbourg','Bordeaux','Lille','Rennes','Reims','Toulon','Saint-Étienne','Le Havre','Grenoble','Dijon','Angers','Nîmes','Villeurbanne','Clermont-Ferrand','Aix-en-Provence'],
    chine:['Beijing','Shanghai','Guangzhou','Shenzhen','Chengdu','Chongqing','Tianjin','Wuhan','Hangzhou','Nanjing','Xi’an','Suzhou','Qingdao','Ningbo','Dongguan','Foshan','Zhengzhou','Changsha','Kunming','Xiamen','Jinan','Harbin','Shenyang'],
    turquie:['Istanbul','Ankara','Izmir','Bursa','Antalya','Adana','Gaziantep','Konya','Mersin','Kayseri','Eskişehir','Diyarbakır','Samsun','Denizli','Şanlıurfa','Malatya','Trabzon','Erzurum','Sakarya','Tekirdağ'],
    emiratsarabesunis:['Abu Dhabi','Dubai','Sharjah','Ajman','Al Ain','Ras Al Khaimah','Fujairah','Umm Al Quwain','Khor Fakkan','Kalba','Jebel Ali'],
    autre:[]
  };

  function countryKey(value){
    return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[’'\-\s]/g,'').toLowerCase();
  }

  var openSelect=null;
  function closeSelect(wrapper){
    if(!wrapper)return;
    wrapper.classList.remove('is-open');
    var btn=wrapper.querySelector('.pf-select__button');
    if(btn)btn.setAttribute('aria-expanded','false');
    if(openSelect===wrapper)openSelect=null;
  }
  function closeAllSelects(except){
    app.querySelectorAll('.pf-select.is-open').forEach(function(w){if(w!==except)closeSelect(w);});
  }
  function optionVisual(option,mode,menu){
    var flag=option.getAttribute('data-flag')||'';
    var dial=option.getAttribute('data-dial')||'';
    var label=option.textContent.trim();
    if(mode==='dial'){
      var country=option.value||label.replace(dial,'').trim();
      return '<span class="pf-select__content">'+(flag?'<img class="pf-select__flag" src="'+flag+'" alt="">':'')+
        '<span class="pf-select__label">'+(menu?country:(dial||country))+'</span>'+
        (menu&&dial?'<span class="pf-select__dial">'+dial+'</span>':'')+'</span>';
    }
    return '<span class="pf-select__content">'+(flag?'<img class="pf-select__flag" src="'+flag+'" alt="">':'')+
      '<span class="pf-select__label">'+label+'</span></span>';
  }
  function mountSelect(select){
    if(select._pfMounted){ if(select._pfRefresh)select._pfRefresh(); return; }
    select._pfMounted=true;
    select.classList.add('pf-native-select');
    var wrapper=document.createElement('div');
    wrapper.className='pf-select';
    var button=document.createElement('button');
    button.type='button';
    button.className='pf-select__button';
    button.setAttribute('aria-haspopup','listbox');
    button.setAttribute('aria-expanded','false');
    var menu=document.createElement('div');
    menu.className='pf-select__menu';
    menu.setAttribute('role','listbox');
    wrapper.appendChild(button); wrapper.appendChild(menu);
    select.insertAdjacentElement('afterend',wrapper);

    function refresh(){
      var mode=select.getAttribute('data-display-mode')||'';
      var selected=select.options[select.selectedIndex]||select.options[0];
      button.innerHTML=(selected?optionVisual(selected,mode,false):'<span class="pf-select__label">Sélectionner</span>')+'<span class="pf-select__chevron" aria-hidden="true"></span>';
      menu.innerHTML='';
      Array.prototype.slice.call(select.options).forEach(function(option,index){
        var item=document.createElement('button');
        item.type='button'; item.className='pf-select__option'; item.setAttribute('role','option');
        item.setAttribute('aria-selected',index===select.selectedIndex?'true':'false');
        item.disabled=!!option.disabled;
        item.innerHTML=optionVisual(option,mode,true);
        if(index===select.selectedIndex)item.classList.add('is-selected');
        item.addEventListener('click',function(e){
          e.preventDefault();
          if(option.disabled)return;
          select.selectedIndex=index;
          refresh();
          closeSelect(wrapper);
          select.dispatchEvent(new Event('change',{bubbles:true}));
        });
        menu.appendChild(item);
      });
    }
    select._pfRefresh=refresh;
    button.addEventListener('click',function(e){
      e.preventDefault(); e.stopPropagation();
      var opening=!wrapper.classList.contains('is-open');
      closeAllSelects(wrapper);
      wrapper.classList.toggle('is-open',opening);
      button.setAttribute('aria-expanded',opening?'true':'false');
      openSelect=opening?wrapper:null;
    });
    select.addEventListener('change',refresh);
    refresh();
  }

  var factoryCountry=app.querySelector('[data-factory-country]');
  var factoryCity=app.querySelector('[data-factory-city]');
  function populateFactoryCities(){
    if(!factoryCountry||!factoryCity)return;
    var current=factoryCity.value||factoryCity.getAttribute('data-current-city')||'';
    var list=cityMap[countryKey(factoryCountry.value)]||[];
    factoryCity.innerHTML='';
    var placeholder=document.createElement('option');
    placeholder.value=''; placeholder.textContent='Sélectionnez une ville';
    factoryCity.appendChild(placeholder);
    list.forEach(function(city){var o=document.createElement('option');o.value=city;o.textContent=city;factoryCity.appendChild(o);});
    if(current && list.indexOf(current)===-1){var existing=document.createElement('option');existing.value=current;existing.textContent=current;factoryCity.appendChild(existing);}
    var other=document.createElement('option');other.value='Autre ville / localité';other.textContent='Autre ville / localité';factoryCity.appendChild(other);
    if(current)factoryCity.value=current; else factoryCity.selectedIndex=0;
    if(factoryCity._pfRefresh)factoryCity._pfRefresh();
  }
  populateFactoryCities();
  app.querySelectorAll('select[data-pf-select]').forEach(mountSelect);
  if(factoryCountry)factoryCountry.addEventListener('change',function(){factoryCity.setAttribute('data-current-city','');populateFactoryCities();});

  document.addEventListener('click',function(e){if(openSelect&&!openSelect.contains(e.target))closeAllSelects();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAllSelects();});

  // Country-code dropdowns for professional phone and WhatsApp.
  var phoneLocal=app.querySelector('[data-phone-local]');
  var phoneHidden=app.querySelector('[data-phone-hidden]');
  var phoneCountry=app.querySelector('[data-phone-country]');
  var waLocal=app.querySelector('[data-wa-local]');
  var waHidden=app.querySelector('[data-wa-hidden]');
  var waCountry=app.querySelector('[data-wa-country]');
  var waSame=app.querySelector('[data-wa-same]');
  function dial(select){var o=select&&select.options[select.selectedIndex];return o?o.getAttribute('data-dial')||'':'';}
  function normalize(local,select){var v=(local&&local.value||'').trim();var d=dial(select);return v?(d?d+' '+v:v):'';}
  function setSelectValue(select,value){
    if(!select)return;
    Array.prototype.slice.call(select.options).some(function(o,i){if(o.value===value){select.selectedIndex=i;return true;}return false;});
    if(select._pfRefresh)select._pfRefresh();
  }
  function syncPhone(){
    if(phoneHidden)phoneHidden.value=normalize(phoneLocal,phoneCountry);
    if(waSame&&waSame.checked&&waLocal){
      waLocal.value=phoneLocal?phoneLocal.value:'';
      if(phoneCountry&&waCountry)setSelectValue(waCountry,phoneCountry.value);
      if(waHidden)waHidden.value=normalize(waLocal,waCountry);
    }
  }
  function syncWa(){if(waHidden)waHidden.value=normalize(waLocal,waCountry);}
  if(phoneLocal){phoneLocal.addEventListener('input',syncPhone);}
  if(phoneCountry){phoneCountry.addEventListener('change',syncPhone);}
  if(waLocal){waLocal.addEventListener('input',syncWa);}
  if(waCountry){waCountry.addEventListener('change',syncWa);}
  if(waSame){
    if(phoneLocal&&waLocal&&phoneLocal.value&&phoneLocal.value===waLocal.value)waSame.checked=true;
    waSame.addEventListener('change',function(){if(waSame.checked&&phoneLocal&&waLocal){waLocal.value=phoneLocal.value;if(phoneCountry&&waCountry)setSelectValue(waCountry,phoneCountry.value);syncWa();}});
  }
  syncPhone(); syncWa();

  // Keep file-drop cards visually informative after selection.
  app.querySelectorAll('.pf-drop input[type=file]').forEach(function(input){
    input.addEventListener('change',function(){
      var label=input.closest('.pf-drop');
      if(!label||!input.files||!input.files.length)return;
      var text=label.querySelector('span:last-child');
      if(text)text.textContent=input.files.length===1?input.files[0].name:(input.files.length+' fichiers sélectionnés');
    });
  });



  /* Multi-select / token fields. Values are kept in the original scalar fields as comma-separated strings. */
  var tokenGroups={};
  function splitTokens(value){return String(value||'').split(/[,;]+/).map(function(v){return v.trim();}).filter(Boolean);}
  function initTokenField(root){
    var hidden=root.querySelector('[data-token-hidden]');
    var button=root.querySelector('[data-token-button]');
    var menu=root.querySelector('[data-token-menu]');
    var chips=root.querySelector('[data-token-chips]');
    if(!hidden||!button||!menu||!chips)return;
    var placeholder=root.getAttribute('data-placeholder')||'Sélectionner...';
    var mirror=root.getAttribute('data-token-mirror')||'';
    var values=splitTokens(hidden.value);
    function emitMirrors(){
      if(!mirror)return;
      (tokenGroups[mirror]||[]).forEach(function(other){if(other!==api)other.set(values,true);});
    }
    function render(silent){
      chips.innerHTML='';
      values.forEach(function(value){
        var opt=menu.querySelector('[data-token-option="'+CSS.escape(value)+'"]');
        var flag=opt&&opt.getAttribute('data-flag');
        var chip=document.createElement('span');chip.className='pf-chip';
        chip.innerHTML=(flag?'<img src="'+flag+'" alt="">':'')+'<span></span><button type="button" aria-label="Supprimer">×</button>';
        chip.querySelector('span').textContent=value;
        chip.querySelector('button').addEventListener('click',function(){values=values.filter(function(v){return v!==value;});render();});
        chips.appendChild(chip);
      });
      Array.prototype.slice.call(menu.querySelectorAll('[data-token-option]')).forEach(function(opt){opt.classList.toggle('is-selected',values.indexOf(opt.getAttribute('data-token-option'))!==-1);});
      hidden.value=values.join(', ');
      var ph=button.querySelector('[data-token-placeholder]');if(ph)ph.textContent=values.length?(values.length+' sélectionné'+(values.length>1?'s':'')):placeholder;
      if(!silent){hidden.dispatchEvent(new Event('change',{bubbles:true}));emitMirrors();}
    }
    var api={set:function(next,silent){values=Array.isArray(next)?next.slice():splitTokens(next);render(!!silent);},get:function(){return values.slice();}};
    if(mirror){(tokenGroups[mirror]||(tokenGroups[mirror]=[])).push(api);}
    button.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();app.querySelectorAll('.pf-tokenfield.is-open').forEach(function(x){if(x!==root)x.classList.remove('is-open');});root.classList.toggle('is-open');});
    Array.prototype.slice.call(menu.querySelectorAll('[data-token-option]')).forEach(function(opt){opt.addEventListener('click',function(e){e.preventDefault();var value=opt.getAttribute('data-token-option');if(values.indexOf(value)===-1)values.push(value);else values=values.filter(function(v){return v!==value;});render();});});
    render(true);
    root._tokenApi=api;
  }
  app.querySelectorAll('[data-token-field]').forEach(initTokenField);
  Object.keys(tokenGroups).forEach(function(key){var group=tokenGroups[key];if(group.length>1){var base=group.reduce(function(found,item){return found&&found.length?found:item.get();},[]);group.forEach(function(item){item.set(base,true);});}});

  document.addEventListener('click',function(e){app.querySelectorAll('.pf-tokenfield.is-open').forEach(function(root){if(!root.contains(e.target))root.classList.remove('is-open');});});

  /* Value + unit fields saved as one string for the existing backend schema. */
  app.querySelectorAll('[data-compose-field]').forEach(function(root){
    var hidden=root.querySelector('[data-compose-hidden]'), value=root.querySelector('[data-compose-value]'), unit=root.querySelector('[data-compose-unit]');
    if(!hidden||!value||!unit)return;
    var raw=(hidden.value||'').trim();
    if(raw){var m=raw.match(/^([0-9.,\s]+)\s*(.*)$/);if(m){value.value=m[1].replace(/\s/g,'');var u=m[2].trim();if(u){Array.prototype.slice.call(unit.options).some(function(o,i){if(o.textContent.toLowerCase()===u.toLowerCase()){unit.selectedIndex=i;if(unit._pfRefresh)unit._pfRefresh();return true;}return false;});}}}
    function sync(){hidden.value=(value.value||'').trim() ? ((value.value||'').trim()+' '+unit.options[unit.selectedIndex].textContent.trim()) : '';}
    value.addEventListener('input',sync);unit.addEventListener('change',sync);sync();
  });

  /* Structured equipment list, persisted in main_equipment as a readable string. */
  var eqHidden=app.querySelector('[data-equipment-hidden]'),eqType=app.querySelector('[data-equipment-type]'),eqBrand=app.querySelector('[data-equipment-brand]'),eqQty=app.querySelector('[data-equipment-qty]'),eqYear=app.querySelector('[data-equipment-year]'),eqAdd=app.querySelector('[data-equipment-add]'),eqChips=app.querySelector('[data-equipment-chips]');
  if(eqHidden&&eqChips){
    var equipments=String(eqHidden.value||'').split(/\s*;\s*|\s*\|\|\s*/).filter(Boolean).map(function(v){return {label:v};});
    function renderEquipment(){eqChips.innerHTML='';equipments.forEach(function(item,index){var chip=document.createElement('span');chip.className='pf-chip';chip.innerHTML='<span></span><button type="button">×</button>';chip.querySelector('span').textContent=item.label;chip.querySelector('button').addEventListener('click',function(){equipments.splice(index,1);renderEquipment();});eqChips.appendChild(chip);});eqHidden.value=equipments.map(function(x){return x.label;}).join('; ');}
    if(eqAdd)eqAdd.addEventListener('click',function(){var type=eqType&&eqType.value;if(!type)return;var parts=[type];if(eqBrand&&eqBrand.value.trim())parts.push(eqBrand.value.trim());if(eqQty&&eqQty.value)parts.push('Qté '+eqQty.value);if(eqYear&&eqYear.value)parts.push(eqYear.value);equipments.push({label:parts.join(' · ')});renderEquipment();eqType.selectedIndex=0;if(eqType._pfRefresh)eqType._pfRefresh();if(eqBrand)eqBrand.value='';if(eqQty)eqQty.value='';if(eqYear){eqYear.selectedIndex=0;if(eqYear._pfRefresh)eqYear._pfRefresh();}});
    renderEquipment();
  }

  /* Browser geolocation for the factory position field. */
  var locateBtn=app.querySelector('[data-locate-factory]'), positionInput=app.querySelector('[data-factory-position]');
  if(locateBtn&&positionInput){locateBtn.addEventListener('click',function(){if(!navigator.geolocation){return;}var original=locateBtn.innerHTML;locateBtn.textContent='Localisation...';navigator.geolocation.getCurrentPosition(function(pos){positionInput.value=pos.coords.latitude.toFixed(6)+', '+pos.coords.longitude.toFixed(6);locateBtn.innerHTML=original;},function(){locateBtn.innerHTML=original;},{enableHighAccuracy:true,timeout:8000});});}

  /* Certification rows: first row keeps legacy field names, extra rows are serialized in certifications_json. */
  var certWrap=app.querySelector('[data-certifications]'),certJson=app.querySelector('[data-certifications-json]'),addCert=app.querySelector('[data-add-certification]');
  function certRows(){return certWrap?Array.prototype.slice.call(certWrap.querySelectorAll('[data-cert-row]')):[];}
  function syncCerts(){if(!certWrap||!certJson)return;certJson.value=JSON.stringify(certRows().map(function(row){var obj={};row.querySelectorAll('[data-cert-key]').forEach(function(el){obj[el.getAttribute('data-cert-key')]=el.value||'';});return obj;}));}
  function wireCertRow(row,index){row.querySelectorAll('[data-cert-key]').forEach(function(el){el.addEventListener('input',syncCerts);el.addEventListener('change',syncCerts);});var del=row.querySelector('[data-remove-certification]');if(del)del.addEventListener('click',function(){if(certRows().length===1){row.querySelectorAll('input:not([type=file]),select').forEach(function(el){el.value='';if(el._pfRefresh)el._pfRefresh();});}else row.remove();syncCerts();});var file=row.querySelector('input[type=file]');if(file)file.addEventListener('change',function(){var n=row.querySelector('[data-file-name]');if(n&&file.files&&file.files[0])n.textContent=file.files[0].name;});}
  if(certWrap){certRows().forEach(wireCertRow);}
  if(addCert&&certWrap){addCert.addEventListener('click',function(){var first=certRows()[0];if(!first)return;var clone=first.cloneNode(true);clone.querySelectorAll('input').forEach(function(el){if(el.type!=='file')el.value='';else el.value='';if(el.name&&!el.name.startsWith('documents['))el.removeAttribute('name');});clone.querySelectorAll('select').forEach(function(el){el.selectedIndex=0;el.removeAttribute('name');el._pfMounted=false;var custom=el.nextElementSibling;if(custom&&custom.classList.contains('pf-select'))custom.remove();mountSelect(el);});var fn=clone.querySelector('[data-file-name]');if(fn)fn.textContent='Ajouter';certWrap.appendChild(clone);wireCertRow(clone,certRows().length-1);syncCerts();});}
  if(certWrap)syncCerts();

  if(form){
    form.addEventListener('submit',function(event){
      var submitter=event.submitter;
      var action=submitter&&submitter.value ? submitter.value : '';
      if(action!=='submit')return; // Le brouillon reste volontairement permissif.

      for(var i=0;i<keys.length;i++){
        if(!validatePanel(keys[i],{focus:false})){
          event.preventDefault();
          setActive(keys[i]);
          validatePanel(keys[i],{focus:true});
          return;
        }
        setTabClientComplete(keys[i],true);
      }
    });
  }

  // Dès qu'un utilisateur corrige un champ en erreur, on retire l'erreur locale.
  if(form){
    form.addEventListener('input',function(event){
      var container=event.target.closest('.is-client-error');
      if(container)clearClientError(container);
      updateNavigationAccess();
    });
    form.addEventListener('change',function(event){
      var container=event.target.closest('.is-client-error');
      if(container)clearClientError(container);
      updateNavigationAccess();
    });
  }

  setActive(activeKey);
})();
