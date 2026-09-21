<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30)->index();
                $table->integer('points');
                $table->unsignedInteger('balance_after');
                $table->string('reference')->unique();
                $table->string('description')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }


        if (! Schema::hasTable('vendor_payout_adjustments')) {
            Schema::create('vendor_payout_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_payout_id')->nullable()->constrained('vendor_payouts')->nullOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('return_id')->unique()->constrained('returns')->cascadeOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('type', 40)->default('refund_deduction');
                $table->string('status', 40)->default('applied')->index();
                $table->string('reference')->unique();
                $table->text('note')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'loyalty_points_used')) {
                    $table->unsignedInteger('loyalty_points_used')->default(0)->after('discount');
                }
                if (! Schema::hasColumn('orders', 'loyalty_discount')) {
                    $table->decimal('loyalty_discount', 15, 2)->default(0)->after('loyalty_points_used');
                }
            });
        }

        if (Schema::hasTable('payments') && Schema::hasTable('client_payment_methods')) {
            Schema::table('payments', function (Blueprint $table) {
                if (! Schema::hasColumn('payments', 'client_payment_method_id')) {
                    $table->foreignId('client_payment_method_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('client_payment_methods')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'client_payment_method_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_payment_method_id');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = array_values(array_filter(
                    ['loyalty_points_used', 'loyalty_discount'],
                    fn (string $column) => Schema::hasColumn('orders', $column)
                ));

                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('vendor_payout_adjustments');
        Schema::dropIfExists('loyalty_transactions');
    }
};
