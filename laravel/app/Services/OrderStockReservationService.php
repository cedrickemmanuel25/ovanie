<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrderStockReservationService
{
    public function reserve(Order $order): void
    {
        $order->loadMissing('items');
        $meta = $order->delivery_pricing_meta ?? [];
        $status = data_get($meta, 'stock_reservation.status');

        if (in_array($status, ['reserved', 'consumed'], true)) {
            return;
        }

        foreach ($order->items as $item) {
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

            if (! $product || (int) $product->stock < (int) $item->quantity) {
                throw new RuntimeException('Stock insuffisant pour le produit ' . ($product?->name ?? ('#' . $item->product_id)) . '.');
            }

            $product->decrement('stock', (int) $item->quantity);
        }

        data_set($meta, 'stock_reservation', [
            'status' => 'reserved',
            'reserved_at' => now()->toDateTimeString(),
            'released_at' => null,
            'consumed_at' => null,
        ]);

        $order->forceFill(['delivery_pricing_meta' => $meta])->save();
    }

    public function commit(Order $order): void
    {
        $meta = $order->delivery_pricing_meta ?? [];

        if (data_get($meta, 'stock_reservation.status') !== 'reserved') {
            return;
        }

        data_set($meta, 'stock_reservation.status', 'consumed');
        data_set($meta, 'stock_reservation.consumed_at', now()->toDateTimeString());
        $order->forceFill(['delivery_pricing_meta' => $meta])->save();
    }

    /**
     * Libère une réservation non payée et reconstruit les lignes concernées
     * dans le panier du client. L'opération est idempotente.
     */
    public function releaseAndRestoreCart(Order $order, bool $restoreCart = true): bool
    {
        return DB::transaction(function () use ($order, $restoreCart) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->load('items');
            $meta = $lockedOrder->delivery_pricing_meta ?? [];

            if (data_get($meta, 'stock_reservation.status') !== 'reserved') {
                return false;
            }

            foreach ($lockedOrder->items as $item) {
                Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('stock', (int) $item->quantity);
            }

            data_set($meta, 'stock_reservation.status', 'released');
            data_set($meta, 'stock_reservation.released_at', now()->toDateTimeString());
            $lockedOrder->forceFill(['delivery_pricing_meta' => $meta])->save();

            if ($restoreCart) {
                $this->restoreOrderItemsToCart($lockedOrder);
            }

            return true;
        });
    }

    private function restoreOrderItemsToCart(Order $order): void
    {
        $cart = Cart::firstOrCreate(['user_id' => $order->client_id]);

        foreach ($order->items as $orderItem) {
            $productId = (int) ($orderItem->original_product_id ?: $orderItem->product_id);
            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            $existing = $cart->items()->where('product_id', $productId)->first();

            if ($existing) {
                $existing->quantity = (int) $existing->quantity + (int) $orderItem->quantity;
                $existing->save();
                continue;
            }

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'original_product_id' => $productId,
                'fulfillment_product_id' => $productId,
                'original_shop_id' => $product->shop_id,
                'fulfillment_shop_id' => $product->shop_id,
                'optimization_applied' => false,
                'optimization_savings' => 0,
                'price' => $orderItem->price,
                'quantity' => $orderItem->quantity,
            ]);
        }

        if (Schema::hasColumn('carts', 'delivery_fee')) {
            $cart->delivery_fee = 0;
        }

        if (Schema::hasColumn('carts', 'grand_total')) {
            $cart->load('items.product');
            $cart->grand_total = (float) $cart->items->sum(function ($item) {
                $price = $item->price ?: ($item->product?->final_price ?? $item->product?->price ?? 0);
                return (float) $price * (int) $item->quantity;
            });
        }

        $cart->save();
    }
}
