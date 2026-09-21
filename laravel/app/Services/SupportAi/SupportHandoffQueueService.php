<?php

namespace App\Services\SupportAi;

use App\Models\CommercialLead;
use App\Models\DeliveryIncident;
use App\Models\SupportAgentHandoff;
use App\Models\SupportCall;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportHandoffQueueService
{
    public function __construct(
        private readonly SupportAiAuditLogger $audit
    ) {}

    /**
     * Crée un transfert vers un service.
     *
     * IMPORTANT :
     * - commercial et logistique = transfert métier parallèle.
     *   La conversation IA continue.
     *
     * - support et administration = véritable transfert humain.
     *   La conversation passe en attente humaine.
     */
    public function enqueue(
        SupportConversation $conversation,
        string $targetDepartment,
        string $reason,
        string $severity = 'normal',
        ?SupportCall $call = null,
        ?SupportTicket $ticket = null,
        ?DeliveryIncident $incident = null,
        ?CommercialLead $lead = null,
        ?User $requestedBy = null,
        string $requestedByType = 'ai',
    ): SupportAgentHandoff {
        $queueKey = $this->queueKey(
            $targetDepartment,
            $conversation,
            $incident,
            $lead
        );

        $latestMessageId = (int) $conversation->messages()->max('id');

        $idempotencyKey = hash('sha256', implode('|', [
            $conversation->id,
            $call?->id ?: 0,
            $targetDepartment,
            $queueKey,
            $ticket?->id ?: 0,
            $incident?->id ?: 0,
            $lead?->id ?: 0,
            $latestMessageId,
        ]));

        return DB::transaction(function () use (
            $conversation,
            $targetDepartment,
            $reason,
            $severity,
            $call,
            $ticket,
            $incident,
            $lead,
            $requestedBy,
            $requestedByType,
            $queueKey,
            $idempotencyKey
        ) {
            /**
             * Évite les doublons.
             */
            $existing = SupportAgentHandoff::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            /**
             * Recherche automatiquement le collaborateur
             * le moins chargé dans le service.
             */
            $assignee = $this->leastLoadedAgent($targetDepartment);

            try {
                $handoff = SupportAgentHandoff::create([
                    'support_conversation_id' => $conversation->id,
                    'support_call_id' => $call?->id,
                    'support_ticket_id' => $ticket?->id,
                    'delivery_incident_id' => $incident?->id,
                    'commercial_lead_id' => $lead?->id,

                    'ai_agent_id' => $conversation->ai_agent_id,

                    'requested_by_user_id' => $requestedBy?->id,
                    'requested_by_type' => $requestedByType,

                    'assigned_to' => $assignee?->id,

                    'target_department' => $targetDepartment,
                    'queue_key' => $queueKey,
                    'idempotency_key' => $idempotencyKey,

                    'severity' => $severity,

                    'status' => $assignee
                        ? 'assigned'
                        : 'pending',

                    'reason' => $reason,

                    'requested_at' => now(),

                    'assigned_at' => $assignee
                        ? now()
                        : null,

                    'due_at' => $this->dueAt($severity),
                ]);
            } catch (QueryException $exception) {
                /**
                 * Protection supplémentaire contre les doublons
                 * créés simultanément.
                 */
                $existing = SupportAgentHandoff::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                throw $exception;
            }

            /**
             * ==========================================================
             * GESTION DE L'ÉTAT DE LA CONVERSATION
             * ==========================================================
             *
             * Commercial / Logistique :
             * le transfert est parallèle.
             * L'IA continue de répondre au client.
             *
             * Support / Administration :
             * véritable prise en charge humaine.
             * L'IA est suspendue.
             */
            $humanConversationDepartments = [
                'support',
                'administration',
            ];

            if (in_array(
                $targetDepartment,
                $humanConversationDepartments,
                true
            )) {
                /**
                 * Véritable transfert humain.
                 */
                $conversation->forceFill([
                    'assigned_to' => $targetDepartment === 'support'
                        ? $assignee?->id
                        : $conversation->assigned_to,

                    'status' => 'waiting_human',
                    'requires_human' => true,
                ])->save();
            } else {
                /**
                 * Transfert métier parallèle :
                 *
                 * - commercial
                 * - logistique
                 *
                 * Le transfert est visible dans sa file métier,
                 * mais la conversation WhatsApp reste utilisable
                 * par les agents IA.
                 */
                $conversation->forceFill([
                    'status' => $conversation->assigned_to
                        ? 'human'
                        : 'active',

                    'requires_human' => false,
                ])->save();
            }

            /**
             * Journal d'audit.
             */
            $this->audit->log([
                'support_conversation_id' => $conversation->id,
                'support_call_id' => $call?->id,
                'ai_agent_id' => $conversation->ai_agent_id,
                'actor_user_id' => $requestedBy?->id,

                'action' => 'handoff_enqueued',

                'decision' => $targetDepartment,

                'risk_level' => in_array(
                    $severity,
                    ['urgent', 'critical'],
                    true
                )
                    ? 'high'
                    : 'medium',

                'input' => [
                    'reason' => $reason,
                ],

                'output' => [
                    'handoff_id' => $handoff->id,
                    'reference' => $handoff->reference,
                    'assigned_to' => $assignee?->id,
                    'queue_key' => $queueKey,
                ],
            ]);

            return $handoff->fresh([
                'assignee',
                'ticket',
                'deliveryIncident',
                'commercialLead',
            ]);
        }, 3);
    }

    /**
     * Un collaborateur prend en charge un transfert.
     */
    public function claim(
        SupportAgentHandoff $handoff,
        User $agent
    ): SupportAgentHandoff {
        return DB::transaction(function () use ($handoff, $agent) {
            $locked = SupportAgentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanWorkQueue(
                $agent,
                $locked->target_department
            );

            if (! in_array(
                $locked->status,
                ['pending', 'assigned'],
                true
            )) {
                throw ValidationException::withMessages([
                    'handoff' => 'Ce transfert a déjà été pris en charge.',
                ]);
            }

            if (
                $locked->assigned_to
                && (int) $locked->assigned_to !== (int) $agent->id
            ) {
                throw ValidationException::withMessages([
                    'handoff' => 'Ce transfert est déjà assigné à un autre collaborateur.',
                ]);
            }

            $locked->forceFill([
                'assigned_to' => $agent->id,
                'status' => 'accepted',

                'assigned_at' => $locked->assigned_at
                    ?: now(),

                'accepted_at' => now(),

                'lock_version' => $locked->lock_version + 1,
            ])->save();

            /**
             * Seul le service Support prend réellement
             * la main sur la conversation.
             */
            if ($locked->target_department === 'support') {
                $locked->conversation?->forceFill([
                    'assigned_to' => $agent->id,
                    'status' => 'human',
                    'requires_human' => true,
                ])->save();
            }

            $this->audit->log([
                'support_conversation_id' => $locked->support_conversation_id,
                'support_call_id' => $locked->support_call_id,
                'ai_agent_id' => $locked->ai_agent_id,
                'actor_user_id' => $agent->id,

                'action' => 'handoff_claimed',

                'decision' => $locked->target_department,

                'risk_level' => 'low',

                'output' => [
                    'handoff_id' => $locked->id,
                    'reference' => $locked->reference,
                ],
            ]);

            return $locked->fresh('assignee');
        }, 3);
    }

    /**
     * Affectation manuelle d'un transfert.
     */
    public function assign(
        SupportAgentHandoff $handoff,
        User $assignee,
        User $actor
    ): SupportAgentHandoff {
        $this->assertCanWorkQueue(
            $assignee,
            $handoff->target_department
        );

        return DB::transaction(function () use (
            $handoff,
            $assignee,
            $actor
        ) {
            $locked = SupportAgentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array(
                $locked->status,
                ['pending', 'assigned'],
                true
            )) {
                throw ValidationException::withMessages([
                    'handoff' => 'Ce transfert ne peut plus être réassigné.',
                ]);
            }

            $locked->forceFill([
                'assigned_to' => $assignee->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->log([
                'support_conversation_id' => $locked->support_conversation_id,
                'support_call_id' => $locked->support_call_id,
                'actor_user_id' => $actor->id,

                'action' => 'handoff_assigned',

                'decision' => $locked->target_department,

                'risk_level' => 'low',

                'output' => [
                    'handoff_id' => $locked->id,
                    'assigned_to' => $assignee->id,
                ],
            ]);

            return $locked->fresh('assignee');
        }, 3);
    }

    /**
     * Clôture un transfert.
     */
    public function resolve(
        SupportAgentHandoff $handoff,
        User $actor,
        ?string $notes = null,
        ?string $resolutionCode = null
    ): SupportAgentHandoff {
        return DB::transaction(function () use (
            $handoff,
            $actor,
            $notes,
            $resolutionCode
        ) {
            $locked = SupportAgentHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanWorkQueue(
                $actor,
                $locked->target_department
            );

            if (! in_array(
                $locked->status,
                [
                    'pending',
                    'accepted',
                    'in_progress',
                    'assigned',
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'handoff' => 'Ce transfert ne peut pas être clôturé dans son état actuel.',
                ]);
            }

            $locked->forceFill([
                'status' => 'resolved',

                'notes' => $notes
                    ?: $locked->notes,

                'resolution_code' => $resolutionCode,

                'completed_by' => $actor->id,

                'resolved_at' => now(),

                'lock_version' => $locked->lock_version + 1,
            ])->save();

            /**
             * Si plus aucun transfert humain ouvert,
             * on autorise de nouveau l'IA.
             */
            if (
                $locked->conversation
                && ! $locked->conversation
                    ->handoffs()
                    ->open()
                    ->where('id', '!=', $locked->id)
                    ->whereIn('target_department', [
                        'support',
                        'administration',
                    ])
                    ->exists()
            ) {
                $conversation = $locked->conversation;

                if ($conversation->status === 'waiting_human') {
                    $conversation->forceFill([
                        'requires_human' => false,

                        'status' => $conversation->assigned_to
                            ? 'human'
                            : 'active',
                    ])->save();
                } else {
                    $conversation->forceFill([
                        'requires_human' => false,
                    ])->save();
                }
            }

            $this->audit->log([
                'support_conversation_id' => $locked->support_conversation_id,
                'support_call_id' => $locked->support_call_id,
                'actor_user_id' => $actor->id,

                'action' => 'handoff_resolved',

                'decision' => $resolutionCode
                    ?: 'resolved',

                'risk_level' => 'low',

                'input' => [
                    'notes' => $notes,
                ],

                'output' => [
                    'handoff_id' => $locked->id,
                ],
            ]);

            return $locked->fresh([
                'completedBy',
                'conversation',
            ]);
        }, 3);
    }

    /**
     * Retourne les collaborateurs disponibles
     * pour un département.
     */
    public function agentsForDepartment(string $department)
    {
        $query = User::query()
            ->where('status', 'active');

        if ($department === 'administration') {
            $query->where('is_admin', true);
        } else {
            $query
                ->where('role', $department)
                ->whereHas(
                    'staffProfile',
                    fn ($profile) => $profile->where(
                        'is_active',
                        true
                    )
                );
        }

        return $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
            ]);
    }

    /**
     * Sélectionne automatiquement
     * le collaborateur le moins chargé.
     */
    private function leastLoadedAgent(
        string $department
    ): ?User {
        $query = User::query()
            ->where('status', 'active');

        if ($department === 'administration') {
            $query->where('is_admin', true);
        } else {
            $query
                ->where('role', $department)
                ->whereHas(
                    'staffProfile',
                    fn ($profile) => $profile->where(
                        'is_active',
                        true
                    )
                );
        }

        return $query
            ->withCount([
                'assignedSupportHandoffs as open_handoffs_count'
                    => fn ($handoffs) => $handoffs->open(),
            ])
            ->orderBy('open_handoffs_count')
            ->orderBy('id')
            ->first();
    }

    /**
     * Vérifie que le collaborateur
     * appartient bien au service demandé.
     */
    private function assertCanWorkQueue(
        User $user,
        string $department
    ): void {
        $allowed = $department === 'administration'
            ? (bool) $user->is_admin
            : $user->role === $department;

        if (! $allowed) {
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'Ce collaborateur n’appartient pas au service destinataire.',
            ]);
        }
    }

    /**
     * Détermine la file métier.
     */
    private function queueKey(
        string $department,
        SupportConversation $conversation,
        ?DeliveryIncident $incident,
        ?CommercialLead $lead
    ): string {
        if ($department === 'logistique') {
            return $incident?->severity === 'critical'
                ? 'logistique_critique'
                : 'logistique_incidents';
        }

        if ($department === 'commercial') {
            return 'commercial_business';
        }

        if ($department === 'administration') {
            return 'administration_escalade';
        }

        return $conversation->priority === 'urgent'
            ? 'support_urgent'
            : 'support_general';
    }

    /**
     * Calcule la date limite
     * selon la gravité.
     */
    private function dueAt(string $severity)
    {
        return match ($severity) {
            'critical',
            'urgent' => now()->addMinutes(15),

            'high' => now()->addHour(),

            'low' => now()->addDay(),

            default => now()->addHours(4),
        };
    }
}