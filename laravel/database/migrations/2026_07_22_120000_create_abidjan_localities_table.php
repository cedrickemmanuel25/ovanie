<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abidjan_localities')) {
            Schema::create('abidjan_localities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commune_id')->constrained('abidjan_communes')->cascadeOnDelete();
                $table->foreignId('quarter_id')->nullable()->constrained('abidjan_quarters')->nullOnDelete();

                // Colonnes métier demandées pour le référentiel d'Abidjan.
                $table->string('commune', 120)->index();
                $table->string('quartier', 180)->nullable()->index();
                $table->string('sous_quartier', 180)->nullable()->index();
                $table->string('cite', 180)->nullable()->index();
                $table->string('village', 180)->nullable()->index();
                $table->string('carrefour', 180)->nullable()->index();
                $table->json('alias_google_maps')->nullable();

                // Colonnes techniques nécessaires à la recherche et à la normalisation GPS.
                $table->string('name', 180);
                $table->string('slug', 200);
                $table->string('type', 40)->default('quartier')->index();
                $table->text('search_text')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('source', 100)->default('ovanie')->index();
                $table->string('source_reference', 255)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_verified')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(
                    ['commune_id', 'type', 'slug'],
                    'abidjan_localities_commune_type_slug_unique'
                );
                $table->index(
                    ['commune_id', 'is_active', 'type'],
                    'abidjan_localities_commune_active_type_index'
                );
            });
        }

        $this->importReferenceFile();
        Cache::forget('geo:abidjan-locality-registry:v1');
        Cache::forget('geo:abidjan-locality-registry:v2');
    }

    public function down(): void
    {
        Schema::dropIfExists('abidjan_localities');
    }

    private function importReferenceFile(): void
    {
        $path = database_path('data/abidjan_localities_database.csv');

        if (! is_file($path) || ! is_readable($path)
            || ! Schema::hasTable('abidjan_communes')
            || ! Schema::hasTable('abidjan_quarters')) {
            return;
        }

        $communes = DB::table('abidjan_communes')->get(['id', 'name', 'slug', 'aliases']);
        $communeLookup = [];

        foreach ($communes as $commune) {
            $aliases = (array) json_decode((string) ($commune->aliases ?: '[]'), true);
            foreach ([$commune->name, $commune->slug, ...$aliases] as $alias) {
                $key = $this->normalize((string) $alias);
                if ($key !== '') {
                    $communeLookup[$key] = $commune;
                }
            }
        }

        $handle = fopen($path, 'rb');
        if (! $handle) {
            return;
        }

        $headers = fgetcsv($handle, 0, ',');
        if (! is_array($headers)) {
            fclose($handle);
            return;
        }

        $headers = array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? (string) $header;
            return Str::snake(Str::ascii(trim($header)));
        }, $headers);

        $now = now();

        while (($values = fgetcsv($handle, 0, ',')) !== false) {
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));

            if (! is_array($row)) {
                continue;
            }

            $commune = $communeLookup[$this->normalize((string) ($row['commune'] ?? ''))] ?? null;
            [$column, $type, $name] = $this->localityIdentity($row);

            if (! $commune || $name === '') {
                continue;
            }

            $aliases = $this->aliases($row['alias_google_maps'] ?? null);
            $slug = Str::slug($name);

            $quarter = DB::table('abidjan_quarters')
                ->where('commune_id', $commune->id)
                ->where('slug', $slug)
                ->first(['id']);

            $quarterValues = [
                'name' => $name,
                'aliases' => json_encode($aliases, JSON_UNESCAPED_UNICODE),
                'type' => $type,
                'source' => (string) ($row['source'] ?: 'abidjan_reference_2026'),
                'source_reference' => 'database/data/abidjan_localities_database.csv',
                'is_verified' => false,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($quarter) {
                DB::table('abidjan_quarters')->where('id', $quarter->id)->update($quarterValues);
                $quarterId = $quarter->id;
            } else {
                $quarterId = DB::table('abidjan_quarters')->insertGetId([
                    'commune_id' => $commune->id,
                    'slug' => $slug,
                    'latitude' => null,
                    'longitude' => null,
                    'created_at' => $now,
                    ...$quarterValues,
                ]);
            }

            $locationColumns = [
                'quartier' => null,
                'sous_quartier' => null,
                'cite' => null,
                'village' => null,
                'carrefour' => null,
            ];
            $locationColumns[$column] = $name;

            DB::table('abidjan_localities')->updateOrInsert(
                [
                    'commune_id' => $commune->id,
                    'type' => $type,
                    'slug' => $slug,
                ],
                [
                    'quarter_id' => $quarterId,
                    'commune' => $commune->name,
                    ...$locationColumns,
                    'alias_google_maps' => json_encode($aliases, JSON_UNESCAPED_UNICODE),
                    'name' => $name,
                    'search_text' => $this->searchText($commune->name, $name, $aliases),
                    'latitude' => null,
                    'longitude' => null,
                    'source' => (string) ($row['source'] ?: 'abidjan_reference_2026'),
                    'source_reference' => 'database/data/abidjan_localities_database.csv',
                    'notes' => filled($row['remarque'] ?? null) ? trim((string) $row['remarque']) : null,
                    'is_verified' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        fclose($handle);
    }

    private function localityIdentity(array $row): array
    {
        foreach ([
            'quartier' => 'quartier',
            'sous_quartier' => 'sous_quartier',
            'cite' => 'cite',
            'village' => 'village',
            'carrefour' => 'carrefour',
        ] as $column => $type) {
            $name = trim((string) ($row[$column] ?? ''));
            if ($name !== '') {
                return [$column, $type, $name];
            }
        }

        return ['quartier', 'quartier', ''];
    }

    private function aliases(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($alias) => trim((string) $alias),
            preg_split('/[|;,]+/u', (string) $value) ?: []
        ))));
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function searchText(string $commune, string $name, array $aliases): string
    {
        return $this->normalize(implode(' ', [$commune, $name, ...$aliases]));
    }
};
