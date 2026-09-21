@extends('layouts.logistics')
@section('title','Notifications')
@section('crumb','Pilotage › Notifications')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
    $types = [
        'gps' => ['Alerte GPS','blue','pin'],
        'delay' => ['Retard','orange','clock'],
        'incident' => ['Incident','red','warning'],
        'return' => ['Retour','purple','return'],
        'mission' => ['Mission','green','truck'],
    ];
    $priorityLabels = ['critical'=>'Critique','high'=>'Haute','medium'=>'Moyenne','info'=>'Info'];
    $lossThreshold = preg_replace('/[^0-9]/', '', (string)($settingValues['loss_alert'] ?? '10')) ?: '10';
    $delayThreshold = (int)($settingValues['critical_delay'] ?? 30);
    $missionThreshold = (int)($settingValues['unassigned_alert'] ?? 15);
    $channels = [
        ['channel_web','Notification web','bell'],
        ['channel_sms','SMS responsable','phone'],
        ['channel_whatsapp','WhatsApp support','phone'],
        ['channel_email','E-mail critique','mail'],
    ];
@endphp
<main class="mg-page notifications-page pilotage-v2">
    <header class="mg-titlebar pilotage-titlebar">
        <div><h1>Notifications</h1><p>Alertes opérationnelles générées à partir des données OVANIE Logistics.</p></div>
        <div class="mg-actions">
            <form method="POST" action="{{ route('logistics.control.notifications.read-all') }}">@csrf<button class="mg-btn"><x-operations.icon name="check"/>Tout marquer comme lu</button></form>
            <a class="mg-btn mg-btn-primary" href="{{ route('logistics.control.settings') }}"><x-operations.icon name="settings"/>Paramètres</a>
        </div>
    </header>

    <section class="mg-kpis mg-kpis-5 notification-kpis pilotage-notification-kpis">
        @foreach([
            ['gps','Alertes GPS','Positions à vérifier','blue','pin'],
            ['delay','Retards','Échéances dépassées','orange','clock'],
            ['incident','Incidents','Dossiers ouverts','red','warning'],
            ['return','Retours','Dossiers à traiter','purple','return'],
            ['mission','Missions non affectées','Colis prêts sans livreur','green','truck']
        ] as [$key,$label,$subtitle,$tone,$icon])
            <article class="mg-kpi">
                <span class="pilotage-notif-icon {{ $tone }}"><x-operations.icon :name="$icon"/></span>
                <div><strong>{{ $counts[$key] ?? 0 }}</strong><span>{{ $label }}</span><small>{{ $subtitle }}</small></div>
            </article>
        @endforeach
    </section>

    <form class="mg-filters notification-filters pilotage-notification-filters" method="GET">
        <label class="mg-search"><x-operations.icon name="search"/><input name="q" value="{{ request('q') }}" placeholder="Rechercher une notification, mission, référence..."></label>
        <select name="type"><option value="all">Type : Tous</option>@foreach($types as $key=>$meta)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $meta[0] }}</option>@endforeach</select>
        <select name="priority"><option value="all">Priorité : Toutes</option>@foreach($priorityLabels as $key=>$label)<option value="{{ $key }}" @selected(request('priority')===$key)>{{ $label }}</option>@endforeach</select>
        <select name="read"><option value="all">Statut : Tous</option><option value="unread" @selected(request('read')==='unread')>Non lu</option><option value="read" @selected(request('read')==='read')>Lu</option></select>
        <select name="period"><option value="today" @selected(request('period','today')==='today')>Période : Aujourd’hui</option><option value="7d" @selected(request('period')==='7d')>7 derniers jours</option><option value="30d" @selected(request('period')==='30d')>30 derniers jours</option><option value="all" @selected(request('period')==='all')>Toutes les dates</option></select>
        <button class="mg-btn pilotage-filter-submit" type="submit">Filtrer</button>
    </form>

    <section class="notification-layout">
        <article class="mg-card mg-table-card">
            <header class="mg-card-head"><div><h2>Centre de notifications</h2><small>{{ $counts['unread'] ?? 0 }} non lue(s)</small></div></header>
            <div class="mg-table-wrap"><table class="mg-table notif-table"><thead><tr><th>Type</th><th>Alerte / Message</th><th>Référence</th><th>Date &amp; heure</th><th>Priorité</th><th>Statut</th><th>Action</th></tr></thead><tbody>
            @forelse($notifications as $notification)
                @php($typeMeta = $types[$notification->type] ?? ['Notification','blue','bell'])
                <tr>
                    <td><span class="notif-type-svg {{ $typeMeta[1] }}"><x-operations.icon :name="$typeMeta[2]"/></span>{{ $typeMeta[0] }}</td>
                    <td><b>{{ $notification->title }}</b><small>{{ $notification->message ?: 'Aucun détail complémentaire.' }}</small></td>
                    <td>{{ $notification->reference ?: '—' }}<small>{{ $notification->location ?: 'Zone non renseignée' }}</small></td>
                    <td>{{ optional($notification->occurred_at)->format('d/m/Y') }}<small>{{ optional($notification->occurred_at)->format('H:i') }}</small></td>
                    <td><span class="priority {{ $notification->priority }}">{{ $priorityLabels[$notification->priority] ?? ucfirst($notification->priority) }}</span></td>
                    <td><span class="read-pill {{ $notification->is_read?'read':'unread' }}"><i></i>{{ $notification->is_read?'Lu':'Non lu' }}</span></td>
                    <td><a class="mg-btn small" href="{{ route('logistics.control.notifications', array_merge(request()->except(['page','open']), ['open'=>$notification->id])) }}">Ouvrir <x-operations.icon name="next"/></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="pilotage-table-empty">Aucune alerte opérationnelle correspondant aux filtres.</td></tr>
            @endforelse
            </tbody></table></div>
            @if($notifications->hasPages() || $notifications->total())
                <footer class="mg-table-footer"><span>{{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} sur {{ $notifications->total() }} notification(s)</span><div class="mg-pages">
                    @if($notifications->onFirstPage())<span>‹</span>@else<a href="{{ $notifications->previousPageUrl() }}">‹</a>@endif
                    @for($page=1;$page<=$notifications->lastPage();$page++)
                        <a class="{{ $notifications->currentPage()===$page?'active':'' }}" href="{{ $notifications->url($page) }}">{{ $page }}</a>
                    @endfor
                    @if($notifications->hasMorePages())<a href="{{ $notifications->nextPageUrl() }}">›</a>@else<span>›</span>@endif
                </div></footer>
            @endif
        </article>

        <aside class="mg-side-stack">
            <article class="mg-card mg-side-card">
                <header><h3><x-operations.icon name="chart"/> Répartition par type</h3></header>
                <div class="bar-list">
                    @php($maxCount=max(1,$counts['gps']??0,$counts['delay']??0,$counts['incident']??0,$counts['return']??0,$counts['mission']??0))
                    @foreach($types as $key=>$meta)
                        <div><span>{{ $meta[0] }}</span><i><b class="{{ $meta[1] }}" style="width:{{ (($counts[$key]??0)/$maxCount)*100 }}%"></b></i><strong>{{ $counts[$key] ?? 0 }}</strong></div>
                    @endforeach
                </div>
            </article>

            <article class="mg-card mg-side-card">
                <header><h3><x-operations.icon name="bell"/> Canaux configurés</h3><a href="{{ route('logistics.control.settings') }}">Modifier</a></header>
                <div class="channel-list pilotage-channel-list">
                    @foreach($channels as [$key,$label,$icon])
                        <label><span><x-operations.icon :name="$icon"/>{{ $label }}</span><input type="checkbox" disabled @checked(($settingValues[$key] ?? '0') === '1')></label>
                    @endforeach
                </div>
            </article>

            <article class="mg-card mg-side-card priority-rules">
                <header><h3><x-operations.icon name="settings"/> Règles prioritaires</h3></header>
                <div><span>Perte de signal GPS &gt; {{ $lossThreshold }} min</span><b class="red">Critique</b></div>
                <div><span>Retard &gt; {{ $delayThreshold }} min</span><b class="orange">Haute</b></div>
                <div><span>Incident critique</span><b class="red">Critique</b></div>
                <div><span>Mission non affectée &gt; {{ $missionThreshold }} min</span><b class="purple">Moyenne</b></div>
                <div><span>Retour accepté à organiser</span><b class="orange">Haute</b></div>
            </article>
        </aside>
    </section>
</main>
@endsection
