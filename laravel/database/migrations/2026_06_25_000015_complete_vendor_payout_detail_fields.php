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
                $table->string('payment_channel', 50)->nullable();
                $table->string('payout_reference', 100)->nullable();
                $table->string('batch_reference', 100)->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processing_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->date('expected_payment_date')->nullable();
                $table->timestamp('vendor_followup_requested_at')->nullable();
                $table->text('vendor_note')->nullable();
                $table->text('admin_note')->nullable();
                $table->string('transfer_receipt_path')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('vendor_payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payouts', 'payment_channel')) {
                $table->string('payment_channel', 50)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('vendor_payouts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('vendor_payouts', 'processing_at')) {
                $table->timestamp('processing_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('processing_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'expected_payment_date')) {
                $table->date('expected_payment_date')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'vendor_followup_requested_at')) {
                $table->timestamp('vendor_followup_requested_at')->nullable()->after('expected_payment_date');
            }

            if (! Schema::hasColumn('vendor_payouts', 'vendor_note')) {
                $table->text('vendor_note')->nullable()->after('vendor_followup_requested_at');
            }

            if (! Schema::hasColumn('vendor_payouts', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('vendor_note');
            }

            if (! Schema::hasColumn('vendor_payouts', 'transfer_receipt_path')) {
                $table->string('transfer_receipt_path')->nullable()->after('admin_note');
            }
        });
    }

    public function down(): void
    {
        // Migration volontairement non destructive pour préserver l'historique financier.
    }
};
