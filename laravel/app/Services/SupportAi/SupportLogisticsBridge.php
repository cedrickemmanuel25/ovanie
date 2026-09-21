<?php

namespace App\Services\SupportAi;

use App\Models\DeliveryIncident;
use App\Models\OrderItem;
use App\Services\OrderWorkflowService;
use App\Models\SupportConversation;
use Illuminate\Support\Str;

class SupportLogisticsBridge
{
    public function __construct(
        private readonly SupportTicketFactory $tickets,
        private readonly SupportHandoffQueueService $handoffs,
    ) {}

    public function syncIncident(SupportConversation $conversation, string $message, string $priority): ?DeliveryIncident
    {
        if (! $this->isIncidentReport($message)) {
            return $conversation->deliveryIncident;
        }

        $conversation->loadMissing('shipment.order', 'order', 'aiAgent');
        $order = $conversation->order ?: $conversation->shipment?->order;
        $shipment = $conversation->shipment;

        if (! $order && ! $shipment) {
            return null;
        }

        $activeStatuses = [
            OrderWorkflowService::DELIVERY_ASSIGNED,
            OrderWorkflowService::DELIVERY_PICKED_UP,
            OrderWorkflowService::DELIVERY_IN_TRANSIT,
            OrderWorkflowService::DELIVERY_LATE,
        ];

        $item = OrderItem::query()
            ->where('order_id', $order?->id)
            ->where('delivery_provider', OrderWorkflowService::PROVIDER_OVANIE)
            ->whereIn('delivery_status', $activeStatuses)
            ->when($shipment?->order_item_id, fn ($q) => $q->whereKey($shipment->order_item_id))
            ->latest('id')
            ->first();

        // Un message Support concernant une commande qui n'est plus en livraison
        // reste un dossier Support, mais ne crée pas artificiellement un incident logistique.
        if (! $item) {
            return null;
        }

        $incidentType = $this->incidentType($message);
        $incident = DeliveryIncident::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where('incident_type', $incidentType)
            ->where('order_item_id', $item->id)
            ->latest('id')
            ->first();

        if (! $incident) {
            $impactLevel = match ($incidentType) {
                'panne_vehicule', 'accident', 'produit_endommage', 'livraison_echouee', 'refus_reception' => 'blocked',
                'client_absent' => 'rescheduled',
                default => 'delay',
            };
            $impactLabel = match ($impactLevel) {
                'blocked' => 'Livraison interrompue',
                'rescheduled' => 'Livraison à reprogrammer',
                default => 'Retard probable',
            };

            $conversation->loadMissing('requester');
            $incident = DeliveryIncident::create([
                'order_id' => $order?->id,
                'order_item_id' => $item->id,
                'shipment_id' => $item->shipment_id ?: $shipment?->id,
                'reported_by_type' => 'support_ai',
                'reported_by_id' => $conversation->requester?->id,
                'incident_type' => $incidentType,
                'severity' => $this->severity($priority, $message),
                'responsibility' => 'unknown',
                'description' => trim($message),
                'occurred_at' => now(),
                'status' => $impactLevel === 'rescheduled' ? 'rescheduled' : 'open',
                'next_action' => 'Analyse requise par le centre logistique depuis l’assistance client.',
                'meta' => [
                    'signal_source' => 'support_transfer',
                    'signal_source_label' => 'Transfert de l’assistance client',
                    'source_actor_name' => $conversation->requester?->name ?: 'Client / demandeur Support',
                    'source_actor_phone' => $conversation->requester?->phone,
                    'source_received_at' => now()->toIso8601String(),
                    'delivery_status_before_incident' => $item->delivery_status,
                    'impact_level' => $impactLevel,
                    'impact_label' => $impactLabel,
                    'delivery_interrupted' => $impactLevel !== 'delay',
                    'customer_notification_required' => false,
                    'recorded_by_name' => 'Assistance client OVANIE',
                ],
            ]);
        }

        $conversation->forceFill([
            'delivery_incident_id' => $incident->id,
            'order_id' => $conversation->order_id ?: $order?->id,
            'shipment_id' => $conversation->shipment_id ?: $shipment?->id,
        ])->save();

        $ticket = $this->tickets->fromConversation(
            $conversation->fresh(['aiAgent', 'ticket']),
            "Incident de livraison transmis automatiquement à la Logistique.\n\n".$message,
            $priority,
            [
                'team' => 'logistique',
                'category' => 'delivery_incident',
                'delivery_incident_id' => $incident->id,
            ],
        );

        $this->handoffs->enqueue(
            $conversation->fresh(),
            'logistique',
            'Incident de livraison signalé par le demandeur : '.$message,
            $incident->severity === 'critical' ? 'urgent' : ($incident->severity === 'high' ? 'high' : 'normal'),
            ticket: $ticket,
            incident: $incident,
        );

        return $incident->fresh(['order', 'shipment']);
    }

    private function isIncidentReport(string $message): bool
    {
        $normalized = Str::lower(Str::ascii($message));
        foreach (config('support_ai.delivery_incident_keywords', []) as $keyword) {
            if ($keyword !== '' && Str::contains($normalized, Str::lower(Str::ascii((string) $keyword)))) {
                return true;
            }
        }

        return false;
    }

    private function incidentType(string $message): string
    {
        $normalized = Str::lower(Str::ascii($message));

        return match (true) {
            Str::contains($normalized, ['endommage', 'casse', 'cassé']) => 'produit_endommage',
            Str::contains($normalized, ['incomplet', 'manquant']) => 'produit_incomplet',
            Str::contains($normalized, ['quantite incorrecte', 'quantité incorrecte', 'mauvaise quantite', 'mauvaise quantité']) => 'quantite_incorrecte',
            Str::contains($normalized, ['mauvais produit', 'produit different', 'produit différent', 'non conforme', 'probleme de commande', 'problème de commande', 'commande incorrecte']) => 'litige_client',
            Str::contains($normalized, ['adresse introuvable', 'mauvaise adresse']) => 'adresse_introuvable',
            Str::contains($normalized, ['absent', 'pas sur place']) => 'client_absent',
            Str::contains($normalized, ['panne']) => 'panne_vehicule',
            Str::contains($normalized, ['accident', 'danger']) => 'accident',
            Str::contains($normalized, ['refus', 'refuse']) => 'refus_reception',
            Str::contains($normalized, ['echou', 'échou']) => 'livraison_echouee',
            default => 'retard_important',
        };
    }

    private function severity(string $priority, string $message): string
    {
        $normalized = Str::lower(Str::ascii($message));
        if ($priority === 'urgent' || Str::contains($normalized, ['accident', 'danger', 'agression'])) {
            return 'critical';
        }
        if ($priority === 'high' || Str::contains($normalized, ['endommage', 'echou', 'échou', 'retard important'])) {
            return 'high';
        }

        return 'medium';
    }
}
