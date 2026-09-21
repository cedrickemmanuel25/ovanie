<?php

namespace App\Services\SupportAi;

use App\Models\SupportConversation;
use App\Services\SupportAi\Providers\ClaudeSupportAiProvider;

/**
 * @deprecated Le flux principal passe désormais par SupportAiOrchestrator.
 * Cette classe reste comme adaptateur de compatibilité et n'utilise plus les tools Anthropic.
 */
class ClaudeSupportConversationService
{
    public function __construct(
        private readonly SupportConversationMemoryService $memory,
        private readonly SupportContextResolver $contextResolver,
        private readonly SupportContextAuthorizationService $authorizer,
        private readonly ClaudeSupportDecisionService $decisionService,
        private readonly SupportAgentRouter $router,
        private readonly ClaudeSupportAiProvider $claude,
    ) {}

    /** @return array<string,mixed> */
    public function respond(
        SupportConversation $conversation,
        string $message,
        ?int $currentMessageId = null,
    ): array {
        $conversation->loadMissing('requester');

        $currentMetadata = [];
        if ($currentMessageId) {
            $currentMessage = $conversation->messages()->whereKey($currentMessageId)->first();
            $currentMetadata = (array) ($currentMessage?->metadata ?? []);
        }

        $memory = $this->memory->build($conversation, $currentMessageId, $currentMetadata);
        $rawContext = $this->contextResolver->build($conversation, $message, $memory);
        $context = $this->authorizer->authorize($conversation, $rawContext);
        $decision = $this->decisionService->decide($conversation, $message, $context);
        $agent = $this->router->selectByRole((string) ($decision['agent_role'] ?? 'general'));

        $context['decision'] = [
            'intent' => $decision['intent'] ?? 'general',
            'agent_role' => $agent->role_key,
            'priority' => $decision['priority'] ?? 'normal',
            'topic_key' => $decision['topic_key'] ?? 'general',
        ];

        $result = $this->claude->generate($conversation, $agent, $message, $context);
        $action = (string) data_get($result, 'metadata.action', 'answer');
        $handoffTarget = trim((string) data_get($result, 'metadata.handoff_target', ''));

        if ($action === 'handoff' && $handoffTarget !== '') {
            $nextAgent = $this->router->selectHandoffTarget($handoffTarget, $agent);
            if ($nextAgent) {
                $context['handoff'] = [
                    'from_role' => $agent->role_key,
                    'to_role' => $nextAgent->role_key,
                    'reason' => data_get($result, 'metadata.handoff_reason'),
                ];
                $agent = $nextAgent;
                $context['decision']['agent_role'] = $agent->role_key;
                $result = $this->claude->generate($conversation, $agent, $message, $context);
                $action = (string) data_get($result, 'metadata.action', 'answer');
            }
        }

        return [
            'body' => (string) $result['body'],
            'agent_role' => $agent->role_key,
            'priority' => (string) ($decision['priority'] ?? 'normal'),
            'action' => $action,
            'confidence' => (float) ($result['confidence'] ?? 0),
            'provider' => (string) ($result['provider'] ?? 'anthropic'),
            'metadata' => (array) ($result['metadata'] ?? []),
        ];
    }
}
