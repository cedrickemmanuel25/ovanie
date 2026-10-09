(() => {
  const cfg = window.OVANIE_VENDOR_CONFIGURATION || {};
  const form = document.getElementById('vscLogisticsForm');
  if (!form) return;

  const panel = document.getElementById('vscOvaniePanel');
  const status = document.getElementById('vscLocationStatus');
  const retry = document.getElementById('vscRetryLocation');
  const submit = document.getElementById('vscLogisticsSubmit');
  const radios = [...form.querySelectorAll('input[name="logistics_type"]')];
  const fields = {
    address: document.getElementById('vscAddress'), commune: document.getElementById('vscCommune'),
    district: document.getElementById('vscDistrict'), landmark: document.getElementById('vscLandmark'),
    lat: document.getElementById('vscLatitude'), lng: document.getElementById('vscLongitude'),
    accuracy: document.getElementById('vscAccuracy'), capturedAt: document.getElementById('vscCapturedAt')
  };

  const selected = () => radios.find(r => r.checked)?.value || '';
  const setStatus = (type, title, detail) => {
    status?.classList.remove('ready','error');
    if (type) status?.classList.add(type);
    const strong = status?.querySelector('strong');
    const small = status?.querySelector('small');
    if (strong) strong.textContent = title;
    if (small) small.textContent = detail;
  };

  async function resolvePosition(pos) {
    const latitude = pos.coords.latitude;
    const longitude = pos.coords.longitude;
    const accuracy = Math.max(1, pos.coords.accuracy || 9999);
    const capturedAt = new Date().toISOString();
    fields.lat.value = latitude;
    fields.lng.value = longitude;
    fields.accuracy.value = accuracy;
    fields.capturedAt.value = capturedAt;

    setStatus('', 'Position détectée, identification de l’adresse…', 'OVANIE traite la localisation en arrière-plan.');
    try {
      const body = new FormData();
      body.append('preview_only','1'); body.append('latitude',latitude); body.append('longitude',longitude);
      body.append('geo_source','browser_gps'); body.append('geo_accuracy',accuracy); body.append('geo_captured_at',capturedAt);
      const res = await fetch(cfg.resolveUrl, {method:'POST', headers:{'X-CSRF-TOKEN':cfg.csrf,'Accept':'application/json'}, body});
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Adresse non identifiée');
      fields.address.value = data.address || fields.address.value;
      fields.commune.value = data.commune || fields.commune.value;
      fields.district.value = data.district || fields.district.value;
      fields.landmark.value = data.landmark || fields.landmark.value;
      setStatus('ready','Position de la boutique enregistrée','Les coordonnées GPS restent en arrière-plan. Vérifiez simplement l’adresse détectée ci-dessous.');
    } catch (e) {
      setStatus('error','Position reçue mais adresse non identifiée',e.message || 'Complétez l’adresse puis réessayez la détection.');
    }
  }

  function detect() {
    if (!navigator.geolocation) {
      setStatus('error','Localisation indisponible','Votre navigateur ne permet pas la détection de position. Utilisez de préférence votre téléphone à la boutique.');
      return;
    }
    setStatus('', 'Détection de votre position…', 'Autorisez la localisation lorsque votre navigateur le demande.');
    navigator.geolocation.getCurrentPosition(resolvePosition, err => {
      const message = err.code === 1 ? 'Autorisez la localisation dans votre navigateur puis réessayez.' : 'La position n’a pas pu être détectée. Réessayez depuis la boutique.';
      setStatus('error','Localisation non disponible',message);
    }, {enableHighAccuracy:true, timeout:15000, maximumAge:0});
  }

  function sync() {
    const isOvanie = selected() === 'ovanie';
    if (panel) panel.hidden = !isOvanie;
    if (isOvanie && !fields.lat.value) detect();
    // Sans choix, le bouton ne présume pas d'OVANIE Logistics.
    if (submit) submit.textContent = selected() === 'seller' ? 'Configurer mes zones et tarifs' : (isOvanie ? 'Enregistrer mon point de retrait' : 'Continuer');
  }
  radios.forEach(r => r.addEventListener('change', sync));
  retry?.addEventListener('click', detect);
  sync();
})();
