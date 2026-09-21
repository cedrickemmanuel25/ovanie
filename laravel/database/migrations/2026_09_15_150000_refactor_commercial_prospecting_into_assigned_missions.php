<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureMissionsTable();
        $this->ensureMissionMembersTable();
        $this->ensureMissionQuartersTable();
        $this->ensureProspectMissionLink();
        $this->ensureVisitMissionLink();
    }

    private function ensureMissionsTable(): void
    {
        if (Schema::hasTable('commercial_prospecting_missions')) {
            return;
        }

        Schema::create('commercial_prospecting_missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commune_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->unsignedInteger('shop_target')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Noms volontairement courts : MySQL limite les identifiants à 64 caractères.
            $table->foreign('commune_id', 'cpm_commune_fk')
                ->references('id')->on('abidjan_communes')->restrictOnDelete();
            $table->foreign('created_by_id', 'cpm_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['commune_id', 'starts_on', 'ends_on'], 'cpm_commune_period_idx');
        });
    }

    private function ensureMissionMembersTable(): void
    {
        if (Schema::hasTable('commercial_prospecting_mission_members')) {
            return;
        }

        Schema::create('commercial_prospecting_mission_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('commercial_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->foreign('mission_id', 'cpmm_mission_fk')
                ->references('id')->on('commercial_prospecting_missions')->cascadeOnDelete();
            $table->foreign('commercial_id', 'cpmm_commercial_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['mission_id', 'commercial_id'], 'cpmm_member_uq');
            $table->index(['commercial_id', 'mission_id'], 'cpmm_commercial_mission_idx');
        });
    }

    private function ensureMissionQuartersTable(): void
    {
        if (! Schema::hasTable('commercial_prospecting_mission_quarters')) {
            Schema::create('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id');
                $table->unsignedBigInteger('quarter_id');
                $table->unsignedBigInteger('updated_by_commercial_id')->nullable();
                $table->string('status', 24)->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('mission_id', 'cpmq_mission_fk')
                    ->references('id')->on('commercial_prospecting_missions')->cascadeOnDelete();
                $table->foreign('quarter_id', 'cpmq_quarter_fk')
                    ->references('id')->on('abidjan_quarters')->restrictOnDelete();
                $table->foreign('updated_by_commercial_id', 'cpmq_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index('status', 'cpmq_status_idx');
                $table->unique(['mission_id', 'quarter_id'], 'cpmq_mission_quarter_uq');
                $table->index(['mission_id', 'status'], 'cpmq_mission_status_idx');
            });

            return;
        }

        // Répare une table créée avant l'échec de l'ancienne migration.
        if (! Schema::hasColumn('commercial_prospecting_mission_quarters', 'updated_by_commercial_id')) {
            Schema::table('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->unsignedBigInteger('updated_by_commercial_id')->nullable()->after('quarter_id');
            });
        }

        if (! $this->foreignKeyExists('commercial_prospecting_mission_quarters', 'updated_by_commercial_id')) {
            Schema::table('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->foreign('updated_by_commercial_id', 'cpmq_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! $this->indexExists('commercial_prospecting_mission_quarters', 'cpmq_status_idx')
            && ! $this->indexCoversColumns('commercial_prospecting_mission_quarters', ['status'])) {
            Schema::table('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->index('status', 'cpmq_status_idx');
            });
        }

        if (! $this->indexExists('commercial_prospecting_mission_quarters', 'cpmq_mission_quarter_uq')
            && ! $this->uniqueIndexCoversColumns('commercial_prospecting_mission_quarters', ['mission_id', 'quarter_id'])) {
            Schema::table('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->unique(['mission_id', 'quarter_id'], 'cpmq_mission_quarter_uq');
            });
        }

        if (! $this->indexExists('commercial_prospecting_mission_quarters', 'cpmq_mission_status_idx')
            && ! $this->indexCoversColumns('commercial_prospecting_mission_quarters', ['mission_id', 'status'])) {
            Schema::table('commercial_prospecting_mission_quarters', function (Blueprint $table) {
                $table->index(['mission_id', 'status'], 'cpmq_mission_status_idx');
            });
        }
    }

    private function ensureProspectMissionLink(): void
    {
        if (! Schema::hasTable('commercial_prospects')) {
            return;
        }

        if (! Schema::hasColumn('commercial_prospects', 'mission_id')) {
            Schema::table('commercial_prospects', function (Blueprint $table) {
                $table->unsignedBigInteger('mission_id')->nullable()->after('id');
            });
        }

        if (! $this->foreignKeyExists('commercial_prospects', 'mission_id')) {
            Schema::table('commercial_prospects', function (Blueprint $table) {
                $table->foreign('mission_id', 'cpros_mission_fk')
                    ->references('id')->on('commercial_prospecting_missions')->nullOnDelete();
            });
        }

        if (! $this->indexExists('commercial_prospects', 'cpros_mission_q_status_idx')
            && ! $this->indexCoversColumns('commercial_prospects', ['mission_id', 'quarter_id', 'status'])) {
            Schema::table('commercial_prospects', function (Blueprint $table) {
                $table->index(['mission_id', 'quarter_id', 'status'], 'cpros_mission_q_status_idx');
            });
        }
    }

    private function ensureVisitMissionLink(): void
    {
        if (! Schema::hasTable('commercial_prospecting_visits')) {
            return;
        }

        if (! Schema::hasColumn('commercial_prospecting_visits', 'mission_id')) {
            Schema::table('commercial_prospecting_visits', function (Blueprint $table) {
                $table->unsignedBigInteger('mission_id')->nullable()->after('prospect_id');
            });
        }

        if (! $this->foreignKeyExists('commercial_prospecting_visits', 'mission_id')) {
            Schema::table('commercial_prospecting_visits', function (Blueprint $table) {
                $table->foreign('mission_id', 'cpvisit_mission_fk')
                    ->references('id')->on('commercial_prospecting_missions')->nullOnDelete();
            });
        }

        if (! $this->indexExists('commercial_prospecting_visits', 'cpvisit_mission_com_date_idx')
            && ! $this->indexCoversColumns('commercial_prospecting_visits', ['mission_id', 'commercial_id', 'visited_at'])) {
            Schema::table('commercial_prospecting_visits', function (Blueprint $table) {
                $table->index(['mission_id', 'commercial_id', 'visited_at'], 'cpvisit_mission_com_date_idx');
            });
        }
    }

    /**
     * Vérifie si la colonne possède déjà une clé étrangère.
     * La migration est ainsi relançable après un échec DDL MySQL partiel.
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();
        }

        if ($driver === 'sqlite') {
            $safeTable = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA foreign_key_list('{$safeTable}')") as $foreign) {
                if (($foreign->from ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            return DB::table('information_schema.key_column_usage as kcu')
                ->join('information_schema.table_constraints as tc', function ($join) {
                    $join->on('tc.constraint_name', '=', 'kcu.constraint_name')
                        ->on('tc.table_schema', '=', 'kcu.table_schema');
                })
                ->where('tc.constraint_type', 'FOREIGN KEY')
                ->where('kcu.table_name', $table)
                ->where('kcu.column_name', $column)
                ->exists();
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $safeTable = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA index_list('{$safeTable}')") as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $indexName)
                ->exists();
        }

        return false;
    }

    private function indexCoversColumns(string $table, array $columns): bool
    {
        return $this->matchingIndexExists($table, $columns, false);
    }

    private function uniqueIndexCoversColumns(string $table, array $columns): bool
    {
        return $this->matchingIndexExists($table, $columns, true);
    }

    private function matchingIndexExists(string $table, array $columns, bool $uniqueOnly): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return false;
        }

        $indexes = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->orderBy('INDEX_NAME')
            ->orderBy('SEQ_IN_INDEX')
            ->get(['INDEX_NAME', 'COLUMN_NAME', 'NON_UNIQUE'])
            ->groupBy('INDEX_NAME');

        foreach ($indexes as $parts) {
            if ($uniqueOnly && (int) ($parts->first()->NON_UNIQUE ?? 1) !== 0) {
                continue;
            }

            $indexedColumns = $parts->pluck('COLUMN_NAME')->filter()->values()->all();
            if ($indexedColumns === array_values($columns)) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (Schema::hasTable('commercial_prospecting_visits') && Schema::hasColumn('commercial_prospecting_visits', 'mission_id')) {
            $this->dropForeignForColumn('commercial_prospecting_visits', 'mission_id');
            Schema::table('commercial_prospecting_visits', fn (Blueprint $table) => $table->dropColumn('mission_id'));
        }

        if (Schema::hasTable('commercial_prospects') && Schema::hasColumn('commercial_prospects', 'mission_id')) {
            $this->dropForeignForColumn('commercial_prospects', 'mission_id');
            Schema::table('commercial_prospects', fn (Blueprint $table) => $table->dropColumn('mission_id'));
        }

        Schema::dropIfExists('commercial_prospecting_mission_quarters');
        Schema::dropIfExists('commercial_prospecting_mission_members');
        Schema::dropIfExists('commercial_prospecting_missions');
    }

    private function dropForeignForColumn(string $table, string $column): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($constraint) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($constraint));
            }
        }
    }
};
