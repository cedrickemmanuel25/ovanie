<?php

namespace App\Console\Commands;

use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Console\Command;

class SupportIntegrationsStatusCommand extends Command
{
    protected $signature = 'support:integrations-status';

    protected $description = 'Affiche l’état de configuration OpenAI, WhatsApp et du webhook Support sans révéler les secrets.';

    public function handle(SupportServiceStatusService $services): int
    {
        $statuses = $services->all();

        $this->table(
            ['Service', 'Fournisseur', 'Configuré', 'Opérationnel', 'Message'],
            collect(['ai', 'whatsapp', 'telephony', 'email', 'chat'])
                ->map(function (string $key) use ($statuses): array {
                    $status = $statuses[$key];

                    return [
                        $status['label'],
                        $status['provider'] ?: '—',
                        $status['configured'] ? 'Oui' : 'Non',
                        $status['operational'] ? 'Oui' : 'Non',
                        $status['message'],
                    ];
                })
                ->all(),
        );

        $this->newLine();
        $this->line('Webhook Meta : '.rtrim((string) config('app.url'), '/').'/api/webhooks/whatsapp');
        $this->line('Connexion de file d’attente : '.config('queue.default'));
        $this->line('Queue Support WhatsApp : '.config('support_ai.queue', 'default'));
        $this->line('Numéro WhatsApp public configuré : '.(ovanie_whatsapp_number() ? 'Oui' : 'Non'));
        $this->warn('Aucune clé, aucun token et aucun App Secret ne sont affichés par cette commande.');

        return self::SUCCESS;
    }
}
