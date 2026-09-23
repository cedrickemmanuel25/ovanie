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
        ['section' => 'Service client'],
        ['label' => 'Boîte de réception', 'icon' => 'messages-square', 'route' => 'support.conversations.index'],
        ['label' => 'Dossiers Support', 'icon' => 'inbox', 'route' => 'support.tickets.index'],
        ['label' => 'Appels', 'icon' => 'phone', 'route' => 'support.calls.index'],
        ['section' => 'Outils'],
        ['label' => 'Recherche OVANIE', 'icon' => 'search', 'route' => 'support.search'],
        ['label' => 'Réponses & procédures', 'icon' => 'book-open-check', 'route' => 'support.knowledge.index'],
        ['section' => 'Suivi interne'],
        ['label' => 'Transferts aux équipes OVANIE', 'icon' => 'forward', 'route' => 'support.handoffs.index'],
    ];

    $supportAdminMenu = [
        ['label' => 'Assistants automatisés', 'icon' => 'bot', 'route' => 'support.ai-agents.index'],
        ['label' => 'Historique des actions', 'icon' => 'history', 'route' => 'support.ai-audit.index'],
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
    $routePermission = function (string $route) {
        return collect(config('staff.route_permissions', []))
            ->first(fn($permission, $pattern) => \Illuminate\Support\Str::is($pattern, $route));
    };
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <style>
        :root{--staff-sidebar:252px;--staff-topbar:68px;--staff-accent:{{ $accent }};--navy:#10264f;--ink:#18365f;--muted:#718096;--line:#e3e9f1;--soft:#f5f7fb;--white:#fff;--orange:#f97316;--green:#059669;--red:#dc2626;--amber:#d97706;--shadow:0 8px 28px rgba(16,38,79,.06)}
        *{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,system-ui,sans-serif;background:var(--soft);color:var(--navy)}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}.staff-shell{min-height:100vh}.staff-sidebar{position:fixed;inset:0 auto 0 0;width:var(--staff-sidebar);background:#fff;border-right:1px solid var(--line);z-index:50;display:flex;flex-direction:column}.staff-brand{height:86px;display:flex;align-items:center;padding:0 24px;border-bottom:1px solid #edf1f5}.staff-brand img{width:138px;max-height:52px;object-fit:contain}.staff-nav{padding:14px 10px 22px;overflow:auto;flex:1}.staff-section{margin:22px 12px 8px;padding-top:15px;border-top:1px solid #edf1f5;color:#98a2b3;font-size:9px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.staff-section:first-child{border-top:0;margin-top:2px;padding-top:0}.staff-link{min-height:44px;display:flex;align-items:center;gap:12px;padding:0 13px;border-radius:10px;color:#324d73;font-size:13px;font-weight:650;margin:3px 0;position:relative;transition:.15s}.staff-link:hover{background:#f5f8fd;color:var(--staff-accent)}.staff-link.active{background:#edf4ff;color:var(--staff-accent);font-weight:800}.staff-link.active:before{content:"";position:absolute;left:-10px;top:7px;bottom:7px;width:3px;border-radius:0 4px 4px 0;background:var(--staff-accent)}.staff-link svg{width:18px;height:18px;stroke-width:1.9}.staff-admin{margin:20px 4px 0;border-top:1px solid #edf1f5;padding-top:12px}.staff-admin summary{list-style:none;display:flex;align-items:center;gap:10px;padding:10px 9px;color:#667892;font-size:10.5px;font-weight:800;cursor:pointer;border-radius:8px}.staff-admin summary::-webkit-details-marker{display:none}.staff-admin summary:hover{background:#f7f9fc}.staff-admin summary .chev{margin-left:auto;width:14px;transition:.15s}.staff-admin[open] summary .chev{transform:rotate(180deg)}.staff-admin-links{padding:3px 0 0}.staff-admin .staff-link{font-size:11px;min-height:40px}.staff-sidebar-foot{padding:14px 16px 20px;border-top:1px solid #edf1f5}.staff-home{height:40px;display:flex;align-items:center;gap:10px;padding:0 11px;border-radius:9px;color:#5c6f8a;font-size:11px;font-weight:700}.staff-home:hover{background:#f4f7fb;color:var(--staff-accent)}
        .staff-topbar{position:fixed;top:0;left:var(--staff-sidebar);right:0;height:var(--staff-topbar);z-index:40;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 24px}.staff-menu-toggle{display:none;border:0;background:transparent;width:42px;height:42px;color:var(--navy)}.staff-topbar-title{display:flex;flex-direction:column;gap:3px}.staff-topbar-title small{font-size:9px;color:#98a2b3;font-weight:800;text-transform:uppercase;letter-spacing:.1em}.staff-topbar-title strong{font-size:14px}.staff-topbar-context{margin-left:24px;padding-left:24px;border-left:1px solid #e8edf3;color:#7c899c;font-size:10px}.staff-account{margin-left:auto;display:flex;align-items:center;gap:10px}.staff-account-copy{text-align:right}.staff-account-copy strong{display:block;font-size:11px}.staff-account-copy span{display:block;color:#8994a7;font-size:9px;margin-top:2px}.staff-avatar{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,var(--navy),var(--staff-accent));color:#fff;font-size:10px;font-weight:900}.staff-logout{border:0;background:transparent;width:38px;height:38px;border-radius:9px;color:#63738b;cursor:pointer}.staff-logout:hover{background:#fff0ee;color:var(--red)}.staff-main{margin-left:var(--staff-sidebar);padding-top:var(--staff-topbar);min-height:100vh}.staff-content{padding:26px;max-width:1580px;margin:0 auto}
        .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:20px}.page-header-main{display:flex;gap:13px;align-items:flex-start}.page-header-icon{width:42px;height:42px;border-radius:12px;background:#eaf2ff;color:var(--staff-accent);display:grid;place-items:center;flex:0 0 auto}.page-header-icon svg{width:20px}.page-title{margin:0;font-size:28px;letter-spacing:-.035em}.page-subtitle{margin:7px 0 0;color:var(--muted);font-size:13px;line-height:1.65;max-width:850px}.page-actions{display:flex;gap:9px;flex-wrap:wrap}.btn{min-height:40px;padding:0 15px;border:1px solid #d7dfeb;border-radius:9px;background:#fff;color:#294364;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.15s}.btn:hover{border-color:#a9c6f6;color:var(--staff-accent);background:#fbfdff}.btn-primary{background:var(--staff-accent);border-color:var(--staff-accent);color:#fff}.btn-primary:hover{color:#fff;background:var(--staff-accent);filter:brightness(.96)}.btn-soft{background:#edf4ff;border-color:#d8e6fb;color:#0b5bd3}.btn-danger{background:#fff5f4;border-color:#fecaca;color:#b91c1c}.btn svg{width:16px;height:16px}
        .grid{display:grid;gap:14px}.kpi-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px}.kpi-card,.card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;box-shadow:var(--shadow)}.kpi-card{padding:17px}.kpi-head{display:flex;align-items:center;justify-content:space-between;gap:12px}.kpi-label{color:#6e7c92;font-size:11px;font-weight:750}.kpi-icon{width:32px;height:32px;border-radius:9px;display:grid;place-items:center;background:#eef4ff;color:var(--staff-accent)}.kpi-icon svg{width:16px;height:16px}.kpi-value{margin-top:12px;font-size:24px;font-weight:850;letter-spacing:-.04em}.kpi-foot{margin-top:5px;color:#8c97a8;font-size:9.5px}.card{padding:18px}.card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.card-head h2{margin:0;font-size:14px}.card-head p{margin:4px 0 0;color:var(--muted);font-size:11px;line-height:1.5}.two-columns{grid-template-columns:minmax(0,1.55fr) minmax(300px,.85fr)}.three-columns{grid-template-columns:repeat(3,minmax(0,1fr))}
        .workspace-banner{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 22px;margin-bottom:16px;box-shadow:var(--shadow)}.workspace-banner h1{margin:0;font-size:25px;letter-spacing:-.035em}.workspace-banner p{margin:7px 0 0;color:#708096;line-height:1.65;font-size:13px;max-width:860px}.workspace-banner .eyebrow{margin-bottom:6px}.eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:8.5px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#55749d}.eyebrow svg{width:14px}.workspace-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .quick-search{display:grid;grid-template-columns:21px minmax(0,1fr) auto;align-items:center;gap:11px;background:#fff;border:1px solid #dfe6ef;border-radius:13px;padding:8px 8px 8px 14px;margin-bottom:16px;box-shadow:0 4px 15px rgba(15,35,68,.035)}.quick-search svg{width:18px;color:#7b8aa1}.quick-search input{border:0;outline:0;min-width:0;width:100%;height:38px;color:#16345e;font-size:12.5px;background:transparent}
        .tabs{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:14px}.tab{display:inline-flex;align-items:center;gap:7px;min-height:36px;padding:0 12px;border:1px solid #dde5ef;background:#fff;border-radius:9px;color:#5d708d;font-size:11px;font-weight:800}.tab:hover{border-color:#b7cdf0;color:var(--staff-accent)}.tab.active{background:#eaf2ff;border-color:#d6e5fb;color:#075ee8}.tab-count{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#f1f4f8;color:#718096;font-size:8px}.tab.active .tab-count{background:#fff;color:#075ee8}
        .toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.filters{display:flex;gap:8px;flex-wrap:wrap;margin:0}.field,.filters input,.filters select{height:40px;border:1px solid #dbe3ed;border-radius:9px;background:#fff;padding:0 11px;color:#233d62;font-size:12px;outline:none}.filters input{min-width:250px}.field:focus,.filters input:focus,.filters select:focus,textarea:focus{border-color:var(--staff-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--staff-accent) 10%,transparent)}
        .table-wrap{overflow:auto;border:1px solid #e7ecf2;border-radius:11px}.data-table{width:100%;border-collapse:collapse;min-width:760px}.data-table th{padding:11px 12px;background:#fafbfd;border-bottom:1px solid #e5eaf1;text-align:left;color:#75849a;font-size:9.5px;text-transform:uppercase;letter-spacing:.07em}.data-table td{padding:13px 12px;border-bottom:1px solid #edf1f5;font-size:12px;vertical-align:middle}.data-table tr:last-child td{border-bottom:0}.data-table tbody tr{transition:.12s}.data-table tbody tr:hover td{background:#fbfdff}.record-title{font-weight:800;color:#18365f}.record-sub{display:block;color:#8994a7;font-size:10.5px;margin-top:3px;line-height:1.4}.status{display:inline-flex;align-items:center;min-height:23px;padding:0 8px;border-radius:999px;background:#eef3f8;color:#50647f;font-size:9.5px;font-weight:850;text-transform:capitalize}.status.open,.status.new,.status.pending{background:#fff7e6;color:#a85b00}.status.in_progress,.status.qualified,.status.proposal,.status.negotiation,.status.shipped,.status.in_transit,.status.human{background:#eef4ff;color:#075ee8}.status.resolved,.status.closed,.status.won,.status.paid,.status.completed,.status.delivered,.status.active,.status.approved{background:#eafaf2;color:#047857}.status.urgent,.status.high,.status.escalated,.status.failed,.status.cancelled,.status.rejected,.status.lost,.status.suspended{background:#fff0ef;color:#c52d2d}.status.waiting_customer,.status.waiting_internal,.status.waiting_human,.status.waiting_transfer,.status.ringing,.status.scheduled{background:#f6f0ff;color:#7c3aed}.status.missed,.status.unavailable,.status.paused{background:#fff0ef;color:#c52d2d}
        .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-group{display:flex;flex-direction:column;gap:6px}.form-group.full{grid-column:1/-1}.form-group label{color:#354d70;font-size:10.5px;font-weight:800}.form-group input,.form-group select,.form-group textarea{width:100%;border:1px solid #dbe3ed;border-radius:9px;background:#fff;padding:10px 11px;color:#17345f;font-size:12px;outline:none}.form-group input,.form-group select{height:42px}.form-group textarea{min-height:110px;resize:vertical;line-height:1.55}.form-help{font-size:10px;color:#8b96a8}.form-section{padding:18px;border:1px solid #e6ebf2;border-radius:13px;background:#fff}.form-section+.form-section{margin-top:12px}.form-section-title{display:flex;align-items:center;gap:9px;margin-bottom:14px}.form-section-title span{width:30px;height:30px;border-radius:8px;background:#edf4ff;color:#075ee8;display:grid;place-items:center}.form-section-title svg{width:15px}.form-section-title h3{margin:0;font-size:12px}.form-section-title p{margin:3px 0 0;color:#8994a7;font-size:9px}
        .alert{margin-bottom:14px;padding:12px 14px;border-radius:10px;font-size:10.5px;font-weight:650}.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}.alert-error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.notice{display:flex;gap:10px;align-items:flex-start;border:1px solid #dde6f0;background:#fff;border-radius:11px;padding:12px 14px;margin-bottom:14px;color:#5f718b;font-size:10px;line-height:1.55}.notice svg{width:17px;flex:0 0 auto;color:#6f86a5}.notice.warning{border-color:#f7ddb2;background:#fffaf0;color:#915c11}.notice.error{border-color:#fecaca;background:#fff7f7;color:#b42318}.empty{padding:44px 20px;text-align:center;color:#7b879a}.empty svg{width:31px;height:31px;margin-bottom:10px}.pagination-row{display:flex;justify-content:space-between;align-items:center;margin-top:13px;color:#7e8a9d;font-size:9.5px}.timeline{display:grid;gap:9px}.timeline-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:10px}.timeline-dot{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;background:#edf4ff;color:var(--staff-accent);font-size:9px;font-weight:900}.timeline-dot svg{width:15px}.timeline-body{border:1px solid #e6ebf2;border-radius:10px;padding:11px}.timeline-body.internal{background:#fff9ec;border-color:#fde6b2}.timeline-meta{display:flex;justify-content:space-between;gap:10px;color:#8792a5;font-size:8.5px;margin-bottom:6px}.timeline-body p{margin:0;white-space:pre-line;font-size:11.5px;line-height:1.55;color:#344b6c}.detail-list{display:grid;gap:0}.detail-row{display:grid;grid-template-columns:130px minmax(0,1fr);gap:12px;padding:10px 0;border-bottom:1px solid #edf1f5;font-size:11.5px}.detail-row:last-child{border-bottom:0}.detail-row span:first-child{color:#8490a2}.detail-row strong{font-weight:750;word-break:break-word}.support-source-badge{display:inline-flex;align-items:center;gap:5px;background:#eef5ff;color:#245fa8;border-radius:999px;padding:5px 8px;font-size:8.2px;font-weight:800}.support-source-badge.vendor{background:#fff4e8;color:#b55c00}.support-source-badge.driver{background:#ecfdf5;color:#047857}.support-source-badge.commercial{background:#fff7ed;color:#c2410c}.support-section-title{display:flex;align-items:center;gap:9px;margin:19px 0 10px;font-size:10px;font-weight:900;color:#5c708f;text-transform:uppercase;letter-spacing:.07em}.support-section-title:after{content:"";height:1px;background:#e4eaf1;flex:1}.support-result-list{display:grid;gap:9px}.support-result-item{display:grid;grid-template-columns:42px minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid #e6ebf2;border-radius:12px;padding:12px 13px;transition:.15s}.support-result-item:hover{border-color:#bdd3f5;background:#fbfdff}.support-result-icon{width:40px;height:40px;border-radius:10px;background:#eef5ff;color:var(--staff-accent);display:grid;place-items:center}.support-result-icon svg{width:18px}.support-result-main strong{display:block;color:#18365f;font-size:11.5px;margin-top:4px}.support-result-main small{display:block;color:#8290a5;font-size:9px;margin-top:4px}.support-result-meta{display:flex;align-items:center;gap:8px}.support-result-meta>span:first-child{font-size:8.2px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#7890af}.support-result-actions{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}
        .channel-summary{display:flex;gap:8px;flex-wrap:wrap}.channel-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;background:#fff;border:1px solid #e0e7f0;border-radius:999px;color:#667892;font-size:9px;font-weight:750}.channel-dot{width:7px;height:7px;border-radius:50%;background:#cbd5e1}.channel-pill.ok .channel-dot{background:#10b981}.channel-pill.off .channel-dot{background:#ef4444}
        .modal-backdrop{position:fixed;inset:0;background:rgba(9,25,52,.48);z-index:100;display:none;align-items:center;justify-content:center;padding:20px}.modal-backdrop.open{display:flex}.modal{width:min(760px,100%);max-height:88vh;overflow:auto;background:#fff;border-radius:16px;box-shadow:0 30px 90px rgba(8,24,50,.25)}.modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #e8edf3}.modal-header h2{margin:0;font-size:15px}.modal-header p{margin:5px 0 0;color:#7b8799;font-size:9.5px}.modal-close{width:34px;height:34px;border:0;border-radius:9px;background:#f3f6fa;color:#52657f;display:grid;place-items:center;cursor:pointer}.modal-body{padding:20px}.modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #e8edf3;background:#fafbfd}.overlay{display:none}
        @media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two-columns{grid-template-columns:1fr}.staff-sidebar{transform:translateX(-100%);transition:.2s}.staff-topbar{left:0}.staff-main{margin-left:0}.staff-menu-toggle{display:grid;place-items:center}.staff-sidebar-open .staff-sidebar{transform:translateX(0)}.staff-sidebar-open .overlay{display:block;position:fixed;inset:0;background:rgba(4,20,48,.4);z-index:45}.staff-topbar-context{display:none}}
        @media(max-width:700px){.staff-content{padding:14px}.page-header,.workspace-banner{flex-direction:column}.page-actions,.workspace-actions{width:100%;justify-content:stretch}.page-actions .btn,.workspace-actions .btn{flex:1}.kpi-grid,.form-grid,.three-columns{grid-template-columns:1fr}.form-group.full{grid-column:auto}.staff-account-copy{display:none}.filters{width:100%}.filters input{min-width:100%;width:100%}.filters select{flex:1}.detail-row{grid-template-columns:1fr;gap:4px}.quick-search{grid-template-columns:20px 1fr}.quick-search .btn{grid-column:1/-1;width:100%}.support-result-item{grid-template-columns:40px 1fr}.support-result-actions{grid-column:1/-1;justify-content:stretch}.support-result-actions .btn{flex:1}}
        @yield('inline_styles')
    </style>
    @stack('styles')
</head>
<body>
<div class="staff-shell">
    <aside class="staff-sidebar" id="staffSidebar">
        <div class="staff-brand"><a href="{{ route($workspace . '.dashboard') }}"><img src="{{ asset('images/home/logo-ovanie.png') }}" alt="OVANIE"></a></div>
        <nav class="staff-nav" aria-label="Navigation {{ $workspaceLabel }}">
            @foreach($menu as $item)
                @if(isset($item['section']))
                    <div class="staff-section">{{ $item['section'] }}</div>
                @else
                    @php $requiredPermission = $routePermission($item['route']); @endphp
                    @continue($requiredPermission && ! $user?->hasStaffPermission($requiredPermission))
                    <a href="{{ route($item['route']) }}" class="staff-link {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index','.*',$item['route'])) ? 'active' : '' }}">
                        <i data-lucide="{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach

            @if($isSupport)
                @php
                    $visibleAdminItems = collect($supportAdminMenu)->filter(function($item) use ($routePermission, $user) {
                        $permission = $routePermission($item['route']);
                        return ! $permission || $user?->hasStaffPermission($permission);
                    });
                    $adminActive = $visibleAdminItems->contains(fn($item) => request()->routeIs($item['route']) || request()->routeIs(str_replace('.index','.*',$item['route'])));
                @endphp
                @if($visibleAdminItems->isNotEmpty())
                    <details class="staff-admin" @if($adminActive) open @endif>
                        <summary><i data-lucide="settings-2"></i><span>Administration Support</span><i class="chev" data-lucide="chevron-down"></i></summary>
                        <div class="staff-admin-links">
                            @foreach($visibleAdminItems as $item)
                                <a href="{{ route($item['route']) }}" class="staff-link {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index','.*',$item['route'])) ? 'active' : '' }}">
                                    <i data-lucide="{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endif
        </nav>
        <div class="staff-sidebar-foot"><a class="staff-home" href="{{ route('home') }}"><i data-lucide="arrow-left"></i><span>Retour sur OVANIE</span></a></div>
    </aside>
    <div class="overlay" data-close-sidebar></div>
    <header class="staff-topbar">
        <button class="staff-menu-toggle" type="button" data-open-sidebar aria-label="Ouvrir le menu"><i data-lucide="menu"></i></button>
        <div class="staff-topbar-title"><small>OVANIE</small><strong>{{ $workspaceLabel }}</strong></div>
        @if($isSupport)<div class="staff-topbar-context">Assistance humaine · suivi des demandes · relais vers les équipes OVANIE</div>@endif
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
document.addEventListener('DOMContentLoaded',()=>{
    if(window.lucide)lucide.createIcons();
    document.querySelector('[data-open-sidebar]')?.addEventListener('click',()=>document.body.classList.add('staff-sidebar-open'));
    document.querySelectorAll('[data-close-sidebar]').forEach(x=>x.addEventListener('click',()=>document.body.classList.remove('staff-sidebar-open')));
    document.querySelectorAll('[data-modal-open]').forEach(trigger=>trigger.addEventListener('click',()=>document.querySelector(trigger.dataset.modalOpen)?.classList.add('open')));
    document.querySelectorAll('[data-modal-close]').forEach(trigger=>trigger.addEventListener('click',()=>trigger.closest('.modal-backdrop')?.classList.remove('open')));
    document.querySelectorAll('.modal-backdrop').forEach(backdrop=>backdrop.addEventListener('click',event=>{if(event.target===backdrop)backdrop.classList.remove('open')}));
});
</script>
@stack('scripts')
</body>
</html>
