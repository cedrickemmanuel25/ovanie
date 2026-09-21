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
        if (! Schema::hasTable('abidjan_communes')) {
            Schema::create('abidjan_communes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->json('aliases')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('abidjan_quarters')) {
            Schema::create('abidjan_quarters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commune_id')->constrained('abidjan_communes')->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('slug', 180);
                $table->json('aliases')->nullable();
                $table->string('type', 30)->default('quartier');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('source', 50)->default('ovanie_seed');
                $table->string('source_reference', 255)->nullable();
                $table->boolean('is_verified')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['commune_id', 'slug'], 'abidjan_quarters_commune_slug_unique');
                $table->index(['commune_id', 'is_active'], 'abidjan_quarters_commune_active_index');
            });
        }

        $this->seedRegistry();
    }

    public function down(): void
    {
        Schema::dropIfExists('abidjan_quarters');
        Schema::dropIfExists('abidjan_communes');
    }

    private function seedRegistry(): void
    {
        $now = now();

        foreach ((array) config('abidjan_localities.communes', []) as $name => $definition) {
            $slug = Str::slug((string) $name);

            DB::table('abidjan_communes')->updateOrInsert(
                ['slug' => $slug],
                [
                    'code' => (string) ($definition['code'] ?? Str::upper(Str::substr(Str::slug($name, ''), 0, 3))),
                    'name' => (string) $name,
                    'aliases' => json_encode(array_values(array_unique(array_filter((array) ($definition['aliases'] ?? [])))), JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $communeId = DB::table('abidjan_communes')->where('slug', $slug)->value('id');
            if (! $communeId) {
                continue;
            }

            foreach ((array) ($definition['quarters'] ?? []) as $quarter) {
                $row = is_array($quarter) ? $quarter : ['name' => $quarter];
                $quarterName = trim((string) ($row['name'] ?? ''));
                if ($quarterName === '') {
                    continue;
                }

                DB::table('abidjan_quarters')->updateOrInsert(
                    [
                        'commune_id' => $communeId,
                        'slug' => Str::slug($quarterName),
                    ],
                    [
                        'name' => $quarterName,
                        'aliases' => json_encode(array_values(array_unique(array_filter((array) ($row['aliases'] ?? [])))), JSON_UNESCAPED_UNICODE),
                        'type' => (string) ($row['type'] ?? 'quartier'),
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                        'source' => (string) ($row['source'] ?? 'ovanie_seed'),
                        'source_reference' => $row['source_reference'] ?? null,
                        'is_verified' => (bool) ($row['verified'] ?? false),
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }
};
