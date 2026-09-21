@extends('layouts.vendor')

@section('title', 'Avis clients | OVANIE')

@section('styles')
<style>
    :root{
        --rv-navy:#0b2d83;
        --rv-blue:#165dff;
        --rv-blue-soft:#eef4ff;
        --rv-amber:#f59e0b;
        --rv-green:#16a34a;
        --rv-text:#112b66;
        --rv-muted:#7182a8;
        --rv-line:#e6ebf5;
        --rv-bg:#f8faff;
        --rv-card:#ffffff;
        --rv-shadow:0 5px 18px rgba(16,43,102,.045);
    }

    .rv-page{width:100%;max-width:1200px;margin:0 auto;padding:2px 0 34px;color:var(--rv-text)}
    .rv-page *{box-sizing:border-box}
    .rv-page a{text-decoration:none}
    .rv-breadcrumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:2px 0 18px;font-size:12px;font-weight:750;color:#8a9abb}
    .rv-breadcrumb a{color:var(--rv-blue)}
    .rv-breadcrumb svg{width:13px;height:13px;color:#b3bfd5}

    .rv-heading h1{margin:0;color:var(--rv-navy);font-size:28px;line-height:1.15;letter-spacing:-.6px;font-weight:850}
    .rv-heading p{margin:7px 0 22px;color:#7084b0;font-size:13px;font-weight:520}

    .rv-top-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
    .rv-stat-card{min-height:100px;background:var(--rv-card);border:1px solid var(--rv-line);border-radius:10px;padding:16px 18px;box-shadow:var(--rv-shadow)}
    .rv-stat-card small{display:block;color:#526b9f;font-size:11.5px;font-weight:700;margin-bottom:5px}
    .rv-stat-card strong{display:block;color:var(--rv-navy);font-size:24px;line-height:1.05;font-weight:850}
    .rv-stat-card .rv-stars{color:var(--rv-amber);font-size:13px;margin-top:4px}

    .rv-filters{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px}
    .rv-filter-pill{height:34px;padding:0 14px;border-radius:999px;border:1px solid var(--rv-line);background:#fff;color:var(--rv-muted);font-size:11.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px}
    .rv-filter-pill.is-active{border-color:var(--rv-blue);background:var(--rv-blue-soft);color:var(--rv-blue)}
    .rv-filter-checkbox{display:inline-flex;align-items:center;gap:7px;font-size:11.5px;font-weight:700;color:var(--rv-muted);margin-left:auto}

    .rv-list{display:grid;gap:12px}
    .rv-card{background:var(--rv-card);border:1px solid var(--rv-line);border-radius:10px;padding:18px 20px;box-shadow:var(--rv-shadow)}
    .rv-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .rv-card-head strong{display:block;color:var(--rv-navy);font-size:13.5px;font-weight:800}
    .rv-card-head small{display:block;color:var(--rv-muted);font-size:11px;font-weight:600;margin-top:3px}
    .rv-stars{color:var(--rv-amber);font-size:14px;letter-spacing:1px}
    .rv-comment{margin:12px 0 0;color:#33456f;font-size:12.5px;line-height:1.6}
    .rv-reply{margin-top:14px;padding:12px 14px;border-radius:8px;background:var(--rv-blue-soft);border:1px solid #d7e4ff}
    .rv-reply strong{display:block;color:var(--rv-navy);font-size:11px;font-weight:800;margin-bottom:4px}
    .rv-reply p{margin:0;color:#25406f;font-size:12px;line-height:1.55}
    .rv-reply-form{margin-top:14px;display:grid;gap:8px}
    .rv-reply-form textarea{width:100%;min-height:70px;border:1px solid var(--rv-line);border-radius:7px;padding:10px 12px;font-size:12.5px;font-family:inherit;resize:vertical;color:var(--rv-text)}
    .rv-reply-form textarea:focus{outline:none;border-color:var(--rv-blue);box-shadow:0 0 0 3px rgba(22,93,255,.09)}
    .rv-reply-form button{align-self:flex-end;height:36px;padding:0 16px;border-radius:7px;border:1px solid var(--rv-blue);background:var(--rv-blue);color:#fff;font-size:11.5px;font-weight:800;cursor:pointer}
    .rv-empty{text-align:center;padding:60px 20px;color:var(--rv-muted);font-size:13px}
    .rv-empty i{width:36px;height:36px;color:#c3cfe6;margin-bottom:10px}

    @media (max-width:900px){ .rv-top-stats{grid-template-columns:repeat(2,minmax(0,1fr))} }
</style>
@endsection

@section('content')
@php
    $moyenne = $summary['average'];
    $ratingOptions = [5, 4, 3, 2, 1];
@endphp

<div class="rv-page">
    <nav class="rv-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('vendor.dashboard') }}">Espace vendeur</a>
        <i data-lucide="chevron-right"></i>
        <span>Avis clients</span>
    </nav>

    <div class="rv-heading">
        <h1>Avis clients</h1>
        <p>Consultez les avis laissés sur vos produits et répondez-y directement.</p>
    </div>

    <section class="rv-top-stats" aria-label="Indicateurs">
        <article class="rv-stat-card">
            <small>Note moyenne</small>
            <strong>{{ $moyenne !== null ? number_format($moyenne, 1, ',', ' ') : '—' }}</strong>
            @if($moyenne !== null)
                <span class="rv-stars">{{ str_repeat('★', (int) round($moyenne)) }}{{ str_repeat('☆', 5 - (int) round($moyenne)) }}</span>
            @endif
        </article>
        <article class="rv-stat-card">
            <small>Total avis</small>
            <strong>{{ $summary['count'] }}</strong>
        </article>
        <article class="rv-stat-card">
            <small>Répondus</small>
            <strong>{{ $summary['replied'] }}</strong>
        </article>
        <article class="rv-stat-card">
            <small>Ce mois-ci</small>
            <strong>{{ $summary['this_month'] }}</strong>
        </article>
    </section>

    <div class="rv-filters">
        <a href="{{ route('vendor.reviews.index', array_filter(['unanswered' => $onlyUnanswered ? 1 : null])) }}" class="rv-filter-pill {{ $rating === 'all' ? 'is-active' : '' }}">Toutes les notes</a>
        @foreach($ratingOptions as $option)
            <a href="{{ route('vendor.reviews.index', array_filter(['rating' => $option, 'unanswered' => $onlyUnanswered ? 1 : null])) }}" class="rv-filter-pill {{ (string) $rating === (string) $option ? 'is-active' : '' }}">{{ $option }} ★</a>
        @endforeach

        <a href="{{ route('vendor.reviews.index', array_filter(['rating' => $rating !== 'all' ? $rating : null, 'unanswered' => $onlyUnanswered ? null : 1])) }}" class="rv-filter-pill {{ $onlyUnanswered ? 'is-active' : '' }}" style="margin-left:auto;">
            {{ $onlyUnanswered ? '✓ ' : '' }}Sans réponse uniquement
        </a>
    </div>

    <div class="rv-list">
        @forelse($filtered as $review)
            <article class="rv-card">
                <div class="rv-card-head">
                    <div>
                        <strong>{{ $review->user?->name ?? 'Client OVANIE' }}</strong>
                        <small>{{ $review->product?->name ?? 'Produit supprimé' }} · {{ optional($review->created_at)->format('d/m/Y') }}</small>
                    </div>
                    <span class="rv-stars">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                </div>

                @if(filled($review->comment))
                    <p class="rv-comment">{{ $review->comment }}</p>
                @endif

                @if(filled($review->vendor_reply))
                    <div class="rv-reply">
                        <strong>Votre réponse @if($review->vendor_replied_at)· {{ $review->vendor_replied_at->format('d/m/Y') }}@endif</strong>
                        <p>{{ $review->vendor_reply }}</p>
                    </div>
                @else
                    <form class="rv-reply-form" method="POST" action="{{ route('vendor.reviews.reply', $review) }}">
                        @csrf
                        <textarea name="reply" placeholder="Répondre à cet avis…" required maxlength="2000"></textarea>
                        <button type="submit">Publier la réponse</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="rv-empty">
                <i data-lucide="star-off"></i>
                <p>Aucun avis pour le moment.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
