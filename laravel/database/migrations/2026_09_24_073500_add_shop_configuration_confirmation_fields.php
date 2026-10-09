<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'logistics_confirmed_at')) {
                $table->timestamp('logistics_confirmed_at')->nullable()->after('logistics_status')->index();
            }
            if (! Schema::hasColumn('shops', 'payout_configured_at')) {
                $table->timestamp('payout_configured_at')->nullable()->after('payout_onboarding_started_at')->index();
            }
        });

        // Préserve les boutiques déjà exploitables au moment du déploiement.
        // Les boutiques historiques connues comme NON confirmées sont ensuite
        // marquées explicitement avec la commande ovanie:shops-require-configuration.
        DB::table('shops')
            ->whereNull('logistics_confirmed_at')
            ->whereIn('logistics_type', ['ovanie', 'seller'])
            ->update(['logistics_confirmed_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);

        DB::table('shops')
            ->whereNull('payout_configured_at')
            ->whereIn('payment_mode', ['post_delivery', 'weekly'])
            ->whereNotNull('mm_operator')->where('mm_operator', '!=', '')
            ->whereNotNull('mm_number')->where('mm_number', '!=', '')
            ->whereNotNull('mm_holder')->where('mm_holder', '!=', '')
            ->update(['payout_configured_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'logistics_confirmed_at')) {
                $table->dropColumn('logistics_confirmed_at');
            }
            if (Schema::hasColumn('shops', 'payout_configured_at')) {
                $table->dropColumn('payout_configured_at');
            }
        });
    }
};
