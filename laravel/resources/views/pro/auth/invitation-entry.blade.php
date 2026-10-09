@extends('pro.auth.guest')
@section('title', 'Invitation')
@section('top_link')<a class="pa-link" href="{{ route('pro.login') }}">Retour à la connexion</a>@endsection
@section('content')
<div class="pa-head">
    <p class="pa-kicker">Collaborateur</p>
    <h1 class="pa-title">Rejoindre votre entreprise</h1>
    <p class="pa-sub">Ouvrez le lien reçu par e-mail, ou collez-le ci-dessous.</p>
</div>

@include('pro.auth.partials.alerts')

<form method="POST" action="{{ route('pro.invitation.lookup') }}">
    @csrf
    <div class="pa-field">
        <label class="pa-label" for="token">Lien ou code d’invitation</label>
        <div class="pa-control">@proicon('key')<input class="pa-input" id="token" name="token" value="{{ old('token') }}" required maxlength="500" autocomplete="off" placeholder="Collez le lien reçu"></div>
    </div>
    <button class="pa-btn pa-btn--block" type="submit">Voir l’invitation @proicon('arrow-right')</button>
</form>
@endsection
