@extends('layouts.staff')
@section('title', 'Ajouter un produit | Commercial OVANIE')
@section('content')
@include('commercial.products._form', ['product' => null])
@endsection
