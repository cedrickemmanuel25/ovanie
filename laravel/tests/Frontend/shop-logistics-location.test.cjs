const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/shop-logistics-location.js'), 'utf8');

function page(ready = false, resolve = async () => ({ ok: true, json: async () => ({
    address: 'Nouvelle rue', commune: 'Cocody', district: 'Angré', landmark: '',
    display_name: 'Nouvelle rue, Angré, Cocody', location_token: 'signed-location',
}) })) {
    const elements = {};
    for (const id of ['logistics-location-form', 'capture-shop-gps', 'gps-feedback', 'latitude', 'longitude',
        'geo_accuracy', 'geo_source', 'geo_captured_at', 'confirmation', 'submit', 'location_token',
        'detected-location', 'detected-location-address', 'detected-location-map', 'location-address-status',
        'address', 'commune', 'district', 'landmark', '_token', 'choose-shop-map', 'confirm-map-point', 'shop-map-panel']) {
        elements[id] = { value: '', checked: false, disabled: false, listeners: {},
            addEventListener(event, listener) { this.listeners[event] = listener; } };
    }
    const form = elements['logistics-location-form'];
    form.dataset = { locationReady: ready ? '1' : '0', changing: '1', resolveUrl: '/resolve' };
    form.querySelector = selector => elements[selector.includes('_token') ? '_token' : selector.includes('location_confirmed') ? 'confirmation' : 'submit'];
    let success, error, deadline, options, cleared = 0, confirmed = 0;
    let mapClick, zoom = 11, markers = 0;
    vm.runInNewContext(source, {
        document: { getElementById: id => elements[id] },
        navigator: { geolocation: {
            watchPosition(onSuccess, onError, settings) { success = onSuccess; error = onError; options = settings; return 9; },
            clearWatch(id) { assert.equal(id, 9); cleared++; },
        } },
        window: { addEventListener() {}, confirm() { confirmed++; return true; } },
        setTimeout(callback) { deadline = callback; return 7; }, clearTimeout() {},
        AbortController, fetch: resolve,
        maplibregl: {
            Map: class {
                addControl() {} resize() {} getZoom() { return zoom; }
                flyTo(settings) { zoom = settings.zoom; }
                on(event, callback) { if (event === 'click') mapClick = callback; }
            },
            Marker: class {
                constructor() { markers++; }
                setLngLat() { return this; } addTo() { return this; } remove() {}
            },
            NavigationControl: class {},
        },
    });
    return {
        elements, start: () => elements['capture-shop-gps'].listeners.click(),
        reading: (accuracy, age = 0) => success({ coords: { latitude: 5.37, longitude: -3.98, accuracy }, timestamp: Date.now() - age }),
        denied: () => error({ code: 1 }), timeout: () => deadline(),
        options: () => options, cleared: () => cleared, confirmed: () => confirmed,
        edit: () => form.listeners.input({ target: { name: 'address' } }),
        submit: () => { let blocked = false; form.listeners.submit({ preventDefault() { blocked = true; } }); return blocked; },
        openMap: () => elements['choose-shop-map'].listeners.click(),
        clickMap: () => mapClick({ lngLat: { lat: 5.37, lng: -3.98 } }),
        confirmMap: () => elements['confirm-map-point'].listeners.click(),
        markers: () => markers,
    };
}

test('imprecise readings are not accepted or saved as a shop location', async () => {
    const p = page(); p.start(); p.reading(4000);
    assert.equal(p.elements.latitude.value, '');
    assert.equal(p.elements.submit.disabled, true);
    assert.equal(p.elements.confirmation.disabled, true);
    assert.equal(p.options().maximumAge, 0);
    await p.timeout();
    assert.equal(p.cleared(), 1);
    assert.equal(p.elements['capture-shop-gps'].disabled, false);
    assert.equal(p.submit(), true);
});

test('a later precise fresh reading resolves and replaces the old address before confirmation', async () => {
    const p = page(); p.elements.address.value = 'Ancienne adresse';
    p.start();
    assert.equal(p.elements.address.value, 'Ancienne adresse');
    await p.reading(1000); await p.reading(10);
    assert.equal(p.elements.latitude.value, '5.3700000');
    assert.equal(p.elements.geo_accuracy.value, '10');
    assert.equal(p.elements.geo_source.value, 'browser_gps');
    assert.equal(p.elements.address.value, 'Nouvelle rue');
    assert.equal(p.elements.district.value, 'Angré');
    assert.equal(p.elements.landmark.value, '');
    assert.equal(p.elements.location_token.value, 'signed-location');
    assert.equal(p.elements['detected-location'].hidden, false);
    assert.equal(p.elements.submit.disabled, false);
    assert.equal(p.cleared(), 1);
    assert.equal(p.submit(), true);
    p.elements.confirmation.checked = true;
    assert.equal(p.submit(), false);
    assert.equal(p.confirmed(), 1);
    assert.doesNotMatch(p.elements['gps-feedback'].textContent, /5\.37|-3\.98/);
});

test('a geocoding failure cannot validate or restore the opening address', async () => {
    const p = page(false, async () => ({ ok: false, json: async () => ({ message: 'Adresse non identifiée' }) }));
    p.elements.address.value = 'Ancienne adresse';
    p.start(); await p.reading(10);
    assert.equal(p.elements.address.value, 'Ancienne adresse');
    assert.equal(p.elements.submit.disabled, true);
    assert.equal(p.elements.location_token.value, '');
    assert.equal(p.submit(), true);
});

test('late reverse geocoding cannot overwrite a newer address edit', async () => {
    let finish;
    const response = new Promise(resolve => { finish = resolve; });
    const p = page(false, () => response);
    p.start(); const pending = p.reading(10);
    p.edit(); p.elements.address.value = 'Nouvelle saisie';
    finish({ ok: true, json: async () => ({ address: 'Réponse ancienne', commune: 'Cocody', location_token: 'token' }) });
    await pending;
    assert.equal(p.elements.address.value, 'Nouvelle saisie');
    assert.equal(p.elements.submit.disabled, true);
});

test('stale and missing precision readings are ignored', () => {
    const p = page(); p.start();
    p.reading(8, 60000);
    assert.equal(p.elements.latitude.value, '');
    p.reading(0); p.reading(NaN);
    assert.equal(p.elements.submit.disabled, true);
});

test('address edits invalidate a captured location and cancel a pending watch', () => {
    const p = page(); p.start(); p.edit();
    assert.equal(p.cleared(), 1);
    p.reading(8);
    assert.equal(p.elements.latitude.value, '');
    assert.equal(p.submit(), true);
});

test('permission denial cannot leave the previous position eligible for submission', () => {
    const p = page(true);
    assert.equal(p.elements.submit.disabled, false);
    p.start(); p.denied();
    assert.equal(p.elements.submit.disabled, true);
    assert.equal(p.cleared(), 1);
    assert.equal(p.elements.geo_captured_at.value, '');
});

test('approximate position fills indicative fields but cannot activate logistics', async () => {
    const p = page(false, async () => ({ ok: true, json: async () => ({
        address: 'Adresse indicative', commune: 'Cocody', location_token: null,
    }) }));
    p.start(); await p.reading(1000); await p.timeout();
    assert.equal(p.elements.address.value, 'Adresse indicative');
    assert.equal(p.elements.location_token.value, '');
    assert.equal(p.elements.submit.disabled, true);
    assert.match(p.elements['gps-feedback'].textContent, /indicatif/);
    assert.equal(p.submit(), true);
});

test('manual placement control and dependency are removed', () => {
    const view = fs.readFileSync(path.join(__dirname, '../../resources/views/vendor/delivery/location.blade.php'), 'utf8');
    assert.doesNotMatch(view, /choose-shop-map|shop-map-panel|maplibre/);
    assert.doesNotMatch(source, /chooseMap|maplibregl/);
});
