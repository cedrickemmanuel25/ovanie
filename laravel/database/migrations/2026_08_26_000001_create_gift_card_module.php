<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('family')->default('fixed'); // fixed | rechargeable
            $table->decimal('face_value', 14, 2)->default(0);
            $table->decimal('activation_price', 14, 2)->default(0);
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedInteger('validity_months')->nullable();
            $table->boolean('is_rechargeable')->default(false);
            $table->decimal('max_total_recharge', 14, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['family', 'is_active', 'sort_order']);
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_product_id')->nullable()->constrained('gift_card_products')->nullOnDelete();
            $table->foreignId('purchaser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_email')->nullable();
            $table->string('beneficiary_phone', 40)->nullable();
            $table->string('code', 64)->unique();
            $table->string('pin_hash');
            $table->text('pin_encrypted')->nullable();
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->decimal('reserved_balance', 14, 2)->default(0);
            $table->decimal('total_recharged', 14, 2)->default(0);
            $table->string('currency', 8)->default('XOF');
            $table->string('status', 30)->default('active'); // pending | active | blocked | expired | exhausted
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
            $table->index(['purchaser_user_id', 'status']);
            $table->index('expires_at');
        });

        Schema::create('gift_card_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_product_id')->constrained('gift_card_products');
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('gift_card_id')->nullable()->constrained('gift_cards')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->text('personal_message')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 30)->default('pending'); // pending | paid | failed | cancelled
            $table->string('payment_method', 40)->default('paydunya');
            $table->string('provider_token')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_user_id', 'status']);
        });

        Schema::create('gift_card_recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 30)->default('pending');
            $table->string('provider_token')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gift_card_id', 'status']);
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type', 40); // activation_credit | recharge_credit | order_hold | order_debit | hold_release | refund_credit | adjustment
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('balance_before', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->decimal('reserved_before', 14, 2)->default(0);
            $table->decimal('reserved_after', 14, 2)->default(0);
            $table->string('reference')->nullable()->unique();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['gift_card_id', 'created_at']);
            $table->index(['order_id', 'type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'gift_card_id')) {
                $table->foreignId('gift_card_id')->nullable()->constrained('gift_cards')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'gift_card_amount')) {
                $table->decimal('gift_card_amount', 14, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'gift_card_id')) {
                $table->dropConstrainedForeignId('gift_card_id');
            }
            if (Schema::hasColumn('orders', 'gift_card_amount')) {
                $table->dropColumn('gift_card_amount');
            }
        });

        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_card_recharges');
        Schema::dropIfExists('gift_card_purchases');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('gift_card_products');
    }
};
