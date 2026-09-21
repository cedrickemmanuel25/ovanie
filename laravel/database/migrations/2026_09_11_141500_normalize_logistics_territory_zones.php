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
        if (! Schema::hasTable('logistics_territory_zones')) {
            return;
        }

        Schema::table('logistics_territory_zones', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_territory_zones', 'source')) {
                $table->string('source', 30)->default('manual')->index();
            }
            if (! Schema::hasColumn('logistics_territory_zones', 'coverage_geojson')) {
                $table->json('coverage_geojson')->nullable();
            }
            if (! Schema::hasColumn('logistics_territory_zones', 'center_latitude')) {
                $table->decimal('center_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('logistics_territory_zones', 'center_longitude')) {
                $table->decimal('center_longitude', 10, 7)->nullable();
            }
        });

        if (! Schema::hasTable('logistics_territory_zone_communes')) {
            Schema::create('logistics_territory_zone_communes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zone_id')->constrained('logistics_territory_zones')->cascadeOnDelete();
                $table->foreignId('commune_id')->constrained('abidjan_communes')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['zone_id', 'commune_id'], 'territory_zone_commune_unique');
                $table->index(['commune_id', 'zone_id'], 'territory_commune_zone_idx');
            });
        }

        $this->markLegacyDemoRows();
        $this->backfillGeometry();
        $this->backfillCommuneRelations();
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_territory_zone_communes');

        if (! Schema::hasTable('logistics_territory_zones')) {
            return;
        }

        Schema::table('logistics_territory_zones', function (Blueprint $table) {
            foreach (['source', 'coverage_geojson', 'center_latitude', 'center_longitude'] as $column) {
                if (Schema::hasColumn('logistics_territory_zones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function markLegacyDemoRows(): void
    {
        $signatures = [
            'Z-001' => 'Abidjan Centre',
            'Z-002' => 'Abidjan Nord',
            'Z-003' => 'Abidjan Sud',
            'Z-004' => 'Bingerville',
            'Z-005' => 'Anyama',
            'Z-006' => 'Yopougon',
            'Z-007' => 'Port-Bouët',
            'Z-008' => 'San Pedro',
            'Z-009' => 'Yamoussoukro',
            'Z-010' => 'Bouaké',
            'Z-011' => 'Daloa',
            'Z-012' => 'Korhogo',
        ];

        foreach ($signatures as $code => $name) {
            DB::table('logistics_territory_zones')
                ->where('code', $code)
                ->where('name', $name)
                ->where(function ($query) {
                    $query->where('map_variant', '!=', 'custom')
                        ->orWhereNotNull('drivers_snapshot')
                        ->orWhereNotNull('activity_snapshot');
                })
                ->update(['source' => 'demo', 'updated_at' => now()]);
        }
    }

    private function backfillGeometry(): void
    {
        DB::table('logistics_territory_zones')
            ->where('source', '!=', 'demo')
            ->orderBy('id')
            ->get(['id', 'meta', 'coverage_geojson', 'center_latitude', 'center_longitude'])
            ->each(function ($zone) {
                $meta = $this->decodeJson($zone->meta);
                $map = (array) ($meta['map'] ?? []);
                $geometry = $this->decodeJson($zone->coverage_geojson) ?: ($map['geometry'] ?? null);
                $center = (array) ($map['center'] ?? []);

                $payload = [];
                if (is_array($geometry) && ($geometry['type'] ?? null) === 'Polygon') {
                    $payload['coverage_geojson'] = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if ($zone->center_latitude === null && is_numeric($center['lat'] ?? null)) {
                    $payload['center_latitude'] = (float) $center['lat'];
                }
                if ($zone->center_longitude === null && is_numeric($center['lng'] ?? null)) {
                    $payload['center_longitude'] = (float) $center['lng'];
                }

                if ($payload) {
                    DB::table('logistics_territory_zones')->where('id', $zone->id)->update($payload);
                }
            });
    }

    private function backfillCommuneRelations(): void
    {
        if (! Schema::hasTable('abidjan_communes')) {
            return;
        }

        $communes = DB::table('abidjan_communes')
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'aliases']);

        $lookup = [];
        foreach ($communes as $commune) {
            foreach (array_merge([$commune->name, $commune->slug], $this->decodeJson($commune->aliases)) as $label) {
                $key = $this->normalize((string) $label);
                if ($key !== '') {
                    $lookup[$key] = $commune;
                }
            }
        }

        DB::table('logistics_territory_zones')
            ->where('source', '!=', 'demo')
            ->orderBy('id')
            ->get(['id', 'covered_communes'])
            ->each(function ($zone) use ($lookup) {
                $names = $this->decodeJson($zone->covered_communes);
                $ids = [];
                $canonicalNames = [];

                foreach ($names as $name) {
                    $name = preg_replace('/\s*\(partiel\)\s*$/iu', '', trim((string) $name));
                    $match = $lookup[$this->normalize($name)] ?? null;
                    if (! $match) {
                        continue;
                    }
                    $ids[(int) $match->id] = (int) $match->id;
                    $canonicalNames[(int) $match->id] = (string) $match->name;
                }

                foreach ($ids as $communeId) {
                    DB::table('logistics_territory_zone_communes')->updateOrInsert(
                        ['zone_id' => $zone->id, 'commune_id' => $communeId],
                        ['updated_at' => now(), 'created_at' => now()]
                    );
                }

                DB::table('logistics_territory_zones')->where('id', $zone->id)->update([
                    'covered_communes' => json_encode(array_values($canonicalNames), JSON_UNESCAPED_UNICODE),
                    'communes_count' => count($canonicalNames),
                    'updated_at' => now(),
                ]);
            });
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim($value)))) ?: '';
    }
};
