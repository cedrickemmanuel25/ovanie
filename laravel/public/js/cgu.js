document.addEventListener('DOMContentLoaded', () => {
    const articleSearch = document.getElementById('cguArticleSearch');
    const documentSearch = document.getElementById('cguDocumentSearch');
    const links = Array.from(document.querySelectorAll('[data-article-link]'));
    const articles = Array.from(document.querySelectorAll('.cgu-article'));
    const noResults = document.getElementById('cguNoResults');

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    articleSearch?.addEventListener('input', () => {
        const query = normalize(articleSearch.value);
        links.forEach((link) => {
            link.classList.toggle('is-filtered-out', query !== '' && !normalize(link.textContent).includes(query));
        });
    });

    const clearHighlights = () => {
        document.querySelectorAll('mark.cgu-highlight').forEach((mark) => {
            mark.replaceWith(document.createTextNode(mark.textContent || ''));
        });
    };

    const highlightText = (root, query) => {
        if (!query) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                if (!node.nodeValue?.trim()) return NodeFilter.FILTER_REJECT;
                if (node.parentElement?.closest('mark.cgu-highlight')) return NodeFilter.FILTER_REJECT;
                return normalize(node.nodeValue).includes(query) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });

        const nodes = [];
        let node;
        while ((node = walker.nextNode())) nodes.push(node);

        nodes.forEach((textNode) => {
            const raw = textNode.nodeValue || '';
            const normalizedRaw = normalize(raw);
            const index = normalizedRaw.indexOf(query);
            if (index < 0) return;

            const before = raw.slice(0, index);
            const matched = raw.slice(index, index + query.length);
            const after = raw.slice(index + query.length);
            const fragment = document.createDocumentFragment();
            if (before) fragment.appendChild(document.createTextNode(before));
            const mark = document.createElement('mark');
            mark.className = 'cgu-highlight';
            mark.textContent = matched;
            fragment.appendChild(mark);
            if (after) fragment.appendChild(document.createTextNode(after));
            textNode.replaceWith(fragment);
        });
    };

    documentSearch?.addEventListener('input', () => {
        const query = normalize(documentSearch.value);
        clearHighlights();

        let visibleCount = 0;
        articles.forEach((article) => {
            const matches = query === '' || normalize(article.textContent).includes(query);
            article.classList.toggle('is-search-hidden', !matches);
            if (matches) {
                visibleCount += 1;
                highlightText(article, query);
            }
        });

        if (noResults) noResults.hidden = visibleCount > 0;
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (!visible) return;
            links.forEach((link) => {
                link.classList.toggle('is-active', link.dataset.articleLink === visible.target.id);
            });
        }, {
            rootMargin: '-20% 0px -65% 0px',
            threshold: [0.05, 0.2, 0.5]
        });

        articles.forEach((article) => observer.observe(article));
    }

    window.lucide?.createIcons();
});
