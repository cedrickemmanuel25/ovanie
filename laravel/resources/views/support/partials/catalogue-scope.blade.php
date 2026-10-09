{{-- Catalogue produits dans le Support Center : écrans produits de l'administration affichés dans le layout Support,
     avec leurs styles de page limités à la zone de contenu (.admin-scope). --}}
@section('content_class', 'admin-scope')
@push('styles')
    <link rel="stylesheet" href="{{ asset('support/css/catalogue-scope.css') }}?v={{ @filemtime(public_path('support/css/catalogue-scope.css')) ?: '1' }}">
@endpush
