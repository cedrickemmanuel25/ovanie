@extends('layouts.staff')

@section('title', 'Création de compte | Commercial OVANIE')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Choisir un parcours</h1>
        <p class="page-subtitle">Les créations Client et Vendeur utilisent désormais deux formulaires totalement séparés.</p>
    </div>
</div>

<div class="card" style="padding:24px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px">
    <a class="btn" style="min-height:72px;justify-content:flex-start;padding:16px" href="{{ route('commercial.clients.create') }}">
        <i data-lucide="user-plus"></i>
        <span><strong style="display:block">Créer un client</strong><small style="display:block;margin-top:4px">Compte acheteur uniquement</small></span>
    </a>
    <a class="btn btn-orange" style="min-height:72px;justify-content:flex-start;padding:16px" href="{{ route('commercial.vendors.create') }}">
        <i data-lucide="store"></i>
        <span><strong style="display:block">Créer un vendeur et sa boutique</strong><small style="display:block;margin-top:4px">Compte vendeur et boutique</small></span>
    </a>
</div>
@endsection
