        @if($eventList->isNotEmpty())
        <section class="ov-panel ov-feature-section ov-event-section {{ $isFridayCampaign ? 'is-black-friday' : 'is-flash-sale' }}">
                <div class="ov-feature-intro ov-event-intro">
                    <span class="ov-kicker">{{ $eventKicker }}</span>
                    <h2>{{ $eventTitle }}</h2>
                    <p>{{ $eventDescription }}</p>

                    @if($eventEndsAt)
                        <div class="ov-countdown" data-countdown data-ends-at="{{ $eventEndsAt }}" aria-label="Temps restant">
                            @foreach(['days' => 'Jours', 'hours' => 'Heures', 'minutes' => 'Minutes', 'seconds' => 'Secondes'] as $unit => $label)
                                <span><b data-countdown-unit="{{ $unit }}">00</b><small>{{ $label }}</small></span>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ $eventUrl }}" class="ov-btn ov-btn--outline-dark">Voir toutes les offres</a>
                </div>

                <div class="ov-feature-products">
                    @foreach($eventList as $product)
                        @php
                            $productName = $clean($product->name ?? 'Produit OVANIE', $product->slug ?? null);
                            $productRating = $rating($product);
                        @endphp
                        <article class="ov-product-card ov-product-card--compact">
                            <a class="ov-product-card__image" href="{{ $productUrl($product) }}">
                                <img src="{{ $productImage($product) }}" alt="{{ $productName }}" loading="lazy">
                            </a>
                            <div class="ov-product-card__body">
                                <a href="{{ $productUrl($product) }}" class="ov-product-card__name">{{ $productName }}</a>
                                <div class="ov-feature-bottom">
                                    <div>
                                        <strong>{{ $formatPrice($price($product)) }}</strong>
                                        @if($productRating)
                                            <small><span class="ov-star">★</span> {{ $productRating[0] }} ({{ $productRating[1] }})</small>
                                        @endif
                                    </div>
                                    <a href="{{ $productUrl($product) }}" class="ov-plus-button" aria-label="Voir {{ $productName }}">+</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-countdown]').forEach(function (countdown) {
                var end = Date.parse(countdown.dataset.endsAt || '');
                if (!Number.isFinite(end)) return;

                var units = {};
                countdown.querySelectorAll('[data-countdown-unit]').forEach(function (node) {
                    units[node.dataset.countdownUnit] = node;
                });

                function renderCountdown() {
                    var remaining = Math.max(0, end - Date.now());
                    var totalSeconds = Math.floor(remaining / 1000);
                    var values = {
                        days: Math.floor(totalSeconds / 86400),
                        hours: Math.floor((totalSeconds % 86400) / 3600),
                        minutes: Math.floor((totalSeconds % 3600) / 60),
                        seconds: totalSeconds % 60
                    };

                    Object.keys(values).forEach(function (unit) {
                        if (units[unit]) units[unit].textContent = String(values[unit]).padStart(2, '0');
                    });

                    if (remaining === 0) {
                        countdown.classList.add('is-finished');
                        clearInterval(timer);
                    }
                }

                renderCountdown();
                var timer = setInterval(renderCountdown, 1000);
            });
        });
        </script>
        @endpush
        @endif
