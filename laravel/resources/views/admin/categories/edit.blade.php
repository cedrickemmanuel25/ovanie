@extends('admin.layouts.app')

@php
    $isSubcategory = ($categoryType ?? ($category->parent_id ? 'subcategory' : 'category')) === 'subcategory';
@endphp

@section('title', ($isSubcategory ? 'Modifier une sous-catégorie' : 'Modifier une catégorie principale') . ' | Administration OVANIE')
@section('page-title', $isSubcategory ? 'Modifier une sous-catégorie' : 'Modifier une catégorie principale')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_categories.css') }}?v={{ file_exists(public_path('admin/css/admin_categories.css')) ? filemtime(public_path('admin/css/admin_categories.css')) : time() }}">
@endpush

@section('content')
<div class="category-admin-page category-form-page">
    <header class="category-form-header category-form-header-compact">
        <div>
            <span class="category-eyebrow">{{ $isSubcategory ? 'MISE À JOUR DE LA SOUS-CATÉGORIE' : 'MISE À JOUR DE LA CATÉGORIE PRINCIPALE' }}</span>
            <h2>{{ $category->name }}</h2>
            <p>Les modifications sont enregistrées dans la base et répercutées sur les formulaires, le catalogue et les menus concernés.</p>
        </div>
        <a class="category-btn category-btn-secondary" href="{{ route('admin.categories.index') }}">← Retour aux catégories</a>
    </header>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="category-professional-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="category_type" value="{{ $isSubcategory ? 'subcategory' : 'category' }}">
        @include('admin.categories.partials.form', ['categoryType' => $isSubcategory ? 'subcategory' : 'category'])

        <footer class="category-form-footer">
            <div class="category-delete-area">
                <button type="button" class="category-btn category-btn-danger-outline" onclick="document.getElementById('deleteCategoryForm').requestSubmit()">Supprimer</button>
                <small>Suppression possible uniquement si cet élément n’est utilisé nulle part.</small>
            </div>
            <div class="category-footer-actions">
                <a href="{{ route('admin.categories.index') }}" class="category-btn category-btn-secondary">Annuler</a>
                <button type="submit" class="category-btn category-btn-primary">Enregistrer les modifications</button>
            </div>
        </footer>
    </form>

    <form id="deleteCategoryForm" method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Supprimer définitivement cet élément ? Cette action est impossible s’il est encore utilisé.');">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
