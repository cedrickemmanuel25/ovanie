<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abidjan_quarters')) {
            return;
        }

        $missingParent = ! Schema::hasColumn('abidjan_quarters', 'parent_id');
        $missingNormalized = ! Schema::hasColumn('abidjan_quarters', 'normalized_name');
        $missingSearchTerms = ! Schema::hasColumn('abidjan_quarters', 'search_terms');
        $missingSourceMetadata = ! Schema::hasColumn('abidjan_quarters', 'source_metadata');
        $missingPriority = ! Schema::hasColumn('abidjan_quarters', 'priority');

        Schema::table('abidjan_quarters', function (Blueprint $table) use (
            $missingParent,
            $missingNormalized,
            $missingSearchTerms,
            $missingSourceMetadata,
            $missingPriority
        ) {
            if ($missingParent) {
                $table->foreignId('parent_id')->nullable()->after('commune_id')
                    ->constrained('abidjan_quarters')->nullOnDelete();
            }
            if ($missingNormalized) {
                $table->string('normalized_name', 190)->nullable()->after('slug')->index();
            }
            if ($missingSearchTerms) {
                $table->json('search_terms')->nullable()->after('aliases');
            }
            if ($missingSourceMetadata) {
                $table->json('source_metadata')->nullable()->after('source_reference');
            }
            if ($missingPriority) {
                $table->unsignedSmallInteger('priority')->default(100)->after('source_metadata')->index();
            }
        });

        DB::table('abidjan_quarters')
            ->select(['id', 'name', 'aliases', 'type'])
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $aliases = json_decode((string) ($row->aliases ?? '[]'), true);
                    $aliases = is_array($aliases) ? $aliases : [];
                    $type = $this->inferType((string) $row->name, (string) ($row->type ?? 'quartier'));
                    $terms = $this->searchTerms((string) $row->name, $aliases, $type);

                    DB::table('abidjan_quarters')->where('id', $row->id)->update([
                        'normalized_name' => $this->normalize((string) $row->name),
                        'search_terms' => json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'type' => $type,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('abidjan_quarters')) {
            return;
        }

        Schema::table('abidjan_quarters', function (Blueprint $table) {
            foreach (['priority', 'source_metadata', 'search_terms', 'normalized_name'] as $column) {
                if (Schema::hasColumn('abidjan_quarters', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('abidjan_quarters', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });
    }

    private function searchTerms(string $name, array $aliases, string $type): array
    {
        $terms = [];
        foreach (array_values(array_unique(array_filter([$name, ...$aliases]))) as $value) {
            $normalized = $this->normalize((string) $value);
            if ($normalized === '') {
                continue;
            }

            $terms[] = trim((string) $value);
            $terms[] = $normalized;
            $terms[] = str_replace(' ', '', $normalized);
            $terms[] = preg_replace('/^(quartier|sous quartier|cite|carrefour|village|zone|secteur|lotissement|campement)\s+/u', '', $normalized) ?: $normalized;

            if (preg_match('/\bpk\s*(\d+)\b/u', $normalized, $match)) {
                $terms[] = 'pk '.$match[1];
                $terms[] = 'pk'.$match[1];
            }
        }

        $type = trim($type);
        if ($type !== '' && $type !== 'quartier') {
            $terms[] = str_replace('_', ' ', $type).' '.$this->normalize($name);
        }

        return array_values(array_unique(array_filter($terms)));
    }

    private function inferType(string $name, string $current): string
    {
        $normalized = $this->normalize($name);
        $current = trim($current) ?: 'quartier';

        if ($current !== 'quartier') {
            return $current;
        }

        return match (true) {
            str_starts_with($normalized, 'cite ') => 'cite',
            str_starts_with($normalized, 'carrefour ') => 'carrefour',
            str_starts_with($normalized, 'village '), str_ends_with($normalized, ' village') => 'village',
            str_starts_with($normalized, 'sous quartier ') => 'sous_quartier',
            str_starts_with($normalized, 'zone ') => 'zone',
            str_starts_with($normalized, 'secteur ') => 'secteur',
            str_starts_with($normalized, 'lotissement ') => 'lotissement',
            str_starts_with($normalized, 'campement ') => 'campement',
            default => 'quartier',
        };
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
};
