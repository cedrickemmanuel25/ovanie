<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportCallbackRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportCallbackController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportCallbackRequest::with(['requester', 'assignee', 'call', 'ticket'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return view('support.callbacks.index', [
            'callbacks' => $query->paginate(25)->withQueryString(),
            'agents' => User::where('role', 'support')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, SupportCallbackRequest $callback)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'scheduled', 'completed', 'cancelled'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'preferred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        $callback->update($data);

        return back()->with('success', 'Demande de rappel mise à jour.');
    }
}
