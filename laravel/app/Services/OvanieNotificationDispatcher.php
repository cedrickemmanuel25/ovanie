<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OvanieNotificationDispatcher
{
    public function __construct(private readonly FirebasePushService $mobilePush)
    {
    }

    public function send(
        ?User $user,
        string $category,
        string $title,
        string $message,
        array $data = [],
        array $channels = ['in_app', 'email', 'sms']
    ): void {
        if (! $user) {
            return;
        }

        $category = $this->canonicalCategory($category);

        $requested = array_values(array_unique($channels));
        $databaseSent = false;

        foreach ($requested as $channel) {
            // Historiquement « push » signifiait notification in-app. Pour ne
            // perdre aucune alerte lors du passage à FCM, une demande push
            // conserve aussi la notification dans la boîte OVANIE.
            if ($channel === 'push') {
                if (! $databaseSent && $this->enabled($user, $category, 'in_app')) {
                    $this->databaseNotification($user, $category, $title, $message, $data);
                    $databaseSent = true;
                }

                if ($this->enabled($user, $category, 'push')) {
                    $this->mobilePush->sendToUser($user, $title, $message, [
                        'category' => $category,
                        'order_id' => $data['order_id'] ?? null,
                        'shipment_id' => $data['shipment_id'] ?? null,
                        'return_id' => $data['return_id'] ?? null,
                        'ticket_id' => $data['ticket_id'] ?? null,
                        'url' => $data['url'] ?? null,
                    ]);
                }
                continue;
            }

            if (! $this->enabled($user, $category, $channel)) {
                continue;
            }

            match ($channel) {
                'in_app' => $databaseSent ? null : $this->databaseNotification($user, $category, $title, $message, $data),
                'email' => $this->email($user, $title, $message, $data),
                'sms' => $this->sms($user, $message, $data),
                default => null,
            };

            if ($channel === 'in_app') {
                $databaseSent = true;
            }
        }
    }

    public function enabled(User $user, string $category, string $channel): bool
    {
        $category = $this->canonicalCategory($category);

        if ($category === 'security') {
            return true;
        }

        $preferences = $user->notification_preferences ?? [];
        $value = data_get($preferences, "{$category}.{$channel}");

        // Compatibilité avec les anciennes préférences : avant FCM, la clé
        // push était parfois utilisée pour la boîte in-app.
        if ($value === null && $channel === 'in_app') {
            $value = data_get($preferences, "{$category}.push");
        }
        if ($value === null && $channel === 'push') {
            $value = data_get($preferences, "{$category}.in_app");
        }

        return $value === null ? true : (bool) $value;
    }

    public function canonicalCategory(string $category): string
    {
        return match ($category) {
            'orders', 'order' => 'orders',
            'payments', 'payment', 'payouts' => 'payments',
            'logistics', 'delivery', 'deliveries' => 'deliveries',
            'returns', 'return', 'refund' => 'returns',
            'promo', 'promotion', 'promotions' => 'promotions',
            'security' => 'security',
            'support' => 'support',
            'system', 'account', 'admin' => 'account',
            'newsletter' => 'newsletter',
            'cart' => 'cart',
            default => 'account',
        };
    }

    private function databaseNotification(User $user, string $category, string $title, string $message, array $data): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'ovanie.client.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'message' => $message,
                'category' => $category,
                'url' => $data['url'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'order_item_id' => $data['order_item_id'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function email(User $user, string $title, string $message, array $data): void
    {
        if (! filled($user->email) || str_ends_with((string) $user->email, '@deleted.ovanie.local')) {
            return;
        }

        try {
            Mail::raw($message . (filled($data['url'] ?? null) ? "\n\n" . $data['url'] : ''), function ($mail) use ($user, $title) {
                $mail->to($user->email)->subject($title);
            });
        } catch (\Throwable $e) {
            Log::warning('Notification email OVANIE non envoyée.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sms(User $user, string $message, array $data): void
    {
        $phone = trim((string) ($data['phone'] ?? $user->phone ?? $user->whatsapp_phone ?? ''));
        if ($phone === '') {
            return;
        }

        try {
            SmsService::send($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('Notification SMS OVANIE non envoyée.', [
                'user_id' => $user->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
