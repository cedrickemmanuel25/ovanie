<?php

namespace App\Http\Controllers;

use App\Services\Geo\AbidjanLandmarkService;
use App\Services\Geo\AbidjanLocalityRegistry;
use App\Services\Geo\GeocodingService;
use App\Services\Geo\RoutingService;
use App\Services\Geo\ShopLocationResolver;
use App\Services\Geo\WazeLinkService;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function search(Request $request, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:5'],
        ]);

        return response()->json([
            'success' => true,
            'results' => $geocoding->search(
                $data['q'],
                $data['country_code'] ?? config('geo.country_code')
            ),
        ]);
    }

    public function reverse(Request $request, GeocodingService $geocoding)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'fresh' => ['nullable', 'boolean'],
        ]);

        // V45 : quand le client envoie fresh=1, il vient de fournir un nouveau
        // fix GPS. On contourne alors le cache de reverse-geocoding afin qu'un
        // ancien libellé (commune/quartier) ne puisse pas être recyclé. Le cache
        // reste disponible pour les lectures non temps-réel.
        $fresh = (bool) ($data['fresh'] ?? false);
        $result = $geocoding->reverse(
            (float) $data['lat'],
            (float) $data['lng'],
            ! $fresh,
        );

        return response()->json([
            'success' => true,
            'result' => $result,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Endpoint public et limité utilisé par le formulaire d'ouverture de boutique.
     * Il transforme des coordonnées GPS en champs région/ville/commune/quartier/adresse.
     */
    public function reverseShopLocation(Request $request, ShopLocationResolver $resolver)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $resolver->reverse((float) $data['lat'], (float) $data['lng']);

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => "La position GPS a été obtenue, mais l'adresse n'a pas pu être identifiée automatiquement.",
            ], 422);
        }

        return response()->json($result);
    }

    public function abidjanCommunes(AbidjanLocalityRegistry $registry)
    {
        return response()->json([
            'success' => true,
            'district' => config('abidjan_localities.district'),
            'communes' => $registry->communes(),
        ]);
    }

    public function abidjanQuarters(Request $request, AbidjanLocalityRegistry $registry)
    {
        $data = $request->validate([
            'commune' => ['required', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'between:1,500'],
        ]);

        $commune = $registry->canonicalCommune($data['commune']);

        $localities = $registry->localitiesForCommune(
            $commune,
            $data['q'] ?? null,
            (int) ($data['limit'] ?? 500)
        );

        return response()->json([
            'success' => true,
            'commune' => $commune,
            'localities' => $localities,
            // Clé conservée pour les anciennes interfaces qui attendent « quarters ».
            'quarters' => $localities,
        ]);
    }

    public function abidjanLandmarks(Request $request, AbidjanLandmarkService $landmarks)
    {
        $data = $request->validate([
            'commune' => ['required', 'string', 'max:120'],
            'quarter' => ['required', 'string', 'max:160'],
            'q' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        return response()->json([
            'success' => true,
            'commune' => $data['commune'],
            'quarter' => $data['quarter'],
            'landmarks' => $landmarks->search(
                $data['commune'],
                $data['quarter'],
                $data['q'] ?? null,
                (int) ($data['limit'] ?? 30),
            ),
        ]);
    }

    public function route(Request $request, RoutingService $routing, WazeLinkService $waze)
    {
        $data = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $routing->route(
            (float) $data['from_lat'],
            (float) $data['from_lng'],
            (float) $data['to_lat'],
            (float) $data['to_lng']
        );

        if ($result['success'] ?? false) {
            $result['waze_url'] = $waze->navigationUrl(
                (float) $data['to_lat'],
                (float) $data['to_lng']
            );
        }

        return response()->json($result);
    }

    public function resolveShopAddress(Request $request, ShopLocationResolver $resolver)
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:120'],
            'commune' => ['nullable', 'string', 'max:120'],
            'quarter' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $resolver->search($data);

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => "Nous n'avons pas pu confirmer la position exacte de votre boutique. Ajoutez un quartier, une commune ou un repère plus précis.",
            ], 422);
        }

        return response()->json($result);
    }
}
