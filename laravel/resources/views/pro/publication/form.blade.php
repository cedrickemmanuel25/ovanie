@extends('pro.auth.access')
@section('title', $kind === 'offer' ? 'Publiez votre offre' : 'Décrivez votre besoin')
@include('pro.publication.partials.setup')
@section('container_class', 'op-wide')
@section('content')
@php
    $p = $publication;
    $isOffer = $kind === 'offer';
    $word = $isOffer ? 'offre' : 'besoin';
    $v = fn (string $field, $default = null) => old($field, $p->{$field} ?? $default);
    $num = fn ($value) => $value === null || $value === '' ? '' : rtrim(rtrim((string) $value, '0'), '.');
    $units = $options['units'];
    $countryFlags = ['Côte d’Ivoire' => 'ci', 'Sénégal' => 'sn', 'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Ghana' => 'gh', 'Guinée' => 'gn', 'Togo' => 'tg', 'Bénin' => 'bj', 'Niger' => 'ne', 'Cameroun' => 'cm', 'Nigeria' => 'ng', 'Maroc' => 'ma', 'France' => 'fr', 'Chine' => 'cn', 'Turquie' => 'tr', 'Émirats arabes unis' => 'ae'];
    $flagBase = asset('ovanie-pro/flags');
    $country = $v('country', 'Côte d’Ivoire');
    $photos = $p->exists ? $p->files->where('type', 'photo') : collect();
    $docs = $p->exists ? $p->files->where('type', 'document') : collect();
    $action = $p->exists ? route('pro.publish.update', ['type' => $type, 'publication' => $p]) : route('pro.publish.store', ['type' => $type]);
    $validity = old('validity_days', $p->validity_days ?: ($p->exists && $p->expires_on ? 'custom' : '30'));
    $expires = old('expires_on', $p->expires_on?->toDateString() ?? now()->addDays(30)->toDateString());
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="op-layout op-layout--form" novalidate
      data-op-form data-kind="{{ $kind }}" data-subcategories='@json($subcategoryMap)' data-units='@json($units)'>
    @csrf
    <div class="op-main op-main--form">
        @include('pro.publication.partials.topline', ['step' => 2, 'kind' => $kind])
        <h1 class="op-title">{{ $isOffer ? 'Publiez votre offre' : 'Décrivez votre besoin' }}</h1>
        <p class="op-lead op-lead--sm">{{ $isOffer
            ? 'Présentez clairement ce que vous proposez afin que les professionnels intéressés puissent vous répondre.'
            : 'Donnez les informations essentielles pour permettre aux professionnels de vous proposer une solution adaptée.' }}</p>

        @include('pro.auth.access.alerts')

        {{-- 1. Type --}}
        <section class="op-card op-card--tight" id="section-type">
            <h2 class="op-section op-section--sm"><span>1</span>{{ $isOffer ? 'Que souhaitez-vous proposer ?' : 'Quel type de besoin avez-vous ?' }}</h2>
            <div class="op-tiles">
                @foreach($natures as $key => [$label, $icon])
                    <label class="op-tile">
                        <input type="radio" name="nature" value="{{ $key }}" data-label="{{ $label }}" @checked($v('nature', 'product') === $key)>
                        <span class="op-tile__check">@proicon('check', 'oa-ico')</span>
                        @proicon($icon, 'oa-ico op-tile__icon')
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- 2. Informations principales --}}
        <section class="op-card op-card--tight" id="section-main">
            <h2 class="op-section op-section--sm"><span>2</span>Informations principales</h2>
            <div class="op-grid op-grid--main">
                <div class="op-field @error('title') is-invalid @enderror">
                    <label for="title">{{ $isOffer ? 'Titre de l’offre' : 'Titre du besoin' }} <em>*</em></label>
                    <input class="op-input" id="title" name="title" value="{{ $v('title') }}" maxlength="160" data-preview="title"
                           placeholder="{{ $isOffer ? 'Ex. Lot de 500 sacs de ciment disponibles à Abidjan' : 'Ex. Recherche 1 000 blocs béton pour chantier à Cocody' }}">
                    @error('title')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('category_id') is-invalid @enderror">
                    <label for="category_id">Catégorie <em>*</em></label>
                    <div class="op-icon-input op-select">@proicon('file', 'oa-ico op-orange')
                        <select id="category_id" name="category_id" data-op-category>
                            <option value="">Choisir…</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) $v('category_id') === (string) $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    @error('category_id')<p class="op-error">{{ $message }}</p>@enderror
                </div>
                <div class="op-field @error('subcategory_id') is-invalid @enderror">
                    <label for="subcategory_id">Sous-catégorie <em>*</em></label>
                    <div class="op-select">
                        <select id="subcategory_id" name="subcategory_id" data-op-subcategory data-selected="{{ $v('subcategory_id') }}">
                            <option value="">Choisir…</option>
                        </select>
                    </div>
                    @error('subcategory_id')<p class="op-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="op-field @error('description') is-invalid @enderror">
                <label for="description">Description détaillée <em>*</em></label>
                <textarea class="op-input op-textarea" id="description" name="description" maxlength="1000" rows="2" data-op-count="desc-count"
                          placeholder="{{ $isOffer ? 'Décrivez le produit, son état, son conditionnement et les modalités de livraison.' : 'Décrivez ce que vous recherchez, la qualité attendue et les conditions de livraison.' }}">{{ $v('description') }}</textarea>
                <div class="op-under"><span>@proicon('info', 'oa-ico op-blue') Plus votre description est précise, plus vous recevez des {{ $isOffer ? 'réponses' : 'propositions' }} pertinentes.</span><span><b id="desc-count">0</b> / 1 000</span></div>
                @error('description')<p class="op-error">{{ $message }}</p>@enderror
            </div>
        </section>

        @if($isOffer)
            {{-- 3. Quantité & conditions --}}
            <section class="op-card op-card--tight" id="section-quantity">
                <h2 class="op-section op-section--sm"><span>3</span>Quantité &amp; conditions</h2>
                <div class="op-grid op-grid--qty">
                    <div class="op-field @error('quantity') is-invalid @enderror">
                        <label for="quantity">Quantité disponible <em>*</em></label>
                        <input class="op-input" id="quantity" name="quantity" inputmode="decimal" value="{{ $num($v('quantity')) }}" data-preview="quantity" placeholder="Ex. 500">
                    </div>
                    <div class="op-field @error('unit') is-invalid @enderror">
                        <label for="unit">Unité <em>*</em></label>
                        <div class="op-select"><select id="unit" name="unit" data-preview="unit">@foreach($units as $k => $l)<option value="{{ $k }}" @selected($v('unit', 'sac_50') === $k)>{{ $l }}</option>@endforeach</select></div>
                    </div>
                    <div class="op-field @error('price') is-invalid @enderror">
                        <label for="price">Prix indicatif <small>(facultatif)</small></label>
                        <input class="op-input" id="price" name="price" inputmode="decimal" value="{{ $num($v('price')) }}" data-preview="price" placeholder="Ex. 5 500">
                    </div>
                    <div class="op-field">
                        <label for="currency">Devise</label>
                        <div class="op-select"><select id="currency" name="currency" data-preview="currency">@foreach($options['currencies'] as $k => $l)<option value="{{ $k }}" @selected($v('currency', 'XOF') === $k)>{{ $l }}</option>@endforeach</select></div>
                    </div>
                    <div class="op-field">
                        <label>Prix négociable ?</label>
                        <div class="op-radios">
                            <label class="op-radio"><input type="radio" name="price_negotiable" value="1" @checked((string) (int) $v('price_negotiable', true) === '1')><span>Oui</span></label>
                            <label class="op-radio"><input type="radio" name="price_negotiable" value="0" @checked((string) (int) $v('price_negotiable', true) === '0')><span>Non</span></label>
                        </div>
                    </div>
                    <div class="op-field op-field--end">
                        <label class="op-check op-check--inline"><input type="checkbox" name="price_on_request" value="1" data-preview="on_request" @checked($v('price_on_request'))><span>Prix sur demande</span></label>
                    </div>
                </div>
                <div class="op-grid op-grid--qty2">
                    <div class="op-field">
                        <label for="min_order_quantity">Commande minimale <small>(facultatif)</small></label>
                        <input class="op-input" id="min_order_quantity" name="min_order_quantity" inputmode="decimal" value="{{ $num($v('min_order_quantity')) }}" placeholder="Ex. 50">
                    </div>
                    <div class="op-field">
                        <label for="min_order_unit">Unité</label>
                        <div class="op-select"><select id="min_order_unit" name="min_order_unit">@foreach($units as $k => $l)<option value="{{ $k }}" @selected($v('min_order_unit', 'sac_50') === $k)>{{ $l }}</option>@endforeach</select></div>
                    </div>
                    <div class="op-field @error('availability') is-invalid @enderror">
                        <label>Disponibilité <em>*</em></label>
                        <div class="op-radios">
                            @foreach($options['availability'] as $k => $l)
                                <label class="op-radio"><input type="radio" name="availability" value="{{ $k }}" @checked($v('availability', 'immediate') === $k)><span>{{ $l }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>
                @foreach(['quantity', 'unit', 'price', 'availability'] as $f)@error($f)<p class="op-error">{{ $message }}</p>@enderror @endforeach
            </section>

            <div class="op-row-2">
                {{-- 4. Localisation --}}
                <section class="op-card op-card--tight op-span-2" id="section-location">
                    <h2 class="op-section op-section--sm"><span>4</span>Où cette offre est-elle disponible ?</h2>
                    @include('pro.publication.partials.location', ['detailLabel' => 'Lieu d’origine ou d’expédition', 'detailPlaceholder' => 'Ex. Zone industrielle de Yopougon'])
                </section>
            </div>

            <div class="op-row-2">
                {{-- 5. Validité --}}
                <section class="op-card op-card--tight" id="section-validity">
                    <h2 class="op-section op-section--sm"><span>5</span>Jusqu’à quand cette offre est-elle valable ?</h2>
                    <div class="op-chips" data-op-validity>
                        @foreach($options['validity'] as $days => $label)
                            <label class="op-chip"><input type="radio" name="validity_days" value="{{ $days }}" data-days="{{ $days }}" @checked((string) $validity === (string) $days)><span>{{ $label }}</span></label>
                        @endforeach
                        <label class="op-chip"><input type="radio" name="validity_days" value="custom" @checked($validity === 'custom')><span>Choisir date</span></label>
                    </div>
                    <div class="op-field @error('expires_on') is-invalid @enderror">
                        <label for="expires_on">Date de fin de validité <em>*</em></label>
                        <div class="op-icon-input">@proicon('clock', 'oa-ico')<input class="op-input" type="date" id="expires_on" name="expires_on" value="{{ $expires }}" min="{{ now()->addDay()->toDateString() }}" data-preview="expires"></div>
                        @error('expires_on')<p class="op-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                {{-- 6. Photos & documents --}}
                <section class="op-card op-card--tight" id="section-media">
                    <h2 class="op-section op-section--sm"><span>6</span>Photos &amp; documents</h2>
                    @include('pro.publication.partials.media', ['photosLabel' => 'Ajoutez des photos de votre offre', 'photosRequired' => true])
                </section>
            </div>
        @else
            {{-- 3. Quantité recherchée --}}
            <section class="op-card op-card--tight" id="section-quantity">
                <h2 class="op-section op-section--sm"><span>3</span>Quantité recherchée</h2>
                <div class="op-grid op-grid--need-qty">
                    <div class="op-field @error('quantity') is-invalid @enderror">
                        <label for="quantity">Quantité <em>*</em></label>
                        <input class="op-input" id="quantity" name="quantity" inputmode="decimal" value="{{ $num($v('quantity')) }}" data-preview="quantity" placeholder="Ex. 1 000">
                    </div>
                    <div class="op-field @error('unit') is-invalid @enderror">
                        <label for="unit">Unité <em>*</em></label>
                        <div class="op-select"><select id="unit" name="unit" data-preview="unit">@foreach($units as $k => $l)<option value="{{ $k }}" @selected($v('unit', 'bloc') === $k)>{{ $l }}</option>@endforeach</select></div>
                    </div>
                    <div class="op-field op-field--end">
                        <label class="op-check op-check--inline"><input type="checkbox" name="quantity_to_define" value="1" data-op-qty-define @checked($v('quantity_to_define'))><span>Quantité à définir avec le professionnel</span></label>
                    </div>
                </div>
                @foreach(['quantity', 'unit'] as $f)@error($f)<p class="op-error">{{ $message }}</p>@enderror @endforeach
            </section>

            {{-- 4. Destination --}}
            <section class="op-card op-card--tight" id="section-location">
                <h2 class="op-section op-section--sm"><span>4</span>Où avez-vous besoin de cette solution ?</h2>
                @include('pro.publication.partials.location', ['detailLabel' => 'Commune / quartier', 'detailPlaceholder' => 'Ex. Cocody'])
            </section>

            {{-- 5. Délai --}}
            <section class="op-card op-card--tight" id="section-deadline">
                <h2 class="op-section op-section--sm"><span>5</span>Pour quand en avez-vous besoin ?</h2>
                <div class="op-chips op-chips--wide" data-op-deadline>
                    @foreach($options['deadlines'] as $k => $l)
                        <label class="op-chip"><input type="radio" name="deadline" value="{{ $k }}" data-preview="deadline" data-label="{{ $l }}" @checked((string) $v('deadline', '15') === (string) $k)><span>{{ $l }}</span></label>
                    @endforeach
                    <input class="op-input op-input--date" type="date" name="needed_by" value="{{ old('needed_by', $p->needed_by?->toDateString()) }}" min="{{ now()->addDay()->toDateString() }}" data-op-needed-by aria-label="Date souhaitée">
                </div>
                @foreach(['deadline', 'needed_by'] as $f)@error($f)<p class="op-error">{{ $message }}</p>@enderror @endforeach
            </section>

            {{-- 6. Caractéristiques --}}
            <section class="op-card op-card--tight" id="section-specs">
                <h2 class="op-section op-section--sm"><span>6</span>Caractéristiques recherchées</h2>
                <div class="op-grid op-grid--specs">
                    <div class="op-field">
                        <label for="specifications">Caractéristiques <small>(facultatif)</small></label>
                        <textarea class="op-input op-textarea op-textarea--sm" id="specifications" name="specifications" maxlength="1000" rows="2" placeholder="Ex. Blocs béton creux standard 15x20x40 cm ou équivalent.">{{ $v('specifications') }}</textarea>
                    </div>
                    <div class="op-field">
                        <label for="brand">Marque souhaitée <small>(facultatif)</small></label>
                        <input class="op-input" id="brand" name="brand" value="{{ $v('brand') }}" maxlength="120" placeholder="Ex. CIMAF, SOCIM, etc.">
                    </div>
                    <div class="op-field">
                        <label for="reference">Référence / modèle <small>(facultatif)</small></label>
                        <input class="op-input" id="reference" name="reference" value="{{ $v('reference') }}" maxlength="120" placeholder="Ex. Bloc creux 15x20x40">
                    </div>
                    <div class="op-field">
                        <label>Alternatives acceptées ?</label>
                        <div class="op-radios">
                            <label class="op-radio"><input type="radio" name="alternatives_accepted" value="1" @checked((string) (int) $v('alternatives_accepted', true) === '1')><span>Oui</span></label>
                            <label class="op-radio"><input type="radio" name="alternatives_accepted" value="0" @checked((string) (int) $v('alternatives_accepted', true) === '0')><span>Non</span></label>
                        </div>
                    </div>
                </div>
            </section>

            <div class="op-row-2 op-row-2--need">
                {{-- 7. Budget --}}
                <section class="op-card op-card--tight" id="section-budget">
                    <h2 class="op-section op-section--sm"><span>7</span>Budget indicatif <small>(facultatif)</small></h2>
                    <div class="op-budget" data-op-budget>
                        <label class="op-radio op-radio--block"><input type="radio" name="budget_mode" value="proposals" data-preview="budget" @checked($v('budget_mode', 'proposals') === 'proposals')><span><b>Recevoir des propositions</b><small>Je préfère recevoir les propositions des professionnels.</small></span></label>
                        <label class="op-radio op-radio--block"><input type="radio" name="budget_mode" value="range" data-preview="budget" @checked($v('budget_mode') === 'range')><span><b>Indiquer mon budget</b><small>Je précise une fourchette de budget.</small></span></label>
                    </div>
                    <div class="op-grid op-grid--2 op-budget-range" data-op-budget-range>
                        <input class="op-input" name="budget_min" inputmode="decimal" value="{{ $num($v('budget_min')) }}" placeholder="Minimum (XOF)" aria-label="Budget minimum">
                        <input class="op-input" name="budget_max" inputmode="decimal" value="{{ $num($v('budget_max')) }}" placeholder="Maximum (XOF)" aria-label="Budget maximum">
                    </div>
                    @foreach(['budget_mode', 'budget_min', 'budget_max'] as $f)@error($f)<p class="op-error">{{ $message }}</p>@enderror @endforeach
                </section>

                {{-- 8. Images et documents --}}
                <section class="op-card op-card--tight" id="section-media">
                    <h2 class="op-section op-section--sm"><span>8</span>Images et documents <small>(facultatif)</small></h2>
                    @include('pro.publication.partials.media', ['photosLabel' => 'Ajoutez des images ou documents utiles', 'photosRequired' => false])
                </section>
            </div>
        @endif
    </div>

    {{-- Colonne de droite : aperçu en direct --}}
    <aside class="op-side op-side--form">
        @include('pro.publication.partials.preview', ['publication' => $p, 'live' => true])

        <div class="oa-note oa-note--tip op-advice">
            @proicon('lightbulb', 'oa-ico')
            <div>
                <strong>{{ $isOffer ? 'Conseils pour une offre efficace' : 'Pour recevoir de meilleures propositions' }}</strong>
                <ul>
                    @foreach($isOffer
                        ? [['Utilisez un titre précis', 'Indiquez le produit, la quantité et la zone.'], ['Ajoutez des photos claires', 'Des photos de qualité renforcent la confiance.'], ['Indiquez votre zone de disponibilité', 'Précisez la ville ou la région.'], ['Précisez vos conditions importantes', 'Mentionnez la disponibilité, le prix et les modalités si nécessaire.']]
                        : [['Soyez précis sur votre quantité', 'Indiquez la quantité et l’unité souhaitées.'], ['Ajoutez les caractéristiques importantes', 'Taille, qualité, marque, etc.'], ['Indiquez clairement la destination', 'Ville, zone ou chantier concerné.'], ['Précisez votre délai réel', 'Cela permet aux professionnels de mieux répondre.']] as [$t, $d])
                        <li>@proicon('check', 'oa-ico')<span><b>{{ $t }}</b><small>{{ $d }}</small></span></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="op-private">
            <span class="op-private__icon">@proicon('shield', 'oa-ico')</span>
            <span><b>Vos coordonnées personnelles restent confidentielles.</b><small>Votre nom, votre e-mail, votre téléphone{{ $isOffer ? '' : ' et votre adresse exacte' }} ne seront pas affichés publiquement dans votre {{ $isOffer ? 'offre' : 'demande' }}.</small></span>
        </div>
    </aside>

    <div class="op-bottom">
        <a class="oa-btn oa-btn--outline" href="{{ route('pro.publish.start', ['type' => $type]) }}">@proicon('chevron-left', 'oa-ico') Retour</a>
        <div class="op-actions__end">
            <button class="oa-btn oa-btn--outline" type="submit" name="action" value="draft">@proicon('file', 'oa-ico') Enregistrer comme brouillon</button>
            <button class="oa-btn oa-btn--outline" type="button" data-op-preview-focus>@proicon('eye', 'oa-ico') Aperçu</button>
            <button class="oa-btn oa-btn--primary" type="submit" name="action" value="continue">Continuer @proicon('arrow-right', 'oa-ico')</button>
        </div>
    </div>
</form>
@endsection
