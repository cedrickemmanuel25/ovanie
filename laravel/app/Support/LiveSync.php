<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronisation web ↔ mobile sans WebSocket.
 *
 * Chaque écriture significative (panier, commande, produit, mission…) fait
 * avancer la « version » d'un ou plusieurs sujets. Les pages web et les
 * applications mobiles interrogent régulièrement GET /live-sync (très léger :
 * lectures de cache uniquement) et se rafraîchissent dès qu'un sujet qu'elles
 * affichent a changé. Compatible cPanel : aucun processus permanent requis.
 *
 * Clés de sujet (internes) :
 *  - user.{id}.cart | user.{id}.orders | user.{id}.favorites | user.{id}.notifications
 *  - driver.{id}.missions | driver.{id}.notifications
 *  - shop.{id}         (commandes, produits, avis de la boutique)
 *  - product.{id}      (fiche produit publique)
 *  - catalog           (catalogue global)
 *  - ops               (supervision interne : commandes / expéditions / missions)
 */
class LiveSync
{
    public const CLIENT_HEADER = 'X-Ovanie-Client';

    private const CACHE_PREFIX = 'live_sync:';

    /** @var array<string, true> sujets modifiés dans la requête courante */
    private static array $pending = [];

    public static function touch(string ...$topics): void
    {
        foreach ($topics as $topic) {
            $topic = trim($topic);
            if ($topic !== '' && ! str_contains($topic, '..') && ! str_ends_with($topic, '.')) {
                self::$pending[$topic] = true;
            }
        }

        if (self::$pending === []) {
            return;
        }

        try {
            // Dans une transaction : publié après le commit, pour qu'un client
            // ne relise jamais des données pas encore visibles (et rien n'est
            // publié en cas de rollback).
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(static fn () => self::flush());

                return;
            }
        } catch (Throwable) {
            // Pas de connexion disponible : on publie directement.
        }

        self::flush();
    }

    public static function touchUser(?int $userId, string ...$sections): void
    {
        if (! $userId) {
            return;
        }

        self::touch(...array_map(static fn (string $s) => "user.{$userId}.{$s}", $sections));
    }

    public static function touchShop(?int $shopId): void
    {
        if ($shopId) {
            self::touch("shop.{$shopId}");
        }
    }

    public static function touchProduct(?int $productId, ?int $shopId = null): void
    {
        if ($productId) {
            self::touch("product.{$productId}", 'catalog');
        }
        self::touchShop($shopId);
    }

    public static function touchDriver(?int $driverId, string ...$sections): void
    {
        if (! $driverId) {
            return;
        }

        self::touch(...array_map(static fn (string $s) => "driver.{$driverId}.{$s}", $sections ?: ['missions']));
    }

    /**
     * @param  array<int, string>  $topics
     * @return array<string, array{v: string, o: string}>
     */
    public static function versions(array $topics): array
    {
        $topics = array_values(array_unique(array_filter($topics)));
        if ($topics === []) {
            return [];
        }

        $keys = array_map(static fn (string $t) => self::CACHE_PREFIX.$t, $topics);

        try {
            $values = Cache::many($keys);
        } catch (Throwable) {
            $values = [];
        }

        $result = [];
        foreach ($topics as $i => $topic) {
            $raw = (string) ($values[$keys[$i]] ?? '');
            [$version, $origin] = array_pad(explode('|', $raw, 2), 2, '');
            $result[$topic] = ['v' => $version !== '' ? $version : '0', 'o' => $origin];
        }

        return $result;
    }

    public static function currentClientId(): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return '';
        }

        try {
            $value = (string) request()->headers->get(self::CLIENT_HEADER, '');
        } catch (Throwable) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $value) ? $value : '';
    }

    private static function flush(): void
    {
        $topics = array_keys(self::$pending);
        self::$pending = [];

        if ($topics === []) {
            return;
        }

        // Version monotone (ms) + suffixe aléatoire pour distinguer deux
        // écritures dans la même milliseconde.
        $value = (string) (int) floor(microtime(true) * 1000).bin2hex(random_bytes(2)).'|'.self::currentClientId();

        try {
            Cache::putMany(array_fill_keys(
                array_map(static fn (string $t) => self::CACHE_PREFIX.$t, $topics),
                $value
            ), now()->addDays(30));
        } catch (Throwable $e) {
            // La synchronisation ne doit jamais faire échouer une écriture métier.
            Log::debug('LiveSync flush failed: '.$e->getMessage());
        }
    }
}
