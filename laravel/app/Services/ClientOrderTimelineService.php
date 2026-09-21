<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientOrderTimelineService
{
    public function build(Order $order): Collection
    {
        $order->loadMissing([
            'items.statusHistories',
            'shipments.statusHistories',
            'payments',
            'returns',
        ]);

        $events = collect([
            $this->event(
                'Commande enregistrée',
                'Votre commande a été enregistrée par OVANIE.',
                $order->created_at,
                'order',
                'order:' . $order->id . ':created',
                null,
                'success'
            ),
        ]);

        $shipmentItemIds = $order->shipments
            ->filter(fn ($shipment) => $shipment->statusHistories->isNotEmpty())
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $hasShipmentHistories = $order->shipments->contains(
            fn ($shipment) => $shipment->statusHistories->isNotEmpty()
        );

        foreach ($order->payments as $payment) {
            if ($payment->isPaid()) {
                [$paymentLabel, $paymentMessage] = $this->paymentConfirmedPresentation(
                    $payment->type,
                    $payment->method
                );

                $events->push($this->event(
                    $paymentLabel,
                    $paymentMessage,
                    $payment->paid_at ?: $payment->updated_at,
                    'payment',
                    'payment:' . $payment->id . ':paid',
                    null,
                    'success'
                ));
            } elseif ($payment->isFailed()) {
                $events->push($this->event(
                    'Paiement échoué',
                    'Le paiement n’a pas pu être confirmé.',
                    $payment->failed_at ?: $payment->updated_at,
                    'payment_problem',
                    'payment:' . $payment->id . ':failed',
                    null,
                    'error'
                ));
            } elseif ($payment->isCancelled()) {
                $events->push($this->event(
                    'Paiement annulé',
                    'Le paiement a été annulé.',
                    $payment->updated_at,
                    'payment_problem',
                    'payment:' . $payment->id . ':cancelled',
                    null,
                    'neutral'
                ));
            }
        }

        // Les historiques globaux excluent explicitement les lignes article. Sans
        // cette contrainte, la relation Order::statusHistories contient aussi les
        // événements déjà parcourus via items.statusHistories et crée des doublons.
        $globalHistories = $order->statusHistories()
            ->whereNull('order_item_id')
            ->orderBy('created_at')
            ->get();

        foreach ($globalHistories as $history) {
            if ($history->status_type === 'delivery' && $hasShipmentHistories) {
                continue;
            }

            $mapped = $this->mapHistory(
                $history->status_type,
                $history->new_status,
                $history->label,
                $history->message,
                $history->created_at,
                'order_history:' . $history->id
            );

            if ($mapped) {
                $events->push($mapped);
            }
        }

        foreach ($order->items as $item) {
            foreach ($item->statusHistories as $history) {
                if ($history->status_type === 'delivery' && $shipmentItemIds->contains((int) $item->id)) {
                    continue;
                }

                $mapped = $this->mapHistory(
                    $history->status_type,
                    $history->new_status,
                    $history->label,
                    $history->message,
                    $history->created_at,
                    'item_history:' . $item->id . ':' . $history->id,
                    (int) $item->id
                );

                if ($mapped) {
                    $events->push($mapped);
                }
            }
        }

        foreach ($order->shipments as $shipment) {
            foreach ($shipment->statusHistories as $history) {
                $events->push($this->event(
                    $this->publicDeliveryLabel($history->status),
                    $this->publicDeliveryMessage($history->status),
                    $history->created_at,
                    'delivery',
                    'shipment_history:' . $shipment->id . ':' . $history->id,
                    $shipment->order_item_id ? (int) $shipment->order_item_id : null,
                    $this->stateFor('delivery', $history->status),
                    'shipment:' . $shipment->id
                ));
            }
        }

        foreach ($order->returns as $return) {
            if ($this->hasHistoryForReturnState($events, $return)) {
                continue;
            }

            $events->push($this->returnEvent($return));
        }

        return $events
            ->filter(fn (array $event) => $event['date'] !== null)
            ->sortBy(fn (array $event) => $event['date']->getTimestamp())
            ->unique('event_key')
            // Les événements livraison peuvent être écrits dans l'historique de
            // commande et dans l'historique d'expédition. La clé sémantique retire
            // ces doublons publics sans supprimer des étapes réellement distinctes.
            ->unique('semantic_key')
            ->values();
    }

    private function returnEvent(ReturnModel $return): array
    {
        $status = (string) $return->status;
        $type = (string) ($return->return_type ?: 'return');

        [$label, $message] = match ($type) {
            'claim' => match ($status) {
                ReturnModel::STATUS_ACCEPTED => ['Réclamation prise en charge', 'Votre réclamation est en cours d’analyse.'],
                ReturnModel::STATUS_REJECTED => ['Réclamation refusée', 'Votre réclamation a été refusée.'],
                ReturnModel::STATUS_CLOSED, 'resolved' => ['Réclamation résolue', 'Le traitement de votre réclamation est terminé.'],
                ReturnModel::STATUS_REFUNDED => ['Remboursement effectué', 'Le remboursement décidé à la suite de votre réclamation a été enregistré.'],
                default => ['Réclamation enregistrée', 'Votre réclamation a été enregistrée et est en cours de traitement.'],
            },
            'refund' => match ($status) {
                ReturnModel::STATUS_ACCEPTED => ['Demande de remboursement acceptée', 'Votre demande de remboursement est en cours de traitement financier.'],
                ReturnModel::STATUS_REJECTED => ['Demande de remboursement refusée', 'Votre demande de remboursement a été refusée.'],
                ReturnModel::STATUS_CLOSED, 'resolved' => ['Dossier de remboursement clôturé', 'Le traitement de votre demande de remboursement est terminé.'],
                ReturnModel::STATUS_REFUNDED => ['Remboursement effectué', 'Votre remboursement a été enregistré.'],
                default => ['Demande de remboursement enregistrée', 'Votre demande de remboursement a été enregistrée et est en cours d’analyse.'],
            },
            default => match ($status) {
                ReturnModel::STATUS_ACCEPTED => ['Retour accepté', 'Votre demande de retour a été acceptée.'],
                ReturnModel::STATUS_REJECTED => ['Retour refusé', 'Votre demande de retour a été refusée.'],
                ReturnModel::STATUS_CLOSED, 'resolved' => ['Retour clôturé', 'Le traitement de votre retour est terminé.'],
                ReturnModel::STATUS_REFUNDED => ['Remboursement effectué', 'Le remboursement associé à votre retour a été enregistré.'],
                default => ['Demande de retour enregistrée', 'Votre demande de retour est en cours de traitement.'],
            },
        };

        [$date, $stateName, $visualState] = match ($status) {
            ReturnModel::STATUS_ACCEPTED => [$return->accepted_at ?: $return->updated_at ?: $return->created_at, 'accepted', 'success'],
            ReturnModel::STATUS_REJECTED => [$return->rejected_at ?: $return->resolved_at ?: $return->updated_at ?: $return->created_at, 'rejected', 'error'],
            ReturnModel::STATUS_REFUNDED => [$return->refunded_at ?: $return->resolved_at ?: $return->updated_at, 'refunded', 'success'],
            ReturnModel::STATUS_CLOSED, 'resolved' => [$return->resolved_at ?: $return->updated_at ?: $return->created_at, 'resolved', 'success'],
            default => [$return->created_at, 'requested', 'pending'],
        };

        return $this->event(
            $label,
            $message,
            $date,
            'return',
            'return:' . $return->id . ':' . $stateName,
            $return->order_item_id ? (int) $return->order_item_id : null,
            $visualState
        );
    }

    private function hasHistoryForReturnState(Collection $events, ReturnModel $return): bool
    {
        $acceptedLabels = match ($return->status) {
            ReturnModel::STATUS_ACCEPTED => ['retour accepté', 'demande acceptée', 'réclamation prise en charge', 'demande de remboursement en analyse'],
            ReturnModel::STATUS_REJECTED => ['retour refusé', 'retour rejeté', 'retour rejeté après contrôle', 'demande refusée', 'réclamation refusée'],
            ReturnModel::STATUS_REFUNDED => ['remboursement confirmé', 'remboursement effectué'],
            ReturnModel::STATUS_CLOSED, 'resolved' => ['demande résolue', 'réclamation résolue', 'dossier de remboursement clôturé', 'retour clôturé'],
            default => [],
        };

        if ($acceptedLabels === []) {
            return false;
        }

        return $events->contains(function (array $event) use ($acceptedLabels, $return) {
            $type = (string) ($event['type'] ?? '');
            if (! in_array($type, ['return', 'return_logistics', 'return_refund'], true)) {
                return false;
            }

            $eventItemId = $event['order_item_id'] ?? null;
            if ($eventItemId !== null && (int) $eventItemId !== (int) $return->order_item_id) {
                return false;
            }

            $label = mb_strtolower(trim((string) ($event['label'] ?? '')));

            return in_array($label, $acceptedLabels, true);
        });
    }

    private function mapHistory(
        ?string $type,
        ?string $status,
        ?string $label,
        ?string $message,
        $date,
        string $eventKey,
        ?int $orderItemId = null
    ): ?array {
        if (! in_array($type, ['delivery', 'return', 'return_logistics', 'return_refund', 'reception', 'payment', 'order'], true)) {
            return null;
        }

        return $this->event(
            $this->publicHistoryLabel($type, $status),
            $this->publicHistoryMessage($type, $status),
            $date,
            $type,
            $eventKey,
            $orderItemId,
            $this->stateFor($type, $status, $label)
        );
    }

    private function paymentConfirmedPresentation(?string $type, ?string $method): array
    {
        return match ($type) {
            'commission_payment' => [
                'Frais initiaux confirmés',
                'Les frais initiaux OVANIE ont été confirmés. Le solde reste séparé du paiement initial.',
            ],
            'order_line_payment', 'item_payment' => [
                'Paiement d’un article confirmé',
                'Le paiement d’un article de la commande a été confirmé de manière sécurisée.',
            ],
            'order_lines_payment' => [
                'Solde de commande confirmé',
                'Le paiement du solde des articles concernés a été confirmé de manière sécurisée.',
            ],
            'return_refund' => [
                'Remboursement enregistré',
                'Un remboursement lié à la commande a été enregistré.',
            ],
            default => [
                'Paiement de la commande confirmé',
                'Le paiement de la commande a été confirmé de manière sécurisée.',
            ],
        };
    }

    private function publicHistoryLabel(?string $type, ?string $status): string
    {
        return match ($type) {
            'delivery' => $this->publicDeliveryLabel($status),
            'payment' => match ($status) {
                'commission_paid' => 'Frais initiaux confirmés',
                'partial' => 'Paiement partiel confirmé',
                'paid' => 'Paiement confirmé',
                'cancelled' => 'Paiement annulé',
                'failed' => 'Paiement échoué',
                default => 'Mise à jour du paiement',
            },
            'reception' => 'Réception confirmée',
            'return_refund' => match ($status) {
                'refund_pending' => 'Remboursement en préparation',
                'refunded' => 'Remboursement confirmé',
                default => 'Mise à jour du remboursement',
            },
            'return_logistics' => match ($status) {
                'return_pickup_planned' => 'Collecte du retour planifiée',
                'return_in_transit' => 'Retour en cours d’acheminement',
                'return_received' => 'Retour reçu et contrôlé',
                default => 'Mise à jour du retour',
            },
            'return' => match ($status) {
                'accepted' => 'Demande acceptée',
                'rejected' => 'Demande refusée',
                'closed', 'resolved' => 'Demande résolue',
                'refunded' => 'Remboursement effectué',
                default => 'Demande enregistrée',
            },
            'order' => $status === 'cancelled' ? 'Commande annulée' : 'Mise à jour de la commande',
            default => 'Mise à jour',
        };
    }

    private function publicHistoryMessage(?string $type, ?string $status): string
    {
        return match ($type) {
            'delivery' => $this->publicDeliveryMessage($status),
            'payment' => match ($status) {
                'commission_paid' => 'Les frais initiaux OVANIE ont été confirmés.',
                'partial' => 'Un paiement partiel a été confirmé sur la commande.',
                'paid' => 'Le paiement a été confirmé de manière sécurisée.',
                'cancelled' => 'Le paiement a été annulé.',
                'failed' => 'Le paiement n’a pas pu être confirmé.',
                default => 'Le statut du paiement a été mis à jour.',
            },
            'reception' => 'La réception des articles concernés a été confirmée.',
            'return_refund' => match ($status) {
                'refund_pending' => 'Le remboursement est en cours de préparation.',
                'refunded' => 'Le remboursement exécuté a été enregistré.',
                default => 'Le dossier de remboursement a été mis à jour.',
            },
            'return_logistics' => match ($status) {
                'return_pickup_planned' => 'La collecte du retour a été planifiée.',
                'return_in_transit' => 'Le retour est en cours d’acheminement vers le point de contrôle.',
                'return_received' => 'Le retour a été reçu et contrôlé.',
                default => 'Le traitement du retour a été mis à jour.',
            },
            'return' => match ($status) {
                'accepted' => 'Votre demande a été acceptée et suit le workflow approprié.',
                'rejected' => 'Votre demande a été refusée.',
                'closed', 'resolved' => 'Le traitement de votre demande est terminé.',
                'refunded' => 'Le remboursement associé à votre demande a été enregistré.',
                default => 'Votre demande a été enregistrée et est en cours de traitement.',
            },
            'order' => $status === 'cancelled'
                ? 'La commande a été annulée.'
                : 'Le statut de votre commande a été mis à jour.',
            default => 'Une nouvelle étape a été enregistrée sur votre commande.',
        };
    }

    private function publicDeliveryLabel(?string $status, ?string $fallback = null): string
    {
        return match ($status) {
            'preparing' => 'Préparation de la commande',
            'ready_for_pickup' => 'Commande prête pour prise en charge',
            'assigned' => 'Livraison planifiée',
            'picked_up' => 'Commande prise en charge',
            'late' => 'Livraison retardée',
            'in_transit', 'in_delivery' => 'Commande en livraison',
            'delivered', 'completed' => 'Commande livrée',
            'delivery_failed', 'failed', 'problem' => 'Incident de livraison',
            'cancelled' => 'Livraison annulée',
            'returned' => 'Commande retournée',
            'not_required' => 'Retrait sans livraison',
            default => $fallback ?: 'Mise à jour de la livraison',
        };
    }

    private function publicDeliveryMessage(?string $status): string
    {
        return match ($status) {
            'preparing' => 'Vos articles sont en préparation.',
            'ready_for_pickup' => 'La commande est prête pour la prochaine étape de livraison.',
            'assigned' => 'La livraison est planifiée par OVANIE.',
            'picked_up' => 'La commande a été prise en charge pour la livraison.',
            'late' => 'La livraison prend du retard. OVANIE suit la situation et le statut sera actualisé.',
            'in_transit', 'in_delivery' => 'Votre commande est en route vers l’adresse de livraison.',
            'delivered', 'completed' => 'La commande a été remise au destinataire.',
            'delivery_failed', 'failed', 'problem' => 'Un incident de livraison a été signalé et est en cours de traitement.',
            'returned' => 'La commande a été retournée dans le cadre du processus de retour.',
            'not_required' => 'Aucune livraison à domicile n’est requise pour cette étape.',
            default => 'Le statut de livraison a été mis à jour.',
        };
    }

    private function fallbackLabel(?string $type, ?string $status): string
    {
        return match ($type) {
            'payment' => 'Mise à jour du paiement',
            'reception' => 'Réception confirmée',
            'return', 'return_logistics', 'return_refund' => 'Mise à jour du retour',
            'order' => $status === 'cancelled' ? 'Commande annulée' : 'Mise à jour de la commande',
            default => 'Mise à jour',
        };
    }

    private function fallbackMessage(?string $type, ?string $status): string
    {
        return $type === 'order' && $status === 'cancelled'
            ? 'La commande a été annulée.'
            : 'Une nouvelle étape a été enregistrée sur votre commande.';
    }

    private function stateFor(?string $type, ?string $status, ?string $label = null): string
    {
        $status = Str::lower((string) $status);
        $label = Str::lower(Str::ascii((string) $label));

        if (in_array($status, ['failed', 'delivery_failed', 'problem', 'rejected'], true)
            || Str::contains($label, ['echoue', 'refuse', 'rejete', 'incident'])) {
            return 'error';
        }

        if ($status === 'late' || Str::contains($label, ['retard', 'attention'])) {
            return 'warning';
        }

        if (in_array($status, ['pending', 'requested', 'refund_pending', 'pending_pickup'], true)) {
            return 'pending';
        }

        if (in_array($status, ['processing', 'preparing', 'assigned', 'picked_up', 'in_transit', 'in_delivery', 'return_in_transit'], true)) {
            return 'active';
        }

        if ($status === 'cancelled') {
            return 'neutral';
        }

        return 'success';
    }

    private function event(
        string $label,
        string $message,
        $date,
        string $type,
        string $eventKey,
        ?int $orderItemId = null,
        string $state = 'success',
        ?string $semanticScope = null
    ): array {
        $minute = $date?->format('YmdHi') ?? 'nodate';
        $semanticType = $type === 'delivery' ? 'delivery' : $type;
        $semanticItemId = $semanticScope ?: ($orderItemId ?: 0);
        $semanticLabel = Str::lower(Str::ascii(trim($label)));

        return [
            'label' => $label,
            'message' => $message,
            'date' => $date,
            'type' => $type,
            'state' => $state,
            'event_key' => $eventKey,
            'semantic_key' => implode(':', [
                $semanticType,
                $semanticItemId,
                $semanticLabel,
                $minute,
            ]),
            'order_item_id' => $orderItemId,
        ];
    }
}
