@extends('admin.layouts.app')
@section('title', 'Demandes du Support | Administration OVANIE')
@section('page-title', 'Demandes transmises par le Support')
@push('styles')
<style>
.support-admin-wrap{display:grid;gap:18px}.support-admin-hero{background:linear-gradient(135deg,#0d2d63,#0d62d8);color:#fff;border-radius:20px;padding:24px;box-shadow:0 16px 35px rgba(13,45,99,.14)}.support-admin-hero h2{font-size:26px;margin:0 0 8px}.support-admin-hero p{margin:0;max-width:900px;color:#dceaff;line-height:1.55}.support-admin-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.support-admin-kpi,.support-admin-panel{background:#fff;border:1px solid #e3e9f2;border-radius:16px;padding:18px}.support-admin-kpi span{display:block;color:#6b7890;font-size:12px;font-weight:700}.support-admin-kpi strong{display:block;color:#0b2d62;font-size:28px;margin-top:7px}.support-admin-filters{display:flex;gap:10px;flex-wrap:wrap}.support-admin-filters input,.support-admin-filters select{min-height:42px;border:1px solid #d7e0ec;border-radius:10px;padding:0 12px;background:#fff;color:#15345e}.support-admin-filters input{min-width:280px;flex:1}.support-admin-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:10px;padding:10px 14px;background:#0d62d8;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.support-admin-btn.secondary{background:#eef4ff;color:#0d52ae}.support-admin-table{width:100%;border-collapse:collapse;min-width:980px}.support-admin-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7890;background:#f7f9fc;text-align:left;padding:12px;border-bottom:1px solid #e2e8f1}.support-admin-table td{padding:14px 12px;border-bottom:1px solid #edf1f6;color:#18375f;vertical-align:top}.support-admin-table strong{color:#0b2d62}.support-admin-sub{display:block;color:#7a879b;font-size:11px;margin-top:3px}.support-admin-badge{display:inline-flex;padding:6px 9px;border-radius:999px;background:#eef4ff;color:#0d52ae;font-size:11px;font-weight:800}.support-admin-badge.done{background:#e9f8f0;color:#087a49}.support-admin-badge.warn{background:#fff4e5;color:#a55a00}.support-admin-actions{display:flex;gap:7px;flex-wrap:wrap}.support-admin-actions form{margin:0}.support-admin-resolve{display:grid;gap:8px;margin-top:8px}.support-admin-resolve textarea{width:100%;min-height:72px;border:1px solid #d7e0ec;border-radius:10px;padding:10px;resize:vertical}.support-admin-empty{text-align:center;padding:44px;color:#748198}.support-admin-pagination{margin-top:14px}.support-admin-table-wrap{overflow:auto}@media(max-width:1000px){.support-admin-kpis{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.support-admin-kpis{grid-template-columns:1fr}.support-admin-hero{padding:18px}}
</style>
@endpush
@section('content')
@php
    $statusLabels=['pending'=>'En attente','assigned'=>'Affecté','accepted'=>'Pris en charge','in_progress'=>'En traitement','resolved'=>'Réponse envoyée','closed'=>'Archivé','cancelled'=>'Annulé'];
@endphp
<div class="support-admin-wrap">
    <section class="support-admin-hero">
        <h2>Demandes reçues du Support OVANIE</h2>
        <p>Cette file contient uniquement les dossiers pour lesquels le Support a besoin d’une vérification ou d’une décision de l’Administration. L’Administration traite l’action demandée et retourne sa réponse ; le Support reste l’interlocuteur du client, du vendeur, du livreur partenaire ou du commercial.</p>
    </section>

    <section class="support-admin-kpis">
        <article class="support-admin-kpi"><span>Nouvelles demandes</span><strong>{{ $stats['new'] }}</strong></article>
        <article class="support-admin-kpi"><span>Affectées</span><strong>{{ $stats['assigned'] }}</strong></article>
        <article class="support-admin-kpi"><span>En traitement</span><strong>{{ $stats['in_treatment'] }}</strong></article>
        <article class="support-admin-kpi"><span>Réponses envoyées</span><strong>{{ $stats['resolved_total'] }}</strong></article>
    </section>

    <section class="support-admin-panel">
        <form class="support-admin-filters" method="GET" action="{{ route('admin.support-handoffs.index') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Référence, demandeur, dossier ou motif…">
            <select name="status"><option value="">Tous les statuts</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select>
            <select name="severity"><option value="">Toutes les priorités</option><option value="high" @selected(request('severity')==='high')>Haute / urgente</option><option value="normal" @selected(request('severity')==='normal')>Normale</option><option value="low" @selected(request('severity')==='low')>Faible</option></select>
            <button class="support-admin-btn" type="submit">Filtrer</button>
            <a class="support-admin-btn secondary" href="{{ route('admin.support-handoffs.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="support-admin-panel">
        <div class="support-admin-table-wrap">
            <table class="support-admin-table">
                <thead><tr><th>Référence</th><th>Dossier Support</th><th>Demandeur</th><th>Action demandée</th><th>Priorité</th><th>Statut</th><th>Traitement Administration</th></tr></thead>
                <tbody>
                @forelse($handoffs as $handoff)
                    @php
                        $ticket=$handoff->ticket ?: $handoff->conversation?->ticket;
                        $requesterName=$ticket?->requester_name ?: $ticket?->requester?->name ?: $handoff->conversation?->requester_name ?: $handoff->conversation?->requester?->name ?: 'Non identifié';
                        $isOpen=in_array($handoff->status,['pending','assigned','accepted','in_progress'],true);
                    @endphp
                    <tr>
                        <td><strong>{{ $handoff->reference }}</strong><span class="support-admin-sub">{{ $handoff->requested_at?->format('d/m/Y H:i') }}</span></td>
                        <td><strong>{{ $ticket?->reference ?: '—' }}</strong><span class="support-admin-sub">{{ $ticket?->subject ?: $handoff->conversation?->subject }}</span></td>
                        <td><strong>{{ $requesterName }}</strong></td>
                        <td>{{ \Illuminate\Support\Str::limit($handoff->reason,150) }}@if($handoff->notes)<span class="support-admin-sub"><strong>Réponse :</strong> {{ $handoff->notes }}</span>@endif</td>
                        <td><span class="support-admin-badge {{ in_array($handoff->severity,['high','urgent','critical'],true)?'warn':'' }}">{{ ucfirst($handoff->severity) }}</span></td>
                        <td><span class="support-admin-badge {{ in_array($handoff->status,['resolved','closed'],true)?'done':'' }}">{{ $statusLabels[$handoff->status] ?? $handoff->status }}</span></td>
                        <td>
                            @if($isOpen)
                                <div class="support-admin-actions">
                                    @if(in_array($handoff->status,['pending','assigned'],true))<form method="POST" action="{{ route('admin.support-handoffs.claim',$handoff) }}">@csrf<button class="support-admin-btn secondary" type="submit">Prendre en charge</button></form>@endif
                                </div>
                                <form class="support-admin-resolve" method="POST" action="{{ route('admin.support-handoffs.resolve',$handoff) }}">@csrf
                                    <textarea name="notes" required minlength="5" maxlength="3000" placeholder="Réponse / décision à retourner au Support…"></textarea>
                                    <input type="hidden" name="resolution_code" value="answered">
                                    <button class="support-admin-btn" type="submit">Envoyer la réponse au Support</button>
                                </form>
                            @else
                                <span class="support-admin-sub">Aucune action en attente.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="support-admin-empty">Aucune demande du Support n’est actuellement transmise à l’Administration.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="support-admin-pagination">{{ $handoffs->links() }}</div>
    </section>
</div>
@endsection
