@if(collect($flashProducts)->isNotEmpty())
<section class="ovh-section ovh-section--flash" aria-labelledby="ovh-flash-title">
    <div class="ovh-container">
        <div class="ovh-flash-heading">
            <div>
                <span class="ovh-flash-heading__label"><i data-lucide="zap"></i> Offres Flash</span>
                <h2 id="ovh-flash-title">Des prix à saisir avant la fin du chrono</h2>
            </div>

            <div class="ovh-flash-heading__actions">
                @if($flashSaleEndsAt)
                    <div class="ovh-countdown" data-countdown-ends-at="{{ $flashSaleEndsAt }}" aria-label="Temps restant">
                        <span data-countdown-hours>00</span><b>:</b><span data-countdown-minutes>00</span><b>:</b><span data-countdown-seconds>00</span>
                    </div>
                @endif
                <a href="{{ $flashUrl }}" class="ovh-section-link ovh-section-link--light">Voir tout <i data-lucide="arrow-right"></i></a>
            </div>
        </div>

        <x-home.product-rail
            :products="$flashProducts"
            :favorite-product-ids="$favoriteProductIds"
            badge="Flash"
            id="ovh-flash-products"
        />
    </div>
</section>
@endif
