@extends('admin.layouts.app')

@section('title', 'Nouvelle catégorie principale | Administration OVANIE')
@section('page-title', 'Nouvelle catégorie principale')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_categories.css') }}?v={{ file_exists(public_path('admin/css/admin_categories.css')) ? filemtime(public_path('admin/css/admin_categories.css')) : time() }}">
@endpush

@section('content')
<div class="category-admin-page category-form-page">
    <header class="category-form-header category-form-header-compact">
        <div>
            <span class="category-eyebrow">CATÉGORIE PRINCIPALE</span>
            <h2>Créer un univers produit</h2>
            <p>Créez une catégorie de premier niveau destinée à regrouper plusieurs sous-catégories cohérentes.</p>
        </div>
        <a class="category-btn category-btn-secondary" href="{{ route('admin.categories.index') }}">← Retour aux catégories</a>
    </header>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="category-professional-form">
        @csrf
        <input type="hidden" name="category_type" value="category">
        @include('admin.categories.partials.form', ['categoryType' => 'category'])

        <footer class="category-form-footer">
            <div class="category-form-footer-copy">
                <span class="category-form-footer-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
                </span>
                <div>
                    <strong>Catégorie principale</strong>
                    <small>L’univers sera enregistré dans la base et disponible dans les formulaires actifs.</small>
                </div>
            </div>
            <div class="category-form-footer-actions">
                <a href="{{ route('admin.categories.index') }}" class="category-btn category-btn-secondary">Annuler</a>
                <button type="submit" class="category-btn category-btn-primary">Enregistrer la catégorie principale</button>
            </div>
        </footer>
    </form>
</div>
@endsection
