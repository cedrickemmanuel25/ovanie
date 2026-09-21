<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobilePushDevice;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MobilePushDeviceController extends Controller
{
    public function status(Request $request, FirebasePushService $push): JsonResponse
    {
        $available = Schema::hasTable('mobile_push_devices') && $push->configured();

        $registeredDevices = 0;
        $latestDevice = null;

        if (Schema::hasTable('mobile_push_devices')) {
            $activeDevices = MobilePushDevice::query()
                ->where('user_id', $request->user()->id)
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
                // Tous les appareils actifs du compte reçoivent les notifications.
                'delivery_mode' => 'all_active_devices',
                'registered_devices' => $registeredDevices,
                // Conservé pour compatibilité avec les anciennes versions mobiles.
                // Il s'agit uniquement du dernier appareil vu, pas de l'unique
                // appareil autorisé à recevoir des notifications.
                'current_device' => $latestDevice,
                'latest_device' => $latestDevice,
            ],
        ]);
    }

    public function store(Request $request, FirebasePushService $push): JsonResponse
    {
        abort_unless(
            Schema::hasTable('mobile_push_devices'),
            503,
            'Le registre push mobile n’est pas encore installé.'
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
            // Accepté pour compatibilité avec l'ancien client « appareil courant ».
            // Il n'est plus utilisé pour désactiver les autres appareils.
            'make_current' => ['nullable', 'boolean'],
        ]);

        $token = trim((string) $data['token']);
        $userId = (int) $request->user()->id;

        // Un token FCM représente une installation précise. On réactive seulement
        // l'installation qui vient de s'enregistrer, sans désactiver les autres
        // téléphones/tablettes déjà connectés au même compte OVANIE.
        $device = MobilePushDevice::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $userId,
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

        $activeCount = MobilePushDevice::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'message' => 'Cet appareil est enregistré pour les notifications OVANIE.',
            'data' => [
                'id' => (int) $device->id,
                'active' => true,
                'delivery_mode' => 'all_active_devices',
                'active_devices' => $activeCount,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (! Schema::hasTable('mobile_push_devices')) {
            return response()->json(['message' => 'Aucun appareil push enregistré.']);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        // Une déconnexion ne désactive que l'installation concernée.
        // Les autres appareils encore connectés au même compte continuent de
        // recevoir leurs notifications.
        MobilePushDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('token', trim((string) $data['token']))
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Notifications push désactivées sur cet appareil uniquement.',
        ]);
    }
}
