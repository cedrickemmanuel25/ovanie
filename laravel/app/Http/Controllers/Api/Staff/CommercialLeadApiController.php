<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\StoreCommercialActivityRequest;
use App\Http\Requests\Commercial\StoreCommercialLeadRequest;
use App\Models\CommercialLead;
use Illuminate\Http\Request;

class CommercialLeadApiController extends Controller
{
    public function index(Request $request)
    {
        return CommercialLead::with(['user:id,name,email,phone', 'shop:id,name,status', 'assignee:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->query('assigned_to')))
            ->latest()->paginate(50);
    }

    public function store(StoreCommercialLeadRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $lead = CommercialLead::create($data);
        return response()->json($lead->load(['user', 'shop', 'assignee']), 201);
    }

    public function show(CommercialLead $lead)
    {
        return $lead->load(['user', 'shop', 'assignee', 'businessRequest', 'devis', 'appelOffre', 'activities.author']);
    }

    public function update(StoreCommercialLeadRequest $request, CommercialLead $lead)
    {
        $lead->update($request->validated());
        return $lead->fresh(['user', 'shop', 'assignee']);
    }


    public function activity(StoreCommercialActivityRequest $request, CommercialLead $lead)
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;
        $data['happened_at'] ??= now();
        $activity = $lead->activities()->create($data);

        if (! empty($data['next_follow_up_at'])) {
            $lead->update(['next_action_at' => $data['next_follow_up_at']]);
        }

        return response()->json($activity->load('author'), 201);
    }
}
