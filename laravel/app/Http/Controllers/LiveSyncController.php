<?php

namespace App\Http\Controllers;

use App\Models\InternalUser;
use App\Models\DeliveryDriver;
use App\Models\Shop;
use App\Models\User;
use App\Support\LiveSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /live-sync (web) et GET /api/live-sync (mobile).
 *
 * Renvoie la version courante de chaque sujet visible par l'appelant. Les
 * clients comparent avec la version précédente et rechargent uniquement ce
 * qui a changé. Aucune donnée métier n'est exposée : seulement des jetons de
 * version opaques.
 *
 * Paramètre optionnel `watch` (liste séparée par des virgules) pour suivre
 * des fiches produit publiques : product:{id}.
 */
class LiveSyncController extends Controller
{
    private const MAX_WATCH = 20;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, string> $aliases alias public => clé interne */
        $aliases = [];

        foreach ($this->viewers($request) as $viewer) {
            if ($viewer instanceof DeliveryDriver) {
                $id = (int) $viewer->getKey();
                $aliases['missions'] = "driver.{$id}.missions";
                $aliases['notifications'] = "driver.{$id}.notifications";
            } elseif ($viewer instanceof InternalUser) {
                $aliases['ops'] = 'ops';
            } elseif ($viewer instanceof User) {
                $id = (int) $viewer->getKey();
                foreach (['cart', 'orders', 'favorites', 'notifications'] as $section) {
                    $aliases[$section] = "user.{$id}.{$section}";
                }
                $shopId = (int) Shop::query()->where('user_id', $id)->value('id');
                if ($shopId > 0) {
                    $aliases['shop'] = "shop.{$shopId}";
                }
            }
        }

        $aliases['catalog'] = 'catalog';

        foreach ($this->watchedTopics($request) as $alias => $key) {
            $aliases[$alias] = $key;
        }

        $versions = LiveSync::versions(array_values($aliases));

        $topics = [];
        $origins = [];
        foreach ($aliases as $alias => $key) {
            $topics[$alias] = $versions[$key]['v'] ?? '0';
            if (($versions[$key]['o'] ?? '') !== '') {
                $origins[$alias] = $versions[$key]['o'];
            }
        }

        return response()
            ->json([
                'topics' => $topics,
                'origins' => (object) $origins,
                'poll_after_ms' => (int) config('ovanie.live_sync_poll_ms', 8000),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<int, object>
     */
    private function viewers(Request $request): array
    {
        if ($request->is('api/*')) {
            $user = $request->user('sanctum');

            return $user ? [$user] : [];
        }

        $viewers = [];
        foreach (['admin', 'driver', 'web'] as $guard) {
            try {
                $user = auth($guard)->user();
            } catch (\Throwable) {
                $user = null;
            }
            if ($user) {
                $viewers[] = $user;
            }
        }

        return $viewers;
    }

    /**
     * @return array<string, string>
     */
    private function watchedTopics(Request $request): array
    {
        $raw = $request->query('watch', '');
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        $topics = [];
        foreach (array_slice($items, 0, self::MAX_WATCH) as $item) {
            $item = trim((string) $item);
            if (preg_match('/^product:(\d{1,12})$/', $item, $m)) {
                $topics[$item] = "product.{$m[1]}";
            }
        }

        return $topics;
    }
}
