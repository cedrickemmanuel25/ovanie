<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\AbidjanCommune;
use App\Models\ShopConfigurationAssignment;
use App\Services\Commercial\ShopConfigurationAssignmentService;
use App\Services\Commercial\ShopConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommercialShopConfigurationMissionController extends Controller
{
    public function index(Request $request, ShopConfigurationAssignmentService $service): JsonResponse
    {
        $rows = ShopConfigurationAssignment::query()
            ->where('commercial_id', $request->user()->id)
            ->whereIn('status', [ShopConfigurationAssignment::STATUS_PENDING, ShopConfigurationAssignment::STATUS_IN_PROGRESS])
            ->with(['shop.user:id,name,email,phone'])
            ->latest('assigned_at')
            ->get()
            ->map(fn ($assignment) => $this->payload($service->sync($assignment)));

        return response()->json([
            'summary' => [
                'total' => $rows->count(),
                'pending' => $rows->where('status', ShopConfigurationAssignment::STATUS_PENDING)->count(),
                'in_progress' => $rows->where('status', ShopConfigurationAssignment::STATUS_IN_PROGRESS)->count(),
            ],
            'missions' => $rows->values(),
        ]);
    }

    public function show(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service): JsonResponse
    {
        $assignment = $this->owned($request, $assignment);
        $assignment = $service->sync($assignment)->load(['shop.user:id,name,email,phone','shop.sellerDeliveryProfile','shop.sellerDeliveryZones']);
        $communes = AbidjanCommune::query()->where('is_active', true)->orderBy('name')->get(['id','name']);

        return response()->json([
            'mission' => $this->payload($assignment, true),
            'communes' => $communes,
        ]);
    }

    public function start(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service): JsonResponse
    {
        $assignment = $service->start($this->owned($request, $assignment));
        return response()->json(['message' => 'Mission démarrée.', 'mission' => $this->payload($assignment)]);
    }

    public function logistics(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationService $service): JsonResponse
    {
        $assignment = $service->saveLogistics($this->owned($request, $assignment), $request->all());
        return response()->json(['message' => 'Configuration logistique enregistrée.', 'mission' => $this->payload($assignment, true)]);
    }

    public function payout(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationService $service): JsonResponse
    {
        $assignment = $service->savePayout($this->owned($request, $assignment), $request->all());
        return response()->json(['message' => 'Reversement vendeur enregistré.', 'mission' => $this->payload($assignment, true)]);
    }

    public function productsDone(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service): JsonResponse
    {
        $assignment = $service->completeProducts($this->owned($request, $assignment));
        return response()->json(['message' => 'Tâche produits terminée.', 'mission' => $this->payload($assignment, true)]);
    }

    public function complete(Request $request, ShopConfigurationAssignment $assignment, ShopConfigurationAssignmentService $service): JsonResponse
    {
        $assignment = $service->complete($this->owned($request, $assignment));
        return response()->json(['message' => 'Mission terminée.', 'mission' => $this->payload($assignment)]);
    }

    private function owned(Request $request, ShopConfigurationAssignment $assignment): ShopConfigurationAssignment
    {
        abort_unless((int) $assignment->commercial_id === (int) $request->user()->id, 404);
        abort_if($assignment->status === ShopConfigurationAssignment::STATUS_CANCELLED, 404);
        return $assignment;
    }

    private function payload(ShopConfigurationAssignment $assignment, bool $detail = false): array
    {
        $assignment->loadMissing('shop.user');
        $shop = $assignment->shop;
        $data = [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'assigned_at' => optional($assignment->assigned_at)->toIso8601String(),
            'started_at' => optional($assignment->started_at)->toIso8601String(),
            'configure_logistics' => (bool) $assignment->configure_logistics,
            'configure_payout' => (bool) $assignment->configure_payout,
            'manage_products' => (bool) $assignment->manage_products,
            'logistics_completed' => $assignment->logistics_completed_at !== null,
            'payout_completed' => $assignment->payout_completed_at !== null,
            'products_completed' => $assignment->products_completed_at !== null,
            'can_complete' => $assignment->requiredConfigurationComplete(),
            'notes' => (string) ($assignment->notes ?? ''),
            'shop' => [
                'id' => $shop?->id,
                'name' => (string) ($shop?->name ?? ''),
                'owner_name' => (string) ($shop?->user?->name ?? ''),
                'owner_phone' => (string) ($shop?->user?->phone ?? ''),
                'city' => $shop?->configuration_location_verified ? (string) ($shop?->city ?? '') : '',
                'commune' => $shop?->configuration_location_verified ? (string) ($shop?->commune ?? '') : '',
                'district' => $shop?->configuration_location_verified ? (string) ($shop?->district ?? '') : '',
                'products_count' => $shop ? $shop->products()->count() : 0,
                'logistics_configured' => (bool) $shop?->hasConfirmedLogistics(),
                'payout_configured' => (bool) $shop?->hasConfiguredPayout(),
                'logistics_type' => (string) ($shop?->logistics_type ?? ''),
                'logistics_label' => (string) ($shop?->logistics_mode_label ?? 'À configurer'),
                'payment_mode' => (string) ($shop?->payment_mode ?? ''),
                'payout_operator' => (string) ($shop?->mm_operator ?? ''),
            ],
        ];

        if ($detail && $shop) {
            $data['configuration'] = [
                'address' => (string) ($shop->address ?? ''),
                'landmark' => (string) ($shop->landmark ?? ''),
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
                'payment_mode' => (string) ($shop->payment_mode ?? ''),
                'mm_operator' => (string) ($shop->mm_operator ?? ''),
                'mm_number' => (string) ($shop->mm_number ?? ''),
                'mm_holder' => (string) ($shop->mm_holder ?? ''),
                'seller_profile' => $shop->sellerDeliveryProfile,
                'seller_zones' => $shop->sellerDeliveryZones?->values() ?? [],
            ];
        }

        return $data;
    }
}
