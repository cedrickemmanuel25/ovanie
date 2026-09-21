const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/open-shop.js'), 'utf8');
const handlers = source.slice(source.indexOf('    function optionButtons('), source.indexOf('    function updateOptionSelection('));

function selector(value) {
    const listeners = {}, toggleListeners = {};
    const options = ['Abobo', 'Adjamé', 'Cocody', 'Yopougon'].map(name => {
        const classes = new Set();
        return { dataset: { value: name }, hidden: false,
            classList: { remove: key => classes.delete(key), contains: key => classes.has(key),
                toggle: (key, enabled) => enabled ? classes.add(key) : classes.delete(key) },
            scrollIntoView() {} };
    });
    const empty = { hidden: true };
    const menu = { hidden: true, querySelectorAll: () => options,
        querySelector: () => empty, addEventListener() {} };
    const input = { value, disabled: false, setAttribute() {}, addEventListener: (key, fn) => listeners[key] = fn,
        focus: () => listeners.focus() };
    const toggle = { addEventListener: (key, fn) => toggleListeners[key] = fn };
    const context = { input, menu, toggle, document: { querySelectorAll: () => [menu] },
        normalizeLocalitySearch: text => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() };
    vm.runInNewContext(handlers + '\nbindCombobox({input, menu, toggle, onSelect() {}});', context);
    return { input, menu, empty, listeners, toggleListeners, visible: () => options.filter(o => !o.hidden).map(o => o.dataset.value) };
}

test('opening with stale Abidjan autofill displays the commune catalogue', () => {
    const box = selector('Abidjan');
    box.listeners.focus();
    assert.equal(box.menu.hidden, false);
    assert.equal(box.visible().length, 4);
    assert.equal(box.empty.hidden, true);
});

test('typing still filters communes and reports no matches', () => {
    const box = selector('adjame');
    box.listeners.input();
    assert.deepEqual(box.visible(), ['Adjamé']);
    box.input.value = 'introuvable';
    box.listeners.input();
    assert.deepEqual(box.visible(), []);
    assert.equal(box.empty.hidden, false);
});

test('reopening an already selected commune allows choosing another', () => {
    const box = selector('Cocody');
    box.listeners.input();
    assert.deepEqual(box.visible(), ['Cocody']);
    box.listeners.keydown({ key: 'Escape' });
    box.toggleListeners.click();
    assert.equal(box.visible().length, 4);
    assert.equal(box.menu.hidden, false);
    assert.equal(box.input.value, 'Cocody');
});

test('toggle closes the list even when focusing the input triggers focus', () => {
    const box = selector('Cocody');
    box.listeners.focus();
    box.toggleListeners.click();
    assert.equal(box.menu.hidden, true);
});
