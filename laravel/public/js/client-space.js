document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
    };
    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('open');
    });
    overlay?.addEventListener('click', closeSidebar);

    const account = document.querySelector('[data-account-menu]');
    const accountToggle = account?.querySelector('[data-account-toggle]');
    const accountDropdown = account?.querySelector('[data-account-dropdown]');
    const closeAccount = () => {
        if (!accountDropdown || !accountToggle) return;
        accountDropdown.hidden = true;
        account.classList.remove('open');
        accountToggle.setAttribute('aria-expanded', 'false');
    };
    accountToggle?.addEventListener('click', event => {
        event.stopPropagation();
        const willOpen = accountDropdown.hidden;
        accountDropdown.hidden = !willOpen;
        account.classList.toggle('open', willOpen);
        accountToggle.setAttribute('aria-expanded', String(willOpen));
    });
    document.addEventListener('click', event => {
        if (account && !account.contains(event.target)) closeAccount();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeAccount();
    });

    document.querySelectorAll('[data-modal-open]').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.modalOpen);
            modal?.classList.add('open');
            modal?.setAttribute('aria-hidden', 'false');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.cs-modal');
            modal?.classList.remove('open');
            modal?.setAttribute('aria-hidden', 'true');
        });
    });
    document.querySelectorAll('.cs-modal').forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) modal.classList.remove('open');
        });
    });

    document.querySelectorAll('[data-fill-address]').forEach(button => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-address-form]');
            if (!form) return;
            const address = JSON.parse(button.dataset.fillAddress);
            form.action = `/client/addresses/${address.id}`;
            form.querySelector('[name="_method"]').value = 'PATCH';
            ['type','recipient_name','label','city','commune','quartier','phone','address'].forEach(name => {
                if (form.elements[name]) form.elements[name].value = address[name] ?? '';
            });
            form.elements.is_default.checked = Boolean(address.is_default);
        });
    });

    document.querySelectorAll('[data-tabs]').forEach(tabs => {
        const buttons = tabs.querySelectorAll('[data-tab]');
        const panels = tabs.querySelectorAll('[data-tab-panel]');
        buttons.forEach(button => button.addEventListener('click', () => {
            buttons.forEach(item => item.classList.toggle('active', item === button));
            panels.forEach(panel => panel.hidden = panel.dataset.tabPanel !== button.dataset.tab);
        }));
    });
});
