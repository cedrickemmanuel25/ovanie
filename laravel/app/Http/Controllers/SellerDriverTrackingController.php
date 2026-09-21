<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerDeliveryTrackingSession;
use App\Services\DeliveryNotificationService;
use App\Services\OrderWorkflowService;
use App\Services\SellerDeliverySupervisionService;
use App\Services\VendorOrderTransitionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerDriverTrackingController extends Controller
{
    public function show(
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $signal = $supervision->signalStatus($session);

        return view('seller-driver.mission', [
            'session' => $session,
            'signal' => $signal,
            'missionUrl' => $supervision->publicMissionUrl($session),
        ]);
    }

    public function accept(
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $supervision->acceptSession($session);

        return response()->json([
            'success' => true,
            'message' => 'Mission acceptée.',
        ]);
    }

    public function start(
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $supervision->startDelivery($session);

        return response()->json([
            'success' => true,
            'message' => 'Livraison démarrée.',
        ]);
    }

    public function gpsUnavailable(
        Request $request,
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $validated = $request->validate([
            'reason' => ['required', 'in:permission_denied,no_signal,device_issue,battery_saving,other'],
            'manual_eta_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $supervision->markGpsUnavailable(
            $session,
            $validated['reason'],
            filled($validated['manual_eta_at'] ?? null) ? Carbon::parse($validated['manual_eta_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Mode sans GPS activé. Mettez les étapes à jour manuellement.',
        ]);
    }

    public function manualStatus(
        Request $request,
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,in_transit,arrived'],
            'manual_eta_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $supervision->updateManualStatus(
            $session,
            $validated['status'],
            filled($validated['manual_eta_at'] ?? null) ? Carbon::parse($validated['manual_eta_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Étape mise à jour.',
        ]);
    }

    public function location(
        Request $request,
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $location = $supervision->recordLocation($session, $validated);
        $fresh = $session->fresh();

        return response()->json([
            'success' => true,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'location' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => is_numeric($location->accuracy) ? (float) $location->accuracy : null,
                'speed' => is_numeric($location->speed) ? (float) $location->speed : null,
                'heading' => is_numeric($location->heading) ? (float) $location->heading : null,
            ],
            'signal' => $supervision->signalStatus($fresh),
            'mission_status' => $fresh->mission_status,
            'arrived_at' => $fresh->arrived_at?->toIso8601String(),
            'route' => [
                'distance_km' => $fresh->remaining_distance_km,
                'eta_minutes' => $fresh->eta_minutes,
                'traffic_delay_minutes' => $fresh->traffic_delay_minutes,
                'geometry' => $this->decodeGeometry($fresh->route_geometry),
            ],
        ]);
    }

    public function incident(
        Request $request,
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);

        $validated = $request->validate([
            'incident_type' => ['required', 'in:traffic_jam,client_absent,address_issue,vehicle_breakdown,accident,product_damaged,access_impossible,delivery_refused,other'],
            'incident_note' => ['nullable', 'string', 'max:1500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $supervision->reportIncident(
            $session,
            $validated['incident_type'],
            $validated['incident_note'] ?? null,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Incident transmis au centre de supervision OVANIE.',
        ]);
    }

    public function verifyOtp(
        Request $request,
        string $publicId,
        string $token,
        SellerDeliverySupervisionService $supervision,
        VendorOrderTransitionService $transitions,
        OrderWorkflowService $workflow,
        DeliveryNotificationService $deliveryNotifications
    ) {
        $session = $supervision->resolvePublicSession($publicId, $token);
        $validated = $request->validate([
            'delivery_otp_code' => ['required', 'digits:6'],
        ]);

        $deliveredItem = null;

        DB::transaction(function () use ($session, $validated, $transitions, $workflow, $supervision, &$deliveredItem) {
            $lockedSession = SellerDeliveryTrackingSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSession->isTrackable()) {
                throw ValidationException::withMessages([
                    'delivery_otp_code' => 'Cette mission est déjà terminée.',
                ]);
            }

            $order = Order::query()->whereKey($lockedSession->order_id)->lockForUpdate()->firstOrFail();
            $items = OrderItem::query()
                ->where('seller_tracking_session_id', $lockedSession->id)
                ->where('delivery_provider', OrderWorkflowService::PROVIDER_SELLER)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'delivery_otp_code' => 'Aucune ligne de livraison n’est rattachée à cette mission.',
                ]);
            }

            $deliveredItem = $items->first();

            foreach ($items as $item) {
                $transitions->assertCanVerifyOtp($item);
            }

            $expectedCodes = $items->pluck('delivery_otp_code')->filter()->unique()->values();

            if ($expectedCodes->count() !== 1
                || ! hash_equals((string) $expectedCodes->first(), (string) $validated['delivery_otp_code'])) {
                throw ValidationException::withMessages([
                    'delivery_otp_code' => 'Code OTP invalide.',
                ]);
            }

            foreach ($items as $item) {
                $workflow->setDeliveryStatus(
                    $item,
                    OrderWorkflowService::DELIVERY_DELIVERED,
                    null,
                    'seller_driver',
                    'Livraison vendeur confirmée par OTP depuis la mission mobile supervisée par OVANIE.',
                    [
                        'delivery_otp_verified_at' => now(),
                        'vendor_delivery_status' => 'delivered',
                        'suppress_notifications' => true,
                    ]
                );
            }

            $supervision->completeSession($lockedSession);
            $order->refreshGlobalStatusFromItems();
        });

        if ($deliveredItem) {
            $deliveryNotifications->notifyGroup(
                $deliveredItem->refresh(),
                'delivered',
                'Livraison effectuée',
                'Votre livraison a été remise. Vérifiez les articles puis confirmez la réception depuis votre espace client.'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Livraison confirmée. Merci.',
        ]);
    }

    private function decodeGeometry($geometry): ?array
    {
        if (is_array($geometry)) {
            return $geometry;
        }

        if (! is_string($geometry) || trim($geometry) === '') {
            return null;
        }

        $decoded = json_decode($geometry, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
