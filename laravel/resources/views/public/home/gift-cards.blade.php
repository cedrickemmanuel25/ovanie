        <section class="ov-panel ov-gift-section">
            <div class="ov-section-head">
                <div class="ov-section-head__title">
                    <span class="ov-kicker">OFFREZ, PARTAGEZ, RÉCOMPENSEZ</span>
                    <h2>Cartes et bons OVANIE</h2>
                    <p>Des solutions simples pour toutes les occasions.</p>
                </div>
                <a href="{{ $giftCardsUrl }}">Voir toutes les cartes <span>→</span></a>
            </div>

            <div class="ov-gift-grid">
                @foreach($giftGroups as $gift)
                    @php
                        $key = $gift['key'] ?? 'bon-achat';
                        $giftTitle = $gift['title'] ?? 'Carte OVANIE';
                        $giftDescription = $gift['description'] ?? 'Une solution OVANIE simple et pratique.';
                        $giftHref = !empty($gift['representative_id']) && Route::has('gift-cards.show')
                            ? route('gift-cards.show', $gift['representative_id'])
                            : (Route::has('gift-cards.category') ? route('gift-cards.category', $key) : $giftCardsUrl);
                    @endphp
                    <a href="{{ $giftHref }}" class="ov-gift-card">
                        <span class="ov-gift-card__copy">
                            @if(!empty($gift['count']))
                                <small>{{ (int) $gift['count'] }} modèles actifs</small>
                            @endif
                            <h3>{{ $giftTitle }}</h3>
                            <p>{{ $giftDescription }}</p>
                            @if(!empty($gift['min_price']))
                                <strong>À partir de {{ $formatPrice($gift['min_price']) }}</strong>
                            @endif
                            <span>Découvrir →</span>
                        </span>
                        <span class="ov-gift-card__visual">
                            <img src="{{ $giftImages[$key] ?? $giftImages['bon-achat'] }}" alt="{{ $giftTitle }} OVANIE" loading="lazy">
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
