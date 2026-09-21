<?php

use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $addWhatsappPhone = ! Schema::hasColumn('users', 'whatsapp_phone');
            $addWhatsappVerifiedAt = ! Schema::hasColumn('users', 'whatsapp_verified_at');

            if ($addWhatsappPhone || $addWhatsappVerifiedAt) {
                Schema::table('users', function (Blueprint $table) use ($addWhatsappPhone, $addWhatsappVerifiedAt) {
                    if ($addWhatsappPhone) {
                        $table->string('whatsapp_phone', 40)->nullable()->index();
                    }
                    if ($addWhatsappVerifiedAt) {
                        $table->timestamp('whatsapp_verified_at')->nullable();
                    }
                });
            }
        }

        if (Schema::hasTable('support_conversations')) {
            $addIncident = ! Schema::hasColumn('support_conversations', 'delivery_incident_id');
            $addMatchMethod = ! Schema::hasColumn('support_conversations', 'requester_match_method');
            $addMatchedAt = ! Schema::hasColumn('support_conversations', 'requester_matched_at');
            $addLinkedAt = ! Schema::hasColumn('support_conversations', 'linked_at');

            if ($addIncident || $addMatchMethod || $addMatchedAt || $addLinkedAt) {
                Schema::table('support_conversations', function (Blueprint $table) use ($addIncident, $addMatchMethod, $addMatchedAt, $addLinkedAt) {
                    if ($addIncident) {
                        $table->foreignId('delivery_incident_id')->nullable()->constrained('delivery_incidents')->nullOnDelete();
                    }
                    if ($addMatchMethod) {
                        $table->string('requester_match_method', 30)->nullable()->index();
                    }
                    if ($addMatchedAt) {
                        $table->timestamp('requester_matched_at')->nullable()->index();
                    }
                    if ($addLinkedAt) {
                        $table->timestamp('linked_at')->nullable()->index();
                    }
                });
            }
        }

        if (Schema::hasTable('commercial_leads') && ! Schema::hasColumn('commercial_leads', 'support_conversation_id')) {
            Schema::table('commercial_leads', function (Blueprint $table) {
                $table->foreignId('support_conversation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('support_conversations')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('support_agent_handoffs')) {
            $columns = [
                'reference' => ! Schema::hasColumn('support_agent_handoffs', 'reference'),
                'support_ticket_id' => ! Schema::hasColumn('support_agent_handoffs', 'support_ticket_id'),
                'delivery_incident_id' => ! Schema::hasColumn('support_agent_handoffs', 'delivery_incident_id'),
                'commercial_lead_id' => ! Schema::hasColumn('support_agent_handoffs', 'commercial_lead_id'),
                'target_department' => ! Schema::hasColumn('support_agent_handoffs', 'target_department'),
                'queue_key' => ! Schema::hasColumn('support_agent_handoffs', 'queue_key'),
                'idempotency_key' => ! Schema::hasColumn('support_agent_handoffs', 'idempotency_key'),
                'assigned_at' => ! Schema::hasColumn('support_agent_handoffs', 'assigned_at'),
                'due_at' => ! Schema::hasColumn('support_agent_handoffs', 'due_at'),
                'completed_by' => ! Schema::hasColumn('support_agent_handoffs', 'completed_by'),
                'resolution_code' => ! Schema::hasColumn('support_agent_handoffs', 'resolution_code'),
                'lock_version' => ! Schema::hasColumn('support_agent_handoffs', 'lock_version'),
            ];

            if (in_array(true, $columns, true)) {
                Schema::table('support_agent_handoffs', function (Blueprint $table) use ($columns) {
                    if ($columns['reference']) {
                        $table->string('reference', 40)->nullable()->unique();
                    }
                    if ($columns['support_ticket_id']) {
                        $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                    }
                    if ($columns['delivery_incident_id']) {
                        $table->foreignId('delivery_incident_id')->nullable()->constrained('delivery_incidents')->nullOnDelete();
                    }
                    if ($columns['commercial_lead_id']) {
                        $table->foreignId('commercial_lead_id')->nullable()->constrained('commercial_leads')->nullOnDelete();
                    }
                    if ($columns['target_department']) {
                        $table->string('target_department', 30)->default('support')->index();
                    }
                    if ($columns['queue_key']) {
                        $table->string('queue_key', 80)->default('support_general')->index();
                    }
                    if ($columns['idempotency_key']) {
                        $table->string('idempotency_key', 64)->nullable()->unique();
                    }
                    if ($columns['assigned_at']) {
                        $table->timestamp('assigned_at')->nullable()->index();
                    }
                    if ($columns['due_at']) {
                        $table->timestamp('due_at')->nullable()->index();
                    }
                    if ($columns['completed_by']) {
                        $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if ($columns['resolution_code']) {
                        $table->string('resolution_code', 60)->nullable();
                    }
                    if ($columns['lock_version']) {
                        $table->unsignedInteger('lock_version')->default(0);
                    }
                });
            }

            DB::table('support_agent_handoffs')
                ->whereNull('reference')
                ->orderBy('id')
                ->eachById(function (object $handoff): void {
                    DB::table('support_agent_handoffs')
                        ->where('id', $handoff->id)
                        ->update(['reference' => 'TRF-'.str_pad((string) $handoff->id, 8, '0', STR_PAD_LEFT)]);
                });
        }

        if (Schema::hasTable('support_knowledge_articles')) {
            $addVersion = ! Schema::hasColumn('support_knowledge_articles', 'version');
            $addApprovedBy = ! Schema::hasColumn('support_knowledge_articles', 'approved_by');
            $addReviewedAt = ! Schema::hasColumn('support_knowledge_articles', 'reviewed_at');
            $addExpiresAt = ! Schema::hasColumn('support_knowledge_articles', 'expires_at');

            if ($addVersion || $addApprovedBy || $addReviewedAt || $addExpiresAt) {
                Schema::table('support_knowledge_articles', function (Blueprint $table) use ($addVersion, $addApprovedBy, $addReviewedAt, $addExpiresAt) {
                    if ($addVersion) {
                        $table->unsignedInteger('version')->default(1);
                    }
                    if ($addApprovedBy) {
                        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if ($addReviewedAt) {
                        $table->timestamp('reviewed_at')->nullable()->index();
                    }
                    if ($addExpiresAt) {
                        $table->timestamp('expires_at')->nullable()->index();
                    }
                });
            }
        }

        if (Schema::hasTable('support_ai_audit_logs')) {
            $columns = [
                'event_uuid' => ! Schema::hasColumn('support_ai_audit_logs', 'event_uuid'),
                'occurred_at' => ! Schema::hasColumn('support_ai_audit_logs', 'occurred_at'),
                'previous_hash' => ! Schema::hasColumn('support_ai_audit_logs', 'previous_hash'),
                'record_hash' => ! Schema::hasColumn('support_ai_audit_logs', 'record_hash'),
                'source_ip' => ! Schema::hasColumn('support_ai_audit_logs', 'source_ip'),
                'user_agent' => ! Schema::hasColumn('support_ai_audit_logs', 'user_agent'),
                'context' => ! Schema::hasColumn('support_ai_audit_logs', 'context'),
            ];

            if (in_array(true, $columns, true)) {
                Schema::table('support_ai_audit_logs', function (Blueprint $table) use ($columns) {
                    if ($columns['event_uuid']) {
                        $table->uuid('event_uuid')->nullable()->unique();
                    }
                    if ($columns['occurred_at']) {
                        $table->timestamp('occurred_at')->nullable()->index();
                    }
                    if ($columns['previous_hash']) {
                        $table->char('previous_hash', 64)->nullable();
                    }
                    if ($columns['record_hash']) {
                        $table->char('record_hash', 64)->nullable()->unique();
                    }
                    if ($columns['source_ip']) {
                        $table->string('source_ip', 45)->nullable();
                    }
                    if ($columns['user_agent']) {
                        $table->string('user_agent', 500)->nullable();
                    }
                    if ($columns['context']) {
                        $table->json('context')->nullable();
                    }
                });
            }
        }

        $this->hardenAuditForeignKeys();
        $this->dropImmutableAuditTriggers();
        $this->backfillAuditHashes();
        $this->createImmutableAuditTriggers();
    }

    public function down(): void
    {
        $this->dropImmutableAuditTriggers();

        if (Schema::hasTable('support_ai_audit_logs')) {
            $this->dropColumns('support_ai_audit_logs', [
                'event_uuid', 'occurred_at', 'previous_hash', 'record_hash',
                'source_ip', 'user_agent', 'context',
            ]);
        }

        if (Schema::hasTable('support_knowledge_articles')) {
            $this->dropForeignColumn('support_knowledge_articles', 'approved_by');
            $this->dropColumns('support_knowledge_articles', ['version', 'reviewed_at', 'expires_at']);
        }

        if (Schema::hasTable('support_agent_handoffs')) {
            foreach (['support_ticket_id', 'delivery_incident_id', 'commercial_lead_id', 'completed_by'] as $column) {
                $this->dropForeignColumn('support_agent_handoffs', $column);
            }
            $this->dropColumns('support_agent_handoffs', [
                'reference', 'target_department', 'queue_key', 'idempotency_key',
                'assigned_at', 'due_at', 'resolution_code', 'lock_version',
            ]);
        }

        if (Schema::hasTable('commercial_leads')) {
            $this->dropForeignColumn('commercial_leads', 'support_conversation_id');
        }

        if (Schema::hasTable('support_conversations')) {
            $this->dropForeignColumn('support_conversations', 'delivery_incident_id');
            $this->dropColumns('support_conversations', [
                'requester_match_method', 'requester_matched_at', 'linked_at',
            ]);
        }

    }

    private function hardenAuditForeignKeys(): void
    {
        if (! Schema::hasTable('support_ai_audit_logs')
            || ! Schema::hasColumn('support_ai_audit_logs', 'support_conversation_id')
            || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('support_ai_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['support_conversation_id']);
        });

        Schema::table('support_ai_audit_logs', function (Blueprint $table): void {
            $table->foreign('support_conversation_id')
                ->references('id')
                ->on('support_conversations')
                ->nullOnDelete();
        });
    }

    private function backfillAuditHashes(): void
    {
        if (! Schema::hasTable('support_ai_audit_logs') || ! Schema::hasColumn('support_ai_audit_logs', 'record_hash')) {
            return;
        }

        $previousHash = null;

        DB::table('support_ai_audit_logs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$previousHash): void {
                foreach ($rows as $row) {
                    $eventUuid = $row->event_uuid ?: (string) Str::uuid();
                    $occurredAt = Carbon::parse($row->occurred_at ?: $row->created_at ?: now())->startOfSecond();
                    $payload = [
                        'event_uuid' => $eventUuid,
                        'support_conversation_id' => $row->support_conversation_id,
                        'support_call_id' => $row->support_call_id,
                        'ai_agent_id' => $row->ai_agent_id,
                        'actor_user_id' => $row->actor_user_id,
                        'action' => $row->action,
                        'decision' => $row->decision,
                        'risk_level' => $row->risk_level,
                        'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                        'input' => $this->decodeJson($row->input),
                        'output' => $this->decodeJson($row->output),
                        'context' => $this->decodeJson($row->context ?? null),
                        'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                        'previous_hash' => $previousHash,
                        'source_ip' => $row->source_ip ?? null,
                        'user_agent' => $row->user_agent ?? null,
                    ];

                    $recordHash = hash('sha256', json_encode(
                        $this->sortRecursively($payload),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                    ));

                    DB::table('support_ai_audit_logs')->where('id', $row->id)->update([
                        'event_uuid' => $eventUuid,
                        'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                        'previous_hash' => $previousHash,
                        'record_hash' => $recordHash,
                    ]);

                    $previousHash = $recordHash;
                }
            });
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }

    private function createImmutableAuditTriggers(): void
    {
        if (! Schema::hasTable('support_ai_audit_logs')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_delete");
            DB::unprepared("CREATE TRIGGER support_ai_audit_logs_no_update BEFORE UPDATE ON support_ai_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les journaux IA sont immuables.'");
            DB::unprepared("CREATE TRIGGER support_ai_audit_logs_no_delete BEFORE DELETE ON support_ai_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les journaux IA sont immuables.'");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_delete");
            DB::unprepared("CREATE TRIGGER support_ai_audit_logs_no_update BEFORE UPDATE ON support_ai_audit_logs BEGIN SELECT RAISE(ABORT, 'Les journaux IA sont immuables.'); END");
            DB::unprepared("CREATE TRIGGER support_ai_audit_logs_no_delete BEFORE DELETE ON support_ai_audit_logs BEGIN SELECT RAISE(ABORT, 'Les journaux IA sont immuables.'); END");
        }
    }

    private function dropImmutableAuditTriggers(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS support_ai_audit_logs_no_delete");
        }
    }

    private function dropForeignColumn(string $tableName, string $column): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column) {
            $table->dropConstrainedForeignId($column);
        });
    }

    private function dropColumns(string $tableName, array $columns): void
    {
        $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($tableName, $column)));
        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
