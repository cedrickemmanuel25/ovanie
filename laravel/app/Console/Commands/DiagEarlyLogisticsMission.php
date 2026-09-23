<?php

namespace App\Console\Commands;

use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\LogisticsShipmentWorkflowService;
use App\Services\OrderWorkflowService;
use Illuminate\Console\Command;

class DiagEarlyLogisticsMission extends Command
{
    protected $signature = 'diag:early-mission {order : Numéro de commande ou ID}';

    protected $description = 'Vérifie qu’une mission OVANIE Logistics est proposée avant que le vendeur marque la commande prête.';

    public function __construct(private readonly LogisticsShipmentWorkflowService $logisticsWorkflow)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $reference = trim((string) $this->argument('order'));

        $order = Order::query()
            ->with(['items.product.shop', 'items.shipment'])
            ->where(function ($query) use ($reference) {
                if (ctype_digit($reference)) {
                    $query->whereKey((int) $reference)
                        ->orWhere('order_number', $reference);
                } else {
                    $query->where('order_number', $reference);
                }
            })
            ->first();

        if (! $order) {
            $this->error("Commande introuvable : {$reference}");
            return self::FAILURE;
        }

        $this->info("=== CORRECTION 01 — MISSION ANTICIPÉE ===");
        $this->line("Commande : {$order->order_number} (ID {$order->id})");
        $this->line("Statut commande : {$order->status}");
        $this->line("Paiement : {$order->payment_method} / {$order->payment_status}");
        $this->newLine();

        $ovanieItems = $order->items
            ->filter(fn (OrderItem $item) => (string) $item->delivery_provider === OrderWorkflowService::PROVIDER_OVANIE)
            ->values();

        if ($ovanieItems->isEmpty()) {
            $this->warn('Cette commande ne contient aucune ligne OVANIE Logistics.');
            return self::INVALID;
        }

        $allPassed = true;

        foreach ($ovanieItems as $item) {
            $assignments = DeliveryAssignment::query()
                ->with('driver')
                ->where('order_item_id', $item->id)
                ->latest('id')
                ->get();

            $activeAssignments = $assignments->whereIn('status', [
                'offered', 'accepted', 'assigned', 'collecting', 'picked_up', 'in_transit', 'arrived', 'delivered',
            ]);

            $eligibleCount = $this->logisticsWorkflow->eligibleDrivers($item)->count();
            $vendorVisible = $item->vendor_visible_at !== null;
            $sellerReady = in_array($item->delivery_status, [
                OrderWorkflowService::DELIVERY_READY_FOR_PICKUP,
                OrderWorkflowService::DELIVERY_ASSIGNED,
                OrderWorkflowService::DELIVERY_PICKED_UP,
                OrderWorkflowService::DELIVERY_IN_TRANSIT,
                OrderWorkflowService::DELIVERY_DELIVERED,
            ], true);

            $this->line("Ligne #{$item->id} — " . ($item->product?->name ?: 'Produit'));
            $this->line('  Boutique : ' . ($item->product?->shop?->name ?: ('#' . $item->shop_id)));
            $this->line('  Visible vendeur : ' . ($vendorVisible ? 'OUI' : 'NON'));
            $this->line("  Statut livraison : {$item->delivery_status}");
            $this->line('  Vendeur prêt : ' . ($sellerReady ? 'OUI' : 'NON'));
            $this->line("  Livreurs éligibles : {$eligibleCount}");
            $this->line('  Affectations actives : ' . $activeAssignments->count());

            foreach ($activeAssignments as $assignment) {
                $driverName = $assignment->driver?->name ?: ('Livreur #' . $assignment->driver_id);
                $amount = $assignment->driver_net_amount !== null
                    ? number_format((float) $assignment->driver_net_amount, 0, ',', ' ') . ' FCFA'
                    : '—';
                $this->line("    - {$assignment->status} | {$driverName} | net {$amount}");
            }

            if ($vendorVisible && ! $sellerReady) {
                if ($activeAssignments->isNotEmpty()) {
                    $this->info('  ✅ PASS : mission déjà proposée/réservée alors que le vendeur prépare encore.');
                } elseif ($eligibleCount === 0) {
                    $this->warn('  ⚠ Aucun livreur éligible : la diffusion anticipée a pu s’exécuter mais aucune offre ne peut être créée.');
                } else {
                    $this->error('  ❌ FAIL : vendeur visible + livreurs éligibles, mais aucune offre anticipée trouvée.');
                    $allPassed = false;
                }
            } elseif (! $vendorVisible) {
                $this->comment('  ℹ La commande n’est pas encore libérée au vendeur : le déclencheur n’a pas encore eu lieu.');
            } else {
                $this->comment('  ℹ Le vendeur est déjà prêt : utilisez une nouvelle commande pour tester précisément le déclenchement anticipé.');
            }

            $this->newLine();
        }

        if ($allPassed) {
            $this->info('Diagnostic terminé : aucun échec détecté pour la correction 01.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
