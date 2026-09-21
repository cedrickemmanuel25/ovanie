<?php

namespace App\Http\Controllers;

use App\Services\VendorDashboardService;
use Illuminate\Support\Facades\Auth;

class VendorDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Tableau de bord vendeur OVANIE.
     */
    public function index(VendorDashboardService $dashboardService)
    {
        $user = Auth::user();
        $shop = $user?->shop;

        if (! $shop) {
            return redirect()
                ->route('open-shop')
                ->with('error', 'Vous devez créer une boutique pour accéder au tableau de bord vendeur.');
        }

        $data = $dashboardService->build($shop);

        return view('vendor.dashboard', $data);
    }
}
