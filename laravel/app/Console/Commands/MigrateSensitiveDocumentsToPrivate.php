<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigrateSensitiveDocumentsToPrivate extends Command
{
    protected $signature = 'documents:migrate-private {--dry-run : Analyse sans copie, suppression ni écriture en base}';

    protected $description = 'Migre les justificatifs historiques du disque public vers le stockage privé';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $totals = ['references' => 0, 'eligible' => 0, 'missing' => 0, 'private' => 0, 'migrated' => 0, 'errors' => 0];

        foreach (config('private_documents.sources', []) as $source) {
            $stats = $this->processSource($source, $dryRun);
            $rows[] = [$source['category'], ...array_values($stats)];
            foreach ($totals as $key => $unused) {
                $totals[$key] += $stats[$key];
            }
        }

        $this->table(['Catégorie', 'Références', 'Éligibles', 'Manquants', 'Déjà privés', 'Migrés', 'Erreurs'], $rows);
        $this->line(sprintf(
            '%s — références: %d; éligibles: %d; manquants: %d; déjà privés: %d; migrés: %d; erreurs: %d.',
            $dryRun ? 'DRY-RUN' : 'MIGRATION', ...array_values($totals)
        ));

        return $totals['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processSource(array $source, bool $dryRun): array
    {
        $stats = ['references' => 0, 'eligible' => 0, 'missing' => 0, 'private' => 0, 'migrated' => 0, 'errors' => 0];
        $table = $source['table'];

        if (! Schema::hasTable($table)) {
            return $stats;
        }

        foreach ($source['columns'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->select(['id', $column])->whereNotNull($column)->where($column, '<>', '')
                ->orderBy('id')->chunkById(100, function ($records) use ($source, $table, $column, $dryRun, &$stats): void {
                    foreach ($records as $record) {
                        $stats['references']++;
                        $path = $this->normalise((string) $record->{$column});
                        if ($path === '' || Str::startsWith($path, trim(config('private_documents.prefix'), '/').'/')) {
                            $stats['private']++;
                            continue;
                        }
                        if (! Storage::disk('public')->exists($path)) {
                            $stats['missing']++;
                            continue;
                        }

                        $stats['eligible']++;
                        if ($dryRun) {
                            continue;
                        }

                        try {
                            $destination = $this->destination($source['category'], $table, (int) $record->id, $column, $path);
                            $this->copyAndVerify($path, $destination);
                            DB::transaction(function () use ($table, $column, $record, $path, $destination): void {
                                $updated = DB::table($table)->where('id', $record->id)->where($column, $record->{$column})->update([$column => $destination]);
                                if ($updated !== 1) {
                                    throw new \RuntimeException('La référence a changé pendant la migration.');
                                }
                            });
                            Storage::disk('public')->delete($path);
                            $stats['migrated']++;
                        } catch (Throwable $exception) {
                            $stats['errors']++;
                            Log::error('Échec de migration d’un document sensible.', [
                                'category' => $source['category'], 'table' => $table, 'record_id' => $record->id,
                                'column' => $column, 'exception' => $exception::class,
                            ]);
                        }
                    }
                });
        }

        return $stats;
    }

    private function normalise(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^(?:https?:)?//[^/]+/storage/#i', '', $path) ?: $path;
        return ltrim(Str::after($path, 'public/storage/'), '/');
    }

    private function destination(string $category, string $table, int $id, string $column, string $source): string
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $token = hash_hmac('sha256', "$table:$id:$column:$source", (string) config('app.key'));
        return trim(config('private_documents.prefix'), '/')."/$category/$token".($extension ? ".$extension" : '');
    }

    private function copyAndVerify(string $source, string $destination): void
    {
        if (! Storage::disk('local')->exists($destination)) {
            $stream = Storage::disk('public')->readStream($source);
            if (! is_resource($stream) || ! Storage::disk('local')->writeStream($destination, $stream)) {
                throw new \RuntimeException('Copie privée impossible.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (Storage::disk('public')->size($source) !== Storage::disk('local')->size($destination)
            || hash_file('sha256', Storage::disk('public')->path($source)) !== hash_file('sha256', Storage::disk('local')->path($destination))) {
            Storage::disk('local')->delete($destination);
            throw new \RuntimeException('La vérification de copie a échoué.');
        }
    }
}
