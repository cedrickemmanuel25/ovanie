<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('logistics_partners')) {
            Schema::create('logistics_partners', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('partner_type')->default('partner');
                $table->string('contact_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('main_zone')->nullable();
                $table->json('coverage')->nullable();
                $table->unsignedInteger('vehicle_count')->default(0);
                $table->unsignedInteger('mission_count')->default(0);
                $table->decimal('sla_percent', 5, 2)->default(0);
                $table->decimal('acceptance_percent', 5, 2)->default(0);
                $table->unsignedInteger('successful_deliveries')->default(0);
                $table->unsignedInteger('delays_count')->default(0);
                $table->string('status')->default('active');
                $table->string('contract_reference')->nullable();
                $table->date('integrated_at')->nullable();
                $table->string('priority_level')->default('normal');
                $table->boolean('can_receive_missions')->default(true);
                $table->boolean('auto_assignment_visible')->default(true);
                $table->boolean('supports_special_loads')->default(false);
                $table->boolean('intercommunal_delivery')->default(false);
                $table->text('description')->nullable();
                $table->text('observations')->nullable();
                $table->json('vehicle_types')->nullable();
                $table->json('fleet_distribution')->nullable();
                $table->json('vehicles')->nullable();
                $table->json('mission_history')->nullable();
                $table->json('internal_notes')->nullable();
                $table->json('available_missions')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_fleet_vehicles')) {
            Schema::create('logistics_fleet_vehicles', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('registration')->unique();
                $table->string('vehicle_type');
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedInteger('capacity_kg')->default(0);
                $table->decimal('volume_m3', 8, 2)->nullable();
                $table->string('zone')->nullable();
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();
                $table->string('driver_email')->nullable();
                $table->string('status')->default('available');
                $table->unsignedInteger('mission_count')->default(0);
                $table->unsignedInteger('mileage_km')->default(0);
                $table->unsignedTinyInteger('fuel_percent')->default(100);
                $table->string('last_gps_position')->nullable();
                $table->string('last_gps_age')->nullable();
                $table->string('last_activity')->nullable();
                $table->date('service_date')->nullable();
                $table->string('maintenance_state')->nullable();
                $table->json('documents')->nullable();
                $table->json('mission_history')->nullable();
                $table->json('maintenance_history')->nullable();
                $table->json('features')->nullable();
                $table->text('observations')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pilotage_snapshots')) {
            Schema::create('logistics_pilotage_snapshots', function (Blueprint $table) {
                $table->id();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('territory')->default('Tous les territoires');
                $table->json('kpis')->nullable();
                $table->json('delivery_evolution')->nullable();
                $table->json('cost_distribution')->nullable();
                $table->json('punctuality_evolution')->nullable();
                $table->json('driver_performance')->nullable();
                $table->json('top_zones')->nullable();
                $table->json('latest_incidents')->nullable();
                $table->json('latest_returns')->nullable();
                $table->json('key_indicators')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pilotage_notifications')) {
            Schema::create('logistics_pilotage_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('title');
                $table->string('message')->nullable();
                $table->string('reference')->nullable();
                $table->string('location')->nullable();
                $table->dateTime('occurred_at');
                $table->string('priority')->default('medium');
                $table->boolean('is_read')->default(false);
                $table->string('status')->default('open');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logistics_pilotage_settings')) {
            Schema::create('logistics_pilotage_settings', function (Blueprint $table) {
                $table->id();
                $table->string('group_key');
                $table->string('setting_key')->unique();
                $table->string('label');
                $table->text('value')->nullable();
                $table->string('type')->default('text');
                $table->text('description')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_pilotage_settings');
        Schema::dropIfExists('logistics_pilotage_notifications');
        Schema::dropIfExists('logistics_pilotage_snapshots');
        Schema::dropIfExists('logistics_fleet_vehicles');
        Schema::dropIfExists('logistics_partners');
    }
};
