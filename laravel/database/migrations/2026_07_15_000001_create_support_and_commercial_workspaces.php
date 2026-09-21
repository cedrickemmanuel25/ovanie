<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('client','vendor','admin','logistique','support','commercial') NOT NULL DEFAULT 'client'");
        }

        if (! Schema::hasTable('staff_profiles')) {
            Schema::create('staff_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('department', 30)->index();
                $table->string('employee_code', 40)->unique();
                $table->string('job_title')->nullable();
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('phone_extension', 20)->nullable();
                $table->json('permissions')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requester_name')->nullable();
                $table->string('requester_email')->nullable();
                $table->string('requester_phone', 40)->nullable();
                $table->string('channel', 30)->default('internal')->index();
                $table->string('category', 60)->default('general')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('status', 30)->default('open')->index();
                $table->string('team', 40)->default('support')->index();
                $table->string('subject');
                $table->longText('description');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('escalation_level')->default(0);
                $table->timestamp('sla_due_at')->nullable()->index();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
                $table->foreignId('dispute_id')->nullable()->constrained('disputes')->nullOnDelete();
                $table->foreignId('delivery_incident_id')->nullable()->constrained('delivery_incidents')->nullOnDelete();
                $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'priority', 'assigned_to']);
                $table->index(['requester_user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('author_type', 20)->default('staff')->index();
                $table->longText('body');
                $table->boolean('is_internal_note')->default(false)->index();
                $table->json('attachments')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commercial_leads')) {
            Schema::create('commercial_leads', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->foreignId('business_request_id')->nullable()->constrained('business_requests')->nullOnDelete();
                $table->foreignId('devis_id')->nullable()->constrained('devis')->nullOnDelete();
                $table->foreignId('appel_offre_id')->nullable()->constrained('appel_offres')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source', 50)->default('manual')->index();
                $table->string('lead_type', 40)->default('buyer')->index();
                $table->string('status', 40)->default('new')->index();
                $table->string('company_name')->nullable();
                $table->string('contact_name');
                $table->string('email')->nullable()->index();
                $table->string('phone', 40)->nullable()->index();
                $table->string('city')->nullable();
                $table->string('sector')->nullable();
                $table->string('title');
                $table->longText('need_summary')->nullable();
                $table->decimal('estimated_value', 15, 2)->default(0);
                $table->unsignedTinyInteger('probability')->default(0);
                $table->date('expected_close_at')->nullable()->index();
                $table->timestamp('next_action_at')->nullable()->index();
                $table->timestamp('won_at')->nullable();
                $table->timestamp('lost_at')->nullable();
                $table->text('lost_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'assigned_to', 'next_action_at']);
            });
        }

        if (! Schema::hasTable('commercial_activities')) {
            Schema::create('commercial_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commercial_lead_id')->constrained('commercial_leads')->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30)->default('note')->index();
                $table->string('subject')->nullable();
                $table->longText('description');
                $table->string('outcome')->nullable();
                $table->timestamp('happened_at')->useCurrent()->index();
                $table->timestamp('next_follow_up_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_activities');
        Schema::dropIfExists('commercial_leads');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('staff_profiles');

        if (Schema::hasTable('users') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE users SET role = 'client' WHERE role IN ('support','commercial')");
            DB::statement("ALTER TABLE users MODIFY role ENUM('client','vendor','admin','logistique') NOT NULL DEFAULT 'client'");
        }
    }
};
