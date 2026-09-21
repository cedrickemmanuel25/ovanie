<?php

namespace App\Http\Controllers;

use App\Services\PayDunyaPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayDunyaPayoutCallbackController extends Controller
{
    public function __invoke(Request $request, PayDunyaPayoutService $service): JsonResponse
    {
        try {
            $payout = $service->handleCallback($request->all());

            return response()->json([
                'success' => true,
                'matched' => $payout !== null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Callback PayDunya Payout rejeté.', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback invalide.',
            ], 403);
        }
    }
}
