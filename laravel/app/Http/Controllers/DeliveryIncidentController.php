<?php

namespace App\Http\Controllers;

use App\Models\DeliveryIncident;
use App\Models\OrderItem;
use App\Notifications\Delivery\IncidentVendorNotification;
use App\Services\DeliveryNotificationService;
use App\Services\OrderWorkflowService;
use App\Services\SmsService;
use App\Services\SupportAi\SupportHandoffQueueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryIncidentController extends Controller
{
    private const TYPES = [
        'client_absent' => 'Client absent',
        'client_introuvable' => 'Client introuvable',
        'adresse_introuvable' => 'Adresse introuvable',
        'adresse_incorrecte' => 'Adresse incorrecte',
        'vendeur_pas_pret' => 'Boutique non prête',
        'point_vente_pas_pret' => 'Boutique non prête',
        'produit_endommage' => 'Produit endommagé',
        'produit_incomplet' => 'Produit incomplet',
        'quantite_incorrecte' => 'Quantité incorrecte',
        'acces_chantier_difficile' => 'Accès chantier difficile',
        'panne_vehicule' => 'Panne véhicule',
        'accident' => 'Accident',
        'litige_client' => 'Litige client',
        'probleme_chargement' => 'Problème de chargement',
        'blocage_route' => 'Blocage route',
        'probleme_securite' => 'Problème sécurité',
        'colis_perdu' => 'Colis perdu',
        'probleme_carburant' => 'Problème carburant',
        'retard_important' => 'Retard important',
        'retard_livraison' => 'Retard de livraison',
        'refus_reception' => 'Refus de réception',
        'otp_impossible' => 'Validation OTP impossible',
        'livraison_reportee' => 'Livraison reportée',
        'livraison_echouee' => 'Livraison échouée',
        'retour_point_vente' => 'Retour au point de vente',
        'autre_incident' => 'Autre incident',
    ];

    private const SIGNAL_SOURCES = [
        'ovanie_driver_app' => 'Application du livreur OVANIE',
        'seller_driver_app' => 'Application du chauffeur vendeur',
        'support_transfer' => 'Transfert de l’assistance client',
        'system_alert' => 'Alerte automatique / GPS',
        'driver_phone' => 'Livreur — appel / WhatsApp',
        'client_support' => 'Client — assistance / téléphone',
        'shop_contact' => 'Boutique — appel / WhatsApp',
        'operations_control' => 'Contrôle du centre logistique',
    ];

    /**
     * Impact opérationnel constaté sur la livraison. Ce champ pilote la
     * notification client et évite de bloquer une mission pour un incident
     * qui n'empêche pas réellement la livraison de continuer.
     */
    private const IMPACT_LEVELS = [
        'none' => 'Aucun impact confirmé',
        'delay' => 'Retard probable',
        'blocked' => 'Livraison interrompue',
        'rescheduled' => 'Livraison à reprogrammer',
    ];

    /** Incidents qui interrompent la mission même si l'opérateur sous-estime l'impact. */
    private const BLOCKING_TYPES = [
        'panne_vehicule',
        'accident',
        'probleme_securite',
        'colis_perdu',
        'livraison_echouee',
        'retour_point_vente',
    ];

    public function index(Request $request)
    {
        $baseIncidents = $this->incomingIncidentQuery();

        $query = (clone $baseIncidents)
            ->with([
                'order.client',
                'shipment',
                'orderItem.product.shop',
                'orderItem.latestDeliveryAssignment.driver',
                'orderItem.latestDeliveryAssignment.latestLocation',
            ])
            ->withCount(['supportConversations', 'supportHandoffs']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        if ($type = $request->query('type')) {
            $query->where('incident_type', $type);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('occurred_at', $date);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($search) {
                $numericIncidentId = preg_replace('/^INC-0*/i', '', $search);
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('incident_type', 'like', "%{$search}%")
                    ->orWhere('id', ctype_digit($numericIncidentId) ? (int) $numericIncidentId : -1)
                    ->orWhereHas('orderItem.latestDeliveryAssignment.driver', function (Builder $driver) use ($search) {
                        $driver->where('name', 'like', "%{$search}%")
                            ->orWhere('vehicle', 'like', "%{$search}%");
                    })
                    ->orWhereHas('orderItem.latestDeliveryAssignment', function (Builder $assignment) use ($search) {
                        $assignment->where('mission_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order', fn (Builder $order) => $order->where('order_number', 'like', "%{$search}%"));
            });
        }

        $allIncidents = (clone $baseIncidents)
            ->latest('occurred_at')
            ->latest('id')
            ->get();

        return view('logistics.incidents.index', [
            'incidents' => $query
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(max(1, min(100, (int) $request->query('per_page', 10))))
                ->withQueryString(),
            'allIncidents' => $allIncidents,
            'types' => self::TYPES,
            'impactLevels' => self::IMPACT_LEVELS,
            'stats' => [
                'open' => (clone $baseIncidents)->whereIn('status', ['open', 'in_progress', 'rescheduled'])->count(),
                'critical' => (clone $baseIncidents)->where('severity', 'critical')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved_today' => (clone $baseIncidents)->whereIn('status', ['resolved', 'closed'])->whereDate('resolved_at', today())->count(),
            ],
        ]);
    }

    public function create(OrderItem $item)
    {
        return redirect()
            ->route('logistics.incidents.index')
            ->with('info', 'Les incidents sont créés depuis les signalements réels des livreurs, des vendeurs ou de l’assistance client.');
    }

    public function store(Request $request, OrderItem $item)
    {
        abort(405, 'La création manuelle d’un incident par la Logistique est désactivée.');
    }

    public function show(DeliveryIncident $incident)
    {
        $this->ensureRealIncident($incident);

        return view('logistics.incidents.show', [
            'incident' => $incident->load([
                'order.client',
                'shipment',
                'orderItem.product.shop',
                'orderItem.latestDeliveryAssignment.driver',
                'orderItem.latestDeliveryAssignment.latestLocation',
                'supportConversations.requester',
                'supportConversations.aiAgent',
                'supportConversations.ticket.assignee',
                'supportHandoffs.assignee',
                'supportHandoffs.aiAgent',
                'supportHandoffs.ticket.assignee',
            ]),
            'types' => self::TYPES,
            'signalSources' => self::SIGNAL_SOURCES,
            'impactLevels' => self::IMPACT_LEVELS,
        ]);
    }

    public function update(Request $request, DeliveryIncident $incident, SupportHandoffQueueService $queues)
    {
        $this->ensureRealIncident($incident);

        $incidentAction = (string) $request->input('incident_action', '');
        if ($incidentAction === 'notify_client') {
            return $this->notifyClientFromIncident($incident);
        }

        if ($incidentAction === 'forward_vendor') {
            return $this->forwardIncidentToVendor($incident);
        }

        if ($request->has('note')) {
            $request->validate(['note' => 'required|string|max:1000']);
            $meta = $incident->meta ?? [];
            $meta['notes'][] = [
                'text' => $request->note,
                'author' => $request->user('admin')?->name ?? 'Responsable Logistique',
                'at' => now()->toIso8601String(),
            ];
            $incident->update(['meta' => $meta]);

            return back()->with('success', 'Note ajoutée.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,rescheduled,resolved,closed'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'rescheduled_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($incident, $data, $request) {
            $lockedIncident = DeliveryIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $closing = in_array($data['status'], ['resolved', 'closed'], true);

            $lockedIncident->forceFill($data + [
                'resolved_by' => $closing ? ($request->user('admin')?->id ?? $request->user()?->id) : $lockedIncident->resolved_by,
                'resolved_at' => $closing ? ($lockedIncident->resolved_at ?: now()) : null,
            ])->save();

            if ($closing && $lockedIncident->orderItem?->delivery_status === OrderWorkflowService::DELIVERY_FAILED) {
                $item = $lockedIncident->orderItem;
                $previousStatus = data_get($lockedIncident->meta, 'delivery_status_before_incident');
                $resumableStatuses = [
                    OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                    OrderWorkflowService::DELIVERY_ASSIGNED,
                    OrderWorkflowService::DELIVERY_PICKED_UP,
                    OrderWorkflowService::DELIVERY_IN_TRANSIT,
                    OrderWorkflowService::DELIVERY_LATE,
                ];

                $resumeStatus = in_array($previousStatus, $resumableStatuses, true)
                    ? $previousStatus
                    : ($item->latestDeliveryAssignment?->driver_id
                        ? OrderWorkflowService::DELIVERY_ASSIGNED
                        : OrderWorkflowService::DELIVERY_READY_FOR_PICKUP);

                app(OrderWorkflowService::class)->setDeliveryStatus(
                    $item,
                    $resumeStatus,
                    $request->user('admin') ?: $request->user(),
                    'admin',
                    'Incident résolu. La mission reprend à son dernier état opérationnel réel.',
                    [
                        'incident_id' => $lockedIncident->id,
                        'suppress_notifications' => true,
                    ]
                );
            }
        });

        $incident->refresh();
        if (in_array($incident->status, ['resolved', 'closed'], true)) {
            foreach ($incident->supportHandoffs()->open()->where('target_department', 'logistique')->get() as $handoff) {
                $queues->resolve(
                    $handoff,
                    $request->user('admin') ?: $request->user(),
                    $data['resolution_note'] ?? 'Incident logistique résolu.',
                    'incident_resolved',
                );
            }
        }

        return back()->with('success', 'Incident mis à jour et file Support IA synchronisée.');
    }

    private function incomingIncidentQuery(): Builder
    {
        return DeliveryIncident::query()
            ->operationalReal()
            ->where(function (Builder $query) {
                $query->whereIn('reported_by_type', [
                    'ovanie_driver',
                    'driver',
                    'delivery_driver',
                    'seller_driver',
                    'seller',
                    'vendor',
                    'support_ai',
                    'client',
                    'customer',
                ])->orWhereIn('meta->signal_source', [
                    'ovanie_driver_app',
                    'seller_driver_app',
                    'support_transfer',
                    'client_app',
                    'seller_app',
                    'vendor_app',
                ]);
            });
    }

    private function notifyClientFromIncident(DeliveryIncident $incident)
    {
        $incident->loadMissing(['order.client', 'orderItem.product.shop', 'orderItem.latestDeliveryAssignment.driver']);
        $item = $incident->orderItem;

        abort_unless($item, 409, 'Aucune livraison réelle n’est rattachée à cet incident.');

        $meta = $incident->meta ?? [];
        if (data_get($meta, 'customer_notification_sent_at') || data_get($meta, 'customer_notification_requested_at')) {
            return back()->with('info', 'Le client a déjà été informé de cet incident.');
        }

        $typeLabel = self::TYPES[$incident->incident_type] ?? 'incident opérationnel';
        $message = match ($incident->incident_type) {
            'panne_vehicule', 'probleme_carburant' => 'Un incident concernant le véhicule du livreur peut entraîner un retard de votre livraison. OVANIE Logistics suit la situation et organise la continuité de la mission.',
            'accident', 'probleme_securite' => 'Un incident opérationnel affecte temporairement votre livraison. OVANIE Logistics sécurise la mission et vous informera de sa reprise.',
            'blocage_route', 'acces_chantier_difficile', 'retard_important', 'retard_livraison', 'adresse_introuvable' => 'Votre livraison peut subir un retard en raison d’un aléa sur le trajet. OVANIE Logistics suit la mission et mettra à jour l’heure estimée.',
            default => 'Un incident opérationnel peut affecter le délai de votre livraison. OVANIE Logistics suit la situation et vous informera de la suite.',
        };

        $order = $incident->order;
        $clientUser = $order?->client;
        $recipientPhone = $order?->delivery_recipient_phone ?: $order?->phone ?: $clientUser?->phone;

        if ($clientUser) {
            app(DeliveryNotificationService::class)->notifyGroup(
                $item,
                'incident-client-' . $incident->id,
                'Mise à jour de votre livraison',
                $message,
                $message,
            );
        } elseif ($recipientPhone) {
            abort_unless(SmsService::send($recipientPhone, $message) !== false, 503, 'La notification client n’a pas pu être envoyée : aucun canal de notification n’est disponible.');
        } else {
            abort(409, 'Aucun compte client ni numéro de téléphone n’est disponible pour cette livraison.');
        }

        $meta['customer_notification_required'] = true;
        $meta['customer_notification_sent_at'] = now()->toIso8601String();
        $meta['customer_notification_message'] = $message;
        $meta['customer_notification_reason'] = $typeLabel;
        $incident->forceFill([
            'meta' => $meta,
            'notified_at' => now(),
            'status' => $incident->status === 'open' ? 'in_progress' : $incident->status,
        ])->save();

        return back()->with('success', 'Le client a été informé que sa livraison peut être affectée par cet incident.');
    }

    private function forwardIncidentToVendor(DeliveryIncident $incident)
    {
        $incident->loadMissing(['order', 'orderItem.product.shop.user']);
        $item = $incident->orderItem;
        $vendorUser = $item?->product?->shop?->user;

        abort_unless($item && $vendorUser, 409, 'Aucun vendeur réel n’est disponible pour cette commande.');

        $meta = $incident->meta ?? [];
        if (data_get($meta, 'vendor_notification_sent_at')) {
            return back()->with('info', 'Le vendeur a déjà reçu ce dossier incident.');
        }

        $typeLabel = self::TYPES[$incident->incident_type] ?? ucfirst(str_replace('_', ' ', (string) $incident->incident_type));
        $orderNumber = $incident->order?->order_number ?: ('Commande #' . $incident->order_id);
        $message = sprintf(
            'Un client a signalé « %s » sur %s. Merci de vérifier la commande et de transmettre votre retour à OVANIE.',
            mb_strtolower($typeLabel),
            $orderNumber,
        );

        app(DeliveryNotificationService::class)->notifyUser(
            $vendorUser,
            new IncidentVendorNotification($incident, $item, $message),
        );

        $meta['vendor_notification_required'] = true;
        $meta['vendor_notification_sent_at'] = now()->toIso8601String();
        $meta['vendor_notification_message'] = $message;
        $incident->forceFill([
            'meta' => $meta,
            'notified_at' => now(),
            'status' => $incident->status === 'open' ? 'in_progress' : $incident->status,
            'next_action' => $incident->next_action ?: 'Attendre le retour du vendeur sur le problème signalé par le client.',
        ])->save();

        return back()->with('success', 'Le dossier incident a été transmis au vendeur concerné.');
    }

    private function ensureRealIncident(DeliveryIncident $incident): void
    {
        abort_unless(
            DeliveryIncident::query()->operationalReal()->whereKey($incident->id)->exists(),
            404
        );
    }

}
