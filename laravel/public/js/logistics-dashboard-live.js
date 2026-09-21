(() => {
    'use strict';
    const url = window.OVANIE_DASHBOARD_LIVE_URL;
    if (!url) return;
    let running = false;

    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const setText = (selector, value) => { const el = document.querySelector(selector); if (el) el.textContent = String(value); };

    const updateKpi = count => {
        document.querySelectorAll('[data-live-kpis] .ops-kpi').forEach(card => {
            if (!card.textContent.includes('Livreurs actifs')) return;
            const strong = card.querySelector('strong');
            if (strong) strong.textContent = String(count);
        });
    };

    const updateNetwork = counts => {
        document.querySelectorAll('[data-live-network]').forEach(row => {
            const key = (row.dataset.liveNetwork || '').toLowerCase();
            const strong = row.querySelector('strong');
            if (!strong) return;
            if (key.includes('occup')) strong.textContent = String(counts.busy ?? 0);
            else if (key.includes('dispon')) strong.textContent = String(counts.available ?? 0);
            else if (key.includes('hors')) strong.textContent = String(counts.offline ?? 0);
        });
    };

    const renderDrivers = drivers => {
        const host = document.querySelector('[data-live-driver-list]');
        if (!host) return;
        if (!drivers.length) {
            host.innerHTML = '<p class="ops-empty">Aucun livreur actif.</p>';
            return;
        }
        host.innerHTML = drivers.map(driver => {
            const name = esc(driver.name || 'Livreur');
            const zone = esc(driver.zone || '—');
            const vehicle = esc(driver.vehicle || 'Véhicule');
            const plate = esc(driver.vehiclePlate || '');
            const color = esc(driver.vehicleColor || '');
            const online = driver.online === true;
            const availability = esc(driver.availability || 'Indisponible');
            const photo = esc(driver.vehiclePhotoUrl || driver.vehicleReferenceAssetUrl || '');
            return `<div>
                ${photo ? `<img class="ops-dashboard-driver-vehicle-photo" src="${photo}" alt="${vehicle}">` : '<span class="ops-driver-initials">'+name.slice(0,2).toUpperCase()+'</span>'}
                <div><strong>${name}</strong><small>${zone}</small></div>
                <span>${vehicle}${color || plate ? `<small style="display:block">${color}${color && plate ? ' · ' : ''}${plate}</small>` : ''}</span>
                <span class="ops-badge ${online ? 'is-green' : ''}">${online ? availability : 'Hors ligne'}</span>
            </div>`;
        }).join('');
    };

    async function refresh() {
        if (running || document.hidden) return;
        running = true;
        try {
            const response = await fetch(url, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', cache:'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            const counts = data.counts || {};
            setText('[data-live-online]', counts.online ?? 0);
            setText('[data-home-live-online]', counts.online ?? 0);
            updateKpi(counts.online ?? 0);
            updateNetwork(counts);
            renderDrivers(Array.isArray(data.drivers) ? data.drivers : []);
            const sync = document.querySelector('[data-home-live-sync]');
            if (sync) sync.textContent = `Actualisé à ${new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})}`;
            const legend = document.querySelector('[data-home-live-legend]');
            if (legend) {
                const parts = [];
                if ((counts.available ?? 0) > 0) parts.push(`<span><i class="is-available"></i>${counts.available} disponible(s)</span>`);
                if ((counts.busy ?? 0) > 0) parts.push(`<span><i class="is-mission"></i>${counts.busy} en mission</span>`);
                if ((counts.unavailable ?? 0) > 0) parts.push(`<span><i class="is-unavailable"></i>${counts.unavailable} indisponible(s)</span>`);
                legend.innerHTML = parts.join('') + Array.from(legend.querySelectorAll('span')).filter(el => el.textContent.includes('boutique')).map(el => el.outerHTML).join('');
            }
            window.dispatchEvent(new CustomEvent('ovanie:dashboard-live', {detail: data}));
        } catch (_) {
            // La dernière vue valide reste affichée; le prochain cycle réessaiera.
        } finally {
            running = false;
        }
    }

    refresh();
    setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
