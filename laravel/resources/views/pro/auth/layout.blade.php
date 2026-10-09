<!DOCTYPE html>
<html lang="{{ auth('pro')->user()?->preferred_language ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') · OVANIE Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/pro-access.css') }}?v={{ @filemtime(public_path('css/pro-access.css')) ?: '1' }}">
    @stack('styles')
</head>
<body class="pa-body @yield('body_class')">
<div class="pa-page">
    @if (!View::hasSection('hide_topbar'))
        @auth('pro')
            @include('pro.auth.partials.topbar')
        @endauth
    @endif
    <main class="pa-main">
        <div class="pa-shell @yield('shell')">
            @yield('content')
        </div>
    </main>
    @if (!View::hasSection('hide_footer'))
        <footer class="pa-foot">
            &copy; {{ date('Y') }} OVANIE Pro
            <a href="{{ route('terms') }}">Conditions</a>
            <a href="{{ route('privacy-policy') }}">Confidentialité</a>
        </footer>
    @endif
</div>
<script src="{{ asset('js/pro-access.js') }}?v={{ @filemtime(public_path('js/pro-access.js')) ?: '1' }}" defer></script>
@stack('scripts')
</body>
</html>
