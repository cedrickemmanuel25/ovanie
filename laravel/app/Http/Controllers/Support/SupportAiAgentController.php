<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportAiAgent;
use App\Services\SupportAi\SupportAiAuditLogger;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportAiAgentController extends Controller
{
    public function index(SupportServiceStatusService $services)
    {
        $agents = SupportAiAgent::query()
            ->withCount([
                'conversations as active_conversations_count' => fn ($query) => $query->active(),
                'handoffs as pending_handoffs_count' => fn ($query) => $query->open(),
                'tickets as ai_tickets_count' => fn ($query) => $query->where('created_by_ai', true),
                'calls as active_calls_count' => fn ($query) => $query->whereIn('status', ['waiting', 'queued', 'ringing', 'in_progress', 'waiting_transfer']),
            ])
            ->orderBy('id')
            ->get();

        return view('support.ai-agents.index', [
            'agents' => $agents,
            'services' => $services->all(),
        ]);
    }

    public function update(
        Request $request,
        SupportAiAgent $agent,
        SupportServiceStatusService $services,
        SupportAiAuditLogger $audit,
    ) {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused', 'unavailable'])],
            'voice_name' => ['nullable', 'string', 'max:80'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['chat', 'phone', 'whatsapp', 'email'])],
        ]);

        $requestedChannels = array_values(array_unique($data['channels'] ?? $agent->channels ?? []));
        $operationalChannels = array_values(array_filter($requestedChannels, function (string $channel) use ($services) {
            $statusKey = $channel === 'phone' ? 'telephony' : $channel;
            return $services->channel($statusKey)['operational'];
        }));

        $before = $agent->only(['status', 'voice_name', 'channels']);
        $agent->update([
            'status' => $data['status'],
            'voice_name' => $data['voice_name'] ?? null,
            'channels' => $operationalChannels,
        ]);

        $audit->log([
            'ai_agent_id' => $agent->id,
            'actor_user_id' => $request->user('admin')->id,
            'action' => 'ai_agent_configuration_updated',
            'decision' => $agent->status,
            'risk_level' => 'low',
            'input' => $before,
            'output' => $agent->only(['status', 'voice_name', 'channels']),
        ]);

        $removed = array_values(array_diff($requestedChannels, $operationalChannels));
        $message = 'Configuration de l’agent IA mise à jour.';
        if ($removed !== []) {
            $message .= ' Canaux non configurés ignorés : '.implode(', ', $removed).'.';
        }

        return back()->with('success', $message);
    }
}
