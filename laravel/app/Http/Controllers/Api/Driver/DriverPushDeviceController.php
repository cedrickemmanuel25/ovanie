<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use App\Models\DriverPushDevice;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DriverPushDeviceController extends Controller
{
    public function status(Request $request, FirebasePushService $push): JsonResponse
    {
        $available = Schema::hasTable('driver_push_devices') && $push->configured();

        $registeredDevices = 0;
        $latestDevice = null;

        if (Schema::hasTable('driver_push_devices')) {
            $activeDevices = DriverPushDevice::query()
                ->where('driver_id', $this->driver($request)->id)
                ->where('is_active', true);

            $registeredDevices = (clone $activeDevices)->count();

            $device = (clone $activeDevices)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->first();

            if ($device) {
                $latestDevice = [
                    'id' => (int) $device->id,
                    'platform' => (string) $device->platform,
                    'device_name' => $device->device_name,
                    'app_version' => $device->app_version,
                    'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'available' => $available,
                'provider' => $push->configured() ? 'firebase_fcm_http_v1' : null,
                'delivery_mode' => 'all_active_devices',
                'registered_devices' => $registeredDevices,
                'latest_device' => $latestDevice,
            ],
        ]);
    }

    public function store(Request $request, FirebasePushService $push): JsonResponse
    {
        abort_unless(
            Schema::hasTable('driver_push_devices'),
            503,
            'Le registre push livreur n’est pas encore installé.'
        );
        abort_unless(
            $push->configured(),
            503,
            'Firebase Cloud Messaging n’est pas encore configuré sur le serveur OVANIE.'
        );

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
        ]);

        $token = trim((string) $data['token']);
        $driverId = $this->driver($request)->id;

        $device = DriverPushDevice::query()->updateOrCreate(
            ['token' => $token],
            [
                'driver_id' => $driverId,
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
                'last_error_at' => null,
                'last_error_code' => null,
            ]
        );

        $activeCount = DriverPushDevice::query()
            ->where('driver_id', $driverId)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'message' => 'Cet appareil est enregistré pour les notifications OVANIE Logistics.',
            'data' => [
                'id' => (int) $device->id,
                'active' => true,
                'active_devices' => $activeCount,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (! Schema::hasTable('driver_push_devices')) {
            return response()->json(['message' => 'Aucun appareil push enregistré.']);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DriverPushDevice::query()
            ->where('driver_id', $this->driver($request)->id)
            ->where('token', trim((string) $data['token']))
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Notifications push désactivées sur cet appareil uniquement.',
        ]);
    }

    private function driver(Request $request): DeliveryDriver
    {
        $driver = $request->user();
        abort_unless($driver instanceof DeliveryDriver, 401);

        return $driver;
    }
}
