<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorShopConfigurationController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()->shop()->with(['sellerDeliveryProfile', 'sellerDeliveryZones'])->firstOrFail();

        if ($shop->configurationReady()) {
            return redirect()->route('vendor.dashboard')->with('success', 'La configuration de votre boutique est complète.');
        }

        $needsLogistics = $shop->needsLogisticsConfiguration();

        // Le vendeur a déjà choisi « ma propre logistique » à l'ouverture de sa boutique : on ne lui
        // repose pas la question. Il arrive directement sur ce qu'il lui reste à remplir.
        // L'écran de choix reste réservé aux anciennes boutiques, créées avant que ce choix existe.
        $chosenLogistics = $shop->hasChosenLogisticsMode() ? $shop->logistics_type : null;
        if ($needsLogistics && $chosenLogistics === 'seller') {
            return redirect()->route('vendor.delivery.edit')->with(
                'info',
                'Il vous reste à terminer la configuration de votre livraison : vos paramètres de transport, puis au moins un tarif par commune. Vos clients pourront ensuite commander.'
            );
        }

        return view('vendor.configuration.index', [
            'shop' => $shop,
            'needsLogistics' => $needsLogistics,
            'chosenLogistics' => $chosenLogistics,
            'needsPayout' => $shop->needsPayoutConfiguration(),
        ]);
    }

    public function logistics(Request $request)
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);

        $data = $request->validate([
            'logistics_type' => ['required', Rule::in(['ovanie', 'seller'])],
            'address' => ['nullable', 'required_if:logistics_type,ovanie', 'string', 'max:255'],
            'commune' => ['nullable', 'required_if:logistics_type,ovanie', 'string', 'max:100'],
            'district' => ['nullable', 'required_if:logistics_type,ovanie', 'string', 'max:100'],
            'landmark' => ['nullable', 'required_if:logistics_type,ovanie', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_if:logistics_type,ovanie', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:logistics_type,ovanie', 'numeric', 'between:-180,180'],
            'geo_accuracy' => ['nullable', 'required_if:logistics_type,ovanie', 'numeric', 'gt:0', 'max:40075000'],
            'geo_captured_at' => ['nullable', 'required_if:logistics_type,ovanie', 'date'],
        ], [
            'latitude.required_if' => 'Autorisez la localisation de votre appareil afin qu’OVANIE puisse enregistrer le point de retrait de la boutique.',
            'longitude.required_if' => 'Autorisez la localisation de votre appareil afin qu’OVANIE puisse enregistrer le point de retrait de la boutique.',
            'geo_accuracy.required_if' => 'La précision de localisation n’a pas pu être vérifiée. Réessayez la détection.',
        ]);

        if ($data['logistics_type'] === 'seller') {
            DB::transaction(function () use ($shop) {
                $shop->forceFill([
                    'logistics_type' => 'seller',
                    'logistics_status' => Shop::LOGISTICS_INCOMPLETE,
                    // Le choix est enregistré : la question ne sera plus reposée. La livraison reste
                    // « à terminer » tant que les tarifs ne sont pas saisis (logistics_status).
                    'logistics_confirmed_at' => now(),
                ])->save();

                $shop->sellerDeliveryProfile()->firstOrCreate(
                    ['shop_id' => $shop->id],
                    ['is_enabled' => false, 'status' => 'incomplete']
                );
            });

            return redirect()
                ->route('vendor.delivery.edit')
                ->with('info', 'Vous avez choisi votre propre logistique. Renseignez maintenant vos capacités, vos zones, vos tarifs et vos délais.');
        }

        $accuracy = (float) $data['geo_accuracy'];
        $geoStatus = $accuracy <= 50
            ? Shop::GEO_STATUS_RELIABLE
            : Shop::GEO_STATUS_REVIEW_RECOMMENDED;

        DB::transaction(function () use ($shop, $data, $accuracy, $geoStatus) {
            $shop->forceFill([
                'logistics_type' => 'ovanie',
                'address' => trim((string) $data['address']),
                'commune' => trim((string) $data['commune']),
                'district' => trim((string) $data['district']),
                'landmark' => trim((string) $data['landmark']),
                'latitude' => (float) $data['latitude'],
                'longitude' => (float) $data['longitude'],
                'geo_accuracy' => $accuracy,
                'geo_source' => 'browser_gps',
                'geo_status' => $geoStatus,
                'geo_verified_at' => null,
                'logistics_status' => Shop::LOGISTICS_READY,
                'logistics_confirmed_at' => now(),
            ])->save();

            $shop->sellerDeliveryProfile()->update(['is_enabled' => false]);
        });

        return redirect()
            ->route('vendor.configuration.index')
            ->with('success', 'OVANIE Logistics est configuré. La position GPS a été enregistrée en arrière-plan et ne sera pas affichée au public.');
    }

    public function payout(Request $request)
    {
        $shop = $request->user()->shop;
        abort_unless($shop, 404);

        $data = $request->validate([
            'payment_mode' => ['required', Rule::in([Shop::PAYMENT_POST_DELIVERY, Shop::PAYMENT_WEEKLY])],
            'mm_operator' => ['required', Rule::in(['orange', 'mtn', 'wave', 'moov'])],
            'mm_number' => ['required', 'regex:/^[0-9+ ]{8,20}$/'],
            'mm_holder' => ['required', 'string', 'max:255'],
            'seller_confirmation' => ['required', 'accepted'],
        ], [
            'payment_mode.required' => 'Choisissez quand vous souhaitez recevoir vos reversements.',
            'mm_operator.required' => 'Choisissez le compte Mobile Money qui recevra les reversements.',
            'mm_number.required' => 'Le numéro de réception est obligatoire.',
            'mm_number.regex' => 'Le numéro de réception est invalide.',
            'mm_holder.required' => 'Indiquez le nom du titulaire du compte.',
            'seller_confirmation.accepted' => 'Confirmez que ces coordonnées de reversement vous appartiennent.',
        ]);

        $shop->forceFill([
            'payment_mode' => $data['payment_mode'],
            'mm_operator' => $data['mm_operator'],
            'mm_number' => preg_replace('/\s+/', '', trim($data['mm_number'])),
            'mm_holder' => trim($data['mm_holder']),
            'payout_configured_at' => now(),
        ])->save();

        if ($shop->fresh()->configurationReady()) {
            return redirect()->route('vendor.dashboard')->with('success', 'Votre boutique est maintenant entièrement configurée.');
        }

        return redirect()->route('vendor.configuration.index')->with('success', 'Le mode de reversement a été enregistré.');
    }
}
