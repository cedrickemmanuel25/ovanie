<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\StoreCommercialActivityRequest;
use App\Http\Requests\Commercial\StoreCommercialLeadRequest;
use App\Models\CommercialLead;
use App\Models\User;
use App\Services\SupportAi\SupportHandoffQueueService;
use Illuminate\Http\Request;

class CommercialLeadController extends Controller
{
    public function index(Request $request)
    {
        $query = CommercialLead::with(['assignee', 'user', 'shop'])->latest();
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($x) => $x->where('reference', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%")->orWhere('company_name', 'like', "%{$q}%")->orWhere('contact_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        foreach (['status', 'lead_type', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) $query->where($filter, $request->query($filter));
        }
        return view('commercial.leads.index', ['leads' => $query->paginate(25)->withQueryString(), 'agents' => $this->agents()]);
    }

    public function create(Request $request)
    {
        return view('commercial.leads.create', ['agents' => $this->agents(), 'defaults' => $request->all()]);
    }

    public function store(StoreCommercialLeadRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['assigned_to'] ??= $request->user()->id;
        $lead = CommercialLead::create($this->statusDates($data));
        return redirect()->route('commercial.leads.show', $lead)->with('success', 'Opportunité créée.');
    }

    public function show(CommercialLead $lead)
    {
        $lead->load([
            'assignee', 'creator', 'user', 'shop', 'businessRequest.user', 'devis', 'appelOffre',
            'activities.author', 'supportConversation.requester', 'supportConversation.aiAgent',
            'supportConversation.order', 'supportConversation.payment', 'supportConversation.shipment',
            'supportConversation.ticket', 'supportConversation.deliveryIncident',
        ]);
        return view('commercial.leads.show', ['lead' => $lead, 'agents' => $this->agents()]);
    }

    public function update(
        StoreCommercialLeadRequest $request,
        CommercialLead $lead,
        SupportHandoffQueueService $queues,
    ) {
        $lead->update($this->statusDates($request->validated(), $lead));

        if (in_array($lead->status, ['won', 'lost', 'cancelled'], true)) {
            foreach ($lead->supportConversation?->handoffs()->open()->where('target_department', 'commercial')->get() ?? [] as $handoff) {
                $queues->resolve(
                    $handoff,
                    $request->user('admin') ?: $request->user(),
                    'Opportunité commerciale clôturée avec le statut '.$lead->status.'.',
                    'commercial_follow_up',
                );
            }
        }

        return back()->with('success', 'Opportunité mise à jour et file Support synchronisée.');
    }

    public function activity(StoreCommercialActivityRequest $request, CommercialLead $lead)
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;
        $data['happened_at'] ??= now();
        $lead->activities()->create($data);
        if (! empty($data['next_follow_up_at'])) $lead->update(['next_action_at' => $data['next_follow_up_at']]);
        return back()->with('success', 'Activité commerciale enregistrée.');
    }

    private function statusDates(array $data, ?CommercialLead $lead = null): array
    {
        if (($data['status'] ?? null) === 'won' && ! $lead?->won_at) $data['won_at'] = now();
        if (($data['status'] ?? null) === 'lost' && ! $lead?->lost_at) $data['lost_at'] = now();
        return $data;
    }

    private function agents()
    {
        return User::where('role', 'commercial')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }
}
