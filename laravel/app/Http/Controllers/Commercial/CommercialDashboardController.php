<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Services\Staff\CommercialDashboardService;
use Illuminate\Http\Request;

class CommercialDashboardController extends Controller
{
    public function __invoke(Request $request, CommercialDashboardService $service)
    {
        return view('commercial.dashboard', $service->build($request->user()));
    }
}
