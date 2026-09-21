<header class="ops-dispatch-header">
    <div class="ops-dispatch-header-main">
        <nav class="ops-breadcrumb">
            <a href="{{ route('logistics.dashboard') }}">Logistique</a>
            <x-operations.icon name="next"/>
            <span>Tracking</span>
        </nav>
        <div class="ops-dispatch-title-row">
            <div>
                <h1>{{ $trackingTitle ?? 'Tracking' }}</h1>
                <p>{{ $trackingSubtitle ?? 'Suivi GPS des livreurs et des missions' }}</p>
            </div>
            <div class="ops-dispatch-header-actions">
                <span class="ops-dispatch-live-pill" data-live-status><i></i> Temps réel</span>
                <span class="ops-dispatch-sync"><x-operations.icon name="clock"/><time data-tracking-sync>{{ now()->format('H:i') }}</time></span>
                <button class="ops-button ops-dispatch-fullscreen" type="button" data-tracking-fullscreen><x-operations.icon name="expand"/> Plein écran</button>
            </div>
        </div>
    </div>
</header>
