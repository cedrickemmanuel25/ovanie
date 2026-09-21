<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_sequences')) {
            Schema::create('invoice_sequences', function (Blueprint $table) {
                $table->unsignedSmallInteger('year')->primary();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            return;
        }

        $maxByYear = [];
        DB::table('orders')
            ->whereNotNull('invoice_number')
            ->orderBy('id')
            ->pluck('invoice_number')
            ->each(function ($invoice) use (&$maxByYear): void {
                if (preg_match('/^FAC-(\d{4})-(\d+)$/', (string) $invoice, $matches) !== 1) {
                    return;
                }

                $year = (int) $matches[1];
                $number = (int) $matches[2];
                $maxByYear[$year] = max($maxByYear[$year] ?? 0, $number);
            });

        foreach ($maxByYear as $year => $lastNumber) {
            DB::table('invoice_sequences')->updateOrInsert(
                ['year' => $year],
                [
                    'last_number' => $lastNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
