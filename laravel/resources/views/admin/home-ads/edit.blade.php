@extends('admin.layouts.app')

@section('title', 'Modifier une pub accueil')

@section('content')
<main class="marketing-page">
    <section class="marketing-hero marketing-hero--compact">
        <div>
            <p class="marketing-eyebrow">PUBLICITÉS D’ACCUEIL</p>
            <h1 class="marketing-title">Modifier la pub</h1>
            <p class="marketing-subtitle">Mettez à jour les informations de cette publicité accueil.</p>
        </div>

        <div class="marketing-hero__actions">
            <a href="{{ route('admin.home-ads.index') }}" class="marketing-btn marketing-btn--secondary">Retour à la liste</a>
        </div>
    </section>

    @if($errors->any())
        <div class="marketing-alert marketing-alert--error">Le formulaire contient des erreurs. Merci de vérifier les champs.</div>
    @endif

    <form action="{{ route('admin.home-ads.update', $homeAd) }}" method="POST" enctype="multipart/form-data">
        @include('admin.home-ads._form', ['homeAd' => $homeAd])
    </form>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_marketing_media.css') }}">
@endpush

@push('scripts')
@include('admin.home-ads.partials.preview-script')
@endpush
