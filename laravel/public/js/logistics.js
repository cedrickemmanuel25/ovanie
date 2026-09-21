document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sidebar-toggle]').forEach(button => {
        button.addEventListener('click', () => document.querySelector('[data-sidebar]')?.classList.toggle('open'));
    });

    const updateSelected = () => {
        const count = document.querySelectorAll('[data-row-check]:checked').length;
        document.querySelectorAll('[data-selected-count]').forEach(el => {
            el.textContent = `${count} sélectionnée${count > 1 ? 's' : ''}`;
            el.closest('.lg-bulk')?.classList.toggle('show', count > 0);
        });
    };

    document.querySelector('[data-check-all]')?.addEventListener('change', event => {
        document.querySelectorAll('[data-row-check]').forEach(input => input.checked = event.target.checked);
        updateSelected();
    });
    document.querySelectorAll('[data-row-check]').forEach(input => input.addEventListener('change', updateSelected));


    setTimeout(() => document.querySelector('.lg-toast')?.remove(), 4500);

});
