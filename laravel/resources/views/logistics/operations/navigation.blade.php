@php
    try {
        $pilotageUnreadCount = app(\App\Services\LogisticsPilotageDataService::class)->unreadNotificationCount();
    } catch (\Throwable $e) {
        $pilotageUnreadCount = 0;
    }
    $logisticsUser = auth('admin')->user();
    $logisticsUserName = $logisticsUser?->name ?: 'Responsable Logistique';
    $initials = collect(preg_split('/\s+/', trim($logisticsUserName)))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('') ?: 'RL';
@endphp
<header class="ops-topbar">
    <a class="ops-brand" href="{{ route('logistics.dashboard') }}" aria-label="OVANIE Logistics, accueil">
        <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="#00a66b" d="M28 3C17 4 4 6 4 16c0 8 8 12 15 8 6-4 8-11 9-21Z"/><path fill="#003b35" d="M22 10c-6 2-10 6-12 11 5-1 11-5 12-11Z"/><path fill="#008955" d="M6 22c6-1 11 0 15 4-8 5-14 2-15-4Z"/></svg>
        <strong>OVANIE <span>Logistics</span></strong>
    </a>
    <button class="ops-mobile-toggle ops-icon-button" aria-label="Ouvrir la navigation" aria-expanded="false" data-nav-toggle><x-operations.icon name="list"/></button>
    <nav class="ops-navigation" aria-label="Navigation logistique">
        <a class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}" href="{{ route('logistics.dashboard') }}">Accueil</a>
        <details class="ops-nav-menu"><summary class="{{ request()->routeIs('logistics.shipments*', 'logistics.assignments.*', 'logistics.active-deliveries', 'logistics.tours.*') ? 'active' : '' }}">Opérations <x-operations.icon name="chevron"/></summary><div class="ops-dropdown">
            @foreach(['shipments'=>['box','Expéditions'], 'assignments.index'=>['user','Affectation'], 'active-deliveries'=>['truck','Livraison en cours'], 'tours.index'=>['tour','Tournée']] as $target => [$icon,$label])
            <a href="{{ route('logistics.'.$target) }}" class="{{ request()->routeIs('logistics.'.$target) ? 'selected' : '' }}"><x-operations.icon :name="$icon"/>{{ $label }}</a>
            @endforeach
        </div></details>
        <details class="ops-nav-menu ops-tracking-menu"><summary class="{{ request()->routeIs('logistics.tracking*') ? 'active' : '' }}">Tracking <x-operations.icon name="chevron"/></summary><div class="ops-dropdown">
            @foreach(['tracking'=>['pin','Centre de suivi GPS'],'tracking.mission'=>['box','Suivi d’une mission'],'tracking.drivers'=>['user','Suivi des livreurs']] as $target=>[$icon,$label])
            <a href="{{ route('logistics.'.$target) }}" class="{{ request()->routeIs('logistics.'.$target) ? 'selected' : '' }}"><x-operations.icon :name="$icon"/>{{ $label }}</a>
            @endforeach
        </div></details>
        <a class="{{ request()->routeIs('logistics.drivers*') ? 'active' : '' }}" href="{{ route('logistics.drivers') }}">Livreurs</a>
        @foreach(['fleet'=>'Flotte','incidents.index'=>'Incidents','returns'=>'Retours','handoffs.index'=>'Support','zones'=>'Territoire','ovanie-pricing.index'=>'Tarification','partners'=>'Partenaires','control'=>'Pilotage'] as $route => $label)
        <a class="{{ request()->routeIs('logistics.'.$route, 'logistics.'.str_replace('.index','',$route).'.*')?'active':'' }}" href="{{ route('logistics.'.$route) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="ops-account">
        <a class="ops-notifications {{ request()->routeIs('logistics.control.notifications') ? 'is-active' : '' }}" href="{{ route('logistics.control.notifications') }}" aria-label="Notifications logistiques"><x-operations.icon name="bell"/>@if($pilotageUnreadCount>0)<b>{{ min(99,$pilotageUnreadCount) }}</b>@endif</a>
        <a href="{{ route('logistics.handoffs.index') }}" aria-label="Aide"><x-operations.icon name="help"/></a>
        <details class="ops-account-menu">
            <summary aria-label="Ouvrir le menu du responsable logistique">
                <span class="ops-account-avatar">{{ $initials }}</span>
                <span class="ops-account-name">{{ $logisticsUserName }}</span>
                <x-operations.icon name="chevron"/>
            </summary>
            <div class="ops-account-dropdown">
                <div class="ops-account-dropdown-head"><span class="ops-account-avatar">{{ $initials }}</span><div><strong>{{ $logisticsUserName }}</strong><small>Responsable Logistique</small></div></div>
                <a href="{{ route('logistics.control.reports') }}"><x-operations.icon name="chart"/><span>Rapports &amp; performances</span></a>
                <a href="{{ route('logistics.control.notifications') }}"><x-operations.icon name="bell"/><span>Notifications</span>@if($pilotageUnreadCount>0)<b>{{ $pilotageUnreadCount }}</b>@endif</a>
                <a href="{{ route('logistics.control.settings') }}" class="{{ request()->routeIs('logistics.control.settings') ? 'selected' : '' }}"><x-operations.icon name="settings"/><span>Paramètres logistiques</span></a>
                @if(\Illuminate\Support\Facades\Route::has('admin.logout'))
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit"><x-operations.icon name="back"/><span>Déconnexion</span></button></form>
                @endif
            </div>
        </details>
    </div>
</header>
