<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accès interne OVANIE')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin/css/internal_auth.css') }}">
    @stack('styles')
</head>
<body>
<div class="internal-auth-shell">
    <div class="internal-auth-brand">
        <a href="{{ route('home') }}" class="internal-auth-logo" aria-label="Retour à OVANIE">
            <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
        </a>
        <div class="internal-auth-brand-copy">
            <span>Portail sécurisé</span>
            <strong>Personnel interne OVANIE</strong>
        </div>
    </div>

    <main class="auth-container">
        <div class="internal-auth-heading">
            <span class="internal-auth-kicker">Administration</span>
            <h1>@yield('page-title')</h1>
            @hasSection('page-description')
                <p>@yield('page-description')</p>
            @endif
        </div>

        @if(session('success'))
            <div class="message message-success">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="message message-warning">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="message message-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <p class="internal-auth-footer">Accès réservé à l’Administration, la Logistique, au Support et au Commercial.</p>
</div>

@yield('scripts')
@stack('scripts')
</body>
</html>
