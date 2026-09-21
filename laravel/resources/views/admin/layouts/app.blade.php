<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', setting('siteName'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('admin/css/admin_base.css') }}">
    @stack('styles')
</head>

@php
    $adminUser = auth('admin')->user();
    $adminName = $adminUser->name ?? 'Administrateur';
    $adminInitial = strtoupper(substr($adminName, 0, 1));
@endphp

<body>
    <div class="admin-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <x-admin-sidebar />

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>

                    <div>
                        <span class="topbar-kicker">Administration OVANIE</span>
                        <h1>@yield('page-title', 'Tableau de bord')</h1>
                    </div>
                </div>

                <div class="admin-info">
                    <div class="admin-info-copy">
                        <strong>{{ $adminName }}</strong>
                        <span>Administrateur</span>
                    </div>
                    <div class="admin-avatar" aria-label="Avatar de {{ $adminName }}">
                        {{ $adminInitial }}
                    </div>
                </div>
            </header>

            <section class="page-content">
                @if(session('success'))
                    <div class="admin-alert admin-alert-success">
                        <span>✓</span>
                        <p>{{ session('success') }}</p>
                    </div>
                @endif

                @if(session('error'))
                    <div class="admin-alert admin-alert-error">
                        <span>!</span>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="admin-alert admin-alert-error">
                        <span>!</span>
                        <div>
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @yield('content')
            </section>
        </main>
    </div>

    <script>
        const adminLoginUrl = @json(route('admin.adminlogin'));
        const apiAuthMeUrl = @json(url('/api/auth/me'));
        const apiProductsUrl = @json(url('/api/products'));
        const apiOrdersUrl = @json(url('/api/orders'));
        const apiUsersUrl = @json(url('/api/users'));

        (() => {
            const body = document.body;
            const toggle = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');

            const closeSidebar = () => body.classList.remove('sidebar-open');

            toggle?.addEventListener('click', () => {
                body.classList.toggle('sidebar-open');
            });

            overlay?.addEventListener('click', closeSidebar);

            window.addEventListener('resize', () => {
                if (window.innerWidth > 1100) {
                    closeSidebar();
                }
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
