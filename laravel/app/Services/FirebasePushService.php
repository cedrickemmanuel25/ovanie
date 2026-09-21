<?php

namespace App\Services;

use App\Models\DeliveryDriver;
use App\Models\DriverPushDevice;
use App\Models\MobilePushDevice;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FirebasePushService
{
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const OAUTH_AUDIENCE = 'https://oauth2.googleapis.com/token';

    public function configured(): bool
    {
        $account = $this->serviceAccount();
        return filled(config('firebase_push.project_id'))
            && filled($account['client_email'] ?? null)
            && filled($account['private_key'] ?? null);
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->configured() || ! Schema::hasTable('mobile_push_devices')) {
            return 0;
        }

        // Une notification métier OVANIE est envoyée à toutes les installations
        // FCM actives du compte client. Ainsi, si le même client est connecté
        // sur un téléphone et une tablette, les deux appareils reçoivent la
        // même notification provenant de la même donnée Laravel.
        $devices = MobilePushDevice::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $sent = 0;
        foreach ($devices as $device) {
            if ($this->sendToDevice($device, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToDevice(MobilePushDevice $device, string $title, string $body, array $data = []): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $projectId = (string) config('firebase_push.project_id');
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payloadData = collect($data)
            ->filter(fn ($value) => $value !== null)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) ? (string) $value : json_encode($value)])
            ->all();

        try {
            $response = Http::timeout((int) config('firebase_push.timeout', 15))
                ->withToken($this->accessToken())
                ->acceptJson()
                ->post($endpoint, [
                    'message' => [
                        'token' => $device->token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $payloadData,
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'ovanie_client_updates',
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                // last_seen_at représente la dernière utilisation /
                // réinscription réelle de l'appareil, pas la date d'envoi d'un
                // push. Cela permet d'identifier correctement l'appareil que
                // le client a utilisé le plus récemment.
                $device->forceFill([
                    'last_error_at' => null,
                    'last_error_code' => null,
                ])->save();
                return true;
            }

            $errorStatus = (string) data_get($response->json(), 'error.status', 'HTTP_'.$response->status());
            $invalid = in_array($errorStatus, ['UNREGISTERED', 'INVALID_ARGUMENT'], true);
            $device->forceFill([
                'is_active' => $invalid ? false : $device->is_active,
                'last_error_at' => now(),
                'last_error_code' => $errorStatus,
            ])->save();

            Log::warning('FCM OVANIE: notification non envoyée.', [
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'status' => $response->status(),
                'error' => $errorStatus,
            ]);
            return false;
        } catch (\Throwable $exception) {
            report($exception);
            $device->forceFill([
                'last_error_at' => now(),
                'last_error_code' => 'transport_error',
            ])->save();
            return false;
        }
    }

    public function sendToDriver(DeliveryDriver $driver, string $title, string $body, array $data = []): int
    {
        if (! $this->configured() || ! Schema::hasTable('driver_push_devices')) {
            return 0;
        }

        // Comme pour les clients, toutes les installations actives du livreur
        // reçoivent la notification (téléphone principal, éventuel second
        // appareil), sans désactiver les autres.
        $devices = DriverPushDevice::query()
            ->where('driver_id', $driver->id)
            ->where('is_active', true)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $sent = 0;
        foreach ($devices as $device) {
            if ($this->sendToDriverDevice($device, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToDriverDevice(DriverPushDevice $device, string $title, string $body, array $data = []): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $projectId = (string) config('firebase_push.project_id');
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payloadData = collect($data)
            ->filter(fn ($value) => $value !== null)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) ? (string) $value : json_encode($value)])
            ->all();

        try {
            $response = Http::timeout((int) config('firebase_push.timeout', 15))
                ->withToken($this->accessToken())
                ->acceptJson()
                ->post($endpoint, [
                    'message' => [
                        'token' => $device->token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $payloadData,
                        // Pas de channel_id personnalisé : contrairement à l'app
                        // client, l'app livreur ne crée pas encore de canal de
                        // notification Android dédié. Un channel_id qui n'existe
                        // pas sur l'appareil fait échouer silencieusement
                        // l'affichage sur Android 8+, donc on laisse FCM utiliser
                        // son canal de secours par défaut.
                        'android' => [
                            'priority' => 'high',
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $device->forceFill([
                    'last_error_at' => null,
                    'last_error_code' => null,
                ])->save();
                return true;
            }

            $errorStatus = (string) data_get($response->json(), 'error.status', 'HTTP_'.$response->status());
            $invalid = in_array($errorStatus, ['UNREGISTERED', 'INVALID_ARGUMENT'], true);
            $device->forceFill([
                'is_active' => $invalid ? false : $device->is_active,
                'last_error_at' => now(),
                'last_error_code' => $errorStatus,
            ])->save();

            Log::warning('FCM OVANIE Livreur: notification non envoyée.', [
                'device_id' => $device->id,
                'driver_id' => $device->driver_id,
                'status' => $response->status(),
                'error' => $errorStatus,
            ]);
            return false;
        } catch (\Throwable $exception) {
            report($exception);
            $device->forceFill([
                'last_error_at' => now(),
                'last_error_code' => 'transport_error',
            ])->save();
            return false;
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('ovanie:fcm:http-v1-access-token', now()->addMinutes(45), function () {
            $account = $this->serviceAccount();
            $now = time();
            $jwt = JWT::encode([
                'iss' => (string) ($account['client_email'] ?? ''),
                'scope' => self::OAUTH_SCOPE,
                'aud' => self::OAUTH_AUDIENCE,
                'iat' => $now,
                'exp' => $now + 3600,
            ], (string) ($account['private_key'] ?? ''), 'RS256');

            $response = Http::asForm()
                ->timeout((int) config('firebase_push.timeout', 15))
                ->post(self::OAUTH_AUDIENCE, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            $response->throw();
            $token = (string) data_get($response->json(), 'access_token', '');
            if ($token === '') {
                throw new \RuntimeException('Firebase n’a pas retourné de jeton OAuth.');
            }
            return $token;
        });
    }

    private function serviceAccount(): array
    {
        $json = trim((string) config('firebase_push.service_account_json'));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        }

        $path = trim((string) config('firebase_push.service_account_path'));
        if ($path !== '' && is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
