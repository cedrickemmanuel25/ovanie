        <section class="ov-hero" aria-label="Bienvenue sur OVANIE">
            <img class="ov-hero__background" src="{{ asset('images/home/hero-ovanie.png') }}" alt="Chantier de construction OVANIE avec deux professionnels du BTP">
            <div class="ov-hero__overlay"></div>

            <div class="ov-hero__copy">
                <span class="ov-eyebrow">Marketplace BTP en Côte d’Ivoire</span>
                <h1>Votre chantier<br>commence ici<br>avec <em>OVANIE</em></h1>
                <p>Matériaux, équipements et services BTP de qualité, livrés partout en Côte d’Ivoire. Pour les pros comme pour les particuliers.</p>

                <div class="ov-hero__actions">
                    <a href="{{ $catalogUrl }}" class="ov-btn ov-btn--orange">Acheter maintenant <span>→</span></a>
                    <a href="{{ $sellUrl }}" class="ov-btn ov-btn--outline-light">Vendre sur OVANIE <span>→</span></a>
                </div>

                <div class="ov-hero__support">
                    <a href="{{ $faqUrl }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M9.8 9a2.4 2.4 0 1 1 4.1 1.7c-1 .8-1.9 1.3-1.9 2.8M12 17h.01"></path></svg>
                        Besoin d’aide ?
                    </a>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="ov-hero__whatsapp">
                        <svg class="ov-whatsapp-brand" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                            <path d="M16.04 3C9.46 3 4.1 8.35 4.1 14.93c0 2.1.55 4.15 1.6 5.96L4 27l6.27-1.64a11.9 11.9 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.93-11.93C27.98 8.35 22.62 3 16.04 3Zm0 21.8h-.01a9.89 9.89 0 0 1-5.04-1.38l-.36-.21-3.72.97.99-3.63-.24-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.46 4.44-9.9 9.9-9.9a9.84 9.84 0 0 1 7 2.9 9.84 9.84 0 0 1 2.9 7c0 5.46-4.44 9.9-9.91 9.9Zm5.43-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.74-1.64-2.03-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.02-1.05 2.5s1.08 2.92 1.23 3.12c.15.2 2.13 3.24 5.15 4.54.72.31 1.28.49 1.72.63.72.23 1.38.2 1.9.12.58-.09 1.76-.72 2.01-1.41.25-.7.25-1.28.17-1.42-.07-.15-.27-.23-.57-.38Z"/>
                        </svg>
                        Écrivez-nous sur WhatsApp
                    </a>
                </div>
            </div>

            <div class="ov-hero__stats">
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4.5-8 4.5-8-4.5L12 3Z"></path><path d="m4 7.5 8 4.5 8-4.5M4 12l8 4.5 8-4.5M4 16.5l8 4.5 8-4.5"></path></svg></span>
                    <div><strong>+{{ number_format((int) ($publicProductCount ?? 0), 0, ',', ' ') }}</strong><small>produits disponibles</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path><path d="m18 5 1.5 1.5L22 4"></path></svg></span>
                    <div><strong>+{{ number_format((int) ($verifiedSellerCount ?? 0), 0, ',', ' ') }}</strong><small>vendeurs vérifiés</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg></span>
                    <div><strong>Livraison rapide</strong><small>partout en Côte d’Ivoire</small></div>
                </div>
                <div class="ov-stat">
                    <span class="ov-stat__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 3 8.4 7 10 4-1.6 7-5.3 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
                    <div><strong>Paiements sécurisés</strong><small>modes disponibles au checkout</small></div>
                </div>
            </div>
        </section>
