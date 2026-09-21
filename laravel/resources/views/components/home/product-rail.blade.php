@props([
    'products',
    'favoriteProductIds' => [],
    'badge' => null,
    'id' => null,
])

@if(collect($products)->isNotEmpty())
    <div class="ovh-product-rail" @if($id) id="{{ $id }}" @endif>
        @foreach($products as $product)
            <x-home.product-card
                :product="$product"
                :favorite-product-ids="$favoriteProductIds"
                :badge="$badge"
            />
        @endforeach
    </div>
@endif
