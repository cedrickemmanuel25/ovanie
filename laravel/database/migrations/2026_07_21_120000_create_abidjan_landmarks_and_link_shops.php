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
        if (! Schema::hasTable('abidjan_landmarks')) {
            Schema::create('abidjan_landmarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commune_id')->constrained('abidjan_communes')->cascadeOnDelete();
                $table->foreignId('quarter_id')->constrained('abidjan_quarters')->cascadeOnDelete();
                $table->string('name', 180);
                $table->string('slug', 200);
                $table->string('category', 80)->nullable();
                $table->string('address', 255)->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->string('source', 50)->default('ovanie');
                $table->string('source_reference', 255)->nullable();
                $table->boolean('is_verified')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['quarter_id', 'slug', 'source'], 'abidjan_landmarks_quarter_slug_source_unique');
                $table->index(['commune_id', 'quarter_id', 'is_active'], 'abidjan_landmarks_location_active_index');
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (! Schema::hasColumn('shops', 'commune_id')) {
                    $table->foreignId('commune_id')->nullable()->after('commune')
                        ->constrained('abidjan_communes')->nullOnDelete();
                }
                if (! Schema::hasColumn('shops', 'quarter_id')) {
                    $table->foreignId('quarter_id')->nullable()->after('district')
                        ->constrained('abidjan_quarters')->nullOnDelete();
                }
                if (! Schema::hasColumn('shops', 'landmark_id')) {
                    $table->foreignId('landmark_id')->nullable()->after('landmark')
                        ->constrained('abidjan_landmarks')->nullOnDelete();
                }
            });

            $this->backfillExistingShops();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                foreach (['landmark_id', 'quarter_id', 'commune_id'] as $column) {
                    if (Schema::hasColumn('shops', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        Schema::dropIfExists('abidjan_landmarks');
    }

    private function backfillExistingShops(): void
    {
        if (! Schema::hasTable('abidjan_communes') || ! Schema::hasTable('abidjan_quarters')) {
            return;
        }

        $communes = DB::table('abidjan_communes')->get(['id', 'name', 'slug', 'aliases']);
        $communeLookup = [];
        foreach ($communes as $commune) {
            foreach ([$commune->name, $commune->slug, ...((array) json_decode($commune->aliases ?: '[]', true))] as $alias) {
                $communeLookup[Str::slug((string) $alias)] = $commune;
            }
        }

        foreach (DB::table('shops')->get() as $shop) {
            $commune = $communeLookup[Str::slug((string) ($shop->commune ?? ''))] ?? null;
            if (! $commune) {
                continue;
            }

            $quarter = DB::table('abidjan_quarters')
                ->where('commune_id', $commune->id)
                ->where('slug', Str::slug((string) ($shop->district ?? '')))
                ->first();

            $values = ['commune_id' => $commune->id];
            if ($quarter) {
                $values['quarter_id'] = $quarter->id;
            }

            if ($quarter
                && filled($shop->landmark ?? null)
                && is_numeric($shop->latitude ?? null)
                && is_numeric($shop->longitude ?? null)) {
                $slug = Str::slug((string) $shop->landmark);
                DB::table('abidjan_landmarks')->updateOrInsert(
                    [
                        'quarter_id' => $quarter->id,
                        'slug' => $slug,
                        'source' => 'shop_backfill',
                    ],
                    [
                        'commune_id' => $commune->id,
                        'name' => trim((string) $shop->landmark),
                        'category' => 'point_de_repere',
                        'address' => $shop->address ?? null,
                        'latitude' => $shop->latitude,
                        'longitude' => $shop->longitude,
                        'source_reference' => 'shop:' . $shop->id,
                        'is_verified' => in_array((string) ($shop->geo_status ?? ''), ['verified', 'reliable'], true),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $values['landmark_id'] = DB::table('abidjan_landmarks')
                    ->where('quarter_id', $quarter->id)
                    ->where('slug', $slug)
                    ->where('source', 'shop_backfill')
                    ->value('id');
            }

            DB::table('shops')->where('id', $shop->id)->update($values);
        }
    }
};
