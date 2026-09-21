<?php

namespace Database\Seeders;

use App\Models\SupportAgentHandoff;
use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupportPagesSeeder extends Seeder
{
    public function run(): void
    {
        $agent = SupportAiAgent::query()->updateOrCreate(
            ['code' => 'AI-LOGISTICS-SUPPORT'],
            [
                'name' => 'Support IA', 'slug' => 'support-ia-logistics', 'role_key' => 'logistics',
                'description' => 'Agent de transfert des dossiers logistiques.', 'status' => 'active',
                'channels' => ['chat', 'web'], 'routing_keywords' => ['livraison', 'retard', 'retour', 'adresse'],
                'capabilities' => ['handoff'], 'is_default' => false,
            ]
        );

        $firstCases = [
            ['SUP-0076','08/09/2025 14:32','Support IA','ai','Koffi Alain','Retard de livraison','Client signale un retard de 2 jours','Livraison','high','pending','4h','—','14:32'],
            ['SUP-0075','08/09/2025 11:20','Support humain','human','Konan Marie','Adresse introuvable','Vérifier et contacter le client','Adresse','normal','assigned','12h','Yao S.','11:20'],
            ['SUP-0074','07/09/2025 16:48','Support IA','ai','Yao Stéphane','Retour produit','Planifier la collecte','Retour','high','assigned','4h','Diallo S.','10:15'],
            ['SUP-0073','07/09/2025 14:15','Support humain','human','Diallo Seydou','Produit endommagé','À la livraison','Litige','critical','in_progress','2h','Kouamé Y.','09:48'],
            ['SUP-0072','06/09/2025 10:03','Support IA','ai','Adama Koné',"Changement d'adresse",'Nouvelle adresse chantier','Adresse','normal','accepted','12h','Traoré B.','08:30'],
            ['SUP-0071','05/09/2025 17:40','Support IA','ai',"N'Guessan Paul",'Annulation de commande','Après préparation','Commande','low','resolved','24h','—','17:40'],
            ['SUP-0070','05/09/2025 09:25','Support humain','human','Kouamé Yao','Problème de paiement','Vérification statuts','Paiement','high','assigned','4h','Yao S.','09:25'],
            ['SUP-0069','04/09/2025 15:10','Support IA','ai','Traoré Brice',"Demande d'information",'Délais de livraison','Information','low','resolved','24h','—','15:10'],
            ['SUP-0068','03/09/2025 11:52','Support humain','human','Koffi Adjoua','Litige client','Colis incomplet','Litige','high','assigned','4h','Diallo S.','11:52'],
            ['SUP-0067','02/09/2025 14:08','Support IA','ai','Bionnou Mensah','Collecte retour urgente','Client disponible ce soir','Retour','critical','pending','2h','—','14:08'],
        ];

        foreach ($firstCases as $row) {
            [$reference,$date,$source,$sourceKind,$client,$subject,$description,$type,$severity,$status,$sla,$assigned,$latestTime] = $row;
            $requestedAt = CarbonImmutable::createFromFormat('d/m/Y H:i', $date, 'UTC');
            $conversation = $this->conversation($agent, $reference, $client, $subject, $description, $type, $source, $assigned, [
                'sla_hours' => (int) $sla,
                'latest_time' => $latestTime,
            ]);

            if ($reference === 'SUP-0076') {
                $conversation->update([
                    'requester_phone' => '+225 07 12 34 56 78',
                    'summary' => "Client signale un retard de 2 jours sur sa commande. Demande des informations et une nouvelle date de livraison.",
                    'metadata' => array_merge($conversation->metadata ?? [], $this->detailMetadata()),
                ]);
            }

            SupportAgentHandoff::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'support_conversation_id' => $conversation->id,
                    'ai_agent_id' => $agent->id,
                    'requested_by_type' => $sourceKind,
                    'target_department' => 'logistique',
                    'queue_key' => 'delivery_support',
                    'severity' => $severity,
                    'status' => $status,
                    'reason' => $subject,
                    'notes' => $reference === 'SUP-0076' ? null : $description,
                    'requested_at' => $requestedAt,
                    'assigned_at' => in_array($status, ['assigned', 'accepted', 'in_progress'], true) ? $requestedAt->addMinutes(3) : null,
                    'accepted_at' => in_array($status, ['accepted', 'in_progress'], true) ? $requestedAt->addMinutes(5) : null,
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $requestedAt->addHours(4) : null,
                    'due_at' => $requestedAt->addHours((int) $sla),
                ]
            );
        }

        $remainingStatuses = array_merge(
            array_fill(0, 10, 'pending'),
            array_fill(0, 4, 'assigned'),
            array_fill(0, 5, 'in_progress'),
            array_fill(0, 27, 'accepted'),
            array_fill(0, 18, 'resolved'),
            array_fill(0, 2, 'closed'),
        );
        $remainingPriorities = array_merge(
            array_fill(0, 3, 'critical'),
            array_fill(0, 14, 'high'),
            array_fill(0, 30, 'normal'),
            array_fill(0, 19, 'low'),
        );
        $subjects = [
            ['Retard de livraison','Client demande une nouvelle estimation','Livraison'],
            ['Adresse à confirmer','Point de repère incomplet','Adresse'],
            ['Retour à organiser','Planification de la collecte','Retour'],
            ['Colis endommagé','Vérification avec le transporteur','Litige'],
            ['Suivi de commande','Demande de position du livreur','Information'],
            ['Paiement à la livraison','Confirmation avant remise','Paiement'],
        ];
        $clients = ['Awa Coulibaly','Mariam Touré','Yannick Koffi','Aïcha Koné','Serge Kouassi','Fatou Bamba','Jean Yao','Nadia Traoré'];
        $assignments = ['Yao S.','Diallo S.','Kouamé Y.','Traoré B.','—'];

        $cursor = CarbonImmutable::create(2025, 9, 1, 18, 0, 0, 'UTC');
        for ($i = 0; $i < 66; $i++) {
            $number = 66 - $i;
            $reference = 'SUP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            [$subject,$description,$type] = $subjects[$i % count($subjects)];
            $client = $clients[$i % count($clients)];
            $status = $remainingStatuses[$i];
            $severity = $remainingPriorities[$i];
            $sourceKind = $i % 3 === 1 ? 'human' : 'ai';
            $source = $sourceKind === 'human' ? 'Support humain' : 'Support IA';
            $assigned = in_array($status, ['assigned','accepted','in_progress'], true) ? $assignments[$i % 4] : '—';
            $slaHours = match ($severity) { 'critical' => 2, 'high' => 4, 'normal' => 12, default => 24 };
            $date = $cursor->subHours($i * 4 + intdiv($i, 4) * 3);

            $conversation = $this->conversation($agent, $reference, $client, $subject, $description, $type, $source, $assigned, [
                'sla_hours' => $slaHours,
                'latest_time' => $date->format('H:i'),
            ]);

            SupportAgentHandoff::query()->updateOrCreate(
                ['reference' => $reference],
                [
                    'support_conversation_id' => $conversation->id,
                    'ai_agent_id' => $agent->id,
                    'requested_by_type' => $sourceKind,
                    'target_department' => 'logistique',
                    'queue_key' => 'delivery_support',
                    'severity' => $severity,
                    'status' => $status,
                    'reason' => $subject,
                    'notes' => $description,
                    'requested_at' => $date,
                    'assigned_at' => in_array($status, ['assigned','accepted','in_progress'], true) ? $date->addMinutes(6) : null,
                    'accepted_at' => in_array($status, ['accepted','in_progress'], true) ? $date->addMinutes(10) : null,
                    'resolved_at' => in_array($status, ['resolved','closed'], true) ? $date->addHours(3) : null,
                    'due_at' => $date->addHours($slaHours),
                ]
            );
        }

        // Historique cumulé : 118 dossiers clôturés hors de la file courante + 2 clôturés courants = 120.
        for ($i = 1; $i <= 118; $i++) {
            $date = CarbonImmutable::create(2025, 8, 1, 8, 0, 0, 'UTC')->subDays($i);
            SupportAgentHandoff::query()->updateOrCreate(
                ['reference' => 'SUP-ARCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                [
                    'target_department' => 'logistique', 'queue_key' => 'delivery_support_archive',
                    'ai_agent_id' => $agent->id, 'requested_by_type' => 'ai', 'severity' => 'normal',
                    'status' => 'closed', 'reason' => 'Dossier logistique clôturé', 'requested_at' => $date,
                    'resolved_at' => $date->addHours(5), 'due_at' => $date->addHours(12),
                ]
            );
        }

        $this->seedDetailMessages($agent, SupportConversation::query()->where('metadata->reference', 'SUP-0076')->first());
    }

    private function conversation(SupportAiAgent $agent, string $reference, string $client, string $subject, string $description, string $type, string $source, string $assigned, array $extra = []): SupportConversation
    {
        $existing = SupportConversation::query()->where('metadata->reference', $reference)->first();
        $metadata = array_merge([
            'reference' => $reference,
            'source_label' => $source,
            'client_name' => $client,
            'client_phone' => '—',
            'channel_label' => 'Application client',
            'case_type' => $type,
            'subject_detail' => $description,
            'assigned_label' => $assigned,
        ], $extra);

        if ($reference === 'SUP-0076') {
            $metadata['dashboard'] = [
                'new_today' => 4,
                'taken_delta' => '+18 cette semaine',
                'sla_percent' => 87,
                'sla_in_time' => 66,
                'sla_late' => 10,
                'avg_pickup' => '1h 35',
                'avg_resolution' => '6h 20',
                'closed_lifetime' => 120,
            ];
        }

        if ($existing) {
            $existing->update([
                'requester_name' => $client, 'channel' => 'chat', 'status' => 'active', 'ai_agent_id' => $agent->id,
                'subject' => $subject, 'summary' => $description, 'priority' => 'normal', 'requires_human' => true,
                'metadata' => array_merge($existing->metadata ?? [], $metadata),
            ]);
            return $existing;
        }

        return SupportConversation::create([
            'public_token' => (string) Str::uuid(),
            'requester_name' => $client,
            'channel' => 'chat',
            'status' => 'active',
            'ai_agent_id' => $agent->id,
            'subject' => $subject,
            'summary' => $description,
            'priority' => 'normal',
            'requires_human' => true,
            'metadata' => $metadata,
        ]);
    }

    private function detailMetadata(): array
    {
        return [
            'client_phone' => '+225 07 12 34 56 78',
            'channel_label' => 'Application client',
            'case_type' => 'Livraison',
            'sla_hours' => 4,
            'sla_remaining' => '2h15min',
            'assigned_label' => 'Non assigné',
            'order_number' => 'CMD-4587',
            'order_date' => '04/09/2025 10:15',
            'product_name' => 'Ciment 50 kg',
            'product_quantity' => 20,
            'products_count' => 2,
            'delivery_mode' => 'OVANIE Logistics',
            'tracking_number' => 'OVL-2025-87421',
            'delivery_status' => 'En transit',
            'expected_date' => '06/09/2025',
            'delivery_address' => 'Cocody Angré, Abidjan',
            'problem_category' => 'Retard de livraison',
            'problem_severity' => 'Importante',
            'problem_details' => "Le client indique que sa commande devait être livrée le 06/09 mais n'a pas encore été reçue. Il souhaite connaître la nouvelle date de livraison et être informé de la situation.",
            'initial_description' => "Client signale un retard de 2 jours sur sa commande. Demande des informations et une nouvelle date de livraison.",
            'attachments' => [
                ['type' => 'image', 'label' => 'Photo du colis'],
                ['type' => 'pdf', 'label' => 'Bon de livraison.pdf'],
            ],
            'resolution_label' => 'En attente',
            'history' => [
                ['time' => '08/09/2025 14:32', 'tone' => 'green', 'title' => 'Dossier créé par le Support IA', 'text' => 'Transfert automatique depuis la conversation client'],
                ['time' => '08/09/2025 14:35', 'tone' => 'blue', 'title' => "Pris en charge par l'équipe logistique", 'text' => 'Dossier attribué au service Opérations', 'avatar' => 'RL'],
                ['time' => '08/09/2025 15:10', 'tone' => 'blue', 'title' => 'Contact transporteur', 'text' => 'Vérification de la position du véhicule'],
                ['time' => '—', 'tone' => 'orange', 'title' => 'Mise à jour', 'text' => 'En attente de retour du transporteur'],
                ['time' => '—', 'tone' => 'green', 'title' => 'Résolution', 'text' => 'À compléter'],
            ],
        ];
    }

    private function seedDetailMessages(SupportAiAgent $agent, ?SupportConversation $conversation): void
    {
        if (! $conversation) {
            return;
        }

        SupportConversationMessage::query()->where('support_conversation_id', $conversation->id)->delete();
        $rows = [
            ['08/09/2025 14:20','user',null,"Bonjour, ma commande CMD-4587 devait être livrée le 06/09 mais je ne l'ai toujours pas reçue.\nPouvez-vous me dire où en est la livraison ?",['sender_label'=>'Client - Koffi Alain','initials'=>'KA','tone'=>'client']],
            ['08/09/2025 14:22','ai',$agent->id,"Bonjour, merci pour votre message. Votre demande a été transmise à notre équipe logistique pour suivi.\nVous serez tenu informé dès que nous aurons une mise à jour.",['sender_label'=>'Support IA','initials'=>'AI','tone'=>'ai']],
            ['08/09/2025 15:10','human',null,"Bonjour M. Koffi, nous avons contacté le transporteur. Le véhicule est en route et devrait arriver ce soir\nentre 18h et 20h. Nous restons disponibles.",['sender_label'=>'Équipe Logistique - Marie S.','initials'=>'MS','tone'=>'team']],
            ['08/09/2025 15:12','user',null,"Merci pour l'information.",['sender_label'=>'Client - Koffi Alain','initials'=>'KA','tone'=>'client']],
        ];

        foreach ($rows as [$date,$senderType,$aiAgentId,$body,$metadata]) {
            $message = SupportConversationMessage::create([
                'support_conversation_id' => $conversation->id,
                'sender_type' => $senderType,
                'ai_agent_id' => $aiAgentId,
                'body' => $body,
                'format' => 'text',
                'is_internal' => false,
                'provider' => 'seed',
                'metadata' => $metadata,
            ]);
            $message->timestamps = false;
            $message->created_at = CarbonImmutable::createFromFormat('d/m/Y H:i', $date, 'UTC');
            $message->updated_at = $message->created_at;
            $message->save();
        }
    }
}
