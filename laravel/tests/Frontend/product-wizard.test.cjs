const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/vendor/products/partials/ovanie-product-wizard.blade.php'), 'utf8');
const handler = source.slice(source.indexOf('    let submitting = false;'), source.indexOf('    function syncCategoryFilters()')).replaceAll('@json($isEdit)', 'false');

async function submit({ status = 201, payload = { redirect: '/vendeur/products' }, invalidJson = false, networkError = false, draft = false } = {}) {
    let listener, message, step, redirect, options;
    const buttons = [{ disabled: false }];
    const photo = { name: 'images[]', files: ['photo'], type: 'file', classList: { add() {} }, closest(selector) {
        return selector === '[data-panel]' ? { dataset: { panel: '5' } } : { classList: { add() {} } };
    } };
    const form = { action: 'https://ovanie.com/vendeur/products', elements: [photo], addEventListener(_, fn) { listener = fn; } };
    vm.runInNewContext(handler, {
        form, FormData: class { set(key, value) { this[key] = value; } },
        qs: () => ({ disabled: true }), qsa: () => buttons, validateStep: () => true,
        hideError() {}, showError(value) { message = value; }, goToStep(value) { step = value; },
        console: { error() {} }, window: { location: { assign(value) { redirect = value; } } },
        fetch: async (_, value) => {
            options = value;
            if (networkError) throw new TypeError('Failed to fetch');
            return { status, ok: status < 300, json: async () => {
                if (invalidJson) throw new SyntaxError('Invalid JSON');
                return payload;
            } };
        },
    });
    await listener({ submitter: draft ? { name: 'save_as_draft' } : {}, preventDefault() {} });
    assert.equal(buttons[0].disabled, false);
    assert.deepEqual(photo.files, ['photo']);
    return { message, step, redirect, options };
}

test('publication redirects and sends the session cookie', async () => {
    const result = await submit();
    assert.equal(result.redirect, '/vendeur/products');
    assert.equal(result.options.credentials, 'same-origin');
});
test('validation preserves images and opens their step', async () => {
    const result = await submit({ status: 422, payload: { errors: { 'images.0': ['Image trop petite'] } } });
    assert.equal(result.step, 5);
    assert.equal(result.message, 'Image trop petite');
});
test('invalid JSON is not reported as a network failure', async () => {
    const result = await submit({ invalidJson: true });
    assert.match(result.message, /réponse illisible/);
    assert.doesNotMatch(result.message, /Connexion interrompue/);
});
test('HTML upload and session errors keep their actual meaning', async () => {
    assert.match((await submit({ status: 413, invalidJson: true })).message, /volumineux/);
    assert.match((await submit({ status: 419, invalidJson: true })).message, /session a expiré/);
});
test('network failure preserves the form and draft submission is supported', async () => {
    assert.match((await submit({ networkError: true })).message, /Connexion interrompue/);
    assert.equal((await submit({ draft: true })).options.body.save_as_draft, '1');
});
