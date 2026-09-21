<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OvanieReferenceDataService;
use Illuminate\Http\JsonResponse;

class DeliveryTerritoryController extends Controller
{
    public function index(OvanieReferenceDataService $references): JsonResponse
    {
        $zones = $references->deliveryTerritory();

        return response()->json([
            'zones' => $zones,
            'coverage_configured' => ! empty($zones),
            'source' => 'logistics_territory_zones',
        ]);
    }
}
