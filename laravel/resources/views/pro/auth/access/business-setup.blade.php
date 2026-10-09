{{-- Réglages communs des écrans « Profil professionnel » et « Informations métier ». --}}
@section('body_class', 'oa-body--pb')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pro-business.css') }}?v={{ @filemtime(public_path('css/pro-business.css')) ?: '1' }}">
    <script src="{{ asset('js/pro-business.js') }}?v={{ @filemtime(public_path('js/pro-business.js')) ?: '1' }}" defer></script>
@endpush
