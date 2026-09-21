@extends('layouts.vendor')

@section('title', 'Modifier produit')

@section('styles')
<style>
.main-content {
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    font-family: Arial, sans-serif;
}

.main-content h1 {
    font-size: 28px;
    margin-bottom: 20px;
    color: #333;
}

form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

form label {
    font-weight: bold;
    margin-bottom: 6px;
    display: block;
}

form input[type="text"],
form input[type="number"],
form input[type="file"],
form select,
form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

form button {
    background-color: #ff9900;
    color: #fff;
    padding: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

form button:hover {
    background-color: #e68a00;
}

.image-gallery {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.image-box {
    border: 1px solid #ccc;
    padding: 6px;
    border-radius: 4px;
    text-align: center;
}

.image-box img {
    max-width: 120px;
    max-height: 120px;
    object-fit: contain;
    display: block;
    margin-bottom: 6px;
}

.image-box input[type="radio"] {
    margin-top: 4px;
}

.boost-box {
    margin-top: 10px;
    padding: 18px;
    border: 1px solid #f1c27d;
    border-radius: 10px;
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

.alert-danger ul {
    margin: 0;
    padding-left: 18px;
}

.current-boost-status {
    padding: 10px 14px;
    border-radius: 8px;
    font-weight: 700;
    margin-bottom: 12px;
}

.current-boost-status.active {
    background: #e9f9ef;
    color: #0f7b3a;
    border: 1px solid #b8e3c7;
}

.current-boost-status.pending {
    background: #fff7e8;
    color: #a16207;
    border: 1px solid #f5d58b;
}

.current-boost-status.inactive {
    background: #f7f7f7;
    color: #666;
    border: 1px solid #ddd;
}

.boost-info-card {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #f1c27d;
    color: #8a5a00;
    font-weight: 700;
    line-height: 1.6;
}

.boost-ref {
    margin-top: 10px;
    font-size: 14px;
    color: #555;
}
</style>
@endsection

@section('content')
<main class="main-content">
    <h1>Modifier : {{ $product->name }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
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

    <form action="{{ route('daniel.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <label for="name">Nom du produit</label>
        <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required>

        <label for="price">Prix</label>
        <input type="number" id="price" name="price" value="{{ old('price', $product->price) }}" step="1" required>

        <label for="stock">Stock</label>
        <input type="number" id="stock" name="stock" value="{{ old('stock', $product->stock) }}" required>

        <label for="transport">Prix de livraison</label>
        <input type="number" id="transport" name="transport" value="{{ old('transport', $product->transport) }}" step="1" min="0">

        <label for="category_id">Catégorie</label>
        <select id="category_id" name="category_id">
            <option value="">-- Choisir --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}"
                    {{ (string) old('category_id', $product->category_id) === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>

        <div class="boost-box">
            <h3>Gestion du boost</h3>

            @if($product->is_boost_active)
                <div class="current-boost-status active">
                    Produit actuellement boosté / visible dans À LA UNE
                </div>
            @elseif($product->boost_payment_status === 'pending')
                <div class="current-boost-status pending">
                    Paiement du boost en attente
                </div>
            @else
                <div class="current-boost-status inactive">
                    Produit non boosté actuellement
                </div>
            @endif

            <p class="boost-note">
                Le boost s’active uniquement après paiement Mobile Money.
                Pour lancer le paiement, utilise le bouton <strong>Booster</strong> dans la liste de tes produits.
            </p>

            <div class="boost-info-card">
                Tarif du boost préparé pour PayDunya :
                <strong>{{ number_format($boostPrice ?? $product->boost_price ?? 5000, 0, ',', ' ') }} FCFA</strong>
            </div>

            @if($product->boost_payment_reference)
                <div class="boost-ref">
                    Référence paiement : <strong>{{ $product->boost_payment_reference }}</strong>
                </div>
            @endif

            @if($product->boost_end_at)
                <div class="boost-ref">
                    Fin prévue : <strong>{{ $product->boost_end_at->format('d/m/Y H:i') }}</strong>
                </div>
            @endif
        </div>

        <label>Galerie d’images</label>
        <div class="image-gallery">
            @forelse($product->images ?? collect() as $img)
                <div class="image-box">
                    <img src="{{ asset('storage/' . $img->path) }}" alt="Image produit">
                    <div>
                        <input type="radio" name="main_image" value="{{ $img->id }}" {{ $img->is_main ? 'checked' : '' }}>
                        <small>Image principale</small>
                    </div>
                </div>
            @empty
                <p>Aucune image disponible.</p>
            @endforelse
        </div>

        <label for="new_images">Ajouter de nouvelles images</label>
        <input type="file" id="new_images" name="images[]" multiple>

        <button type="submit">Mettre à jour</button>
    </form>
</main>
@endsection
