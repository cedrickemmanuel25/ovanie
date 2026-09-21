<?php

namespace App\Http\Resources;

use App\Models\ReturnModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $return = $this->resource;
        $return->loadMissing(['order.client', 'orderItem.product']);

        $photoPaths = array_values(array_filter((array) data_get($return->meta, 'photo_proofs', [])));
        if ($return->photo_proof && ! in_array($return->photo_proof, $photoPaths, true)) {
            array_unshift($photoPaths, $return->photo_proof);
        }
        $videoPaths = array_values(array_filter((array) data_get($return->meta, 'video_proofs', [])));
        $order = $return->order;
        $client = $order?->client;

        return [
            'id' => (int) $return->id,
            'order_id' => $return->order_id ? (int) $return->order_id : null,
            'order_number' => $order?->order_number ?: $return->order_reference,
            'order_item_id' => $return->order_item_id ? (int) $return->order_item_id : null,
            'product' => [
                'id' => $return->product_id ? (int) $return->product_id : null,
                'name' => $return->orderItem?->product?->name ?: $return->product_name ?: 'Produit OVANIE',
                'main_image_url' => $return->orderItem?->product?->main_image_url,
            ],
            'quantity' => (int) ($return->quantity ?: 1),
            'type' => $return->return_type ?: 'return',
            'type_label' => $this->typeLabel($return->return_type),
            'reason' => (string) $return->reason,
            'status' => (string) $return->status,
            'status_label' => $this->statusLabel($return->status, $return->return_type),
            'logistics_status' => $return->logistics_status,
            'logistics_status_label' => $this->logisticsLabel($return->logistics_status),
            'vendor_response' => $return->vendor_response,
            'refund_amount' => $return->refund_amount !== null ? (float) $return->refund_amount : null,
            'unit_price' => $return->orderItem?->price !== null ? (float) $return->orderItem->price : null,
            'has_photos' => count($photoPaths) > 0,
            'photo_count' => count($photoPaths),
            'video_count' => count($videoPaths),
            'order_context' => [
                'created_at' => $order?->created_at?->toIso8601String(),
                'delivery_address' => (string) ($order?->delivery_address ?: $order?->address ?: ''),
                'delivery_city' => (string) ($order?->delivery_city ?: ''),
                'delivery_commune' => (string) ($order?->delivery_commune ?: ''),
                'delivery_quartier' => (string) ($order?->delivery_quartier ?: ''),
                'recipient_name' => (string) ($order?->delivery_recipient_name ?: $order?->customer_name ?: $client?->name ?: ''),
                'recipient_phone' => (string) ($order?->delivery_recipient_phone ?: $order?->phone ?: $client?->phone ?: ''),
                'recipient_email' => (string) ($client?->email ?: ''),
            ],
            'preparation' => [
                'detailed_description' => (string) data_get($return->meta, 'detailed_description', ''),
                'discovery_date' => data_get($return->meta, 'discovery_date'),
                'storage_location' => (string) data_get($return->meta, 'storage_location', ''),
                'pickup_address' => (string) data_get($return->meta, 'pickup_address', ''),
                'pickup_contact_name' => (string) data_get($return->meta, 'pickup_contact_name', ''),
                'pickup_contact_phone' => (string) data_get($return->meta, 'pickup_contact_phone', ''),
                'pickup_contact_email' => (string) data_get($return->meta, 'pickup_contact_email', ''),
                'pickup_date' => data_get($return->meta, 'pickup_date'),
                'pickup_time_slot' => (string) data_get($return->meta, 'pickup_time_slot', ''),
            ],
            'request_date' => $return->request_date?->toDateString(),
            'accepted_at' => $return->accepted_at?->toIso8601String(),
            'rejected_at' => $return->rejected_at?->toIso8601String(),
            'refunded_at' => $return->refunded_at?->toIso8601String(),
            'resolved_at' => $return->resolved_at?->toIso8601String(),
            'created_at' => $return->created_at?->toIso8601String(),
            'updated_at' => $return->updated_at?->toIso8601String(),
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'claim' => 'Réclamation',
            'refund' => 'Remboursement',
            default => 'Retour',
        };
    }

    private function statusLabel(?string $status, ?string $type): string
    {
        if ($status === ReturnModel::STATUS_REFUNDED) {
            return 'Remboursement effectué';
        }

        return match ($status) {
            ReturnModel::STATUS_ACCEPTED => $type === 'claim' ? 'Réclamation prise en charge' : 'Demande acceptée',
            ReturnModel::STATUS_REJECTED => 'Demande refusée',
            ReturnModel::STATUS_CLOSED, 'resolved' => 'Dossier clôturé',
            'cancelled' => 'Demande annulée',
            default => 'En cours de traitement',
        };
    }

    private function logisticsLabel(?string $status): ?string
    {
        return match ($status) {
            ReturnModel::LOGISTICS_PENDING_PICKUP => 'Collecte du retour à planifier',
            ReturnModel::LOGISTICS_PICKUP_PLANNED => 'Collecte du retour planifiée',
            ReturnModel::LOGISTICS_IN_TRANSIT => 'Retour en cours d’acheminement',
            ReturnModel::LOGISTICS_RECEIVED => 'Retour reçu et contrôlé',
            ReturnModel::LOGISTICS_REFUND_PENDING => 'Remboursement en préparation',
            ReturnModel::LOGISTICS_REFUNDED => 'Remboursement effectué',
            ReturnModel::LOGISTICS_CLAIM_REVIEW => 'Réclamation en analyse',
            ReturnModel::LOGISTICS_REFUND_REVIEW => 'Remboursement en analyse',
            null, '' => null,
            default => 'Traitement en cours',
        };
    }
}
