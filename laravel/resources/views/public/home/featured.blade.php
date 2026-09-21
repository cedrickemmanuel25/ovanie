@if(collect($featuredProducts)->isNotEmpty())
<section class="ovh-section" aria-labelledby="ovh-featured-title">
    <div class="ovh-container">
        <x-home.section-heading
            eyebrow="Sélection OVANIE"
            title="Des produits pour faire avancer vos travaux"
            description="Une sélection de produits mis en avant pour vos besoins de construction, de rénovation et d’équipement."
            :url="$catalogUrl"
        />
        <x-home.product-rail :products="$featuredProducts" :favorite-product-ids="$favoriteProductIds" />
    </div>
</section>
@endif
