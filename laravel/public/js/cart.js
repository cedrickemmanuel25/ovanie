document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.ov-cart-page');
    if (!root) return;

    const rows = () => Array.from(document.querySelectorAll('[data-row]'));
    const selectAll = document.getElementById('selectAllCheckbox');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    const titleCountEl = document.getElementById('ovCartTitleCount');
    const summaryItemCountEl = document.getElementById('summaryItemCount');
    const checkoutBtn = document.querySelector('.checkout-btn');
    const checkoutSelectionForm = document.getElementById('checkoutSelectionForm');
    const checkoutSelectionInputs = document.getElementById('checkoutSelectionInputs');
    const messagesBox = document.getElementById('cart-messages');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function refreshLucide() {
        if (window.lucide) window.lucide.createIcons();
    }

    function formatPrice(amount) {
        return `${Number(amount || 0).toLocaleString('fr-FR')} FCFA`;
    }

    function showMessage(message, type = 'warning') {
        if (!messagesBox) return;
        messagesBox.innerHTML = `<div class="ov-alert ov-alert--${type}">${escapeHtml(message)}</div>`;
        window.setTimeout(() => {
            messagesBox.innerHTML = '';
        }, 3200);
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function getRowQuantity(row) {
        const input = row.querySelector('.quantity-input');
        return Math.max(1, parseInt(input?.value || row.dataset.quantity || '1', 10) || 1);
    }

    function updateRowTotal(row) {
        const price = Number(row.dataset.price || 0);
        const qty = getRowQuantity(row);
        const total = price * qty;
        const totalEl = row.querySelector('.line-total');
        if (totalEl) totalEl.textContent = formatPrice(total);
        row.dataset.quantity = String(qty);
        return total;
    }

    function getSelectedRows() {
        return rows().filter(row => row.querySelector('.cart-item-checkbox')?.checked);
    }

    function updateTotalsFromDom() {
        let subtotal = 0;
        let selectedQty = 0;
        let totalQty = 0;

        rows().forEach(row => {
            const qty = getRowQuantity(row);
            totalQty += qty;
            const rowTotal = updateRowTotal(row);
            if (row.querySelector('.cart-item-checkbox')?.checked) {
                subtotal += rowTotal;
                selectedQty += qty;
            }
        });

        if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal);
        if (totalEl) totalEl.textContent = formatPrice(subtotal);

        const countText = `${selectedQty} article${selectedQty > 1 ? 's' : ''}`;
        if (summaryItemCountEl) summaryItemCountEl.textContent = `(${countText})`;
        if (titleCountEl) titleCountEl.textContent = `(${totalQty} article${totalQty > 1 ? 's' : '' })`;

        const allChecked = rows().length > 0 && rows().every(row => row.querySelector('.cart-item-checkbox')?.checked);
        if (selectAll) selectAll.checked = allChecked;

        if (checkoutBtn) {
            checkoutBtn.classList.toggle('is-disabled', selectedQty === 0);
            const btnDesktop = checkoutBtn.querySelector('.checkout-btn-text-desktop');
            const btnMobile = checkoutBtn.querySelector('.checkout-btn-text-mobile');
            if (btnDesktop) btnDesktop.textContent = 'Passer au paiement';
            if (btnMobile) btnMobile.textContent = `Passer au paiement (${selectedQty})`; 
        }

        updateDeleteSelectedLabel(selectedQty);
        updateCartCountBadge(totalQty);
    }

    function updateCartCountBadge(count) {
        document.querySelectorAll('#cart-count, [data-cart-count], .cart-count').forEach(el => {
            el.textContent = String(count);
            el.dataset.count = String(count);
        });
    }

    function updateDeleteSelectedLabel(count) {
        if (!deleteSelectedBtn) return;
        const span = deleteSelectedBtn.querySelector('span');
        if (span) span.textContent = `(${count})`;
    }

    async function updateQuantityOnServer(productId, quantity) {
        const response = await fetch(`/cart/update/${productId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ quantity }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Impossible de mettre à jour la quantité.');
        }

        return data;
    }

    async function changeQuantity(input, delta) {
        const row = input.closest('[data-row]');
        if (!row) return;

        const stock = parseInt(input.dataset.stock || row.dataset.stock || '0', 10) || 0;
        let current = parseInt(input.value || '1', 10) || 1;
        let next = Math.max(1, current + delta);

        if (stock > 0 && next > stock) {
            next = stock;
            showMessage(`Stock limité : maximum ${stock} unité(s).`);
        }

        input.value = next;
        updateTotalsFromDom();

        const productId = row.dataset.productId;
        if (!productId) return;

        row.classList.add('is-updating');
        try {
            const data = await updateQuantityOnServer(productId, next);
            if (data.item_quantity) {
                input.value = data.item_quantity;
            }
            updateTotalsFromDom();
        } catch (error) {
            input.value = current;
            updateTotalsFromDom();
            showMessage(error.message || 'Erreur lors de la mise à jour du panier.');
        } finally {
            row.classList.remove('is-updating');
        }
    }

    async function removeRow(row) {
        const productId = row.dataset.productId;
        if (!productId) return;

        row.classList.add('is-removing');

        const response = await fetch(`/cart/remove/${productId}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            row.classList.remove('is-removing');
            throw new Error(data.message || 'Impossible de supprimer le produit.');
        }

        row.remove();
        updateTotalsFromDom();

        if (!rows().length) {
            window.location.reload();
        }
    }

    document.addEventListener('click', async event => {
        const minus = event.target.closest('[data-qty-minus]');
        const plus = event.target.closest('[data-qty-plus]');
        const removeBtn = event.target.closest('[data-remove-item]');

        if (minus || plus) {
            event.preventDefault();
            const input = event.target.closest('.ov-qty-stepper')?.querySelector('.quantity-input');
            if (!input) return;
            await changeQuantity(input, plus ? 1 : -1);
            return;
        }

        if (removeBtn) {
            event.preventDefault();
            const row = removeBtn.closest('[data-row]');
            if (!row) return;
            try {
                await removeRow(row);
                showMessage('Produit supprimé du panier.', 'success');
            } catch (error) {
                showMessage(error.message || 'Impossible de supprimer le produit.');
            }
        }
    });

    document.addEventListener('change', async event => {
        if (event.target.classList.contains('cart-item-checkbox')) {
            updateTotalsFromDom();
            return;
        }

        if (event.target.classList.contains('quantity-input')) {
            const input = event.target;
            let value = parseInt(input.value || '1', 10) || 1;
            const stock = parseInt(input.dataset.stock || '0', 10) || 0;
            if (value < 1) value = 1;
            if (stock > 0 && value > stock) value = stock;
            input.value = value;
            await changeQuantity(input, 0);
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            rows().forEach(row => {
                const checkbox = row.querySelector('.cart-item-checkbox');
                if (checkbox) checkbox.checked = selectAll.checked;
            });
            updateTotalsFromDom();
        });
    }

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', async () => {
            const selected = getSelectedRows();
            if (!selected.length) {
                showMessage('Aucun article sélectionné.');
                return;
            }

            if (!window.confirm(`Supprimer ${selected.length} article(s) sélectionné(s) ?`)) return;

            deleteSelectedBtn.disabled = true;
            try {
                for (const row of selected) {
                    await removeRow(row);
                }
                showMessage('Sélection supprimée du panier.', 'success');
            } catch (error) {
                showMessage(error.message || 'Suppression interrompue.');
            } finally {
                deleteSelectedBtn.disabled = false;
            }
        });
    }

    if (checkoutSelectionForm) {
        checkoutSelectionForm.addEventListener('submit', event => {
            const selected = getSelectedRows();

            if (!selected.length) {
                event.preventDefault();
                showMessage('Selectionnez au moins un produit pour passer commande.');
                return;
            }

            if (checkoutSelectionInputs) {
                checkoutSelectionInputs.innerHTML = '';
                selected.forEach(row => {
                    const itemId = row.dataset.itemId;
                    if (!itemId) return;

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'cart_item_ids[]';
                    input.value = itemId;
                    checkoutSelectionInputs.appendChild(input);
                });
            }
        });
    }

    const similarTrack = document.getElementById('similarTrack');
    const simPrev = document.getElementById('simPrevBtn');
    const simNext = document.getElementById('simNextBtn');

    if (similarTrack && simPrev && simNext) {
        const scrollStep = () => Math.max(180, Math.floor(similarTrack.clientWidth * 0.72));

        function updateSimButtons() {
            simPrev.disabled = similarTrack.scrollLeft <= 2;
            simNext.disabled = similarTrack.scrollLeft + similarTrack.clientWidth >= similarTrack.scrollWidth - 2;
        }

        simPrev.addEventListener('click', () => {
            similarTrack.scrollBy({ left: -scrollStep(), behavior: 'smooth' });
        });

        simNext.addEventListener('click', () => {
            similarTrack.scrollBy({ left: scrollStep(), behavior: 'smooth' });
        });

        similarTrack.addEventListener('scroll', updateSimButtons, { passive: true });
        window.addEventListener('resize', updateSimButtons);
        updateSimButtons();
    }

    updateTotalsFromDom();
    refreshLucide();
});
