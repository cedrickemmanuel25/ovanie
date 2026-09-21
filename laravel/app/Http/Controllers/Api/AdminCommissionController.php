<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\Request;

class AdminCommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Commission::with(['order.client', 'shop']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $commissions = $query->latest()->get()->map(function ($commission) {
            $order = $commission->order;

            return [
                'id' => $commission->id,
                'client' => $order?->client?->name
                    ?? trim(($order?->client?->first_name ?? '') . ' ' . ($order?->client?->last_name ?? ''))
                    ?: 'N/A',
                'order_number' => $order?->order_number ?? 'N/A',
                'shop' => $commission->shop?->name ?? 'N/A',
                'subtotal' => $order?->subtotal ?? 0,
                'commission' => $commission->amount ?? 0,
                'status' => $commission->status ?? 'non payé',
                'date' => $commission->created_at?->format('Y-m-d'),
            ];
        });

        return response()->json($commissions);
    }
}