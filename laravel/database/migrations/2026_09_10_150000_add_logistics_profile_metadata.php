<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_drivers', fn (Blueprint $table) => $table->json('profile')->nullable());
        Schema::table('delivery_incidents', fn (Blueprint $table) => $table->json('meta')->nullable());
    }

    public function down(): void
    {
        Schema::table('delivery_drivers', fn (Blueprint $table) => $table->dropColumn('profile'));
        Schema::table('delivery_incidents', fn (Blueprint $table) => $table->dropColumn('meta'));
    }
};
