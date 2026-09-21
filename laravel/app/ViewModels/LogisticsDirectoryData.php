<?php

namespace App\ViewModels;

use App\Models\DeliveryDriver;
use App\Models\ReturnModel;

class LogisticsDirectoryData
{
    public static function driverConnectionStatus(DeliveryDriver $driver): array
    {
        return $driver->is_online
            ? ['En ligne', 'green']
            : ['Hors ligne', 'slate'];
    }

    public static function driverAvailabilityStatus(DeliveryDriver $driver): array
    {
        if (($driver->active_assignments_count ?? 0) > 0 || in_array($driver->status, ['En livraison', 'En mission'], true)) {
            return ['En mission', 'blue'];
        }

        if (! $driver->is_online) {
            return ['—', 'slate'];
        }

        if ($driver->status === 'Indisponible') {
            return ['Indisponible', 'slate'];
        }

        return ['Disponible', 'green'];
    }

    /**
     * Compatibilité avec les écrans plus anciens qui utilisent encore un seul
     * statut. Sur les nouveaux écrans, connexion GPS et disponibilité sont
     * volontairement affichées dans deux colonnes distinctes.
     */
    public static function driverStatus(DeliveryDriver $driver): array
    {
        if (! $driver->is_online) {
            return ['Hors ligne', 'slate'];
        }

        return self::driverAvailabilityStatus($driver);
    }

    /**
     * Libellé + couleur du badge d'inscription (parcours livreur partenaire).
     */
    public static function driverOnboarding(DeliveryDriver $driver): array
    {
        return match ($driver->onboarding_status) {
            DeliveryDriver::ONBOARDING_INVITED => ["En attente d'inscription", 'slate'],
            DeliveryDriver::ONBOARDING_PENDING_REVIEW => ['Dossier à vérifier', 'orange'],
            DeliveryDriver::ONBOARDING_SUSPENDED => ['Suspendu', 'red'],
            DeliveryDriver::ONBOARDING_REJECTED => ['Refusé', 'red'],
            default => ['Actif', 'green'],
        };
    }

    public static function returnStatus(ReturnModel $return): array
    {
        if ($return->status === 'refunded') {
            return ['Remboursé', 'green', 'refunded'];
        }
        if ($return->status === 'rejected') {
            return ['Rejeté', 'red', 'rejected'];
        }
        if (in_array($return->logistics_status, ['return_received', 'refund_pending'])) {
            return ['Reçu', 'purple', 'received'];
        }
        if ($return->status === 'accepted' && $return->return_type === 'refund') {
            return ['Remboursement', 'green', 'pickup'];
        }
        if ($return->status === 'accepted' && $return->return_type === 'claim') {
            return ['En analyse', 'blue', 'pickup'];
        }
        if ($return->status === 'accepted') {
            return ['Enlèvement', 'orange', 'pickup'];
        }

        return ['Demande', 'blue', 'pending'];
    }

    /**
     * Statut opérationnel affiché à la Logistique.
     *
     * Il est volontairement distinct de la décision du vendeur : une décision
     * peut être « Rejeté », « Retour confirmé » ou « Remboursé » alors que le
     * dossier est encore « À confirmer » côté Logistique.
     */
    public static function returnWorkflowStatus(ReturnModel $return): array
    {
        $decision = (array) data_get($return->meta, 'vendor_decision', []);
        $decisionType = (string) ($decision['type'] ?? '');

        if ($decisionType === '') {
            $decisionType = match (true) {
                $return->status === ReturnModel::STATUS_REJECTED => 'reject',
                $return->status === ReturnModel::STATUS_REFUNDED => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'refund' => 'refund',
                $return->status === ReturnModel::STATUS_ACCEPTED && $return->return_type === 'return' => 'accept',
                default => '',
            };
        }

        $published = ! empty($decision['published_at']);

        // Une décision vendeur existe, mais OVANIE Logistics ne l'a pas encore
        // transmise au client : le statut du dossier reste « À confirmer ».
        if ($decisionType !== '' && ! $published) {
            return ['À confirmer', 'yellow'];
        }

        if ($return->status === ReturnModel::STATUS_REFUNDED) {
            return ['Clôturé', 'green'];
        }

        if ($decisionType === 'reject' && $published) {
            return ['Clôturé', 'slate'];
        }

        return match ((string) $return->logistics_status) {
            ReturnModel::LOGISTICS_RECEIVED => ['Reçu', 'purple'],
            ReturnModel::LOGISTICS_REFUND_PENDING, ReturnModel::LOGISTICS_REFUND_REVIEW => ['Remboursement en cours', 'orange'],
            ReturnModel::LOGISTICS_IN_TRANSIT => ['Retour en transit', 'blue'],
            ReturnModel::LOGISTICS_PICKUP_PLANNED => ['Collecte planifiée', 'orange'],
            ReturnModel::LOGISTICS_PENDING_PICKUP => ['Enlèvement à planifier', 'orange'],
            ReturnModel::LOGISTICS_CLAIM_REVIEW => ['En analyse', 'blue'],
            default => $return->status === ReturnModel::STATUS_PENDING
                ? ['Demande', 'blue']
                : ['En traitement', 'blue'],
        };
    }


    public static function returnLogisticsStatusLabel(?string $value): string
    {
        return match ((string) $value) {
            ReturnModel::LOGISTICS_PENDING_PICKUP => 'Enlèvement à planifier',
            ReturnModel::LOGISTICS_PICKUP_PLANNED => 'Collecte planifiée',
            ReturnModel::LOGISTICS_IN_TRANSIT => 'Retour en transit',
            ReturnModel::LOGISTICS_RECEIVED => 'Retour reçu',
            ReturnModel::LOGISTICS_REFUND_PENDING => 'Remboursement en attente',
            ReturnModel::LOGISTICS_REFUNDED => 'Remboursé',
            ReturnModel::LOGISTICS_CLAIM_REVIEW => 'Réclamation en analyse',
            ReturnModel::LOGISTICS_REFUND_REVIEW => 'Remboursement en analyse',
            'not_required' => 'Aucune collecte requise',
            'pending' => 'En attente',
            '' => 'Non renseigné',
            default => 'En traitement',
        };
    }

    public static function refundMethodLabel(?string $value): string
    {
        return match ((string) $value) {
            'mobile_money' => 'Mobile Money',
            'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            'paydunya_manual' => 'PayDunya',
            '', 'null' => 'À renseigner',
            default => 'Autre mode',
        };
    }

    public static function ref($model, string $prefix): string
    {
        return $prefix.'-'.str_pad((string) $model->id, 4, '0', STR_PAD_LEFT);
    }

    public static function severity(string $value): array
    {
        return ['critical' => ['Critique', 'red'], 'high' => ['Élevée', 'orange'], 'medium' => ['Moyenne', 'yellow'], 'low' => ['Basse', 'slate']][$value] ?? [$value, 'slate'];
    }

    public static function incidentStatus(string $value): array
    {
        return ['open' => ['Ouvert', 'red'], 'in_progress' => ['En cours', 'blue'], 'rescheduled' => ['Reprogrammé', 'orange'], 'resolved' => ['Résolu', 'green'], 'closed' => ['Clôturé', 'green'], 'draft' => ['Brouillon', 'slate']][$value] ?? [$value, 'slate'];
    }

    public static function incidentIcon(string $value): array
    {
        return match ($value) {
            'accident','client_absent' => ['users', 'red'],'panne_vehicule' => ['settings', 'orange'],'produit_endommage','produit_incomplet' => ['box', 'green'],'refus_reception' => ['user', 'blue'],default => ['truck', 'slate']
        };
    }
}
