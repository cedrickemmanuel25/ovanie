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
            if (! Schema::hasColumn('shops', 'geo_precision')) {
                $table->string('geo_precision', 20)->nullable()->after('geo_source');
            }

            if (! Schema::hasColumn('shops', 'geo_precision_score')) {
                $table->unsignedTinyInteger('geo_precision_score')->nullable()->after('geo_precision');
            }

            if (! Schema::hasColumn('shops', 'geo_status')) {
                $table->string('geo_status', 40)
                    ->default('verification_required')
                    ->after('geo_precision_score')
                    ->index();
            }
        });

        // Les anciennes coordonnées restent exploitables, mais elles ne sont pas
        // considérées comme « vérifiées » sans preuve explicite de leur origine.
        if (Schema::hasColumn('shops', 'geo_status')) {
            DB::table('shops')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where(function ($query) {
                    $query->whereNull('geo_status')
                        ->orWhere('geo_status', '')
                        ->orWhere('geo_status', 'verification_required');
                })
                ->update(['geo_status' => 'review_recommended']);

            DB::table('shops')
                ->whereNotIn('geo_source', ['browser_gps', 'manual_map', 'logistics_verified'])
                ->orWhereNull('geo_source')
                ->update(['geo_verified_at' => null]);

            DB::table('shops')
                ->whereIn('geo_source', ['browser_gps', 'manual_map', 'logistics_verified'])
                ->update(['geo_status' => 'verified']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'geo_status')) {
                $table->dropColumn('geo_status');
            }

            if (Schema::hasColumn('shops', 'geo_precision_score')) {
                $table->dropColumn('geo_precision_score');
            }

            if (Schema::hasColumn('shops', 'geo_precision')) {
                $table->dropColumn('geo_precision');
            }
        });
    }
};
