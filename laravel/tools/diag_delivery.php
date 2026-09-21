<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Find test client cart
$cart = Cart::with('items.product.shop')->whereHas('items')->first();
if (!$cart) { echo "Aucun panier trouvé.\n"; exit; }

echo "=== PANIER CLIENT (User ID: {$cart->user_id}) ===\n\n";

// Check product table columns
$productColumns = Schema::getColumnListing('products');
$hasWeight     = in_array('weight_kg', $productColumns);
$hasVolume     = in_array('volume_m3', $productColumns);
$hasWeightOld  = in_array('weight', $productColumns);

echo "Colonnes poids disponibles: " . ($hasWeight ? 'weight_kg ✅' : 'weight_kg ❌') . " | " . ($hasVolume ? 'volume_m3 ✅' : 'volume_m3 ❌') . " | " . ($hasWeightOld ? 'weight ✅' : 'weight ❌') . "\n\n";

// Group by shop
$groups = $cart->items
    ->filter(fn($item) => $item->product)
    ->groupBy(fn($item) => $item->product->shop_id);

$totalWeight = 0;
$totalVolume = 0;

foreach ($groups as $shopId => $items) {
    $shop = $items->first()->product->shop;
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║ BOUTIQUE: {$shop->name} (ID: {$shopId})\n";
    echo "║ Logistique: " . ($shop->logistics_type ?? 'ovanie') . "\n";
    echo "║ Commune boutique: " . ($shop->commune ?? 'N/A') . "\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    
    $groupWeight = 0;
    $groupVolume = 0;
    $groupSubtotal = 0;
    
    foreach ($items as $item) {
        $product = $item->product;
        $price = (float)$item->price * (int)$item->quantity;
        $groupSubtotal += $price;
        
        $weight = 0;
        $volume = 0;
        if ($hasWeight)    $weight = (float)($product->weight_kg ?? 0);
        elseif ($hasWeightOld) $weight = (float)($product->weight ?? 0);
        if ($hasVolume)    $volume = (float)($product->volume_m3 ?? 0);
        
        $weightPerUnit   = $weight;
        $volumePerUnit   = $volume;
        $weightTotal     = $weight * $item->quantity;
        $volumeTotal     = $volume * $item->quantity;
        $groupWeight    += $weightTotal;
        $groupVolume    += $volumeTotal;
        
        echo "  📦 {$product->name}\n";
        echo "     Qté: {$item->quantity} | Prix: " . number_format($price, 0, ',', ' ') . " FCFA\n";
        echo "     Poids/unité: {$weightPerUnit} kg | Total poids: {$weightTotal} kg\n";
        echo "     Volume/unité: {$volumePerUnit} m³ | Total volume: {$volumeTotal} m³\n";
    }
    
    $totalWeight += $groupWeight;
    $totalVolume += $groupVolume;
    
    echo "\n  ► Sous-total boutique: " . number_format($groupSubtotal, 0, ',', ' ') . " FCFA\n";
    echo "  ► Poids total boutique: {$groupWeight} kg\n";
    echo "  ► Volume total boutique: " . round($groupVolume, 4) . " m³\n";
    
    // Determine vehicle
    $vehicle = 'Inconnue';
    if ($groupWeight <= 20 && $groupVolume <= 0.18) {
        $vehicle = 'Moto (≤20 kg, ≤0.18 m³)';
    } elseif ($groupWeight <= 250 && $groupVolume <= 1.5) {
        $vehicle = 'Tricycle (≤250 kg, ≤1.5 m³)';
    } elseif ($groupWeight <= 1000 && $groupVolume <= 7.0) {
        $vehicle = 'Pickup (≤1000 kg, ≤7 m³)';
    } elseif ($groupWeight <= 3000 && $groupVolume <= 20.0) {
        $vehicle = 'Camion 3T (≤3000 kg, ≤20 m³)';
    } else {
        $vehicle = 'Camion 10T (>3000 kg ou >20 m³)';
    }
    echo "  ► Véhicule recommandé: 🚚 {$vehicle}\n\n";
    
    // Delivery zone coverage
    if ($shop->logistics_type === 'seller' || $shop->logistics_type === 'vendor') {
        $zones = DB::table('seller_delivery_zones')
            ->where('shop_id', $shopId)
            ->where('is_active', 1)
            ->orderBy('commune')
            ->get();
        echo "  ZONES VENDEUR CONFIGURÉES:\n";
        if ($zones->isEmpty()) {
            echo "  ⚠️  Aucune zone configurée !\n";
        } else {
            $communesDone = [];
            foreach ($zones as $z) {
                if (!in_array($z->commune, $communesDone)) {
                    echo "    • {$z->commune}: " . (int)$z->delivery_price . " FCFA (délai: {$z->estimated_delay})\n";
                    $communesDone[] = $z->commune;
                }
            }
        }
    } else {
        echo "  LOGISTIQUE OVANIE → Tarifs dynamiques par commune\n";
        $rates = DB::table('delivery_service_rates')
            ->join('delivery_services', 'delivery_service_rates.delivery_service_id', '=', 'delivery_services.id')
            ->where('delivery_service_rates.is_active', 1)
            ->where('delivery_services.is_active', 1)
            ->select('delivery_service_rates.commune', 'delivery_service_rates.base_fee', 'delivery_services.name')
            ->get();
        if ($rates->isEmpty()) {
            echo "  ⚠️  Aucun tarif OVANIE configuré !\n";
        } else {
            foreach ($rates->unique('commune') as $r) {
                echo "    • {$r->commune}: à partir de " . (int)$r->base_fee . " FCFA\n";
            }
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "RÉCAPITULATIF GLOBAL:\n";
echo "  Boutiques impliquées: " . $groups->count() . "\n";
echo "  Poids total panier: {$totalWeight} kg\n";
echo "  Volume total panier: " . round($totalVolume, 4) . " m³\n";
echo "\n";

// Determine global vehicle
if ($groups->count() >= 2) {
    echo "  ℹ️  Plusieurs boutiques → livraison CONSOLIDÉE OVANIE possible\n";
    echo "     (Un seul véhicule collecte chez tous les vendeurs)\n";
}

$globalVehicle = 'Inconnue';
if ($totalWeight <= 20 && $totalVolume <= 0.18) {
    $globalVehicle = 'Moto (≤20 kg, ≤0.18 m³)';
} elseif ($totalWeight <= 250 && $totalVolume <= 1.5) {
    $globalVehicle = 'Tricycle (≤250 kg, ≤1.5 m³)';
} elseif ($totalWeight <= 1000 && $totalVolume <= 7.0) {
    $globalVehicle = 'Pickup (≤1000 kg, ≤7 m³)';
} elseif ($totalWeight <= 3000 && $totalVolume <= 20.0) {
    $globalVehicle = 'Camion 3T';
} else {
    $globalVehicle = 'Camion 10T';
}
echo "  Véhicule global recommandé: 🚚 {$globalVehicle}\n\n";

// Check if products have weight set
echo "=== DIAGNOSTIC POIDS DES PRODUITS ===\n";
$productsInCart = $cart->items->pluck('product')->filter();
$zeroWeight = $productsInCart->filter(function($p) use ($hasWeight, $hasWeightOld) {
    if ($hasWeight) return ($p->weight_kg ?? 0) == 0;
    if ($hasWeightOld) return ($p->weight ?? 0) == 0;
    return true;
});

if ($zeroWeight->count() > 0) {
    echo "⚠️  " . $zeroWeight->count() . "/" . $productsInCart->count() . " produits n'ont PAS de poids défini:\n";
    foreach ($zeroWeight as $p) {
        echo "   - {$p->name} (ID: {$p->id})\n";
    }
    echo "\n→ Sans poids, le moteur utilise 0 kg → véhicule 'Moto' par défaut\n";
} else {
    echo "✅ Tous les produits ont un poids défini.\n";
}
