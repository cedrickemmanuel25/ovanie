<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_requesters')) {
            Schema::create('support_requesters', function (Blueprint $table) {
                $table->id();
                $table->string('requester_type', 30)->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('delivery_driver_id')->nullable()->constrained('delivery_drivers')->nullOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('email')->nullable()->index();
                $table->string('phone', 40)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['requester_type', 'user_id']);
                $table->index(['requester_type', 'delivery_driver_id']);
                $table->index(['requester_type', 'shop_id']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('support_tickets', 'support_requester_id')) {
                    $table->foreignId('support_requester_id')->nullable()->after('reference')->constrained('support_requesters')->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'source_app')) {
                    $table->string('source_app', 40)->default('internal')->after('channel')->index();
                }
            });
        }

        if (Schema::hasTable('support_conversations')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('support_conversations', 'support_requester_id')) {
                    $table->foreignId('support_requester_id')->nullable()->after('public_token')->constrained('support_requesters')->nullOnDelete();
                }
                if (! Schema::hasColumn('support_conversations', 'source_app')) {
                    $table->string('source_app', 40)->default('website')->after('channel')->index();
                }
            });
        }

        if (! Schema::hasTable('support_context_links')) {
            Schema::create('support_context_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('context_type', 50)->index();
                $table->unsignedBigInteger('context_id')->index();
                $table->boolean('is_primary')->default(false)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['support_ticket_id', 'context_type', 'context_id'], 'support_context_ticket_type_id_unique');
                $table->index(['context_type', 'context_id'], 'support_context_type_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_context_links');

        if (Schema::hasTable('support_conversations')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                if (Schema::hasColumn('support_conversations', 'support_requester_id')) {
                    $table->dropConstrainedForeignId('support_requester_id');
                }
                if (Schema::hasColumn('support_conversations', 'source_app')) {
                    $table->dropColumn('source_app');
                }
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (Schema::hasColumn('support_tickets', 'support_requester_id')) {
                    $table->dropConstrainedForeignId('support_requester_id');
                }
                if (Schema::hasColumn('support_tickets', 'source_app')) {
                    $table->dropColumn('source_app');
                }
            });
        }

        Schema::dropIfExists('support_requesters');
    }
};
