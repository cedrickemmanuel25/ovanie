<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs OTP pour la connexion livreur par SMS (remplace le code PIN
     * comme mécanisme de connexion — voir DriverAuthController::login).
     */
    public function up(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->string('otp_code', 6)->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->timestamp('otp_used_at')->nullable()->after('otp_expires_at');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires_at', 'otp_used_at', 'otp_last_sent_at']);
        });
    }
};
