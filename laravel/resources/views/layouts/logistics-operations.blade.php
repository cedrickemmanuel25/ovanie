<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — OVANIE Logistics</title>
    <link rel="stylesheet" href="{{ asset('fonts/operations/fonts.css') }}">
    @vite('resources/js/logistics-icons.js')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('css/logistics-operations.css') }}?v={{ filemtime(public_path('css/logistics-operations.css')) }}">
    <script defer src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script defer src="{{ asset('js/logistics-operations.js') }}?v={{ filemtime(public_path('js/logistics-operations.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/logistics-navigation.css') }}">
<link rel="stylesheet" href="{{ asset('css/logistics-missions.css') }}">
<link rel="stylesheet" href="{{ asset('css/logistics-tracking.css') }}?v={{ filemtime(public_path('css/logistics-tracking.css')) }}">
<script defer src="{{ asset('js/logistics-tracking.js') }}?v={{ filemtime(public_path('js/logistics-tracking.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/logistics-supervision.css') }}?v={{ filemtime(public_path('css/logistics-supervision.css')) }}">
<script defer src="{{ asset('js/logistics-navigation.js') }}"></script>
@stack('styles')
</head>
<body class="ops-shell @yield('page-class')">
@include('logistics.operations.icons')
@include('logistics.operations.navigation')
<main class="ops-main">
    @if(session('error'))<div class="ops-feedback is-red" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ops-feedback is-red" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @yield('content')
</main>
<div class="ops-toast" role="status" aria-live="polite" hidden></div>
@stack('dialogs')
<script type="application/json" id="ops-map-config">{!! json_encode(['token'=>config('geo.mapbox.public_token')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@stack('scripts')
</body></html>
