<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'severity',
        'latitude',
        'longitude',
        'occurred_at',
        'resolved_by',
        'resolved_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('delivery_incidents')) {
            return;
        }

        $missing = collect(self::COLUMNS)
            ->reject(fn (string $column) => Schema::hasColumn('delivery_incidents', $column))
            ->values()
            ->all();

        if ($missing === []) {
            return;
        }

        Schema::table('delivery_incidents', function (Blueprint $table) use ($missing) {
            if (in_array('severity', $missing, true)) {
                $table->string('severity', 20)->default('medium')->after('incident_type');
            }
            if (in_array('latitude', $missing, true)) {
                $table->decimal('latitude', 10, 7)->nullable()->after('description');
            }
            if (in_array('longitude', $missing, true)) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (in_array('occurred_at', $missing, true)) {
                $table->dateTime('occurred_at')->nullable()->after('longitude');
            }
            if (in_array('resolved_by', $missing, true)) {
                $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_note');
            }
            if (in_array('resolved_at', $missing, true)) {
                $table->dateTime('resolved_at')->nullable()->after('resolved_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_incidents')) {
            return;
        }

        $existing = collect(self::COLUMNS)
            ->filter(fn (string $column) => Schema::hasColumn('delivery_incidents', $column))
            ->values()
            ->all();

        if ($existing === []) {
            return;
        }

        Schema::table('delivery_incidents', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
