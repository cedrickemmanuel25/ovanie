@extends('layouts.app')

@section('title', 'Calculateur BTP')

@section('content')
<section class="calculator-page">
    <div class="calculator-shell">
        <div class="calculator-hero">
            <div>
                <span class="eyebrow">Calculateur BTP OVANIE</span>
                <h1>Estimez vos quantites avant d'acheter</h1>
                <p>Renseignez les dimensions du chantier, ajoutez une marge de perte et obtenez une estimation exploitable avec des produits recommandes.</p>
            </div>
            <div class="hero-metrics">
                <strong>5</strong>
                <span>categories MVP</span>
            </div>
        </div>

        <div class="calculator-grid">
            <form method="POST" action="{{ route('calculator.estimate') }}" class="calculator-card">
                @csrf
                <div class="field">
                    <label for="category">Categorie</label>
                    <select id="category" name="category" required>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(($input['category'] ?? 'ciment') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="length">Longueur (m)</label>
                        <input id="length" name="length" type="number" step="0.01" min="0" value="{{ old('length', $input['length'] ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="width">Largeur (m)</label>
                        <input id="width" name="width" type="number" step="0.01" min="0" value="{{ old('width', $input['width'] ?? '') }}">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="height">Hauteur (m)</label>
                        <input id="height" name="height" type="number" step="0.01" min="0" value="{{ old('height', $input['height'] ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="thickness">Epaisseur (m)</label>
                        <input id="thickness" name="thickness" type="number" step="0.01" min="0" value="{{ old('thickness', $input['thickness'] ?? '') }}">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="surface">Surface directe (m2)</label>
                        <input id="surface" name="surface" type="number" step="0.01" min="0" value="{{ old('surface', $input['surface'] ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="layers">Nombre de couches</label>
                        <input id="layers" name="layers" type="number" min="1" max="10" value="{{ old('layers', $input['layers'] ?? 1) }}">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="waste_margin">Marge de perte (%)</label>
                        <input id="waste_margin" name="waste_margin" type="number" step="0.1" min="0" max="50" value="{{ old('waste_margin', $input['waste_margin'] ?? 10) }}">
                    </div>
                    <div class="field">
                        <label for="unit_budget">Budget unitaire</label>
                        <input id="unit_budget" name="unit_budget" type="number" step="1" min="0" value="{{ old('unit_budget', $input['unit_budget'] ?? '') }}">
                    </div>
                </div>

                @if ($errors->any())
                    <div class="form-errors">{{ $errors->first() }}</div>
                @endif

                <button type="submit" class="primary-action">Calculer l'estimation</button>
            </form>

            <aside class="calculator-card result-card">
                @if($estimate)
                    <span class="eyebrow">{{ ucfirst($estimate['category']) }}</span>
                    <h2>{{ number_format($estimate['quantity'], 2, ',', ' ') }} {{ $estimate['unit'] }}</h2>
                    <p>{{ $estimate['basis'] }}. Marge incluse : {{ $estimate['waste_margin'] }}%.</p>

                    <div class="result-list">
                        <span>Surface : <strong>{{ $estimate['surface_m2'] }} m2</strong></span>
                        <span>Volume : <strong>{{ $estimate['volume_m3'] }} m3</strong></span>
                        <span>Budget : <strong>{{ $estimate['budget_estimate'] ? number_format($estimate['budget_estimate'], 0, ',', ' ') . ' FCFA' : 'Non renseigne' }}</strong></span>
                    </div>
                @else
                    <span class="eyebrow">Resultat</span>
                    <h2>Votre estimation apparait ici</h2>
                    <p>Le calculateur accepte une surface directe ou des dimensions. La marge de perte est appliquee automatiquement.</p>
                @endif
            </aside>
        </div>

        @if($estimate)
            <div class="calculator-card recommendations">
                <div class="section-heading">
                    <h2>Produits recommandes</h2>
                    <span>{{ count($estimate['recommended_products']) }} resultat(s)</span>
                </div>

                @if(count($estimate['recommended_products']))
                    <div class="product-row">
                        @foreach($estimate['recommended_products'] as $product)
                            <article class="product-mini-card">
                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                                <div>
                                    <h3>{{ $product['name'] }}</h3>
                                    <p>{{ number_format($product['price'], 0, ',', ' ') }} FCFA</p>
                                </div>
                                @auth
                                    <form method="POST" action="{{ route('calculator.addToCart') }}">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                        <input type="hidden" name="quantity" value="{{ max((int) ceil($estimate['quantity']), 1) }}">
                                        <button type="submit">Ajouter</button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}">Ajouter</a>
                                @endauth
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="empty-state">Aucun produit correspondant trouve pour cette estimation.</p>
                @endif
            </div>
        @endif
    </div>
</section>

<style>
    .calculator-page { background:#eef3f8; padding:48px 24px; color:#061531; }
    .calculator-shell { max-width:1280px; margin:0 auto; }
    .calculator-hero { display:flex; justify-content:space-between; gap:32px; align-items:flex-end; background:linear-gradient(135deg,#061531,#075bc7); color:white; border-radius:8px; padding:38px 42px; box-shadow:0 18px 40px rgba(6,21,49,.14); }
    .calculator-hero h1 { margin:8px 0 12px; font-size:42px; line-height:1.05; letter-spacing:0; }
    .calculator-hero p { margin:0; max-width:760px; color:#dbeafe; font-size:18px; line-height:1.6; }
    .eyebrow { color:#7dd3fc; font-weight:800; text-transform:uppercase; letter-spacing:1.6px; font-size:13px; }
    .hero-metrics { min-width:150px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18); border-radius:8px; padding:20px; text-align:center; }
    .hero-metrics strong { display:block; font-size:42px; }
    .hero-metrics span { color:#dbeafe; font-weight:700; }
    .calculator-grid { display:grid; grid-template-columns:1.25fr .75fr; gap:24px; margin-top:24px; }
    .calculator-card { background:#fff; border:1px solid #d7e2ef; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(6,21,49,.08); }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .field { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
    .field label { font-weight:800; color:#0f2344; }
    .field input, .field select { height:48px; border:1px solid #cbd8e8; border-radius:6px; padding:0 14px; font-size:16px; background:#f8fafc; }
    .primary-action, .product-mini-card button { border:0; background:#075bd8; color:white; border-radius:6px; font-weight:900; height:52px; padding:0 22px; cursor:pointer; }
    .result-card h2 { font-size:36px; margin:12px 0; color:#061531; }
    .result-card p { color:#53657f; line-height:1.6; }
    .result-list { display:grid; gap:12px; margin-top:24px; }
    .result-list span { display:flex; justify-content:space-between; padding:14px; border-radius:6px; background:#f1f5f9; color:#53657f; }
    .section-heading { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
    .section-heading h2 { margin:0; }
    .product-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .product-mini-card { display:grid; grid-template-columns:86px 1fr auto; gap:14px; align-items:center; border:1px solid #dce6f2; border-radius:8px; padding:12px; }
    .product-mini-card img { width:86px; height:86px; object-fit:cover; border-radius:6px; background:#eef3f8; }
    .product-mini-card h3 { margin:0 0 6px; font-size:16px; }
    .product-mini-card p { margin:0; font-weight:900; color:#061531; }
    .product-mini-card a { color:#075bd8; font-weight:900; text-decoration:none; }
    .form-errors { margin:4px 0 16px; color:#b91c1c; font-weight:800; }
    .empty-state { color:#53657f; margin:0; }
    @media (max-width:900px) {
        .calculator-page { padding:18px 12px; }
        .calculator-hero, .calculator-grid { grid-template-columns:1fr; display:grid; }
        .calculator-hero { padding:22px 20px; gap:16px; }
        .field-row { grid-template-columns:1fr; }
        .calculator-hero h1 { font-size:26px; }
        .calculator-card { padding:16px; }
        /* Produits recommandes : une seule ligne en scroll horizontal plutot
           qu'une liste empilee en pleine largeur. */
        .product-row {
            display:flex;
            flex-wrap:nowrap;
            grid-template-columns:none;
            overflow-x:auto;
            gap:10px;
            padding-bottom:6px;
            scroll-snap-type:x mandatory;
            -webkit-overflow-scrolling:touch;
        }
        .product-mini-card {
            flex:0 0 80%;
            grid-template-columns:64px 1fr;
            scroll-snap-align:start;
        }
    }
</style>
@endsection
