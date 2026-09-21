document.addEventListener('DOMContentLoaded', () => {
    const campaignScrollKey = 'ovanie.home.campaign-refresh-scroll';
    const savedCampaignScroll = sessionStorage.getItem(campaignScrollKey);
    if (savedCampaignScroll !== null) {
        sessionStorage.removeItem(campaignScrollKey);
        window.requestAnimationFrame(() => window.scrollTo({ top: Number(savedCampaignScroll) || 0, behavior: 'instant' }));
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const showToast = (message, type = 'success') => {
        let toast = document.querySelector('[data-home-toast]');

        if (!toast) {
            toast = document.createElement('div');
            toast.dataset.homeToast = '';
            toast.className = 'ovanie-home-toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.classList.remove('is-success', 'is-error', 'is-visible');
        toast.classList.add(type === 'error' ? 'is-error' : 'is-success');

        window.requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.clearTimeout(toast._hideTimer);
        toast._hideTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3000);
    };

    const refreshHomepagePreservingScroll = () => {
        sessionStorage.setItem(campaignScrollKey, String(window.scrollY || 0));
        window.location.reload();
    };

    const homepageRoot = document.querySelector('[data-homepage-status-url]');
    const homepageStatusUrl = homepageRoot?.getAttribute('data-homepage-status-url') || '';
    const currentFlashPanelMode = homepageRoot?.getAttribute('data-flash-panel-mode') || 'latest';
    const currentRightPanelMode = homepageRoot?.getAttribute('data-right-panel-mode') || 'featured';

    const checkHomepageCampaignState = async () => {
        if (!homepageStatusUrl) {
            return;
        }

        try {
            const response = await fetch(homepageStatusUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const desiredFlashMode = Number(payload?.flash?.count || 0) > 0 ? 'flash' : 'latest';
            const desiredRightMode = payload?.right_panel_mode
                || (payload?.black_friday?.visible ? 'black_friday' : 'discovery');

            if (desiredFlashMode !== currentFlashPanelMode || desiredRightMode !== currentRightPanelMode) {
                refreshHomepagePreservingScroll();
            }
        } catch (error) {
            console.warn('OVANIE: vérification des campagnes impossible.', error);
        }
    };

    // Les nouvelles campagnes programmées et le passage au vendredi sont
    // détectés sans que le client ait besoin de recharger manuellement la page.
    if (homepageStatusUrl) {
        window.setInterval(checkHomepageCampaignState, 300000);
    }

    /* ---------------------------------------------------------------------- */
    /* Rails / carrousels                                                       */
    /* ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-scroll-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetSelector = button.getAttribute('data-scroll-target');
            const direction = button.getAttribute('data-scroll-direction') || 'right';
            const target = document.querySelector(targetSelector);

            if (!target) {
                return;
            }

            const scrollAmount = Math.round(target.clientWidth * 0.85);
            target.scrollBy({
                left: direction === 'left' ? -scrollAmount : scrollAmount,
                behavior: 'smooth',
            });
        });
    });

    /* ---------------------------------------------------------------------- */
    /* Compte à rebours Offres Flash                                           */
    /* ---------------------------------------------------------------------- */
    const setupCountdown = async (timer) => {
        const parseDeadline = (value) => {
            if (!value) return null;
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        };

        let deadline = parseDeadline(timer.getAttribute('data-countdown-ends-at'));
        const statusUrl = timer.getAttribute('data-status-url')
            || document.querySelector('[data-homepage-status-url]')?.getAttribute('data-homepage-status-url')
            || '';

        // Si le HTML ne contient pas encore de date valide, on la redemande au backend.
        if ((!deadline || deadline.getTime() <= Date.now()) && statusUrl) {
            try {
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (response.ok) {
                    const payload = await response.json();
                    deadline = parseDeadline(payload?.flash?.ends_at || '');
                    if (deadline) {
                        timer.setAttribute('data-countdown-ends-at', payload.flash.ends_at);
                    }
                }
            } catch (error) {
                console.warn('OVANIE: impossible de récupérer l’échéance Flash.', error);
            }
        }

        if (!deadline) {
            timer.textContent = 'À VENIR';
            timer.classList.add('is-inactive');
            return;
        }

        let intervalId = null;

        const render = () => {
            const remainingSeconds = Math.max(0, Math.floor((deadline.getTime() - Date.now()) / 1000));
            const totalHours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;

            timer.textContent = [totalHours, minutes, seconds]
                .map((value) => String(value).padStart(2, '0'))
                .join(' : ');

            if (remainingSeconds <= 0) {
                timer.textContent = 'ACTUALISATION';
                timer.classList.add('is-inactive');
                if (intervalId !== null) {
                    window.clearInterval(intervalId);
                }

                // Le backend recalcule immédiatement les produits actifs : le
                // produit expiré quitte la section et le prochain prend sa place.
                window.setTimeout(refreshHomepagePreservingScroll, 650);
            }
        };

        render();
        intervalId = window.setInterval(render, 1000);
    };

    document.querySelectorAll('[data-countdown-ends-at]').forEach((timer) => {
        setupCountdown(timer);
    });

    /* ---------------------------------------------------------------------- */
    /* Newsletter : soumission AJAX avec fallback HTML                         */
    /* ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-newsletter-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const feedback = form.parentElement?.querySelector('[data-newsletter-feedback]');
            const originalLabel = button?.textContent || 'S’abonner';

            if (button) {
                button.disabled = true;
                button.textContent = 'Envoi...';
            }

            if (feedback) {
                feedback.innerHTML = '';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = payload?.errors?.newsletter_email?.[0]
                        || payload?.message
                        || 'Impossible de vous inscrire pour le moment.';
                    throw new Error(message);
                }

                form.reset();

                if (feedback) {
                    feedback.innerHTML = `<p class="ov-footer__newsletter-feedback is-success">${payload.message || 'Inscription confirmée.'}</p>`;
                }
            } catch (error) {
                if (feedback) {
                    feedback.innerHTML = `<p class="ov-footer__newsletter-feedback is-error">${error.message}</p>`;
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            }
        });
    });

    /* ---------------------------------------------------------------------- */
    /* Favoris                                                                 */
    /* ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-home-favorite]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.getAttribute('data-favorite-url') || '';
            const loginUrl = button.getAttribute('data-login-url') || '';

            if (!url) {
                if (loginUrl && loginUrl !== '#') {
                    window.location.href = loginUrl;
                }
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Impossible de modifier les favoris.');
                }

                const payload = await response.json();
                const active = Boolean(payload.added);

                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.setAttribute('aria-label', active ? 'Retirer des favoris' : 'Ajouter aux favoris');

                const icon = button.querySelector('svg');
                if (icon) {
                    icon.style.fill = active ? 'currentColor' : 'none';
                }

                showToast(active ? 'Produit ajouté aux favoris.' : 'Produit retiré des favoris.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        });
    });

    /* ---------------------------------------------------------------------- */
    /* Ajout panier depuis l’accueil, sans quitter la page                     */
    /* ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-home-cart-form][data-ajax="1"]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const originalLabel = button?.textContent || 'Ajouter';

            if (button) {
                button.disabled = true;
                button.textContent = 'Ajout...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Impossible d’ajouter ce produit au panier.');
                }

                document.querySelectorAll('[data-cart-count]').forEach((counter) => {
                    counter.textContent = String(payload.cart_count ?? payload.item_count ?? 0);
                    counter.dataset.count = String(payload.cart_count ?? payload.item_count ?? 0);
                });

                showToast(payload.message || 'Produit ajouté au panier.');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            }
        });
    });
});
