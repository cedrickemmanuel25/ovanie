@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-directory.css') }}?v={{ filemtime(public_path('css/logistics-directory.css')) }}">
@endpush

@push('scripts')
<script defer src="{{ asset('js/logistics-directory.js') }}?v={{ filemtime(public_path('js/logistics-directory.js')) }}"></script>
@endpush
