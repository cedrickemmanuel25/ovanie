<?php

namespace App\Services\Staff;

use App\Models\AppelOffre;
use App\Models\BusinessRequest;
use App\Models\CommercialLead;
use App\Models\Devis;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommercialDashboardService
{
    public function build(User $user): array
    {
        $active = CommercialLead::active();
        $monthStart = now()->startOfMonth();

        return [
            'stats' => [
                'active' => (clone $active)->count(),
                'mine' => (clone $active)->where('assigned_to', $user->id)->count(),
                'pipeline_value' => (float) (clone $active)->sum('estimated_value'),
                'weighted_value' => (float) CommercialLead::active()->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')->value('total'),
                'followups_due' => (clone $active)->whereNotNull('next_action_at')->where('next_action_at', '<=', now())->count(),
                'won_month' => CommercialLead::where('status', 'won')->where('won_at', '>=', $monthStart)->count(),
                'business_requests' => BusinessRequest::where('status', 'open')->count(),
                'clients_created' => User::where('role', 'client')->where('created_by_commercial_id', $user->id)->count(),
                'vendors_created' => User::where('role', 'vendor')->where('created_by_commercial_id', $user->id)->count(),
                'shops_created' => Shop::where('created_by_commercial_id', $user->id)->count(),
                'new_shops_month' => Shop::where('created_by_commercial_id', $user->id)->where('created_at', '>=', $monthStart)->count(),
                'sales_month' => (float) Order::operational()->where('created_at', '>=', $monthStart)->sum('total_amount'),
                'quotes_total' => Devis::count() + AppelOffre::count(),
            ],
            'recentLeads' => CommercialLead::with(['assignee', 'user', 'shop'])->latest()->limit(10)->get(),
            'myFollowUps' => CommercialLead::with(['user', 'shop'])
                ->active()->where('assigned_to', $user->id)
                ->whereNotNull('next_action_at')->orderBy('next_action_at')->limit(8)->get(),
            'pipeline' => CommercialLead::query()
                ->select('status', DB::raw('count(*) as total'), DB::raw('sum(estimated_value) as value'))
                ->groupBy('status')->get()->keyBy('status'),
            'recentRequests' => BusinessRequest::with('user')->latest()->limit(6)->get(),
        ];
    }
}
