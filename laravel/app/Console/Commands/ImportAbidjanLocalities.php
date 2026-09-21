<?php

namespace App\Console\Commands;

use App\Models\AbidjanCommune;
use App\Models\AbidjanLocality;
use App\Models\AbidjanQuarter;
use App\Services\Geo\AbidjanLocalityRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAbidjanLocalities extends Command
{
    protected $signature = 'geo:import-abidjan-localities
        {path? : Fichier CSV ou JSON à importer}
        {--file= : Chemin du fichier CSV ou JSON}
        {--source= : Source à appliquer aux données importées}
        {--verified : Marquer les lignes comme vérifiées}
        {--replace-source : Supprimer auparavant les lignes provenant de la même source}';

    protected $aliases = ['localities:import-abidjan'];

    protected $description = 'Importe le référentiel communes, quartiers, sous-quartiers, cités, villages, carrefours et alias d’Abidjan.';

    public function handle(AbidjanLocalityRegistry $registry): int
    {
        if (! Schema::hasTable('abidjan_localities')) {
            $this->error('La table abidjan_localities est absente. Exécutez d’abord : php artisan migrate --force');
            return self::FAILURE;
        }

        $path = trim((string) ($this->option('file') ?: $this->argument('path')));
        if ($path === '') {
            $path = 'database/data/abidjan_localities_database.csv';
        }
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Le fichier est introuvable ou illisible : '.$path);
            return self::FAILURE;
        }

        try {
            $rows = $this->readRows($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $sourceOption = trim((string) $this->option('source'));
        if ($this->option('replace-source') && $sourceOption === '') {
            $this->error('L’option --replace-source exige une valeur explicite pour --source.');
            return self::FAILURE;
        }
        if ($this->option('replace-source')) {
            AbidjanLocality::query()->where('source', $sourceOption)->delete();
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $registry, $sourceOption, $path, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $communeName = $registry->canonicalCommune($row['commune'] ?? null);
                [$column, $type, $name] = $this->localityIdentity($row);

                if (! $communeName || $name === '') {
                    $skipped++;
                    continue;
                }

                $commune = AbidjanCommune::query()->where('name', $communeName)->first();
                if (! $commune) {
                    $skipped++;
                    continue;
                }

                $aliases = $this->aliases(
                    $row['alias_google_maps']
                        ?? $row['aliases']
                        ?? $row['alias']
                        ?? null
                );
                $source = $sourceOption !== ''
                    ? $sourceOption
                    : (trim((string) ($row['source'] ?? '')) ?: 'manual_import');
                $slug = Str::slug($name);

                $quarter = AbidjanQuarter::query()->firstOrNew([
                    'commune_id' => $commune->id,
                    'slug' => $slug,
                ]);
                $quarter->fill([
                    'name' => $name,
                    'aliases' => $aliases,
                    'type' => $type,
                    'latitude' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
                    'longitude' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
                    'source' => $source,
                    'source_reference' => $row['source_reference'] ?? basename($path),
                    'is_verified' => $this->option('verified') || filter_var($row['verified'] ?? false, FILTER_VALIDATE_BOOL),
                    'is_active' => true,
                ]);
                $quarter->save();

                $locality = AbidjanLocality::query()->firstOrNew([
                    'commune_id' => $commune->id,
                    'type' => $type,
                    'slug' => $slug,
                ]);
                $exists = $locality->exists;

                $locationColumns = [
                    'quartier' => null,
                    'sous_quartier' => null,
                    'cite' => null,
                    'village' => null,
                    'carrefour' => null,
                ];
                $locationColumns[$column] = $name;

                $locality->fill([
                    'quarter_id' => $quarter->id,
                    'commune' => $commune->name,
                    ...$locationColumns,
                    'alias_google_maps' => $aliases,
                    'name' => $name,
                    'search_text' => $this->normalize(implode(' ', [$commune->name, $name, ...$aliases])),
                    'latitude' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
                    'longitude' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
                    'source' => $source,
                    'source_reference' => $row['source_reference'] ?? basename($path),
                    'notes' => trim((string) ($row['remarque'] ?? $row['notes'] ?? '')) ?: null,
                    'is_verified' => $this->option('verified') || filter_var($row['verified'] ?? false, FILTER_VALIDATE_BOOL),
                    'is_active' => true,
                ]);
                $locality->save();

                $exists ? $updated++ : $created++;
            }
        });

        $registry->forgetCache();

        $this->info("Import terminé : {$created} ajoutée(s), {$updated} mise(s) à jour, {$skipped} ignorée(s).");
        return self::SUCCESS;
    }

    private function readRows(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Le JSON est invalide.');
            }

            return array_is_list($decoded) ? $decoded : (array) ($decoded['localities'] ?? []);
        }

        if ($extension !== 'csv') {
            throw new RuntimeException('Formats acceptés : CSV ou JSON.');
        }

        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('Impossible d’ouvrir le fichier CSV.');
        }

        $headers = fgetcsv($handle, 0, ',');
        if (! is_array($headers)) {
            fclose($handle);
            return [];
        }

        $headers = array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? (string) $header;
            return Str::snake(Str::ascii(trim($header)));
        }, $headers);

        $rows = [];
        while (($values = fgetcsv($handle, 0, ',')) !== false) {
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    private function localityIdentity(array $row): array
    {
        foreach ([
            'quartier' => 'quartier',
            'sous_quartier' => 'sous_quartier',
            'cite' => 'cite',
            'village' => 'village',
            'carrefour' => 'carrefour',
        ] as $column => $type) {
            $name = trim((string) ($row[$column] ?? ''));
            if ($name !== '') {
                return [$column, $type, $name];
            }
        }

        // Compatibilité avec les anciens fichiers : quartier + type + nom.
        $legacyName = trim((string) ($row['nom_de_la_localite'] ?? $row['name'] ?? $row['quarter'] ?? ''));
        $legacyType = $this->normalizeType((string) ($row['type'] ?? $row['type_source'] ?? 'quartier'));

        return [$legacyType, $legacyType, $legacyName];
    }

    private function normalizeType(string $value): string
    {
        $value = $this->normalize($value);

        return match (true) {
            str_contains($value, 'carrefour') => 'carrefour',
            str_contains($value, 'village') => 'village',
            str_contains($value, 'cite') => 'cite',
            str_contains($value, 'sous quartier'), str_contains($value, 'secteur') => 'sous_quartier',
            default => 'quartier',
        };
    }

    private function aliases(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('trim', $value))));
        }

        return array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[|;,]+/u', (string) $value) ?: []
        ))));
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
