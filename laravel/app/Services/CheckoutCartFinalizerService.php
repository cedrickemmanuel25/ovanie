<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finalise le panier uniquement quand la commande devient réellement valide.
 *
 * Pour un paiement en ligne, les articles restent dans le panier tant que le
 * prestataire n'a pas confirmé le paiement. Le service est idempotent : un
 * webhook envoyé plusieurs fois ne vide jamais le panier plusieurs fois.
 */
class CheckoutCartFinalizerService
{
    public const POLICY_PRESERVE_UNTIL_PAYMENT = 'preserve_until_payment';
    public const POLICY_CLEAR_IMMEDIATELY = 'clear_immediately';

    public function finalize(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $freshOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $meta = is_array($freshOrder->delivery_pricing_meta)
                ? $freshOrder->delivery_pricing_meta
                : [];

            if (data_get($meta, 'checkout.cart_status') === 'cleared') {
                return false;
            }

            $cartId = (int) data_get($meta, 'checkout.cart_id', 0);
            $cartItemIds = collect(data_get($meta, 'checkout.cart_item_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($cartId > 0 && $cartItemIds !== []) {
                $cart = Cart::query()
                    ->whereKey($cartId)
                    ->where('user_id', $freshOrder->client_id)
                    ->lockForUpdate()
                    ->first();

                if ($cart) {
                    $cart->items()->whereIn('id', $cartItemIds)->delete();
                    $cart->load('items.product');

                    if (Schema::hasColumn('carts', 'delivery_fee')) {
                        $cart->delivery_fee = 0;
                    }

                    if (Schema::hasColumn('carts', 'grand_total')) {
                        $cart->grand_total = (float) $cart->items->sum(function ($item) {
                            $price = $item->price
                                ?: ($item->product?->final_price ?? $item->product?->price ?? 0);

                            return (float) $price * (int) $item->quantity;
                        });
                    }

                    $cart->save();
                }
            }

            data_set($meta, 'checkout.cart_status', 'cleared');
            data_set($meta, 'checkout.cart_cleared_at', now()->toDateTimeString());
            $freshOrder->forceFill(['delivery_pricing_meta' => $meta])->save();

            return true;
        }, 3);
    }

    public function cartWasPreserved(Order $order): bool
    {
        $meta = is_array($order->delivery_pricing_meta)
            ? $order->delivery_pricing_meta
            : [];

        return data_get($meta, 'checkout.cart_policy') === self::POLICY_PRESERVE_UNTIL_PAYMENT
            && data_get($meta, 'checkout.cart_status') !== 'cleared';
    }

    public function markAbandoned(Order $order): void
    {
        $meta = is_array($order->delivery_pricing_meta)
            ? $order->delivery_pricing_meta
            : [];

        data_set($meta, 'checkout.state', 'abandoned');
        data_set($meta, 'checkout.abandoned_at', now()->toDateTimeString());

        $order->forceFill(['delivery_pricing_meta' => $meta])->save();
    }
}
