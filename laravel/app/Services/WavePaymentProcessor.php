<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WavePaymentProcessor
{
    /**
     * Traite un événement dont l'authenticité Wave a déjà été vérifiée.
     */
    public function process(array $event): string
    {
        $reference = (string) ($event['reference'] ?? '');
        $transactionId = (string) ($event['transaction_id'] ?? $event['event_id'] ?? '');
        $status = (string) ($event['status'] ?? '');

        if ($reference === '' || $transactionId === '' || ! in_array($status, ['success', 'succeeded'], true)) {
            throw new InvalidArgumentException('Événement Wave incomplet ou non confirmant.');
        }

        return DB::transaction(function () use ($reference, $transactionId): string {
            $payment = Payment::query()
                ->where('method', Payment::METHOD_WAVE)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('Paiement Wave introuvable.');
            }

            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('Commande Wave introuvable.');
            }

            if ($payment->status === Payment::STATUS_SUCCESS) {
                return 'already_processed';
            }

            $transactionAlreadyUsed = Payment::query()
                ->where('method', Payment::METHOD_WAVE)
                ->where('transaction_id', $transactionId)
                ->whereKeyNot($payment->getKey())
                ->lockForUpdate()
                ->exists();

            if ($transactionAlreadyUsed) {
                throw new RuntimeException('Événement Wave déjà utilisé.');
            }

            $payment->forceFill([
                'status' => Payment::STATUS_SUCCESS,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ])->save();

            $order->forceFill([
                'payment_status' => 'paid',
                'status' => 'paid',
            ])->save();

            return 'processed';
        }, 3);
    }
}
