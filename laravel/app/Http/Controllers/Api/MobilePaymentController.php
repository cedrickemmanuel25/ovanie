<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class MobilePaymentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Payment::with('order')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate((int) $request->get('per_page', 20))
        );
    }

    public function show(Request $request, Payment $payment)
    {
        abort_unless((int) $payment->user_id === (int) $request->user()->id, 403);
        return response()->json(['data' => $payment->load('order')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'method' => ['required', 'in:wave,orange_money,mtn_money,moov_money,paydunya,flutterwave,cash_on_delivery,manual_proof,card'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'operator' => ['nullable', 'string', 'max:100'],
        ]);

        $order = Order::where('id', $data['order_id'])
            ->where('client_id', $request->user()->id)
            ->firstOrFail();

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'method' => $data['method'],
            'amount' => $order->total_amount,
            'status' => 'pending',
            'operator' => $data['operator'] ?? $data['method'],
            'mobile_number' => $data['mobile_number'] ?? null,
        ]);

        return response()->json([
            'message' => 'Paiement initialisé',
            'data' => $payment,
            'next_action' => $data['method'] === 'manual_proof' ? 'upload_payment_proof' : 'redirect_or_confirm_provider',
        ], 201);
    }
}
