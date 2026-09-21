<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - OVANIE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    @yield('styles')
</head>
<body class="bg-[#EFF6FF] h-screen w-screen flex items-center justify-center font-sans text-gray-800 overflow-hidden relative">

    <!-- Contenu principal qui sera redimensionné automatiquement -->
    <div class="w-full max-w-[480px] px-4 auth-card-wrapper">
        @yield('content')
    </div>

    <!-- Footer copyright fixe en bas -->
    <div class="absolute bottom-3 left-0 w-full text-center text-gray-400 text-xs">
        &copy; 2024 OVANIE. Tous droits réservés.
    </div>
    
    <style>
        /* Réduction proportionnelle (Zoom arrière) automatique sur les écrans moins hauts 
           pour garantir que RIEN ne soit coupé et qu'il n'y ait AUCUN scroll */
        @media (max-height: 900px) {
            .auth-card-wrapper { transform: scale(0.9); transform-origin: center; }
        }
        @media (max-height: 800px) {
            .auth-card-wrapper { transform: scale(0.85); transform-origin: center; }
        }
        @media (max-height: 700px) {
            .auth-card-wrapper { transform: scale(0.75); transform-origin: center; }
        }
        @media (max-height: 600px) {
            .auth-card-wrapper { transform: scale(0.65); transform-origin: center; }
        }
    </style>

    @yield('scripts')
</body>
</html>
