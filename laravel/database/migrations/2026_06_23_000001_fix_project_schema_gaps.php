<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'bank_reference')) {
                $table->string('bank_reference')->nullable();
            }

            if (!Schema::hasColumn('orders', 'receipt_path')) {
                $table->string('receipt_path')->nullable();
            }

            if (!Schema::hasColumn('orders', 'payment_proof')) {
                $table->string('payment_proof')->nullable();
            }
        });

        Schema::table('disputes', function (Blueprint $table) {
            if (!Schema::hasColumn('disputes', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('disputes', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('response');
            }

            if (!Schema::hasColumn('disputes', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('escalated');
            }
        });

        if (!Schema::hasTable('vendor_payouts')) {
            Schema::create('vendor_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('payout_amount', 12, 2)->default(0);
                $table->string('phone', 30)->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('payout_reference', 100)->nullable();
                $table->string('batch_reference', 100)->nullable();
                $table->string('status', 30)->default('pending');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        $this->widenOrderEnums();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [];
            foreach (['subtotal', 'delivery_fee', 'bank_reference', 'receipt_path', 'payment_proof'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'vendor_id')) {
                $table->dropConstrainedForeignId('vendor_id');
            }

            $columns = [];
            foreach (['responded_at', 'escalated_at'] as $column) {
                if (Schema::hasColumn('disputes', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }

    private function widenOrderEnums(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','paid','processing','confirmed','shipped','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending','paid','rejected','failed','commission_paid','cod_completed') NOT NULL DEFAULT 'pending'");
    }
};
