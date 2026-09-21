<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payouts')) {
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

            return;
        }

        Schema::table('vendor_payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payouts', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('vendor_payouts', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            }

            if (! Schema::hasColumn('vendor_payouts', 'order_id')) {
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            }

            if (! Schema::hasColumn('vendor_payouts', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('vendor_payouts', 'commission_amount')) {
                $table->decimal('commission_amount', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('vendor_payouts', 'payout_amount')) {
                $table->decimal('payout_amount', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('vendor_payouts', 'phone')) {
                $table->string('phone', 30)->nullable();
            }

            if (! Schema::hasColumn('vendor_payouts', 'payment_method')) {
                $table->string('payment_method', 50)->nullable();
            }

            if (! Schema::hasColumn('vendor_payouts', 'payout_reference')) {
                $table->string('payout_reference', 100)->nullable();
            }

            if (! Schema::hasColumn('vendor_payouts', 'batch_reference')) {
                $table->string('batch_reference', 100)->nullable();
            }

            if (! Schema::hasColumn('vendor_payouts', 'status')) {
                $table->string('status', 30)->default('pending');
            }

            if (! Schema::hasColumn('vendor_payouts', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Migration sécurisée : ne supprime pas la table pour éviter de perdre l'historique financier.
    }
};
