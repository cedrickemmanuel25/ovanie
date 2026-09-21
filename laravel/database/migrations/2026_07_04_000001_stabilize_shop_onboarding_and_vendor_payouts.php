<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shops')) {
            // L'ancien ENUM MySQL empêchait d'enregistrer certains états historiques.
            // Désormais, status représente uniquement l'activation générale de la boutique.
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE shops MODIFY status VARCHAR(30) NOT NULL DEFAULT 'approved'");
            }

            Schema::table('shops', function (Blueprint $table) {
                if (! Schema::hasColumn('shops', 'kyc_status')) {
                    $table->string('kyc_status', 30)->default('pending')->after('status')->index();
                }

                if (! Schema::hasColumn('shops', 'logistics_status')) {
                    $table->string('logistics_status', 30)->default('ready')->after('kyc_status')->index();
                }

                if (! Schema::hasColumn('shops', 'payout_onboarding_started_at')) {
                    $table->timestamp('payout_onboarding_started_at')->nullable()->after('payment_mode');
                }
            });

            DB::table('shops')
                ->whereIn('status', ['pending', 'pending_ovanie_validation', 'pending_logistics_configuration'])
                ->update([
                    'status' => 'approved',
                    'is_active' => true,
                    'approved_at' => DB::raw('COALESCE(approved_at, CURRENT_TIMESTAMP)'),
                ]);

            DB::table('shops')
                ->where('logistics_type', 'seller')
                ->update(['logistics_status' => 'incomplete']);

            DB::table('shops')
                ->where(function ($query) {
                    $query->whereNull('logistics_type')
                        ->orWhere('logistics_type', '!=', 'seller');
                })
                ->update(['logistics_status' => 'ready']);

            DB::table('shops')
                ->whereNull('payout_onboarding_started_at')
                ->update(['payout_onboarding_started_at' => DB::raw('created_at')]);
        }

        if (Schema::hasTable('vendor_payouts')) {
            Schema::table('vendor_payouts', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_payouts', 'processing_fee_amount')) {
                    $table->decimal('processing_fee_amount', 12, 2)->default(0)->after('commission_amount');
                }

                if (! Schema::hasColumn('vendor_payouts', 'payment_mode_snapshot')) {
                    $table->string('payment_mode_snapshot', 30)->nullable()->after('payment_method');
                }

                if (! Schema::hasColumn('vendor_payouts', 'eligible_at')) {
                    $table->timestamp('eligible_at')->nullable()->after('cancelled_at');
                }

                if (! Schema::hasColumn('vendor_payouts', 'scheduled_for')) {
                    $table->timestamp('scheduled_for')->nullable()->after('eligible_at')->index();
                }

                if (! Schema::hasColumn('vendor_payouts', 'payout_period_key')) {
                    $table->string('payout_period_key', 50)->nullable()->after('batch_reference')->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Migration volontairement non destructive : les états KYC/logistiques et
        // l'historique financier ne doivent pas être perdus lors d'un rollback.
    }
};
