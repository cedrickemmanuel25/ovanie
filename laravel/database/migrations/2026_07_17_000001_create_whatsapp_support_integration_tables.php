<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_webhook_events')) {
            Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 64)->unique();
                $table->string('object_type', 60)->nullable()->index();
                $table->boolean('signature_valid')->default(false)->index();
                $table->string('status', 20)->default('received')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->json('payload');
                $table->text('error')->nullable();
                $table->timestamp('received_at')->useCurrent()->index();
                $table->timestamp('processed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_conversation_id')
                    ->nullable()
                    ->constrained('support_conversations')
                    ->nullOnDelete();
                $table->foreignId('support_conversation_message_id')
                    ->nullable()
                    ->constrained('support_conversation_messages')
                    ->nullOnDelete();
                $table->string('direction', 20)->index();
                $table->string('provider_message_id')->nullable()->unique();
                $table->string('phone_number_id')->nullable()->index();
                $table->string('from_phone', 40)->nullable()->index();
                $table->string('to_phone', 40)->nullable()->index();
                $table->string('message_type', 40)->default('text')->index();
                $table->string('status', 30)->default('received')->index();
                $table->longText('body')->nullable();
                $table->json('payload')->nullable();
                $table->string('error_code', 80)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->unique('support_conversation_message_id');
                $table->index(['support_conversation_id', 'created_at']);
                $table->index(['direction', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
