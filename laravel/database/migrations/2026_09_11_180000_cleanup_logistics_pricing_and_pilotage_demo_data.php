<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pricingTables = [
            'delivery_vehicle_rate_cards',
            'logistics_pricing_grids',
            'logistics_pricing_matrices',
            'logistics_pricing_supplements',
            'logistics_pricing_special_zones',
            'logistics_pricing_rules',
        ];

        foreach ($pricingTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'meta')) {
                continue;
            }

            DB::table($table)
                ->select(['id', 'meta'])
                ->orderBy('id')
                ->get()
                ->each(function ($row) use ($table) {
                    $meta = $row->meta;
                    if (is_string($meta)) {
                        $decoded = json_decode($meta, true);
                        $meta = is_array($decoded) ? $decoded : [];
                    } elseif (is_object($meta)) {
                        $meta = (array) $meta;
                    }

                    if ((bool) data_get((array) $meta, 'pricing_ui_seed', false)) {
                        DB::table($table)->where('id', $row->id)->delete();
                    }
                });
        }

        if (Schema::hasTable('logistics_pilotage_settings')) {
            DB::table('logistics_pilotage_settings')->whereIn('setting_key', [
                'timezone',
                'currency',
                'mode',
                'coverage_radius',
                'automatic_tours',
                'sms_confirmation',
                'proof_required',
                'pickup_tolerance',
                'critical_incident',
                'frequency',
                'precision',
                'zone_exit',
                'extended_stop',
                'alert_recipients',
                'auto_assignment',
                'vehicle_compatibility',
                'proof_required_rule',
                'reassignment',
            ])->delete();
        }
    }

    public function down(): void
    {
        // Nettoyage volontaire : les anciennes données de démonstration et les
        // paramètres non fonctionnels ne sont pas recréés lors d'un rollback.
    }
};
