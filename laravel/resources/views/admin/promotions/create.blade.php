@extends('admin.layouts.app')

@section('title', 'Nouvelle promotion | Administration OVANIE')
@section('page-title', 'Nouvelle promotion')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_promotions.css') }}">
@endpush

@section('content')
<div class="promo-page promo-form-page">
    <header class="promo-form-hero">
        <div>
            <span class="promo-kicker">NOUVELLE CAMPAGNE</span>
            <h2>Créer une promotion</h2>
            <p>Définissez la remise, sa période de validité et les produits auxquels elle s’applique.</p>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="promo-btn promo-btn-secondary">Retour aux promotions</a>
    </header>

    <form action="{{ route('admin.promotions.store') }}" method="POST" class="promo-editor-form">
        @csrf
        @include('admin.promotions.partials.form')
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/admin_promotions.js') }}"></script>
@endpush
