@extends('pro.auth.access')
@section('title', $kind === 'offer' ? 'Vérifiez votre offre' : 'Vérifiez votre besoin')
@include('pro.publication.partials.setup')
@section('container_class', 'op-wide')
@section('content')
@php
    $p = $publication;
    $isOffer = $kind === 'offer';
    $edit = fn (string $anchor) => route('pro.publish.edit', ['type' => $type, 'publication' => $p]) . '#' . $anchor;
    $fmt = fn ($n) => $n === null ? null : number_format((float) $n, (float) $n == floor((float) $n) ? 0 : 2, ',', ' ');
    $unit = $options['units'][$p->unit] ?? '';
    $unitShort = trim(preg_replace('/\s*\(.*\)/', '', mb_strtolower($unit)));
    $plural = fn ($word, $n) => $word !== '' && (float) $n > 1 && ! preg_match('/(s|x|²|³|kg)$/u', $word) ? $word . 's' : $word;
    $minUnit = trim(preg_replace('/\s*\(.*\)/', '', mb_strtolower($options['units'][$p->min_order_unit] ?? '')));
    $photos = $p->files->where('type', 'photo')->values();
    $docs = $p->files->where('type', 'document')->values();
    $flag = ['Côte d’Ivoire' => 'ci', 'Sénégal' => 'sn', 'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Ghana' => 'gh', 'Guinée' => 'gn', 'Togo' => 'tg', 'Bénin' => 'bj', 'Niger' => 'ne', 'Cameroun' => 'cm', 'Nigeria' => 'ng', 'Maroc' => 'ma', 'France' => 'fr', 'Chine' => 'cn', 'Turquie' => 'tr', 'Émirats arabes unis' => 'ae'][$p->country] ?? null;
    $deadline = $p->deadline === 'date' && $p->needed_by ? 'Avant le ' . $p->needed_by->format('d/m/Y') : ($options['deadlines'][$p->deadline] ?? '—');
    $visible = $isOffer
        ? ['Titre de l’offre', 'Prix indicatif', 'Description', 'Ville / zone', 'Catégorie', 'Photos', 'Quantité']
        : ['Titre du besoin', 'Destination (ville / zone)', 'Description', 'Délai demandé', 'Catégorie', 'Documents autorisés', 'Quantité'];
    $ready = collect($checks)->every('ok');
@endphp
<form method="POST" action="{{ route('pro.publish.publish', ['type' => $type, 'publication' => $p]) }}" class="op-layout op-layout--review" novalidate>
    @csrf
    <div class="op-main op-main--review">
        @include('pro.publication.partials.topline', ['step' => 3, 'kind' => $kind])
        <h1 class="op-title">{{ $isOffer ? 'Vérifiez votre offre' : 'Vérifiez votre besoin' }}</h1>
        <p class="op-lead op-lead--sm">{{ $isOffer ? 'Relisez les informations avant de publier votre offre sur OVANIE PRO.' : 'Relisez votre demande avant de la publier auprès des professionnels OVANIE PRO.' }}</p>

        @include('pro.auth.access.alerts', ['except' => ['confirm_accuracy', 'confirm_rights', 'accept_rules']])

        <section class="op-card op-rv">
            <header class="op-rv__head"><h2 class="op-section op-section--sm"><span>1</span>{{ $isOffer ? 'Votre offre' : 'Votre besoin' }}</h2><a class="op-edit" href="{{ $edit('section-main') }}">@proicon('wrench', 'oa-ico') Modifier</a></header>
            <dl class="op-rv__list">
                <div>@proicon('boxes', 'oa-ico')<dt>{{ $isOffer ? 'Type d’offre' : 'Type de besoin' }}</dt><dd>{{ $natures[$p->nature][0] ?? '—' }}</dd></div>
                <div>@proicon('file', 'oa-ico')<dt>{{ $isOffer ? 'Titre' : 'Titre du besoin' }}</dt><dd>{{ $p->title ?: '—' }}</dd></div>
                <div>@proicon('store', 'oa-ico')<dt>Catégorie</dt><dd>{{ $p->category?->name ?: '—' }}</dd></div>
                <div>@proicon('tag', 'oa-ico')<dt>Sous-catégorie</dt><dd>{{ $p->subcategory?->name ?: '—' }}</dd></div>
                <div class="op-rv__desc">@proicon('file', 'oa-ico')<dt>Description</dt><dd>{!! nl2br(e($p->description ?: '—')) !!}</dd></div>
            </dl>
        </section>

        <section class="op-card op-rv">
            <header class="op-rv__head"><h2 class="op-section op-section--sm"><span>2</span>{{ $isOffer ? 'Quantité & conditions' : 'Quantité & caractéristiques' }}</h2><a class="op-edit" href="{{ $edit('section-quantity') }}">@proicon('wrench', 'oa-ico') Modifier</a></header>
            @if($isOffer)
                <div class="op-rv__facts op-rv__facts--3">
                    <div>@proicon('boxes', 'oa-ico op-orange')<span><small>Quantité disponible</small><b>{{ $fmt($p->quantity) }} {{ $plural($unitShort, $p->quantity) }}</b></span></div>
                    <div>@proicon('tag', 'oa-ico op-orange')<span><small>Prix indicatif</small><b>{{ $p->price_on_request ? 'Sur demande' : ($p->price !== null ? $fmt($p->price) . ' ' . $p->currency . ' / ' . $unitShort : 'Non communiqué') }}</b></span></div>
                    <div>@proicon('handshake', 'oa-ico op-orange')<span><small>Prix négociable</small><b>@if($p->price_negotiable)<i class="op-ok">@proicon('check', 'oa-ico')</i> Oui @else Non @endif</b></span></div>
                    <div>@proicon('cart', 'oa-ico op-orange')<span><small>Commande minimale</small><b>{{ $p->min_order_quantity ? $fmt($p->min_order_quantity) . ' ' . $plural($minUnit, $p->min_order_quantity) : 'Aucune' }}</b></span></div>
                    <div>@proicon('clock', 'oa-ico op-orange')<span><small>Disponibilité</small><b>{{ $options['availability'][$p->availability] ?? '—' }}</b></span></div>
                </div>
            @else
                <div class="op-rv__facts op-rv__facts--2">
                    <div>@proicon('boxes', 'oa-ico op-orange')<span><small>Quantité recherchée</small><b>{{ $p->quantity_to_define ? 'À définir avec le professionnel' : $fmt($p->quantity) }}</b></span></div>
                    <div>@proicon('tag', 'oa-ico op-orange')<span><small>Marque souhaitée</small><b>{{ $p->brand ?: 'Non précisée' }}</b></span></div>
                    <div>@proicon('boxes', 'oa-ico op-orange')<span><small>Unité</small><b>{{ $p->quantity_to_define ? '—' : $unit }}</b></span></div>
                    <div>@proicon('file', 'oa-ico op-orange')<span><small>Référence / modèle</small><b>{{ $p->reference ?: 'Non précisée' }}</b></span></div>
                    <div>@proicon('file', 'oa-ico op-orange')<span><small>Spécifications recherchées</small><b>{{ $p->specifications ?: 'Non précisées' }}</b></span></div>
                    <div>@proicon('handshake', 'oa-ico op-orange')<span><small>Alternatives acceptées</small><b>@if($p->alternatives_accepted)<i class="op-ok">@proicon('check', 'oa-ico')</i> Oui @else Non @endif</b></span></div>
                </div>
            @endif
        </section>

        <section class="op-card op-rv">
            <header class="op-rv__head"><h2 class="op-section op-section--sm"><span>3</span>{{ $isOffer ? 'Localisation & validité' : 'Destination & délai' }}</h2><a class="op-edit" href="{{ $edit('section-location') }}">@proicon('wrench', 'oa-ico') Modifier</a></header>
            <div class="op-rv__facts op-rv__facts--row">
                <div>@proicon('map-pin', 'oa-ico op-orange')<span><small>Pays</small><b>@if($flag)<img class="op-flag" src="{{ asset('ovanie-pro/flags/' . $flag . '.svg') }}" alt="">@endif{{ $p->country }}</b></span></div>
                <div>@proicon('building', 'oa-ico')<span><small>Ville / zone</small><b>{{ $p->city }}</b></span></div>
                <div>@proicon('map-pin', 'oa-ico')<span><small>{{ $isOffer ? 'Lieu d’origine ou d’expédition' : 'Commune / quartier' }}</small><b>{{ $p->location_detail ?: 'Non précisé' }}</b></span></div>
                @if($isOffer)
                    <div>@proicon('clock', 'oa-ico')<span><small>Durée de validité</small><b>{{ $p->validity_days ? $p->validity_days . ' jours' : 'Date choisie' }}</b></span></div>
                    <div>@proicon('clock', 'oa-ico')<span><small>Date d’expiration</small><b>{{ $p->expires_on?->format('d/m/Y') ?? '—' }}</b></span></div>
                @else
                    <div>@proicon('clock', 'oa-ico')<span><small>Délai demandé</small><b>{{ $deadline }}</b></span></div>
                @endif
            </div>
        </section>

        @unless($isOffer)
            <section class="op-card op-rv">
                <header class="op-rv__head"><h2 class="op-section op-section--sm"><span>4</span>Budget</h2><a class="op-edit" href="{{ $edit('section-budget') }}">@proicon('wrench', 'oa-ico') Modifier</a></header>
                <p class="op-rv__budget">@proicon('tag', 'oa-ico op-orange') Budget indicatif <span>{{ $p->budget_mode === 'range' ? $fmt($p->budget_min) . ' – ' . $fmt($p->budget_max) . ' XOF' : 'Non communiqué — recevoir des propositions' }}</span></p>
            </section>
        @endunless

        <section class="op-card op-rv">
            <header class="op-rv__head"><h2 class="op-section op-section--sm"><span>{{ $isOffer ? 4 : 5 }}</span>{{ $isOffer ? 'Photos & documents' : 'Documents & images' }}</h2><a class="op-edit" href="{{ $edit('section-media') }}">@proicon('wrench', 'oa-ico') Modifier les médias</a></header>
            <div class="op-rv__media">
                @forelse($photos->take(3) as $photo)
                    <figure class="op-rv__photo"><img src="{{ $photo->url }}" alt="">@if($loop->first)<figcaption>Photo principale</figcaption>@endif</figure>
                @empty
                    <p class="op-muted">Aucune photo ajoutée.</p>
                @endforelse
                <div class="op-rv__count">@proicon('upload', 'oa-ico op-blue')<span><b>{{ $photos->count() }} photo{{ $photos->count() > 1 ? 's' : '' }}</b><small>JPG, PNG, WEBP</small></span></div>
                <div class="op-rv__count">@proicon('file', 'oa-ico op-blue')<span><b>{{ $docs->count() }} document{{ $docs->count() > 1 ? 's' : '' }} joint{{ $docs->count() > 1 ? 's' : '' }}</b><small>{{ $docs->first()?->original_name ?? 'Aucun document' }}</small></span></div>
            </div>
        </section>

        <section class="op-card op-rv">
            <h2 class="op-section op-section--sm"><span>{{ $isOffer ? 5 : 6 }}</span>Confidentialité et visibilité</h2>
            <div class="op-visibility">
                <div class="op-visibility__public">
                    @proicon('eye', 'oa-ico')
                    <div><b>Informations visibles{{ $isOffer ? '' : ' publiquement' }}</b><small>Ces informations seront visibles par les professionnels sur OVANIE PRO.</small>
                        <ul>@foreach($visible as $item)<li>@proicon('check', 'oa-ico'){{ $item }}</li>@endforeach</ul></div>
                </div>
                <div class="op-visibility__private">
                    @proicon('lock', 'oa-ico')
                    <div><b>Informations privées</b><small>Ces informations restent confidentielles.</small>
                        <ul>@foreach(['Votre adresse e-mail', 'Votre numéro de téléphone', 'Votre adresse personnelle exacte', 'Vos informations de compte'] as $item)<li>@proicon('lock', 'oa-ico'){{ $item }}</li>@endforeach</ul></div>
                </div>
            </div>
        </section>

        <div class="op-bottom op-bottom--left">
            <a class="oa-btn oa-btn--outline" href="{{ route('pro.publish.edit', ['type' => $type, 'publication' => $p]) }}">@proicon('chevron-left', 'oa-ico') {{ $isOffer ? 'Retour à mon offre' : 'Retour à mon besoin' }}</a>
        </div>
    </div>

    <aside class="op-side op-side--review">
        @include('pro.publication.partials.preview', ['publication' => $p, 'live' => false])

        <section class="op-card op-before">
            <h2>Avant de publier</h2>
            @unless($isOffer)<p class="op-muted">Vérifiez les points suivants avant de publier votre besoin.</p>@endunless
            <ul class="op-before__checks">
                @foreach($checks as $check)
                    <li class="{{ $check['ok'] ? 'is-ok' : 'is-ko' }}">
                        <span class="op-before__dot">@proicon($check['ok'] ? 'check' : 'x', 'oa-ico')</span>
                        <span><b>{{ $check['title'] }}</b><small>{{ $check['text'] }}</small></span>
                    </li>
                @endforeach
            </ul>
            <div class="op-confirms">
                <label class="op-check @error('confirm_accuracy') is-invalid @enderror"><input type="checkbox" name="confirm_accuracy" value="1" @checked(old('confirm_accuracy'))><span>Je confirme que les informations publiées sont exactes. <em>*</em></span></label>
                <label class="op-check @error('confirm_rights') is-invalid @enderror"><input type="checkbox" name="confirm_rights" value="1" @checked(old('confirm_rights'))><span>Je confirme {{ $isOffer ? 'disposer des droits nécessaires sur les photos et documents ajoutés' : 'avoir le droit de partager les fichiers ajoutés' }}. <em>*</em></span></label>
                <label class="op-check @error('accept_rules') is-invalid @enderror"><input type="checkbox" name="accept_rules" value="1" @checked(old('accept_rules'))><span>J’accepte les <a href="{{ route('terms') }}" target="_blank" rel="noopener">règles de publication</a> OVANIE PRO. <em>*</em></span></label>
                @foreach(['confirm_accuracy', 'confirm_rights', 'accept_rules'] as $f)@error($f)<p class="op-error">{{ $message }}</p>@enderror @endforeach
            </div>
            <div class="op-warning">@proicon('alert', 'oa-ico')<span>{{ $isOffer
                ? 'OVANIE PRO peut modérer ou retirer une publication qui ne respecte pas les règles de la plateforme.'
                : 'Cette publication est un besoin simple. Elle ne constitue pas automatiquement une RFQ ou un appel d’offres professionnel.' }}</span></div>
        </section>

        <div class="op-publish">
            <div class="op-publish__buttons">
                <a class="oa-btn oa-btn--outline" href="{{ route('pro.account') }}" title="Votre {{ $isOffer ? 'offre' : 'besoin' }} est déjà enregistré en brouillon">@proicon('file', 'oa-ico') Enregistrer comme brouillon</a>
                <button class="oa-btn oa-btn--primary" type="submit" @disabled(! $ready)>{{ $isOffer ? 'Publier mon offre' : 'Publier mon besoin' }} @proicon('arrow-right', 'oa-ico')</button>
            </div>
            <p class="op-muted">@proicon('lock', 'oa-ico') Vos coordonnées personnelles ne seront pas affichées publiquement.</p>
        </div>
    </aside>
</form>
@endsection
