<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'logistics_confirmed_at')) {
                $table->timestamp('logistics_confirmed_at')->nullable()->after('logistics_status')->index();
            }
            if (! Schema::hasColumn('shops', 'payout_configured_at')) {
                $table->timestamp('payout_configured_at')->nullable()->after('payout_onboarding_started_at')->index();
            }
        });

        // Compatibilité production : on ne déclare PAS toutes les anciennes boutiques
        // incomplètes automatiquement. L'administrateur dispose de la liste exacte et
        // la commande ovanie:shops-require-configuration remettra uniquement celles-ci
        // à "non confirmé".
        DB::table('shops')->whereNull('logistics_confirmed_at')->update([
            'logistics_confirmed_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
        ]);
        DB::table('shops')->whereNull('payout_configured_at')->update([
            'payout_configured_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
        ]);

        if (! Schema::hasTable('shop_configuration_assignments')) {
            Schema::create('shop_configuration_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('commercial_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('configure_logistics')->default(false);
                $table->boolean('configure_payout')->default(false);
                $table->boolean('manage_products')->default(false);
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('logistics_completed_at')->nullable();
                $table->timestamp('payout_completed_at')->nullable();
            $table->timestamp('products_completed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['commercial_id', 'status'], 'shop_config_assign_commercial_status_idx');
                $table->index(['shop_id', 'status'], 'shop_config_assign_shop_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_configuration_assignments');

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'payout_configured_at')) {
                $table->dropColumn('payout_configured_at');
            }
            if (Schema::hasColumn('shops', 'logistics_confirmed_at')) {
                $table->dropColumn('logistics_confirmed_at');
            }
        });
    }
};
