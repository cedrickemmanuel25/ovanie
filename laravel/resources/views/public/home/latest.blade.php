@if(collect($latestProducts)->isNotEmpty())
<section class="ovh-section ovh-section--surface" aria-labelledby="ovh-latest-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Nouveautés"
            title="Nouveaux produits sur OVANIE"
            description="Découvrez les dernières références ajoutées au catalogue."
            :url="$catalogUrl"
        />
        <x-home.product-rail :products="$latestProducts" :favorite-product-ids="$favoriteProductIds" />
    </div>
</section>
@endif
