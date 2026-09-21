<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payouts')) {
            return;
        }

        Schema::table('vendor_payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payouts', 'product_amount')) {
                $table->decimal('product_amount', 15, 2)->default(0)->after('order_id');
            }
            if (! Schema::hasColumn('vendor_payouts', 'seller_delivery_amount')) {
                $table->decimal('seller_delivery_amount', 15, 2)->default(0)->after('product_amount');
            }
            if (! Schema::hasColumn('vendor_payouts', 'ovanie_delivery_amount')) {
                $table->decimal('ovanie_delivery_amount', 15, 2)->default(0)->after('seller_delivery_amount');
            }
            if (! Schema::hasColumn('vendor_payouts', 'net_product_amount')) {
                $table->decimal('net_product_amount', 15, 2)->default(0)->after('ovanie_delivery_amount');
            }
        });

        $hasDeliveryProvider = Schema::hasColumn('order_items', 'delivery_provider');
        $hasDeliveryPrice = Schema::hasColumn('order_items', 'delivery_price');
        $hasSubtotal = Schema::hasColumn('order_items', 'subtotal');
        $hasMeta = Schema::hasColumn('vendor_payouts', 'meta');

        DB::table('vendor_payouts')
            ->orderBy('id')
            ->chunkById(100, function ($payouts) use (
                $hasDeliveryProvider,
                $hasDeliveryPrice,
                $hasSubtotal,
                $hasMeta
            ) {
                foreach ($payouts as $payout) {
                    if (! $payout->order_id || ! $payout->shop_id) {
                        continue;
                    }

                    $items = DB::table('order_items')
                        ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                        ->where('order_items.order_id', $payout->order_id)
                        ->where(function ($query) use ($payout) {
                            $query->where('order_items.shop_id', $payout->shop_id)
                                ->orWhere(function ($legacy) use ($payout) {
                                    $legacy->whereNull('order_items.shop_id')
                                        ->where('products.shop_id', $payout->shop_id);
                                });
                        })
                        ->select([
                            'order_items.price',
                            'order_items.quantity',
                            ...($hasSubtotal ? ['order_items.subtotal'] : []),
                            ...($hasDeliveryPrice ? ['order_items.delivery_price'] : []),
                            ...($hasDeliveryProvider ? ['order_items.delivery_provider'] : []),
                        ])
                        ->get();

                    $productAmount = 0.0;
                    $sellerDeliveryAmount = 0.0;
                    $ovanieDeliveryAmount = 0.0;
                    $unassignedDeliveryAmount = 0.0;

                    foreach ($items as $item) {
                        $lineSubtotal = $hasSubtotal && $item->subtotal !== null
                            ? (float) $item->subtotal
                            : (float) $item->price * max(1, (int) $item->quantity);
                        $productAmount += $lineSubtotal;

                        $deliveryPrice = $hasDeliveryPrice
                            ? max(0, (float) ($item->delivery_price ?? 0))
                            : 0.0;
                        $provider = $hasDeliveryProvider
                            ? strtolower((string) ($item->delivery_provider ?? ''))
                            : '';

                        if ($provider === 'seller') {
                            $sellerDeliveryAmount += $deliveryPrice;
                        } elseif ($provider === 'ovanie') {
                            $ovanieDeliveryAmount += $deliveryPrice;
                        } else {
                            $unassignedDeliveryAmount += $deliveryPrice;
                        }
                    }

                    if ($unassignedDeliveryAmount > 0) {
                        $logisticsType = strtolower((string) DB::table('shops')
                            ->where('id', $payout->shop_id)
                            ->value('logistics_type'));

                        if ($logisticsType === 'seller') {
                            $sellerDeliveryAmount += $unassignedDeliveryAmount;
                        } else {
                            $ovanieDeliveryAmount += $unassignedDeliveryAmount;
                        }
                    }

                    $productAmount = round(max(0, $productAmount), 2);
                    $sellerDeliveryAmount = round(max(0, $sellerDeliveryAmount), 2);
                    $ovanieDeliveryAmount = round(max(0, $ovanieDeliveryAmount), 2);
                    $commissionAmount = round(max(0, (float) ($payout->commission_amount ?? 0)), 2);
                    $processingFee = round(max(0, (float) ($payout->processing_fee_amount ?? 0)), 2);
                    $netProductAmount = round(max(0, $productAmount - $commissionAmount), 2);
                    $totalAmount = round($productAmount + $sellerDeliveryAmount, 2);
                    $payoutAmount = round(max(0, $netProductAmount + $sellerDeliveryAmount - $processingFee), 2);

                    $update = [
                        'product_amount' => $productAmount,
                        'seller_delivery_amount' => $sellerDeliveryAmount,
                        'ovanie_delivery_amount' => $ovanieDeliveryAmount,
                        'net_product_amount' => $netProductAmount,
                        'total_amount' => $totalAmount,
                        'payout_amount' => $payoutAmount,
                        'updated_at' => now(),
                    ];

                    if ($hasMeta) {
                        $meta = [];
                        if (is_string($payout->meta ?? null) && $payout->meta !== '') {
                            $decoded = json_decode($payout->meta, true);
                            $meta = is_array($decoded) ? $decoded : [];
                        }

                        $meta['financial_breakdown'] = [
                            'product_amount' => $productAmount,
                            'commission_amount' => $commissionAmount,
                            'net_product_amount' => $netProductAmount,
                            'seller_delivery_amount' => $sellerDeliveryAmount,
                            'ovanie_delivery_amount' => $ovanieDeliveryAmount,
                            'processing_fee_amount' => $processingFee,
                            'payout_amount' => $payoutAmount,
                        ];
                        $meta['delivery_owner'] = $sellerDeliveryAmount > 0 ? 'seller' : 'ovanie';
                        $update['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    DB::table('vendor_payouts')->where('id', $payout->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_payouts')) {
            return;
        }

        Schema::table('vendor_payouts', function (Blueprint $table) {
            $columns = collect([
                'product_amount',
                'seller_delivery_amount',
                'ovanie_delivery_amount',
                'net_product_amount',
            ])->filter(fn (string $column) => Schema::hasColumn('vendor_payouts', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
