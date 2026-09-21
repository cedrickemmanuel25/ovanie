<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->completeOrders();
        $this->completeOrderItems();
        $this->completeShipments();
        $this->createOrderStatusHistories();
        $this->createShipmentStatusHistories();
        $this->createDeliveryAssignments();
        $this->createDeliveryProofs();
        $this->completeReturns();
        $this->completeVendorPayouts();
        $this->seedOvanieDeliveryMethods();
    }

    public function down(): void
    {
        // Migration volontairement non destructive.
        // Ces tables contiennent des preuves, historiques, retours et informations financières.
    }

    private function completeOrders(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $this->stringColumn($table, 'invoice_number', 100);
            $this->decimalColumn($table, 'subtotal');
            $this->decimalColumn($table, 'delivery_fee');
            $this->jsonColumn($table, 'delivery_breakdown');
            $this->jsonColumn($table, 'selected_carriers');
            $this->stringColumn($table, 'bank_reference', 255);
            $this->stringColumn($table, 'receipt_path', 255);
            $this->stringColumn($table, 'payment_proof', 255);
            $this->stringColumn($table, 'carrier', 255);
            $this->stringColumn($table, 'tracking_number', 255);
            $this->timestampColumn($table, 'shipment_date');
            $this->textColumn($table, 'notes');
            $this->decimalColumn($table, 'discount');
            $this->stringColumn($table, 'phone', 50);
            $this->textColumn($table, 'address');
            $this->stringColumn($table, 'delivery_zone', 50);
            $this->stringColumn($table, 'delivery_destination_type', 50);
            $this->stringColumn($table, 'delivery_site_name', 255);
            $this->stringColumn($table, 'delivery_recipient_name', 255);
            $this->stringColumn($table, 'delivery_recipient_phone', 50);
            $this->stringColumn($table, 'delivery_commune', 150);
            $this->stringColumn($table, 'delivery_quartier', 150);
            $this->stringColumn($table, 'delivery_city', 150);
            $this->decimalColumn($table, 'delivery_lat', 13, 8);
            $this->decimalColumn($table, 'delivery_lng', 13, 8);
            $this->timestampColumn($table, 'delivery_started_at');
            $this->timestampColumn($table, 'delivery_min_date');
            $this->timestampColumn($table, 'delivery_max_date');
            $this->textColumn($table, 'delivery_note');
            $this->stringColumn($table, 'delivery_provider', 50);
            $this->stringColumn($table, 'delivery_status', 50);
            $this->stringColumn($table, 'reception_status', 50);
            $this->stringColumn($table, 'payout_status', 50);
        });
    }

    private function completeOrderItems(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $this->unsignedBigIntegerColumn($table, 'shipment_id');
            $this->stringColumn($table, 'vendor_status', 50);
            $this->textColumn($table, 'vendor_status_note');
            $this->timestampColumn($table, 'vendor_status_updated_at');
            $this->timestampColumn($table, 'vendor_confirmed_at');
            $this->timestampColumn($table, 'vendor_prepared_at');
            $this->timestampColumn($table, 'vendor_shipped_at');
            $this->timestampColumn($table, 'vendor_delivered_at');
            $this->timestampColumn($table, 'vendor_cancelled_at');
            $this->textColumn($table, 'vendor_cancel_reason');
            $this->stringColumn($table, 'vendor_carrier', 150);
            $this->stringColumn($table, 'vendor_tracking_number', 150);
            $this->timestampColumn($table, 'vendor_shipment_date');
            $this->stringColumn($table, 'vendor_delivery_status', 50, 'pending');
            $this->timestampColumn($table, 'vendor_delivery_updated_at');
            $this->textColumn($table, 'vendor_delivery_note');
            $this->stringColumn($table, 'driver_name', 150);
            $this->stringColumn($table, 'driver_phone', 50);
            $this->stringColumn($table, 'vehicle_plate', 80);
            $this->decimalColumn($table, 'driver_latitude', 10, 8);
            $this->decimalColumn($table, 'driver_longitude', 11, 8);
            $this->timestampColumn($table, 'driver_location_updated_at');
            $this->stringColumn($table, 'pickup_photo', 255);
            $this->stringColumn($table, 'delivery_photo', 255);
            $this->stringColumn($table, 'delivery_otp_code', 20);
            $this->timestampColumn($table, 'delivery_otp_verified_at');
            $this->stringColumn($table, 'delivery_provider', 50);
            $this->stringColumn($table, 'delivery_mode', 50);
            $this->stringColumn($table, 'delivery_status', 50, 'pending');
            $this->stringColumn($table, 'delivery_delay', 100);
            $this->decimalColumn($table, 'delivery_price');
            $this->unsignedBigIntegerColumn($table, 'delivery_service_id');
            $this->unsignedBigIntegerColumn($table, 'delivery_zone_id');
            $this->timestampColumn($table, 'seller_ready_for_pickup_at');
            $this->timestampColumn($table, 'logistics_assigned_at');
            $this->timestampColumn($table, 'picked_up_at');
            $this->timestampColumn($table, 'delivery_completed_at');
            $this->timestampColumn($table, 'delivery_failed_at');
            $this->textColumn($table, 'delivery_failure_reason');
            $this->stringColumn($table, 'reception_status', 50, 'waiting');
            $this->timestampColumn($table, 'reception_confirmed_at');
            $this->stringColumn($table, 'payout_status', 50, 'not_ready');
            $this->timestampColumn($table, 'payout_ready_at');
            $this->stringColumn($table, 'return_status', 50);
            $this->booleanColumn($table, 'is_paid');
        });

        $this->safeIndex('order_items', ['order_id', 'shop_id'], 'oi_order_shop_idx');
        $this->safeIndex('order_items', ['delivery_provider', 'delivery_status'], 'oi_delivery_provider_status_idx');
        $this->safeIndex('order_items', ['reception_status', 'payout_status'], 'oi_reception_payout_idx');
    }

    private function completeShipments(): void
    {
        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'carrier_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'delivery_service_id');
                $this->unsignedBigIntegerColumn($table, 'order_delivery_selection_id');
                $this->stringColumn($table, 'tracking_number', 120);
                $this->stringColumn($table, 'provider_type', 50, 'ovanie');
                $this->stringColumn($table, 'service_code', 100);
                $this->stringColumn($table, 'status', 50, 'pending');
                $this->timestampColumn($table, 'estimated_delivery_at');
                $this->timestampColumn($table, 'delivered_at');
                $this->decimalColumn($table, 'final_price');
                $this->jsonColumn($table, 'meta');
                $table->timestamps();
            });
        } else {
            Schema::table('shipments', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'carrier_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'delivery_service_id');
                $this->unsignedBigIntegerColumn($table, 'order_delivery_selection_id');
                $this->stringColumn($table, 'tracking_number', 120);
                $this->stringColumn($table, 'provider_type', 50, 'ovanie');
                $this->stringColumn($table, 'service_code', 100);
                $this->stringColumn($table, 'status', 50, 'pending');
                $this->timestampColumn($table, 'estimated_delivery_at');
                $this->timestampColumn($table, 'delivered_at');
                $this->decimalColumn($table, 'final_price');
                $this->jsonColumn($table, 'meta');
            });
        }

        $this->safeIndex('shipments', ['order_id', 'status'], 'shipments_order_status_idx');
        $this->safeIndex('shipments', ['order_item_id', 'status'], 'shipments_item_status_idx');
        $this->safeIndex('shipments', ['provider_type', 'status'], 'shipments_provider_status_idx');
    }

    private function createOrderStatusHistories(): void
    {
        if (Schema::hasTable('order_status_histories')) {
            return;
        }

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $this->unsignedBigIntegerColumn($table, 'order_id');
            $this->unsignedBigIntegerColumn($table, 'order_item_id');
            $this->unsignedBigIntegerColumn($table, 'user_id');
            $this->stringColumn($table, 'actor_type', 50, 'system');
            $this->stringColumn($table, 'status_type', 50);
            $this->stringColumn($table, 'old_status', 80);
            $this->stringColumn($table, 'new_status', 80);
            $this->stringColumn($table, 'label', 180);
            $this->textColumn($table, 'message');
            $this->jsonColumn($table, 'metadata');
            $table->timestamps();
        });

        $this->safeIndex('order_status_histories', ['order_id', 'status_type'], 'osh_order_type_idx');
        $this->safeIndex('order_status_histories', ['order_item_id', 'status_type'], 'osh_item_type_idx');
    }

    private function createShipmentStatusHistories(): void
    {
        if (Schema::hasTable('shipment_status_histories')) {
            return;
        }

        Schema::create('shipment_status_histories', function (Blueprint $table) {
            $table->id();
            $this->unsignedBigIntegerColumn($table, 'shipment_id');
            $this->stringColumn($table, 'status', 80, 'pending');
            $this->stringColumn($table, 'label', 180);
            $this->textColumn($table, 'note');
            $this->unsignedBigIntegerColumn($table, 'created_by');
            $this->jsonColumn($table, 'meta');
            $table->timestamps();
        });

        $this->safeIndex('shipment_status_histories', ['shipment_id', 'status'], 'ssh_shipment_status_idx');
    }

    private function createDeliveryAssignments(): void
    {
        if (! Schema::hasTable('delivery_assignments')) {
            Schema::create('delivery_assignments', function (Blueprint $table) {
                $table->id();
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'driver_id');
                $this->unsignedBigIntegerColumn($table, 'vehicle_id');
                $this->unsignedBigIntegerColumn($table, 'assigned_by');
                $this->stringColumn($table, 'status', 50, 'assigned');
                $this->textColumn($table, 'pickup_address');
                $this->textColumn($table, 'delivery_address');
                $this->timestampColumn($table, 'pickup_scheduled_at');
                $this->timestampColumn($table, 'picked_up_at');
                $this->timestampColumn($table, 'delivered_at');
                $this->jsonColumn($table, 'meta');
                $table->timestamps();
            });
        } else {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'driver_id');
                $this->unsignedBigIntegerColumn($table, 'vehicle_id');
                $this->unsignedBigIntegerColumn($table, 'assigned_by');
                $this->stringColumn($table, 'status', 50, 'assigned');
                $this->textColumn($table, 'pickup_address');
                $this->textColumn($table, 'delivery_address');
                $this->timestampColumn($table, 'pickup_scheduled_at');
                $this->timestampColumn($table, 'picked_up_at');
                $this->timestampColumn($table, 'delivered_at');
                $this->jsonColumn($table, 'meta');
            });
        }

        $this->safeIndex('delivery_assignments', ['order_item_id', 'status'], 'da_item_status_idx');
        $this->safeIndex('delivery_assignments', ['driver_id', 'status'], 'da_driver_status_idx');
    }

    private function createDeliveryProofs(): void
    {
        if (! Schema::hasTable('delivery_proofs')) {
            Schema::create('delivery_proofs', function (Blueprint $table) {
                $table->id();
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->stringColumn($table, 'delivery_provider', 50);
                $this->unsignedBigIntegerColumn($table, 'driver_id');
                $this->stringColumn($table, 'receiver_name', 180);
                $this->stringColumn($table, 'receiver_phone', 50);
                $this->stringColumn($table, 'proof_photo', 255);
                $this->stringColumn($table, 'signature_path', 255);
                $this->textColumn($table, 'delivery_note');
                $this->timestampColumn($table, 'delivered_at');
                $this->jsonColumn($table, 'meta');
                $table->timestamps();
            });
        } else {
            Schema::table('delivery_proofs', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->stringColumn($table, 'delivery_provider', 50);
                $this->unsignedBigIntegerColumn($table, 'driver_id');
                $this->stringColumn($table, 'receiver_name', 180);
                $this->stringColumn($table, 'receiver_phone', 50);
                $this->stringColumn($table, 'proof_photo', 255);
                $this->stringColumn($table, 'signature_path', 255);
                $this->textColumn($table, 'delivery_note');
                $this->timestampColumn($table, 'delivered_at');
                $this->jsonColumn($table, 'meta');
            });
        }

        $this->safeIndex('delivery_proofs', ['order_item_id', 'delivery_provider'], 'dp_item_provider_idx');
    }

    private function completeReturns(): void
    {
        if (! Schema::hasTable('returns')) {
            Schema::create('returns', function (Blueprint $table) {
                $table->id();
                $this->stringColumn($table, 'order_reference', 120);
                $this->stringColumn($table, 'product_name', 255);
                $this->textColumn($table, 'reason');
                $table->date('request_date')->nullable();
                $this->stringColumn($table, 'status', 50, 'pending');
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'client_id');
                $this->unsignedBigIntegerColumn($table, 'vendor_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'product_id');
                $table->unsignedInteger('quantity')->default(1);
                $this->stringColumn($table, 'photo_proof', 255);
                $this->decimalColumn($table, 'refund_amount');
                $this->stringColumn($table, 'return_type', 50, 'return');
                $this->stringColumn($table, 'logistics_status', 60, 'not_required');
                $this->textColumn($table, 'vendor_response');
                $this->timestampColumn($table, 'resolved_at');
                $table->timestamps();
            });
        } else {
            try {
                DB::statement("ALTER TABLE `returns` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
            } catch (Throwable $e) {
                // Certaines bases n'utilisent pas MySQL ou la colonne est déjà compatible.
            }

            Schema::table('returns', function (Blueprint $table) {
                $this->stringColumn($table, 'order_reference', 120);
                $this->stringColumn($table, 'product_name', 255);
                $this->textColumn($table, 'reason');
                if (! Schema::hasColumn('returns', 'request_date')) {
                    $table->date('request_date')->nullable();
                }
                $this->stringColumn($table, 'status', 50, 'pending');
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->unsignedBigIntegerColumn($table, 'order_item_id');
                $this->unsignedBigIntegerColumn($table, 'client_id');
                $this->unsignedBigIntegerColumn($table, 'vendor_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'product_id');
                if (! Schema::hasColumn('returns', 'quantity')) {
                    $table->unsignedInteger('quantity')->default(1);
                }
                $this->stringColumn($table, 'photo_proof', 255);
                $this->decimalColumn($table, 'refund_amount');
                $this->stringColumn($table, 'return_type', 50, 'return');
                $this->stringColumn($table, 'logistics_status', 60, 'not_required');
                $this->textColumn($table, 'vendor_response');
                $this->timestampColumn($table, 'resolved_at');
            });
        }

        $this->safeIndex('returns', ['order_id', 'order_item_id'], 'returns_order_item_idx');
        $this->safeIndex('returns', ['shop_id', 'status'], 'returns_shop_status_idx');
        $this->safeIndex('returns', ['client_id', 'status'], 'returns_client_status_idx');
    }

    private function completeVendorPayouts(): void
    {
        if (! Schema::hasTable('vendor_payouts')) {
            Schema::create('vendor_payouts', function (Blueprint $table) {
                $table->id();
                $this->unsignedBigIntegerColumn($table, 'vendor_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->decimalColumn($table, 'total_amount');
                $this->decimalColumn($table, 'commission_amount');
                $this->decimalColumn($table, 'payout_amount');
                $this->stringColumn($table, 'phone', 50);
                $this->stringColumn($table, 'payment_method', 50);
                $this->stringColumn($table, 'payment_channel', 50);
                $this->stringColumn($table, 'payout_reference', 120);
                $this->stringColumn($table, 'batch_reference', 120);
                $this->stringColumn($table, 'status', 50, 'blocked');
                $this->timestampColumn($table, 'approved_at');
                $this->timestampColumn($table, 'processing_at');
                $this->timestampColumn($table, 'paid_at');
                $this->timestampColumn($table, 'failed_at');
                $this->timestampColumn($table, 'cancelled_at');
                if (! Schema::hasColumn('vendor_payouts', 'expected_payment_date')) {
                    $table->date('expected_payment_date')->nullable();
                }
                $this->timestampColumn($table, 'vendor_followup_requested_at');
                $this->textColumn($table, 'vendor_note');
                $this->textColumn($table, 'admin_note');
                $this->stringColumn($table, 'transfer_receipt_path', 255);
                $this->jsonColumn($table, 'meta');
                $table->timestamps();
            });
        } else {
            try {
                DB::statement("ALTER TABLE `vendor_payouts` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'blocked'");
            } catch (Throwable $e) {}

            Schema::table('vendor_payouts', function (Blueprint $table) {
                $this->unsignedBigIntegerColumn($table, 'vendor_id');
                $this->unsignedBigIntegerColumn($table, 'shop_id');
                $this->unsignedBigIntegerColumn($table, 'order_id');
                $this->decimalColumn($table, 'total_amount');
                $this->decimalColumn($table, 'commission_amount');
                $this->decimalColumn($table, 'payout_amount');
                $this->stringColumn($table, 'phone', 50);
                $this->stringColumn($table, 'payment_method', 50);
                $this->stringColumn($table, 'payment_channel', 50);
                $this->stringColumn($table, 'payout_reference', 120);
                $this->stringColumn($table, 'batch_reference', 120);
                $this->stringColumn($table, 'status', 50, 'blocked');
                $this->timestampColumn($table, 'approved_at');
                $this->timestampColumn($table, 'processing_at');
                $this->timestampColumn($table, 'paid_at');
                $this->timestampColumn($table, 'failed_at');
                $this->timestampColumn($table, 'cancelled_at');
                if (! Schema::hasColumn('vendor_payouts', 'expected_payment_date')) {
                    $table->date('expected_payment_date')->nullable();
                }
                $this->timestampColumn($table, 'vendor_followup_requested_at');
                $this->textColumn($table, 'vendor_note');
                $this->textColumn($table, 'admin_note');
                $this->stringColumn($table, 'transfer_receipt_path', 255);
                $this->jsonColumn($table, 'meta');
            });
        }

        $this->safeIndex('vendor_payouts', ['order_id', 'shop_id'], 'vp_order_shop_idx');
        $this->safeIndex('vendor_payouts', ['vendor_id', 'status'], 'vp_vendor_status_idx');
    }

    private function seedOvanieDeliveryMethods(): void
    {
        if (! Schema::hasTable('delivery_services')) {
            return;
        }

        try {
            DB::table('delivery_services')
                ->where('provider_type', 'ovanie')
                ->whereIn('code', ['ovanie-priority', 'ovanie-standard'])
                ->update(['is_active' => false, 'updated_at' => now()]);
        } catch (Throwable $e) {}

        $services = [
            ['code' => 'ovanie-express', 'name' => 'OVANIE Express', 'description' => 'Colis rapide.', 'estimated_hours' => 24, 'sort_order' => 10, 'vehicle_type' => 'Colis rapide'],
            ['code' => 'ovanie-raider', 'name' => 'OVANIE Raider', 'description' => 'Livraison à moto.', 'estimated_hours' => 24, 'sort_order' => 20, 'vehicle_type' => 'Moto'],
            ['code' => 'ovanie-pickup', 'name' => 'OVANIE Pickup', 'description' => 'Tricycle ou fourgonnette.', 'estimated_hours' => 48, 'sort_order' => 30, 'vehicle_type' => 'Tricycle / Fourgonnette'],
            ['code' => 'ovanie-cargo', 'name' => 'OVANIE Cargo', 'description' => 'Matériaux gros volume.', 'estimated_hours' => 72, 'sort_order' => 40, 'vehicle_type' => 'Camion / Cargo'],
            ['code' => 'ovanie-pro', 'name' => 'OVANIE Pro', 'description' => 'Livraison sur chantier.', 'estimated_hours' => 72, 'sort_order' => 50, 'vehicle_type' => 'Chantier'],
        ];

        foreach ($services as $service) {
            $payload = [
                'provider_type' => 'ovanie',
                'name' => $service['name'],
                'description' => $service['description'],
                'estimated_hours' => $service['estimated_hours'],
                'min_estimated_hours' => null,
                'max_estimated_hours' => $service['estimated_hours'],
                'sort_order' => $service['sort_order'],
                'is_active' => true,
                'meta' => json_encode([
                    'quote_required' => true,
                    'vehicle_type' => $service['vehicle_type'],
                    'public_price_label' => 'Livraison calculee a l etape suivante',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            $existing = DB::table('delivery_services')->where('code', $service['code'])->first();

            if ($existing) {
                DB::table('delivery_services')->where('id', $existing->id)->update($payload);
            } else {
                $payload['code'] = $service['code'];
                $payload['created_at'] = now();
                DB::table('delivery_services')->insert($payload);
            }
        }
    }

    private function stringColumn(Blueprint $table, string $name, int $length = 255, ?string $default = null): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $column = $table->string($name, $length)->nullable();
            if ($default !== null) {
                $column->default($default);
            }
        }
    }

    private function textColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->text($name)->nullable();
        }
    }

    private function jsonColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->json($name)->nullable();
        }
    }

    private function timestampColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->timestamp($name)->nullable();
        }
    }

    private function decimalColumn(Blueprint $table, string $name, int $precision = 12, int $scale = 2): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->decimal($name, $precision, $scale)->default(0);
        }
    }

    private function unsignedBigIntegerColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->unsignedBigInteger($name)->nullable()->index();
        }
    }

    private function booleanColumn(Blueprint $table, string $name): void
    {
        if (! Schema::hasColumn($table->getTable(), $name)) {
            $table->boolean($name)->default(false);
        }
    }

    private function safeIndex(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (Throwable $e) {
            // Index déjà existant ou colonne non compatible : on ne bloque pas la migration.
        }
    }
};
