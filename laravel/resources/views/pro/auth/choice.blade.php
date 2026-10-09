@extends('pro.auth.register-layout')

@section('title', 'Choisir son parcours')
@section('page_class', 'opr-page-choice')

@section('content')
<main class="opr-choice-main">
    <section class="opr-choice-shell" aria-labelledby="choice-title">
        <header class="opr-choice-head">
            <span class="opr-choice-accent" aria-hidden="true"></span>
            <h1 id="choice-title">Comment souhaitez-vous utiliser<br>OVANIE PRO&nbsp;?</h1>
            <p>Choisissez votre parcours. Vous pourrez faire évoluer votre compte plus tard.</p>
        </header>

        @if($errors->has('token'))
            <div class="opr-alert opr-alert--error" role="alert">{{ $errors->first('token') }}</div>
        @endif

        <div class="opr-choice-cards">
            <article class="opr-choice-card opr-choice-card--recommended">
                <div class="opr-recommended"><span aria-hidden="true">★</span> Recommandé pour les professionnels</div>

                <div class="opr-choice-icon" aria-hidden="true">
                    <img src="{{ asset('ovanie pro/choice-professional.svg') }}" alt="">
                </div>

                <h2>Créer mon profil<br>professionnel</h2>
                <p>Acheter, vendre, trouver des fournisseurs, répondre à des RFQ, proposer des services ou développer votre activité B2B.</p>

                <a class="opr-choice-btn opr-choice-btn--primary" href="{{ route('pro.register.form', ['accountType' => 'pro']) }}">
                    <span>Créer mon profil PRO</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                </a>
            </article>

            <article class="opr-choice-card">
                <div class="opr-choice-icon" aria-hidden="true">
                    <img src="{{ asset('ovanie pro/choice-publish.svg') }}" alt="">
                </div>

                <h2>Publier une offre<br>ou un besoin</h2>
                <p>Publiez rapidement une offre ou exprimez un besoin sans configurer immédiatement un profil professionnel complet.</p>

                <a class="opr-choice-btn" href="{{ route('pro.register.form', ['accountType' => 'simple']) }}">
                    <span>Continuer avec un compte simple</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                </a>
            </article>

            <article class="opr-choice-card opr-choice-card--invite">
                <div class="opr-choice-icon" aria-hidden="true">
                    <img src="{{ asset('ovanie pro/choice-team.svg') }}" alt="">
                </div>

                <h2>Rejoindre une entreprise</h2>
                <p>Vous avez reçu une invitation d’une entreprise déjà présente sur OVANIE PRO.</p>

                <form method="POST" action="{{ route('pro.invitation.lookup') }}" class="opr-invite-form">
                    @csrf
                    <label class="opr-invite-input" for="token">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v11H3z"></path><path d="m4 7.5 8 6 8-6"></path></svg>
                        <input
                            id="token"
                            name="token"
                            value="{{ old('token') }}"
                            maxlength="500"
                            autocomplete="off"
                            placeholder="Code ou lien d’invitation"
                            aria-label="Code ou lien d’invitation">
                    </label>

                    <button class="opr-choice-btn" type="submit">
                        <span>Utiliser mon invitation</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
                    </button>
                </form>
            </article>
        </div>

        <p class="opr-existing">Vous avez déjà un compte&nbsp;? <a href="{{ route('pro.login') }}">Se connecter</a></p>

        <div class="opr-single-account">
            <div class="opr-single-account__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 5.5 5.7v5.8c0 4.2 2.6 7.5 6.5 9.5 3.9-2 6.5-5.3 6.5-9.5V5.7z"></path><path d="m9.2 12 1.8 1.8 3.9-4"></path></svg>
            </div>
            <div class="opr-single-account__copy">
                <strong>Un seul compte pour toutes vos activités OVANIE PRO.</strong>
                <span>Accédez à l’ensemble des services&nbsp;: achats, ventes, appels d’offres, mise en relation et plus encore, avec un compte unique et sécurisé.</span>
            </div>
        </div>
    </section>
</main>
@endsection
