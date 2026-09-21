@extends('layouts.staff')
@section('title', $lead->reference . ' | Commercial OVANIE')
@section('content')
@php $money = fn($v) => number_format((float)$v,0,',',' ') . ' FCFA'; @endphp
<div class="page-header">
    <div><h1 class="page-title">{{ $lead->reference }} — {{ $lead->title }}</h1><p class="page-subtitle">Créée le {{ $lead->created_at?->format('d/m/Y à H:i') }} par {{ $lead->creator?->name ?: 'le système' }}.</p></div>
    <div class="page-actions"><a class="btn" href="{{ route('commercial.leads.index') }}"><i data-lucide="arrow-left"></i>Retour</a><span class="status {{ $lead->status }}">{{ str_replace('_',' ',$lead->status) }}</span></div>
</div>
<div class="grid two-columns">
    <div class="grid">
        <section class="card">
            <div class="card-head"><div><h2>Historique commercial</h2><p>Appels, e-mails, réunions, propositions et tâches de suivi.</p></div></div>
            <div class="timeline">
            @forelse($lead->activities as $activity)
                <article class="timeline-item">
                    <span class="timeline-dot">{{ mb_strtoupper(mb_substr($activity->type,0,2)) }}</span>
                    <div class="timeline-body">
                        <div class="timeline-meta"><strong>{{ ucfirst($activity->type) }} · {{ $activity->author?->name ?: 'Système' }}</strong><span>{{ $activity->happened_at?->format('d/m/Y H:i') }}</span></div>
                        @if($activity->subject)<p style="font-weight:800;margin-bottom:5px">{{ $activity->subject }}</p>@endif
                        <p>{{ $activity->description }}</p>
                        @if($activity->outcome)<p style="margin-top:7px;color:#66758b"><strong>Résultat :</strong> {{ $activity->outcome }}</p>@endif
                    </div>
                </article>
            @empty<div class="empty"><i data-lucide="history"></i><div>Aucune activité commerciale enregistrée.</div></div>@endforelse
            </div>
        </section>
        @if(auth()->user()->hasStaffPermission('leads.write'))
        <section class="card">
            <div class="card-head"><div><h2>Ajouter une activité</h2><p>Planifiez une relance pour alimenter automatiquement la prochaine action.</p></div></div>
            <form method="POST" action="{{ route('commercial.leads.activity',$lead) }}">@csrf
                <div class="form-grid">
                    <div class="form-group"><label>Type *</label><select name="type" required>@foreach(['note'=>'Note','call'=>'Appel','email'=>'E-mail','meeting'=>'Réunion','whatsapp'=>'WhatsApp','proposal'=>'Proposition','task'=>'Tâche'] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Date de l’activité</label><input type="datetime-local" name="happened_at" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                    <div class="form-group full"><label>Objet</label><input name="subject" value="{{ old('subject') }}"></div>
                    <div class="form-group full"><label>Compte rendu *</label><textarea name="description" required>{{ old('description') }}</textarea></div>
                    <div class="form-group"><label>Résultat</label><input name="outcome" value="{{ old('outcome') }}"></div>
                    <div class="form-group"><label>Prochaine relance</label><input type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}"></div>
                </div>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-orange" type="submit"><i data-lucide="plus"></i>Ajouter l’activité</button></div>
            </form>
        </section>
        @endif
    </div>
    <aside class="grid">
        @if(auth()->user()->hasStaffPermission('leads.write'))
        <section class="card">
            <div class="card-head"><div><h2>Mettre à jour l’opportunité</h2><p>Évolution du pipeline, valeur et responsable.</p></div></div>
            <form method="POST" action="{{ route('commercial.leads.update',$lead) }}">@csrf @method('PUT')
                @foreach(['user_id','shop_id','business_request_id','devis_id','appel_offre_id'] as $field)<input type="hidden" name="{{ $field }}" value="{{ old($field,$lead->{$field}) }}">@endforeach
                <div class="form-grid">
                    <div class="form-group"><label>Source</label><input name="source" value="{{ old('source',$lead->source) }}" required></div>
                    <div class="form-group"><label>Profil</label><select name="lead_type">@foreach(['buyer'=>'Acheteur','vendor'=>'Vendeur','business'=>'Business','partner'=>'Partenaire'] as $v=>$l)<option value="{{ $v }}" @selected(old('lead_type',$lead->lead_type)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Société</label><input name="company_name" value="{{ old('company_name',$lead->company_name) }}"></div>
                    <div class="form-group"><label>Contact *</label><input name="contact_name" value="{{ old('contact_name',$lead->contact_name) }}" required></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="{{ old('email',$lead->email) }}"></div>
                    <div class="form-group"><label>Téléphone</label><input name="phone" value="{{ old('phone',$lead->phone) }}"></div>
                    <div class="form-group"><label>Ville</label><input name="city" value="{{ old('city',$lead->city) }}"></div>
                    <div class="form-group"><label>Secteur</label><input name="sector" value="{{ old('sector',$lead->sector) }}"></div>
                    <div class="form-group full"><label>Objet *</label><input name="title" value="{{ old('title',$lead->title) }}" required></div>
                    <div class="form-group full"><label>Besoin</label><textarea name="need_summary">{{ old('need_summary',$lead->need_summary) }}</textarea></div>
                    <div class="form-group"><label>Étape</label><select name="status">@foreach(['new'=>'Nouveau','qualified'=>'Qualifié','proposal'=>'Proposition','negotiation'=>'Négociation','won'=>'Gagné','lost'=>'Perdu','cancelled'=>'Annulé'] as $v=>$l)<option value="{{ $v }}" @selected(old('status',$lead->status)===$v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Conseiller</label><select name="assigned_to"><option value="">Non assigné</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected((string)old('assigned_to',$lead->assigned_to)===(string)$agent->id)>{{ $agent->name ?: trim($agent->first_name.' '.$agent->last_name) }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Valeur estimée</label><input type="number" min="0" name="estimated_value" value="{{ old('estimated_value',$lead->estimated_value) }}"></div>
                    <div class="form-group"><label>Probabilité</label><input type="number" min="0" max="100" name="probability" value="{{ old('probability',$lead->probability) }}"></div>
                    <div class="form-group"><label>Conclusion prévue</label><input type="date" name="expected_close_at" value="{{ old('expected_close_at',$lead->expected_close_at?->format('Y-m-d')) }}"></div>
                    <div class="form-group"><label>Prochaine action</label><input type="datetime-local" name="next_action_at" value="{{ old('next_action_at',$lead->next_action_at?->format('Y-m-d\TH:i')) }}"></div>
                    <div class="form-group full"><label>Raison de perte</label><textarea name="lost_reason" style="min-height:75px">{{ old('lost_reason',$lead->lost_reason) }}</textarea></div>
                </div>
                <div class="page-actions" style="justify-content:flex-end;margin-top:14px"><button class="btn btn-orange" type="submit"><i data-lucide="save"></i>Mettre à jour</button></div>
            </form>
        </section>
        @endif
        <section class="card">
            <div class="card-head"><div><h2>Synthèse</h2><p>Informations principales et échéances.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Contact</span><strong>{{ $lead->contact_name }}</strong></div>
                <div class="detail-row"><span>Société</span><strong>{{ $lead->company_name ?: '—' }}</strong></div>
                <div class="detail-row"><span>Valeur</span><strong>{{ $money($lead->estimated_value) }}</strong></div>
                <div class="detail-row"><span>Probabilité</span><strong>{{ $lead->probability }}%</strong></div>
                <div class="detail-row"><span>Valeur pondérée</span><strong>{{ $money((float)$lead->estimated_value * $lead->probability / 100) }}</strong></div>
                <div class="detail-row"><span>Prochaine action</span><strong>{{ $lead->next_action_at?->format('d/m/Y H:i') ?: '—' }}</strong></div>
            </div>
        </section>
        @if($lead->supportConversation)
        @php $sourceConversation = $lead->supportConversation; @endphp
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Origine Support IA</h2>
                    <p>Opportunité qualifiée par Miss Rita depuis une conversation réelle, sans duplication des données métier.</p>
                </div>
                <span class="status active">Miss Rita</span>
            </div>
            <div class="detail-list">
                <div class="detail-row"><span>Conversation</span><strong>#{{ $sourceConversation->id }}</strong></div>
                <div class="detail-row"><span>Demandeur identifié</span><strong>{{ $sourceConversation->requester?->name ?: $sourceConversation->requester_name ?: 'Non identifié' }}</strong></div>
                <div class="detail-row"><span>Méthode d’identification</span><strong>{{ str_replace('_', ' ', $sourceConversation->requester_match_method ?: 'non rapproché') }}</strong></div>
                <div class="detail-row"><span>Canal</span><strong>{{ strtoupper($sourceConversation->channel ?: '—') }}</strong></div>
                <div class="detail-row"><span>Statut Support</span><strong>{{ str_replace('_', ' ', $sourceConversation->status ?: '—') }}</strong></div>
                <div class="detail-row"><span>Commande liée</span><strong>{{ $sourceConversation->order?->order_number ?: ($sourceConversation->order_id ? '#'.$sourceConversation->order_id : '—') }}</strong></div>
                <div class="detail-row"><span>Paiement lié</span><strong>{{ $sourceConversation->payment?->reference ?: ($sourceConversation->payment_id ? '#'.$sourceConversation->payment_id : '—') }}</strong></div>
                <div class="detail-row"><span>Livraison liée</span><strong>{{ $sourceConversation->shipment?->tracking_number ?: ($sourceConversation->shipment_id ? '#'.$sourceConversation->shipment_id : '—') }}</strong></div>
                <div class="detail-row"><span>Incident logistique</span><strong>{{ $sourceConversation->deliveryIncident ? '#'.$sourceConversation->deliveryIncident->id.' · '.str_replace('_', ' ', $sourceConversation->deliveryIncident->status) : '—' }}</strong></div>
                <div class="detail-row"><span>Ticket Support</span><strong>{{ $sourceConversation->ticket?->reference ?: ($sourceConversation->support_ticket_id ? '#'.$sourceConversation->support_ticket_id : '—') }}</strong></div>
            </div>
            @if($sourceConversation->summary)
                <div style="margin-top:16px;padding:14px;border:1px solid #e4eaf2;border-radius:12px;background:#f8fafc;color:#4b5d73;font-size:13px;line-height:1.65">
                    <strong style="display:block;color:#17345b;margin-bottom:5px">Résumé transmis par le Support</strong>
                    {{ $sourceConversation->summary }}
                </div>
            @endif
        </section>
        @endif

        <section class="card">
            <div class="card-head"><div><h2>Données liées</h2><p>Références vers les modules centraux.</p></div></div>
            <div class="detail-list">
                <div class="detail-row"><span>Compte client</span><strong>{{ $lead->user?->name ?: ($lead->user_id ? '#'.$lead->user_id : '—') }}</strong></div>
                <div class="detail-row"><span>Boutique</span><strong>{{ $lead->shop?->name ?: ($lead->shop_id ? '#'.$lead->shop_id : '—') }}</strong></div>
                <div class="detail-row"><span>Demande Business</span><strong>{{ $lead->businessRequest?->title ?: ($lead->business_request_id ? '#'.$lead->business_request_id : '—') }}</strong></div>
                <div class="detail-row"><span>Devis</span><strong>{{ $lead->devis?->projet ?: ($lead->devis_id ? '#'.$lead->devis_id : '—') }}</strong></div>
                <div class="detail-row"><span>Appel d’offre</span><strong>{{ $lead->appel_offre_id ? '#'.$lead->appel_offre_id : '—' }}</strong></div>
            </div>
        </section>
    </aside>
</div>
@endsection
