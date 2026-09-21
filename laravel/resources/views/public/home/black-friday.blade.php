@if(collect($blackFridayProducts)->isNotEmpty())
<section class="ovh-section ovh-section--black" aria-labelledby="ovh-black-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Événement promotionnel"
            title="Black Friday OVANIE"
            description="Une sélection limitée de produits à prix promotionnels."
            :url="$blackFridayUrl"
            theme="dark"
        />
        <x-home.product-rail
            :products="$blackFridayProducts"
            :favorite-product-ids="$favoriteProductIds"
            badge="Black Friday"
        />
    </div>
</section>
@endif
