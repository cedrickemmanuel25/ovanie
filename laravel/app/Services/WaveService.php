<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class WaveService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.wave.url');
        $this->apiKey = config('services.wave.secret');
    }

    /**
     * Créer un paiement Wave
     */
    public function createPayment(Order $order, $payment)
    {
        try {

            // 🔥 ON UTILISE LE BON MONTANT
            $amount = $payment->amount;

            $response = Http::withToken($this->apiKey)
                ->post($this->apiUrl . '/checkout/sessions', [
                    "amount" => $amount,
                    "currency" => "XOF",

                    // ⚡ référence unique selon type de paiement
                    "reference" => $payment->reference ?? $order->order_number,

                    "callback_url" => route('wave.callback'),
                ]);

            $data = $response->json();

            if (!isset($data['checkout_url'])) {

                Log::error("Wave error", [
                    'response' => $data,
                    'order_id' => $order->id,
                    'payment_id' => $payment->id
                ]);

                return redirect()
                    ->route('checkout.index')
                    ->with('error', 'Erreur paiement Wave');
            }

            return redirect($data['checkout_url']);

        } catch (\Exception $e) {

            Log::error("Wave API Exception", [
                'message' => $e->getMessage(),
                'order_id' => $order->id,
                'payment_id' => $payment->id
            ]);

            return redirect()
                ->route('checkout.index')
                ->with('error', 'Impossible de lancer le paiement');
        }
    }
}
