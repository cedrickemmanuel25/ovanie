<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OvanieReferenceDataService;
use App\Services\OvanieSchemaCompatibilityService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;

class MobileReferenceDataController extends Controller
{
    public function index(OvanieReferenceDataService $references): JsonResponse
    {
        return response()->json($references->apiPayload());
    }

    public function compatibility(
        Request $request,
        OvanieSchemaCompatibilityService $compatibility
    ): JsonResponse {
        $app = (string) $request->query('app', $request->header('X-Ovanie-App', ''));
        $schema = (string) $request->query(
            'schema_version',
            $request->header('X-Ovanie-Schema-Version', '')
        );

        try {
            $result = $compatibility->evaluate($app, $schema);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'compatible' => false,
                'message' => $exception->getMessage(),
                'meta' => $compatibility->publicMeta(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'compatible' => (bool) $result['compatible'],
            'compatibility' => $result,
        ]);
    }

    public function meta(OvanieReferenceDataService $references, OvanieSchemaCompatibilityService $compatibility): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'meta' => array_merge($references->apiMeta(), [
                'compatibility' => $compatibility->publicMeta(),
            ]),
        ]);
    }
}
