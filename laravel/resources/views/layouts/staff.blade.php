@php
    $user = auth('admin')->user();
    $workspace = $user?->role === 'commercial' ? 'commercial' : 'support';
    $isSupport = $workspace === 'support';
    $workspaceLabel = $isSupport ? 'Support Center' : 'Espace Commercial';
    $workspaceRole = $isSupport ? 'Agent support' : 'Conseiller commercial';
    $accent = $isSupport ? '#075ee8' : '#f97316';
    $initials = collect(preg_split('/\s+/', trim((string) ($user?->name ?: 'OVANIE'))))->filter()->take(2)->map(fn($x) => mb_strtoupper(mb_substr($x, 0, 1)))->implode('');

    $supportMenu = [
        ['label' => 'Tableau de bord', 'icon' => 'layout-dashboard', 'route' => 'support.dashboard'],
        ['section' => 'Support IA'],
        ['label' => 'Agents IA', 'icon' => 'bot', 'route' => 'support.ai-agents.index'],
        ['label' => 'Conversations', 'icon' => 'messages-square', 'route' => 'support.conversations.index'],
        ['label' => 'Appels', 'icon' => 'headphones', 'route' => 'support.calls.index'],
        ['label' => 'Transferts humains', 'icon' => 'user-round-check', 'route' => 'support.handoffs.index'],
        ['label' => 'Demandes de rappel', 'icon' => 'phone-call', 'route' => 'support.callbacks.index'],
        ['label' => 'Base de connaissances', 'icon' => 'book-open-check', 'route' => 'support.knowledge.index'],
        ['label' => 'Supervision IA', 'icon' => 'scroll-text', 'route' => 'support.ai-audit.index'],
        ['section' => 'Service client'],
        ['label' => 'Tous les tickets', 'icon' => 'inbox', 'route' => 'support.tickets.index'],
        ['label' => 'Créer un ticket', 'icon' => 'circle-plus', 'route' => 'support.tickets.create'],
        ['label' => 'Messages entrants', 'icon' => 'mail', 'route' => 'support.messages.index'],
        ['label' => 'Clients', 'icon' => 'users', 'route' => 'support.clients.index'],
        ['label' => 'Vendeurs', 'icon' => 'store', 'route' => 'support.vendors.index'],
        ['section' => 'Opérations'],
        ['label' => 'Commandes', 'icon' => 'clipboard-list', 'route' => 'support.orders.index'],
        ['label' => 'Paiements', 'icon' => 'credit-card', 'route' => 'support.payments.index'],
        ['label' => 'Livraisons', 'icon' => 'truck', 'route' => 'support.deliveries.index'],
        ['label' => 'Incidents', 'icon' => 'triangle-alert', 'route' => 'support.incidents.index'],
        ['label' => 'Retours', 'icon' => 'rotate-ccw', 'route' => 'support.returns.index'],
        ['label' => 'Litiges', 'icon' => 'shield-alert', 'route' => 'support.disputes.index'],
    ];

    $commercialMenu = [
        ['label' => 'Tableau de bord', 'icon' => 'layout-dashboard', 'route' => 'commercial.dashboard'],
        ['section' => 'Pipeline commercial'],
        ['label' => 'Opportunités', 'icon' => 'chart-no-axes-combined', 'route' => 'commercial.leads.index'],
        ['label' => 'Nouvelle opportunité', 'icon' => 'circle-plus', 'route' => 'commercial.leads.create'],
        ['label' => 'Transferts de Miss Rita', 'icon' => 'user-round-check', 'route' => 'commercial.handoffs.index'],
        ['label' => 'Clients', 'icon' => 'users', 'route' => 'commercial.clients.index'],
        ['label' => 'Vendeurs & boutiques', 'icon' => 'store', 'route' => 'commercial.vendors.index'],
        ['section' => 'Prospection vendeurs'],
        ['label' => 'Ma mission de prospection', 'icon' => 'route', 'route' => 'commercial.prospecting.areas'],
        ['label' => 'Vendeurs prospectés', 'icon' => 'store-plus', 'route' => 'commercial.prospecting.prospects.index'],
        ['section' => 'Données marketplace'],
        ['label' => 'Produits', 'icon' => 'package', 'route' => 'commercial.products.index'],
        ['label' => 'Suivi commandes', 'icon' => 'clipboard-list', 'route' => 'commercial.orders.index'],
        ['label' => 'Demandes Business', 'icon' => 'briefcase-business', 'route' => 'commercial.business.index'],
        ['label' => 'Devis & appels d’offres', 'icon' => 'file-text', 'route' => 'commercial.quotes.index'],
    ];
    $menu = $isSupport ? $supportMenu : $commercialMenu;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $workspaceLabel . ' | OVANIE')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <style>
        :root{--staff-sidebar:244px;--staff-topbar:64px;--staff-accent:{{ $accent }};--navy:#10264f;--muted:#6b7890;--line:#e3e9f1;--soft:#f7f9fc;--white:#fff;--orange:#f97316;--green:#059669;--red:#dc2626;--amber:#d97706}
        *{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,system-ui,sans-serif;background:var(--soft);color:var(--navy)}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}.staff-shell{min-height:100vh}.staff-sidebar{position:fixed;inset:0 auto 0 0;width:var(--staff-sidebar);background:#fff;border-right:1px solid var(--line);z-index:50;display:flex;flex-direction:column}.staff-brand{height:88px;display:flex;align-items:center;padding:0 24px;border-bottom:1px solid #eef2f6}.staff-brand img{width:138px;max-height:54px;object-fit:contain}.staff-brand-copy{display:none}.staff-nav{padding:12px 9px 24px;overflow:auto;flex:1}.staff-section{margin:25px 12px 9px;padding-top:17px;border-top:1px solid #e5eaf0;color:#8994a7;font-size:9px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.staff-section:first-child{border-top:0;margin-top:5px;padding-top:0}.staff-link{min-height:43px;display:flex;align-items:center;gap:12px;padding:0 13px;border-radius:7px;color:#2e466d;font-size:12px;font-weight:650;margin:2px 0;position:relative}.staff-link:hover{background:#f3f7fd;color:var(--staff-accent)}.staff-link.active{background:color-mix(in srgb,var(--staff-accent) 9%,white);color:var(--staff-accent);font-weight:800}.staff-link.active:before{content:"";position:absolute;left:-9px;top:3px;bottom:3px;width:3px;border-radius:0 4px 4px 0;background:var(--staff-accent)}.staff-link svg{width:18px;height:18px;stroke-width:1.9}.staff-sidebar-foot{padding:14px 16px 22px;border-top:1px solid #edf1f5}.staff-home{height:38px;display:flex;align-items:center;gap:10px;padding:0 11px;border-radius:7px;color:#536784;font-size:11px;font-weight:700}.staff-home:hover{background:#f4f7fb;color:var(--staff-accent)}.staff-topbar{position:fixed;top:0;left:var(--staff-sidebar);right:0;height:var(--staff-topbar);z-index:40;background:rgba(255,255,255,.98);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 20px}.staff-menu-toggle{display:none;border:0;background:transparent;width:42px;height:42px;color:var(--navy)}.staff-topbar-title{display:flex;flex-direction:column;gap:3px}.staff-topbar-title small{font-size:9px;color:#8792a4;font-weight:800;text-transform:uppercase;letter-spacing:.09em}.staff-topbar-title strong{font-size:13px}.staff-account{margin-left:auto;display:flex;align-items:center;gap:10px}.staff-account-copy{text-align:right}.staff-account-copy strong{display:block;font-size:11px}.staff-account-copy span{display:block;color:#8994a7;font-size:9px;margin-top:2px}.staff-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--navy),var(--staff-accent));color:#fff;font-size:10px;font-weight:900}.staff-logout{border:0;background:transparent;width:38px;height:38px;border-radius:7px;color:#63738b;cursor:pointer}.staff-logout:hover{background:#fff0ee;color:var(--red)}.staff-main{margin-left:var(--staff-sidebar);padding-top:var(--staff-topbar);min-height:100vh}.staff-content{padding:20px;max-width:1700px;margin:0 auto}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:17px}.page-title{margin:0;font-size:22px;letter-spacing:-.03em}.page-subtitle{margin:7px 0 0;color:var(--muted);font-size:12px;line-height:1.6}.page-actions{display:flex;gap:9px;flex-wrap:wrap}.btn{min-height:38px;padding:0 15px;border:1px solid #d7dfeb;border-radius:7px;background:#fff;color:#294364;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}.btn:hover{border-color:var(--staff-accent);color:var(--staff-accent)}.btn-primary{background:var(--staff-accent);border-color:var(--staff-accent);color:#fff}.btn-primary:hover{color:#fff;filter:brightness(.96)}.btn-orange{background:var(--orange);border-color:var(--orange);color:#fff}.btn-danger{background:#fff5f4;border-color:#fecaca;color:#b91c1c}.btn svg{width:16px;height:16px}.grid{display:grid;gap:14px}.kpi-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:14px}.kpi-card,.card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 8px 25px rgba(16,38,79,.035)}.kpi-card{padding:17px}.kpi-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.kpi-label{color:#6e7c92;font-size:10.5px;font-weight:700}.kpi-icon{width:31px;height:31px;border-radius:8px;display:grid;place-items:center;background:#eef4ff;color:var(--staff-accent)}.kpi-icon svg{width:16px;height:16px}.kpi-value{margin-top:12px;font-size:23px;font-weight:850;letter-spacing:-.04em}.kpi-foot{margin-top:5px;color:#8c97a8;font-size:9.5px}.card{padding:18px}.card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.card-head h2{margin:0;font-size:13px}.card-head p{margin:4px 0 0;color:var(--muted);font-size:9.5px}.two-columns{grid-template-columns:minmax(0,1.6fr) minmax(300px,.8fr)}.three-columns{grid-template-columns:repeat(3,minmax(0,1fr))}.table-wrap{overflow:auto;border:1px solid #e6ebf2;border-radius:10px}.data-table{width:100%;border-collapse:collapse;min-width:760px}.data-table th{padding:11px 12px;background:#f8fafc;border-bottom:1px solid #e4eaf1;text-align:left;color:#718096;font-size:9px;text-transform:uppercase;letter-spacing:.06em}.data-table td{padding:12px;border-bottom:1px solid #edf1f5;font-size:10.5px;vertical-align:middle}.data-table tr:last-child td{border-bottom:0}.data-table tr:hover td{background:#fbfdff}.record-title{font-weight:800;color:#18365f}.record-sub{display:block;color:#8994a7;font-size:9.5px;margin-top:3px}.status{display:inline-flex;align-items:center;min-height:24px;padding:0 9px;border-radius:999px;background:#eef3f8;color:#50647f;font-size:8.7px;font-weight:850;text-transform:capitalize}.status.open,.status.new,.status.pending{background:#fff7e6;color:#a85b00}.status.in_progress,.status.qualified,.status.proposal,.status.negotiation,.status.shipped,.status.in_transit{background:#eef4ff;color:#075ee8}.status.resolved,.status.closed,.status.won,.status.paid,.status.completed,.status.delivered,.status.active,.status.approved{background:#eafaf2;color:#047857}.status.urgent,.status.high,.status.escalated,.status.failed,.status.cancelled,.status.rejected,.status.lost,.status.suspended{background:#fff0ef;color:#c52d2d}.status.waiting_customer,.status.waiting_internal,.status.waiting_human,.status.waiting_transfer,.status.ringing{background:#f6f0ff;color:#7c3aed}.status.human,.status.in_progress{background:#eef4ff;color:#075ee8}.status.missed,.status.unavailable,.status.paused{background:#fff0ef;color:#c52d2d}.filters{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:14px}.field,.filters input,.filters select{height:39px;border:1px solid #dbe3ed;border-radius:7px;background:#fff;padding:0 11px;color:#233d62;font-size:10.5px;outline:none}.filters input{min-width:260px}.field:focus,.filters input:focus,.filters select:focus,textarea:focus{border-color:var(--staff-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--staff-accent) 10%,transparent)}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-group{display:flex;flex-direction:column;gap:6px}.form-group.full{grid-column:1/-1}.form-group label{color:#354d70;font-size:10px;font-weight:800}.form-group input,.form-group select,.form-group textarea{width:100%;border:1px solid #dbe3ed;border-radius:7px;background:#fff;padding:10px 11px;color:#17345f;font-size:11px;outline:none}.form-group input,.form-group select{height:42px}.form-group textarea{min-height:115px;resize:vertical;line-height:1.55}.form-help{font-size:8.8px;color:#8b96a8}.alert{margin-bottom:14px;padding:12px 14px;border-radius:9px;font-size:10.5px;font-weight:650}.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}.alert-error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.empty{padding:42px 20px;text-align:center;color:#7b879a}.empty svg{width:32px;height:32px;margin-bottom:10px}.pagination-row{display:flex;justify-content:space-between;align-items:center;margin-top:13px;color:#7e8a9d;font-size:9.5px}.pagination-actions{display:flex;gap:8px}.timeline{display:grid;gap:10px}.timeline-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:11px}.timeline-dot{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#edf4ff;color:var(--staff-accent);font-size:9px;font-weight:900}.timeline-body{border:1px solid #e5ebf2;border-radius:9px;padding:11px}.timeline-body.internal{background:#fff9ec;border-color:#fde6b2}.timeline-meta{display:flex;justify-content:space-between;gap:10px;color:#8792a5;font-size:8.5px;margin-bottom:7px}.timeline-body p{margin:0;white-space:pre-line;font-size:10.5px;line-height:1.6;color:#344b6c}.detail-list{display:grid;gap:0}.detail-row{display:grid;grid-template-columns:130px minmax(0,1fr);gap:12px;padding:10px 0;border-bottom:1px solid #edf1f5;font-size:10px}.detail-row:last-child{border-bottom:0}.detail-row span:first-child{color:#8490a2}.detail-row strong{font-weight:750;word-break:break-word}.overlay{display:none}
        @media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two-columns{grid-template-columns:1fr}.staff-sidebar{transform:translateX(-100%);transition:.2s}.staff-topbar{left:0}.staff-main{margin-left:0}.staff-menu-toggle{display:grid;place-items:center}.staff-sidebar-open .staff-sidebar{transform:translateX(0)}.staff-sidebar-open .overlay{display:block;position:fixed;inset:0;background:rgba(4,20,48,.4);z-index:45}}
        @media(max-width:700px){.staff-content{padding:12px}.page-header{flex-direction:column}.kpi-grid,.form-grid,.three-columns{grid-template-columns:1fr}.form-group.full{grid-column:auto}.staff-account-copy{display:none}.filters input{min-width:100%;width:100%}.filters select{flex:1}.page-actions{width:100%}.page-actions .btn{flex:1}.detail-row{grid-template-columns:1fr;gap:4px}}
        @yield('inline_styles')
    </style>
    @stack('styles')
</head>
<body>
<div class="staff-shell">
    <aside class="staff-sidebar" id="staffSidebar">
        <div class="staff-brand"><a href="{{ route($workspace . '.dashboard') }}"><img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE"></a></div>
        <nav class="staff-nav" aria-label="Navigation {{ $workspaceLabel }}">
            @foreach($menu as $item)
                @if(isset($item['section']))
                    <div class="staff-section">{{ $item['section'] }}</div>
                @else
                    @php
                        $requiredPermission = collect(config('staff.route_permissions', []))
                            ->first(fn($permission, $pattern) => \Illuminate\Support\Str::is($pattern, $item['route']));
                    @endphp
                    @continue($requiredPermission && ! $user?->hasStaffPermission($requiredPermission))
                    <a href="{{ route($item['route']) }}" class="staff-link {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index','.*',$item['route'])) ? 'active' : '' }}">
                        <i data-lucide="{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="staff-sidebar-foot"><a class="staff-home" href="{{ route('home') }}"><i data-lucide="arrow-left"></i><span>Retour sur OVANIE</span></a></div>
    </aside>
    <div class="overlay" data-close-sidebar></div>
    <header class="staff-topbar">
        <button class="staff-menu-toggle" type="button" data-open-sidebar aria-label="Ouvrir le menu"><i data-lucide="menu"></i></button>
        <div class="staff-topbar-title"><small>OVANIE</small><strong>{{ $workspaceLabel }}</strong></div>
        <div class="staff-account">
            <div class="staff-account-copy"><strong>{{ $user?->name }}</strong><span>{{ $user?->staffProfile?->job_title ?: $workspaceRole }}</span></div>
            <div class="staff-avatar">{{ $initials ?: 'OV' }}</div>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="staff-logout" type="submit" title="Déconnexion"><i data-lucide="log-out"></i></button></form>
        </div>
    </header>
    <main class="staff-main"><div class="staff-content">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </div></main>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{if(window.lucide)lucide.createIcons();document.querySelector('[data-open-sidebar]')?.addEventListener('click',()=>document.body.classList.add('staff-sidebar-open'));document.querySelectorAll('[data-close-sidebar]').forEach(x=>x.addEventListener('click',()=>document.body.classList.remove('staff-sidebar-open')));});
</script>
@stack('scripts')
</body>
</html>
