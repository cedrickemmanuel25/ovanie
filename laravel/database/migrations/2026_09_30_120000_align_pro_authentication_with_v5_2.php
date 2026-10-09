<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pro_users')) {
            if (! Schema::hasColumn('pro_users', 'residence_country')) {
                Schema::table('pro_users', function (Blueprint $table) {
                    $table->string('residence_country', 120)->nullable()->after('phone');
                });
            }
            if (! Schema::hasColumn('pro_users', 'preferred_language')) {
                Schema::table('pro_users', function (Blueprint $table) {
                    $table->string('preferred_language', 10)->default('fr')->after('residence_country');
                });
            }
            if (! Schema::hasColumn('pro_users', 'onboarding_path')) {
                Schema::table('pro_users', function (Blueprint $table) {
                    $table->string('onboarding_path', 30)->nullable()->after('account_type')->index();
                });
            }
        }

        if (! Schema::hasTable('pro_professional_profiles')) {
            Schema::create('pro_professional_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pro_user_id')->unique()->constrained('pro_users')->cascadeOnDelete();
                $table->string('profile_key', 80)->index();
                $table->string('entity_type', 40)->nullable();
                $table->string('activity_country', 120)->nullable();
                $table->string('registration_country', 120)->nullable();
                $table->json('desired_functions')->nullable();
                $table->string('business_name', 180)->nullable();
                $table->string('trade_name', 180)->nullable();
                $table->string('activity', 180)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('address')->nullable();
                $table->string('legal_form', 120)->nullable();
                $table->string('registration_number', 120)->nullable();
                $table->string('tax_number', 120)->nullable();
                $table->unsignedSmallInteger('founded_year')->nullable();
                $table->string('website')->nullable();
                $table->string('responsible_position', 120)->nullable();
                $table->string('professional_email')->nullable();
                $table->string('professional_phone', 40)->nullable();
                $table->string('professional_whatsapp', 40)->nullable();
                $table->json('profile_data')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pro_company_invitations')) {
            Schema::create('pro_company_invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inviter_pro_user_id')->nullable()->constrained('pro_users')->nullOnDelete();
                $table->string('organization_name', 180);
                $table->string('email')->nullable()->index();
                $table->string('phone', 40)->nullable()->index();
                $table->string('permission', 50);
                $table->string('token_hash', 64)->unique();
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->foreignId('accepted_pro_user_id')->nullable()->constrained('pro_users')->nullOnDelete();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pro_company_memberships')) {
            Schema::create('pro_company_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pro_user_id')->constrained('pro_users')->cascadeOnDelete();
                $table->string('organization_name', 180);
                $table->string('permission', 50)->index();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
                $table->unique(['pro_user_id', 'organization_name']);
            });
        }

        // Reprise non destructive des profils déjà créés avec l'ancienne structure.
        if (Schema::hasTable('pro_user_role_profiles') && Schema::hasTable('pro_user_roles') && Schema::hasTable('pro_professional_profiles')) {
            $legacyRows = DB::table('pro_user_role_profiles as p')
                ->join('pro_user_roles as r', 'r.id', '=', 'p.pro_user_role_id')
                ->join('pro_users as u', 'u.id', '=', 'r.pro_user_id')
                ->whereNotNull('u.professional_profile')
                ->select('u.id as pro_user_id', 'u.professional_profile', 'p.*')
                ->get();

            foreach ($legacyRows as $legacy) {
                DB::table('pro_professional_profiles')->updateOrInsert(
                    ['pro_user_id' => $legacy->pro_user_id],
                    [
                        'profile_key' => $legacy->professional_profile,
                        'business_name' => $legacy->business_name,
                        'activity' => $legacy->activity,
                        'country' => $legacy->country,
                        'activity_country' => $legacy->country,
                        'registration_country' => $legacy->country,
                        'city' => $legacy->city,
                        'professional_email' => $legacy->professional_email,
                        'professional_phone' => $legacy->professional_phone,
                        'profile_data' => $legacy->profile_data,
                        'status' => $legacy->status ?: 'draft',
                        'created_at' => $legacy->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_company_memberships');
        Schema::dropIfExists('pro_company_invitations');
        Schema::dropIfExists('pro_professional_profiles');

        if (Schema::hasTable('pro_users')) {
            foreach (['onboarding_path', 'preferred_language', 'residence_country'] as $column) {
                if (Schema::hasColumn('pro_users', $column)) {
                    Schema::table('pro_users', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
