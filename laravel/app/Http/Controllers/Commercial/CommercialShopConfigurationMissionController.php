<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Models\AbidjanCommune;
use App\Models\ShopConfigurationAssignment;
use App\Services\Commercial\ShopConfigurationAssignmentService;
use App\Services\Commercial\ShopConfigurationService;
use Illuminate\Http\Request;

class CommercialShopConfigurationMissionController extends Controller
{
    public function index(Request $request, ShopConfigurationAssignmentService $service)
    {
        $missions = ShopConfigurationAssignment::query()
            ->where('commercial_id', $request->user()->id)
            ->whereIn('status', [ShopConfigurationAssignment::STATUS_PENDING, ShopConfigurationAssignment::STATUS_IN_PROGRESS])
            ->with(['shop.user:id,name,email,phone'])
            ->latest('assigned_at')
            ->paginate(20);
        $missions->getCollection()->transform(fn ($mission) => $service->sync($mission));
        return view('commercial.configuration-missions.index', compact('missions'));
    }

    public function show(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service)
    {
        $assignment = $this->owned($request, $assignment);
        $assignment = $service->sync($assignment)->load(['shop.user:id,name,email,phone','shop.sellerDeliveryProfile','shop.sellerDeliveryZones']);
        $communes = AbidjanCommune::query()->where('is_active', true)->orderBy('name')->get(['id','name']);
        return view('commercial.configuration-missions.show', compact('assignment','communes'));
    }

    public function start(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service)
    {
        $service->start($this->owned($request, $assignment));
        return back()->with('success', 'Mission démarrée.');
    }

    public function logistics(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationService $service)
    {
        $assignment = $this->owned($request, $assignment);
        $service->saveLogistics($assignment, $request->all());
        return back()->with('success', 'Configuration logistique enregistrée pour la boutique.');
    }

    public function payout(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationService $service)
    {
        $assignment = $this->owned($request, $assignment);
        $service->savePayout($assignment, $request->all());
        return back()->with('success', 'Coordonnées de reversement confirmées et enregistrées.');
    }

    public function productsDone(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service)
    {
        $service->completeProducts($this->owned($request, $assignment));
        return back()->with('success', 'Tâche produits marquée comme terminée.');
    }

    public function complete(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service)
    {
        $service->complete($this->owned($request, $assignment));
        return redirect()->route('commercial.configuration-missions.index')->with('success', 'Mission boutique terminée.');
    }

    private function owned(Request $request, ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        abort_unless((int) $assignment->commercial_id === (int) $request->user()->id, 404);
        abort_if($assignment->status === ShopConfigurationAssignment::STATUS_CANCELLED, 404);
        return $assignment;
    }
}
