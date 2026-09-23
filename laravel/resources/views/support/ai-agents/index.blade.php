@extends('layouts.staff')
@section('title', 'Assistants automatisés | OVANIE Support')
@section('content')
@php
    $roleLabels = [
        'general' => 'Assistance générale',
        'business' => 'OVANIE Business',
        'logistics' => 'Assistance Logistique',
        'technical' => 'Assistance technique',
        'escalation' => 'Escalades et cas sensibles',
    ];
    $channelLabels = ['chat'=>'Chat web','web'=>'Chat web','phone'=>'Téléphone','whatsapp'=>'WhatsApp','email'=>'E-mail'];
    $statusLabels = ['active'=>'Actif','paused'=>'En pause','unavailable'=>'Indisponible'];
@endphp

<div class="page-header">
    <div class="page-header-main">
        <span class="page-header-icon"><i data-lucide="bot"></i></span>
        <div><h1 class="page-title">Assistants automatisés</h1><p class="page-subtitle">Vue d’administration des assistants réellement enregistrés dans OVANIE. Les chiffres affichés excluent les anciennes données de démonstration et proviennent des conversations, dossiers et transferts du workflow actuel.</p></div>
    </div>
    <div class="page-actions"><a class="btn" href="{{ route('support.conversations.index') }}"><i data-lucide="messages-square"></i>Boîte de réception</a></div>
</div>

<div class="grid kpi-grid" style="margin-bottom:16px">
    @foreach($services as $service)
        <div class="kpi-card">
            <div class="kpi-head"><span class="kpi-label">{{ $service['label'] }}</span><span class="status {{ $service['operational'] ? 'active' : 'unavailable' }}">{{ $service['operational'] ? 'Opérationnel' : 'Indisponible' }}</span></div>
            <div style="margin-top:12px;font-weight:800;color:#102d57">{{ $service['provider'] ? ucfirst((string)$service['provider']) : 'OVANIE' }}</div>
            <div class="record-sub" style="margin-top:6px">{{ $service['message'] }}</div>
        </div>
    @endforeach
</div>

<section class="card" style="margin-bottom:16px">
    <div class="card-head"><div><h2>Assistants configurés</h2><p>Un assistant n’est réellement disponible que si son statut est actif et si le canal ou moteur dont il dépend est opérationnel.</p></div></div>

    <div class="grid three-columns" style="padding:0 22px 22px">
    @forelse($agents as $agent)
        @php
            $agentChannels = collect($agent->channels ?? [])->map(fn($c)=>$channelLabels[$c] ?? ucfirst($c))->unique()->values();
            $lastActivity = collect([$agent->last_conversation_at, $agent->last_handoff_at])->filter()->sort()->last();
            $engineOk = (bool)($services['ai']['operational'] ?? false);
            $effectiveOk = $agent->status === 'active' && ($agent->role_key === 'logistics' || $engineOk || in_array('chat', $agent->channels ?? [], true));
        @endphp
        <article class="card" style="box-shadow:none;border:1px solid #dfe8f4">
            <div class="card-head">
                <div><h2>{{ $agent->name }}</h2><p>{{ $roleLabels[$agent->role_key] ?? 'Assistant OVANIE' }}</p></div>
                <span class="status {{ $effectiveOk ? 'active' : ($agent->status === 'paused' ? 'pending' : 'unavailable') }}">{{ $effectiveOk ? 'Disponible' : ($statusLabels[$agent->status] ?? 'Indisponible') }}</span>
            </div>

            <p class="page-subtitle" style="min-height:48px">{{ $agent->description ?: 'Assistant enregistré dans le Support OVANIE.' }}</p>

            <div class="detail-list" style="margin-top:12px">
                <div class="detail-row"><span>Canaux autorisés</span><strong>{{ $agentChannels->implode(' · ') ?: 'Aucun' }}</strong></div>
                <div class="detail-row"><span>Conversations en cours</span><strong>{{ $agent->active_conversations_count }}</strong></div>
                <div class="detail-row"><span>Nouvelles conversations aujourd’hui</span><strong>{{ $agent->conversations_today_count }}</strong></div>
                <div class="detail-row"><span>Transferts en attente</span><strong>{{ $agent->pending_handoffs_count }}</strong></div>
                <div class="detail-row"><span>Transferts créés aujourd’hui</span><strong>{{ $agent->handoffs_today_count }}</strong></div>
                <div class="detail-row"><span>Dossiers créés automatiquement</span><strong>{{ $agent->ai_tickets_count }}</strong></div>
                <div class="detail-row"><span>Dernière activité</span><strong>{{ $lastActivity ? \Carbon\Carbon::parse($lastActivity)->diffForHumans() : 'Aucune activité réelle' }}</strong></div>
            </div>

            @if(!$engineOk && $agent->role_key !== 'logistics')
                <div class="notice" style="margin-top:14px"><i data-lucide="triangle-alert"></i><div>Le moteur IA n’est pas opérationnel. Cet assistant peut rester configuré mais ne peut pas assurer toutes ses réponses automatiques.</div></div>
            @endif

            @if(auth('admin')->user()->hasStaffPermission('support.ai.agents.manage'))
                <details style="margin-top:14px"><summary style="cursor:pointer;color:#315f95;font-weight:800">Paramètres de l’assistant</summary>
                    <form method="POST" action="{{ route('support.ai-agents.update',$agent) }}" style="margin-top:12px">@csrf @method('PUT')
                        <div class="form-grid">
                            <div class="form-group full"><label>Statut</label><select name="status"><option value="active" @selected($agent->status==='active')>Actif</option><option value="paused" @selected($agent->status==='paused')>En pause</option><option value="unavailable" @selected($agent->status==='unavailable')>Indisponible</option></select></div>
                            <div class="form-group full"><label>Canaux autorisés</label><div style="display:grid;gap:9px">@foreach(['chat'=>'Chat web','phone'=>'Téléphone','whatsapp'=>'WhatsApp','email'=>'E-mail'] as $key=>$label)@php $service=$services[$key==='phone'?'telephony':$key]; @endphp<label style="display:flex;align-items:center;gap:8px;color:#4d6382"><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key,$agent->channels??[],true)) @disabled(!$service['operational'])> {{ $label }} @if(!$service['operational'])<span class="record-sub">indisponible</span>@endif</label>@endforeach</div></div>
                            <input type="hidden" name="voice_name" value="{{ $agent->voice_name }}">
                        </div>
                        <button class="btn btn-primary" type="submit" style="margin-top:12px"><i data-lucide="save"></i>Enregistrer</button>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <div class="empty" style="grid-column:1/-1"><i data-lucide="bot-off"></i><div>Aucun assistant réel configuré.</div><span class="record-sub">Aucune donnée fictive n’est affichée.</span></div>
    @endforelse
    </div>
</section>
@endsection
