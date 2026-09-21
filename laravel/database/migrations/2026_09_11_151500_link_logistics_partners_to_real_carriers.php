<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_partners')) {
            return;
        }

        $addCarrierId = ! Schema::hasColumn('logistics_partners', 'carrier_id');
        $addLogoPath = ! Schema::hasColumn('logistics_partners', 'logo_path');
        $addContractPath = ! Schema::hasColumn('logistics_partners', 'contract_path');

        if ($addCarrierId || $addLogoPath || $addContractPath) {
            Schema::table('logistics_partners', function (Blueprint $table) use ($addCarrierId, $addLogoPath, $addContractPath) {
                if ($addCarrierId) {
                    $table->unsignedBigInteger('carrier_id')->nullable()->index();
                }
                if ($addLogoPath) {
                    $table->string('logo_path')->nullable();
                }
                if ($addContractPath) {
                    $table->string('contract_path')->nullable();
                }
            });
        }

        // Retire uniquement les fiches générées par l'ancien jeu de démonstration.
        DB::table('logistics_partners')
            ->whereIn('code', [
                'OVL-INT', 'OVL-YNG', 'OVL-BTP', 'OVL-IVE', 'OVL-HMA',
                'OVL-UFC', 'OVL-RCP', 'OVL-STS', 'OVL-AEP',
            ])
            ->whereNull('carrier_id')
            ->delete();

        // Relie les éventuelles fiches réelles existantes à leur transporteur métier.
        if (Schema::hasTable('carriers')) {
            $profiles = DB::table('logistics_partners')->whereNull('carrier_id')->get(['id', 'name']);
            foreach ($profiles as $profile) {
                $carrier = DB::table('carriers')->where('name', $profile->name)->first(['id']);
                if ($carrier) {
                    DB::table('logistics_partners')->where('id', $profile->id)->update(['carrier_id' => $carrier->id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('logistics_partners')) {
            return;
        }

        // Les données métier créées après cette migration ne doivent pas être détruites
        // automatiquement par un rollback. Les colonnes sont donc conservées.
    }
};
