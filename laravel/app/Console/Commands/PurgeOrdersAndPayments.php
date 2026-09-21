<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PurgeOrdersAndPayments
 *
 * Supprime les commandes et paiements de la base de données.
 * Utilisé pour réinitialiser les données de test/démo sur le serveur.
 *
 * Usage :
 *   php artisan ovanie:purge-orders              → aperçu de ce qui sera supprimé
 *   php artisan ovanie:purge-orders --confirm    → suppression réelle
 *   php artisan ovanie:purge-orders --space=client --confirm
 *
 * Espaces disponibles : all, client, vendor, admin, driver, logistics
 */
class PurgeOrdersAndPayments extends Command
{
    protected $signature = 'ovanie:purge-orders
                            {--space=all : Espace à purger (all|client|vendor|admin|driver|logistics)}
                            {--confirm   : Confirme la suppression réelle (sans cette option = dry-run)}
                            {--keep-users : Ne supprime pas les paniers (conserve l\'historique utilisateurs)}';

    protected $description = 'Supprime les commandes et paiements de la base de données par espace';

    /**
     * Tables liées aux commandes, livraisons et paiements, dans l'ordre de suppression
     * (les tables dépendantes d'abord pour respecter les contraintes FK).
     */
    private array $relatedTables = [
        // Livraison & preuves & tracking
        'delivery_proofs',
        'delivery_incidents',
        'delivery_assignments',
        'seller_delivery_tracking_sessions',
        'driver_locations',
        'delivery_quotes',
        'order_experience_reviews',
        'shipment_status_histories',
        'shipments',

        // Paiements & Payouts & Commissions
        'payment_proofs',
        'vendor_payment_verification_requests',
        'vendor_payout_adjustments',
        'vendor_payouts',
        'commissions',
        'payments',

        // Retours, formulaires de réception & historique statut
        'returns',
        'return_requests',
        'order_reception_form_items',
        'order_reception_forms',
        'order_status_histories',
        'order_delivery_selections',

        // Cartes cadeaux & litiges liés aux commandes
        'gift_card_transactions',
        'disputes',

        // Support lié aux commandes
        'support_ticket_messages',
        'support_tickets',
        'support_conversation_messages',
        'support_conversations',

        // Fidélité
        'loyalty_transactions',

        // Articles & commandes
        'order_items',
        'order_promotion',
        'orders',
    ];

    /**
     * Tables panier (supprimées séparément si --keep-users non précisé).
     */
    private array $cartTables = [
        'cart_fulfillment_optimizations',
        'cart_items',
        'carts',
    ];

    public function handle(): int
    {
        $space   = $this->option('space');
        $isDryRun = ! $this->option('confirm');
        $keepUsers = $this->option('keep-users');

        $this->printBanner($space, $isDryRun);

        // Récupérer les statistiques avant suppression
        $stats = $this->gatherStats($space);

        if ($stats['orders'] === 0 && $stats['payments'] === 0) {
            $this->info('✅ Aucune donnée à supprimer. La base est déjà vide.');
            return self::SUCCESS;
        }

        $this->displayStats($stats);

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  MODE DRY-RUN — Aucune donnée supprimée.');
            $this->line('   Ajoutez <fg=yellow>--confirm</> pour effectuer la suppression réelle.');
            $this->newLine();
            $this->line('   Exemple : <fg=cyan>php artisan ovanie:purge-orders --space=' . $space . ' --confirm</>');
            return self::SUCCESS;
        }

        // Confirmation interactive en production
        if (app()->environment('production') || app()->environment('staging')) {
            if (! $this->confirm(
                "⚠️  ATTENTION : Vous êtes en environnement <{$_ENV['APP_ENV']}>. Confirmer la suppression ?",
                false
            )) {
                $this->line('Opération annulée.');
                return self::SUCCESS;
            }
        }

        $this->performPurge($space, $keepUsers, $stats);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Statistiques
    // ─────────────────────────────────────────────────────────────────────────

    private function gatherStats(string $space): array
    {
        $orderIds = $this->resolveOrderIds($space);

        return [
            'space'         => $space,
            'orders'        => $orderIds->count(),
            'order_items'   => DB::table('order_items')->whereIn('order_id', $orderIds)->count(),
            'payments'      => DB::table('payments')->whereIn('order_id', $orderIds)->count(),
            'payment_proofs'=> DB::table('payment_proofs')
                                  ->whereIn('payment_id', function ($q) use ($orderIds) {
                                      $q->select('id')->from('payments')->whereIn('order_id', $orderIds);
                                  })->count(),
            'shipments'     => DB::table('shipments')->whereIn('order_id', $orderIds)->count(),
            'commissions'   => DB::table('commissions')->whereIn('order_id', $orderIds)->count(),
            'vendor_payouts'=> DB::table('vendor_payouts')->whereIn('order_id', $orderIds)->count(),
            'carts'         => DB::table('carts')->count(),
            'cart_items'    => DB::table('cart_items')->count(),
            'order_ids'     => $orderIds,
        ];
    }

    /**
     * Résout les IDs de commandes selon l'espace sélectionné.
     */
    private function resolveOrderIds(string $space): \Illuminate\Support\Collection
    {
        $query = DB::table('orders');

        return match ($space) {
            // Espace client : commandes passées par les utilisateurs clients
            'client' => $query->pluck('id'),

            // Espace vendeur : commandes ayant des articles appartenant à des boutiques
            'vendor' => $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereNotNull('order_items.shop_id');
            })->pluck('id'),

            // Espace admin : toutes les commandes
            'admin'  => $query->pluck('id'),

            // Espace driver : commandes avec livraisons assignées
            'driver' => $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('delivery_assignments')
                    ->whereColumn('delivery_assignments.order_id', 'orders.id');
            })->pluck('id'),

            // Espace logistique : commandes avec expéditions
            'logistics' => $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('shipments')
                    ->whereColumn('shipments.order_id', 'orders.id');
            })->pluck('id'),

            // Par défaut / "all" : toutes les commandes
            default => $query->pluck('id'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Suppression
    // ─────────────────────────────────────────────────────────────────────────

    private function performPurge(string $space, bool $keepUsers, array $stats): void
    {
        $orderIds = $stats['order_ids'];

        $this->newLine();
        $this->info('🗑️  Début de la suppression...');
        $this->newLine();

        DB::transaction(function () use ($orderIds, $keepUsers, $space) {
            // 1. Désactiver temporairement les contraintes FK (MySQL)
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                // 2. Supprimer delivery_route_stops et delivery_routes globalement
                //    (ces tables sont liées aux drivers/shipments, pas directement à order_id)
                $this->deleteRoutes();

                // 3. Supprimer les tables liées aux commandes
                foreach ($this->relatedTables as $table) {
                    try {
                        $deleted = $this->deleteFromTable($table, $orderIds, $space);
                        $this->line(sprintf('   <fg=red>✗</> %-45s <fg=yellow>%d supprimé(s)</>', $table, $deleted));
                    } catch (\Exception $e) {
                        $this->warn(sprintf('   ⚠ %-45s erreur ignorée : %s', $table, $e->getMessage()));
                    }
                }

                // 4. Supprimer les paniers si pas de --keep-users
                if (! $keepUsers) {
                    foreach ($this->cartTables as $table) {
                        if (DB::getSchemaBuilder()->hasTable($table)) {
                            $count = DB::table($table)->delete();
                            $this->line(sprintf('   <fg=red>✗</> %-45s <fg=yellow>%d supprimé(s)</>', $table, $count));
                        }
                    }
                }

            } finally {
                // 5. Réactiver les contraintes FK
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $this->newLine();
        $this->info('✅ Suppression terminée avec succès.');
        $this->displaySummary($stats, $keepUsers);
    }

    /**
     * Supprime les routes de livraison et leurs stops.
     * delivery_routes n'a PAS de colonne order_id — elle est liée au driver.
     * delivery_route_stops est liée à shipment_id.
     * On supprime toutes les routes/stops (données opérationnelles temporaires).
     */
    private function deleteRoutes(): void
    {
        if (DB::getSchemaBuilder()->hasTable('delivery_route_stops')) {
            $count = DB::table('delivery_route_stops')->delete();
            $this->line(sprintf('   <fg=red>✗</> %-45s <fg=yellow>%d supprimé(s)</>', 'delivery_route_stops', $count));
        }

        if (DB::getSchemaBuilder()->hasTable('delivery_routes')) {
            $count = DB::table('delivery_routes')->delete();
            $this->line(sprintf('   <fg=red>✗</> %-45s <fg=yellow>%d supprimé(s)</>', 'delivery_routes', $count));
        }
    }

    private function deleteFromTable(string $table, \Illuminate\Support\Collection $orderIds, string $space): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        // Si l'espace est 'all', on supprime tous les enregistrements de la table
        if ($space === 'all') {
            return DB::table($table)->delete();
        }

        // Tables liées aux paiements (via payment_id)
        if ($table === 'payment_proofs') {
            return DB::table($table)->whereIn('payment_id', function ($q) use ($orderIds) {
                $q->select('id')->from('payments')->whereIn('order_id', $orderIds);
            })->delete();
        }

        // Tables liées aux ajustements de paiement vendeur
        if ($table === 'vendor_payout_adjustments') {
            return DB::table($table)->whereIn('vendor_payout_id', function ($q) use ($orderIds) {
                $q->select('id')->from('vendor_payouts')->whereIn('order_id', $orderIds);
            })->delete();
        }

        // Tables liées au support (ticket)
        if ($table === 'support_ticket_messages') {
            return DB::table($table)->whereIn('support_ticket_id', function ($q) use ($orderIds) {
                $q->select('id')->from('support_tickets')->whereIn('order_id', $orderIds);
            })->delete();
        }

        if ($table === 'support_conversation_messages') {
            return DB::table($table)->whereIn('support_conversation_id', function ($q) use ($orderIds) {
                $q->select('id')->from('support_conversations')->whereIn('order_id', $orderIds);
            })->delete();
        }

        if ($table === 'shipment_status_histories') {
            return DB::table($table)->whereIn('shipment_id', function ($q) use ($orderIds) {
                $q->select('id')->from('shipments')->whereIn('order_id', $orderIds);
            })->delete();
        }

        // Pour les tables order_promotion (pivot)
        if ($table === 'order_promotion') {
            return DB::table($table)->whereIn('order_id', $orderIds)->delete();
        }

        // Cas général : la table a une colonne order_id directe
        if (DB::getSchemaBuilder()->hasColumn($table, 'order_id')) {
            return DB::table($table)->whereIn('order_id', $orderIds)->delete();
        }

        return DB::table($table)->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Affichage
    // ─────────────────────────────────────────────────────────────────────────

    private function printBanner(string $space, bool $isDryRun): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║        <fg=red>OVANIE — Purge Commandes & Paiements</>         ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line('  Espace    : <fg=cyan>' . strtoupper($space) . '</>');
        $this->line('  Mode      : ' . ($isDryRun ? '<fg=green>DRY-RUN (simulation)</>  ' : '<fg=red>SUPPRESSION RÉELLE</>'));
        $this->line('  Environnement : <fg=yellow>' . app()->environment() . '</>');
        $this->newLine();
    }

    private function displayStats(array $stats): void
    {
        $this->line('  📊 <fg=yellow>Données trouvées :</>');
        $this->table(
            ['Table', 'Enregistrements'],
            [
                ['orders',        $stats['orders']],
                ['order_items',   $stats['order_items']],
                ['payments',      $stats['payments']],
                ['payment_proofs',$stats['payment_proofs']],
                ['shipments',     $stats['shipments']],
                ['commissions',   $stats['commissions']],
                ['vendor_payouts',$stats['vendor_payouts']],
                ['carts',         $stats['carts']],
                ['cart_items',    $stats['cart_items']],
            ]
        );
    }

    private function displaySummary(array $stats, bool $keepUsers): void
    {
        $this->newLine();
        $this->line('  📋 <fg=green>Résumé de l\'opération :</>');
        $this->line('  • Espace purgé     : <fg=cyan>' . strtoupper($stats['space']) . '</>');
        $this->line('  • Commandes        : <fg=red>' . $stats['orders'] . ' supprimée(s)</>');
        $this->line('  • Paiements        : <fg=red>' . $stats['payments'] . ' supprimé(s)</>');
        $this->line('  • Paniers          : ' . ($keepUsers ? '<fg=green>conservés</>' : '<fg=red>supprimés</>'));
        $this->newLine();
        $this->warn('  💡 Pensez à vider le cache : php artisan cache:clear');
        $this->newLine();
    }
}
