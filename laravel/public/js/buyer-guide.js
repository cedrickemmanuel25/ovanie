document.addEventListener('DOMContentLoaded', () => {
    const links = Array.from(document.querySelectorAll('.buyer-guide-summary a'));
    const sections = Array.from(document.querySelectorAll('[data-guide-section]'));

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if ('IntersectionObserver' in window && sections.length) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible) return;
            const id = visible.target.id;

            links.forEach((link) => {
                link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
            });
        }, {
            rootMargin: '-20% 0px -65% 0px',
            threshold: [0.05, 0.2, 0.5],
        });

        sections.forEach((section) => observer.observe(section));
    }

    window.lucide?.createIcons();
});
