<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="light">
    <title>@yield('title') · OVANIE PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pro-auth-professional.css') }}?v={{ @filemtime(public_path('css/pro-auth-professional.css')) ?: '1' }}">
    @stack('head')
</head>
<body class="opx-body @yield('body_class')">
    @include('pro.auth.partials.professional-header')
    <main class="opx-page">
        @yield('content')
    </main>
    <script src="{{ asset('js/pro-auth-professional.js') }}?v={{ @filemtime(public_path('js/pro-auth-professional.js')) ?: '1' }}" defer></script>
    @stack('scripts')
</body>
</html>
