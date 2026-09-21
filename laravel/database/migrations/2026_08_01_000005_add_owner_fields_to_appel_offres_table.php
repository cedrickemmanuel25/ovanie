<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->after('user_id')->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('appel_offres', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('company_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
