<?php

namespace App\Jobs;

use App\Models\SupportConversationMessage;
use App\Models\WhatsAppMessage;
use App\Services\SupportAi\SupportAiAuditLogger;
use App\Services\SupportAi\SupportPhoneNormalizer;
use App\Services\WhatsApp\WhatsAppCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppSupportMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(public readonly int $conversationMessageId)
    {
        $this->onQueue((string) config('support_ai.queue', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(
        WhatsAppCloudApiService $cloudApi,
        SupportPhoneNormalizer $phones,
        SupportAiAuditLogger $audit,
    ): void {
        $message = SupportConversationMessage::query()
            ->with('conversation')
            ->find($this->conversationMessageId);

        if (! $message
            || ! $message->conversation
            || $message->conversation->channel !== 'whatsapp'
            || $message->is_internal
            || $message->sender_type === 'customer'
            || filled($message->provider_message_id)) {
            return;
        }

        $to = $phones->canonical($message->conversation->requester_phone);
        if (! $to) {
            throw new \RuntimeException('La conversation WhatsApp ne possède aucun numéro destinataire valide.');
        }

        $record = WhatsAppMessage::query()->firstOrNew([
            'support_conversation_message_id' => $message->id,
        ]);

        $record->forceFill([
            'support_conversation_id' => $message->conversation->id,
            'direction' => 'outbound',
            'phone_number_id' => config('support_ai.integrations.whatsapp.phone_number_id'),
            'from_phone' => null,
            'to_phone' => $to,
            'message_type' => 'text',
            'body' => $message->body,
            'status' => $record->exists ? $record->status : 'queued',
        ])->save();

        try {
            $result = $cloudApi->sendTextMessage($to, $message->body);

            /*
            | Garder provider=anthropic/openai/... pour savoir quelle IA a généré
            | le texte. WhatsApp est un canal de livraison, pas le fournisseur IA.
            */
            $message->forceFill([
                'provider_message_id' => $result['id'],
                'metadata' => array_merge($message->metadata ?? [], [
                    'delivery_provider' => 'whatsapp_meta',
                    'whatsapp_sent_at' => now()->toISOString(),
                ]),
            ])->save();

            $record->forceFill([
                'provider_message_id' => $result['id'],
                'status' => 'accepted',
                'sent_at' => now(),
                'payload' => $result['payload'],
                'error_code' => null,
                'error_message' => null,
                'failed_at' => null,
            ])->save();

            $audit->log([
                'support_conversation_id' => $message->conversation->id,
                'ai_agent_id' => $message->ai_agent_id,
                'actor_user_id' => $message->sender_user_id,
                'action' => 'whatsapp_message_sent',
                'decision' => 'outbound_delivered_to_meta',
                'risk_level' => 'low',
                'input' => ['conversation_message_id' => $message->id],
                'output' => [
                    'provider_message_id' => $result['id'],
                    'ai_provider' => $message->provider,
                    'delivery_provider' => 'whatsapp_meta',
                ],
            ]);
        } catch (Throwable $exception) {
            $record->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'failed_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
