<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorPayout;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorPayoutExportController extends Controller
{
    public function export(): StreamedResponse
    {
        $payouts = VendorPayout::with(['vendor', 'shop', 'order'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $filename = 'paydunya-payouts-' . now()->format('Y-m-d-H-i-s') . '.csv';

        return response()->streamDownload(function () use ($payouts) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Nom complet',
                'Téléphone',
                'Email',
                'Montant',
                'Moyen de paiement',
                'custom_data_1',
                'custom_data_2',
                'custom_data_3',
                'custom_data_4',
                'custom_data_5',
            ], ';');

            foreach ($payouts as $payout) {
                fputcsv($handle, [
                    $payout->vendor->name ?? $payout->shop->name ?? 'Vendeur OVANIE',
                    $payout->phone,
                    $payout->vendor->email ?? '',
                    (int) $payout->payout_amount,
                    $this->normalizePaymentMethod($payout->payment_method),
                    $payout->vendor_id,
                    $payout->shop_id,
                    $payout->order_id,
                    (int) $payout->commission_amount,
                    $payout->order->order_number ?? $payout->payout_reference,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizePaymentMethod(?string $method): string
    {
        return match ($method) {
            'wave', 'wave-ci', 'paydunya_payout' => 'Wave CI',
            'orange', 'orange-money-ci' => 'ORANGE MONEY CI',
            'mtn', 'mtn-ci' => 'MTN CI',
            'moov', 'moov-ci' => 'MOOV CI',
            default => 'Wave CI',
        };
    }
}