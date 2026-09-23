<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportCallbackRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportCallbackController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('support.calls.index', array_filter([
            'tab' => 'callbacks',
            'callback_status' => $request->query('status'),
        ]));
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
