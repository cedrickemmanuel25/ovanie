@php
    use Illuminate\Support\Facades\Route;

    $_homeUrl = Route::has('home') ? route('home') : url('/');
    $_catalogUrl = Route::has('catalog.index') ? route('catalog.index') : url('/catalog');
    $_aboutUrl = Route::has('about') ? route('about') : url('/qui-sommes-nous');
    $_careersUrl = Route::has('careers') ? route('careers') : url('/carrieres');
    $_contactUrl = Route::has('contact.index') ? route('contact.index') : url('/contact');
    $_faqUrl = Route::has('faq') ? route('faq') : url('/faq');
    $_helpCenterUrl = Route::has('help.center') ? route('help.center') : $_faqUrl;
    $_trackingUrl = Route::has('order.tracking.public') ? route('order.tracking.public') : $_ordersUrl;
    $_guaranteeUrl = Route::has('buyer.guarantee') ? route('buyer.guarantee') : $_faqUrl;
    $_ordersUrl = Route::has('orders.index') ? route('orders.index') : url('/mes-commandes');
    $_sellUrl = Route::has('sell.on.ovanie') ? route('sell.on.ovanie') : url('/vendre-sur-ovanie');
    $_partnersUrl = Route::has('partners.suppliers') ? route('partners.suppliers') : url('/partenaires-fournisseurs');
    $_businessUrl = Route::has('catalog.business') ? route('catalog.business') : $_catalogUrl;
    $_giftCardsUrl = Route::has('gift-cards.index') ? route('gift-cards.index') : url('/cartes-cadeaux');
    $_buyerGuideUrl = Route::has('buyer.guide') ? route('buyer.guide') : url('/guide-acheteur');
    $_termsUrl = Route::has('terms') ? route('terms') : (Route::has('cgu') ? route('cgu') : '#');
    $_privacyUrl = Route::has('privacy-policy') ? route('privacy-policy') : '#';
    $_newsletterUrl = Route::has('newsletter.subscribe') ? route('newsletter.subscribe') : '#';
    $_deliveryInfoUrl = Route::has('delivery.info') ? route('delivery.info') : url('/livraison');
    $_securePaymentUrl = Route::has('payment.secure') ? route('payment.secure') : url('/paiement-securise');
    $_returnsInfoUrl = Route::has('returns.refunds') ? route('returns.refunds') : url('/retours-remboursements');
    $_missionUrl = Route::has('company.mission') ? route('company.mission') : $_aboutUrl . '#mission';
    $_valuesUrl = Route::has('company.values') ? route('company.values') : $_aboutUrl . '#valeurs';
    $_vendorTermsUrl = Route::has('vendor.terms') ? route('vendor.terms') : $_termsUrl;
    $_problemUrl = Route::has('problem.report') ? route('problem.report') : $_contactUrl . '?sujet=probleme';
    $_legalNoticeUrl = Route::has('legal.notice') ? route('legal.notice') : $_termsUrl;
    $_sitemapUrl = Route::has('sitemap') ? route('sitemap') : url('/plan-du-site');

    $_publicPhone = (string) config('public_contact.phone_display', '01 61 78 00 00');
    $_publicPhoneHref = (string) config('public_contact.phone_e164', '+2250161780000');
    $_publicEmail = (string) config('public_contact.email', 'contact@ovanie.com');
    $_whatsappUrl = (string) config('public_contact.whatsapp_url', 'https://wa.me/2250161781818?text=Bonjour%20OVANIE%2C%20j%E2%80%99ai%20besoin%20d%E2%80%99assistance.');
    $_whatsappLabel = (string) config('public_contact.whatsapp_label', 'Discutez avec un conseiller');

    $_facebookUrl = config('services.social.facebook_url', 'https://web.facebook.com/ovanie.assistance/');
    $_instagramUrl = config('services.social.instagram_url', 'https://www.instagram.com/');
    $_linkedinUrl = config('services.social.linkedin_url', 'https://www.linkedin.com/');
    $_youtubeUrl = config('services.social.youtube_url', 'https://www.youtube.com/@ovanieMarketplace');
    $_googlePlayUrl = config('homepage.app_links.google_play');
    $_appStoreUrl = config('homepage.app_links.app_store');
@endphp

<footer class="ovn-footer">
    <div class="ovn-shell ovn-footer__grid">
        <section class="ovn-footer__brand">
            <a href="{{ $_homeUrl }}" class="ovn-footer__logo" aria-label="OVANIE - Accueil">
                <img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE">
            </a>

            <p>La marketplace ivoirienne des matériaux, équipements et solutions BTP.</p>

            <address class="ovn-footer__contact">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                    FEH KESSÉ, Abidjan
                </span>
                <a href="tel:{{ $_publicPhoneHref }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6.1 6.1l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"></path></svg>
                    {{ $_publicPhone }}
                </a>
                <a href="mailto:{{ $_publicEmail }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                    {{ $_publicEmail }}
                </a>
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                    Lun - Ven : 8h00 - 18h00
                </span>
            </address>

            <nav class="ovn-footer__socials" aria-label="Réseaux sociaux OVANIE">
                <a href="{{ $_facebookUrl }}" target="_blank" rel="noopener" aria-label="Facebook OVANIE" class="is-facebook">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.52 1.49-3.91 3.77-3.91 1.09 0 2.23.2 2.23.2V8.6h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.77l-.44 2.91h-2.33V22C18.34 21.24 22 17.08 22 12.06Z"/></svg>
                </a>
                <a href="{{ $_instagramUrl }}" target="_blank" rel="noopener" aria-label="Instagram OVANIE" class="is-instagram">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"></circle></svg>
                </a>
                <a href="{{ $_linkedinUrl }}" target="_blank" rel="noopener" aria-label="LinkedIn OVANIE" class="is-linkedin">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M5.3 7.9H2.2V22h3.1V7.9ZM3.8 2A1.8 1.8 0 1 0 3.8 5.7 1.8 1.8 0 0 0 3.8 2ZM22 13.7c0-4.2-2.2-6.2-5.2-6.2-2.4 0-3.5 1.3-4.1 2.2V7.9H9.6V22h3.1v-7c0-1.8.3-3.6 2.6-3.6 2.2 0 2.3 2.1 2.3 3.7V22H22v-8.3Z"/></svg>
                </a>
                <a href="{{ $_youtubeUrl }}" target="_blank" rel="noopener" aria-label="YouTube OVANIE" class="is-youtube">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4L15.8 12l-6.2 3.6Z"/></svg>
                </a>
                <a href="{{ $_whatsappUrl }}" target="_blank" rel="noopener" aria-label="{{ $_whatsappLabel }}" class="is-whatsapp">
                    <svg viewBox="0 0 32 32" aria-hidden="true" fill="currentColor"><path d="M16.04 3C9.46 3 4.1 8.35 4.1 14.93c0 2.1.55 4.15 1.6 5.96L4 27l6.27-1.64a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93C27.98 8.35 22.62 3 16.04 3Zm0 21.8h-.01a9.89 9.89 0 0 1-5.04-1.38l-.36-.21-3.72.97.99-3.63-.24-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.46 4.44-9.9 9.9-9.9a9.84 9.84 0 0 1 7 2.9 9.84 9.84 0 0 1 2.9 7c0 5.46-4.44 9.9-9.91 9.9Zm5.43-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.74-1.64-2.03-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.92 1.23 3.12c.15.2 2.13 3.24 5.15 4.54.72.31 1.28.49 1.72.63.72.23 1.38.2 1.9.12.58-.09 1.76-.72 2.01-1.41.25-.7.25-1.28.17-1.42-.07-.15-.27-.23-.57-.38Z"/></svg>
                </a>
            </nav>
        </section>

        <section class="ovn-footer__column">
            <h3>À PROPOS</h3>
            <a href="{{ $_aboutUrl }}">Qui sommes-nous ?</a>
            <a href="{{ $_missionUrl }}">Notre mission</a>
            <a href="{{ $_valuesUrl }}">Nos valeurs</a>
            <a href="{{ $_careersUrl }}">Carrières</a>
            <a href="{{ $_contactUrl }}">Contact</a>
        </section>

        <section class="ovn-footer__column">
            <h3>VENDRE &amp; DÉVELOPPER</h3>
            <a href="{{ $_sellUrl }}">Devenir vendeur</a>
            <a href="{{ $_vendorTermsUrl }}">Conditions vendeurs</a>
            <a href="{{ $_businessUrl }}">OVANIE Pro</a>
            <a href="{{ $_partnersUrl }}">Partenaires &amp; fournisseurs</a>
            <a href="{{ $_contactUrl }}?sujet=publicite">Publicité sur OVANIE</a>
        </section>

        <section class="ovn-footer__column">
            <h3>PAIEMENT &amp; LIVRAISON</h3>
            <a href="{{ $_securePaymentUrl }}">Sécurité des paiements</a>
            <a href="{{ $_deliveryInfoUrl }}">Livraison &amp; zones</a>
            <a href="{{ $_trackingUrl }}">Suivi de commande</a>
            <a href="{{ $_returnsInfoUrl }}">Retours &amp; remboursements</a>
            <a href="{{ $_guaranteeUrl }}">Garantie acheteur</a>
        </section>

        <section class="ovn-footer__column">
            <h3>BESOIN D’AIDE ?</h3>
            <a href="{{ $_helpCenterUrl }}">Centre d’aide</a>
            <a href="{{ $_faqUrl }}">FAQ</a>
            <a href="{{ $_buyerGuideUrl }}">Conseils BTP</a>
            <a href="{{ $_problemUrl }}">Signaler un problème</a>
        </section>

        <section class="ovn-footer__newsletter">
            <h3>NEWSLETTER</h3>
            <p>Recevez nos offres, nouveautés et conseils BTP.</p>
            <form action="{{ $_newsletterUrl }}" method="POST">
                @csrf
                <label class="sr-only" for="ovn-footer-email">Votre e-mail</label>
                <input id="ovn-footer-email" type="email" name="newsletter_email" placeholder="Votre e-mail" required>
                <button type="submit">S’abonner</button>
            </form>
        </section>
    </div>

    <div class="ovn-footer__bottom">
        <div class="ovn-shell ovn-footer__bottom-inner">
            <p>© {{ date('Y') }} OVANIE. Tous droits réservés.</p>

            <nav class="ovn-footer__legal" aria-label="Liens légaux">
                <a href="{{ $_sitemapUrl }}">Plan du site</a>
                <a href="{{ $_legalNoticeUrl }}">Mentions légales</a>
                <a href="{{ $_privacyUrl }}">Politique de confidentialité</a>
                <a href="{{ $_termsUrl }}">CGU</a>
            </nav>

            <div class="ovn-footer__apps" aria-label="Applications OVANIE">
                <span>Télécharger l’application</span>
                @if($_googlePlayUrl)
                    <a href="{{ $_googlePlayUrl }}" target="_blank" rel="noopener" class="ovn-store-badge">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 3 12 9L4 21V3Z"></path></svg>
                        <span><small>Disponible sur</small><strong>Google Play</strong></span>
                    </a>
                @else
                    <span class="ovn-store-badge is-disabled">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 3 12 9L4 21V3Z"></path></svg>
                        <span><small>Bientôt sur</small><strong>Google Play</strong></span>
                    </span>
                @endif

                @if($_appStoreUrl)
                    <a href="{{ $_appStoreUrl }}" target="_blank" rel="noopener" class="ovn-store-badge">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.5 12.3c0-2 1.7-3 1.8-3.1-1-1.4-2.5-1.6-3-1.6-1.3-.1-2.5.8-3.1.8-.7 0-1.7-.8-2.8-.8-1.4 0-2.8.9-3.5 2.2-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7 1.3 0 1.7.7 2.8.7 1.2 0 2-1 2.7-2 .8-1.2 1.1-2.3 1.1-2.4-.1 0-2.6-1-2.6-3.8ZM15.5 6.2c.6-.7 1-1.8.9-2.8-.9 0-2 .6-2.7 1.3-.6.7-1.1 1.7-1 2.7 1 .1 2.1-.5 2.8-1.2Z"></path></svg>
                        <span><small>Télécharger dans</small><strong>App Store</strong></span>
                    </a>
                @else
                    <span class="ovn-store-badge is-disabled">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.5 12.3c0-2 1.7-3 1.8-3.1-1-1.4-2.5-1.6-3-1.6-1.3-.1-2.5.8-3.1.8-.7 0-1.7-.8-2.8-.8-1.4 0-2.8.9-3.5 2.2-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7 1.3 0 1.7.7 2.8.7 1.2 0 2-1 2.7-2 .8-1.2 1.1-2.3 1.1-2.4-.1 0-2.6-1-2.6-3.8ZM15.5 6.2c.6-.7 1-1.8.9-2.8-.9 0-2 .6-2.7 1.3-.6.7-1.1 1.7-1 2.7 1 .1 2.1-.5 2.8-1.2Z"></path></svg>
                        <span><small>Bientôt sur</small><strong>App Store</strong></span>
                    </span>
                @endif
            </div>
        </div>
    </div>
</footer>

<a href="{{ $_whatsappUrl }}" class="ovn-whatsapp-float" target="_blank" rel="noopener" aria-label="{{ $_whatsappLabel }}">
    <svg viewBox="0 0 32 32" aria-hidden="true" fill="currentColor"><path d="M16.04 3C9.46 3 4.1 8.35 4.1 14.93c0 2.1.55 4.15 1.6 5.96L4 27l6.27-1.64a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93C27.98 8.35 22.62 3 16.04 3Zm0 21.8h-.01a9.89 9.89 0 0 1-5.04-1.38l-.36-.21-3.72.97.99-3.63-.24-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.46 4.44-9.9 9.9-9.9a9.84 9.84 0 0 1 7 2.9 9.84 9.84 0 0 1 2.9 7c0 5.46-4.44 9.9-9.91 9.9Zm5.43-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.74-1.64-2.03-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.92 1.23 3.12c.15.2 2.13 3.24 5.15 4.54.72.31 1.28.49 1.72.63.72.23 1.38.2 1.9.12.58-.09 1.76-.72 2.01-1.41.25-.7.25-1.28.17-1.42-.07-.15-.27-.23-.57-.38Z"/></svg>
</a>
