<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            // Le statut devient une chaîne contrôlée par validation applicative afin
            // de prendre en charge active, inactive et suspended sur tous les moteurs.
            Schema::table('users', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->change();
            });
        }

        if (! Schema::hasTable('internal_login_logs')) {
            Schema::create('internal_login_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 30)->index();
                $table->string('attempted_email')->nullable()->index();
                $table->string('role', 30)->nullable()->index();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id', 120)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['event', 'created_at']);
            });
        }

        // Les anciens agents logistiques sans profil rejoignent l'architecture commune.
        if (Schema::hasTable('users') && Schema::hasTable('staff_profiles')) {
            DB::table('users')
                ->where('role', 'logistics')
                ->update(['role' => 'logistique']);

            $logisticsUsers = DB::table('users')
                ->where('role', 'logistique')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('staff_profiles')
                        ->whereColumn('staff_profiles.user_id', 'users.id');
                })
                ->get(['id', 'name']);

            foreach ($logisticsUsers as $user) {
                $baseCode = 'LOG-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
                $employeeCode = $baseCode;
                $suffix = 1;

                while (DB::table('staff_profiles')->where('employee_code', $employeeCode)->exists()) {
                    $employeeCode = $baseCode . '-' . $suffix++;
                }

                DB::table('staff_profiles')->insert([
                    'user_id' => $user->id,
                    'department' => 'logistique',
                    'employee_code' => $employeeCode,
                    'job_title' => 'Agent logistique',
                    'permissions' => json_encode(config('staff.role_permissions.logistique', []), JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_login_logs');

        if (Schema::hasTable('users')) {
            DB::table('users')->where('status', 'inactive')->update(['status' => 'suspended']);

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE users MODIFY status ENUM('active','suspended') NOT NULL DEFAULT 'active'");
            }
        }
    }
};
