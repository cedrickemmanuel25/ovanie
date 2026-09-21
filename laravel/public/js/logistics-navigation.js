(() => {
    const one = s => document.querySelector(s);
    const all = s => [...document.querySelectorAll(s)];

    one('[data-nav-toggle]')?.addEventListener('click', event => {
        const nav = one('.ops-navigation');
        if (!nav) return;
        const open = nav.classList.toggle('is-open');
        event.currentTarget.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', event => {
        all('.ops-nav-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
        all('.ops-account-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        all('.ops-nav-menu[open], .ops-account-menu[open]').forEach(menu => menu.open = false);
    });
})();
