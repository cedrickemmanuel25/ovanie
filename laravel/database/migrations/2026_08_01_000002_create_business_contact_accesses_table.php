<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_contact_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_request_type', 50);
            $table->unsignedBigInteger('business_request_id');
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'business_request_type', 'business_request_id'],
                'business_contact_access_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_contact_accesses');
    }
};
