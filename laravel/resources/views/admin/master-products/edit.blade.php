@extends('admin.layouts.app')
@section('title', 'Modifier une référence | Admin OVANIE')
@section('page-title', 'Modifier la référence')
@push('styles')<link rel="stylesheet" href="{{ asset('admin/css/admin_catalogue.css') }}">@endpush
@section('content')
<div class="admin-catalogue-page catalogue-form-shell">
    <header class="catalogue-heading">
        <div><span class="catalogue-kicker">Catalogue OVANIE</span><h2>{{ $masterProduct->name }}</h2><p>Corrigez la fiche technique et associez les offres de boutiques correspondant exactement à cette référence.</p></div>
        <a href="{{ route('admin.master-products.index') }}" class="catalogue-btn">Retour au catalogue</a>
    </header>
    @include('admin.master-products.partials.form', ['action'=>route('admin.master-products.update',$masterProduct),'method'=>'PUT','masterProduct'=>$masterProduct,'categories'=>$categories])

    <section class="catalogue-panel">
        <div class="catalogue-panel-head"><div><h3>Associer des produits de boutiques</h3><p>Sélectionnez uniquement des produits strictement équivalents : même marque, modèle, conditionnement et caractéristiques.</p></div></div>
        <form method="POST" action="{{ route('admin.master-products.attach-products',$masterProduct) }}" style="padding:20px;display:grid;gap:12px;">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;max-height:420px;overflow:auto;">
                @forelse($availableProducts as $product)
                    <label class="catalogue-choice">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked((int)$product->master_product_id === (int)$masterProduct->id)>
                        <span><strong>{{ $product->name }}</strong><small>{{ $product->shop?->name ?: 'Sans boutique' }} · #{{ $product->id }}</small></span>
                    </label>
                @empty
                    <div class="catalogue-empty">Aucun produit disponible à associer.</div>
                @endforelse
            </div>
            @if($availableProducts->isNotEmpty())<div class="catalogue-actions"><button class="catalogue-btn primary" type="submit">Enregistrer les associations</button></div>@endif
        </form>
    </section>
</div>
@endsection
