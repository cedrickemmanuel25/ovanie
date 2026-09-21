<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppSupportMessage;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\WhatsAppMessage;
use App\Services\SupportAi\SupportAiAuditLogger;
use App\Services\SupportAi\SupportAiOrchestrator;
use App\Services\SupportAi\SupportPhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppSupportMessageProcessor
{
    public function __construct(
        private readonly SupportPhoneNormalizer $phones,
        private readonly SupportAiOrchestrator $orchestrator,
        private readonly SupportAiAuditLogger $audit,
        private readonly WhatsAppCloudApiService $cloudApi,
    ) {}

    /**
     * Sérialise tous les messages d'un même numéro WhatsApp.
     * Deux clients différents peuvent être traités en parallèle, mais deux messages
     * du même client ne peuvent plus faire travailler Claude en concurrence.
     */
    public function processInbound(array $data): void
    {
        $providerMessageId = trim((string) ($data['provider_message_id'] ?? ''));
        $from = $this->phones->canonical($data['from'] ?? null);

        if ($providerMessageId === '' || ! $from) {
            return;
        }

        $ttl = max(30, (int) config('support_ai.conversation.lock_ttl_seconds', 300));
        $wait = max(5, (int) config('support_ai.conversation.lock_wait_seconds', 20));
        $lockKey = 'support-whatsapp:'.hash('sha256', $from);

        Cache::lock($lockKey, $ttl)->block($wait, function () use ($data): void {
            $this->processInboundLocked($data);
        });
    }

    private function processInboundLocked(array $data): void
    {
        /*
        |--------------------------------------------------------------------------
        | Identifiants WhatsApp
        |--------------------------------------------------------------------------
        */

        $providerMessageId = trim(
            (string) ($data['provider_message_id'] ?? '')
        );

        $from = $this->phones->canonical(
            $data['from'] ?? null
        );

        if ($providerMessageId === '' || ! $from) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Éviter les doublons
        |--------------------------------------------------------------------------
        */

        $record = WhatsAppMessage::query()
            ->where(
                'provider_message_id',
                $providerMessageId
            )
            ->first();

        if ($record?->support_conversation_message_id) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Enregistrer le message WhatsApp entrant
        |--------------------------------------------------------------------------
        */

        $record ??= WhatsAppMessage::create([
            'direction' => 'inbound',

            'provider_message_id' =>
                $providerMessageId,

            'phone_number_id' =>
                $data['to_phone_number_id']
                ?? null,

            'from_phone' =>
                $from,

            'to_phone' =>
                $data['display_phone_number']
                ?? null,

            'message_type' =>
                $data['type']
                ?? 'unknown',

            'status' =>
                'received',

            'body' =>
                $data['body']
                ?? null,

            'payload' =>
                $data['raw']
                ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Message déjà traité
        |--------------------------------------------------------------------------
        */

        $existingCustomerMessage =
            SupportConversationMessage::query()
                ->with('conversation')
                ->where(
                    'provider_message_id',
                    $providerMessageId
                )
                ->where(
                    'sender_type',
                    'customer'
                )
                ->first();

        if ($existingCustomerMessage?->conversation) {
            $this->linkInboundRecord(
                $record,
                $existingCustomerMessage->conversation,
                $existingCustomerMessage
            );

            /*
            |--------------------------------------------------------------------------
            | Chercher une réponse générée mais pas encore envoyée
            |--------------------------------------------------------------------------
            */

            $unsentReply =
                $existingCustomerMessage
                    ->conversation
                    ->messages()
                    ->where(
                        'id',
                        '>',
                        $existingCustomerMessage->id
                    )
                    ->whereIn(
                        'sender_type',
                        [
                            'ai',
                            'human',
                        ]
                    )
                    ->where(
                        'is_internal',
                        false
                    )
                    ->whereNull(
                        'provider_message_id'
                    )
                    ->oldest('id')
                    ->first();

            if ($unsentReply) {
                SendWhatsAppSupportMessage::dispatch(
                    $unsentReply->id
                )->afterCommit();
            }

            $this->markReadSafely(
                $providerMessageId
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Conversation
        |--------------------------------------------------------------------------
        */

        $conversation =
            $this->findOrCreateConversation(
                $from,
                $data['contact_name'] ?? null
            );

        /*
        |--------------------------------------------------------------------------
        | Contenu du message
        |--------------------------------------------------------------------------
        */

        $body = trim(
            (string) ($data['body'] ?? '')
        );

        if ($body === '') {
            $body =
                '[Message WhatsApp vide ou non pris en charge]';
        }

        /*
        |--------------------------------------------------------------------------
        | Prise en charge humaine réelle
        |--------------------------------------------------------------------------
        |
        | IMPORTANT :
        |
        | waiting_human ne doit PAS rendre l'IA définitivement muette.
        |
        | Cas 1 :
        | status = waiting_human
        | assigned_to = null
        |
        | => aucun conseiller humain n'a réellement pris la conversation.
        | => Claude peut continuer à analyser les nouveaux messages.
        |
        | Cas 2 :
        | status = human
        | OU assigned_to != null
        |
        | => un conseiller humain a réellement pris la conversation.
        | => l'IA ne doit plus répondre automatiquement.
        |
        */

        if (
            $conversation->assigned_to
            || $conversation->status === 'human'
        ) {
            $customerMessage =
                $this->storeForHumanQueue(
                    $conversation,
                    $body,
                    $providerMessageId,
                    $data
                );

            $this->linkInboundRecord(
                $record,
                $conversation,
                $customerMessage
            );

            $this->markReadSafely(
                $providerMessageId
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Traitement IA
        |--------------------------------------------------------------------------
        */

        try {
            $reply =
                $this->orchestrator
                    ->receiveCustomerMessage(
                        $conversation,
                        $body,
                        null,
                        [
                            'provider_message_id' =>
                                $providerMessageId,

                            'whatsapp_message_type' =>
                                $data['type']
                                ?? 'unknown',

                            'whatsapp_context_message_id' =>
                                $data['context_message_id']
                                ?? null,

                            'requester_phone' =>
                                $from,

                            'requester_name' =>
                                $data['contact_name']
                                ?? null,
                        ]
                    );
        } catch (ValidationException $exception) {
            /*
            |--------------------------------------------------------------------------
            | Fallback sécurité
            |--------------------------------------------------------------------------
            */

            [$customerMessage, $reply] =
                $this->storeSecurityFallback(
                    $conversation,
                    $body,
                    $providerMessageId,
                    $data,
                    $exception,
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Retrouver le message client enregistré par l'orchestrateur
        |--------------------------------------------------------------------------
        */

        $customerMessage ??=
            SupportConversationMessage::query()
                ->where(
                    'support_conversation_id',
                    $conversation->id
                )
                ->where(
                    'provider_message_id',
                    $providerMessageId
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Relier WhatsApp au message Support
        |--------------------------------------------------------------------------
        */

        $this->linkInboundRecord(
            $record,
            $conversation,
            $customerMessage
        );

        /*
        |--------------------------------------------------------------------------
        | Envoyer la réponse vers WhatsApp
        |--------------------------------------------------------------------------
        */

        SendWhatsAppSupportMessage::dispatch(
            $reply->id
        )->afterCommit();

        /*
        |--------------------------------------------------------------------------
        | Marquer le message comme lu
        |--------------------------------------------------------------------------
        */

        $this->markReadSafely(
            $providerMessageId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Statuts WhatsApp
    |--------------------------------------------------------------------------
    */

    public function processStatus(array $data): void
    {
        $providerMessageId = trim(
            (string) ($data['provider_message_id'] ?? '')
        );

        if ($providerMessageId === '') {
            return;
        }

        $message =
            WhatsAppMessage::query()
                ->where(
                    'provider_message_id',
                    $providerMessageId
                )
                ->first();

        if (! $message) {
            return;
        }

        $status = (string) (
            $data['status']
            ?? 'unknown'
        );

        $occurredAt =
            isset($data['timestamp'])
                ? CarbonImmutable::createFromTimestampUTC(
                    (int) $data['timestamp']
                )
                : now();

        $changes = [
            'status' => $status,

            'payload' => array_merge(
                $message->payload ?? [],
                [
                    'status_event' =>
                        $data['raw']
                        ?? $data,
                ]
            ),
        ];

        match ($status) {
            'sent' =>
                $changes['sent_at'] =
                    $message->sent_at
                    ?: $occurredAt,

            'delivered' =>
                $changes['delivered_at'] =
                    $occurredAt,

            'read' =>
                $changes['read_at'] =
                    $occurredAt,

            'failed' =>
                $changes =
                    array_merge(
                        $changes,
                        [
                            'failed_at' =>
                                $occurredAt,

                            'error_code' =>
                                $data['error_code']
                                ?? null,

                            'error_message' =>
                                $data['error_message']
                                ?? null,
                        ]
                    ),

            default => null,
        };

        $message->forceFill(
            $changes
        )->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Trouver ou créer une conversation
    |--------------------------------------------------------------------------
    */

    private function findOrCreateConversation(
        string $from,
        ?string $contactName
    ): SupportConversation {
        $conversation =
            SupportConversation::query()
                ->where(
                    'channel',
                    'whatsapp'
                )
                ->whereIn(
                    'status',
                    [
                        'active',
                        'waiting_human',
                        'human',
                    ]
                )
                ->whereIn(
                    'requester_phone',
                    $this->phones->variants($from)
                )
                ->latest(
                    'last_message_at'
                )
                ->first();

        if ($conversation) {
            if (
                ! $conversation->requester_name
                && $contactName
            ) {
                $conversation->forceFill([
                    'requester_name' =>
                        $contactName,
                ])->save();
            }

            return $conversation;
        }

        return SupportConversation::create([
            'requester_name' =>
                $contactName,

            'requester_phone' =>
                $from,

            'channel' =>
                'whatsapp',

            'status' =>
                'active',

            'subject' =>
                'Assistance WhatsApp OVANIE',

            'metadata' => [
                'source' =>
                    'meta_cloud_api',

                'phone_number_id' =>
                    config(
                        'support_ai.integrations.whatsapp.phone_number_id'
                    ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Stocker un message destiné à la file humaine
    |--------------------------------------------------------------------------
    */

    private function storeForHumanQueue(
        SupportConversation $conversation,
        string $body,
        string $providerMessageId,
        array $data,
    ): SupportConversationMessage {
        return DB::transaction(
            function () use (
                $conversation,
                $body,
                $providerMessageId,
                $data
            ) {
                $message =
                    $conversation
                        ->messages()
                        ->firstOrCreate(
                            [
                                'provider_message_id' =>
                                    $providerMessageId,
                            ],
                            [
                                'sender_type' =>
                                    'customer',

                                'sender_user_id' =>
                                    $conversation
                                        ->requester_user_id,

                                'body' =>
                                    $body,

                                'provider' =>
                                    'whatsapp',

                                'metadata' => [
                                    'whatsapp_message_type' =>
                                        $data['type']
                                        ?? 'unknown',

                                    'whatsapp_context_message_id' =>
                                        $data['context_message_id']
                                        ?? null,

                                    'whatsapp_timestamp' =>
                                        $data['timestamp']
                                        ?? null,

                                    'raw' =>
                                        $data['raw']
                                        ?? null,
                                ],
                            ],
                        );

                $conversation->forceFill([
                    'last_message_at' =>
                        now(),
                ])->save();

                $this->audit->log([
                    'support_conversation_id' =>
                        $conversation->id,

                    'actor_user_id' =>
                        $conversation
                            ->requester_user_id,

                    'action' =>
                        'whatsapp_message_received_human_queue',

                    'decision' =>
                        'waiting_for_human',

                    'risk_level' =>
                        'low',

                    'input' => [
                        'message_id' =>
                            $message->id,

                        'provider_message_id' =>
                            $providerMessageId,
                    ],
                ]);

                return $message;
            },
            3
        );
    }

    /**
     * @return array{
     *     0:SupportConversationMessage,
     *     1:SupportConversationMessage
     * }
     */
    private function storeSecurityFallback(
        SupportConversation $conversation,
        string $body,
        string $providerMessageId,
        array $data,
        ValidationException $exception,
    ): array {
        return DB::transaction(
            function () use (
                $conversation,
                $body,
                $providerMessageId,
                $data,
                $exception
            ) {
                $customer =
                    $conversation
                        ->messages()
                        ->firstOrCreate(
                            [
                                'provider_message_id' =>
                                    $providerMessageId,
                            ],
                            [
                                'sender_type' =>
                                    'customer',

                                'sender_user_id' =>
                                    $conversation
                                        ->requester_user_id,

                                'body' =>
                                    $body,

                                'provider' =>
                                    'whatsapp',

                                'metadata' => [
                                    'raw' =>
                                        $data['raw']
                                        ?? null,
                                ],
                            ],
                        );

                /*
                |--------------------------------------------------------------------------
                | Réponse sécurité
                |--------------------------------------------------------------------------
                */

                $reply =
                    $conversation
                        ->messages()
                        ->create([
                            'sender_type' =>
                                'ai',

                            'ai_agent_id' =>
                                $conversation
                                    ->ai_agent_id,

                            'body' =>
                                'Je ne peux pas rattacher cette référence à votre compte. '
                                .'Vérifiez la référence indiquée ou demandez la prise en charge '
                                .'par un conseiller OVANIE.',

                            'confidence' =>
                                100,

                            'provider' =>
                                'security_fallback',

                            'metadata' => [
                                'validation_errors' =>
                                    $exception->errors(),
                            ],
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Escalade sécurité
                |--------------------------------------------------------------------------
                */

                $conversation->forceFill([
                    'requires_human' =>
                        true,

                    'status' =>
                        'waiting_human',

                    'last_message_at' =>
                        now(),
                ])->save();

                /*
                |--------------------------------------------------------------------------
                | Audit
                |--------------------------------------------------------------------------
                */

                $this->audit->log([
                    'support_conversation_id' =>
                        $conversation->id,

                    'actor_user_id' =>
                        $conversation
                            ->requester_user_id,

                    'action' =>
                        'whatsapp_reference_rejected',

                    'decision' =>
                        'security_handoff_required',

                    'risk_level' =>
                        'high',

                    'input' => [
                        'provider_message_id' =>
                            $providerMessageId,
                    ],

                    'output' => [
                        'validation_errors' =>
                            $exception->errors(),

                        'reply_message_id' =>
                            $reply->id,
                    ],
                ]);

                return [
                    $customer,
                    $reply,
                ];
            },
            3
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lier le message WhatsApp à la conversation Support
    |--------------------------------------------------------------------------
    */

    private function linkInboundRecord(
        WhatsAppMessage $record,
        SupportConversation $conversation,
        ?SupportConversationMessage $message,
    ): void {
        $record->forceFill([
            'support_conversation_id' =>
                $conversation->id,

            'support_conversation_message_id' =>
                $message?->id,
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Marquer comme lu sans casser le traitement
    |--------------------------------------------------------------------------
    */

    private function markReadSafely(
        string $providerMessageId
    ): void {
        try {
            $this->cloudApi->markAsRead(
                $providerMessageId
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}