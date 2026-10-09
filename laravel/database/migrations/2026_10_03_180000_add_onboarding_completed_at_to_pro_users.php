<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Date à laquelle le parcours d'inscription (simple ou professionnel) a été entièrement terminé. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_path');
        });
    }

    public function down(): void
    {
        Schema::table('pro_users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
