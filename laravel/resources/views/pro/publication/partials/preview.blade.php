{{--
    Aperçu public d'une offre / d'un besoin. Variables : $publication, $kind, $options, $live (bool : mis à jour par le script).
    Aucune coordonnée personnelle n'y figure.
--}}
@php
    $pp = $publication;
    $isOfferP = $kind === 'offer';
    $fmt = fn ($n) => $n === null || $n === '' ? null : number_format((float) $n, (float) $n == floor((float) $n) ? 0 : 2, ',', ' ');
    $unitLabel = $options['units'][$pp->unit] ?? '';
    $unitShort = trim(preg_replace('/\s*\(.*\)/', '', mb_strtolower($unitLabel)));
    $unitPlural = $unitShort !== '' && (float) $pp->quantity > 1 && ! preg_match('/(s|x|²|³|kg)$/u', $unitShort) ? $unitShort . 's' : $unitShort;
    $photo = $pp->exists ? $pp->files->where('type', 'photo')->first() : null;
    $days = $pp->validity_days ?: ($pp->expires_on ? max(1, now()->startOfDay()->diffInDays($pp->expires_on)) : 30);
    $deadlineLabel = $pp->deadline === 'date' && $pp->needed_by ? 'Avant le ' . $pp->needed_by->format('d/m/Y') : ($options['deadlines'][$pp->deadline] ?? 'Dès que possible');
@endphp
<section class="op-card op-preview" id="op-preview" @if(!empty($live)) data-op-preview @endif>
    <div class="op-preview__head">
        <div>
            <h2>{{ !empty($live) ? ($isOfferP ? 'Aperçu de votre offre' : 'Aperçu de votre besoin') : 'Aperçu public' }}</h2>
            <p>Voici comment votre {{ $isOfferP ? 'offre' : 'besoin' }} {{ !empty($live) ? 'pourra apparaître' : 'apparaîtra' }} aux professionnels.</p>
        </div>
        @if($isOfferP)
            <span class="op-pill op-pill--green">@proicon('clock', 'oa-ico') Valide <b data-pv="days">{{ $days }}</b> jours</span>
        @else
            <span class="op-pill op-pill--blue">@proicon('info', 'oa-ico') {{ !empty($live) ? 'Besoin' : 'Besoin actif' }}</span>
        @endif
    </div>
    <div class="op-preview__img">
        <img src="{{ $photo?->url ?: 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' }}" alt="" data-pv="img" @unless($photo) hidden @endunless>
        <span class="op-preview__placeholder" data-pv="img-empty" @if($photo) hidden @endif>@proicon('upload', 'oa-ico')<small>{{ $isOfferP ? 'Ajoutez une photo de votre offre' : 'Image de référence (facultatif)' }}</small></span>
    </div>
    <h3 data-pv="title" data-empty="{{ $isOfferP ? 'Titre de votre offre' : 'Titre de votre besoin' }}">{{ $pp->title ?: ($isOfferP ? 'Titre de votre offre' : 'Titre de votre besoin') }}</h3>
    <p class="op-preview__cats">
        <span class="op-pill" data-pv="category">{{ $pp->category?->name ?: 'Catégorie' }}</span>
        @proicon('chevron-right', 'oa-ico')
        <span class="op-pill" data-pv="subcategory">{{ $pp->subcategory?->name ?: 'Sous-catégorie' }}</span>
    </p>
    <p class="op-preview__place">@proicon('map-pin', 'oa-ico op-orange')<span data-pv="place">{{ collect([$isOfferP ? null : $pp->location_detail, $pp->city, $pp->country])->filter()->implode(', ') ?: 'Localisation' }}</span></p>
    <div class="op-preview__facts {{ $isOfferP ? '' : 'op-preview__facts--3' }}">
        <div>@proicon('boxes', 'oa-ico op-orange')<span><small>{{ $isOfferP ? 'Quantité disponible' : 'Quantité recherchée' }}</small><b data-pv="qty">{{ $pp->quantity_to_define ? 'À définir' : (trim(($fmt($pp->quantity) ?? '—') . ' ' . $unitPlural)) }}</b></span></div>
        @if($isOfferP)
            <div>@proicon('tag', 'oa-ico op-orange')<span><small>Prix indicatif</small><b data-pv="price">{{ $pp->price_on_request ? 'Sur demande' : ($pp->price !== null ? $fmt($pp->price) . ' ' . $pp->currency . ($unitShort ? ' / ' . $unitShort : '') : 'Non communiqué') }}</b></span></div>
        @else
            <div>@proicon('clock', 'oa-ico op-orange')<span><small>{{ !empty($live) ? 'Délai souhaité' : 'Délai demandé' }}</small><b data-pv="deadline">{{ $deadlineLabel }}</b></span></div>
            <div>@proicon('tag', 'oa-ico op-orange')<span><small>Budget</small><b data-pv="budget">{{ $pp->budget_mode === 'range' && $pp->budget_max ? $fmt($pp->budget_min) . ' – ' . $fmt($pp->budget_max) . ' XOF' : (!empty($live) ? 'Recevoir des propositions' : 'Non communiqué') }}</b></span></div>
        @endif
    </div>
</section>
