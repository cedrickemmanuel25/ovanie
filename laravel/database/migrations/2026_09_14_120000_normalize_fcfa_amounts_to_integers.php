<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizePricingMatrices();
        $this->normalizeProductPrices();
    }

    public function down(): void
    {
        // Normalisation volontaire et irréversible : les centimes n'existent pas
        // dans la politique tarifaire FCFA d'OVANIE.
    }

    private function normalizePricingMatrices(): void
    {
        if (! Schema::hasTable('logistics_pricing_matrices')) {
            return;
        }

        DB::table('logistics_pricing_matrices')
            ->select(['id', 'vehicle_prices'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $prices = is_string($row->vehicle_prices)
                        ? json_decode($row->vehicle_prices, true)
                        : (array) $row->vehicle_prices;

                    if (! is_array($prices)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($prices as $code => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }

                        $normalized = max(0, (int) round((float) $value));
                        if ((string) $normalized !== (string) $value) {
                            $changed = true;
                        }
                        $prices[$code] = $normalized;
                    }

                    if ($changed) {
                        DB::table('logistics_pricing_matrices')
                            ->where('id', $row->id)
                            ->update([
                                'vehicle_prices' => json_encode($prices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    private function normalizeProductPrices(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $columns = collect(['price', 'promo_price', 'price_p1', 'price_p2', 'price_p3'])
            ->filter(fn (string $column) => Schema::hasColumn('products', $column))
            ->values();

        if ($columns->isEmpty()) {
            return;
        }

        DB::table('products')
            ->select(array_merge(['id'], $columns->all()))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($columns): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column};
                        if ($value === null || $value === '') {
                            continue;
                        }

                        $normalized = max(0, (int) round((float) $value));
                        if ((float) $value !== (float) $normalized) {
                            $updates[$column] = $normalized;
                        }
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('products')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }
};
