<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cart;
use App\Models\Shop;
use App\Services\DeliveryPricingEngine;
use App\Services\CheckoutSummaryService;
use App\Services\CartFulfillmentOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$cart = Cart::with('items.product.shop')->whereHas('items')->first();

echo "=== SIMULATION LIVRAISON vers COCODY ===\n\n";

// Simulate a request to Cocody (Riviera 2)
$request = Request::create('/checkout/delivery-fee-preview', 'POST', [
    'delivery_zone'             => 'abidjan',
    'delivery_destination_type' => 'home',
    'delivery_commune'          => 'Cocody',
    'delivery_city'             => 'Abidjan',
    'address'                   => 'Riviera 2, Abidjan',
    'delivery_latitude'         => '5.3600',
    'delivery_longitude'        => '-3.9900',
]);

$request->setUserResolver(fn() => \App\Models\User::find($cart->user_id));

try {
    $engine = app(DeliveryPricingEngine::class);
    $items  = $cart->items->filter(fn($i) => $i->product);
    
    $quote = $engine->quote($items, $request);
    
    echo "RÉSULTAT DU DEVIS:\n";
    echo "  Disponible: " . ($quote['available'] ?? 'N/A') . "\n";
    echo "  Provider:   " . ($quote['provider_type'] ?? 'N/A') . "\n";
    echo "  Code:       " . ($quote['code'] ?? 'N/A') . "\n";
    echo "  Nom:        " . ($quote['name'] ?? 'N/A') . "\n";
    echo "  Prix:       " . number_format((float)($quote['price'] ?? 0), 0, ',', ' ') . " FCFA\n";
    echo "  Véhicule:   " . ($quote['vehicle_label'] ?? $quote['vehicle_code'] ?? 'N/A') . "\n";
    echo "  Délai:      " . ($quote['delay'] ?? 'N/A') . "\n";
    
    if (!empty($quote['meta']['seller_delivery'])) {
        $sd = $quote['meta']['seller_delivery'];
        echo "\n  DÉTAILS VENDEUR:\n";
        echo "    Zone ID:  " . ($sd['zone_id'] ?? 'N/A') . "\n";
        echo "    Commune:  " . ($sd['commune'] ?? 'N/A') . "\n";
        echo "    Poids:    " . ($sd['weight_kg'] ?? 0) . " kg\n";
        echo "    Volume:   " . ($sd['volume_m3'] ?? 0) . " m³\n";
    }
    
    if (!empty($quote['reason'])) {
        echo "\n  ⚠️  RAISON D'ÉCHEC: " . $quote['reason'] . "\n";
    }
    
    if (!empty($quote['meta']['missing'])) {
        echo "  ⚠️  MANQUANTS: " . implode(', ', $quote['meta']['missing']) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== VÉRIFICATION POIDS / VÉHICULE PAR LE MOTEUR ===\n";
try {
    $items = $cart->items->filter(fn($i) => $i->product);
    
    // Recalculate manually
    $totalWeight = $items->sum(fn($i) => ((float)($i->product->weight_kg ?? $i->product->weight ?? 0)) * (int)$i->quantity);
    $totalVolume = $items->sum(fn($i) => ((float)($i->product->volume_m3 ?? 0)) * (int)$i->quantity);
    
    echo "Poids total: {$totalWeight} kg\n";
    echo "Volume total: " . round($totalVolume, 4) . " m³\n";
    
    // Check carrier_rate_cards 
    $vehicleResolver = app(\App\Services\LogisticsVehicleResolver::class);
    $vehicle = $vehicleResolver->resolve($totalWeight, $totalVolume);
    echo "\nRésolution véhicule:\n";
    echo "  Code:  " . ($vehicle['vehicle_code'] ?? 'N/A') . "\n";
    echo "  Label: " . ($vehicle['vehicle_label'] ?? 'N/A') . "\n";
    echo "  Rate Card: " . ($vehicle['rate_card'] ? "Oui (ID: " . $vehicle['rate_card']->id . ")" : "Non") . "\n";
    
    // Show carrier_rate_cards
    $cards = DB::table('carrier_rate_cards')->where('is_active', 1)->get();
    echo "\nCarrier Rate Cards actives: " . $cards->count() . "\n";
    foreach ($cards as $card) {
        echo "  ID:{$card->id} vehicle:{$card->vehicle_code} min_weight:{$card->min_weight_kg}→{$card->max_weight_kg}kg | base:{$card->base_price} FCFA\n";
    }
    
} catch (\Throwable $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

echo "\n=== SELLER ZONES COCODY pour Shop 10 ===\n";
$zones = DB::table('seller_delivery_zones')
    ->where('shop_id', 10)
    ->where('is_active', 1)
    ->whereRaw("LOWER(commune) = 'cocody'")
    ->get();
foreach ($zones as $z) {
    echo "  Zone ID:{$z->id} commune:{$z->commune} vehicle:{$z->coverage_type} "
       . "prix:{$z->delivery_price} FCFA max_kg:{$z->max_weight_kg}\n";
}
