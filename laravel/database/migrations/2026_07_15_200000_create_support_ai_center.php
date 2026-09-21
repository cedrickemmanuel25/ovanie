<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_ai_agents')) {
            Schema::create('support_ai_agents', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('role_key', 40)->index();
                $table->text('description')->nullable();
                $table->text('personality')->nullable();
                $table->longText('system_prompt')->nullable();
                $table->json('channels')->nullable();
                $table->json('routing_keywords')->nullable();
                $table->json('capabilities')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->string('voice_name', 80)->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_token')->unique();
                $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requester_name')->nullable();
                $table->string('requester_email')->nullable()->index();
                $table->string('requester_phone', 40)->nullable()->index();
                $table->string('channel', 30)->default('chat')->index();
                $table->string('status', 30)->default('active')->index();
                $table->foreignId('ai_agent_id')->nullable()->constrained('support_ai_agents')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
                $table->foreignId('dispute_id')->nullable()->constrained('disputes')->nullOnDelete();
                $table->string('subject')->nullable();
                $table->text('summary')->nullable();
                $table->string('sentiment', 20)->nullable();
                $table->string('priority', 20)->default('normal')->index();
                $table->decimal('ai_confidence', 5, 2)->nullable();
                $table->boolean('requires_human')->default(false)->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'channel', 'last_message_at']);
                $table->index(['ai_agent_id', 'status']);
                $table->index(['assigned_to', 'status']);
            });
        }

        if (! Schema::hasTable('support_conversation_messages')) {
            Schema::create('support_conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_conversation_id')->constrained('support_conversations')->cascadeOnDelete();
                $table->string('sender_type', 20)->index();
                $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ai_agent_id')->nullable()->constrained('support_ai_agents')->nullOnDelete();
                $table->longText('body');
                $table->string('format', 20)->default('text');
                $table->boolean('is_internal')->default(false)->index();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('provider_message_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['support_conversation_id', 'created_at'], 'scm_conv_id_created_at_idx');

            });
        }

        if (! Schema::hasTable('support_calls')) {
            Schema::create('support_calls', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('support_conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
                $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ai_agent_id')->nullable()->constrained('support_ai_agents')->nullOnDelete();
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('provider', 50)->default('webhook')->index();
                $table->string('provider_call_id')->nullable()->unique();
                $table->string('direction', 20)->default('inbound')->index();
                $table->string('status', 30)->default('waiting')->index();
                $table->string('from_number', 40)->nullable()->index();
                $table->string('to_number', 40)->nullable();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->text('recording_url')->nullable();
                $table->longText('transcript')->nullable();
                $table->text('summary')->nullable();
                $table->string('missed_reason')->nullable();
                $table->string('transfer_target')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'direction', 'started_at']);
            });
        }

        if (! Schema::hasTable('support_call_events')) {
            Schema::create('support_call_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_call_id')->constrained('support_calls')->cascadeOnDelete();
                $table->string('event', 50)->index();
                $table->string('provider_event_id')->nullable()->index();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_agent_handoffs')) {
            Schema::create('support_agent_handoffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_conversation_id')->nullable()->constrained('support_conversations')->cascadeOnDelete();
                $table->foreignId('support_call_id')->nullable()->constrained('support_calls')->nullOnDelete();
                $table->foreignId('ai_agent_id')->nullable()->constrained('support_ai_agents')->nullOnDelete();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requested_by_type', 20)->default('ai');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('severity', 20)->default('normal')->index();
                $table->string('status', 20)->default('pending')->index();
                $table->text('reason');
                $table->text('notes')->nullable();
                $table->timestamp('requested_at')->useCurrent()->index();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_callback_requests')) {
            Schema::create('support_callback_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('support_conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
                $table->foreignId('support_call_id')->nullable()->constrained('support_calls')->nullOnDelete();
                $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('requester_name')->nullable();
                $table->string('phone', 40)->index();
                $table->string('email')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('preferred_at')->nullable()->index();
                $table->string('status', 20)->default('pending')->index();
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_knowledge_articles')) {
            Schema::create('support_knowledge_articles', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('category', 60)->default('general')->index();
                $table->longText('content');
                $table->string('status', 20)->default('draft')->index();
                $table->string('source_type', 30)->default('manual');
                $table->string('source_reference')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_ai_audit_logs')) {
            Schema::create('support_ai_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
                $table->foreignId('support_call_id')->nullable()->constrained('support_calls')->nullOnDelete();
                $table->foreignId('ai_agent_id')->nullable()->constrained('support_ai_agents')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 60)->index();
                $table->string('decision', 60)->nullable();
                $table->string('risk_level', 20)->default('low')->index();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->json('input')->nullable();
                $table->json('output')->nullable();
                $table->timestamps();

                $table->index(['action', 'created_at']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            $needsAiAgent = ! Schema::hasColumn('support_tickets', 'ai_agent_id');
            $needsAiFlag = ! Schema::hasColumn('support_tickets', 'created_by_ai');

            if ($needsAiAgent || $needsAiFlag) {
                Schema::table('support_tickets', function (Blueprint $table) use ($needsAiAgent, $needsAiFlag) {
                    if ($needsAiAgent) {
                        $table->foreignId('ai_agent_id')->nullable()->after('created_by')->constrained('support_ai_agents')->nullOnDelete();
                    }
                    if ($needsAiFlag) {
                        $table->boolean('created_by_ai')->default(false)->after('ai_agent_id')->index();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_tickets')) {
            $hasAiAgent = Schema::hasColumn('support_tickets', 'ai_agent_id');
            $hasAiFlag = Schema::hasColumn('support_tickets', 'created_by_ai');

            if ($hasAiAgent || $hasAiFlag) {
                Schema::table('support_tickets', function (Blueprint $table) use ($hasAiAgent, $hasAiFlag) {
                    if ($hasAiAgent) {
                        $table->dropConstrainedForeignId('ai_agent_id');
                    }
                    if ($hasAiFlag) {
                        $table->dropColumn('created_by_ai');
                    }
                });
            }
        }

        Schema::dropIfExists('support_ai_audit_logs');
        Schema::dropIfExists('support_knowledge_articles');
        Schema::dropIfExists('support_callback_requests');
        Schema::dropIfExists('support_agent_handoffs');
        Schema::dropIfExists('support_call_events');
        Schema::dropIfExists('support_calls');
        Schema::dropIfExists('support_conversation_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('support_ai_agents');
    }
};
