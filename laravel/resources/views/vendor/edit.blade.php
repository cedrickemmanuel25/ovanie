@extends('layouts.vendor')

@section('title', 'Modifier le produit | OVANIE Seller Central')

@include('vendor.products.partials.ovanie-product-wizard', [
    'product' => $product,
    'isEdit' => true,
])
