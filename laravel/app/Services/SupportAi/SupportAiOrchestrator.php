<?php

namespace App\Services\SupportAi;

use App\Jobs\SendWhatsAppSupportMessage;
use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\User;
use App\Services\SupportAi\Providers\ClaudeSupportAiProvider;
use Illuminate\Support\Facades\DB;
use Throwable;

class SupportAiOrchestrator
{
    public function __construct(
        private readonly SupportIdentityResolver $identityResolver,
        private readonly SupportConversationMemoryService $memory,
        private readonly SupportContextResolver $contextResolver,
        private readonly SupportContextAuthorizationService $authorizer,
        private readonly SupportAgentRouter $router,
        private readonly ClaudeSupportAiProvider $claude,
        private readonly SupportTicketFactory $ticketFactory,
        private readonly SupportHandoffQueueService $handoffQueue,
        private readonly SupportAiAuditLogger $audit,
    ) {}

    /**
     * Pipeline production V3 :
     * Laravel identifie -> rassemble -> autorise -> UN appel Claude normal.
     * Un appel supplémentaire n'est autorisé que si Claude demande réellement
     * un rechargement de données ou un relais vers une autre agente.
     */
    public function receiveCustomerMessage(
        SupportConversation $conversation,
        string $body,
        ?User $requester = null,
        array $metadata = [],
    ): SupportConversationMessage {
        $body = trim($body) !== '' ? trim($body) : '[Message vide]';

        [$conversation, $customerMessage] = DB::transaction(function () use (
            $conversation,
            $body,
            $requester,
            $metadata,
        ) {
            $conversation = SupportConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            // Laravel établit l'identité fiable. Le modèle ne fait jamais cette association.
            // Un identifiant explicitement saisi dans le message prime sur le numéro
            // WhatsApp technique du webhook. Sinon chaque tentative revérifie toujours
            // le numéro expéditeur et ignore le téléphone de compte fourni par le client.
            $conversation = $this->identityResolver->applyToConversation(
                $conversation,
                $requester,
                $this->extractEmail($body) ?? ($metadata['requester_email'] ?? null) ?? $conversation->requester_email,
                $this->extractPhoneHint($body) ?? ($metadata['requester_phone'] ?? null) ?? $conversation->requester_phone,
            );

            $providerMessageId = trim((string) ($metadata['provider_message_id'] ?? ''));

            if ($providerMessageId !== '') {
                $customerMessage = $conversation->messages()->firstOrCreate(
                    [
                        'provider_message_id' => $providerMessageId,
                        'sender_type' => 'customer',
                    ],
                    [
                        'sender_user_id' => $conversation->requester_user_id ?: $requester?->id,
                        'body' => $body,
                        'provider' => $conversation->channel,
                        'metadata' => $metadata,
                    ],
                );
            } else {
                $customerMessage = $conversation->messages()->create([
                    'sender_type' => 'customer',
                    'sender_user_id' => $conversation->requester_user_id ?: $requester?->id,
                    'body' => $body,
                    'provider' => $conversation->channel,
                    'metadata' => $metadata,
                ]);
            }

            $conversation->forceFill(['last_message_at' => now()])->save();

            return [$conversation->fresh('requester'), $customerMessage];
        }, 3);

        // La mémoire doit être construite AVANT de décider s'il s'agit d'un accueil.
        // Sans cela, une réponse courte au milieu d'un échange peut être prise à tort
        // pour le début d'une nouvelle conversation.
        $memory = $this->memory->build(
            $conversation,
            $customerMessage->id,
            (array) ($customerMessage->metadata ?? []),
        );

        $hasPriorVisibleReply = $conversation->messages()
            ->where('id', '<', $customerMessage->id)
            ->where('is_internal', false)
            ->whereIn('sender_type', ['ai', 'human'])
            ->exists();

        // La présentation complète est strictement réservée au premier échange.
        if ($this->shouldUseDeterministicWelcome($body, $hasPriorVisibleReply)) {
            return $this->createDeterministicWelcome(
                $conversation,
                $customerMessage,
                $body,
                $requester,
            );
        }

        $rawContext = $this->contextResolver->build($conversation, $body, $memory);
        $authorizedContext = $this->authorizer->authorize($conversation, $rawContext);

        if ((bool) ($memory['segment_reset'] ?? false)) {
            $authorizedContext = $this->sanitizeNewSegmentContext($authorizedContext);
        }

        // Les indicateurs de relation doivent être restaurés APRÈS le nettoyage du
        // contexte, sinon une nouvelle demande perd son marqueur avant l'appel Claude.
        $authorizedContext = $this->restoreConversationFlags(
            $authorizedContext,
            $memory,
            $hasPriorVisibleReply,
        );

        // Un nouveau segment commence avec l'agente générale. Une continuation reprend
        // directement avec l'agente déjà active afin de ne pas répéter le même relais.
        $agent = $this->router->selectByRole($this->initialAgentRole($memory));
        $visitedRoles = [$agent->role_key];
        $handoffTrail = [];
        $turnState = [
            'intent' => 'general',
            'agent_role' => $agent->role_key,
            'priority' => 'normal',
            'topic_key' => 'general',
            // Tout message qui n'est pas une continuation prouvée ouvre un nouveau segment.
            'new_topic' => ! (bool) ($memory['continuation'] ?? false),
            'facts' => (array) data_get($authorizedContext, 'conversation.memory.structured.collected_facts', []),
            'missing_fields' => (array) data_get($authorizedContext, 'conversation.memory.structured.missing_fields', []),
        ];

        $authorizedContext['current_turn'] = $turnState;

        $aiCallCount = 0;
        $reloadUsed = false;
        $maxAiCalls = 4; // normal=1 ; les appels suivants sont uniquement reload/handoff réels.
        $result = null;

        try {
            while ($aiCallCount < $maxAiCalls) {
                $aiCallCount++;
                $result = $this->claude->generate(
                    $conversation,
                    $agent,
                    $body,
                    $authorizedContext,
                );

                $turnState = $this->mergeTurnState($turnState, $result, $agent);
                $authorizedContext['current_turn'] = $turnState;

                $action = (string) data_get($result, 'metadata.action', 'answer');

                if ($action === 'reload') {
                    $scopes = $this->requestedScopes($result);

                    if ($reloadUsed || $scopes === []) {
                        $result = $this->safeClarification(
                            'Je dispose des informations accessibles pour cette demande. Pouvez-vous préciser uniquement le point exact que vous souhaitez vérifier ?',
                            'Reload déjà utilisé ou sans domaine valide.',
                        );
                        $turnState = $this->mergeTurnState($turnState, $result, $agent);
                        break;
                    }

                    $reloadUsed = true;

                    // Les faits extraits par le premier appel (ex. produit=ciment, poids=50 kg)
                    // sont utilisés uniquement pour interroger le backend au reload ; ils ne
                    // ressuscitent jamais un ancien historique.
                    $reloadMemory = $memory;
                    data_set(
                        $reloadMemory,
                        'structured.collected_facts',
                        array_merge(
                            (array) data_get($memory, 'structured.collected_facts', []),
                            (array) ($turnState['facts'] ?? []),
                        ),
                    );
                    data_set($reloadMemory, 'structured.active_intent', $turnState['intent'] ?? 'general');

                    $rawContext = $this->contextResolver->build(
                        $conversation,
                        $body,
                        $reloadMemory,
                        $scopes,
                    );
                    $authorizedContext = $this->authorizer->authorize($conversation, $rawContext);
                    $authorizedContext = $this->restoreConversationFlags($authorizedContext, $memory);
                    $authorizedContext['current_turn'] = $turnState;

                    continue;
                }

                if ($action === 'handoff') {
                    $target = trim((string) data_get($result, 'metadata.handoff_target', ''));
                    $nextAgent = $target !== ''
                        ? $this->router->selectHandoffTarget($target, $agent)
                        : null;

                    if (! $nextAgent || in_array($nextAgent->role_key, $visitedRoles, true)) {
                        $result = $this->safeClarification(
                            'Je garde votre demande en cours sans vous faire recommencer. Quel est le point précis qui reste à résoudre ?',
                            'Relais invalide ou boucle de relais évitée.',
                        );
                        $turnState = $this->mergeTurnState($turnState, $result, $agent);
                        break;
                    }

                    $handoff = [
                        'from_agent' => $agent->name,
                        'from_role' => $agent->role_key,
                        'to_agent' => $nextAgent->name,
                        'to_role' => $nextAgent->role_key,
                        'reason' => data_get($result, 'metadata.handoff_reason'),
                        'summary' => data_get($result, 'metadata.handoff_summary'),
                        'current_message' => $body,
                        'collected_facts' => (array) ($turnState['facts'] ?? []),
                        'missing_fields' => (array) ($turnState['missing_fields'] ?? []),
                    ];

                    $handoffTrail[] = $handoff;
                    $turnState['handoff'] = $handoff;
                    $turnState['agent_role'] = $nextAgent->role_key;
                    $authorizedContext['handoff'] = $handoff;
                    $authorizedContext['current_turn'] = $turnState;

                    $agent = $nextAgent;
                    $visitedRoles[] = $agent->role_key;
                    continue;
                }

                // answer / clarify / human : réponse finale disponible.
                break;
            }
        } catch (Throwable $exception) {
            report($exception);
            $result = $this->safeFallback($exception->getMessage(), $conversation, $body, $authorizedContext);
            $turnState = $this->mergeTurnState($turnState, $result, $agent);
        }

        if (! is_array($result)) {
            $result = $this->safeFallback('Aucune réponse produite.', $conversation, $body, $authorizedContext);
            $turnState = $this->mergeTurnState($turnState, $result, $agent);
        }

        $action = (string) data_get($result, 'metadata.action', 'answer');
        if (! in_array($action, ['answer', 'clarify', 'human'], true)) {
            $result = $this->safeClarification(
                'Je n’ai pas encore assez d’éléments fiables pour finaliser cette demande. Pouvez-vous préciser le point que vous souhaitez résoudre ?',
                'Nombre maximal d’appels IA atteint avant réponse finale.',
            );
            $action = 'clarify';
            $turnState = $this->mergeTurnState($turnState, $result, $agent);
        }

        $priority = (string) ($turnState['priority'] ?? 'normal');
        if (! in_array($priority, ['normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $needsHuman = $action === 'human' && $agent->role_key === 'escalation';
        $finalBody = trim((string) ($result['body'] ?? ''));

        if ($finalBody === '') {
            $result = $this->safeFallback('Réponse finale vide.', $conversation, $body, $authorizedContext);
            $finalBody = $result['body'];
            $action = 'clarify';
            $needsHuman = false;
        }

        $finalBody = $this->enforceSellerChannelDisclosure($finalBody, $body, $authorizedContext);
        $finalBody = $this->enforceVerifiedStateClaims($finalBody, $authorizedContext);
        $finalBody = $this->enforceCatalogConsistency($finalBody, $body, $authorizedContext);
        $finalBody = $this->resumeBlockingPendingQuestion($finalBody, $memory);

        // Protection serveur : même si le modèle recommence par une formule d'accueil,
        // une conversation déjà engagée ne reçoit jamais une nouvelle présentation.
        if ($hasPriorVisibleReply) {
            $finalBody = $this->removeRepeatedOpening($finalBody);
        }

        // Demande utilisateur : le relais entre agentes IA reste strictement
        // interne. Chaque agente spécialisée (Rita, Technique, Logistique,
        // Salomé) continue de répondre en coulisses selon le sujet, pour
        // garder la qualité des réponses par domaine - mais le client ne
        // voit jamais ni changement de nom, ni phrase de transition ("je
        // suis Rita", "X m'a transmis votre demande") : il a l'impression de
        // parler à N'Nan du début à la fin. Voir aussi maskSpecialistIdentity()
        // ci-dessous, qui neutralise toute auto-présentation spontanée de
        // Claude sous un autre nom que N'Nan.
        $finalBody = $this->maskSpecialistIdentity($finalBody);

        if ($needsHuman) {
            $finalBody = $this->appendHumanEscalationMessage($finalBody);
        }

        [$reply, $humanTakenOver] = DB::transaction(function () use (
            $conversation,
            $customerMessage,
            $agent,
            $result,
            $finalBody,
            $priority,
            $needsHuman,
            $action,
            $handoffTrail,
            $turnState,
            $aiCallCount,
            $reloadUsed,
        ) {
            $lockedConversation = SupportConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $humanTakenOver = (bool) $lockedConversation->assigned_to
                || $lockedConversation->status === 'human';

            $reply = $lockedConversation->messages()->create([
                'sender_type' => 'ai',
                'ai_agent_id' => $agent->id,
                'body' => $finalBody,
                'is_internal' => $humanTakenOver,
                'confidence' => (float) ($result['confidence'] ?? 0),
                'provider' => (string) ($result['provider'] ?? 'anthropic'),
                'metadata' => array_merge((array) ($result['metadata'] ?? []), [
                    'action' => $action,
                    'final_agent_role' => $agent->role_key,
                    'ai_call_count' => $aiCallCount,
                    'reload_used' => $reloadUsed,
                    'handoff_trail' => $handoffTrail,
                    'turn_state' => $turnState,
                    'suppressed_after_human_takeover' => $humanTakenOver,
                ]),
            ]);

            $lockedConversation->forceFill([
                'ai_agent_id' => $agent->id,
                'priority' => $priority,
                'ai_confidence' => (float) ($result['confidence'] ?? 0),
                'status' => $humanTakenOver
                    ? 'human'
                    : ($needsHuman ? 'waiting_human' : 'active'),
                'requires_human' => $humanTakenOver
                    ? (bool) $lockedConversation->requires_human
                    : $needsHuman,
                'last_message_at' => now(),
            ])->save();

            return [$reply, $humanTakenOver];
        }, 3);

        if (! $humanTakenOver) {
            $memoryState = $turnState;
            $memoryState['agent_role'] = $agent->role_key;
            $memoryState['_reply_to_pending_question'] = (bool) ($memory['reply_to_pending_question'] ?? false);
            $memoryState['_resumed_segment'] = $memory['resumed_segment'] ?? null;
            $memoryState['_suspended_segments'] = (array) ($memory['suspended_segments'] ?? []);
            if ($handoffTrail !== []) {
                $memoryState['handoff'] = end($handoffTrail);
            }

            $this->memory->updateAfterTurn(
                $conversation->fresh(),
                $body,
                $finalBody,
                $memoryState,
                $action,
            );
        }

        if ($needsHuman && ! $humanTakenOver) {
            $this->createHumanHandoff(
                $conversation->fresh(['aiAgent', 'ticket']),
                $body,
                $priority,
            );
        }

        $this->audit->log([
            'support_conversation_id' => $conversation->id,
            'ai_agent_id' => $agent->id,
            'actor_user_id' => $conversation->requester_user_id ?: $requester?->id,
            'action' => 'ai_reply',
            'decision' => $humanTakenOver
                ? 'suppressed_after_human_takeover'
                : ($needsHuman ? 'support_handoff_required' : $action),
            'risk_level' => $agent->role_key === 'escalation' ? 'high' : 'low',
            'confidence' => (float) ($result['confidence'] ?? 0),
            'input' => [
                'message_id' => $customerMessage->id,
                'body' => $body,
            ],
            'output' => [
                'message_id' => $reply->id,
                'body' => $reply->body,
                'agent_role' => $agent->role_key,
                'action' => $action,
                'ai_call_count' => $aiCallCount,
                'reload_used' => $reloadUsed,
                'handoff_trail' => $handoffTrail,
            ],
            'context' => [
                'pipeline' => [
                    'laravel_identified' => true,
                    'laravel_gathered' => true,
                    'laravel_authorized' => true,
                    'single_claude_first_pass' => true,
                    'claude_answered' => ($result['provider'] ?? null) === 'anthropic',
                ],
                'data_status' => $authorizedContext['data_status'] ?? [],
                'provider' => $result['provider'] ?? null,
                'ai_call_count' => $aiCallCount,
                'reload_used' => $reloadUsed,
            ],
        ]);

        return $reply->load('aiAgent');
    }

    /** Reprend l'agente du segment actif sans simuler un nouveau relais à chaque tour. */
    private function initialAgentRole(array $memory): string
    {
        if (! (bool) ($memory['continuation'] ?? false)) {
            return 'general';
        }

        $role = trim((string) data_get($memory, 'structured.agent_role', 'general'));

        return in_array($role, ['general', 'business', 'technical', 'logistics', 'escalation'], true)
            ? $role
            : 'general';
    }

    /**
     * Accueil OVANIE volontairement déterministe.
     *
     * Cette méthode ne cherche PAS à comprendre toutes les formulations humaines.
     * Elle intercepte seulement les messages manifestement introductifs et sans
     * besoin métier précis. Dès qu'un indice métier existe, Claude reçoit le message.
     */
    private function shouldUseDeterministicWelcome(string $body, bool $hasPriorVisibleReply): bool
    {
        if ($hasPriorVisibleReply) {
            return false;
        }

        $normalized = $this->normalizeForWelcome($body);

        if ($normalized === '') {
            return true;
        }

        // IMPORTANT : le message ACTUEL prime toujours sur une ancienne session.
        // Une salutation / demande vague sans besoin métier explicite doit réinitialiser
        // l'ancien segment, même si celui-ci date de quelques minutes. Cela empêche
        // qu'un ancien parcours (boutique, litige, commande, etc.) pollue un nouvel accueil.
        // En revanche, si le message contient déjà un besoin métier précis, il passe à Claude.

        // Ne jamais intercepter une demande qui contient déjà un besoin métier identifiable.
        // Les préfixes permettent de couvrir naturellement les variantes (livrer/livraison,
        // rembourser/remboursement, connecter/connexion, etc.) sans écrire des réponses Q/R.
        $actionablePatterns = [
            'compte', 'inscri', 'connect', 'mot de passe', 'code',
            'boutique', 'vendeur', 'produit', 'catalog', 'ciment', 'materiau',
            'commande', 'achat', 'acheter', 'panier', 'prix', 'stock', 'disponib',
            'paiement', 'wave', 'orange money', 'mtn', 'moov', 'carte bancaire',
            'livraison', 'livrer', 'colis', 'chauffeur', 'adresse', 'gps', 'logistique',
            'devis', 'proforma', 'appel d offre', 'business', 'commercial',
            'litige', 'plainte', 'reclamation', 'contest', 'fraude', 'arnaque',
            'retour', 'rembours', 'annul', 'facture', 'recu',
            'bug', 'erreur', 'probleme', 'bloqu', 'technique',
            'ne fonctionne', 'fonctionne pas', 'ne marche', 'marche pas',
            'responsable', 'superviseur', 'support humain',
        ];

        foreach ($actionablePatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return false;
            }
        }

        // Cas purement introductifs : salutations, "j'ai besoin d'aide", "je suis nouveau",
        // "comment ca va", etc. Le message d'accueil est alors stable et gratuit côté Anthropic.
        $introPatterns = [
            'bonjour', 'bonsoir', 'salut', 'hello', 'coucou', 'cc',
            'comment allez vous', 'comment ca va', 'ca va',
            'j ai besoin d aide', 'besoin d aide', 'pouvez vous m aider', 'aidez moi',
            'j ai besoin d assistance', 'besoin d assistance', 'assistance',
            'je suis nouveau', 'je suis nouvelle', 'nouveau sur la plateforme',
            'nouvelle sur la plateforme', 'je decouvre ovanie', 'premiere fois sur ovanie',
        ];

        foreach ($introPatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        // Un message très court sans aucun indice métier reste une demande vague.
        // Au-delà, on laisse Claude analyser librement afin de ne pas bloquer une formulation inhabituelle.
        $wordCount = preg_match_all('/\\b[\\pL\\pN]+\\b/u', $normalized, $matches);

        return is_int($wordCount) && $wordCount > 0 && $wordCount <= 3;
    }

    private function normalizeForWelcome(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y', 'œ' => 'oe',
            '’' => "'", '‘' => "'",
        ]);
        $value = str_replace("'", ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\\s+/', ' ', $value) ?? $value);
    }

    private function createDeterministicWelcome(
        SupportConversation $conversation,
        SupportConversationMessage $customerMessage,
        string $customerBody,
        ?User $requester,
    ): SupportConversationMessage {
        $agent = $this->router->selectByRole('general');
        $welcome = 'Bienvenue sur le Support OVANIE 👋 Je suis N’Nan. Dites-moi simplement ce dont vous avez besoin, et je vous aiderai.';

        $turnState = [
            'intent' => 'welcome',
            'agent_role' => 'general',
            'priority' => 'normal',
            'topic_key' => 'welcome',
            'new_topic' => true,
            'facts' => [],
            'missing_fields' => [],
        ];

        $reply = DB::transaction(function () use ($conversation, $customerMessage, $agent, $welcome, $turnState) {
            $lockedConversation = SupportConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $humanTakenOver = (bool) $lockedConversation->assigned_to
                || $lockedConversation->status === 'human';

            $reply = $lockedConversation->messages()->create([
                'sender_type' => 'ai',
                'ai_agent_id' => $agent->id,
                'body' => $welcome,
                'is_internal' => $humanTakenOver,
                'confidence' => 1.0,
                'provider' => 'laravel_deterministic_welcome',
                'metadata' => [
                    'action' => 'answer',
                    'intent' => 'welcome',
                    'agent_role' => 'general',
                    'priority' => 'normal',
                    'topic_key' => 'welcome',
                    'new_topic' => true,
                    'facts' => [],
                    'missing_fields' => [],
                    'final_agent_role' => 'general',
                    'ai_call_count' => 0,
                    'reload_used' => false,
                    'handoff_trail' => [],
                    'turn_state' => $turnState,
                    'deterministic_welcome' => true,
                    'suppressed_after_human_takeover' => $humanTakenOver,
                ],
            ]);

            $lockedConversation->forceFill([
                'ai_agent_id' => $agent->id,
                'priority' => 'normal',
                'ai_confidence' => 1.0,
                'status' => $humanTakenOver ? 'human' : 'active',
                'requires_human' => $humanTakenOver
                    ? (bool) $lockedConversation->requires_human
                    : false,
                'last_message_at' => now(),
            ])->save();

            return $reply;
        }, 3);

        // Réinitialise la mémoire active : un accueil vague ne doit jamais ressusciter
        // une ancienne commande, un ancien litige ou un ancien devis au tour suivant.
        $this->memory->updateAfterTurn(
            $conversation->fresh(),
            $customerBody,
            $welcome,
            $turnState,
            'answer',
        );

        $this->audit->log([
            'support_conversation_id' => $conversation->id,
            'ai_agent_id' => $agent->id,
            'actor_user_id' => $conversation->requester_user_id ?: $requester?->id,
            'action' => 'deterministic_welcome',
            'decision' => 'laravel_welcome_without_anthropic',
            'risk_level' => 'low',
            'confidence' => 1.0,
            'input' => [
                'message_id' => $customerMessage->id,
                'body' => $customerBody,
            ],
            'output' => [
                'message_id' => $reply->id,
                'body' => $welcome,
                'agent_role' => 'general',
                'action' => 'answer',
                'ai_call_count' => 0,
            ],
            'context' => [
                'pipeline' => [
                    'laravel_identified' => true,
                    'deterministic_welcome' => true,
                    'anthropic_called' => false,
                ],
                'provider' => 'laravel_deterministic_welcome',
                'ai_call_count' => 0,
            ],
        ]);

        return $reply->load('aiAgent');
    }

    public function sendHumanMessage(
        SupportConversation $conversation,
        User $agent,
        string $body,
        bool $internal = false,
    ): SupportConversationMessage {
        return DB::transaction(function () use ($conversation, $agent, $body, $internal) {
            $message = $conversation->messages()->create([
                'sender_type' => 'human',
                'sender_user_id' => $agent->id,
                'body' => trim($body),
                'is_internal' => $internal,
                'provider' => 'support_console',
            ]);

            $conversation->forceFill([
                'assigned_to' => $agent->id,
                'status' => 'human',
                'last_message_at' => now(),
            ])->save();

            if (! $internal && $conversation->channel === 'whatsapp') {
                SendWhatsAppSupportMessage::dispatch($message->id)->afterCommit();
            }

            return $message;
        }, 3);
    }

    /** @return array<string,mixed> */
    private function restoreConversationFlags(
        array $context,
        array $memory,
        bool $hasPriorVisibleReply,
    ): array
    {
        $context['conversation'] = (array) ($context['conversation'] ?? []);
        $context['conversation']['continuation'] = (bool) ($memory['continuation'] ?? false);
        $context['conversation']['segment_reset'] = (bool) ($memory['segment_reset'] ?? false);
        $context['conversation']['reply_to_pending_question'] = (bool) ($memory['reply_to_pending_question'] ?? false);
        $context['conversation']['new_support_request'] = (bool) ($memory['new_support_request'] ?? false);
        $context['conversation']['relation'] = (string) ($memory['relation'] ?? 'new_topic');
        $context['conversation']['has_started'] = $hasPriorVisibleReply;
        $context['conversation']['greeting_already_sent'] = $hasPriorVisibleReply;

        return $context;
    }

    /** Retire uniquement les formules d'ouverture répétées, jamais le contenu métier. */
    private function removeRepeatedOpening(string $body): string
    {
        $original = trim($body);
        $standardWelcome = 'Bienvenue sur le Support OVANIE 👋 Je suis N’Nan. Dites-moi simplement ce dont vous avez besoin, et je vous aiderai.';
        $clean = str_starts_with($original, $standardWelcome)
            ? trim(mb_substr($original, mb_strlen($standardWelcome)))
            : $original;

        $clean = preg_replace(
            '/^\s*(?:(?:bonjour|bonsoir|salut)(?:\s+[^\pL\pN\r\n]{1,8})?[\s,;:!.-]*)/iu',
            '',
            $original,
            1,
        ) ?? $original;

        $clean = preg_replace(
            '/^\s*(?:bienvenue\s+sur\s+(?:le\s+support\s+)?ovanie[^.?!]*[.?!]\s*)?/iu',
            '',
            $clean,
            1,
        ) ?? $clean;

        $clean = preg_replace(
            '/^\s*je\s+suis\s+(?:miss\s+)?n[’\'`]nan\s*[,.!]?[\s]*/iu',
            '',
            $clean,
            1,
        ) ?? $clean;

        $clean = preg_replace(
            '/^\s*dites-moi\s+simplement\s+ce\s+dont\s+vous\s+avez\s+besoin,?\s+et\s+je\s+vous\s+aiderai[.!]?\s*/iu',
            '',
            $clean,
            1,
        ) ?? $clean;

        $clean = trim($clean);

        return $clean !== ''
            ? $clean
            : 'Je vous écoute. Quel point souhaitez-vous poursuivre ?';
    }

    /**
     * Neutralise toute auto-présentation de l'agente spécialisée réellement
     * active (Rita, Assistante Technique/Logistique OVANIE, Salomé) : le
     * client ne doit jamais apprendre qu'une autre IA que N'Nan a traité sa
     * demande. Les relais restent entièrement internes (voir
     * receiveCustomerMessage) ; cette méthode est un filet de sécurité pour
     * le cas où le modèle se nommerait spontanément dans sa réponse.
     */
    private function maskSpecialistIdentity(string $body): string
    {
        $otherAgentNames = [
            'Miss Rita',
            'Assistante Technique OVANIE',
            'Assistante Logistique OVANIE',
            'Miss Salomé',
        ];

        foreach ($otherAgentNames as $name) {
            $body = preg_replace(
                '/\bje\s+suis\s+'.preg_quote($name, '/').'\b/iu',
                'Je suis N’Nan',
                $body,
            ) ?? $body;
        }

        return $body;
    }

    /** @return array<string,mixed> */
    private function sanitizeNewSegmentContext(array $context): array
    {
        $context['conversation'] = [
            'channel' => data_get($context, 'conversation.channel'),
            'continuation' => false,
            'segment_reset' => true,
            'memory' => [
                'recent_messages' => [],
                'quoted_message' => null,
                'structured' => [],
            ],
        ];

        $context['knowledge'] = [];
        $context['catalog'] = ['query' => '', 'terms' => [], 'categories' => [], 'products' => []];
        $context['orders'] = [];
        $context['payments'] = [];
        $context['shipments'] = [];
        $context['shop'] = null;
        $context['returns'] = [];
        $context['disputes'] = [];
        $context['tickets'] = [];

        foreach (['orders', 'payments', 'shipments', 'shop', 'returns', 'disputes', 'tickets', 'knowledge', 'catalog'] as $scope) {
            $context['data_status'][$scope] = array_merge(
                (array) data_get($context, 'data_status.'.$scope, []),
                [
                    'loaded' => false,
                    'count' => 0,
                    'authorized' => true,
                    'reason' => 'Nouveau segment conversationnel : ancien dossier non transmis au modèle.',
                ],
            );
        }

        return $context;
    }

    /** @return list<string> */
    private function requestedScopes(array $result): array
    {
        $allowed = ['orders', 'payments', 'shipments', 'shop', 'returns', 'disputes', 'tickets', 'knowledge', 'catalog'];

        return array_values(array_unique(array_intersect(
            array_map('strval', (array) data_get($result, 'metadata.data_needs', [])),
            $allowed,
        )));
    }

    /** @return array<string,mixed> */
    private function mergeTurnState(array $state, array $result, SupportAiAgent $agent): array
    {
        $meta = (array) ($result['metadata'] ?? []);

        $facts = array_merge(
            (array) ($state['facts'] ?? []),
            (array) ($meta['facts'] ?? []),
        );

        $state['intent'] = trim((string) ($meta['intent'] ?? $state['intent'] ?? 'general')) ?: 'general';
        $state['agent_role'] = trim((string) ($meta['agent_role'] ?? $agent->role_key)) ?: $agent->role_key;
        $state['priority'] = trim((string) ($meta['priority'] ?? $state['priority'] ?? 'normal')) ?: 'normal';
        $state['topic_key'] = trim((string) ($meta['topic_key'] ?? $state['topic_key'] ?? 'general')) ?: 'general';
        // La segmentation est décidée par Laravel (mémoire + session), jamais par Claude.
        // On conserve seulement la suggestion du modèle pour diagnostic.
        $state['new_topic'] = (bool) ($state['new_topic'] ?? false);
        $state['model_suggested_new_topic'] = (bool) ($meta['new_topic'] ?? false);
        $state['facts'] = array_slice(array_filter($facts, fn ($v) => $v !== null && $v !== ''), 0, 30, true);
        $state['missing_fields'] = array_values(array_unique(array_filter((array) ($meta['missing_fields'] ?? $state['missing_fields'] ?? []))));

        return $state;
    }

    private function createHumanHandoff(
        SupportConversation $conversation,
        string $customerMessage,
        string $priority,
    ): void {
        $ticket = $this->ticketFactory->fromConversation(
            $conversation,
            "Miss Salomé demande une prise en charge humaine après analyse du dossier autorisé.\n\nMessage client : {$customerMessage}",
            $priority,
        );

        $handoff = $this->handoffQueue->enqueue(
            $conversation->fresh(),
            'support',
            'Escalade humaine demandée par Miss Salomé.',
            $priority === 'urgent' ? 'urgent' : 'high',
            ticket: $ticket,
        );

        $conversation->forceFill([
            'support_ticket_id' => $ticket->id,
            'assigned_to' => $handoff->assigned_to,
            'status' => 'waiting_human',
            'requires_human' => true,
        ])->save();
    }

    /** @return array<string,mixed> */
    private function safeFallback(
        ?string $reason = null,
        ?SupportConversation $conversation = null,
        string $customerMessage = '',
        array $context = [],
    ): array
    {
        $previousFailures = $conversation ? $this->consecutiveFallbackCount($conversation) : 0;

        return [
            'body' => $this->fallbackBody($previousFailures, $customerMessage, $context),
            'confidence' => 0.0,
            'provider' => 'safe_fallback',
            'metadata' => [
                'action' => 'clarify',
                'intent' => 'general',
                'agent_role' => 'general',
                'priority' => 'normal',
                'topic_key' => 'temporary_error',
                'new_topic' => false,
                'facts' => [],
                'missing_fields' => [],
                'fallback_reason' => $reason,
            ],
        ];
    }

    private function fallbackBody(int $previousFailures, string $customerMessage = '', array $context = []): string
    {
        $normalized = $this->normalizeForWelcome($customerMessage);
        $matchedAccount = (bool) data_get($context, 'requester.matched_account', false);
        $catalogRequest = collect([
            'produit', 'catalogue', 'prix', 'stock', 'disponib', 'marbre', 'sable',
            'gravier', 'tole', 'ciment', 'carreau', 'panier',
        ])->contains(fn (string $term) => str_contains($normalized, $term));

        if ($catalogRequest) {
            return $previousFailures >= 1
                ? 'La recherche dans le catalogue reste momentanément indisponible. Vous pouvez consulter le catalogue sur le site ou l’application OVANIE, ou demander une prise en charge humaine.'
                : 'Je rencontre momentanément une difficulté pour consulter le catalogue OVANIE. Merci de réessayer dans un instant ; aucune vérification de compte n’est nécessaire pour cette question produit.';
        }

        $ordersLoaded = (bool) data_get($context, 'data_status.orders.loaded', false);
        if ($matchedAccount && $ordersLoaded) {
            $orders = (array) data_get($context, 'orders', []);
            $knownReferences = (array) data_get($context, 'conversation.memory.structured.known_references', []);
            preg_match('/\b(?:CMD|ORD|OV|COM)-[A-Z0-9-]{4,}\b/i', $customerMessage, $match);
            $reference = strtoupper((string) ($match[0] ?? end($knownReferences) ?: ''));

            if ($orders !== []) {
                $order = $orders[0];
                $number = trim((string) ($order['number'] ?? $reference));
                $status = trim((string) ($order['status'] ?? ''));
                $deliveryStatus = trim((string) ($order['delivery_status'] ?? ''));
                $details = array_filter([
                    $status !== '' ? 'statut : '.$status : null,
                    $deliveryStatus !== '' ? 'livraison : '.$deliveryStatus : null,
                ]);

                return 'Votre compte OVANIE a bien été identifié et la commande '.$number.' a été retrouvée'
                    .($details !== [] ? ' ('.implode(', ', $details).')' : '').'.';
            }

            if ($reference !== '') {
                return 'Votre compte OVANIE a bien été identifié, mais aucune commande '.$reference.' n’est associée à ce compte dans les données actuellement accessibles. Vérifiez la référence ou demandez une prise en charge humaine.';
            }
        }

        if ($matchedAccount) {
            return 'Votre compte OVANIE a bien été identifié. Je rencontre toutefois une difficulté pour charger les informations nécessaires à votre demande. Vous pouvez réessayer ou demander une prise en charge humaine.';
        }

        if ($previousFailures >= 1) {
            return 'La vérification reste momentanément indisponible. Vous pouvez essayer avec le numéro de téléphone associé au compte. Si le problème continue, dites « support humain » afin que votre demande soit transmise pour une prise en charge.';
        }

        return 'Je rencontre momentanément une difficulté pour vérifier les informations de votre compte. Vous pouvez réessayer avec l’adresse e-mail ou le numéro de téléphone associé au compte.';
    }

    private function consecutiveFallbackCount(SupportConversation $conversation): int
    {
        return $conversation->messages()
            ->where('is_internal', false)
            ->where('sender_type', 'ai')
            ->latest('id')
            ->limit(2)
            ->get(['provider'])
            ->takeWhile(fn (SupportConversationMessage $message) => $message->provider === 'safe_fallback')
            ->count();
    }

    private function extractEmail(string $message): ?string
    {
        if (! preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', $message, $matches)) {
            return null;
        }

        $email = mb_strtolower(trim((string) ($matches[0] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function extractPhoneHint(string $message): ?string
    {
        if (! preg_match_all('/(?<!\d)(?:\+?\d[\d\s().-]{6,}\d)(?!\d)/u', $message, $matches)) {
            return null;
        }

        foreach ((array) ($matches[0] ?? []) as $candidate) {
            $candidate = trim((string) $candidate);
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                return $candidate;
            }
        }

        return null;
    }

    private function enforceSellerChannelDisclosure(string $body, string $customerBody, array $context): string
    {
        $message = mb_strtolower($customerBody);
        $sellerRequest = str_contains($message, 'vendre')
            || str_contains($message, 'vendeur')
            || (str_contains($message, 'ouvrir') && str_contains($message, 'boutique'));

        if (! $sellerRequest) {
            return $body;
        }

        $lowerBody = mb_strtolower($body);
        if (str_contains($lowerBody, 'whatsapp')
            && (str_contains($lowerBody, 'site') || str_contains($lowerBody, 'application'))) {
            return $body;
        }

        $matchedAccount = (bool) data_get($context, 'requester.matched_account', false);
        $nextQuestion = $matchedAccount
            ? 'Souhaitez-vous que je vous indique où lancer l’ouverture de votre boutique depuis votre espace client ?'
            : 'Souhaitez-vous que je vous indique la page à ouvrir pour créer votre compte OVANIE ?';

        $body = preg_replace(
            '/\s*Avez-vous\s+déjà\s+un\s+compte\s+OVANIE,?\s+ou\s+souhaitez-vous\s+en\s+créer\s+un\s+maintenant\s*\?/iu',
            '',
            $body,
        ) ?? $body;

        $notice = 'Je peux vous accompagner et répondre à vos questions ici sur WhatsApp. En revanche, la création effective du compte et de la boutique doit être réalisée sur le site ou l’application OVANIE ; aucune création ne peut être effectuée directement dans cette conversation.';

        return trim($notice."\n\n".trim($body)."\n\n".$nextQuestion);
    }

    private function enforceVerifiedStateClaims(string $body, array $context): string
    {
        $matchedAccount = (bool) data_get($context, 'requester.matched_account', false)
            && ! (bool) data_get($context, 'requester.account_match_requires_confirmation', false);
        $verifiedShop = $matchedAccount && is_array(data_get($context, 'shop'));
        $hasOrders = $matchedAccount && count((array) data_get($context, 'orders', [])) > 0;
        $hasPayments = $matchedAccount && count((array) data_get($context, 'payments', [])) > 0;

        $claimsCreatedShop = (bool) preg_match(
            '/(?:maintenant\s+que\s+votre\s+boutique\s+est\s+créée|votre\s+boutique\s+(?:est|a\s+été)\s+(?:créée|confirmée|validée|approuvée))/iu',
            $body,
        );
        $claimsExistingCredentials = (bool) preg_match(
            '/(?:vos\s+identifiants(?:\s+de\s+vendeur)?|e-mail\s+et\s+mot\s+de\s+passe\s+que\s+vous\s+avez\s+créés)/iu',
            $body,
        );

        if (($claimsCreatedShop && ! $verifiedShop) || ($claimsExistingCredentials && ! $matchedAccount)) {
            return 'Je ne trouve pas encore de boutique associée à votre compte. Voulez-vous que je vous guide pour la créer sur le site ou l’application OVANIE ?';
        }

        $claimsOrderState = (bool) preg_match(
            '/votre\s+commande\s+(?:est|a\s+été)\s+(?:créée|confirmée|validée|reçue|expédiée|livrée)/iu',
            $body,
        );
        if ($claimsOrderState && ! $hasOrders) {
            return 'Je ne peux pas confirmer l’existence ou le statut de cette commande avec les données actuellement vérifiées. Pouvez-vous fournir sa référence ?';
        }

        $claimsPaymentState = (bool) preg_match(
            '/votre\s+paiement\s+(?:est|a\s+été)\s+(?:créé|confirmé|validé|reçu|accepté)/iu',
            $body,
        );
        if ($claimsPaymentState && ! $hasPayments) {
            return 'Je ne peux pas confirmer ce paiement avec les données actuellement vérifiées. Pouvez-vous fournir sa référence ?';
        }

        return $body;
    }

    /** Empêche Claude de nier un produit que Laravel vient de retrouver. */
    private function enforceCatalogConsistency(string $body, string $customerMessage, array $context): string
    {
        $normalized = $this->normalizeForWelcome($customerMessage);
        $asksToAdd = (str_contains($normalized, 'ajoute') || str_contains($normalized, 'ajoutez'))
            && str_contains($normalized, 'panier');
        if (! $asksToAdd) {
            return $body;
        }

        $products = array_values(array_filter(
            (array) data_get($context, 'catalog.products', []),
            fn ($product) => is_array($product) && trim((string) ($product['name'] ?? '')) !== '',
        ));
        if ($products === []) {
            return $body;
        }

        $product = $products[0];
        $details = trim((string) $product['name']);
        $price = $product['effective_price'] ?? $product['price'] ?? null;
        if (is_numeric($price)) {
            $details .= ' — '.number_format((float) $price, 0, ',', ' ').' FCFA';
        }

        $availability = trim((string) ($product['availability_status'] ?? ''));
        if ($availability !== '') {
            $details .= ' — disponibilité : '.$availability;
        } elseif (is_numeric($product['stock'] ?? null)) {
            $details .= ' — stock : '.(int) $product['stock'];
        }

        return 'Le produit est bien retrouvé dans le catalogue OVANIE : '.$details.'. '
            .'Je ne peux pas modifier votre panier directement depuis WhatsApp. Ouvrez sa fiche sur le site ou l’application OVANIE, choisissez la quantité, puis appuyez sur « Ajouter au panier ».';
    }

    private function resumeBlockingPendingQuestion(string $body, array $memory): string
    {
        if (! (bool) ($memory['reply_to_pending_question'] ?? false)) {
            return $body;
        }

        $questions = array_values(array_filter(
            (array) data_get($memory, 'structured.pending_questions', []),
            fn ($item) => is_array($item) && trim((string) ($item['body'] ?? '')) !== '',
        ));
        if (count($questions) < 2) {
            return $body;
        }

        // La dernière question vient d'être répondue ; reprendre la plus récente
        // des questions bloquantes restées sous celle-ci.
        array_pop($questions);
        $blocking = collect($questions)->reverse()->first(
            fn (array $item) => (bool) ($item['blocking'] ?? false),
        );
        if (! is_array($blocking)) {
            return $body;
        }

        $question = trim((string) ($blocking['body'] ?? ''));
        $lower = mb_strtolower($body);
        if ($question === '' || str_contains($lower, 'adresse e-mail') || str_contains($lower, 'numéro de téléphone')) {
            return $body;
        }

        // Évite deux questions concurrentes : la dernière question générée est
        // remplacée par la question bloquante du dossier initial.
        $paragraphs = preg_split('/\R{2,}/u', trim($body)) ?: [trim($body)];
        $last = trim((string) end($paragraphs));
        if (str_contains($last, '?')) {
            array_pop($paragraphs);
        }

        return trim(implode("\n\n", $paragraphs)
            ."\n\nPour reprendre votre demande en cours : ".$question);
    }

    /** @return array<string,mixed> */
    private function safeClarification(string $body, ?string $reason = null): array
    {
        return [
            'body' => trim($body),
            'confidence' => 0.0,
            'provider' => 'safe_fallback',
            'metadata' => [
                'action' => 'clarify',
                'fallback_reason' => $reason,
            ],
        ];
    }

    private function appendHumanEscalationMessage(string $body): string
    {
        $phone = trim((string) config('support_ai.human_escalation_phone', '01 61 78 18 18')) ?: '01 61 78 18 18';
        $notice = trim((string) config('support_ai.local_rate_notice', 'appel facturé au tarif local'));

        $suffix = 'Je transmets votre dossier à un superviseur pour une prise en charge humaine. '
            .'Pour toute information complémentaire, vous pouvez appeler le '.$phone;

        if ($notice !== '') {
            $suffix .= ', '.$notice;
        }

        return trim($body."\n\n".$suffix.'.');
    }
}
