<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\ClientOrderStatusService;
use App\Services\ClientOrderActionService;
use App\Services\LoyaltyService;
use App\Services\OrderStockReservationService;
use App\Services\CheckoutCartFinalizerService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientDashboardController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('client.dashboard');
    }

    public function orders(Request $request)
    {
        return redirect()->route('client.orders');
    }

    public function showOrder(Request $request, int $id)
    {
        $order = Order::where('client_id', $request->user()->id)->findOrFail($id);

        return redirect()->route('client.orders.show', $order);
    }

    public function cancelOrder(
        Request $request,
        int $id,
        ClientOrderActionService $actions
    ) {
        $order = Order::where('client_id', $request->user()->id)->findOrFail($id);

        try {
            $actions->cancel($request->user(), $order);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first()
                ?: 'Cette commande ne peut plus être annulée depuis votre espace client.');
        }

        return redirect()->route('client.orders')->with(
            'success',
            'Commande annulée. Les articles réservés ont été remis dans votre panier lorsque cela était possible.'
        );
    }

    public function deleteOrder(Request $request, int $id)
    {
        Order::where('client_id', $request->user()->id)->findOrFail($id);

        return back()->with(
            'error',
            'L’historique d’une commande ne peut pas être supprimé. Les commandes annulées restent conservées pour la traçabilité.'
        );
    }

    public function resumeOrder(Request $request, int $id)
    {
        $order = Order::where('client_id', $request->user()->id)->findOrFail($id);

        if ($order->status !== 'pending') {
            return back()->with('error', 'Cette commande ne peut plus être reprise.');
        }

        return redirect()->route('receipt.show', $order->id);
    }
}
