<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OVANIE ne possède pas ses propres livreurs : ce sont des livreurs partenaires
 * contactés par l'équipe Logistique, invités par leur numéro de téléphone, puis
 * qui complètent eux-mêmes leur dossier depuis l'app mobile "OVANIE Livreur".
 *
 * Cette migration ajoute :
 * - le cycle de vie d'inscription (onboarding_status + horodatages + revue admin) ;
 * - la séparation first_name / last_name (le champ historique `name` reste
 *   synchronisé automatiquement par le modèle pour ne rien casser côté vues/API).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_drivers')) {
            return;
        }

        Schema::table('delivery_drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_drivers', 'first_name')) {
                $table->string('first_name', 120)->nullable()->after('name');
            }
            if (! Schema::hasColumn('delivery_drivers', 'last_name')) {
                $table->string('last_name', 120)->nullable()->after('first_name');
            }

            // La colonne est ajoutée nullable (pas de valeur par défaut SQL) afin de
            // pouvoir distinguer, dans le bloc ci-dessous, les lignes déjà existantes
            // (qu'on bascule explicitement sur "active") des lignes réellement
            // nouvelles qui recevront désormais 'invited' comme défaut applicatif
            // (voir App\Models\DeliveryDriver::ONBOARDING_INVITED / boot()).
            if (! Schema::hasColumn('delivery_drivers', 'onboarding_status')) {
                $table->string('onboarding_status', 30)->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('delivery_drivers', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('onboarding_status');
            }
            if (! Schema::hasColumn('delivery_drivers', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('delivery_drivers', 'reviewed_by')) {
                // Les comptes internes (admin/logistique/support/commercial) sont
                // stockés dans la table `users` (voir App\Models\InternalUser et la
                // migration admin_logs qui suit la même convention).
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_drivers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reviewed_by');
            }
        });

        // Toutes les lignes qui existaient déjà avant cette migration ont forcément
        // été créées via l'ancien parcours (déjà opérationnelles) : on les bascule
        // donc explicitement sur 'active' avant que le défaut applicatif 'invited'
        // ne s'applique aux nouvelles créations.
        DB::table('delivery_drivers')
            ->whereNull('onboarding_status')
            ->update(['onboarding_status' => 'active']);

        // Reconstitue first_name/last_name à partir du champ historique `name`
        // pour les lignes existantes (meilleur effort : premier mot = prénom).
        DB::table('delivery_drivers')
            ->whereNull('first_name')
            ->orWhereNull('last_name')
            ->get(['id', 'name'])
            ->each(function ($row) {
                $name = trim((string) $row->name);
                if ($name === '') {
                    return;
                }
                $parts = preg_split('/\s+/', $name);
                $first = array_shift($parts) ?: $name;
                $last = trim(implode(' ', $parts));

                DB::table('delivery_drivers')->where('id', $row->id)->update([
                    'first_name' => $first,
                    'last_name' => $last !== '' ? $last : $first,
                ]);
            });
    }

    public function down(): void
    {
        // Colonnes volontairement conservées : elles portent l'historique
        // d'inscription des livreurs (voir convention du reste du projet).
    }
};
