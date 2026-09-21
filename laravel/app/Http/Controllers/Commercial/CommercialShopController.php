<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\CommercialShopLocationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommercialShopController extends Controller
{
    public function editLocation(Request $request, Shop $shop)
    {
        $this->authorizeManagedShop($request, $shop);

        abort_unless($shop->usesOvanieLogistics(), 422, 'Cette boutique n’utilise pas OVANIE Logistics.');

        return view('commercial.vendors.location', [
            'shop' => $shop->load('user:id,name,email,phone'),
            'mapboxToken' => (string) (config('services.mapbox.public_token') ?: config('geo.mapbox.public_token')),
            'mapboxStyle' => (string) (config('services.mapbox.style_url') ?: config('geo.mapbox.style_url') ?: 'mapbox://styles/mapbox/streets-v12'),
        ]);
    }

    public function updateLocation(
        Request $request,
        Shop $shop,
        CommercialShopLocationService $locations,
    ) {
        $this->authorizeManagedShop($request, $shop);

        abort_unless($shop->usesOvanieLogistics(), 422, 'Cette boutique n’utilise pas OVANIE Logistics.');

        $data = $request->validate([
            'commune' => ['required', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:150'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'geo_source' => ['required', Rule::in(['browser_gps', 'manual_map'])],
            'location_confirmed' => ['accepted'],
        ], [
            'latitude.required' => 'Capturez ou placez la position exacte de la boutique.',
            'longitude.required' => 'Capturez ou placez la position exacte de la boutique.',
            'location_confirmed.accepted' => 'Confirmez que le marqueur correspond au point d’enlèvement de la boutique.',
        ]);

        $shop = $locations->updateShop($shop, $data);

        $message = $shop->logistics_status === Shop::LOGISTICS_READY
            ? 'La position exacte a été enregistrée. La boutique est maintenant visible dans les outils OVANIE Logistics.'
            : 'La position a été enregistrée, mais sa précision doit être contrôlée avant l’activation logistique.';

        return redirect()
            ->route('commercial.vendors.index')
            ->with('success', $message);
    }

    private function authorizeManagedShop(Request $request, Shop $shop): void
    {
        $commercialId = (int) $request->user()->id;

        abort_unless(
            (int) $shop->created_by_commercial_id === $commercialId
            || (int) $shop->managed_by_commercial_id === $commercialId,
            403,
        );
    }
}
