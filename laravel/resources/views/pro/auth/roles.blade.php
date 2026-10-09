@extends('pro.auth.access')
@section('title', 'Vos fonctions')
@section('body_class', 'oa-body--roles')
@section('content')
@php
    $selected = (array) old('roles', $activeRoles);
    $backUrl = $profile && $profile->entity_type
        ? route('pro.register.professional.form', ['profile' => $profile->profile_key])
        : route('pro.register.qualification');
    $doneCount = collect($progress)->where('done', true)->count();
@endphp
<div class="oa-roles">
    <section class="oa-roles__main oa-card" aria-labelledby="roles-title">
        <ol class="oa-stepper" aria-label="Progression de l’inscription">
            @php
                $stepperDone = [true, $progress[0]['done'], $progress[2]['done'], false];
            @endphp
            @foreach(['Compte', 'Profil professionnel', 'Informations métier', 'Fonctions'] as $i => $label)
                @php
                    $isCurrent = $i === 3;
                @endphp
                <li class="{{ $isCurrent ? 'is-current' : ($stepperDone[$i] ? 'is-done' : '') }}" @if($isCurrent) aria-current="step" @endif>
                    <span class="oa-stepper__dot">@if(! $isCurrent && $stepperDone[$i])@proicon('check', 'oa-ico')@else{{ $i + 1 }}@endif</span>
                    <span class="oa-stepper__label">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <header class="oa-roles__head">
            <h1 class="oa-title oa-title--lg" id="roles-title">Vos fonctions sur OVANIE PRO</h1>
            <p class="oa-roles__step">Étape 4 sur 4</p>
            <p class="oa-roles__question">Que souhaitez-vous faire avec votre compte professionnel ?</p>
            <p class="oa-lead oa-lead--sm">Vous pouvez activer plusieurs fonctions avec le même compte.</p>
        </header>

        @include('pro.auth.access.alerts')

        @unless($professionalReady)
            <div class="oa-alert oa-alert--warn">@proicon('alert', 'oa-ico')<span>Les fonctions Acheteur PRO et Vendeur / Fournisseur demandent un profil professionnel soumis. <a href="{{ $backUrl }}">Compléter mon profil</a></span></div>
        @endunless

        <form method="POST" action="{{ route('pro.roles.update') }}" data-oa-roles>
            @csrf
            <div class="oa-role-grid">
                @foreach($roles as $key => $role)
                    @php
                        $meta = $presentation[$key] ?? [];
                        $locked = ($role['requires_pro'] ?? false) && ! $professionalReady;
                    @endphp
                    <label class="oa-role {{ $locked ? 'is-locked' : '' }}">
                        <input class="oa-role__input" type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $selected, true)) @disabled($locked)
                               data-label="{{ $role['label'] }}" data-icon="{{ $meta['icon'] ?? 'briefcase' }}" data-space="{{ $meta['space'] ?? $role['description'] }}">
                        <span class="oa-role__box" aria-hidden="true">@proicon('check', 'oa-ico')</span>
                        <span class="oa-role__icon">@proicon($meta['icon'] ?? 'briefcase', 'oa-ico')</span>
                        <span class="oa-role__body">
                            <span class="oa-role__title">{{ $role['label'] }}</span>
                            <span class="oa-role__text">{{ $meta['text'] ?? $role['description'] }}</span>
                            <span class="oa-role__tags">
                                @foreach($meta['tags'] ?? [] as $tag)<span class="oa-tag">{{ $tag }}</span>@endforeach
                                @if($locked)<span class="oa-tag oa-tag--muted">Profil PRO requis</span>@endif
                            </span>
                        </span>
                        @if(!empty($meta['image']))
                            <img class="oa-role__photo" src="{{ asset($meta['image']) }}" alt="" loading="lazy">
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="oa-note oa-note--blue">@proicon('info', 'oa-ico')<span>Vous pourrez activer d’autres fonctions ultérieurement sans recréer votre compte.</span></div>

            <div class="oa-spaces">
                <h2 class="oa-subhead">Espaces qui seront activés sur votre compte</h2>
                <p class="oa-muted">En fonction des fonctions sélectionnées, ces espaces seront immédiatement accessibles après la validation.</p>
                <div class="oa-spaces__list" data-oa-spaces>
                    @foreach($roles as $key => $role)
                        @php
                            $meta = $presentation[$key] ?? [];
                        @endphp
                        <div class="oa-space" data-space-for="{{ $key }}" @unless(in_array($key, $selected, true)) hidden @endunless>
                            <span class="oa-space__icon">@proicon($meta['icon'] ?? 'briefcase', 'oa-ico')</span>
                            <span class="oa-space__body"><strong>{{ $role['label'] }}</strong><small>{{ $meta['space'] ?? $role['description'] }}</small></span>
                            <span class="oa-round-check oa-round-check--sm">@proicon('check', 'oa-ico')</span>
                        </div>
                    @endforeach
                    <p class="oa-spaces__empty" data-oa-spaces-empty @if($selected !== []) hidden @endif>Aucune fonction sélectionnée pour le moment.</p>
                </div>
            </div>

            <div class="oa-actions oa-actions--roles">
                <a class="oa-btn oa-btn--outline" href="{{ $backUrl }}">@proicon('chevron-left', 'oa-ico') Retour</a>
                <div class="oa-actions__end">
                    <button class="oa-btn oa-btn--outline" type="submit" name="action" value="later">Je le ferai plus tard</button>
                    <button class="oa-btn oa-btn--primary" type="submit">Terminer la configuration @proicon('chevron-right', 'oa-ico')</button>
                </div>
            </div>
        </form>
    </section>

    <aside class="oa-roles__side">
        @include('pro.auth.access.hero', [
            'class' => 'oa-hero--roles',
            'title' => 'Un seul compte,<br><em>plusieurs opportunités</em>',
            'text' => 'Activez les fonctions qui correspondent à vos besoins et développez votre activité dans tout le secteur BTP en Afrique de l’Ouest.',
            'benefits' => [
                ['users', 'Accès à un large réseau de professionnels'],
                ['chart', 'Des opportunités qualifiées'],
                ['shield', 'Une plateforme sécurisée et fiable'],
            ],
        ])

        <div class="oa-note oa-note--tip">
            @proicon('lightbulb', 'oa-ico')
            <div><strong>Bon à savoir</strong><span>Vous pouvez activer une ou plusieurs fonctions aujourd’hui et en ajouter d’autres plus tard, depuis votre espace OVANIE PRO.</span></div>
        </div>

        <div class="oa-card oa-progress">
            <div class="oa-progress__head">
                <span class="oa-progress__icon">@proicon('lock', 'oa-ico')</span>
                <strong>Votre progression</strong>
                <small>{{ $doneCount }} section{{ $doneCount > 1 ? 's' : '' }} complétée{{ $doneCount > 1 ? 's' : '' }} sur 4</small>
            </div>
            <ol class="oa-progress__list">
                @foreach($progress as $i => $step)
                    <li class="{{ $step['done'] ? 'is-done' : '' }} {{ $i === 3 ? 'is-current' : '' }}">
                        <span class="oa-progress__num">{{ $i + 1 }}</span>
                        <span class="oa-progress__label">{{ $step['label'] }}</span>
                        <span class="oa-progress__badge">{{ $step['done'] ? 'Complétée' : ($i === 3 ? 'En cours' : 'À compléter') }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="oa-card oa-help-card">
            <span class="oa-help-card__icon">@proicon('help', 'oa-ico')</span>
            <div><strong>Besoin d’aide ?</strong><small>Notre équipe vous accompagne pour finaliser la configuration de votre compte OVANIE PRO.</small></div>
            <a class="oa-btn oa-btn--outline oa-btn--sm" href="{{ route('contact.index') }}">@proicon('headset', 'oa-ico') Nous contacter</a>
        </div>
    </aside>
</div>
@endsection
