(() => {
    'use strict';

    const STYLE_ID = 'ovanie-catalog-adaptive-image-style';
    const MEDIA_SELECTOR = '.ovcat-product-media';
    const EXTREME_RATIO = 0.82;

    function installStyles() {
        if (document.getElementById(STYLE_ID)) return;

        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
            ${MEDIA_SELECTOR} {
                position: relative !important;
                display: grid !important;
                place-items: center !important;
                width: 100% !important;
                aspect-ratio: 1 / 1 !important;
                overflow: hidden !important;
                isolation: isolate !important;
                background: #f6f8fb !important;
            }

            ${MEDIA_SELECTOR} > img.ovcat-product-image-main {
                position: absolute !important;
                inset: 0 !important;
                z-index: 2 !important;
                display: block !important;
                width: 100% !important;
                height: 100% !important;
                padding: 8px !important;
                object-fit: contain !important;
                object-position: center !important;
                background: transparent !important;
            }

            ${MEDIA_SELECTOR} > img.ovcat-product-image-backdrop {
                position: absolute !important;
                inset: -22px !important;
                z-index: 0 !important;
                display: none !important;
                width: calc(100% + 44px) !important;
                height: calc(100% + 44px) !important;
                padding: 0 !important;
                object-fit: cover !important;
                object-position: center !important;
                filter: blur(18px) saturate(.78) !important;
                opacity: .72 !important;
                transform: scale(1.08) !important;
                pointer-events: none !important;
            }

            ${MEDIA_SELECTOR}.is-adaptive-image::after {
                content: '';
                position: absolute;
                inset: 0;
                z-index: 1;
                background: rgba(255, 255, 255, .54);
                pointer-events: none;
            }

            ${MEDIA_SELECTOR}.is-adaptive-image > img.ovcat-product-image-backdrop {
                display: block !important;
            }

            ${MEDIA_SELECTOR}.is-adaptive-image > img.ovcat-product-image-main {
                inset: 4.5% !important;
                width: 91% !important;
                height: 91% !important;
                padding: 0 !important;
                border-radius: 8px !important;
                box-shadow: 0 8px 22px rgba(8, 39, 95, .16) !important;
            }

            ${MEDIA_SELECTOR} .ovcat-product-badges,
            ${MEDIA_SELECTOR} [class*="badge"],
            ${MEDIA_SELECTOR} button,
            ${MEDIA_SELECTOR} .ovcat-favorite {
                position: relative;
                z-index: 6 !important;
            }
        `;

        document.head.appendChild(style);
    }

    function directMainImage(frame) {
        return Array.from(frame.children).find((node) => {
            return node instanceof HTMLImageElement
                && !node.classList.contains('ovcat-product-image-backdrop');
        }) || frame.querySelector('img:not(.ovcat-product-image-backdrop)');
    }

    function applyAdaptiveState(frame, image) {
        const width = image.naturalWidth || 0;
        const height = image.naturalHeight || 0;

        if (!width || !height) return;

        const ratio = width / height;
        const isExtreme = ratio < EXTREME_RATIO || ratio > (1 / EXTREME_RATIO);

        image.classList.add('ovcat-product-image-main');
        frame.classList.toggle('is-adaptive-image', isExtreme);

        let backdrop = frame.querySelector(':scope > .ovcat-product-image-backdrop');

        if (isExtreme) {
            if (!backdrop) {
                backdrop = image.cloneNode(false);
                backdrop.removeAttribute('id');
                backdrop.removeAttribute('alt');
                backdrop.setAttribute('aria-hidden', 'true');
                backdrop.setAttribute('tabindex', '-1');
                backdrop.className = 'ovcat-product-image-backdrop';
                frame.insertBefore(backdrop, frame.firstChild);
            }

            if (backdrop.src !== image.src) {
                backdrop.src = image.src;
            }
        } else if (backdrop) {
            backdrop.remove();
        }

        frame.dataset.adaptiveImageReady = '1';
    }

    function processFrame(frame) {
        if (!(frame instanceof HTMLElement)) return;

        const image = directMainImage(frame);
        if (!(image instanceof HTMLImageElement)) return;

        if (image.dataset.adaptiveImageBound !== '1') {
            image.dataset.adaptiveImageBound = '1';

            image.addEventListener('load', () => {
                applyAdaptiveState(frame, image);
            });

            image.addEventListener('error', () => {
                frame.classList.remove('is-adaptive-image');
                frame.querySelector(':scope > .ovcat-product-image-backdrop')?.remove();
            });
        }

        if (image.complete && image.naturalWidth > 0) {
            applyAdaptiveState(frame, image);
        }
    }

    function scan(root = document) {
        if (root instanceof Element && root.matches(MEDIA_SELECTOR)) {
            processFrame(root);
        }

        root.querySelectorAll?.(MEDIA_SELECTOR).forEach(processFrame);
    }

    function start() {
        installStyles();
        scan(document);

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node instanceof Element) scan(node);
                }

                if (mutation.type === 'attributes' && mutation.target instanceof HTMLImageElement) {
                    const frame = mutation.target.closest(MEDIA_SELECTOR);
                    if (frame) processFrame(frame);
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src'],
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
