<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Réserve des numéros de facture OVANIE sans collision concurrente.
 *
 * Le numéro est réservé AVANT l'insertion de la commande. Une tentative de
 * commande qui échoue peut donc laisser un trou dans la séquence, ce qui est
 * normal et préférable à la réutilisation d'un numéro de facture.
 */
class InvoiceNumberService
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        if (! Schema::hasTable('invoice_sequences')) {
            return $this->collisionResistantFallback($year);
        }

        return DB::transaction(function () use ($year): string {
            DB::table('invoice_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('invoice_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $lastReserved = (int) ($sequence->last_number ?? 0);
            $lastExisting = $this->highestExistingNumber($year);
            $next = max($lastReserved, $lastExisting) + 1;

            DB::table('invoice_sequences')
                ->where('year', $year)
                ->update([
                    'last_number' => $next,
                    'updated_at' => now(),
                ]);

            return $this->format($year, $next);
        }, 5);
    }

    private function highestExistingNumber(int $year): int
    {
        $prefix = 'FAC-'.$year.'-';
        $max = 0;

        Order::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->whereNotNull('invoice_number')
            ->pluck('invoice_number')
            ->each(function ($invoice) use ($prefix, &$max): void {
                $invoice = (string) $invoice;
                if (! str_starts_with($invoice, $prefix)) {
                    return;
                }

                $suffix = substr($invoice, strlen($prefix));
                if (preg_match('/^\d+$/', $suffix) !== 1) {
                    return;
                }

                $max = max($max, (int) $suffix);
            });

        return $max;
    }

    private function format(int $year, int $number): string
    {
        return 'FAC-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function collisionResistantFallback(int $year): string
    {
        // Sécurité si la migration n'a pas encore été exécutée : ne jamais
        // reprendre "dernier + 1" sans verrou, car deux checkouts concurrents
        // pourraient générer le même numéro.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'FAC-'.$year.'-'.Str::upper(Str::random(10));
            if (! Order::where('invoice_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'FAC-'.$year.'-'.str_replace('-', '', (string) Str::uuid());
    }
}
