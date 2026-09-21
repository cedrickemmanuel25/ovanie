<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSmsController extends Controller
{
    public function index(Request $request): View
    {
        $query = SmsLog::query();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('phone', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('response', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        match ($request->query('sort', 'recent')) {
            'oldest' => $query->oldest('created_at'),
            default => $query->latest('created_at'),
        };

        $smsLogs = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => SmsLog::query()->count(),
            'sent' => SmsLog::query()->where('status', 'sent')->count(),
            'pending' => SmsLog::query()->where('status', 'pending')->count(),
            'failed' => SmsLog::query()->where('status', 'failed')->count(),
        ];

        return view('admin.sms.index', compact('smsLogs', 'stats'));
    }
}
