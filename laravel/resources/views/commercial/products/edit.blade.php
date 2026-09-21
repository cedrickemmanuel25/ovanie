@extends('layouts.staff')
@section('title', 'Modifier le produit | Commercial OVANIE')
@section('content')
@include('commercial.products._form', ['product' => $product])
@endsection
