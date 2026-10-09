document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-ops-config]');
    if (!root) return;

    const radios = [...root.querySelectorAll('input[name="logistics_type"]')];
    const cards = [...root.querySelectorAll('[data-logistics-choice]')];
    const panels = [...root.querySelectorAll('[data-panel]')];

    const refresh = () => {
        const selected = radios.find((radio) => radio.checked)?.value || 'ovanie';
        cards.forEach((card) => card.classList.toggle('is-selected', card.dataset.logisticsChoice === selected));
        panels.forEach((panel) => panel.classList.toggle('is-visible', panel.dataset.panel === selected));
    };

    radios.forEach((radio) => radio.addEventListener('change', refresh));
    refresh();
});
