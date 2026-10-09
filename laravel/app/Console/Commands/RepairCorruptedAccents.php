<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Répare les textes dont les lettres accentuées ont été remplacées par « ?? »
 * lors d'un import de base avec un mauvais encodage (ex. « r??glable »).
 *
 * Deux niveaux de réparation, uniquement quand la correspondance est unique :
 *  1. texte entier retrouvé dans une source propre (référentiel des localités
 *     d'Abidjan : config + CSV) ;
 *  2. mot par mot, à partir des mots correctement accentués déjà présents dans
 *     la base et les sources (« r??glable » → « réglable »).
 * Les symboles courants sont aussi rétablis (60 ?? 60 → 60 × 60, ??? → —).
 */
class RepairCorruptedAccents extends Command
{
    protected $signature = 'ovanie:repair-accents {--dry-run : Afficher sans modifier la base}';

    protected $description = 'Répare les accents perdus (« ?? ») dans les textes de la base.';

    /** @var array<string, array<int, string>> tables => colonnes à réparer */
    private const TARGETS = [
        'abidjan_communes' => ['name', 'aliases'],
        'abidjan_quarters' => ['name', 'aliases', 'search_terms'],
        'abidjan_localities' => ['commune', 'quartier', 'sous_quartier', 'cite', 'village', 'carrefour', 'alias_google_maps', 'notes'],
        'abidjan_landmarks' => ['name', 'address'],
        'delivery_zones' => ['name'],
        'delivery_services' => ['description'],
        'logistics_territory_zones' => ['covered_communes'],
        'seller_delivery_zones' => ['commune', 'district'],
        'product_delivery_zones' => ['commune', 'district'],
        'shops' => ['name', 'description', 'main_category', 'commune', 'district', 'landmark', 'address', 'mm_holder'],
        'products' => ['name', 'short_description', 'description', 'technical_details', 'usage_area', 'packaging', 'material_grade', 'unit_label', 'brand'],
        'orders' => ['delivery_quartier', 'delivery_address'],
        'order_items' => ['commission_status'],
        'users' => ['name', 'first_name', 'last_name'],
        'geocode_cache' => ['display_name', 'query'],
    ];

    /** @var array<string, string> texte « squelette » (accents → ?) => texte propre, pour les phrases entières */
    private array $phrases = [];

    /** @var array<string, array<string, true>> squelette de mot => mots propres possibles */
    private array $words = [];

    public function handle(): int
    {
        $this->collectCleanSources();
        $this->info(sprintf('Dictionnaire : %d textes de référence, %d mots accentués.', count($this->phrases), count($this->words)));

        $dry = (bool) $this->option('dry-run');
        $totals = ['fixed' => 0, 'partial' => 0, 'left' => 0];

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $rows = DB::table($table)->where($column, 'like', '%??%')->get(['id', $column]);
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};
                    $repaired = $this->repair($original);
                    if ($repaired === $original) {
                        $totals['left']++;
                        continue;
                    }

                    str_contains($repaired, '??') ? $totals['partial']++ : $totals['fixed']++;
                    if ($dry) {
                        $this->line("{$table}.{$column}#{$row->id} : {$original}  →  {$repaired}");
                    } else {
                        DB::table($table)->where('id', $row->id)->update([$column => $repaired]);
                    }
                }
            }
        }

        $this->info(sprintf(
            '%s : %d textes entièrement réparés, %d partiellement, %d non réparables sans source.',
            $dry ? 'Simulation' : 'Terminé',
            $totals['fixed'],
            $totals['partial'],
            $totals['left'],
        ));

        return self::SUCCESS;
    }

    private function repair(string $text): string
    {
        // 1. Texte entier connu dans une source propre.
        $skeleton = $this->skeleton($text);
        if (isset($this->phrases[$skeleton])) {
            return $this->phrases[$skeleton];
        }

        // 2. Symboles typographiques.
        $text = preg_replace('/(\d)\s*\?\?\s*(\d)/u', '$1 × $2', $text) ?? $text;
        $text = preg_replace('/\s\?\?\?\s/u', ' — ', $text) ?? $text;
        $text = preg_replace('/(\d)\s?\?\?C\b/u', '$1 °C', $text) ?? $text;
        $text = preg_replace('/(\d)\?\?me\b/u', '$1ème', $text) ?? $text;
        // Élisions : l’, d’, s’, n’, qu’… (l’apostrophe typographique fait 3 octets).
        $text = preg_replace('/\b([LlDdSsNnJjMmTtCc]|[Qq]u|[Jj]usqu|[Ll]orsqu|[Pp]uisqu)\?\?\?(?=[\p{L}?])/u', '$1’', $text) ?? $text;

        // 3. Mot par mot.
        return preg_replace_callback('/[\p{L}\p{N}’\'?-]*\?\?[\p{L}\p{N}’\'?-]*/u', function (array $match) {
            $token = $match[0];
            $candidates = $this->words[$this->skeleton(mb_strtolower($token))] ?? [];
            if (count($candidates) !== 1) {
                // Préposition « à » isolée : seul cas non ambigu fréquent.
                return $token === '??' ? 'à' : $token;
            }

            $word = array_key_first($candidates);

            return $this->matchCase($token, $word);
        }, $text) ?? $text;
    }

    /**
     * Forme « squelette » : chaque caractère non ASCII devient « ?? » (2 octets
     * perdus) ou « ??? » (3 octets), exactement comme dans les données abîmées.
     */
    private function skeleton(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $char) {
            $bytes = strlen($char);
            $out .= $bytes === 1 ? $char : str_repeat('?', $bytes);
        }

        return $out;
    }

    private function matchCase(string $model, string $word): string
    {
        $first = mb_substr($model, 0, 1);
        if ($first !== '?' && $first === mb_strtoupper($first) && $first !== mb_strtolower($first)) {
            return mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
        }

        return $word;
    }

    private function collectCleanSources(): void
    {
        $texts = [];

        // Référentiel propre : config + CSV.
        $config = (array) config('abidjan_localities', []);
        array_walk_recursive($config, function ($value) use (&$texts) {
            if (is_string($value)) {
                $texts[] = $value;
            }
        });
        foreach (glob(database_path('data/*.csv')) ?: [] as $csv) {
            $handle = fopen($csv, 'r');
            while ($handle && ($line = fgetcsv($handle)) !== false) {
                foreach ($line as $cell) {
                    $texts[] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell);
                }
            }
            $handle && fclose($handle);
        }

        // Textes corrects déjà en base (sans « ?? ») : dictionnaire de mots.
        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::table($table)->whereNotNull($column)->where($column, 'not like', '%??%')
                    ->where($column, 'regexp', '[^ -~]')->limit(5000)->pluck($column)
                    ->each(function ($value) use (&$texts) {
                        $texts[] = (string) $value;
                    });
            }
        }
        foreach (['categories' => ['name', 'description'], 'abidjan_communes' => ['name']] as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        DB::table($table)->whereNotNull($column)->where($column, 'not like', '%??%')->pluck($column)
                            ->each(function ($value) use (&$texts) {
                                $texts[] = (string) $value;
                            });
                    }
                }
            }
        }
        // Vocabulaire courant du BTP et du commerce.
        $texts[] = 'à où déjà être règle réglable résistant résistante sécurité équipement équipements électrique électriques électricité céramique cérame grès carré carrée évacuation étanche étanchéité intérieur extérieur extérieure intérieure qualité supérieure lumière légère léger poignée pièce pièces fenêtre fenêtres système systèmes matériaux matériau durée entretien protégé protégée idéal idéale utilisé utilisée utilisation fabriqué fabriquée conçu conçue renforcé renforcée traité traitée métallique métal acier galvanisé galvanisée béton bâtiment bâtiments câble câbles réservoir répartition géotextile hydrofuge thermique acoustique décoratif décorative façade façades épaisseur hauteur longueur largeur diamètre numéro prêt prête élevé élevée très même crème dépôt modèle modèles génie fiabilité fréquent élément éléments complète complet précis précise pré réparation rénovation installé installée installation étagère spécialisé spécialisée haute dégâts tôle tôles toiture charpente plâtre crépi finition mate brillante polyéthylène polypropylène PVC stabilisé stabilisée conformité conforme norme normes livré livrée garantie coût économique écologique énergie énergétique solaire batterie lithium régulateur onduleur panneau panneaux étanches câblage ménage bâche échafaudage chantier entrepôt dépannage expédition délai délais Côte d’Ivoire Abidjan Cocody Marcory Yopougon Attécoubé Adjamé Bingerville Treichville Plateau Port-Bouët Songon Anyama Abobo Koumassi visibilité concassé bélier lavé lavée résidence résidentiel résidentielle résidentiels résidentielles destiné destinée destinés destinées matière matières maçonnerie adapté adaptée adaptés adaptées protéger protégés humidité photovoltaïque photovoltaïques boîte boîtes boîtier tête têtes confirmé confirmée confirmés confirmées épaisseur intérieurs intérieures bétons mécanisme mécanismes réfléchissante réfléchissantes réellement pôle pôles énergie intégré intégrée intégrés déclenchement améliore améliorer vitrifié vitrifiée vérifié vérifiés vérifiée vérifiées séparément propreté préparation préparé préparée préparés préparées précisé précisée précisés précisées granulométrie entrepôt entrepôts contrôlé contrôlée contrôlés contrôlées capacité capacités aménagement télécommande sélectionner réseau réseaux quantité quantités unité unités évacuation annoncé annoncée trempé trempée tolérance séparation séchage raccordé raccordée réglage réelle réel réduire réaliser réalisation réalisé réalisée qualifié qualifiée présence préciser poignées accès fixé fixée fixés fixées posé posée mètre mètres kilomètre degré degrés armé armée pavé pavés sablé sablée perforé perforée soudé soudée âme générale général génère générer différent différente différents différentes spécifique spécifiques rapidité facilité durabilité stabilité sécurisé sécurisée ventilé ventilée mélangé mélangée hydraulique hydrauliques réglementation électroménager éclairage éclairages ampère ampères différentiel câblé isolé isolée aéré aérée étendu étendue réparer préférable conseillé conseillée recommandé recommandée agréé agréée côté côtés entièrement légèrement également particulièrement spécialement immédiatement régulièrement complètement sèche sèches présente présentes présenté présentée accompagné accompagnée équipé équipée équipés équipées adhérence décoration décorations tuyauterie mêmes électricien maçon carreleur ébéniste créer création créé créée';

        foreach ($texts as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            if (preg_match('/[^\x00-\x7F]/', $text)) {
                $this->phrases[$this->skeleton($text)] ??= $text;
            }
            foreach (preg_split('/[^\p{L}\p{N}’\'-]+/u', $text) ?: [] as $word) {
                if ($word === '' || ! preg_match('/[^\x00-\x7F]/', $word)) {
                    continue;
                }
                $lower = mb_strtolower($word);
                $this->words[$this->skeleton($lower)][$lower] = true;
            }
        }
    }
}
