<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->nullable()->default(null)->change();
        });

        // Only remove the historical placeholder from unregistered drivers
        // without any assignment. Existing operational ratings are preserved.
        DB::table('delivery_drivers')
            ->whereIn('onboarding_status', ['invited', 'pending_review'])
            ->where('rating', 4.5)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('delivery_assignments')
                    ->whereColumn('delivery_assignments.driver_id', 'delivery_drivers.id');
            })->update(['rating' => null]);
    }

    public function down(): void
    {
        // Keep unknown ratings nullable; rollback must not invent evaluations.
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->nullable()->default(4.5)->change();
        });
    }
};
