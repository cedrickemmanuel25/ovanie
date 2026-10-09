<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OVANIE PRO V5.2 révision 1 : l'entreprise (organisation) devient une entité à part entière,
 * distincte du compte personnel, du profil professionnel, des rôles métier et des permissions.
 * Les collaborateurs rejoignent l'organisation par invitation ; les documents sont rattachés
 * à l'organisation et ne sont jamais redemandés aux collaborateurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pro_organizations')) {
            Schema::create('pro_organizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_pro_user_id')->nullable()->constrained('pro_users')->nullOnDelete();
                $table->string('entity_type', 20)->default('organization'); // organization | independent
                $table->string('legal_name', 180)->nullable();
                $table->string('trade_name', 180)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('registration_country', 120)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('address')->nullable();
                $table->string('legal_form', 120)->nullable();
                $table->string('registration_number', 120)->nullable();
                $table->string('tax_number', 120)->nullable();
                $table->unsignedSmallInteger('founded_year')->nullable();
                $table->string('website')->nullable();
                $table->string('official_email')->nullable();
                $table->string('official_phone', 40)->nullable();
                // draft -> pending (soumis) -> in_progress / additional_info_requested -> verified / refused / suspended.
                // Jamais « verified » automatiquement (RG-05).
                $table->string('verification_status', 40)->default('draft')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pro_documents')) {
            Schema::create('pro_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pro_organization_id')->constrained('pro_organizations')->cascadeOnDelete();
                $table->foreignId('uploaded_by_pro_user_id')->nullable()->constrained('pro_users')->nullOnDelete();
                $table->string('document_type', 80)->index();
                $table->string('label', 180);
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                // Documents privés par défaut (RG-09) ; statut révisable par l'administration.
                $table->string('status', 30)->default('submitted')->index();
                $table->timestamps();
            });
        }

        foreach (['pro_professional_profiles', 'pro_company_memberships', 'pro_company_invitations'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'pro_organization_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('pro_organization_id')->nullable()->after('id')->constrained('pro_organizations')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['pro_company_invitations', 'pro_company_memberships', 'pro_professional_profiles'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'pro_organization_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('pro_organization_id');
                });
            }
        }

        Schema::dropIfExists('pro_documents');
        Schema::dropIfExists('pro_organizations');
    }
};
