@props(['product'=>null,'name'=>'Produit','compact'=>false])
@php($hasImage=$product && ($product->images->isNotEmpty() || collect(['main_image','image','image_path','photo','thumbnail','gallery'])->contains(fn($key)=>filled($product->getRawOriginal($key)))))
@if($hasImage)<img class="{{ $compact?'directory-product-thumb':'directory-product-image' }}" src="{{ $product->main_image_url }}" alt="{{ $name }}">@elseif($compact)<x-operations.icon name="box"/>@else<div class="directory-placeholder directory-product-image"><x-operations.icon name="box"/>{{ $name }}</div>@endif
