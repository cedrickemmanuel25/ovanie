<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Services\Staff\SupportDashboardService;
use Illuminate\Http\Request;

class SupportDashboardController extends Controller
{
    public function __invoke(Request $request, SupportDashboardService $service)
    {
        return view('support.dashboard', $service->build($request->user()));
    }
}
