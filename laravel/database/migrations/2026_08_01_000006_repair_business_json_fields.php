<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repair('devis', 'activites');
        $this->repair('appel_offres', 'services');
    }

    public function down(): void
    {
        // La normalisation JSON est volontairement irréversible.
    }

    private function repair(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $raw = $row->{$column};

                    if ($raw === null || $raw === '') {
                        DB::table($table)->where('id', $row->id)->update([$column => '[]']);
                        continue;
                    }

                    $decoded = json_decode((string) $raw, true);
                    if (! is_string($decoded)) {
                        continue;
                    }

                    $repaired = json_decode($decoded, true);
                    if (is_array($repaired)) {
                        DB::table($table)->where('id', $row->id)->update([
                            $column => json_encode($repaired, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                }
            });
    }
};
