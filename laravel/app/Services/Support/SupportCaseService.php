<?php

namespace App\Services\Support;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryIncident;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\SupportContextLink;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\SupportRequester;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\VendorPayout;
use App\Services\SupportAi\SupportAiAuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SupportCaseService
{
    public const REQUESTER_TYPES = ['client', 'vendor', 'commercial', 'driver', 'guest'];

    public function __construct(private readonly SupportAiAuditLogger $audit)
    {
    }

    public function requesterFor(Authenticatable $actor, string $type): SupportRequester
    {
        if (! in_array($type, self::REQUESTER_TYPES, true)) {
            throw ValidationException::withMessages(['requester_type' => 'Type de demandeur support invalide.']);
        }

        if ($actor instanceof DeliveryDriver) {
            abort_unless($type === 'driver', 403);
            return SupportRequester::updateOrCreate(
                ['requester_type' => 'driver', 'delivery_driver_id' => $actor->id],
                [
                    'name' => $actor->name,
                    'email' => $actor->email,
                    'phone' => $actor->phone,
                    'metadata' => ['onboarding_status' => $actor->onboarding_status],
                ]
            );
        }

        abort_unless($actor instanceof User, 403);
        $shopId = null;
        if ($type === 'vendor') {
            abort_unless($actor->role === 'vendor', 403);
            $shopId = $actor->shop?->id;
            abort_unless($shopId, 403, 'Aucune boutique vendeur n’est associée à ce compte.');
        }
        if ($type === 'commercial') {
            abort_unless($actor->role === 'commercial', 403);
        }
        if ($type === 'client') {
            abort_unless($actor->role === 'client', 403);
        }

        return SupportRequester::updateOrCreate(
            ['requester_type' => $type, 'user_id' => $actor->id, 'shop_id' => $shopId],
            [
                'name' => $actor->name ?: trim(($actor->first_name ?? '').' '.($actor->last_name ?? '')),
                'email' => $actor->email,
                'phone' => $actor->phone ?: ($actor->whatsapp_phone ?? null),
            ]
        );
    }

    public function createTicket(
        Authenticatable $actor,
        string $requesterType,
        string $sourceApp,
        array $data,
        array $attachments = []
    ): SupportTicket {
        return DB::transaction(function () use ($actor, $requesterType, $sourceApp, $data, $attachments) {
            $requester = $this->requesterFor($actor, $requesterType);
            $contexts = $this->validateContexts($actor, $requesterType, (array) ($data['contexts'] ?? []));

            $ticket = SupportTicket::create([
                'support_requester_id' => $requester->id,
                'requester_user_id' => $actor instanceof User ? $actor->id : null,
                'requester_name' => $requester->name,
                'requester_email' => $requester->email,
                'requester_phone' => $requester->phone,
                'channel' => 'mobile',
                'source_app' => $sourceApp,
                'category' => $data['category'],
                'priority' => 'normal',
                'status' => 'open',
                'team' => 'support',
                'subject' => trim((string) $data['subject']),
                'description' => trim((string) $data['description']),
                'created_by' => $actor instanceof User ? $actor->id : null,
                'metadata' => array_filter([
                    'requester_type' => $requesterType,
                    'source_app' => $sourceApp,
                ]),
            ]);

            foreach ($contexts as $index => $context) {
                SupportContextLink::create([
                    'support_ticket_id' => $ticket->id,
                    'context_type' => $context['type'],
                    'context_id' => $context['id'],
                    'is_primary' => $index === 0,
                ]);
            }

            $this->mirrorLegacyContextColumns($ticket, $contexts);

            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'author_id' => $actor instanceof User ? $actor->id : null,
                'author_type' => $requesterType,
                'body' => trim((string) $data['description']),
                'is_internal_note' => false,
                'attachments' => $attachments,
            ]);

            // Toute demande créée depuis une application devient aussi un échange
            // visible dans la Boîte de réception humaine du Support.
            $conversation = $this->ensureConversationForTicket(
                $ticket->fresh(['requesterProfile']),
                $actor,
                $requesterType,
                'customer',
            );

            $this->audit->log([
                'support_conversation_id' => $conversation->id,
                'actor_user_id' => $actor instanceof User ? $actor->id : null,
                'action' => 'support_request_received',
                'decision' => 'human_support_queue',
                'risk_level' => 'low',
                'context' => [
                    'ticket_id' => $ticket->id,
                    'ticket_reference' => $ticket->reference,
                    'requester_type' => $requesterType,
                    'source_app' => $sourceApp,
                ],
            ]);

            return $ticket->fresh(['requesterProfile', 'contextLinks', 'conversations'])->loadCount('messages');
        });
    }

    /**
     * Garantit qu'un dossier possède une conversation dans la Boîte de réception.
     * Cela permet aux demandes créées directement depuis les applications mobiles
     * et aux dossiers créés manuellement d'être suivis dans le même flux humain.
     */
    public function ensureConversationForTicket(
        SupportTicket $ticket,
        ?Authenticatable $actor = null,
        ?string $requesterType = null,
        string $initialSenderType = 'customer',
    ): SupportConversation {
        $existing = $ticket->conversations()->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        $ticket->loadMissing('requesterProfile');
        $requesterType ??= $ticket->requesterProfile?->requester_type
            ?: data_get($ticket->metadata, 'requester_type', 'guest');

        $channel = match ((string) $ticket->channel) {
            'phone', 'ai_call' => 'phone',
            'email' => 'email',
            'whatsapp' => 'whatsapp',
            'internal' => 'internal',
            default => 'chat',
        };

        $staffInitiated = $initialSenderType === 'human';
        $conversation = SupportConversation::create([
            'support_requester_id' => $ticket->support_requester_id,
            'requester_user_id' => $ticket->requester_user_id,
            'requester_name' => $ticket->requester_name,
            'requester_email' => $ticket->requester_email,
            'requester_phone' => $ticket->requester_phone,
            'channel' => $channel,
            'source_app' => $ticket->source_app ?: 'support_web',
            'status' => $staffInitiated ? 'human' : 'waiting_human',
            'assigned_to' => $ticket->assigned_to,
            'support_ticket_id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'shop_id' => $ticket->shop_id,
            'payment_id' => $ticket->payment_id,
            'shipment_id' => $ticket->shipment_id,
            'return_id' => $ticket->return_id,
            'dispute_id' => $ticket->dispute_id,
            'delivery_incident_id' => $ticket->delivery_incident_id,
            'subject' => $ticket->subject,
            'summary' => $ticket->description,
            'priority' => $ticket->priority ?: 'normal',
            'requires_human' => true,
            'last_message_at' => now(),
            'metadata' => [
                'created_from_ticket' => true,
                'ticket_reference' => $ticket->reference,
                'requester_type' => $requesterType,
            ],
        ]);

        SupportConversationMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_type' => $initialSenderType,
            'sender_user_id' => $actor instanceof User ? $actor->id : null,
            'body' => (string) $ticket->description,
            'format' => 'text',
            'is_internal' => false,
            'provider' => 'support_ticket',
            'metadata' => [
                'ticket_id' => $ticket->id,
                'ticket_reference' => $ticket->reference,
                'requester_type' => $requesterType,
            ],
        ]);

        return $conversation;
    }

    /**
     * Réplique un message de dossier dans la conversation liée afin que la
     * Boîte de réception reste le fil unique de communication visible par le
     * conseiller, sans supprimer l'historique historique des tickets.
     */
    public function appendConversationMessageForTicket(
        SupportTicket $ticket,
        string $body,
        string $senderType,
        ?User $sender = null,
        bool $internal = false,
        array $metadata = [],
    ): SupportConversationMessage {
        $conversation = $this->ensureConversationForTicket(
            $ticket,
            $sender,
            $ticket->requesterProfile?->requester_type,
            $senderType === 'human' ? 'human' : 'customer',
        );

        $message = SupportConversationMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_user_id' => $sender?->id,
            'body' => $body,
            'format' => 'text',
            'is_internal' => $internal,
            'provider' => 'support_ticket',
            'metadata' => array_merge([
                'ticket_id' => $ticket->id,
                'ticket_reference' => $ticket->reference,
            ], $metadata),
        ]);

        $updates = ['last_message_at' => now()];
        if ($senderType === 'customer') {
            $updates['status'] = 'waiting_human';
            $updates['requires_human'] = true;
        } elseif ($senderType === 'human') {
            $updates['status'] = 'human';
            $updates['requires_human'] = true;
            if ($sender) {
                $updates['assigned_to'] = $conversation->assigned_to ?: $sender->id;
            }
        }
        $conversation->forceFill($updates)->save();

        $this->audit->log([
            'support_conversation_id' => $conversation->id,
            'actor_user_id' => $sender?->id,
            'action' => $senderType === 'customer' ? 'support_customer_message' : 'support_human_reply',
            'decision' => $internal ? 'internal_note' : 'message_recorded',
            'risk_level' => 'low',
            'context' => [
                'ticket_id' => $ticket->id,
                'ticket_reference' => $ticket->reference,
            ],
        ]);

        return $message;
    }

    public function actorOwnsTicket(Authenticatable $actor, SupportTicket $ticket, string $requesterType): bool
    {
        if ($ticket->support_requester_id) {
            $requester = $ticket->requesterProfile;
            if (! $requester || $requester->requester_type !== $requesterType) {
                return false;
            }
            return $actor instanceof DeliveryDriver
                ? (int) $requester->delivery_driver_id === (int) $actor->id
                : (int) $requester->user_id === (int) $actor->getAuthIdentifier();
        }

        return $actor instanceof User && (int) $ticket->requester_user_id === (int) $actor->id;
    }

    public function validateContexts(Authenticatable $actor, string $requesterType, array $contexts): array
    {
        $validated = [];
        foreach (array_slice($contexts, 0, 8) as $context) {
            if (! is_array($context)) continue;
            $type = trim((string) ($context['type'] ?? ''));
            $id = (int) ($context['id'] ?? 0);
            if ($type === '' || $id <= 0) continue;
            if (! $this->actorOwnsContext($actor, $requesterType, $type, $id)) {
                throw ValidationException::withMessages(['contexts' => "Le contexte {$type} #{$id} n’est pas accessible depuis ce compte."]);
            }
            $validated[$type.':'.$id] = ['type' => $type, 'id' => $id];
        }
        return array_values($validated);
    }

    private function actorOwnsContext(Authenticatable $actor, string $requesterType, string $type, int $id): bool
    {
        if ($actor instanceof DeliveryDriver) {
            if ($requesterType !== 'driver') return false;
            return match ($type) {
                'driver' => $id === (int) $actor->id,
                'delivery_assignment', 'mission' => DeliveryAssignment::query()->whereKey($id)->where('driver_id', $actor->id)->exists(),
                'delivery_incident' => DeliveryIncident::query()->whereKey($id)->where(function ($q) use ($actor) {
                    $q->where(function ($reporter) use ($actor) {
                        $reporter->where('reported_by_type', 'driver')->where('reported_by_id', $actor->id);
                    })->orWhereIn('order_id', DeliveryAssignment::query()->where('driver_id', $actor->id)->select('order_id'));
                })->exists(),
                default => false,
            };
        }

        if (! $actor instanceof User) return false;

        if ($requesterType === 'client') {
            return match ($type) {
                'order' => Order::query()->operational()->whereKey($id)->where('client_id', $actor->id)->exists(),
                'payment' => Payment::query()->whereKey($id)->where('user_id', $actor->id)->exists(),
                'shipment' => Shipment::query()->whereKey($id)->whereHas('order', fn ($q) => $q->where('client_id', $actor->id))->exists(),
                'return' => ReturnModel::query()->whereKey($id)->where('client_id', $actor->id)->exists(),
                'dispute' => Dispute::query()->whereKey($id)->where(function ($q) use ($actor) {
                    if (Schema::hasColumn('disputes', 'client_id')) $q->where('client_id', $actor->id);
                    elseif (Schema::hasColumn('disputes', 'user_id')) $q->where('user_id', $actor->id);
                    else $q->whereRaw('1 = 0');
                })->exists(),
                default => false,
            };
        }

        if ($requesterType === 'vendor') {
            $shop = $actor->shop;
            if (! $shop) return false;
            return match ($type) {
                'shop' => $id === (int) $shop->id,
                'product' => Product::query()->whereKey($id)->where('shop_id', $shop->id)->exists(),
                'order' => Order::query()->whereKey($id)->where(function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id)->orWhereHas('items', fn ($items) => $items->where('shop_id', $shop->id));
                })->exists(),
                'shipment' => Shipment::query()->whereKey($id)->where('shop_id', $shop->id)->exists(),
                'vendor_payout' => VendorPayout::query()->whereKey($id)->where('shop_id', $shop->id)->exists(),
                'return' => ReturnModel::query()->whereKey($id)->where('shop_id', $shop->id)->exists(),
                default => false,
            };
        }

        if ($requesterType === 'commercial') {
            if ($actor->role !== 'commercial') return false;
            return match ($type) {
                'shop' => Shop::query()->whereKey($id)->where(function ($q) use ($actor) {
                    $q->where('created_by_commercial_id', $actor->id)->orWhere('managed_by_commercial_id', $actor->id);
                })->exists(),
                'client' => User::query()->whereKey($id)->where('created_by_commercial_id', $actor->id)->exists(),
                default => false,
            };
        }

        return false;
    }

    private function mirrorLegacyContextColumns(SupportTicket $ticket, array $contexts): void
    {
        $map = [
            'order' => 'order_id',
            'shop' => 'shop_id',
            'payment' => 'payment_id',
            'shipment' => 'shipment_id',
            'return' => 'return_id',
            'dispute' => 'dispute_id',
            'delivery_incident' => 'delivery_incident_id',
        ];
        $updates = [];
        foreach ($contexts as $context) {
            $column = $map[$context['type']] ?? null;
            if ($column && Schema::hasColumn('support_tickets', $column) && ! array_key_exists($column, $updates)) {
                $updates[$column] = $context['id'];
            }
        }
        if ($updates) $ticket->update($updates);
    }
}
