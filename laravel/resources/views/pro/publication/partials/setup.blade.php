{{-- Réglages communs du parcours « Compte simple » (en-tête, feuille de style). --}}
@section('body_class', 'oa-body--publish')
@section('header_action')
    @guest('pro')
        <span class="op-header-login">Vous avez déjà un compte ? <a href="{{ route('pro.login') }}">Se connecter</a></span>
    @endguest
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pro-publication.css') }}?v={{ @filemtime(public_path('css/pro-publication.css')) ?: '1' }}">
    <script src="{{ asset('js/pro-publication.js') }}?v={{ @filemtime(public_path('js/pro-publication.js')) ?: '1' }}" defer></script>
@endpush
