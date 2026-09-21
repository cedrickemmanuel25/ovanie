<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class SupportAiAuditLog extends Model
{
    protected $fillable = [
        'event_uuid', 'support_conversation_id', 'support_call_id', 'ai_agent_id',
        'actor_user_id', 'action', 'decision', 'risk_level', 'confidence',
        'input', 'output', 'context', 'occurred_at', 'previous_hash', 'record_hash',
        'source_ip', 'user_agent',
    ];

    protected $casts = [
        'confidence' => 'float',
        'input' => 'array',
        'output' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportAiAuditLog $log): void {
            $log->event_uuid ??= (string) Str::uuid();
            $log->occurred_at ??= now()->startOfSecond();
            $log->occurred_at = $log->occurred_at->copy()->startOfSecond();
            $log->previous_hash ??= static::query()->latest('id')->value('record_hash');
            $log->record_hash ??= $log->calculateRecordHash();
        });

        static::updating(function (): void {
            throw new LogicException('Les journaux d’audit IA sont immuables et ne peuvent pas être modifiés.');
        });

        static::deleting(function (): void {
            throw new LogicException('Les journaux d’audit IA sont immuables et ne peuvent pas être supprimés.');
        });
    }

    public function conversation() { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }
    public function call() { return $this->belongsTo(SupportCall::class, 'support_call_id'); }
    public function aiAgent() { return $this->belongsTo(SupportAiAgent::class, 'ai_agent_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }

    public function calculateRecordHash(): string
    {
        $payload = [
            'event_uuid' => $this->event_uuid,
            'support_conversation_id' => $this->support_conversation_id,
            'support_call_id' => $this->support_call_id,
            'ai_agent_id' => $this->ai_agent_id,
            'actor_user_id' => $this->actor_user_id,
            'action' => $this->action,
            'decision' => $this->decision,
            'risk_level' => $this->risk_level,
            'confidence' => $this->confidence,
            'input' => $this->input,
            'output' => $this->output,
            'context' => $this->context,
            'occurred_at' => $this->occurred_at?->format('Y-m-d H:i:s'),
            'previous_hash' => $this->previous_hash,
            'source_ip' => $this->source_ip,
            'user_agent' => $this->user_agent,
        ];

        return hash('sha256', json_encode($this->sortRecursively($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function verifyHash(): bool
    {
        return filled($this->record_hash)
            && hash_equals((string) $this->record_hash, $this->calculateRecordHash());
    }

    public function verifyChainLink(): bool
    {
        $expectedPrevious = static::query()
            ->where('id', '<', $this->id)
            ->latest('id')
            ->value('record_hash');

        return hash_equals((string) ($expectedPrevious ?? ''), (string) ($this->previous_hash ?? ''));
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
}
