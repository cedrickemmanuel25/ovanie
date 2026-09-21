<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Logistique') — OVANIE</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.css" rel="stylesheet">
    <link href="{{ asset('css/ovanie-delivery-map.css') }}?v={{ @filemtime(public_path('css/ovanie-delivery-map.css')) ?: '20260718-4' }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js"></script>
    <script src="{{ asset('js/ovanie-delivery-map.js') }}?v={{ @filemtime(public_path('js/ovanie-delivery-map.js')) ?: '20260718-4' }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sidebar: '#0A2218',
                        green: {
                            50:  '#f0fdf4',
                            100: '#dcfce7',
                            400: '#4ade80',
                            500: '#16a34a',
                            600: '#15803d',
                            700: '#166534',
                        },
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F8FAFC; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* Hide scrollbars for sidebar navigation */
        #sidebar::-webkit-scrollbar,
        #sidebar nav::-webkit-scrollbar {
            display: none !important;
        }
        #sidebar,
        #sidebar nav {
            -ms-overflow-style: none !important;  /* IE and Edge */
            scrollbar-width: none !important;  /* Firefox */
        }

        /* Top nav link */
        .topnav-link { display:inline-flex; align-items:center; padding:8px 12px; border-radius:8px; font-size:13.5px; font-weight:500; color:rgba(255,255,255,.68); transition:all .15s; text-decoration:none; white-space:nowrap; }
        .topnav-link:hover { background:rgba(255,255,255,.08); color:#fff; }
        .topnav-link.active { background:rgba(255,255,255,.14); color:#fff; font-weight:600; }

        /* Status badges */
        .badge-green,
        .badge-orange,
        .badge-red,
        .badge-blue,
        .badge-gray,
        .badge-purple { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-green  { background:#DCFCE7; color:#15803D; }
        .badge-orange { background:#FEF3C7; color:#B45309; }
        .badge-red    { background:#FEE2E2; color:#DC2626; }
        .badge-blue   { background:#DBEAFE; color:#1D4ED8; }
        .badge-gray   { background:#F1F5F9; color:#475569; }
        .badge-purple { background:#EDE9FE; color:#7C3AED; }

        /* Dot indicators */
        .dot-green  { width:7px; height:7px; border-radius:99px; background:#16a34a; display:inline-block; }
        .dot-orange { width:7px; height:7px; border-radius:99px; background:#F59E0B; display:inline-block; }
        .dot-red    { width:7px; height:7px; border-radius:99px; background:#EF4444; display:inline-block; }
        .dot-blue   { width:7px; height:7px; border-radius:99px; background:#3B82F6; display:inline-block; }

        /* Stat card */
        .stat-card { background:#fff; border:1px solid #E2E8F0; border-radius:12px; padding:16px 20px; min-height:128px; display:flex; flex-direction:column; justify-content:space-between; }

        /* Table */
        .data-table { width:100%; border-collapse:collapse; font-size:13px; }
        .data-table th { text-align:left; padding:10px 14px; font-size:11px; font-weight:600; color:#94A3B8; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #F1F5F9; background:#F8FAFC; }
        .data-table td { padding:12px 14px; border-bottom:1px solid #F1F5F9; color:#1E293B; vertical-align:middle; }
        .data-table tbody tr:hover { background:#F8FAFC; }
        .data-table tbody tr:last-child td { border-bottom:none; }

        /* Tabs */
        .tab-btn { padding:8px 16px; font-size:13px; font-weight:500; color:#64748B; border-bottom:2px solid transparent; cursor:pointer; white-space:nowrap; transition:all .15s; background:none; border-top:none; border-left:none; border-right:none; }
        .tab-btn.active { color:#16a34a; border-bottom-color:#16a34a; font-weight:600; }
        .tab-btn:hover:not(.active) { color:#1E293B; }

        /* Btn */
        .btn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; background:#16a34a; color:#fff; border:none; cursor:pointer; transition:background .15s; text-decoration:none; }
        .btn-primary:hover { background:#15803d; }
        .btn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500; background:#fff; color:#475569; border:1px solid #E2E8F0; cursor:pointer; transition:all .15s; text-decoration:none; }
        .btn-secondary:hover { background:#F8FAFC; border-color:#CBD5E1; }
        .btn-danger { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:500; background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; cursor:pointer; transition:all .15s; }
        .btn-danger:hover { background:#FEE2E2; }

        /* Input */
        .form-input { width:100%; padding:8px 12px; border:1px solid #E2E8F0; border-radius:8px; font-size:13px; color:#1E293B; background:#fff; outline:none; transition:border .15s; }
        .form-input:focus { border-color:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.1); }
        .form-select { padding:8px 32px 8px 12px; border:1px solid #E2E8F0; border-radius:8px; font-size:13px; color:#1E293B; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center; appearance:none; outline:none; cursor:pointer; transition:border .15s; }
        .form-select:focus { border-color:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.1); }

        /* Card */
        .card { background:#fff; border:1px solid #E2E8F0; border-radius:12px; }

        /* Sidebar aperçu */
        .apercu-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:12px; color:rgba(255,255,255,0.65); border-bottom:1px solid rgba(255,255,255,0.06); }
        .apercu-row:last-child { border-bottom:none; }
        .apercu-val { font-weight:700; color:#fff; }
        .apercu-val.danger { color:#F87171; }

        /* Checkbox */
        input[type=checkbox] { accent-color:#16a34a; width:14px; height:14px; cursor:pointer; }

        /* Panel detail */
        .detail-panel { border-left:1px solid #E2E8F0; }
        .detail-section { padding:16px; border-bottom:1px solid #F1F5F9; }
        .detail-label { font-size:11px; font-weight:600; color:#94A3B8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
        .detail-value { font-size:13px; font-weight:500; color:#1E293B; }

        /* Flash */
        .flash-success { background:#DCFCE7; border:1px solid #BBF7D0; color:#15803D; border-radius:8px; padding:10px 16px; font-size:13px; font-weight:500; }
        .flash-error   { background:#FEE2E2; border:1px solid #FECACA; color:#DC2626; border-radius:8px; padding:10px 16px; font-size:13px; font-weight:500; }

        [x-cloak] { display:none !important; }

        /* Top nav mobile toggle */
        @media (max-width: 1180px) {
            #topnav-links { display:none; }
        }
    </style>
    @stack('styles')
<link rel="stylesheet" href="{{ asset('fonts/operations/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/logistics-navigation.css') }}">
<script defer src="{{ asset('js/logistics-navigation.js') }}"></script>
</head>
<body class="h-full flex flex-col overflow-hidden">

@include('logistics.operations.icons')
@include('logistics.operations.navigation')

<!-- MAIN -->
<div class="flex-1 flex flex-col overflow-hidden min-w-0">

    <!-- Breadcrumb bar -->
    <div class="flex items-center gap-2 text-sm px-6 py-2.5 bg-white flex-shrink-0" style="border-bottom:1px solid #E2E8F0;">
        <span class="text-slate-400 font-medium">Logistique</span>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-slate-300"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="font-600 text-slate-800">@yield('crumb', 'Dashboard')</span>
    </div>

    <!-- Erreurs uniquement : les confirmations vertes ont été retirées de l'espace logistique. -->
    @if(session('error'))
    <div class="mx-6 mt-4 flash-error">{{ session('error') }}</div>
    @endif

    <!-- CONTENT -->
    <div class="flex-1 overflow-y-auto min-h-0">
        @yield('content')
    </div>
</div>

<script>
window.OVANIE_MAPBOX = {
    token: @js(config('geo.mapbox.public_token')),
    style: @js(config('geo.mapbox.style_url', 'mapbox://styles/mapbox/streets-v12')),
    center: [Number(@js(config('geo.default_lng', -4.008256))), Number(@js(config('geo.default_lat', 5.359952)))],
    zoom: Number(@js(config('geo.default_zoom', 12))),
};
</script>

@stack('scripts')
</body>
</html>
