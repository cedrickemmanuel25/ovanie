<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payment_verification_requests')) {
            Schema::create('vendor_payment_verification_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->string('payment_method', 50)->default('cash');
                $table->decimal('amount_claimed', 15, 2)->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('vendor_note')->nullable();
                $table->text('admin_note')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'shop_id', 'status'], 'vpvr_order_shop_status_idx');
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'vendor_payment_status')) {
                    $table->string('vendor_payment_status', 50)->default('unpaid')->after('subtotal');
                }

                if (! Schema::hasColumn('order_items', 'vendor_payment_verified_at')) {
                    $table->timestamp('vendor_payment_verified_at')->nullable()->after('vendor_payment_status');
                }

                if (! Schema::hasColumn('order_items', 'vendor_payment_note')) {
                    $table->text('vendor_payment_note')->nullable()->after('vendor_payment_verified_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['vendor_payment_status', 'vendor_payment_verified_at', 'vendor_payment_note'] as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('vendor_payment_verification_requests');
    }
};
