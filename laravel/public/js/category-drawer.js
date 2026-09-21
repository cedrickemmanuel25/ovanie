document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('mobileSideMenu');
    if (!drawer) return;

    const accordions = Array.from(drawer.querySelectorAll('[data-category-accordion]'));

    const setAccordionState = (accordion, open) => {
        const trigger = accordion.querySelector('[data-category-trigger]');
        const panel = accordion.querySelector('[data-category-panel]');

        accordion.classList.toggle('is-open', open);
        trigger?.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel?.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    accordions.forEach((accordion) => {
        const trigger = accordion.querySelector('[data-category-trigger]');
        if (!trigger) return;

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const willOpen = !accordion.classList.contains('is-open');

            accordions.forEach((item) => {
                if (item !== accordion) setAccordionState(item, false);
            });

            setAccordionState(accordion, willOpen);

            if (willOpen) {
                requestAnimationFrame(() => {
                    accordion.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                });
            }
        });
    });

    drawer.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            accordions.forEach((accordion) => setAccordionState(accordion, false));
        });
    });
});
