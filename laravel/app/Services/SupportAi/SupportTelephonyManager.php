<?php

namespace App\Services\SupportAi;

use App\Models\SupportAiAgent;
use App\Models\SupportCall;
use App\Models\SupportCallbackRequest;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\SupportAi\Contracts\SupportTelephonyProvider;
use App\Services\SupportAi\Providers\TwilioSupportTelephonyProvider;
use App\Services\SupportAi\Providers\WebhookSupportTelephonyProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupportTelephonyManager
{
    public function __construct(
        private readonly SupportAiOrchestrator $orchestrator,
        private readonly SupportIdentityResolver $identities,
        private readonly SupportEntityLinker $entities,
        private readonly SupportServiceStatusService $services,
        private readonly SupportAiAuditLogger $audit,
    ) {}

    public function initiateOutbound(array $data, User $actor): SupportCall
    {
        if (! $this->services->telephonyConfigured()) {
            throw new RuntimeException($this->services->channel('telephony')['message']);
        }

        return DB::transaction(function () use ($data, $actor) {
            $requester = ! empty($data['requester_user_id'])
                ? User::find($data['requester_user_id'])
                : $this->identities->resolve(null, $data['requester_email'] ?? null, $data['to_number'])['user'];

            $conversation = SupportConversation::create([
                'requester_user_id' => $requester?->id,
                'requester_name' => $data['requester_name'] ?? $requester?->name,
                'requester_email' => $data['requester_email'] ?? $requester?->email,
                'requester_phone' => $data['to_number'],
                'channel' => 'phone',
                'status' => 'active',
                'assigned_to' => $actor->id,
                'subject' => $data['subject'] ?? 'Appel sortant Support',
                'last_message_at' => now(),
            ]);

            $conversation = $this->entities->link(
                $conversation,
                (string) ($data['subject'] ?? ''),
                $data,
                true,
                $requester,
            );

            $call = SupportCall::create([
                'support_conversation_id' => $conversation->id,
                'requester_user_id' => $conversation->requester_user_id,
                'handled_by' => $actor->id,
                'provider' => config('support_ai.telephony.provider'),
                'direction' => 'outbound',
                'status' => 'waiting',
                'from_number' => config('support_ai.telephony.from_number'),
                'to_number' => $data['to_number'],
                'metadata' => ['subject' => $data['subject'] ?? null],
            ]);

            $result = $this->provider()->initiateOutbound($call, [
                'agent_name' => $actor->name,
                'subject' => $data['subject'] ?? null,
            ]);

            $call->forceFill([
                'provider_call_id' => data_get($result, 'call_id') ?? data_get($result, 'id'),
                'status' => $this->normalizeStatus((string) data_get($result, 'status', 'waiting')),
                'metadata' => array_merge($call->metadata ?? [], ['provider_response' => $result]),
            ])->save();

            $this->audit->log([
                'support_conversation_id' => $conversation->id,
                'support_call_id' => $call->id,
                'actor_user_id' => $actor->id,
                'action' => 'outbound_call_requested',
                'decision' => config('support_ai.telephony.provider'),
                'risk_level' => 'low',
                'input' => ['to' => $call->to_number, 'subject' => $data['subject'] ?? null],
                'output' => ['provider_call_id' => $call->provider_call_id, 'status' => $call->status],
            ]);

            return $call->fresh(['conversation', 'handler', 'requester']);
        }, 3);
    }

    public function handleWebhook(array $payload): array
    {
        $event = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $providerCallId = (string) ($payload['call_id'] ?? data_get($payload, 'call.id') ?? '');

        $call = $providerCallId !== ''
            ? SupportCall::where('provider_call_id', $providerCallId)->first()
            : null;

        if (! $call && in_array($event, ['call.started', 'call.inbound', 'incoming_call'], true)) {
            $call = $this->createInboundCall($payload, $providerCallId, 'webhook');
        }

        if (! $call) {
            return ['accepted' => false, 'message' => 'Appel introuvable.'];
        }

        $this->storeCallEvent($call, $event, $payload, $payload['event_id'] ?? null);

        return match ($event) {
            'call.started', 'call.inbound', 'incoming_call' => $this->markStarted($call),
            'call.ringing' => $this->updateStatus($call, 'ringing'),
            'call.answered' => $this->markAnswered($call),
            'call.transcript', 'transcript.final' => $this->handleTranscript($call, $payload),
            'call.missed' => $this->markMissed($call, $payload),
            'call.transferred' => $this->updateStatus($call, 'transferred'),
            'call.completed', 'call.ended' => $this->markEnded($call, $payload),
            'call.failed' => $this->updateStatus($call, 'failed'),
            default => ['accepted' => true, 'call_reference' => $call->reference],
        };
    }

    public function beginTwilioCall(array $payload, ?string $reference = null): SupportCall
    {
        $sid = (string) ($payload['CallSid'] ?? '');
        $call = $reference ? SupportCall::where('reference', $reference)->first() : null;
        $call ??= $sid !== '' ? SupportCall::where('provider_call_id', $sid)->first() : null;

        if (! $call) {
            $call = $this->createInboundCall([
                'from' => $payload['From'] ?? null,
                'to' => $payload['To'] ?? null,
            ], $sid, 'twilio');
        }

        $call->forceFill([
            'provider' => 'twilio',
            'provider_call_id' => $sid ?: $call->provider_call_id,
            'from_number' => $payload['From'] ?? $call->from_number,
            'to_number' => $payload['To'] ?? $call->to_number,
            'status' => 'in_progress',
            'answered_at' => $call->answered_at ?: now(),
        ])->save();

        $this->storeCallEvent($call, 'twilio.voice', $payload, $payload['SequenceNumber'] ?? null);

        return $call->fresh(['conversation.requester', 'aiAgent']);
    }

    public function handleTwilioSpeech(SupportCall $call, string $speech, array $payload = []): array
    {
        $speech = trim($speech);
        if ($speech === '') {
            return ['reply' => 'Je n’ai pas entendu votre demande. Pouvez-vous la répéter ?', 'requires_human' => false];
        }

        $reply = $this->orchestrator->receiveCustomerMessage(
            $call->conversation,
            $speech,
            $call->requester,
            ['support_call_id' => $call->id, 'provider' => 'twilio'],
        );

        $conversation = $call->conversation->fresh(['ticket', 'handoffs']);
        $call->forceFill([
            'ai_agent_id' => $reply->ai_agent_id,
            'support_ticket_id' => $conversation->support_ticket_id,
            'status' => $conversation->requires_human ? 'waiting_transfer' : 'in_progress',
            'transcript' => trim(($call->transcript ? $call->transcript."\n" : '').'Client : '.$speech."\n".$reply->aiAgent?->name.' : '.$reply->body),
        ])->save();

        $this->storeCallEvent($call, 'twilio.speech', array_merge($payload, ['SpeechResult' => $speech]), $payload['SequenceNumber'] ?? null);

        return [
            'reply' => $reply->body,
            'voice_name' => $reply->aiAgent?->voice_name,
            'requires_human' => (bool) $conversation->requires_human,
            'ticket_reference' => $conversation->ticket?->reference,
        ];
    }

    public function handleTwilioStatus(array $payload): ?SupportCall
    {
        $sid = (string) ($payload['CallSid'] ?? '');
        if ($sid === '') {
            return null;
        }

        $call = SupportCall::where('provider_call_id', $sid)->first();
        if (! $call) {
            return null;
        }

        $twilioStatus = (string) ($payload['CallStatus'] ?? '');
        $status = $this->normalizeStatus($twilioStatus);
        $this->storeCallEvent($call, 'twilio.status.'.$twilioStatus, $payload, $payload['SequenceNumber'] ?? null);

        if (in_array($twilioStatus, ['busy', 'no-answer', 'failed', 'canceled'], true)) {
            $this->markMissed($call, ['reason' => 'Twilio : '.$twilioStatus]);
        } elseif ($twilioStatus === 'completed') {
            $this->markEnded($call, ['duration_seconds' => (int) ($payload['CallDuration'] ?? 0)]);
        } else {
            $call->forceFill([
                'status' => $status,
                'answered_at' => $twilioStatus === 'in-progress' ? ($call->answered_at ?: now()) : $call->answered_at,
            ])->save();
        }

        return $call->fresh();
    }

    public function transfer(SupportCall $call, string $destination, User $actor): array
    {
        $result = $this->provider()->transfer($call, $destination, ['requested_by' => $actor->id]);
        $call->forceFill([
            'status' => 'transferred',
            'transfer_target' => $destination,
            'handled_by' => $actor->id,
        ])->save();

        $this->audit->log([
            'support_conversation_id' => $call->support_conversation_id,
            'support_call_id' => $call->id,
            'actor_user_id' => $actor->id,
            'action' => 'call_transferred',
            'decision' => 'human_transfer',
            'risk_level' => 'medium',
            'input' => ['destination' => $destination],
            'output' => ['status' => $call->status],
        ]);

        return $result;
    }

    private function createInboundCall(array $payload, string $providerCallId, string $provider): SupportCall
    {
        return DB::transaction(function () use ($payload, $providerCallId, $provider) {
            $from = $payload['from'] ?? data_get($payload, 'call.from');
            $to = $payload['to'] ?? data_get($payload, 'call.to');
            $match = $this->identities->resolve(null, null, $from);
            $user = $match['user'];
            $defaultAgent = SupportAiAgent::active()->where('is_default', true)->first();

            $conversation = SupportConversation::create([
                'requester_user_id' => $user?->id,
                'requester_name' => $user?->name,
                'requester_email' => $user?->email,
                'requester_phone' => $from,
                'requester_match_method' => $match['method'],
                'requester_matched_at' => $user ? now() : null,
                'channel' => 'phone',
                'status' => 'active',
                'ai_agent_id' => $defaultAgent?->id,
                'subject' => 'Appel entrant Support',
                'last_message_at' => now(),
            ]);

            $conversation = $this->entities->link($conversation, 'appel entrant support', [], false, $user);

            $call = SupportCall::create([
                'support_conversation_id' => $conversation->id,
                'requester_user_id' => $user?->id,
                'ai_agent_id' => $defaultAgent?->id,
                'provider' => $provider,
                'provider_call_id' => $providerCallId ?: null,
                'direction' => 'inbound',
                'status' => 'waiting',
                'from_number' => $from,
                'to_number' => $to,
                'started_at' => now(),
                'metadata' => ['initial_payload' => $payload, 'identity_match' => $match['method']],
            ]);

            $this->audit->log([
                'support_conversation_id' => $conversation->id,
                'support_call_id' => $call->id,
                'ai_agent_id' => $defaultAgent?->id,
                'actor_user_id' => $user?->id,
                'action' => 'inbound_call_received',
                'decision' => $user ? 'account_matched' : 'account_unmatched',
                'risk_level' => 'low',
                'input' => ['from' => $from, 'to' => $to, 'provider' => $provider],
                'output' => ['requester_user_id' => $user?->id],
            ]);

            return $call;
        }, 3);
    }

    private function handleTranscript(SupportCall $call, array $payload): array
    {
        $text = trim((string) ($payload['text'] ?? data_get($payload, 'transcript.text') ?? ''));
        if ($text === '') {
            return ['accepted' => true, 'call_reference' => $call->reference, 'reply' => null];
        }

        return array_merge(['accepted' => true, 'call_reference' => $call->reference], $this->handleTwilioSpeech($call, $text, $payload));
    }

    private function markMissed(SupportCall $call, array $payload): array
    {
        if ($call->status === 'missed') {
            return ['accepted' => true, 'call_reference' => $call->reference, 'callback_created' => false];
        }

        $call->forceFill([
            'status' => 'missed',
            'ended_at' => now(),
            'missed_reason' => $payload['reason'] ?? 'Appel non décroché',
        ])->save();

        $callback = SupportCallbackRequest::firstOrCreate(
            ['support_call_id' => $call->id, 'status' => 'pending'],
            [
                'support_conversation_id' => $call->support_conversation_id,
                'support_ticket_id' => $call->support_ticket_id,
                'requester_user_id' => $call->requester_user_id,
                'requester_name' => $call->requester?->name,
                'phone' => $call->direction === 'inbound' ? $call->from_number : $call->to_number,
                'email' => $call->requester?->email,
                'reason' => $call->missed_reason,
            ],
        );

        $this->audit->log([
            'support_conversation_id' => $call->support_conversation_id,
            'support_call_id' => $call->id,
            'action' => 'call_missed',
            'decision' => 'callback_created',
            'risk_level' => 'medium',
            'output' => ['callback_id' => $callback->id, 'callback_reference' => $callback->reference],
        ]);

        return ['accepted' => true, 'call_reference' => $call->reference, 'callback_created' => true];
    }

    private function markEnded(SupportCall $call, array $payload): array
    {
        $endedAt = now();
        $startedAt = $call->answered_at ?: $call->started_at;
        $duration = (int) ($payload['duration_seconds'] ?? ($startedAt ? $startedAt->diffInSeconds($endedAt) : 0));

        $call->forceFill([
            'status' => 'completed',
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'recording_url' => $payload['recording_url'] ?? $call->recording_url,
            'summary' => $payload['summary'] ?? $call->conversation?->summary,
        ])->save();

        $this->audit->log([
            'support_conversation_id' => $call->support_conversation_id,
            'support_call_id' => $call->id,
            'action' => 'call_completed',
            'decision' => 'completed',
            'risk_level' => 'low',
            'output' => ['duration_seconds' => $duration],
        ]);

        return ['accepted' => true, 'call_reference' => $call->reference];
    }

    private function markStarted(SupportCall $call): array
    {
        $call->forceFill(['status' => 'waiting', 'started_at' => $call->started_at ?: now()])->save();
        return [
            'accepted' => true,
            'call_reference' => $call->reference,
            'greeting' => 'Bonjour et bienvenue chez OVANIE. Je suis Miss N’Nan, votre assistante Support.',
        ];
    }

    private function markAnswered(SupportCall $call): array
    {
        $call->forceFill(['status' => 'in_progress', 'answered_at' => now()])->save();
        return ['accepted' => true, 'call_reference' => $call->reference];
    }

    private function updateStatus(SupportCall $call, string $status): array
    {
        $call->forceFill(['status' => $status])->save();
        return ['accepted' => true, 'call_reference' => $call->reference];
    }

    private function storeCallEvent(SupportCall $call, string $event, array $payload, mixed $providerEventId = null): void
    {
        $call->events()->create([
            'event' => $event,
            'provider_event_id' => $providerEventId !== null ? (string) $providerEventId : null,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function provider(): SupportTelephonyProvider
    {
        return match ((string) config('support_ai.telephony.provider')) {
            'twilio' => app(TwilioSupportTelephonyProvider::class),
            'webhook' => app(WebhookSupportTelephonyProvider::class),
            default => throw new RuntimeException('Aucun fournisseur téléphonique réel n’est configuré.'),
        };
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'queued', 'initiated' => 'waiting',
            'in-progress', 'answered' => 'in_progress',
            'no-answer', 'busy' => 'missed',
            'canceled' => 'cancelled',
            default => str_replace('-', '_', $status ?: 'waiting'),
        };
    }
}
