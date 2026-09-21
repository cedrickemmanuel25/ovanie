@extends('admin.layouts.app')

@section('title', 'Modifier une promotion | Administration OVANIE')
@section('page-title', 'Modifier une promotion')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_promotions.css') }}">
@endpush

@section('content')
<div class="promo-page promo-form-page">
    <header class="promo-form-hero">
        <div>
            <span class="promo-kicker">MISE À JOUR DE LA CAMPAGNE</span>
            <h2>{{ $promotion->code }}</h2>
            <p>Modifiez la remise, la période ou les produits concernés sans changer leurs prix de base.</p>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="promo-btn promo-btn-secondary">Retour aux promotions</a>
    </header>

    <form action="{{ route('admin.promotions.update', $promotion) }}" method="POST" class="promo-editor-form">
        @csrf
        @method('PUT')
        @include('admin.promotions.partials.form')
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/admin_promotions.js') }}"></script>
@endpush
