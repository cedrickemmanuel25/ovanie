<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commande de diagnostic du moteur de livraison OVANIE.
 *
 * Migré depuis diag_delivery.php (racine du projet) — ne pas supprimer ce fichier
 * tant que la commande n'a pas été validée en environnement de développement.
 *
 * Usage : php artisan diag:delivery
 */
class DiagDelivery extends Command
{
    protected $signature = 'diag:delivery';

    protected $description = 'Diagnostic du moteur de livraison OVANIE : poids, véhicule, zones et tarifs.';

    public function handle(): int
    {
        // ─── Panier de test ──────────────────────────────────────────────────────
        $cart = Cart::with('items.product.shop')->whereHas('items')->first();

        if (! $cart) {
            $this->error('Aucun panier trouvé dans la base de données.');
            return Command::FAILURE;
        }

        $this->info("=== PANIER CLIENT (User ID: {$cart->user_id}) ===");
        $this->newLine();

        // ─── Colonnes poids disponibles ──────────────────────────────────────────
        $productColumns = Schema::getColumnListing('products');
        $hasWeight      = in_array('weight_kg',  $productColumns);
        $hasVolume      = in_array('volume_m3',  $productColumns);
        $hasWeightOld   = in_array('weight',     $productColumns);

        $this->line(
            'Colonnes poids : '
            . ($hasWeight    ? 'weight_kg ✅'  : 'weight_kg ❌')  . ' | '
            . ($hasVolume    ? 'volume_m3 ✅'  : 'volume_m3 ❌')  . ' | '
            . ($hasWeightOld ? 'weight ✅'     : 'weight ❌')
        );
        $this->newLine();

        // ─── Groupement par boutique ─────────────────────────────────────────────
        $groups = $cart->items
            ->filter(fn ($item) => $item->product)
            ->groupBy(fn ($item) => $item->product->shop_id);

        $totalWeight = 0;
        $totalVolume = 0;

        foreach ($groups as $shopId => $items) {
            $shop = $items->first()->product->shop;
            $this->line("╔══════════════════════════════════════════════════════════════╗");
            $this->line("║ BOUTIQUE : {$shop->name} (ID : {$shopId})");
            $this->line("║ Logistique : " . ($shop->logistics_type ?? 'ovanie'));
            $this->line("║ Commune boutique : " . ($shop->commune ?? 'N/A'));
            $this->line("╚══════════════════════════════════════════════════════════════╝");

            $groupWeight   = 0;
            $groupVolume   = 0;
            $groupSubtotal = 0;

            foreach ($items as $item) {
                $product  = $item->product;
                $price    = (float) $item->price * (int) $item->quantity;
                $groupSubtotal += $price;

                $weight = 0;
                $volume = 0;
                if ($hasWeight)       { $weight = (float) ($product->weight_kg ?? 0); }
                elseif ($hasWeightOld){ $weight = (float) ($product->weight    ?? 0); }
                if ($hasVolume)       { $volume = (float) ($product->volume_m3 ?? 0); }

                $weightTotal   = $weight * $item->quantity;
                $volumeTotal   = $volume * $item->quantity;
                $groupWeight  += $weightTotal;
                $groupVolume  += $volumeTotal;

                $this->line("  📦 {$product->name}");
                $this->line("     Qté : {$item->quantity} | Prix : " . number_format($price, 0, ',', ' ') . " FCFA");
                $this->line("     Poids/unité : {$weight} kg | Total poids : {$weightTotal} kg");
                $this->line("     Volume/unité : {$volume} m³ | Total volume : {$volumeTotal} m³");
            }

            $totalWeight += $groupWeight;
            $totalVolume += $groupVolume;

            $this->newLine();
            $this->line("  ► Sous-total boutique : " . number_format($groupSubtotal, 0, ',', ' ') . " FCFA");
            $this->line("  ► Poids total boutique : {$groupWeight} kg");
            $this->line("  ► Volume total boutique : " . round($groupVolume, 4) . " m³");

            $vehicle = $this->resolveVehicle($groupWeight, $groupVolume);
            $this->line("  ► Véhicule recommandé : 🚚 {$vehicle}");
            $this->newLine();

            // ─── Zones de livraison ───────────────────────────────────────────────
            if (in_array($shop->logistics_type, ['seller', 'vendor'], true)) {
                $zones = DB::table('seller_delivery_zones')
                    ->where('shop_id', $shopId)
                    ->where('is_active', 1)
                    ->orderBy('commune')
                    ->get();

                $this->line("  ZONES VENDEUR CONFIGURÉES :");
                if ($zones->isEmpty()) {
                    $this->warn("  ⚠  Aucune zone configurée !");
                } else {
                    $done = [];
                    foreach ($zones as $z) {
                        if (! in_array($z->commune, $done)) {
                            $this->line("    • {$z->commune} : " . (int) $z->delivery_price . " FCFA (délai : {$z->estimated_delay})");
                            $done[] = $z->commune;
                        }
                    }
                }
            } else {
                $this->line("  LOGISTIQUE OVANIE → Tarifs dynamiques par commune");
                $rates = DB::table('delivery_service_rates')
                    ->join('delivery_services', 'delivery_service_rates.delivery_service_id', '=', 'delivery_services.id')
                    ->where('delivery_service_rates.is_active', 1)
                    ->where('delivery_services.is_active', 1)
                    ->select('delivery_service_rates.commune', 'delivery_service_rates.base_fee', 'delivery_services.name')
                    ->get();

                if ($rates->isEmpty()) {
                    $this->warn("  ⚠  Aucun tarif OVANIE configuré !");
                } else {
                    foreach ($rates->unique('commune') as $r) {
                        $this->line("    • {$r->commune} : à partir de " . (int) $r->base_fee . " FCFA");
                    }
                }
            }
            $this->newLine();
        }

        // ─── Récapitulatif global ────────────────────────────────────────────────
        $this->line("═══════════════════════════════════════════════════════════════");
        $this->info("RÉCAPITULATIF GLOBAL :");
        $this->line("  Boutiques impliquées : " . $groups->count());
        $this->line("  Poids total panier  : {$totalWeight} kg");
        $this->line("  Volume total panier : " . round($totalVolume, 4) . " m³");
        $this->newLine();

        if ($groups->count() >= 2) {
            $this->comment("  ℹ  Plusieurs boutiques → livraison CONSOLIDÉE OVANIE possible");
        }

        $globalVehicle = $this->resolveVehicle($totalWeight, $totalVolume);
        $this->line("  Véhicule global recommandé : 🚚 {$globalVehicle}");
        $this->newLine();

        // ─── Diagnostic des produits sans poids ─────────────────────────────────
        $this->info("=== DIAGNOSTIC POIDS DES PRODUITS ===");
        $products   = $cart->items->pluck('product')->filter();
        $zeroWeight = $products->filter(function ($p) use ($hasWeight, $hasWeightOld) {
            if ($hasWeight)    return ($p->weight_kg ?? 0) == 0;
            if ($hasWeightOld) return ($p->weight    ?? 0) == 0;
            return true;
        });

        if ($zeroWeight->count() > 0) {
            $this->warn("⚠  {$zeroWeight->count()}/{$products->count()} produits n'ont PAS de poids défini :");
            foreach ($zeroWeight as $p) {
                $this->line("   - {$p->name} (ID : {$p->id})");
            }
            $this->line("→ Sans poids, le moteur utilise 0 kg → véhicule 'Moto' par défaut");
        } else {
            $this->info("✅ Tous les produits ont un poids défini.");
        }

        return Command::SUCCESS;
    }

    /**
     * Détermine le type de véhicule selon le poids et le volume total.
     */
    private function resolveVehicle(float $weight, float $volume): string
    {
        if ($weight <= 20    && $volume <= 0.18) { return 'Moto (≤20 kg, ≤0.18 m³)'; }
        if ($weight <= 250   && $volume <= 1.5)  { return 'Tricycle (≤250 kg, ≤1.5 m³)'; }
        if ($weight <= 1000  && $volume <= 7.0)  { return 'Pickup (≤1000 kg, ≤7 m³)'; }
        if ($weight <= 3000  && $volume <= 20.0) { return 'Camion 3T (≤3000 kg, ≤20 m³)'; }
        return 'Camion 10T (>3000 kg ou >20 m³)';
    }
}
