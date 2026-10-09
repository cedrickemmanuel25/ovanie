@extends('pro.auth.layout')
@section('title', $profileMeta['label'] ?? 'Profil professionnel')
@section('content')
@php
    $stepTitles = collect($blocks)->pluck('title')->push('Justificatifs')->values();
    $submitted = in_array($profile->status, ['pending', 'verified'], true);
    $requiredDocs = collect($documents)->where('required', true)->count();
    $statusTag = $submitted ? ['En attente de vérification', 'pa-tag--amber'] : ['Brouillon', ''];
@endphp

<div class="pa-card">
    @include('pro.auth.partials.steps', ['current' => 3])
    <div class="pa-pro-head">
        <span class="pa-pro-head__icon">@proicon($profileMeta['icon'] ?? 'briefcase')</span>
        <div class="pa-pro-head__body">
            <div class="pa-row" style="margin:0">
                <p class="pa-kicker" style="margin:0">Étape 3 sur 4 · Profil professionnel</p>
                <span class="pa-tag {{ $statusTag[1] }}">{{ $statusTag[0] }}</span>
            </div>
            <h1 class="pa-title" style="margin-top:6px">{{ $profileMeta['label'] ?? '' }}</h1>
            <p class="pa-sub">{{ $profileMeta['form_intro'] ?? '' }}</p>
            <div class="pa-facts">
                <span>@proicon($entityType === 'independent' ? 'user' : 'building'){{ $entityType === 'independent' ? 'Professionnel indépendant' : 'Entreprise / organisation' }}</span>
                <span>@proicon('map-pin'){{ $profile->activity_country }}</span>
                <span>@proicon('file'){{ $requiredDocs }} justificatif(s) obligatoire(s)</span>
                <a class="pa-link" href="{{ route('pro.register.qualification') }}">Modifier le profil ou le statut</a>
            </div>
        </div>
    </div>
</div>

<div style="margin-top:16px">
    @include('pro.auth.partials.alerts')
    @if($submitted)
        <div class="pa-alert pa-alert--info">@proicon('alert')<span>Profil déjà soumis. Vous pouvez compléter ou corriger vos informations : elles seront vérifiées à nouveau.</span></div>
    @endif
</div>

<form method="POST" action="{{ route('pro.register.professional.submit', ['profile' => $profileKey]) }}" enctype="multipart/form-data">
    @csrf
    <div class="pa-wizard">
        <div class="pa-side">
            <ol class="pa-nav" data-step-nav>
                @foreach($stepTitles as $i => $title)
                    <li><button type="button"><i>{{ $i + 1 }}</i>{{ $title }}</button></li>
                @endforeach
            </ol>
            @if(!empty($profileMeta['benefits']))
                <div class="pa-benefits">
                    <strong>Avec le profil {{ $profileMeta['short_label'] ?? $profileMeta['label'] }}</strong>
                    <ul>@foreach($profileMeta['benefits'] as $benefit)<li>@proicon('check'){{ $benefit }}</li>@endforeach</ul>
                </div>
            @endif
        </div>

        <div class="pa-card" data-wizard>
            @foreach($blocks as $blockKey => $block)
                <section class="pa-step">
                    <div class="pa-section__head">
                        <p class="pa-step__count" data-step-count>Étape {{ $loop->iteration }} sur {{ $stepTitles->count() }}</p>
                        <h2 class="pa-h2">{{ $block['title'] }}</h2>
                        @if(!empty($block['intro']))<p class="pa-section__intro">{{ $block['intro'] }}</p>@endif
                    </div>
                    @if($blockKey === 'responsible')
                        <div class="pa-box" style="margin-bottom:20px">
                            <dl class="pa-dl"><dt>Responsable du compte</dt><dd>{{ $user->full_name }}</dd></dl>
                        </div>
                    @endif
                    <div class="pa-grid">
                        @foreach($block['fields'] as $name => $definition)
                            @include('pro.auth.partials.schema-field', ['name' => $name, 'field' => $fields[$name], 'value' => $values[$name] ?? null])
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="pa-step">
                <div class="pa-section__head">
                    <p class="pa-step__count" data-step-count>Étape {{ $stepTitles->count() }} sur {{ $stepTitles->count() }}</p>
                    <h2 class="pa-h2">Justificatifs</h2>
                    <p class="pa-section__intro">PDF, JPG, PNG ou WEBP, 10 Mo maximum (vidéos MP4/MOV jusqu’à 20 Mo). Documents privés, consultés uniquement par l’équipe de vérification.</p>
                </div>
                @foreach($documents as $type => $doc)
                    @php
                        $existing = $providedDocuments->get($type, collect());
                        $mustUpload = $doc['required'] && $existing->isEmpty();
                        $key = 'documents.' . $type;
                    @endphp
                    <div class="pa-doc {{ $existing->isNotEmpty() ? 'is-provided' : '' }} @if($errors->has($key) || $errors->has($key . '.*')) has-error @endif" data-doc>
                        <span class="pa-doc__icon">@proicon($existing->isNotEmpty() ? 'check' : 'file')</span>
                        <div class="pa-doc__body">
                            <span class="pa-doc__title">{{ $doc['label'] }}@if($doc['required'])<span class="pa-req">*</span>@else <span class="pa-opt">(facultatif)</span>@endif</span>
                            <span class="pa-doc__meta {{ $existing->isNotEmpty() ? 'is-ok' : '' }}" data-doc-meta>
                                @if($existing->isNotEmpty())
                                    Déjà fourni : {{ $existing->pluck('original_name')->filter()->join(', ') ?: $existing->count() . ' fichier(s)' }}
                                @else
                                    {{ !empty($doc['multiple']) ? 'Plusieurs fichiers possibles' : 'Aucun fichier sélectionné' }}
                                @endif
                            </span>
                            @error($key)<p class="pa-error">{{ $message }}</p>@enderror
                            @error($key . '.*')<p class="pa-error">{{ $message }}</p>@enderror
                        </div>
                        <input type="file" id="doc_{{ $type }}" name="{{ !empty($doc['multiple']) ? "documents[$type][]" : "documents[$type]" }}"
                               accept="{{ !empty($doc['media']) ? '.pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov' : '.pdf,.jpg,.jpeg,.png,.webp' }}"
                               @if(!empty($doc['multiple'])) multiple @endif @required($mustUpload) data-label="{{ $doc['label'] }}">
                        <label class="pa-btn pa-btn--ghost pa-btn--sm" for="doc_{{ $type }}">@proicon('upload'){{ $existing->isNotEmpty() ? (!empty($doc['multiple']) ? 'Ajouter' : 'Remplacer') : 'Téléverser' }}</label>
                    </div>
                @endforeach

                <div class="pa-alert pa-alert--warn" style="margin-top:20px">
                    @proicon('shield')<span>En soumettant, vous certifiez l’exactitude des informations. Votre profil passe « en attente de vérification » : il n’est jamais validé automatiquement.</span>
                </div>
            </section>

            <div class="pa-actions">
                <div class="pa-inline">
                    <button class="pa-btn pa-btn--ghost" type="button" data-prev hidden>@proicon('arrow-left') Précédent</button>
                    <button class="pa-btn pa-btn--text" type="submit" name="action" value="draft" formnovalidate>Enregistrer le brouillon</button>
                    @if($onboarding)<button class="pa-btn pa-btn--text" type="submit" form="pa-logout" formnovalidate>Quitter</button>@endif
                </div>
                <div class="pa-actions__end">
                    <button class="pa-btn" type="button" data-next hidden>Continuer @proicon('arrow-right')</button>
                    <button class="pa-btn" type="submit" name="action" value="submit" data-submit>Soumettre pour vérification</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
