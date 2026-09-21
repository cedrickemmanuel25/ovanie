<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_reception_form_items')) {
            return;
        }

        Schema::table('order_reception_form_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_reception_form_items', 'site_commission')) {
                $table->decimal('site_commission', 15, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'vendor_gain')) {
                $table->decimal('vendor_gain', 15, 2)->default(0)->after('site_commission');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'courier_name')) {
                $table->string('courier_name')->nullable()->after('shop_name');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'courier_id_number')) {
                $table->string('courier_id_number')->nullable()->after('courier_name');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'courier_id_front_path')) {
                $table->string('courier_id_front_path')->nullable()->after('courier_id_number');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'courier_id_back_path')) {
                $table->string('courier_id_back_path')->nullable()->after('courier_id_front_path');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'payment_status')) {
                $table->string('payment_status', 40)->default('pending')->index()->after('received_time');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('order_reception_form_items', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_reception_form_items')) {
            return;
        }

        $columns = [
            'site_commission',
            'vendor_gain',
            'courier_name',
            'courier_id_number',
            'courier_id_front_path',
            'courier_id_back_path',
            'payment_status',
            'paid_at',
            'payment_reference',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('order_reception_form_items', $column)
        ));

        if ($existing) {
            Schema::table('order_reception_form_items', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
