<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OVANIE PRO') · OVANIE PRO</title>
    <link rel="stylesheet" href="{{ asset('css/pro-auth-redesign.css') }}">
    @stack('styles')
</head>
<body class="opx-body @yield('body_class')">
    @include('pro.auth.partials.redesign-header')
    <main class="opx-main">
        @yield('content')
    </main>
    <script src="{{ asset('js/pro-auth-redesign.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
