@extends('layouts.vendor')

@section('title', 'Ajouter un produit | OVANIE Seller Central')

@include('vendor.products.partials.ovanie-product-wizard', [
    'product' => null,
    'isEdit' => false,
])
