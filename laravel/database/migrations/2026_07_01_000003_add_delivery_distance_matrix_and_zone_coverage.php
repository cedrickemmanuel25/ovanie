<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_distance_matrix')) {
            Schema::create('delivery_distance_matrix', function (Blueprint $table) {
                $table->id();
                $table->string('origin_commune', 120);
                $table->string('destination_commune', 120);
                $table->decimal('distance_km', 8, 2);
                $table->unsignedInteger('estimated_duration_minutes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['origin_commune', 'destination_commune'], 'delivery_distance_communes_unique');
                $table->index(['origin_commune', 'destination_commune', 'is_active'], 'delivery_distance_lookup_idx');
            });
        }

        if (Schema::hasTable('seller_delivery_zones') && ! Schema::hasColumn('seller_delivery_zones', 'coverage_type')) {
            Schema::table('seller_delivery_zones', function (Blueprint $table) {
                $table->string('coverage_type', 40)->default('commune')->after('district')->index();
            });
        }

        if (Schema::hasTable('delivery_incidents')) {
            Schema::table('delivery_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_incidents', 'next_action')) {
                    $table->string('next_action')->nullable()->after('status');
                }
                if (! Schema::hasColumn('delivery_incidents', 'notified_at')) {
                    $table->timestamp('notified_at')->nullable()->after('rescheduled_at');
                }
            });
        }
    }

    public function down(): void
    {
        // Non destructive: logistics configuration and distance data are retained.
    }
};
