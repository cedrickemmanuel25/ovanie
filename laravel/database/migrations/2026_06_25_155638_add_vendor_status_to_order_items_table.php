<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'vendor_status')) {
                $table->string('vendor_status', 50)->nullable()
                      ->comment('pending|accepted|preparing|ready|shipped|delivered|cancelled');
            }
            if (!Schema::hasColumn('order_items', 'vendor_status_note')) {
                $table->text('vendor_status_note')->nullable();
            }
            if (!Schema::hasColumn('order_items', 'vendor_status_updated_at')) {
                $table->timestamp('vendor_status_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['vendor_status', 'vendor_status_note', 'vendor_status_updated_at']);
        });
    }
};
