<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_drivers')) {
            Schema::table('delivery_drivers', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_drivers', 'email')) {
                    $table->string('email')->nullable()->unique()->after('phone');
                }
                if (! Schema::hasColumn('delivery_drivers', 'password')) {
                    $table->string('password')->nullable()->after('email');
                }
                if (! Schema::hasColumn('delivery_drivers', 'remember_token')) {
                    $table->rememberToken();
                }
                if (! Schema::hasColumn('delivery_drivers', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('last_seen_at');
                }
                if (! Schema::hasColumn('delivery_drivers', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable()->after('last_login_at');
                }
                if (! Schema::hasColumn('delivery_drivers', 'avatar')) {
                    $table->string('avatar')->nullable()->after('vehicle');
                }
                if (! Schema::hasColumn('delivery_drivers', 'must_change_password')) {
                    $table->boolean('must_change_password')->default(true)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('delivery_assignments')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_assignments', 'mission_number')) {
                    $table->string('mission_number', 80)->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('delivery_assignments', 'estimated_delivery_at')) {
                    $table->timestamp('estimated_delivery_at')->nullable()->index()->after('pickup_scheduled_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('estimated_delivery_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('accepted_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('arrived_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'gps_status')) {
                    $table->string('gps_status', 30)->default('unknown')->index()->after('status');
                }
                if (! Schema::hasColumn('delivery_assignments', 'gps_disabled_reason')) {
                    $table->string('gps_disabled_reason', 255)->nullable()->after('gps_status');
                }
                if (! Schema::hasColumn('delivery_assignments', 'gps_last_seen_at')) {
                    $table->timestamp('gps_last_seen_at')->nullable()->index()->after('gps_disabled_reason');
                }
                if (! Schema::hasColumn('delivery_assignments', 'manual_eta_at')) {
                    $table->timestamp('manual_eta_at')->nullable()->after('gps_last_seen_at');
                }
            });
        }

        if (Schema::hasTable('seller_delivery_tracking_sessions')) {
            Schema::table('seller_delivery_tracking_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'mission_status')) {
                    $table->string('mission_status', 40)->default('planned')->index()->after('status');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'gps_status')) {
                    $table->string('gps_status', 30)->default('unknown')->index()->after('mission_status');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'gps_disabled_reason')) {
                    $table->string('gps_disabled_reason', 255)->nullable()->after('gps_status');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'departed_at')) {
                    $table->timestamp('departed_at')->nullable()->after('accepted_at');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable()->after('departed_at');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'estimated_delivery_at')) {
                    $table->timestamp('estimated_delivery_at')->nullable()->index()->after('arrived_at');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'manual_eta_at')) {
                    $table->timestamp('manual_eta_at')->nullable()->after('estimated_delivery_at');
                }
                if (! Schema::hasColumn('seller_delivery_tracking_sessions', 'last_manual_status_at')) {
                    $table->timestamp('last_manual_status_at')->nullable()->after('manual_eta_at');
                }
            });
        }
    }

    public function down(): void
    {
        // Les colonnes ne sont volontairement pas supprimées automatiquement afin
        // d'éviter de perdre l'historique des missions en production.
    }
};
