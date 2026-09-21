<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportAiAgent;
use App\Models\SupportAiAuditLog;
use Illuminate\Http\Request;

class SupportAiAuditController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportAiAuditLog::query()
            ->with(['conversation.requester', 'call', 'aiAgent', 'actor'])
            ->latest('occurred_at')
            ->latest('id');

        foreach (['action', 'risk_level', 'ai_agent_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $logs = $query->paginate(40)->withQueryString();
        $logs->getCollection()->each(function (SupportAiAuditLog $log): void {
            $log->setAttribute('integrity_valid', $log->verifyHash() && $log->verifyChainLink());
        });

        return view('support.ai-audit.index', [
            'logs' => $logs,
            'agents' => SupportAiAgent::orderBy('name')->get(['id', 'name']),
            'actions' => SupportAiAuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'integrity' => [
                'total' => SupportAiAuditLog::count(),
                'hashed' => SupportAiAuditLog::whereNotNull('record_hash')->count(),
                'last_hash' => SupportAiAuditLog::latest('id')->value('record_hash'),
            ],
        ]);
    }
}
