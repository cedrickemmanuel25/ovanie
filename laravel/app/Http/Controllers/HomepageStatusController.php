<?php

namespace App\Http\Controllers;

use App\Services\HomepageService;
use Illuminate\Http\JsonResponse;

class HomepageStatusController extends Controller
{
    public function __invoke(HomepageService $homepageService): JsonResponse
    {
        return response()->json($homepageService->status());
    }
}
