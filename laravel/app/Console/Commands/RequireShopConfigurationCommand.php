<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RequireShopConfigurationCommand extends Command
{
    protected $signature = 'ovanie:shops-require-configuration
        {--ids= : IDs séparés par des virgules}
        {--file= : Fichier contenant un ID, slug ou nom exact par ligne}
        {--only=both : logistics, payout ou both}
        {--dry-run : Affiche seulement les boutiques concernées}';

    protected $description = 'Marque uniquement la liste historique fournie comme devant confirmer logistique et/ou reversement.';

    public function handle(): int
    {
        $only = (string) $this->option('only');
        if (! in_array($only, ['logistics','payout','both'], true)) {
            $this->error('--only doit valoir logistics, payout ou both.');
            return self::FAILURE;
        }

        $tokens = collect(explode(',', (string) $this->option('ids')))->map(fn ($v) => trim($v))->filter();
        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                $this->error("Fichier introuvable : {$file}");
                return self::FAILURE;
            }
            $tokens = $tokens->merge(collect(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))->map(fn ($v) => trim($v))->filter());
        }

        if ($tokens->isEmpty()) {
            $this->error('Fournissez --ids ou --file.');
            return self::FAILURE;
        }

        $shops = Shop::query()->where(function ($q) use ($tokens) {
            foreach ($tokens->unique() as $token) {
                if (ctype_digit($token)) {
                    $q->orWhere('id', (int) $token);
                } else {
                    $q->orWhere('slug', $token)->orWhere('name', $token);
                }
            }
        })->orderBy('id')->get();

        if ($shops->isEmpty()) {
            $this->warn('Aucune boutique trouvée.');
            return self::SUCCESS;
        }

        $this->table(['ID','Boutique','Logistique confirmée','Reversement confirmé'], $shops->map(fn ($shop) => [
            $shop->id, $shop->name,
            $shop->logistics_confirmed_at ? 'oui' : 'non',
            $shop->payout_configured_at ? 'oui' : 'non',
        ])->all());

        if ($this->option('dry-run')) {
            $this->comment('Simulation uniquement : aucune donnée modifiée.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($shops, $only) {
            foreach ($shops as $shop) {
                $changes = [];
                if (in_array($only, ['logistics','both'], true)) {
                    $changes['logistics_confirmed_at'] = null;
                    $changes['logistics_status'] = Shop::LOGISTICS_INCOMPLETE;
                }
                if (in_array($only, ['payout','both'], true)) {
                    $changes['payout_configured_at'] = null;
                }
                $shop->forceFill($changes)->save();
            }
        });

        $this->info($shops->count().' boutique(s) marquée(s) comme configuration requise.');
        return self::SUCCESS;
    }
}
