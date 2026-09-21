<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\SupportAgentHandoff;
use App\Models\SupportConversationMessage;
use App\Models\SupportTicketMessage;
use App\Services\OrderWorkflowService;
use App\Services\SupportAi\SupportHandoffQueueService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentHandoffController extends Controller
{
    private const OPEN_STATUSES = ['pending', 'assigned', 'accepted', 'in_progress'];

    public function commercial(Request $request)
    {
        return $this->index($request, 'commercial', 'commercial');
    }

    public function logistics(Request $request)
    {
        return $this->index($request, 'logistique', 'logistics');
    }

    public function showLogistics(Request $request, SupportAgentHandoff $handoff)
    {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);

        $handoff->load($this->detailRelations());

        return view('internal.handoffs.show', [
            'handoff' => $handoff,
            'case' => $this->caseData($handoff),
        ]);
    }

    public function claim(Request $request, SupportAgentHandoff $handoff, SupportHandoffQueueService $queues)
    {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);
        $queues->claim($handoff, $request->user('admin'));

        return back()->with('success', 'Dossier pris en charge.');
    }

    public function updateStatus(Request $request, SupportAgentHandoff $handoff)
    {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);

        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'closed'])],
        ]);

        $status = $data['status'];
        if ($status === 'in_progress' && ! in_array($handoff->status, ['assigned', 'accepted', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Le dossier doit d’abord être affecté ou pris en charge.',
            ]);
        }
        if ($status === 'closed' && $handoff->status !== 'resolved') {
            throw ValidationException::withMessages([
                'status' => 'Un dossier doit être résolu avant d’être clôturé.',
            ]);
        }

        $handoff->status = $status;
        $handoff->save();

        return back()->with('success', $status === 'closed'
            ? 'Dossier clôturé.'
            : 'Le traitement du dossier a démarré.');
    }

    public function message(Request $request, SupportAgentHandoff $handoff)
    {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);
        abort_unless($handoff->support_conversation_id, 422, 'Aucune conversation liée à ce dossier.');

        $data = $request->validate(['message' => ['required', 'string', 'max:2500']]);
        $agent = $request->user('admin');

        SupportConversationMessage::create([
            'support_conversation_id' => $handoff->support_conversation_id,
            'sender_type' => 'human',
            'sender_user_id' => $agent?->id,
            'body' => trim($data['message']),
            'format' => 'text',
            'is_internal' => false,
            'provider' => 'logistics_workspace',
            'metadata' => [
                'sender_label' => 'Équipe Logistique - '.($agent?->name ?? 'Responsable Logistique'),
            ],
        ]);
        $handoff->conversation?->update(['last_message_at' => now()]);

        return back()->with('success', 'Message envoyé dans la conversation du dossier.');
    }

    public function resolve(Request $request, SupportAgentHandoff $handoff, SupportHandoffQueueService $queues)
    {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);

        $data = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:3000'],
            'resolution_code' => ['required', Rule::in([
                'incident_resolved', 'commercial_follow_up', 'answered', 'redirected', 'duplicate', 'other',
            ])],
        ]);

        $queues->resolve(
            $handoff,
            $request->user('admin'),
            trim($data['notes']),
            $data['resolution_code'],
        );

        return back()->with('success', 'Résolution enregistrée. Le dossier est maintenant résolu.');
    }

    public function attachment(
        Request $request,
        SupportAgentHandoff $handoff,
        SupportTicketMessage $message,
        int $index
    ): StreamedResponse {
        $this->ensureDepartment($request, $handoff);
        $this->ensureOperationalLogisticsHandoff($handoff);
        $handoff->loadMissing('conversation');
        $ticketId = $handoff->support_ticket_id ?: $handoff->conversation?->support_ticket_id;
        abort_unless($ticketId, 404);
        abort_unless((int) $message->support_ticket_id === (int) $ticketId, 404);

        $attachments = is_array($message->attachments) ? array_values($message->attachments) : [];
        abort_unless(array_key_exists($index, $attachments), 404, 'Pièce jointe introuvable.');

        $attachment = $attachments[$index];
        abort_unless(is_array($attachment) && filled($attachment['path'] ?? null), 404, 'Fichier privé introuvable.');

        $path = (string) $attachment['path'];
        abort_unless(Storage::disk('local')->exists($path), 404, 'Le fichier n’existe plus sur le stockage privé.');

        $name = trim((string) ($attachment['name'] ?? basename($path))) ?: basename($path);

        return Storage::disk('local')->download($path, $name);
    }

    private function index(Request $request, string $department, string $workspace)
    {
        $base = SupportAgentHandoff::query()
            ->where('target_department', $department);

        if ($workspace === 'logistics') {
            // Les vrais transferts créés par SupportHandoffQueueService utilisent
            // les files logistique_incidents / logistique_critique. Les anciennes
            // données de maquette utilisent delivery_support et ne doivent plus
            // apparaître dans l’espace opérationnel.
            $base->operationalLogistics();
        }

        $query = (clone $base)
            ->with($this->listRelations())
            ->latest('requested_at');

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $term = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('reference', 'like', $term)
                    ->orWhere('reason', 'like', $term)
                    ->orWhereHas('conversation', function (Builder $conversation) use ($term) {
                        $conversation->where('requester_name', 'like', $term)
                            ->orWhere('requester_phone', 'like', $term)
                            ->orWhere('subject', 'like', $term)
                            ->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', $term));
                    })
                    ->orWhereHas('ticket', function (Builder $ticket) use ($term) {
                        $ticket->where('reference', 'like', $term)
                            ->orWhere('requester_name', 'like', $term)
                            ->orWhere('subject', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', $term));
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('severity')) {
            $severity = (string) $request->query('severity');
            if ($severity === 'high') {
                $query->whereIn('severity', ['high', 'urgent']);
            } else {
                $query->where('severity', $severity);
            }
        }
        if ($request->filled('type')) {
            $this->applyTypeFilter($query, (string) $request->query('type'));
        }
        if ($request->filled('date_from') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_from'))) {
            $query->whereDate('requested_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date_to'))) {
            $query->whereDate('requested_at', '<=', $request->query('date_to'));
        }

        $all = (clone $base)->get();
        $stats = $this->dashboardStats($all);

        return view('internal.handoffs.index', [
            'handoffs' => $query->paginate(10)->withQueryString(),
            'latest' => (clone $base)->with($this->listRelations())->latest('requested_at')->limit(5)->get(),
            'department' => $department,
            'workspace' => $workspace,
            'stats' => $stats,
        ]);
    }

    private function dashboardStats(Collection $all): array
    {
        $statusCounts = [
            'pending' => $all->where('status', 'pending')->count(),
            'assigned' => $all->where('status', 'assigned')->count(),
            'accepted' => $all->where('status', 'accepted')->count(),
            'in_progress' => $all->where('status', 'in_progress')->count(),
            'resolved' => $all->where('status', 'resolved')->count(),
            'closed' => $all->where('status', 'closed')->count(),
        ];
        $priorityCounts = [
            'critical' => $all->where('severity', 'critical')->count(),
            'high' => $all->whereIn('severity', ['high', 'urgent'])->count(),
            'normal' => $all->where('severity', 'normal')->count(),
            'low' => $all->where('severity', 'low')->count(),
        ];

        $open = $all->whereIn('status', self::OPEN_STATUSES);
        $slaCases = $all->filter(fn (SupportAgentHandoff $handoff) => $handoff->due_at !== null);
        $slaInTime = $slaCases->filter(function (SupportAgentHandoff $handoff) {
            $end = $handoff->resolved_at ?: now();
            return $handoff->due_at && $end->lte($handoff->due_at);
        })->count();
        $slaLate = max(0, $slaCases->count() - $slaInTime);
        $slaPercent = $slaCases->count() > 0
            ? (int) round(($slaInTime / $slaCases->count()) * 100)
            : 100;

        $pickupSeconds = $all
            ->filter(fn (SupportAgentHandoff $handoff) => $handoff->requested_at && ($handoff->accepted_at || $handoff->assigned_at))
            ->map(fn (SupportAgentHandoff $handoff) => $handoff->requested_at->diffInSeconds($handoff->accepted_at ?: $handoff->assigned_at, false))
            ->filter(fn ($seconds) => $seconds >= 0);

        $resolutionSeconds = $all
            ->filter(fn (SupportAgentHandoff $handoff) => $handoff->requested_at && $handoff->resolved_at)
            ->map(fn (SupportAgentHandoff $handoff) => $handoff->requested_at->diffInSeconds($handoff->resolved_at, false))
            ->filter(fn ($seconds) => $seconds >= 0);

        return [
            'total' => $all->count(),
            'new' => $statusCounts['pending'],
            'new_today' => $all->filter(fn (SupportAgentHandoff $h) => $h->requested_at?->isToday())->count(),
            'assigned' => $statusCounts['assigned'],
            'in_treatment' => $statusCounts['accepted'] + $statusCounts['in_progress'],
            'taken_this_week' => $all->filter(fn (SupportAgentHandoff $h) => $h->accepted_at?->gte(now()->startOfWeek()))->count(),
            'priority' => $open->whereIn('severity', ['critical', 'urgent', 'high'])->count(),
            'resolved_total' => $statusCounts['resolved'] + $statusCounts['closed'],
            'status_counts' => $statusCounts,
            'priority_counts' => $priorityCounts,
            'sla_percent' => $slaPercent,
            'sla_in_time' => $slaInTime,
            'sla_late' => $slaLate,
            'avg_pickup' => $this->formatAverageSeconds($pickupSeconds),
            'avg_resolution' => $this->formatAverageSeconds($resolutionSeconds),
        ];
    }

    private function caseData(SupportAgentHandoff $handoff): array
    {
        $conversation = $handoff->conversation;
        $ticket = $handoff->ticket ?: $conversation?->ticket;
        $incident = $handoff->deliveryIncident ?: $conversation?->deliveryIncident ?: $ticket?->deliveryIncident;
        $shipment = $conversation?->shipment ?: $ticket?->shipment ?: $incident?->shipment;
        $order = $conversation?->order ?: $ticket?->order ?: $incident?->order ?: $shipment?->order;

        if (! $shipment && $order?->relationLoaded('shipments')) {
            $shipment = $order->shipments->sortByDesc('id')->first();
        }

        $items = $order?->items ?? collect();
        $primaryItem = $shipment?->orderItem ?: $incident?->orderItem ?: $items->first();
        $requester = $conversation?->requester ?: $ticket?->requester ?: $order?->client;

        $clientName = $requester?->name
            ?: $conversation?->requester_name
            ?: $ticket?->requester_name
            ?: $order?->customer_name
            ?: 'Non identifié';
        $clientPhone = $conversation?->requester_phone
            ?: $ticket?->requester_phone
            ?: $requester?->phone
            ?: $requester?->whatsapp_phone
            ?: $order?->delivery_recipient_phone
            ?: $order?->phone;
        $clientEmail = $conversation?->requester_email
            ?: $ticket?->requester_email
            ?: $requester?->email;

        $subject = $conversation?->subject ?: $ticket?->subject ?: $handoff->reason;
        $description = $incident?->description
            ?: $ticket?->description
            ?: $conversation?->summary
            ?: $handoff->reason;

        $deliveryStatusCode = $primaryItem?->delivery_status
            ?: $shipment?->status
            ?: $order?->delivery_status;
        $deliveryStatus = $this->deliveryStatusLabel($deliveryStatusCode);

        $workflow = app(OrderWorkflowService::class);
        $deliveryMode = $primaryItem
            ? $workflow->providerLabel($primaryItem->delivery_provider)
            : ($shipment?->provider_type ? $this->labelFromCode($shipment->provider_type) : '—');

        $expectedAt = $shipment?->estimated_delivery_at
            ?: $order?->delivery_max_date
            ?: $order?->delivery_min_date;
        $deliveryAddress = $shipment?->delivery_address
            ?: $order?->delivery_address
            ?: $order?->address;

        $productNames = $items
            ->map(fn ($item) => $item->product?->name)
            ->filter()
            ->values();
        $primaryProduct = $primaryItem?->product?->name ?: $productNames->first();

        $ticketAttachments = collect($ticket?->messages ?? [])
            ->flatMap(function (SupportTicketMessage $message) use ($handoff) {
                return collect(is_array($message->attachments) ? $message->attachments : [])
                    ->values()
                    ->map(function ($attachment, int $index) use ($message, $handoff) {
                        if (! is_array($attachment)) {
                            return null;
                        }
                        return [
                            'name' => trim((string) ($attachment['name'] ?? 'Pièce jointe')) ?: 'Pièce jointe',
                            'mime' => (string) ($attachment['mime'] ?? $attachment['type'] ?? ''),
                            'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                            'download_url' => route('logistics.handoffs.attachments.download', [
                                'handoff' => $handoff,
                                'message' => $message,
                                'index' => $index,
                            ]),
                        ];
                    })
                    ->filter();
            })
            ->values();

        return [
            'client_name' => $clientName,
            'client_phone' => $clientPhone ?: '—',
            'client_email' => $clientEmail ?: '—',
            'source' => $this->sourceLabel($handoff),
            'channel' => $this->channelLabel($conversation?->channel ?: $ticket?->channel),
            'subject' => $subject,
            'initial_description' => $conversation?->summary ?: $ticket?->description ?: $handoff->reason,
            'case_type' => $this->caseType($handoff),
            'sla_label' => $this->slaLabel($handoff),
            'sla_remaining' => $this->slaRemaining($handoff),
            'assigned_label' => $handoff->assignee?->name ?: 'Non assigné',
            'order' => $order,
            'order_number' => $order?->order_number ?: '—',
            'order_date' => $order?->created_at?->format('d/m/Y H:i') ?: '—',
            'product_name' => $primaryProduct ?: '—',
            'product_quantity' => $primaryItem?->quantity,
            'products_count' => $items->count(),
            'product_names' => $productNames,
            'delivery_mode' => $deliveryMode,
            'tracking_number' => $shipment?->tracking_number ?: $order?->tracking_number ?: '—',
            'delivery_status' => $deliveryStatus,
            'expected_date' => $expectedAt instanceof CarbonInterface ? $expectedAt->format('d/m/Y H:i') : ($expectedAt ? (string) $expectedAt : '—'),
            'delivery_address' => $deliveryAddress ?: '—',
            'shipment_item_id' => $primaryItem?->id,
            'incident' => $incident,
            'problem_category' => $incident?->incident_type
                ? $this->incidentTypeLabel($incident->incident_type)
                : ($ticket?->category ? $this->labelFromCode($ticket->category) : $subject),
            'problem_severity' => $this->priorityLabel($handoff->severity),
            'problem_details' => $description,
            'attachments' => $ticketAttachments,
            'history' => $this->history($handoff),
            'resolution_label' => $this->resolutionLabel($handoff),
            'resolution_detail' => $handoff->notes,
            'is_open' => in_array($handoff->status, self::OPEN_STATUSES, true),
        ];
    }

    private function history(SupportAgentHandoff $handoff): array
    {
        $events = [];
        if ($handoff->requested_at) {
            $events[] = [
                'time' => $handoff->requested_at->format('d/m/Y H:i'),
                'tone' => 'green',
                'title' => 'Dossier transmis à la Logistique',
                'text' => $this->sourceLabel($handoff),
            ];
        }
        if ($handoff->assigned_at) {
            $events[] = [
                'time' => $handoff->assigned_at->format('d/m/Y H:i'),
                'tone' => 'blue',
                'title' => 'Dossier affecté',
                'text' => $handoff->assignee?->name ?: 'Affectation automatique',
            ];
        }
        if ($handoff->accepted_at) {
            $events[] = [
                'time' => $handoff->accepted_at->format('d/m/Y H:i'),
                'tone' => 'blue',
                'title' => 'Pris en charge',
                'text' => $handoff->assignee?->name ?: 'Équipe Logistique',
            ];
        }
        if ($handoff->status === 'in_progress' && $handoff->updated_at) {
            $events[] = [
                'time' => $handoff->updated_at->format('d/m/Y H:i'),
                'tone' => 'orange',
                'title' => 'Traitement en cours',
                'text' => 'Le dossier est en cours de traitement par la Logistique.',
            ];
        }
        if ($handoff->resolved_at) {
            $events[] = [
                'time' => $handoff->resolved_at->format('d/m/Y H:i'),
                'tone' => 'green',
                'title' => 'Dossier résolu',
                'text' => $handoff->notes ?: $this->resolutionLabel($handoff),
            ];
        }
        if ($handoff->status === 'closed' && $handoff->updated_at) {
            $events[] = [
                'time' => $handoff->updated_at->format('d/m/Y H:i'),
                'tone' => 'green',
                'title' => 'Dossier clôturé',
                'text' => $handoff->completedBy?->name ?: 'Équipe Logistique',
            ];
        }

        return $events;
    }

    private function applyTypeFilter(Builder $query, string $type): void
    {
        $query->where(function (Builder $builder) use ($type) {
            match ($type) {
                'incident' => $builder
                    ->whereNotNull('delivery_incident_id')
                    ->orWhereHas('conversation', fn (Builder $q) => $q->whereNotNull('delivery_incident_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('delivery_incident_id')),
                'retour' => $builder
                    ->whereHas('conversation', fn (Builder $q) => $q->whereNotNull('return_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('return_id')),
                'litige' => $builder
                    ->whereHas('conversation', fn (Builder $q) => $q->whereNotNull('dispute_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('dispute_id')),
                'paiement' => $builder
                    ->whereHas('conversation', fn (Builder $q) => $q->whereNotNull('payment_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('payment_id')),
                'livraison' => $builder
                    ->whereHas('conversation', fn (Builder $q) => $q->whereNotNull('shipment_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('shipment_id'))
                    ->orWhereNotNull('delivery_incident_id'),
                'commande' => $builder
                    ->whereHas('conversation', fn (Builder $q) => $q->whereNotNull('order_id'))
                    ->orWhereHas('ticket', fn (Builder $q) => $q->whereNotNull('order_id')),
                default => $builder->whereRaw('1 = 1'),
            };
        });
    }

    private function listRelations(): array
    {
        return [
            'conversation.requester',
            'conversation.ticket.requester',
            'conversation.ticket.order',
            'conversation.ticket.shipment',
            'conversation.ticket.returnRequest',
            'conversation.ticket.dispute',
            'conversation.ticket.payment',
            'conversation.ticket.deliveryIncident',
            'conversation.order',
            'conversation.shipment',
            'conversation.returnRequest',
            'conversation.dispute',
            'conversation.payment',
            'conversation.deliveryIncident',
            'call',
            'aiAgent',
            'requester',
            'assignee',
            'ticket.requester',
            'ticket.order',
            'ticket.shipment',
            'ticket.returnRequest',
            'ticket.dispute',
            'ticket.payment',
            'ticket.deliveryIncident',
            'deliveryIncident.order',
            'deliveryIncident.shipment',
            'commercialLead',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'conversation.requester',
            'conversation.aiAgent',
            'conversation.messages.aiAgent',
            'conversation.messages.sender',
            'conversation.ticket.requester',
            'conversation.ticket.messages.author',
            'conversation.ticket.order.client',
            'conversation.ticket.order.items.product',
            'conversation.ticket.order.items.shipment',
            'conversation.ticket.order.shipments.orderItem.product',
            'conversation.ticket.shipment.orderItem.product',
            'conversation.ticket.shipment.order',
            'conversation.ticket.returnRequest',
            'conversation.ticket.dispute',
            'conversation.ticket.payment',
            'conversation.ticket.deliveryIncident.orderItem.product',
            'conversation.ticket.deliveryIncident.order',
            'conversation.ticket.deliveryIncident.shipment.orderItem.product',
            'conversation.order.client',
            'conversation.order.items.product',
            'conversation.order.items.shipment',
            'conversation.order.shipments.orderItem.product',
            'conversation.shipment.orderItem.product',
            'conversation.shipment.order',
            'conversation.returnRequest',
            'conversation.dispute',
            'conversation.payment',
            'conversation.deliveryIncident.orderItem.product',
            'conversation.deliveryIncident.order',
            'conversation.deliveryIncident.shipment.orderItem.product',
            'call',
            'aiAgent',
            'requester',
            'assignee',
            'ticket.requester',
            'ticket.messages.author',
            'ticket.order.client',
            'ticket.order.items.product',
            'ticket.order.items.shipment',
            'ticket.order.shipments.orderItem.product',
            'ticket.shipment.orderItem.product',
            'ticket.shipment.order',
            'ticket.returnRequest',
            'ticket.dispute',
            'ticket.payment',
            'ticket.deliveryIncident.orderItem.product',
            'ticket.deliveryIncident.order',
            'ticket.deliveryIncident.shipment.orderItem.product',
            'deliveryIncident.orderItem.product',
            'deliveryIncident.order.client',
            'deliveryIncident.order.items.product',
            'deliveryIncident.order.items.shipment',
            'deliveryIncident.order.shipments.orderItem.product',
            'deliveryIncident.shipment.orderItem.product',
            'deliveryIncident.shipment.order',
            'commercialLead',
            'completedBy',
        ];
    }

    private function sourceLabel(SupportAgentHandoff $handoff): string
    {
        if ($handoff->requested_by_type === 'human') {
            return $handoff->requester?->name
                ? 'Support humain — '.$handoff->requester->name
                : 'Support humain';
        }

        return $handoff->aiAgent?->name ?: 'Support IA';
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'mobile' => 'Application mobile',
            'phone', 'call', 'voice' => 'Téléphone',
            'email' => 'E-mail',
            'web' => 'Site web',
            'chat' => 'Chat',
            null, '' => '—',
            default => $this->labelFromCode($channel),
        };
    }

    private function caseType(SupportAgentHandoff $handoff): string
    {
        $conversation = $handoff->conversation;
        $ticket = $handoff->ticket ?: $conversation?->ticket;

        if ($conversation?->return_id || $ticket?->return_id) {
            return 'Retour';
        }
        if ($conversation?->dispute_id || $ticket?->dispute_id) {
            return 'Litige';
        }
        if ($conversation?->payment_id || $ticket?->payment_id) {
            return 'Paiement';
        }
        if ($handoff->delivery_incident_id || $conversation?->delivery_incident_id || $ticket?->delivery_incident_id) {
            return 'Incident logistique';
        }
        if ($conversation?->shipment_id || $ticket?->shipment_id) {
            return 'Livraison';
        }
        if ($conversation?->order_id || $ticket?->order_id) {
            return 'Commande';
        }

        return 'Assistance logistique';
    }

    private function priorityLabel(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critique',
            'urgent', 'high' => 'Haute',
            'low' => 'Basse',
            default => 'Moyenne',
        };
    }

    private function incidentTypeLabel(string $type): string
    {
        return match ($type) {
            'produit_endommage' => 'Produit endommagé',
            'produit_incomplet' => 'Produit incomplet',
            'quantite_incorrecte' => 'Quantité incorrecte',
            'litige_client' => 'Litige client',
            'adresse_introuvable' => 'Adresse introuvable',
            'client_absent' => 'Client absent',
            'panne_vehicule' => 'Panne véhicule',
            'accident' => 'Accident',
            'refus_reception' => 'Refus de réception',
            'livraison_echouee' => 'Livraison échouée',
            'retard_important' => 'Retard important',
            default => $this->labelFromCode($type),
        };
    }

    private function deliveryStatusLabel(?string $status): string
    {
        if (! $status) {
            return '—';
        }

        $label = app(OrderWorkflowService::class)->deliveryStatusLabel($status);
        if ($label !== 'Statut non défini') {
            return $label;
        }

        return match ($status) {
            'ready' => 'Prête',
            'shipped' => 'Expédiée',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
            'failed' => 'Échec de livraison',
            'late' => 'En retard',
            'returned' => 'Retournée',
            default => $this->labelFromCode($status),
        };
    }

    private function resolutionLabel(SupportAgentHandoff $handoff): string
    {
        if (! $handoff->resolved_at && ! in_array($handoff->status, ['resolved', 'closed'], true)) {
            return 'En attente';
        }

        return match ($handoff->resolution_code) {
            'incident_resolved' => 'Incident résolu',
            'commercial_follow_up' => 'Suivi commercial transmis',
            'answered' => 'Réponse apportée',
            'redirected' => 'Redirigé vers le service compétent',
            'duplicate' => 'Dossier en doublon',
            'other' => 'Autre résolution',
            default => 'Résolu',
        };
    }

    private function slaLabel(SupportAgentHandoff $handoff): string
    {
        if (! $handoff->requested_at || ! $handoff->due_at) {
            return '—';
        }

        $minutes = max(0, $handoff->requested_at->diffInMinutes($handoff->due_at, false));
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? $hours.' h '.$remaining.' min' : $hours.' h';
    }

    private function slaRemaining(SupportAgentHandoff $handoff): ?string
    {
        if (! $handoff->due_at || ! in_array($handoff->status, self::OPEN_STATUSES, true)) {
            return null;
        }

        $minutes = now()->diffInMinutes($handoff->due_at, false);
        $late = $minutes < 0;
        $minutes = abs($minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        $duration = $hours > 0 ? $hours.' h '.($remaining ? $remaining.' min' : '') : $remaining.' min';

        return $late ? 'Dépassé de '.trim($duration) : 'Reste '.trim($duration);
    }

    private function formatAverageSeconds(Collection $seconds): string
    {
        if ($seconds->isEmpty()) {
            return '—';
        }

        $minutes = (int) round(((float) $seconds->avg()) / 60);
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? $hours.' h '.$remaining.' min' : $hours.' h';
    }

    private function labelFromCode(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return Str::ucfirst(str_replace(['_', '-'], ' ', $value));
    }

    private function ensureDepartment(Request $request, SupportAgentHandoff $handoff): void
    {
        $expected = $request->user('admin')?->role;
        abort_unless($expected && $handoff->target_department === $expected, 403, 'Ce dossier appartient à un autre service.');
    }

    private function ensureOperationalLogisticsHandoff(SupportAgentHandoff $handoff): void
    {
        if ($handoff->target_department !== 'logistique') {
            return;
        }

        abort_unless(
            SupportAgentHandoff::query()->operationalLogistics()->whereKey($handoff->id)->exists(),
            404,
            'Ce dossier de démonstration n’est pas disponible dans le module opérationnel.'
        );
    }
}
