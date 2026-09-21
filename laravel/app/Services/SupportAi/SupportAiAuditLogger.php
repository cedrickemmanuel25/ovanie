<?php

namespace App\Services\SupportAi;

use App\Models\SupportAiAuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportAiAuditLogger
{
    public function log(array $data): SupportAiAuditLog
    {
        return Cache::lock('support-ai-audit-chain', 15)->block(5, function () use ($data): SupportAiAuditLog {
            return DB::transaction(function () use ($data): SupportAiAuditLog {
                $last = SupportAiAuditLog::query()->lockForUpdate()->latest('id')->first();
                $request = app()->bound('request') ? request() : null;

                $log = new SupportAiAuditLog();
                $log->forceFill(array_merge([
                    'event_uuid' => (string) Str::uuid(),
                    'risk_level' => 'low',
                    'occurred_at' => now()->startOfSecond(),
                    'previous_hash' => $last?->record_hash,
                    'source_ip' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                ], $data));

                if ($log->occurred_at) {
                    $log->occurred_at = $log->occurred_at->copy()->startOfSecond();
                }

                $log->record_hash = $log->calculateRecordHash();
                $log->save();

                return $log;
            }, 3);
        });
    }
}
