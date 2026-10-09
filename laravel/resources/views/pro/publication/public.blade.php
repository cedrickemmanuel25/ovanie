@extends('pro.auth.access')
@section('title', $p->title)
@section('html_class', 'oa-natural')
@php
    $isNeed = $p->kind === 'need';
    $photos = $p->files->where('type', 'photo')->values();
    $place = collect([$p->location_detail, $p->city, $p->country])->filter()->unique(fn ($part) => mb_strtolower(trim($part)))->implode(', ');
    $deadline = $p->deadline === 'date' && $p->needed_by
        ? 'Avant le ' . $p->needed_by->format('d/m/Y')
        : ($options['deadlines'][$p->deadline] ?? null);
    $minUnit = mb_strtolower((string) ($options['units'][$p->min_order_unit] ?? ''));
    $whatsapp = (string) config('public_contact.whatsapp_number');
    $message = rawurlencode('Bonjour OVANIE, je souhaite être mis en relation pour l’annonce ' . $p->public_ref . ' (« ' . $p->title . ' »).');
    $facts = array_filter([
        $isNeed ? 'Quantité recherchée' : 'Quantité disponible' => $p->quantity_label,
        $isNeed ? 'Budget' : 'Prix' => $p->amount_label,
        'Commande minimale' => ! $isNeed && $p->min_order_quantity ? rtrim(rtrim(number_format((float) $p->min_order_quantity, 2, ',', ' '), '0'), ',') . ' ' . $minUnit : null,
        'Disponibilité' => ! $isNeed ? ($options['availability'][$p->availability] ?? null) : null,
        'Délai souhaité' => $isNeed ? $deadline : null,
        'Marque' => $p->brand,
        'Référence produit' => $p->reference,
        'Lieu' => $place ?: null,
        'Valable jusqu’au' => $p->expires_on?->format('d/m/Y'),
    ]);
@endphp
@push('styles')
<style>
    .pa-wrap { max-width: 1040px; margin: 0 auto; padding: 28px 20px 56px; }
    .pa-back { display: inline-flex; align-items: center; gap: 8px; color: #0b4fc4; font-weight: 600; text-decoration: none; margin-bottom: 18px; }
    .pa-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr); gap: 24px; align-items: start; }
    .pa-card { background: #fff; border: 1px solid #dce4ef; border-radius: 12px; padding: 24px; }
    .pa-kind { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: #fff; background: #ff5a13; }
    .pa-kind.is-need { background: #0867d9; }
    .pa-title { margin: 14px 0 6px; font-size: 28px; line-height: 1.2; color: #071b3b; }
    .pa-meta { color: #65758e; font-size: 14px; margin: 0 0 18px; }
    .pa-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .pa-photos img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; border: 1px solid #dce4ef; }
    .pa-h2 { font-size: 17px; color: #071b3b; margin: 22px 0 8px; }
    .pa-text { color: #2a3b55; line-height: 1.65; white-space: pre-line; overflow-wrap: anywhere; }
    .pa-facts { margin: 0; }
    .pa-facts div { display: flex; justify-content: space-between; gap: 16px; padding: 11px 0; border-bottom: 1px solid #eef2f7; }
    .pa-facts div:last-child { border-bottom: 0; }
    .pa-facts dt { color: #65758e; font-size: 14px; }
    .pa-facts dd { margin: 0; color: #071b3b; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
    .pa-cta { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; margin-top: 16px; padding: 14px 18px; border-radius: 10px; background: #ff5a13; color: #fff !important; font-weight: 700; text-decoration: none; }
    .pa-note { margin: 12px 0 0; color: #65758e; font-size: 13px; line-height: 1.5; }
    .pa-others { margin-top: 28px; }
    .pa-others a { display: block; padding: 12px 0; border-bottom: 1px solid #eef2f7; color: #071b3b; font-weight: 600; text-decoration: none; }
    .pa-others small { display: block; color: #65758e; font-weight: 500; margin-top: 2px; }
    @media (max-width: 860px) { .pa-grid { grid-template-columns: 1fr; } .pa-title { font-size: 23px; } .pa-card { padding: 18px; } }
</style>
@endpush
@section('content')
<div class="pa-wrap">
    <a class="pa-back" href="{{ route('catalog.business') }}#annonces">@proicon('chevron-left', 'oa-ico') Toutes les annonces</a>

    <div class="pa-grid">
        <article class="pa-card">
            <span class="pa-kind {{ $isNeed ? 'is-need' : '' }}">{{ $isNeed ? 'Besoin' : 'Offre' }}</span>
            <h1 class="pa-title">{{ $p->title }}</h1>
            <p class="pa-meta">
                Annonce {{ $p->public_ref }}
                @if($p->category) · {{ $p->category->name }}@if($p->subcategory) › {{ $p->subcategory->name }}@endif @endif
                · publiée le {{ optional($p->published_at)->format('d/m/Y') }}
            </p>

            @if($photos->isNotEmpty())
                <div class="pa-photos">
                    @foreach($photos as $photo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($photo->disk)->url($photo->path) }}" alt="{{ $p->title }}" loading="lazy">
                    @endforeach
                </div>
            @endif

            <h2 class="pa-h2">Description</h2>
            <p class="pa-text">{{ $p->description ?: 'Aucune description fournie.' }}</p>

            @if($p->specifications)
                <h2 class="pa-h2">Caractéristiques</h2>
                <p class="pa-text">{{ $p->specifications }}</p>
            @endif
        </article>

        <aside>
            <div class="pa-card">
                <dl class="pa-facts">
                    @foreach($facts as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if($whatsapp !== '')
                    <a class="pa-cta" href="https://wa.me/{{ $whatsapp }}?text={{ $message }}" target="_blank" rel="noopener">{{ $isNeed ? 'Répondre à ce besoin' : 'Je suis intéressé' }}</a>
                @endif
                <p class="pa-note">Les coordonnées de l’annonceur restent confidentielles. OVANIE vous met en relation : indiquez la référence {{ $p->public_ref }}.</p>
            </div>

            @if($others->isNotEmpty())
                <div class="pa-card pa-others">
                    <h2 class="pa-h2" style="margin-top:0">{{ $isNeed ? 'Autres besoins' : 'Autres offres' }}</h2>
                    @foreach($others as $other)
                        <a href="{{ route('pro.announcements.show', $other->id) }}">{{ $other->title }}<small>{{ $other->amount_label }} · {{ collect([$other->city, $other->country])->filter()->implode(', ') }}</small></a>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
