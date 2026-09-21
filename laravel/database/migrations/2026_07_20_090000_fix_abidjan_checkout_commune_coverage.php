<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_commune_rate_rules')) {
            return;
        }

        DB::table('delivery_commune_rate_rules')
            ->whereIn('destination_commune', ['attecoubet', 'Attecoubet', 'Attécoubet'])
            ->orderBy('id')
            ->get()
            ->each(function ($rule): void {
                $meta = is_string($rule->meta ?? null)
                    ? json_decode((string) $rule->meta, true)
                    : (array) ($rule->meta ?? []);

                if (! is_array($meta)) {
                    $meta = [];
                }

                $meta['commune_label'] = 'Attécoubé';

                DB::table('delivery_commune_rate_rules')
                    ->where('id', $rule->id)
                    ->update([
                        'destination_commune' => 'attecoube',
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // La valeur « attecoubet » était une faute de clé. Ne pas la restaurer.
    }
};
