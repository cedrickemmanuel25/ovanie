<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCommuneKey('delivery_zones');
        $this->addCommuneKey('delivery_drivers');
        $this->addCommuneKey('seller_delivery_zones');
        $this->addCommuneKey('delivery_commune_rate_rules', 'origin_commune_id');
        $this->addCommuneKey('delivery_commune_rate_rules', 'destination_commune_id');

        if (Schema::hasTable('delivery_commune_rate_rules') && ! Schema::hasColumn('delivery_commune_rate_rules', 'vehicle_code')) {
            Schema::table('delivery_commune_rate_rules', function (Blueprint $table) {
                $table->string('vehicle_code', 30)->nullable()->after('destination_commune_id')->index();
            });
        }

        $communes = DB::table('abidjan_communes')->get(['id', 'name', 'slug', 'aliases']);
        $lookup = [];
        foreach ($communes as $commune) {
            foreach ([$commune->name, $commune->slug, ...((array) json_decode($commune->aliases ?: '[]', true))] as $alias) {
                $lookup[Str::slug((string) $alias)] = $commune;
            }
        }

        $this->backfill('delivery_zones', 'name', 'commune_id', $lookup);
        $this->backfill('delivery_drivers', 'zone', 'commune_id', $lookup);
        $this->backfill('seller_delivery_zones', 'commune', 'commune_id', $lookup);
        $this->backfill('delivery_commune_rate_rules', 'origin_commune', 'origin_commune_id', $lookup, true);
        $this->backfill('delivery_commune_rate_rules', 'destination_commune', 'destination_commune_id', $lookup, true);

        // Les 65 tarifs historiques portent le véhicule dans meta : il devient une vraie clé métier.
        if (Schema::hasTable('delivery_commune_rate_rules')) {
            foreach (DB::table('delivery_commune_rate_rules')->get(['id', 'meta']) as $rule) {
                $meta = (array) json_decode($rule->meta ?: '[]', true);
                if (filled($meta['vehicle_code'] ?? null)) {
                    DB::table('delivery_commune_rate_rules')->where('id', $rule->id)->update([
                        'vehicle_code' => $meta['vehicle_code'],
                    ]);
                }
            }
        }

        // Un quartier appartient toujours à une commune officielle par sa FK commune_id.
        // Les doublons accentués/non accentués sont fusionnés sur la clé canonique.
        $this->deduplicateZones();
        $this->deduplicateSellerRates();
        $this->deduplicateOvanieRates();

        Schema::table('delivery_zones', fn (Blueprint $table) => $table->unique('commune_id', 'delivery_zones_commune_unique'));
        Schema::table('seller_delivery_zones', fn (Blueprint $table) => $table->unique(['shop_id', 'commune_id', 'vehicle_code'], 'seller_zone_commune_vehicle_unique'));
        Schema::table('delivery_commune_rate_rules', fn (Blueprint $table) => $table->unique(['destination_commune_id', 'vehicle_code'], 'commune_vehicle_tariff_unique'));
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_zones')) Schema::table('delivery_zones', fn (Blueprint $table) => $table->dropUnique('delivery_zones_commune_unique'));
        if (Schema::hasTable('seller_delivery_zones')) Schema::table('seller_delivery_zones', fn (Blueprint $table) => $table->dropUnique('seller_zone_commune_vehicle_unique'));
        if (Schema::hasTable('delivery_commune_rate_rules')) Schema::table('delivery_commune_rate_rules', fn (Blueprint $table) => $table->dropUnique('commune_vehicle_tariff_unique'));
        if (Schema::hasTable('delivery_commune_rate_rules') && Schema::hasColumn('delivery_commune_rate_rules', 'vehicle_code')) {
            Schema::table('delivery_commune_rate_rules', fn (Blueprint $table) => $table->dropColumn('vehicle_code'));
        }

        foreach ([['delivery_commune_rate_rules', 'destination_commune_id'], ['delivery_commune_rate_rules', 'origin_commune_id'], ['seller_delivery_zones', 'commune_id'], ['delivery_drivers', 'commune_id'], ['delivery_zones', 'commune_id']] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId($column));
            }
        }
    }

    private function addCommuneKey(string $table, string $column = 'commune_id'): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreignId($column)->nullable()->constrained('abidjan_communes')->restrictOnDelete();
            });
        }
    }

    private function backfill(string $table, string $textColumn, string $idColumn, array $lookup, bool $slugText = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $idColumn)) return;
        foreach (DB::table($table)->get(['id', $textColumn]) as $row) {
            $commune = $lookup[Str::slug((string) $row->{$textColumn})] ?? null;
            if (! $commune) continue;
            $values = [$idColumn => $commune->id];
            $values[$textColumn] = $slugText ? $commune->slug : $commune->name;
            DB::table($table)->where('id', $row->id)->update($values);
        }
    }

    private function deduplicateZones(): void
    {
        if (! Schema::hasTable('delivery_zones')) return;
        $groups = DB::table('delivery_zones')->whereNotNull('commune_id')->orderByDesc('is_active')->orderBy('id')->get()->groupBy('commune_id');
        foreach ($groups as $rows) {
            foreach ($rows->slice(1) as $duplicate) DB::table('delivery_zones')->where('id', $duplicate->id)->delete();
        }
    }

    private function deduplicateSellerRates(): void
    {
        if (! Schema::hasTable('seller_delivery_zones')) return;
        $groups = DB::table('seller_delivery_zones')->whereNotNull('commune_id')->whereNotNull('vehicle_code')->orderByDesc('is_active')->orderByDesc('updated_at')->get()->groupBy(fn ($row) => $row->shop_id.'|'.$row->commune_id.'|'.$row->vehicle_code);
        foreach ($groups as $rows) foreach ($rows->slice(1) as $duplicate) DB::table('seller_delivery_zones')->where('id', $duplicate->id)->delete();
    }

    private function deduplicateOvanieRates(): void
    {
        if (! Schema::hasTable('delivery_commune_rate_rules')) return;
        $groups = DB::table('delivery_commune_rate_rules')->whereNotNull('destination_commune_id')->whereNotNull('vehicle_code')->orderByDesc('is_active')->orderByDesc('updated_at')->get()->groupBy(fn ($row) => $row->destination_commune_id.'|'.$row->vehicle_code);
        foreach ($groups as $rows) foreach ($rows->slice(1) as $duplicate) DB::table('delivery_commune_rate_rules')->where('id', $duplicate->id)->delete();
    }
};
