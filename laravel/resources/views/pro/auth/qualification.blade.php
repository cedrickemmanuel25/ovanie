@extends('pro.auth.access')
@section('title', 'Parlez-nous de votre activité')
@include('pro.auth.access.business-setup')
@section('content')
@php
    $entity = old('entity_type', $profile?->entity_type ?: 'organization');
    $selected = old('professional_profile', $selectedProfile ?: 'fabricant_producteur');
    $chosenFunctions = (array) old('desired_functions', $profile?->desired_functions ?? []);
    $flagCodes = ['Côte d’Ivoire' => 'ci', 'Sénégal' => 'sn', 'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Ghana' => 'gh', 'Guinée' => 'gn', 'Togo' => 'tg', 'Bénin' => 'bj', 'Niger' => 'ne', 'Cameroun' => 'cm', 'Nigeria' => 'ng', 'Maroc' => 'ma', 'France' => 'fr', 'Chine' => 'cn', 'Turquie' => 'tr', 'Émirats arabes unis' => 'ae'];
    $flagBase = asset('ovanie-pro/flags');
    $activity = old('activity_country', $profile?->activity_country ?: ($user->residence_country ?: 'Côte d’Ivoire'));
    $registration = old('registration_country', $profile && $profile->registration_country !== $profile->activity_country ? $profile->registration_country : '');
    $functionMeta = [
        'buy' => ['cart', 'Matériaux, équipements, services, etc.'],
        'sell' => ['store', 'Vos produits à des professionnels'],
        'services' => ['wrench', 'Prestations et expertise BTP'],
        'publish' => ['file', 'Appels d’offres, demandes de devis, besoins spécifiques', 'Publier des offres ou besoins'],
    ];
    $cardTexts = [
        'fabricant_producteur' => 'Vous fabriquez ou produisez directement des matériaux, équipements ou produits.',
        'grossiste' => 'Vous distribuez des produits en gros, par lots ou volumes importants.',
        'demi_grossiste' => 'Vous commercialisez en semi-gros avec plusieurs paliers de quantité.',
        'revendeur_professionnel' => 'Vous achetez auprès de fournisseurs pour revendre à vos clients.',
        'entreprise_btp' => 'Vous réalisez des travaux, gérez des chantiers et effectuez des achats professionnels.',
        'entrepreneur_artisan' => 'Vous exercez un métier ou proposez directement des prestations BTP.',
        'promoteur_immobilier' => 'Vous développez, construisez, aménagez ou gérez des programmes immobiliers.',
        'prestataire_btp' => 'Vous fournissez des services spécialisés aux acteurs du bâtiment et des travaux publics.',
    ];
@endphp
<form method="POST" action="{{ route('pro.register.qualification.submit') }}" class="pb-page" novalidate data-pb-qualification data-compatibility='@json($compatibility)'>
    @csrf
    <div class="pb-main">
        @include('pro.auth.access.stepper', ['current' => 2])
        <h1 class="pb-title">Parlez-nous de votre activité</h1>
        <p class="pb-lead">Votre choix détermine les informations et justificatifs professionnels qui vous seront demandés.</p>

        @include('pro.auth.access.alerts', ['except' => ['entity_type', 'professional_profile', 'activity_country', 'registration_country', 'desired_functions']])

        <section class="pb-card pb-q">
            <div class="pb-q__head">
                <span class="pb-round">@proicon('building', 'oa-ico')</span>
                <div><h2>Vous exercez en tant que :</h2><p>Sélectionnez le statut qui correspond à votre situation.</p></div>
            </div>
            <div class="pb-entity">
                @foreach($entityTypes as $key => $type)
                    <label class="pb-radio-card">
                        <input type="radio" name="entity_type" value="{{ $key }}" @checked($entity === $key)>
                        <span class="pb-radio-card__dot"></span>
                        <span><b>{{ $type['label'] }}</b><small>{{ $key === 'organization' ? 'Société, organisation, structure, etc.' : 'Artisan, entrepreneur individuel, freelance, etc.' }}</small></span>
                    </label>
                @endforeach
                <p class="pb-entity__note">@proicon('info', 'oa-ico')<span>La disponibilité du statut indépendant peut varier selon votre pays de résidence.</span></p>
            </div>
            @error('entity_type')<p class="pb-error">{{ $message }}</p>@enderror

            <div class="pb-q__head pb-q__head--spaced">
                <span class="pb-round">@proicon('grid', 'oa-ico')</span>
                <div><h2>Choisissez votre profil professionnel</h2><p>Sélectionnez le profil qui décrit le mieux votre activité principale sur OVANIE PRO.</p></div>
            </div>
            <div class="pb-profiles">
                @foreach($profiles as $key => $meta)
                    <label class="pb-profile" data-profile="{{ $key }}">
                        <input type="radio" name="professional_profile" value="{{ $key }}" @checked($selected === $key)>
                        <span class="pb-profile__check">@proicon('check', 'oa-ico')</span>
                        <img src="{{ asset('ovanie-pro/profiles/' . $key . '-card.jpg') }}" alt="" loading="lazy">
                        <b>{{ $meta['label'] }}</b>
                        <small>{{ $cardTexts[$key] ?? $meta['description'] }}</small>
                        <em class="pb-profile__lock">Réservé aux entreprises</em>
                    </label>
                @endforeach
            </div>
            @error('professional_profile')<p class="pb-error">{{ $message }}</p>@enderror

            <div class="pb-countries">
                <div class="pb-countries__item @error('activity_country') is-invalid @enderror">
                    <span class="pb-round">@proicon('globe', 'oa-ico')</span>
                    <div>
                        <label for="activity_country">Pays principal d’activité <span class="oa-req">*</span></label>
                        <div class="pb-select pb-select--flag" data-pb-flag>
                            <img src="{{ $flagBase . '/' . ($flagCodes[$activity] ?? 'ci') . '.svg' }}" alt="" data-flag-img>
                            <select id="activity_country" name="activity_country" data-flag-base="{{ $flagBase }}">
                                @foreach($countries as $c)<option value="{{ $c }}" data-flag="{{ $flagCodes[$c] ?? '' }}" @selected($activity === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        @error('activity_country')<p class="pb-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <span class="pb-countries__sep" aria-hidden="true"></span>
                <div class="pb-countries__item" data-pb-registration>
                    <span class="pb-round">@proicon('map-pin', 'oa-ico')</span>
                    <div>
                        <label for="registration_country">Pays d’enregistrement (si différent)</label>
                        <div class="pb-select pb-select--flag" data-pb-flag>
                            <img src="{{ $registration && isset($flagCodes[$registration]) ? $flagBase . '/' . $flagCodes[$registration] . '.svg' : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' }}" alt="" data-flag-img @unless($registration && isset($flagCodes[$registration])) hidden @endunless>
                            <select id="registration_country" name="registration_country" data-flag-base="{{ $flagBase }}">
                                <option value="">Sélectionnez un pays</option>
                                @foreach($countries as $c)<option value="{{ $c }}" data-flag="{{ $flagCodes[$c] ?? '' }}" @selected($registration === $c)>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pb-q__head pb-q__head--spaced pb-q__head--sm">
                <span class="pb-round">@proicon('target', 'oa-ico')</span>
                <div><h2>Que souhaitez-vous faire sur OVANIE PRO ? <small>(Plusieurs choix possibles)</small></h2><p>Ces choix nous permettent de personnaliser votre expérience. Ils seront utilisés à l’étape 4.</p></div>
            </div>
            <div class="pb-functions @error('desired_functions') is-invalid @enderror">
                @foreach($functions as $key => $label)
                    <label class="pb-function">
                        <input type="checkbox" name="desired_functions[]" value="{{ $key }}" @checked(in_array($key, $chosenFunctions, true))>
                        <span class="pb-function__box">@proicon('check', 'oa-ico')</span>
                        @proicon($functionMeta[$key][0] ?? 'briefcase', 'oa-ico pb-function__icon')
                        <span><b>{{ $functionMeta[$key][2] ?? $label }}</b><small>{{ $functionMeta[$key][1] ?? '' }}</small></span>
                    </label>
                @endforeach
            </div>
            @error('desired_functions')<p class="pb-error">{{ $message }}</p>@enderror
        </section>
    </div>

    <aside class="pb-side">
        @foreach($profiles as $key => $meta)
            <div class="pb-side__profile" data-presentation="{{ $key }}" @unless($selected === $key) hidden @endunless>
                <div class="pb-hero" style="background-image:url('{{ asset('ovanie-pro/profiles/' . $key . '-hero.jpg') }}')">
                    <span class="pb-hero__icon">@proicon('chart', 'oa-ico')</span>
                    <strong>{{ $meta['label'] }}</strong>
                    <span>{{ $presentation[$key]['pitch'] ?? $meta['description'] }}</span>
                </div>
                <div class="pb-card pb-asks">
                    <h2>Ce que nous vous demanderons</h2>
                    <p>Les informations exactes seront demandées à l’étape suivante et peuvent varier selon votre pays.</p>
                    <ul>
                        @foreach($presentation[$key]['asks'] ?? [] as [$icon, $title, $text])
                            <li><span class="pb-asks__icon">@proicon($icon, 'oa-ico')</span><span><b>{{ $title }}</b><small>{{ $text }}</small></span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
        <div class="oa-note oa-note--tip pb-tip">
            @proicon('lightbulb', 'oa-ico')
            <div><strong>Bon à savoir</strong><span>Vous pourrez ajouter plusieurs types d’activités plus tard dans votre espace OVANIE PRO.</span></div>
        </div>
    </aside>

    <div class="pb-bottom">
        <a class="oa-btn oa-btn--outline" href="{{ route('pro.register.choice') }}">@proicon('chevron-left', 'oa-ico') Retour</a>
        <div class="pb-bottom__end">
            <button class="oa-btn oa-btn--outline" type="submit" name="action" value="draft" data-quit>@proicon('file', 'oa-ico') Enregistrer et quitter</button>
            <button class="oa-btn oa-btn--primary" type="submit" name="action" value="continue">Continuer avec ce profil @proicon('chevron-right', 'oa-ico')</button>
        </div>
    </div>
    <input type="hidden" name="quit" value="0" data-quit-input>
</form>
@endsection
