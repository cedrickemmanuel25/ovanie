<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;

class WaveController extends Controller
{
    /**
     * Callback utilisateur (retour navigateur)
     */
    public function callback(Request $request)
    {
        $payment = Payment::query()
            ->with('order')
            ->where('reference', $request->query('reference'))
            ->first();

        $order = $payment?->order;

        if (! $payment
            || ! $order
            || (int) $payment->user_id !== (int) $request->user()->id
            || (int) $order->client_id !== (int) $request->user()->id) {
            return redirect()->route('client.orders')
                ->with('error', 'Paiement ou commande introuvable');
        }

        $message = $payment->status === Payment::STATUS_SUCCESS
            ? 'Paiement Wave validé'
            : 'Le paiement Wave est temporairement indisponible';

        return redirect()->route('client.orders.show', $order)
            ->with($payment->status === Payment::STATUS_SUCCESS ? 'success' : 'error', $message);
    }

    /**
     * Webhook Wave (serveur → serveur).
     *
     * Le processeur transactionnel WavePaymentProcessor ne doit être appelé
     * qu'après authentification officielle du corps brut. Le webhook reste
     * fermé tant que cette authentification n'est pas disponible.
     */
    public function webhook(Request $request)
    {
        return response()->json([
            'message' => 'Le webhook Wave est temporairement indisponible',
        ], 503);
    }
}
