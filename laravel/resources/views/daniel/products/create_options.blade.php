@extends('layouts.vendor')

@section('title', 'Ajouter un produit | OVANIE')

@section('styles')
<style>
.add-choice-page { max-width: 1180px; margin: 0 auto; padding-top: 18px; }
.add-choice-grid { margin-top: 64px; display: grid; grid-template-columns: 1fr 1fr; gap: 36px; }
.add-choice-card { min-height: 578px; padding: 38px 54px 50px; display: flex; flex-direction: column; align-items: center; text-align: center; }
.add-choice-card img { width: min(330px, 100%); height: 240px; object-fit: contain; margin-bottom: 26px; }
.add-choice-card h2 { margin: 0 0 22px; color: var(--ov-navy); font-size: 1.85rem; line-height: 1.1; font-weight: 900; }
.add-choice-card p { margin: 0 auto 44px; color: #374056; font-size: 1.12rem; line-height: 1.55; max-width: 420px; }
.add-choice-card .vd-btn { width: 100%; max-width: 370px; min-height: 68px; font-size: 1.28rem; margin-top: auto; }
@media (max-width: 900px) { .add-choice-grid { grid-template-columns: 1fr; margin-top: 32px; } .add-choice-card { min-height: auto; padding: 28px; } }
</style>
@endsection

@section('content')
<section class="add-choice-page">
    <div class="vd-breadcrumb">
        <strong>Produits</strong>
        <i data-lucide="chevron-right"></i>
        <span>Ajouter des produits</span>
    </div>

    <h1 class="vd-title">Ajouter un produit</h1>
    <p class="vd-subtitle">Choisissez le mode d'ajout qui correspond le mieux à vos besoins.</p>

    <div class="add-choice-grid">
        <article class="add-choice-card vd-card">
            <img src="{{ asset('vendor-assets/add-single-product.svg') }}" alt="Ajouter un produit">
            <h2>Ajouter un produit</h2>
            <p>Ajoutez un seul produit manuellement avec toutes les informations nécessaires.</p>
            <a href="{{ route('daniel.add_product', ['type' => 'single']) }}" class="vd-btn primary">Commencer</a>
        </article>

        <article class="add-choice-card vd-card">
            <img src="{{ asset('vendor-assets/add-bulk-products.svg') }}" alt="Importer en masse">
            <h2>Importer en masse (Excel/CSV)</h2>
            <p>Importez plusieurs produits à la fois via un fichier Excel ou CSV pour gagner du temps.</p>
            <a href="#" class="vd-btn primary">Commencer</a>
        </article>
    </div>
</section>
@endsection
