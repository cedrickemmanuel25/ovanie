@extends('pro.auth.access')
@section('title', 'Que souhaitez-vous publier ?')
@include('pro.publication.partials.setup')
@section('content')
<div class="op-layout">
    <form method="POST" action="{{ route('pro.publish.choose') }}" class="op-main op-start" novalidate>
        @csrf
        <span class="op-badge">@proicon('user', 'oa-ico') Compte simple OVANIE PRO</span>
        <h1 class="op-title op-title--xl">Que souhaitez-vous publier ?</h1>
        <p class="op-lead">Déposez rapidement une offre ou exprimez un besoin. Vous pourrez compléter votre profil professionnel plus tard.</p>

        @include('pro.auth.access.alerts')

        <div class="op-choice-grid">
            <label class="op-choice op-choice--offer">
                <input type="radio" name="type" value="offre" @checked(old('type', $selected) === 'offre')>
                <span class="op-choice__radio" aria-hidden="true"></span>
                <span class="op-choice__head">
                    <span class="op-choice__icon">@proicon('tag', 'oa-ico')</span>
                    <span>
                        <strong>Publier une offre</strong>
                        <small>Proposez un produit, un lot, un équipement ou une opportunité commerciale aux professionnels présents sur OVANIE PRO.</small>
                    </span>
                </span>
                <ul class="op-checks">
                    <li>@proicon('check', 'oa-ico')Présentez clairement ce que vous proposez</li>
                    <li>@proicon('check', 'oa-ico')Définissez votre zone et la durée de publication</li>
                    <li>@proicon('check', 'oa-ico')Recevez des réponses de professionnels intéressés</li>
                </ul>
            </label>

            <label class="op-choice op-choice--need">
                <input type="radio" name="type" value="besoin" @checked(old('type', $selected) === 'besoin')>
                <span class="op-choice__radio" aria-hidden="true"></span>
                <span class="op-choice__head">
                    <span class="op-choice__icon">@proicon('file-search', 'oa-ico')</span>
                    <span>
                        <strong>Publier un besoin</strong>
                        <small>Expliquez ce que vous recherchez afin que des professionnels puissent vous proposer une solution.</small>
                    </span>
                </span>
                <ul class="op-checks">
                    <li>@proicon('check', 'oa-ico')Décrivez précisément votre besoin</li>
                    <li>@proicon('check', 'oa-ico')Indiquez quantité, destination et délai</li>
                    <li>@proicon('check', 'oa-ico')Recevez des propositions adaptées</li>
                </ul>
            </label>
        </div>

        <div class="op-banner">
            <span class="op-banner__icon">@proicon('building', 'oa-ico')</span>
            <div>
                <strong>Vous avez un besoin professionnel plus complexe ?</strong>
                <small>Les entreprises disposant d’un profil OVANIE PRO peuvent accéder aux RFQ, appels d’offres et outils d’achat professionnels avancés.</small>
            </div>
            <a class="oa-btn oa-btn--outline oa-btn--sm" href="{{ route('pro.register.form', ['accountType' => 'pro']) }}">Découvrir le profil professionnel @proicon('arrow-right', 'oa-ico')</a>
        </div>

        <ul class="op-features">
            <li><span class="op-features__icon op-features__icon--amber">@proicon('send', 'oa-ico')</span><span><strong>Publication simple</strong><small>En quelques minutes</small></span></li>
            <li><span class="op-features__icon op-features__icon--orange">@proicon('chart', 'oa-ico')</span><span><strong>Compte évolutif</strong><small>Transformez en profil PRO plus tard</small></span></li>
            <li><span class="op-features__icon op-features__icon--blue">@proicon('shield', 'oa-ico')</span><span><strong>Coordonnées personnelles protégées</strong><small>Vos données restent confidentielles</small></span></li>
        </ul>

        <div class="op-actions op-actions--card">
            <a class="oa-btn oa-btn--outline" href="{{ route('pro.register.choice') }}">@proicon('chevron-left', 'oa-ico') Retour</a>
            <button class="oa-btn oa-btn--primary" type="submit">Continuer @proicon('arrow-right', 'oa-ico')</button>
        </div>
    </form>

    <aside class="op-side">
        @include('pro.auth.access.hero', [
            'class' => 'op-hero',
            'title' => 'Trouvez<br>des opportunités<br>dans le secteur BTP<br>en Afrique de l’Ouest.',
            'text' => null,
            'benefits' => [
                ['users', 'Un large réseau de professionnels qualifiés'],
                ['file', 'Des offres et besoins vérifiés et pertinents'],
                ['chart', 'Des projets dans toute la région'],
            ],
        ])
        @include('pro.publication.partials.tip', ['text' => 'Vous pouvez publier une offre ou un besoin avec un compte simple. Vous compléterez votre profil professionnel plus tard pour accéder à plus de fonctionnalités.'])
    </aside>
</div>
@endsection
