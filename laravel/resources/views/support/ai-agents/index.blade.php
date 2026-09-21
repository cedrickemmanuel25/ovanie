@extends('layouts.staff')
@section('title', 'Agents IA Support | OVANIE')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Agents IA du Support</h1>
        <p class="page-subtitle">Les agents utilisent uniquement les canaux réellement configurés et les données reliées de la plateforme.</p>
    </div>
    <div class="page-actions"><a class="btn" href="{{ route('support.conversations.index') }}"><i data-lucide="messages-square"></i>Conversations</a></div>
</div>

<section class="card" style="margin-bottom:14px">
    <div class="card-head"><div><h2>État réel des services</h2><p>Un service non opérationnel n’est pas proposé aux agents IA.</p></div></div>
    <div class="grid kpi-grid" style="margin-bottom:0">
        @foreach($services as $service)
            <div class="kpi-card" style="box-shadow:none">
                <div class="kpi-head">
                    <span class="kpi-label">{{ $service['label'] }}</span>
                    <span class="status {{ $service['operational'] ? 'active' : 'unavailable' }}">{{ $service['operational'] ? 'Opérationnel' : 'Indisponible' }}</span>
                </div>
                <div class="kpi-foot" style="margin-top:10px">{{ $service['provider'] ?: 'Aucun fournisseur' }}</div>
                <div class="record-sub">{{ $service['message'] }}</div>
            </div>
        @endforeach
    </div>
</section>

<div class="grid three-columns">
@forelse($agents as $agent)
    <article class="card">
        <div class="card-head">
            <div><h2>{{ $agent->name }}</h2><p>{{ $agent->code }} · {{ ucfirst($agent->role_key) }}</p></div>
            <span class="status {{ $agent->status }}">{{ str_replace('_', ' ', $agent->status) }}</span>
        </div>
        <p class="page-subtitle" style="min-height:58px;margin-bottom:14px">{{ $agent->description }}</p>
        <div class="detail-list">
            <div class="detail-row"><span>Canaux actifs</span><strong>{{ collect($agent->channels ?? [])->implode(' · ') ?: 'Aucun canal opérationnel' }}</strong></div>
            <div class="detail-row"><span>Conversations actives</span><strong>{{ $agent->active_conversations_count }}</strong></div>
            <div class="detail-row"><span>Appels actifs</span><strong>{{ $agent->active_calls_count }}</strong></div>
            <div class="detail-row"><span>Transferts ouverts</span><strong>{{ $agent->pending_handoffs_count }}</strong></div>
            <div class="detail-row"><span>Tickets IA réels</span><strong>{{ $agent->ai_tickets_count }}</strong></div>
            <div class="detail-row"><span>Voix</span><strong>{{ $agent->voice_name ?: 'Voix du fournisseur' }}</strong></div>
        </div>

        @if(auth('admin')->user()->hasStaffPermission('support.ai.agents.manage'))
        <form method="POST" action="{{ route('support.ai-agents.update', $agent) }}" style="margin-top:15px">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="form-group"><label>Statut</label><select name="status"><option value="active" @selected($agent->status==='active')>Actif</option><option value="paused" @selected($agent->status==='paused')>En pause</option><option value="unavailable" @selected($agent->status==='unavailable')>Indisponible</option></select></div>
                <div class="form-group"><label>Nom de voix</label><input name="voice_name" value="{{ $agent->voice_name }}" placeholder="Ex. alice"></div>
                <div class="form-group full">
                    <label>Canaux autorisés et réellement disponibles</label>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
                        @foreach(['chat' => 'Chat web', 'phone' => 'Téléphone', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail'] as $key => $label)
                            @php $service = $services[$key === 'phone' ? 'telephony' : $key]; @endphp
                            <label style="display:flex;align-items:center;gap:8px;padding:9px;border:1px solid var(--line);border-radius:7px;opacity:{{ $service['operational'] ? 1 : .55 }}">
                                <input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, $agent->channels ?? [], true)) @disabled(!$service['operational'])>
                                <span>{{ $label }}<small class="record-sub">{{ $service['operational'] ? 'Disponible' : 'Non configuré' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" type="submit" style="margin-top:12px"><i data-lucide="save"></i>Enregistrer</button>
        </form>
        @endif
    </article>
@empty
    <div class="card" style="grid-column:1/-1"><div class="empty"><i data-lucide="bot-off"></i><div>Aucun agent IA n’est configuré.</div></div></div>
@endforelse
</div>
@endsection
