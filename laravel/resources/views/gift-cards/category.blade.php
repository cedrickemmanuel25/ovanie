@extends('layouts.guest')

@section('title', $category['title'].' OVANIE')
@section('meta_description', $category['description'])

@push('styles')
<style>
    :root{--orange:var(--ov-orange,#ff6a00);--orange-2:#ff7b21;--navy:var(--ov-night,#020b1c)}
    .ov-page .ov-container,.gift-pay-page .ov-container{width:min(100% - 48px,1460px);margin:0 auto}
    .ov-breadcrumb{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:22px 0 12px;color:#64748b;font-size:13px}
    .ov-breadcrumb a{color:#64748b;text-decoration:none}.ov-breadcrumb a:hover{color:var(--orange)}.ov-breadcrumb strong{color:var(--orange)}
    .category-shell{padding-bottom:56px}
    .category-head{padding:24px 0 30px;display:flex;justify-content:space-between;align-items:flex-end;gap:26px;flex-wrap:wrap}
    .category-kicker{display:inline-flex;color:var(--orange);background:#fff4ed;border:1px solid #ffd7c1;border-radius:999px;padding:8px 13px;font-weight:900;font-size:12px;letter-spacing:.7px}
    .category-head h1{margin:14px 0 10px;font-size:clamp(34px,4vw,54px);letter-spacing:-1.5px;color:#07152d}
    .category-head p{margin:0;max-width:780px;color:#667085;font-size:17px;line-height:1.7}
    .category-stat{min-width:130px;border-radius:18px;background:var(--navy);color:#fff;padding:18px 20px;text-align:center;box-shadow:0 14px 30px rgba(6,26,58,.16)}
    .category-stat strong{display:block;font-size:28px}.category-stat span{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#cbd5e1}
    .category-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
    .category-tab{text-decoration:none;border:1px solid #dfe5ed;background:#fff;color:#2d3a52;padding:11px 16px;border-radius:999px;font-size:13px;font-weight:800}
    .category-tab:hover,.category-tab.is-active{color:#fff;background:var(--navy);border-color:var(--navy)}
    .cards-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}
    .product-card{background:#fff;border:1px solid #e4e9f0;border-radius:17px;overflow:hidden;box-shadow:0 12px 30px rgba(6,26,58,.06);transition:.22s ease;display:flex;flex-direction:column}
    .product-card:hover{transform:translateY(-4px);box-shadow:0 18px 38px rgba(6,26,58,.11)}
    .product-media{position:relative;background:#f5f7fa;aspect-ratio:16/10;display:flex;align-items:center;justify-content:center;padding:10px;border-bottom:1px solid #e8edf3}
    .product-media img{width:100%;height:100%;object-fit:contain;display:block}
    .product-badge{position:absolute;top:12px;left:12px;background:var(--orange);color:#fff;padding:7px 10px;border-radius:6px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.35px;box-shadow:0 6px 14px rgba(255,90,0,.22)}
    .product-body{padding:18px;display:flex;flex-direction:column;flex:1}
    .product-title{text-decoration:none;font-weight:800;color:#101b33;font-size:16px;line-height:1.45;min-height:46px}
    .product-price{font-weight:900;font-size:23px;color:#07152d;margin-top:13px}
    .product-meta{margin:14px 0 16px;padding:12px 13px;background:#f8fafc;border:1px solid #edf1f5;border-radius:11px;color:#607086;font-size:12px;line-height:1.6;min-height:62px}
    .product-bottom{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:10px}
    .secure{display:flex;align-items:center;gap:6px;color:#516078;font-size:11px}.secure svg{width:17px;height:17px;stroke:#f0a000;fill:none;stroke-width:2}
    .buy-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#096de3;color:#fff;text-decoration:none;padding:11px 15px;border-radius:9px;font-weight:800;font-size:13px;box-shadow:0 8px 16px rgba(9,109,227,.20)}
    .buy-btn:hover{background:#075ec5}.buy-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8}
    .info-strip{margin-top:30px;display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e4e9f0;background:#fff;border-radius:16px;overflow:hidden}
    .info-strip>div{padding:18px;display:flex;align-items:center;gap:10px;border-right:1px solid #e4e9f0}.info-strip>div:last-child{border-right:0}.info-strip svg{width:22px;height:22px;stroke:#0d6efd;fill:none;stroke-width:1.8}.info-strip strong{display:block;font-size:13px}.info-strip small{display:block;color:#78869a;margin-top:2px}
    @media(max-width:1200px){.cards-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.cards-grid{grid-template-columns:repeat(2,1fr)}.info-strip{grid-template-columns:repeat(2,1fr)}.info-strip>div:nth-child(2){border-right:0}.info-strip>div:nth-child(-n+2){border-bottom:1px solid #e4e9f0}}
    @media(max-width:600px){.cards-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.product-media{padding:6px}.product-body{padding:10px}.product-title{font-size:12px;min-height:34px}.product-price{font-size:15px;margin-top:8px}.product-meta{margin:8px 0 10px;padding:8px;font-size:10px;min-height:0}.product-bottom{flex-wrap:wrap;gap:6px}.secure{display:none}.buy-btn{width:100%;padding:9px 10px;font-size:11px}.info-strip{grid-template-columns:none;display:flex;flex-wrap:nowrap;overflow-x:auto;gap:10px;padding-bottom:6px;border:0;background:transparent;border-radius:0;box-shadow:none}.info-strip>div{flex:0 0 78%;border:1px solid #e4e9f0;border-radius:12px;background:#fff}.category-stat{width:100%}}
</style>
@endpush

@section('content')
<section class="ov-page category-shell">
    <div class="ov-container">
        <div class="ov-breadcrumb">
            <a href="{{ route('home') }}">Accueil</a><span>›</span>
            <a href="{{ route('gift-cards.index') }}">Cartes OVANIE</a><span>›</span>
            <strong>{{ $category['title'] }}</strong>
        </div>

        <section class="category-head">
            <div>
                <span class="category-kicker">{{ $category['eyebrow'] }}</span>
                <h1>{{ $category['title'] }}</h1>
                <p>{{ $category['long_description'] }}</p>
            </div>
            <div class="category-stat"><strong>{{ $cards->count() }}</strong><span>cartes disponibles</span></div>
        </section>

        <nav class="category-tabs" aria-label="Changer de catégorie">
            @foreach($categories as $item)
                <a href="{{ route('gift-cards.category', $item['key']) }}" class="category-tab {{ $item['key'] === $categoryKey ? 'is-active' : '' }}">{{ $item['title'] }}</a>
            @endforeach
            <a href="{{ route('gift-cards.index') }}" class="category-tab">← Les 3 choix</a>
        </nav>

        <section class="cards-grid">
            @forelse($cards as $item)
                @php $product = $item['product']; @endphp
                <article class="product-card">
                    <a class="product-media" href="{{ route('gift-cards.show', $product) }}">
                        <span class="product-badge">{{ $item['type'] }}</span>
                        <img src="{{ asset($product->image_path) }}" alt="{{ $item['title'] }}" loading="lazy">
                    </a>
                    <div class="product-body">
                        <a class="product-title" href="{{ route('gift-cards.show', $product) }}">{{ $item['title'] }}</a>
                        <div class="product-price">{{ number_format((float)$product->activation_price,0,',',' ') }} FCFA</div>
                        <div class="product-meta">
                            @if($product->validity_days)
                                <strong>Validité :</strong> {{ $product->validity_days }} jours à partir de l’activation.<br>
                            @else
                                <strong>Validité :</strong> {{ $product->validity_months }} mois à partir de l’activation.<br>
                            @endif
                            @if($product->is_rechargeable)
                                <strong>Recharge :</strong> {{ $product->max_total_recharge ? 'jusqu’à '.number_format((float)$product->max_total_recharge,0,',',' ').' FCFA' : 'illimitée' }}.
                            @else
                                <strong>Utilisation :</strong> en une ou plusieurs fois.
                            @endif
                        </div>
                        <div class="product-bottom">
                            <span class="secure"><svg viewBox="0 0 24 24"><path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"></path><path d="m9 12 2 2 4-4"></path></svg>OVANIE sécurisé</span>
                            <a class="buy-btn" href="{{ route('gift-cards.show', $product) }}"><svg viewBox="0 0 24 24"><path d="M6 8h12l1 12H5L6 8Z"></path><path d="M9 9V6a3 3 0 0 1 6 0v3"></path></svg>Voir la fiche</a>
                        </div>
                    </div>
                </article>
            @empty
                <div style="grid-column:1/-1;background:#fff;border:1px dashed #d7dee8;border-radius:16px;padding:34px;text-align:center;color:#667085">Aucune carte active dans cette catégorie.</div>
            @endforelse
        </section>

        <div class="info-strip">
            <div><svg viewBox="0 0 24 24"><path d="M12 3 20 7v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"></path></svg><span><strong>Paiement sécurisé</strong><small>Protection OVANIE</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path><path d="M7 8h10M7 12h7"></path></svg><span><strong>Code généré après paiement</strong><small>Unique et personnel</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M4 13a8 8 0 0 1 16 0"></path><path d="M4 13v5h3v-5H4Zm13 0v5h3v-5h-3Z"></path></svg><span><strong>Assistance 7j/7</strong><small>Support client OVANIE</small></span></div>
            <div><svg viewBox="0 0 24 24"><path d="M3 7h18v10H3z"></path><path d="M7 11h6"></path></svg><span><strong>Paiement mixte</strong><small>Complétez si nécessaire</small></span></div>
        </div>
    </div>
</section>
@endsection
