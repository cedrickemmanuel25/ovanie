@extends('layouts.vendor')

@section('title', 'Ajouter un produit | OVANIE')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor_base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/add_product.css') }}">
    <style>
        .boost-box {
            margin-top: 18px;
            padding: 18px;
            border: 1px solid #f1c27d;
            border-radius: 12px;
            background: #fff8ef;
        }

        .boost-box h3 {
            margin: 0 0 10px;
            color: #c56a00;
            font-size: 18px;
        }

        .boost-note {
            font-size: 14px;
            color: #6b7280;
            margin-top: 8px;
            line-height: 1.5;
        }

        .boost-price-card {
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #f1c27d;
            color: #8a5a00;
            font-weight: 700;
            line-height: 1.6;
        }

        .boost-price-value {
            display: inline-block;
            padding: 4px 10px;
            margin-top: 6px;
            border-radius: 999px;
            background: #fff4e8;
            color: #c56a00;
            border: 1px solid #f1c27d;
            font-weight: 800;
        }

        .alert-danger ul {
            margin: 0;
            padding-left: 18px;
        }
    </style>
@endsection

@section('content')
    <main class="main-content">

        <h1>Ajouter un produit</h1>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="addProductForm" method="POST" action="{{ route('daniel.products.store') }}" enctype="multipart/form-data">
            @csrf

            <label>Nom du produit *</label>
            <input type="text" id="productName" name="name" value="{{ old('name') }}" required>

            <label>Catégorie *</label>
            <select id="productCategory" name="category_id" required>
                <option value="">-- Choisir --</option>
                @if(isset($categories) && $categories->count() > 0)
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                @endif
            </select>

            <label>Type de vente *</label>
            <select id="saleType" name="sale_type" required>
                <option value="">-- Choisir --</option>
                <option value="Vente normale" {{ old('sale_type') == 'Vente normale' ? 'selected' : '' }}>Vente normale</option>
                <option value="Vente flash" {{ old('sale_type') == 'Vente flash' ? 'selected' : '' }}>Vente flash</option>
                <option value="Black Friday" {{ old('sale_type') == 'Black Friday' ? 'selected' : '' }}>Black Friday</option>
                <option value="Promo spéciale" {{ old('sale_type') == 'Promo spéciale' ? 'selected' : '' }}>Promo spéciale</option>
            </select>

            <label>Prix (FCFA) *</label>
            <input type="number" id="productPrice" name="price" value="{{ old('price') }}" min="0" step="1" required>

            <label>Prix de livraison (FCFA)</label>
            <input type="number" step="1" min="0" name="transport" value="{{ old('transport') }}">

            <label>Prix promo (optionnel)</label>
            <input type="number" name="promo_price" value="{{ old('promo_price') }}" min="0" step="1">

            <label>Stock *</label>
            <input type="number" id="productStock" name="stock" value="{{ old('stock') }}" min="0" required>

            <label>Description *</label>
            <textarea id="productDescription" name="description" rows="4" required>{{ old('description') }}</textarea>

            <label>Type de produit (flash_sale, black_friday, etc.)</label>
            <input type="text" id="productType" name="type" value="{{ old('type') }}">

            <label>Prix plafond (optionnel)</label>
            <input type="number" name="price_p1" value="{{ old('price_p1') }}" min="0" step="1">

            <label>Prix contre-offre (optionnel)</label>
            <input type="number" name="price_p2" value="{{ old('price_p2') }}" min="0" step="1">

            <label>Prix plancher (optionnel)</label>
            <input type="number" name="price_p3" value="{{ old('price_p3') }}" min="0" step="1">

            <label>Date fin Flash Sale</label>
            <input type="datetime-local" name="flash_end" value="{{ old('flash_end') }}">

            <label>Date début Black Friday</label>
            <input type="datetime-local" name="bf_start" value="{{ old('bf_start') }}">

            <label>Date fin Black Friday</label>
            <input type="datetime-local" name="bf_end" value="{{ old('bf_end') }}">

            <div class="boost-box">
                <h3>Option de boost</h3>

                <p class="boost-note">
                    Le boost n’est plus activé directement depuis ce formulaire.
                    Après création du produit, tu pourras cliquer sur <strong>Booster</strong>
                    dans la liste de tes produits pour ouvrir le paiement Mobile Money.
                </p>

                <div class="boost-price-card">
                    Tarif du boost préparé pour PayDunya :
                    <div class="boost-price-value">
                        {{ number_format($boostPrice ?? 5000, 0, ',', ' ') }} FCFA
                    </div>
                </div>
            </div>

            <label>Images du produit *</label>
            <input type="file" id="productImages" name="images[]" multiple accept="image/*" required>

            <label>
                <input type="checkbox" name="fast_delivery" value="1" {{ old('fast_delivery') ? 'checked' : '' }}>
                Livraison rapide
            </label>

            <button type="submit">Ajouter le produit</button>
        </form>

    </main>
@endsection

@section('scripts')
    <script src="{{ asset('js/add_product.js') }}"></script>
@endsection
