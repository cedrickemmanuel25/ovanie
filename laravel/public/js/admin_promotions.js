(() => {
    const type = document.getElementById('type');
    const unit = document.getElementById('promotionValueUnit');
    const typeSummary = document.getElementById('promotionTypeSummary');
    const search = document.getElementById('promotionProductSearch');
    const grid = document.getElementById('promotionProductGrid');
    const count = document.getElementById('selectedProductsCount');
    const summaryCount = document.getElementById('promotionProductsSummary');
    const selectVisible = document.getElementById('selectVisibleProducts');
    const clearSelection = document.getElementById('clearProductSelection');

    const options = () => Array.from(grid?.querySelectorAll('.promo-product-option') ?? []);
    const checkboxes = () => Array.from(grid?.querySelectorAll('input[type="checkbox"]') ?? []);

    const updateType = () => {
        const fixed = type?.value === 'fixed';
        if (unit) unit.textContent = fixed ? 'FCFA' : '%';
        if (typeSummary) typeSummary.textContent = fixed ? 'Montant fixe' : 'Pourcentage';
    };

    const updateCount = () => {
        const selected = checkboxes().filter((checkbox) => checkbox.checked).length;
        if (count) count.textContent = String(selected);
        if (summaryCount) summaryCount.textContent = String(selected);
    };

    const filterProducts = () => {
        const term = (search?.value ?? '').trim().toLocaleLowerCase('fr');
        options().forEach((option) => {
            const visible = !term || (option.dataset.search ?? '').includes(term);
            option.hidden = !visible;
        });
    };

    type?.addEventListener('change', updateType);
    search?.addEventListener('input', filterProducts);
    grid?.addEventListener('change', updateCount);

    selectVisible?.addEventListener('click', () => {
        options().filter((option) => !option.hidden).forEach((option) => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = true;
        });
        updateCount();
    });

    clearSelection?.addEventListener('click', () => {
        checkboxes().forEach((checkbox) => { checkbox.checked = false; });
        updateCount();
    });

    updateType();
    updateCount();
})();
