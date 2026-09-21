<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_prospecting_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('abidjan_communes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('quarter_id')->nullable()->constrained('abidjan_quarters')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('abidjan_localities')->cascadeOnUpdate()->nullOnDelete();
            $table->string('priority', 20)->default('medium')->index();
            $table->unsignedTinyInteger('potential_score')->default(50)->index();
            $table->json('target_types')->nullable();
            $table->string('status', 30)->default('to_prospect')->index();
            $table->timestamp('last_prospected_at')->nullable()->index();
            $table->timestamp('next_recommended_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['commune_id', 'quarter_id', 'locality_id'], 'commercial_area_geo_unique');
        });

        Schema::create('commercial_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained('commercial_prospecting_areas')->nullOnDelete();
            $table->foreignId('commune_id')->constrained('abidjan_communes')->restrictOnDelete();
            $table->foreignId('quarter_id')->nullable()->constrained('abidjan_quarters')->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('abidjan_localities')->nullOnDelete();
            $table->foreignId('discovered_by_commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('business_name', 180);
            $table->string('category', 120)->nullable()->index();
            $table->string('contact_name', 180)->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('whatsapp', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('landmark', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 40)->default('to_visit')->index();
            $table->string('potential', 20)->default('medium')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_visited_at')->nullable()->index();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index(['commune_id', 'quarter_id', 'status'], 'commercial_prospect_geo_status');
        });

        Schema::create('commercial_prospecting_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('commercial_prospecting_areas')->cascadeOnDelete();
            $table->foreignId('commercial_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress')->index();
            $table->timestamp('started_at')->useCurrent()->index();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_prospecting_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('commercial_prospects')->cascadeOnDelete();
            $table->foreignId('commercial_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('commercial_prospecting_sessions')->nullOnDelete();
            $table->string('outcome', 40)->index();
            $table->text('notes')->nullable();
            $table->timestamp('visited_at')->useCurrent()->index();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Initialise les zones à partir du référentiel OVANIE existant, sans dupliquer les libellés géographiques.
        if (Schema::hasTable('abidjan_localities')) {
            $now = now();
            $rows = DB::table('abidjan_localities')
                ->where('is_active', true)
                ->whereNotNull('commune_id')
                ->select(['id', 'commune_id', 'quarter_id'])
                ->orderBy('id')
                ->get();

            foreach ($rows->chunk(200) as $chunk) {
                DB::table('commercial_prospecting_areas')->insert($chunk->map(fn ($row) => [
                    'commune_id' => $row->commune_id,
                    'quarter_id' => $row->quarter_id,
                    'locality_id' => $row->id,
                    'priority' => 'medium',
                    'potential_score' => 50,
                    'target_types' => json_encode(['Vendeur BTP', 'Quincaillerie', 'Matériaux de construction'], JSON_UNESCAPED_UNICODE),
                    'status' => 'to_prospect',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_prospecting_visits');
        Schema::dropIfExists('commercial_prospecting_sessions');
        Schema::dropIfExists('commercial_prospects');
        Schema::dropIfExists('commercial_prospecting_areas');
    }
};
