<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_ai_agents')) {
            return;
        }

        $now = now();
        $agents = [
            [
                'code' => 'IA-SUP-NNAN', 'name' => 'N’Nan', 'slug' => 'miss-nnan', 'role_key' => 'general',
                'description' => 'Assistante générale OVANIE. Elle accueille, répond aux questions courantes et oriente la demande vers le bon domaine.',
                'system_prompt' => 'Tu es N’Nan, point d’entrée général du Support OVANIE. Réponds uniquement avec les données OVANIE réellement disponibles. N’invente ni commande, ni paiement, ni produit, ni procédure. Oriente vers le domaine compétent lorsqu’une action spécialisée est nécessaire.',
                'channels' => ['chat', 'whatsapp', 'email'], 'routing_keywords' => ['compte','commande','produit','aide'],
                'capabilities' => ['answer','route','handoff'], 'status' => 'active', 'is_default' => true,
            ],
            [
                'code' => 'IA-SUP-RITA', 'name' => 'Miss Rita', 'slug' => 'miss-rita', 'role_key' => 'business',
                'description' => 'Assistante OVANIE Business pour les devis, proformas, appels d’offres et achats professionnels importants.',
                'system_prompt' => 'Tu es Miss Rita, assistante OVANIE Business. Tu traites uniquement les besoins professionnels : devis, proforma, appels d’offres et achats en volume. Utilise les données réelles OVANIE et ne promets aucune action qui n’a pas été exécutée.',
                'channels' => ['chat', 'whatsapp', 'email'], 'routing_keywords' => ['devis','proforma','appel d’offres','gros achat'],
                'capabilities' => ['answer','commercial_handoff'], 'status' => 'active', 'is_default' => false,
            ],
            [
                'code' => 'IA-SUP-SALOME', 'name' => 'Miss Salomé', 'slug' => 'miss-salome', 'role_key' => 'escalation',
                'description' => 'Assistante des réclamations complexes, litiges, contestations et situations nécessitant une intervention humaine.',
                'system_prompt' => 'Tu es Miss Salomé. Tu traites les litiges, plaintes, contestations et réclamations complexes avec les données OVANIE autorisées. Demande une prise en charge humaine uniquement lorsqu’une décision humaine ou une action non automatisable est réellement nécessaire.',
                'channels' => ['chat', 'whatsapp', 'email'], 'routing_keywords' => ['litige','plainte','contestation','responsable'],
                'capabilities' => ['answer','human_handoff'], 'status' => 'active', 'is_default' => false,
            ],
            [
                'code' => 'IA-SUP-LOGISTIQUE', 'name' => 'Assistante Logistique OVANIE', 'slug' => 'assistante-logistique-ovanie', 'role_key' => 'logistics',
                'description' => 'Assistante spécialisée dans les livraisons, suivis, retards, adresses et incidents logistiques.',
                'system_prompt' => 'Tu es l’assistante Logistique OVANIE. Utilise uniquement les vraies commandes, livraisons, missions, statuts et incidents fournis par Laravel. N’invente aucune position de livreur, heure de livraison ou incident.',
                'channels' => ['chat', 'whatsapp', 'email'], 'routing_keywords' => ['livraison','retard','suivi','adresse','livreur'],
                'capabilities' => ['answer','logistics_handoff'], 'status' => 'active', 'is_default' => false,
            ],
            [
                'code' => 'IA-SUP-TECHNIQUE', 'name' => 'Assistante Technique OVANIE', 'slug' => 'assistante-technique-ovanie', 'role_key' => 'technical',
                'description' => 'Assistante pour les problèmes de connexion, compte, formulaire, boutique, produit, site et applications mobiles.',
                'system_prompt' => 'Tu es l’assistante Technique OVANIE. Aide étape par étape sur les problèmes techniques en t’appuyant uniquement sur les fonctionnalités et procédures réellement disponibles. N’invente aucune procédure ou compatibilité.',
                'channels' => ['chat', 'whatsapp', 'email'], 'routing_keywords' => ['connexion','erreur','application','formulaire','boutique','produit'],
                'capabilities' => ['answer','technical_guidance'], 'status' => 'active', 'is_default' => false,
            ],
        ];

        foreach ($agents as $agent) {
            $payload = $agent;
            foreach (['channels', 'routing_keywords', 'capabilities'] as $jsonColumn) {
                $payload[$jsonColumn] = json_encode($payload[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $payload['updated_at'] = $now;

            $exists = DB::table('support_ai_agents')->where('code', $agent['code'])->exists();
            if ($exists) {
                DB::table('support_ai_agents')->where('code', $agent['code'])->update($payload);
            } else {
                $payload['created_at'] = $now;
                DB::table('support_ai_agents')->insert($payload);
            }
        }

        // Ancien assistant créé par SupportPagesSeeder pour les maquettes.
        // On le conserve pour l’intégrité des anciens journaux mais il ne doit
        // plus router de nouvelles conversations.
        DB::table('support_ai_agents')
            ->where('code', 'AI-LOGISTICS-SUPPORT')
            ->update(['status' => 'unavailable', 'is_default' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_ai_agents')) {
            return;
        }

        DB::table('support_ai_agents')->whereIn('code', [
            'IA-SUP-NNAN', 'IA-SUP-RITA', 'IA-SUP-SALOME', 'IA-SUP-LOGISTIQUE', 'IA-SUP-TECHNIQUE',
        ])->delete();
    }
};
