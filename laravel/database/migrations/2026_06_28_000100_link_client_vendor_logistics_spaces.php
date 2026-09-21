<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'delivery_provider', fn () => $table->string('delivery_provider', 40)->nullable()->index());
                $this->addColumnIfMissing($table, 'delivery_mode', fn () => $table->string('delivery_mode', 40)->nullable());
                $this->addColumnIfMissing($table, 'delivery_status', fn () => $table->string('delivery_status', 60)->nullable()->index());
                $this->addColumnIfMissing($table, 'delivery_delay', fn () => $table->string('delivery_delay', 80)->nullable());
                $this->addColumnIfMissing($table, 'delivery_price', fn () => $table->decimal('delivery_price', 14, 2)->default(0));
                $this->addColumnIfMissing($table, 'delivery_service_id', fn () => $table->unsignedBigInteger('delivery_service_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'delivery_zone_id', fn () => $table->unsignedBigInteger('delivery_zone_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'seller_ready_for_pickup_at', fn () => $table->timestamp('seller_ready_for_pickup_at')->nullable());
                $this->addColumnIfMissing($table, 'logistics_assigned_at', fn () => $table->timestamp('logistics_assigned_at')->nullable());
                $this->addColumnIfMissing($table, 'picked_up_at', fn () => $table->timestamp('picked_up_at')->nullable());
                $this->addColumnIfMissing($table, 'delivery_completed_at', fn () => $table->timestamp('delivery_completed_at')->nullable());
                $this->addColumnIfMissing($table, 'delivery_failed_at', fn () => $table->timestamp('delivery_failed_at')->nullable());
                $this->addColumnIfMissing($table, 'delivery_failure_reason', fn () => $table->text('delivery_failure_reason')->nullable());
                $this->addColumnIfMissing($table, 'reception_status', fn () => $table->string('reception_status', 50)->default('waiting')->index());
                $this->addColumnIfMissing($table, 'reception_confirmed_at', fn () => $table->timestamp('reception_confirmed_at')->nullable());
                $this->addColumnIfMissing($table, 'payout_status', fn () => $table->string('payout_status', 50)->default('not_ready')->index());
            });

            // Rattrapage compatible avec les commandes déjà existantes.
            // Cette section est volontairement prudente : certains serveurs cPanel
            // peuvent avoir une table products/shops légèrement différente.
            $statusSql = Schema::hasColumn('order_items', 'vendor_delivery_status')
                ? "CASE WHEN order_items.vendor_delivery_status = 'delivered' THEN 'delivered' WHEN order_items.vendor_delivery_status = 'in_delivery' THEN 'in_transit' ELSE 'pending' END"
                : "'pending'";

            $canBackfillFromShop = DB::connection()->getDriverName() !== 'sqlite'
                && Schema::hasTable('products')
                && Schema::hasTable('shops')
                && Schema::hasColumn('order_items', 'product_id')
                && Schema::hasColumn('order_items', 'shop_id')
                && Schema::hasColumn('products', 'shop_id')
                && Schema::hasColumn('shops', 'logistics_type');

            if ($canBackfillFromShop) {
                DB::table('order_items')
                    ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
                    ->leftJoin('shops', function ($join) {
                        $join->on('order_items.shop_id', '=', 'shops.id')
                            ->orOn('products.shop_id', '=', 'shops.id');
                    })
                    ->whereNull('order_items.delivery_provider')
                    ->update([
                        'order_items.delivery_provider' => DB::raw("CASE WHEN shops.logistics_type = 'ovanie' THEN 'ovanie' ELSE 'seller' END"),
                        'order_items.delivery_mode' => DB::raw("CASE WHEN shops.logistics_type = 'ovanie' THEN 'ovanie' ELSE 'seller' END"),
                        'order_items.delivery_status' => DB::raw($statusSql),
                        'order_items.delivery_delay' => DB::raw("CASE WHEN shops.logistics_type = 'ovanie' THEN NULL ELSE '24h' END"),
                    ]);
            } else {
                DB::table('order_items')
                    ->whereNull('delivery_provider')
                    ->update([
                        'delivery_provider' => 'seller',
                        'delivery_mode' => 'seller',
                        'delivery_status' => DB::raw($statusSql),
                        'delivery_delay' => '24h',
                    ]);
            }
        }

        if (Schema::hasTable('returns')) {
            Schema::table('returns', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'order_id', fn () => $table->unsignedBigInteger('order_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'order_item_id', fn () => $table->unsignedBigInteger('order_item_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'client_id', fn () => $table->unsignedBigInteger('client_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'vendor_id', fn () => $table->unsignedBigInteger('vendor_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'shop_id', fn () => $table->unsignedBigInteger('shop_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'product_id', fn () => $table->unsignedBigInteger('product_id')->nullable()->index());
                $this->addColumnIfMissing($table, 'quantity', fn () => $table->integer('quantity')->nullable());
                $this->addColumnIfMissing($table, 'photo_proof', fn () => $table->string('photo_proof')->nullable());
                $this->addColumnIfMissing($table, 'refund_amount', fn () => $table->decimal('refund_amount', 14, 2)->nullable());
                $this->addColumnIfMissing($table, 'return_type', fn () => $table->string('return_type', 60)->default('return'));
                $this->addColumnIfMissing($table, 'logistics_status', fn () => $table->string('logistics_status', 60)->default('not_required'));
                $this->addColumnIfMissing($table, 'vendor_response', fn () => $table->text('vendor_response')->nullable());
                $this->addColumnIfMissing($table, 'resolved_at', fn () => $table->timestamp('resolved_at')->nullable());
            });
        }

        if (! Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('actor_type', 40)->default('system')->index();
                $table->string('status_type', 40)->default('order')->index();
                $table->string('old_status', 80)->nullable();
                $table->string('new_status', 80)->nullable();
                $table->string('label')->nullable();
                $table->text('message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_assignments')) {
            Schema::create('delivery_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_by')->nullable()->index();
                $table->string('status', 60)->default('assigned')->index();
                $table->string('pickup_address')->nullable();
                $table->string('delivery_address')->nullable();
                $table->timestamp('pickup_scheduled_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('delivery_proofs')) {
            Schema::create('delivery_proofs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->string('delivery_provider', 40)->default('ovanie')->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->string('receiver_name')->nullable();
                $table->string('receiver_phone', 60)->nullable();
                $table->string('proof_photo')->nullable();
                $table->string('signature_path')->nullable();
                $table->text('delivery_note')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('order_status_histories');
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($table->getTable(), $column)) {
            $definition();
        }
    }
};
