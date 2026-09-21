<?php

use App\Services\HomepageMaintenanceService;
use App\Services\HomepageHealthService;
use App\Services\VendorPayoutScheduleService;
use App\Services\PayDunyaPayoutService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ovanie:payouts-sync', function () {
    $result = app(VendorPayoutScheduleService::class)->synchronizeAndReleaseDue();

    $this->info(sprintf(
        '%d commande(s) synchronisée(s), %d reversement(s) arrivé(s) à échéance.',
        $result['orders_synced'],
        $result['payouts_released']
    ));
})->purpose('Synchronise l’éligibilité et le calendrier des reversements vendeurs OVANIE.');

Schedule::command('ovanie:payouts-sync')
    ->everyTenMinutes()
    ->withoutOverlapping();

Artisan::command('ovanie:payouts-execute {--limit= : Nombre maximum de reversements à traiter} {--dry-run : Vérifie uniquement la configuration}', function () {
    $mode = (string) config('vendor_payouts.execution_mode', 'manual');
    $paydunyaMode = strtolower((string) config('paydunya.mode', 'test'));

    if ($this->option('dry-run')) {
        $this->info('Mode reversement : ' . $mode);
        $this->info('Mode PayDunya : ' . $paydunyaMode);
        $this->info('PayDunya activé : ' . (config('paydunya.enabled') ? 'oui' : 'non'));
        $this->info('Reversements approuvés : ' . \App\Models\VendorPayout::where('status', \App\Models\VendorPayout::STATUS_APPROVED)->count());
        $this->info('Reversements en traitement : ' . \App\Models\VendorPayout::where('status', \App\Models\VendorPayout::STATUS_PROCESSING)->count());
        return self::SUCCESS;
    }

    if ($mode !== 'paydunya') {
        $this->comment('Exécution automatique désactivée : VENDOR_PAYOUT_EXECUTION_MODE=' . $mode);
        return self::SUCCESS;
    }

    if ($paydunyaMode !== 'live') {
        $this->error('Sécurité : l’exécution automatique est bloquée tant que PAYDUNYA_MODE n’est pas live.');
        return self::FAILURE;
    }

    $limit = $this->option('limit');
    $result = app(PayDunyaPayoutService::class)->processOpen($limit !== null ? (int) $limit : null);

    $this->info(sprintf(
        '%d examiné(s), %d payé(s), %d en attente, %d échec(s), %d ignoré(s).',
        $result['examined'],
        $result['paid'],
        $result['pending'],
        $result['failed'],
        $result['skipped']
    ));

    return self::SUCCESS;
})->purpose('Exécute et réconcilie les reversements vendeurs via PayDunya Payout.');

Schedule::command('ovanie:payouts-execute')
    ->everyTenMinutes()
    ->withoutOverlapping();


Artisan::command('ovanie:homepage-promotions-maintain', function () {
    $service = app(HomepageMaintenanceService::class);
    $fixed = $service->ensurePromotionWindows();
    $updated = $service->normalizeExpiredSales();

    $this->info(sprintf(
        '%d début(s) Flash réparé(s), %d fin(s) Flash réparée(s), %d promotion(s) expirée(s) normalisée(s).',
        $fixed['flash_start_fixed'],
        $fixed['flash_end_fixed'],
        $updated
    ));
})->purpose('Répare les fenêtres promotionnelles puis normalise les promotions expirées.');

Artisan::command('ovanie:homepage-health', function () {
    $report = app(HomepageHealthService::class)->inspect();

    $this->info($report['ok'] ? 'Accueil OVANIE : OK' : 'Accueil OVANIE : anomalies détectées');

    foreach ($report['metrics'] as $key => $value) {
        $this->line(sprintf('%-34s %s', $key, $value));
    }

    if ($report['blocking']) {
        $this->newLine();
        $this->error('Points bloquants :');
        foreach ($report['blocking'] as $item) {
            $this->line('- ' . $item);
        }
    }
})->purpose('Vérifie les connexions backend indispensables à la page d’accueil.');

Artisan::command('ovanie:homepage-free-boosts-refresh', function () {
    $updated = app(HomepageMaintenanceService::class)->refreshFreeBoosts();
    $this->info("{$updated} produit(s) sélectionné(s) pour le boost gratuit OVANIE.");
})->purpose('Renouvelle la sélection quotidienne de boosts gratuits OVANIE.');

Schedule::command('ovanie:homepage-promotions-maintain')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('ovanie:homepage-free-boosts-refresh')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Artisan::command('ovanie:delivery-demo-audit {--delete : Supprime uniquement les anciennes commandes CMD-4800 à CMD-4846}', function () {
    $query = \App\Models\Order::query()
        ->whereRaw("order_number REGEXP '^CMD-48(0[0-9]|[1-3][0-9]|4[0-6])$'");

    $count = (clone $query)->count();
    $this->info("{$count} ancienne(s) commande(s) de démonstration logistique détectée(s).");

    if (! $this->option('delete') || $count === 0) {
        $this->line('Aucune suppression effectuée. Relancez avec --delete après vérification.');
        return;
    }

    if (! $this->confirm('Confirmer la suppression de ces seules commandes de démonstration et de leurs données liées ?')) {
        $this->warn('Suppression annulée.');
        return;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($query) {
        $orders = $query->get(['id']);
        $orderIds = $orders->pluck('id');
        $itemIds = \App\Models\OrderItem::query()->whereIn('order_id', $orderIds)->pluck('id');
        $shipmentIds = \App\Models\Shipment::query()->whereIn('order_id', $orderIds)->pluck('id');

        if (\Illuminate\Support\Facades\Schema::hasTable('driver_locations')) {
            \App\Models\DriverLocation::query()
                ->whereIn('order_id', $orderIds)
                ->orWhereIn('shipment_id', $shipmentIds)
                ->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('delivery_assignments')) {
            \App\Models\DeliveryAssignment::query()
                ->whereIn('order_id', $orderIds)
                ->orWhereIn('order_item_id', $itemIds)
                ->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('seller_delivery_tracking_sessions')) {
            \App\Models\SellerDeliveryTrackingSession::query()->whereIn('order_id', $orderIds)->delete();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('shipments')) {
            \App\Models\Shipment::query()->whereIn('id', $shipmentIds)->delete();
        }

        \App\Models\OrderItem::query()->whereIn('id', $itemIds)->delete();
        \App\Models\Order::query()->whereIn('id', $orderIds)->delete();
    });

    $this->info('Anciennes données de démonstration supprimées.');
})->purpose('Audite et, sur confirmation, supprime uniquement les anciennes données logistiques de démonstration.');

Artisan::command('ovanie:delivery-repair {--apply : Applique les réparations détectées}', function () {
    $apply = (bool) $this->option('apply');
    $report = app(\App\Services\DeliveryTrackingRepairService::class)->run($apply);

    $this->info($apply ? 'Réparation du suivi terminée.' : 'Audit du suivi terminé (aucune modification).');
    foreach ($report as $key => $value) {
        $this->line(str_pad($key, 42) . $value);
    }

    if (! $apply && array_sum($report) > 0) {
        $this->newLine();
        $this->comment('Après sauvegarde de la base, relancez avec : php artisan ovanie:delivery-repair --apply');
    }
})->purpose('Audite ou répare les liaisons réelles entre missions, expéditions, GPS et sessions vendeur.');

Artisan::command('ovanie:delivery-health {order? : ID ou numéro de commande à inspecter}', function () {
    $routeChecks = [
        'Portail livreur' => \Illuminate\Support\Facades\Route::has('driver.login'),
        'Mission livreur OVANIE' => \Illuminate\Support\Facades\Route::has('driver.missions.show'),
        'Mission chauffeur vendeur' => \Illuminate\Support\Facades\Route::has('seller-driver.mission'),
        'API suivi commande' => collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($route) => $route->uri() === 'api/orders/{order}/tracking'
        ),
        'Supervision logistique' => \Illuminate\Support\Facades\Route::has('logistics.tracking.api'),
    ];

    $schemaChecks = [
        'Table delivery_assignments' => \Illuminate\Support\Facades\Schema::hasTable('delivery_assignments'),
        'Table driver_locations' => \Illuminate\Support\Facades\Schema::hasTable('driver_locations'),
        'Lien GPS → mission' => \Illuminate\Support\Facades\Schema::hasColumn('driver_locations', 'mission_number'),
        'Lien GPS → affectation' => \Illuminate\Support\Facades\Schema::hasColumn('driver_locations', 'delivery_assignment_id'),
        'Lien GPS → commande' => \Illuminate\Support\Facades\Schema::hasColumn('driver_locations', 'order_id'),
        'Sessions logistique vendeur' => \Illuminate\Support\Facades\Schema::hasTable('seller_delivery_tracking_sessions'),
        'Positions chauffeurs vendeur' => \Illuminate\Support\Facades\Schema::hasTable('seller_driver_locations'),
    ];

    $rows = collect($routeChecks)
        ->merge($schemaChecks)
        ->map(fn ($ok, $label) => [$label, $ok ? 'OK' : 'MANQUANT'])
        ->values()
        ->all();

    $this->table(['Contrôle', 'Résultat'], $rows);

    $failed = collect($routeChecks)->merge($schemaChecks)->contains(false);
    if ($failed) {
        $this->error('Le suivi n’est pas prêt. Exécutez optimize:clear puis migrate avant de tester.');
    } else {
        $this->info('Structure du suivi unifié : OK.');
    }

    $reference = trim((string) $this->argument('order'));
    if ($reference === '') {
        $this->line('Ajoutez un ID ou numéro de commande pour contrôler ses liaisons : php artisan ovanie:delivery-health 1');
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    $order = \App\Models\Order::query()
        ->where(function ($query) use ($reference) {
            if (ctype_digit($reference)) {
                $query->whereKey((int) $reference)->orWhere('order_number', $reference);
            } else {
                $query->where('order_number', $reference);
            }
        })
        ->with([
            'items.latestDeliveryAssignment',
            'items.sellerTrackingSession.latestLocation',
            'items.shipment.latestDriverLocation',
        ])
        ->first();

    if (! $order) {
        $this->error("Commande {$reference} introuvable.");
        return self::FAILURE;
    }

    $this->newLine();
    $this->info("Commande {$order->order_number} — {$order->items->count()} ligne(s)");
    $this->table(
        ['Article', 'Mode', 'Statut', 'Mission/session', 'Dernier GPS'],
        $order->items->map(function ($item) {
            $assignment = $item->latestDeliveryAssignment;
            $session = $item->sellerTrackingSession;
            $lastGps = $session?->latestLocation?->recorded_at
                ?: $item->shipment?->latestDriverLocation?->recorded_at
                ?: $assignment?->gps_last_seen_at;

            return [
                (string) $item->id,
                $item->delivery_provider ?: 'historique',
                $item->delivery_status ?: 'pending',
                $assignment?->resolved_mission_number ?: ($session?->public_id ?: '—'),
                $lastGps?->format('d/m/Y H:i:s') ?: 'Aucun',
            ];
        })->all()
    );

    return $failed ? self::FAILURE : self::SUCCESS;
})->purpose('Contrôle les routes, colonnes et liaisons réelles du suivi de livraison OVANIE.');
