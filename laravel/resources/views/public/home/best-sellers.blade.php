@if(collect($bestSellers)->isNotEmpty())
<section class="ovh-section" aria-labelledby="ovh-best-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Les plus demandés"
            title="Meilleures ventes en Côte d’Ivoire"
            description="Les produits que les clients OVANIE choisissent le plus pour leurs projets."
            :url="$bestSellersUrl"
        />

        <x-home.product-rail
            :products="$bestSellers"
            :favorite-product-ids="$favoriteProductIds"
        />
    </div>
</section>
@endif
