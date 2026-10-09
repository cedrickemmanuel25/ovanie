<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'admin_google_maps_reference')) {
                $table->text('admin_google_maps_reference')->nullable()->after('geo_verified_at');
            }
            if (! Schema::hasColumn('shops', 'logistics_configured_by_admin_id')) {
                $table->unsignedBigInteger('logistics_configured_by_admin_id')->nullable()->index()->after('logistics_confirmed_at');
            }
            if (! Schema::hasColumn('shops', 'logistics_configured_by_admin_at')) {
                $table->timestamp('logistics_configured_by_admin_at')->nullable()->after('logistics_configured_by_admin_id');
            }
            if (! Schema::hasColumn('shops', 'payout_configured_by_admin_id')) {
                $table->unsignedBigInteger('payout_configured_by_admin_id')->nullable()->index()->after('payout_configured_at');
            }
            if (! Schema::hasColumn('shops', 'payout_configured_by_admin_at')) {
                $table->timestamp('payout_configured_by_admin_at')->nullable()->after('payout_configured_by_admin_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            foreach ([
                'admin_google_maps_reference',
                'logistics_configured_by_admin_id',
                'logistics_configured_by_admin_at',
                'payout_configured_by_admin_id',
                'payout_configured_by_admin_at',
            ] as $column) {
                if (Schema::hasColumn('shops', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
