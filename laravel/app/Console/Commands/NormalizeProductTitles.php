<?php

namespace App\Console\Commands;

use App\Support\ProductTitleNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeProductTitles extends Command
{
    protected $signature = 'ovanie:normalize-product-titles {--apply : Enregistre les corrections (sans cette option, la commande ne fait que les lister)}';

    protected $description = 'Normalise les titres des produits existants (majuscules, dimensions, fautes connues).';

    public function handle(): int
    {
        $changes = DB::table('products')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'before' => (string) $p->name, 'after' => ProductTitleNormalizer::normalize((string) $p->name)])
            ->filter(fn ($row) => $row['before'] !== $row['after'])
            ->values();

        if ($changes->isEmpty()) {
            $this->info('Aucun titre à corriger.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Avant', 'Après'], $changes->map(fn ($r) => [$r['id'], $r['before'], $r['after']])->all());

        if (! $this->option('apply')) {
            $this->warn($changes->count() . ' titre(s) à corriger. Relancez avec --apply pour enregistrer.');

            return self::SUCCESS;
        }

        // Le slug (adresse de la fiche) n'est pas modifié : les liens existants restent valides.
        $changes->each(fn ($r) => DB::table('products')->where('id', $r['id'])->update(['name' => $r['after']]));
        $this->info($changes->count() . ' titre(s) corrigé(s).');

        return self::SUCCESS;
    }
}
