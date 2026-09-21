<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Service de calcul des commissions et reversements vendeurs OVANIE.
 *
 * Compatible PHP 8.3 / Laravel 12.
 * Important : ne pas utiliser des types comme OrderItem|object,
 * car "object" contient déjà toutes les classes et provoque une FatalError PHP 8.3.
 */
class CommissionService
{
    public const DEFAULT_RATE = 0.05; // 5 %

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BLOCKED = 'blocked';

    /**
     * Retourne le taux de commission applicable à une boutique.
     * Exemple : 0.05 = 5 %, 5 = 5 % également.
     */
    public function rateForShop(?Shop $shop = null): float
    {
        $rate = null;

        if ($shop) {
            foreach (['commission_rate', 'commission_percent', 'rate'] as $field) {
                if (isset($shop->{$field}) && $shop->{$field} !== null && $shop->{$field} !== '') {
                    $rate = $shop->{$field};
                    break;
                }
            }
        }

        if ($rate === null) {
            $rate = config('marketplace.default_commission_rate', self::DEFAULT_RATE);
        }

        return $this->normalizeRate($rate);
    }

    /**
     * Méthode attendue par VendorPayoutService.
     * Elle retourne uniquement le montant de la commission.
     */
    public function calculate(float $amount, ?Shop $shop = null, ?float $rate = null): float
    {
        $amount = $this->normalizeAmount($amount);
        $rate = $rate !== null ? $this->normalizeRate($rate) : $this->rateForShop($shop);

        return round($amount * $rate, 2);
    }

    /**
     * Convertit un prix vendeur HT de commission en prix public OVANIE.
     */
    public function publicAmountFromSellerAmount(float $sellerAmount, ?Shop $shop = null, ?float $rate = null): float
    {
        $sellerAmount = $this->normalizeAmount($sellerAmount);
        $rate = $rate !== null ? $this->normalizeRate($rate) : $this->rateForShop($shop);

        return round($sellerAmount * (1 + $rate), 2);
    }

    /**
     * Extrait la commission déjà incluse dans un montant public.
     *
     * Exemple à 5 % : 100 000 vendeur -> 105 000 public -> 5 000 commission.
     * Il ne faut donc jamais refaire 105 000 × 5 %.
     */
    public function commissionFromPublicAmount(float $publicAmount, ?Shop $shop = null, ?float $rate = null): float
    {
        $publicAmount = $this->normalizeAmount($publicAmount);
        $rate = $rate !== null ? $this->normalizeRate($rate) : $this->rateForShop($shop);

        if ($publicAmount <= 0 || $rate <= 0) {
            return 0.0;
        }

        $sellerAmount = $publicAmount / (1 + $rate);

        return round($publicAmount - $sellerAmount, 2);
    }

    public function sellerAmountFromPublicAmount(float $publicAmount, ?Shop $shop = null, ?float $rate = null): float
    {
        $publicAmount = $this->normalizeAmount($publicAmount);
        $rate = $rate !== null ? $this->normalizeRate($rate) : $this->rateForShop($shop);

        if ($publicAmount <= 0) {
            return 0.0;
        }

        return round($publicAmount / (1 + $rate), 2);
    }

    /**
     * Somme la commission déjà intégrée dans les prix publics des lignes panier/commande.
     * Chaque boutique peut conserver son propre taux si le projet l'active plus tard.
     */
    public function commissionFromPublicItems(iterable $items): float
    {
        $commission = 0.0;

        foreach ($items as $item) {
            $subtotal = $this->readValue($item, 'subtotal', null);

            if ($subtotal === null || $subtotal === '') {
                $price = $this->readValue($item, 'price', 0);
                $quantity = max(1, (float) $this->readValue($item, 'quantity', 1));
                $subtotal = $this->normalizeAmount($price) * $quantity;
            }

            $commission += $this->commissionFromPublicAmount(
                (float) $subtotal,
                $this->extractShop($item)
            );
        }

        return round($commission, 2);
    }

    /**
     * Donne le détail complet : brut, taux, commission, net vendeur.
     */
    public function breakdown(mixed $amount, ?Shop $shop = null, mixed $rate = null): array
    {
        $gross = $this->normalizeAmount($amount);
        $commissionRate = $rate !== null ? $this->normalizeRate($rate) : $this->rateForShop($shop);
        $commission = $this->commissionFromPublicAmount($gross, $shop, $commissionRate);
        $vendorAmount = max(0, round($gross - $commission, 2));

        return [
            'gross_amount' => $gross,
            'total_amount' => $gross,
            'commission_rate' => $commissionRate,
            'commission_percent' => round($commissionRate * 100, 2),
            'commission_amount' => $commission,
            'vendor_amount' => $vendorAmount,
            'payout_amount' => $vendorAmount,
        ];
    }

    public function calculateCommission(mixed $amount, mixed $shop = null, mixed $rate = null): float
    {
        return $this->breakdown($amount, $shop instanceof Shop ? $shop : null, $rate)['commission_amount'];
    }

    public function calculateVendorAmount(mixed $amount, mixed $shop = null, mixed $rate = null): float
    {
        return $this->breakdown($amount, $shop instanceof Shop ? $shop : null, $rate)['vendor_amount'];
    }

    public function calculatePayoutAmount(mixed $amount, mixed $shop = null, mixed $rate = null): float
    {
        return $this->calculateVendorAmount($amount, $shop, $rate);
    }

    /**
     * Calcule une ligne de commande sans union de type redondante.
     */
    public function calculateForOrderItem(mixed $item, mixed $shop = null): array
    {
        $quantity = (float) $this->readValue($item, 'quantity', 1);
        $price = $this->readValue($item, 'price', null);
        $unitPrice = $this->readValue($item, 'unit_price', null);
        $subtotal = $this->readValue($item, 'subtotal', null);

        if ($subtotal !== null && $subtotal !== '') {
            $gross = $this->normalizeAmount($subtotal);
        } else {
            $gross = $this->normalizeAmount($price ?? $unitPrice ?? 0) * max(1, $quantity);
        }

        $resolvedShop = $shop instanceof Shop ? $shop : $this->extractShop($item);
        $result = $this->breakdown($gross, $resolvedShop);

        return $result + [
            'order_item_id' => $this->readValue($item, 'id'),
            'product_id' => $this->readValue($item, 'product_id'),
            'shop_id' => $this->readValue($item, 'shop_id') ?: $this->readValue($this->extractProduct($item), 'shop_id'),
        ];
    }

    public function calculateOrderItemCommission(mixed $item, mixed $shop = null): float
    {
        return $this->calculateForOrderItem($item, $shop)['commission_amount'];
    }

    public function calculateForOrder(mixed $order, mixed $shop = null): array
    {
        $items = $this->extractItems($order);
        $gross = $this->itemsSubtotal($items);
        $resolvedShop = $shop instanceof Shop ? $shop : null;

        return $this->breakdown($gross, $resolvedShop) + [
            'order_id' => $this->readValue($order, 'id'),
            'order_number' => $this->readValue($order, 'order_number'),
            'items_count' => $items instanceof Collection ? $items->count() : count($items),
        ];
    }

    /**
     * Génère ou met à jour une commission par boutique pour une commande.
     * Méthode utilisée par VendorPayoutService.
     */
    public function createForOrderByShop(Order $order): Collection
    {
        try {
            $order->loadMissing('items.product.shop');
        } catch (Throwable $e) {
            // On continue avec ce qui est déjà chargé.
        }

        $items = $this->extractItems($order);

        if ($items->isEmpty()) {
            return collect();
        }

        $groupedItems = $items
            ->groupBy(function ($item) {
                return $this->readValue($item, 'shop_id')
                    ?: $this->readValue($this->extractProduct($item), 'shop_id');
            })
            ->filter(fn ($items, $shopId) => ! empty($shopId));

        if ($groupedItems->isEmpty()) {
            return collect();
        }

        if (! Schema::hasTable('commissions')) {
            return $groupedItems->map(function ($items, $shopId) use ($order) {
                $shop = $this->extractShop($items->first());
                $subtotal = $this->itemsSubtotal($items);
                $amount = $this->commissionFromPublicAmount($subtotal, $shop);

                return (object) [
                    'order_id' => $order->id,
                    'shop_id' => $shopId,
                    'amount' => $amount,
                    'status' => self::STATUS_PENDING,
                ];
            })->values();
        }

        return DB::transaction(function () use ($groupedItems, $order) {
            return $groupedItems->map(function ($items, $shopId) use ($order) {
                $shop = $this->extractShop($items->first());
                $subtotal = $this->itemsSubtotal($items);
                $commissionAmount = $this->commissionFromPublicAmount($subtotal, $shop);

                return Commission::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'shop_id' => $shopId,
                    ],
                    [
                        'amount' => $commissionAmount,
                        'status' => self::STATUS_PENDING,
                    ]
                );
            })->values();
        });
    }

    public function markPaid(Commission $commission): Commission
    {
        $commission->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $commission->refresh();
    }

    public function markCancelled(Commission $commission): Commission
    {
        $commission->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        return $commission->refresh();
    }

    /**
     * Calcule le total brut d'une collection de lignes de commande.
     */
    public function itemsSubtotal(iterable $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $subtotal = $this->readValue($item, 'subtotal', null);

            if ($subtotal !== null && $subtotal !== '') {
                $total += $this->normalizeAmount($subtotal);
                continue;
            }

            $price = $this->readValue($item, 'price', null)
                ?? $this->readValue($item, 'unit_price', null)
                ?? $this->readValue($this->extractProduct($item), 'price', 0);

            $quantity = (float) $this->readValue($item, 'quantity', 1);

            $total += $this->normalizeAmount($price) * max(1, $quantity);
        }

        return round($total, 2);
    }

    public function normalizeAmount(mixed $amount): float
    {
        if ($amount === null || $amount === '') {
            return 0.0;
        }

        if (is_numeric($amount)) {
            return round((float) $amount, 2);
        }

        $amount = (string) $amount;
        $amount = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $amount);
        $amount = preg_replace('/[^0-9.\-]/', '', $amount) ?: '0';

        return round((float) $amount, 2);
    }

    public function normalizeRate(mixed $rate): float
    {
        if ($rate === null || $rate === '') {
            return self::DEFAULT_RATE;
        }

        if (is_numeric($rate)) {
            $rate = (float) $rate;
        } else {
            $rate = (string) $rate;
            $rate = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], $rate);
            $rate = (float) (preg_replace('/[^0-9.\-]/', '', $rate) ?: '0');
        }

        if ($rate > 1) {
            $rate = $rate / 100;
        }

        if ($rate < 0) {
            return 0.0;
        }

        if ($rate > 1) {
            return 1.0;
        }

        return round($rate, 4);
    }

    public function formatXof(mixed $amount): string
    {
        return number_format($this->normalizeAmount($amount), 0, ',', ' ') . ' FCFA';
    }

    private function extractItems(mixed $order): Collection
    {
        $items = $this->readValue($order, 'items', collect());

        if ($items instanceof Collection) {
            return $items;
        }

        if (is_iterable($items)) {
            return collect($items);
        }

        return collect();
    }

    private function extractProduct(mixed $item): mixed
    {
        return $this->readValue($item, 'product', null);
    }

    private function extractShop(mixed $item): ?Shop
    {
        $directShop = $this->readValue($item, 'shop', null);

        if ($directShop instanceof Shop) {
            return $directShop;
        }

        $product = $this->extractProduct($item);
        $productShop = $this->readValue($product, 'shop', null);

        if ($productShop instanceof Shop) {
            return $productShop;
        }

        $shopId = $this->readValue($item, 'shop_id') ?: $this->readValue($product, 'shop_id');

        if ($shopId && class_exists(Shop::class)) {
            try {
                return Shop::find($shopId);
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function readValue(mixed $source, string $key, mixed $default = null): mixed
    {
        if ($source === null) {
            return $default;
        }

        if (is_array($source)) {
            return array_key_exists($key, $source) ? $source[$key] : $default;
        }

        if (is_object($source)) {
            try {
                if (isset($source->{$key}) || property_exists($source, $key)) {
                    return $source->{$key};
                }
            } catch (Throwable $e) {
                // On essaie data_get ensuite.
            }

            try {
                return data_get($source, $key, $default);
            } catch (Throwable $e) {
                return $default;
            }
        }

        return $default;
    }

    /**
     * Sécurité : si un ancien contrôleur appelle une méthode secondaire absente,
     * on renvoie un résultat neutre au lieu de casser la page vendeur.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_contains(strtolower($name), 'rate')) {
            return self::DEFAULT_RATE;
        }

        if (str_contains(strtolower($name), 'format')) {
            return $this->formatXof($arguments[0] ?? 0);
        }

        if (str_contains(strtolower($name), 'breakdown') || str_contains(strtolower($name), 'detail')) {
            return $this->breakdown($arguments[0] ?? 0);
        }

        if (str_contains(strtolower($name), 'commission')) {
            return $this->calculateCommission($arguments[0] ?? 0);
        }

        if (str_contains(strtolower($name), 'vendor') || str_contains(strtolower($name), 'payout') || str_contains(strtolower($name), 'net')) {
            return $this->calculateVendorAmount($arguments[0] ?? 0);
        }

        return null;
    }
}
