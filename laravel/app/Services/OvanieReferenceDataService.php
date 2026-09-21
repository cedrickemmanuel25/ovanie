<?php

namespace App\Services;

use App\Models\AbidjanCommune;
use App\Models\AbidjanQuarter;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Source Laravel unique des référentiels OVANIE.
 *
 * Étape 3 : les contrôleurs existants peuvent conserver leur forme de réponse,
 * mais ils ne doivent plus reconstruire localement les mêmes listes métier.
 *
 * L'API unifiée publique sera ajoutée à l'étape 4. Ce service est donc la
 * couche interne commune sur laquelle les endpoints existants et futurs
 * doivent s'appuyer.
 */
class OvanieReferenceDataService
{
    public function __construct(private readonly LogisticsTerritoryCoverageService $territoryCoverage)
    {
    }

    public function contractVersion(): string
    {
        return (string) config('ovanie_contract.definition_version', 'unknown');
    }

    public function registryVersion(): string
    {
        return (string) config('ovanie_reference_data.registry_version', 'unknown');
    }

    /**
     * Retourne la map code => libellé d'un enum canonique.
     */
    public function enum(string $enum): array
    {
        $values = config("ovanie_contract.enums.{$enum}");

        if (! is_array($values)) {
            throw new LogicException("Référentiel OVANIE inconnu : {$enum}");
        }

        return $values;
    }

    /**
     * Résout un alias public du registre, par ex. product_units.
     */
    public function reference(string $name): array
    {
        $enum = config("ovanie_reference_data.contract_references.{$name}");

        if (! is_string($enum) || $enum === '') {
            throw new LogicException("Alias de référentiel OVANIE inconnu : {$name}");
        }

        return $this->enum($enum);
    }

    public function codes(string $enumOrReference): array
    {
        $map = config("ovanie_reference_data.contract_references.{$enumOrReference}") !== null
            ? $this->reference($enumOrReference)
            : $this->enum($enumOrReference);

        return array_values(array_map('strval', array_keys($map)));
    }

    /**
     * Format canonique des options : [{code, label}, ...].
     */
    public function options(string $enumOrReference): array
    {
        $map = config("ovanie_reference_data.contract_references.{$enumOrReference}") !== null
            ? $this->reference($enumOrReference)
            : $this->enum($enumOrReference);

        $options = [];
        foreach ($map as $code => $label) {
            $options[] = [
                'code' => (string) $code,
                'label' => (string) $label,
            ];
        }

        return $options;
    }

    /**
     * Format de compatibilité utilisé par certains endpoints existants :
     * [{value, label}, ...].
     */
    public function valueOptions(string $enumOrReference): array
    {
        return array_map(
            static fn (array $item): array => [
                'value' => $item['code'],
                'label' => $item['label'],
            ],
            $this->options($enumOrReference)
        );
    }

    public function contractReferences(): array
    {
        $result = [];

        foreach ((array) config('ovanie_reference_data.contract_references', []) as $alias => $enum) {
            $result[$alias] = $this->options((string) $enum);
        }

        return $result;
    }

    /**
     * Catégories actives, à plat. Conserve la forme déjà renvoyée par
     * VendorMobileController::meta().
     */
    public function categoriesFlat(): array
    {
        if (! $this->hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * Sous-catégories actives, à plat, avec leur parent.
     */
    public function subcategoriesFlat(): array
    {
        if (! $this->hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereNotNull('parent_id')
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->id,
                'parent_id' => (int) $category->parent_id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * Catégories principales actives.
     */
    public function shopCategories(): array
    {
        if (! $this->hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->roots()
            ->active()
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * Arbre catégories/sous-catégories actif.
     */
    public function productCategories(): array
    {
        if (! $this->hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->roots()
            ->active()
            ->ordered()
            ->with(['children' => fn ($query) => $query->active()->ordered()])
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'children' => $category->children
                    ->map(static fn (Category $child): array => [
                        'id' => (int) $child->id,
                        'name' => (string) $child->name,
                        'slug' => (string) $child->slug,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Communes actives, avec quartiers actifs en option.
     */
    public function communes(bool $withQuarters = false): array
    {
        if (! $this->hasTable('abidjan_communes')) {
            return [];
        }

        $query = AbidjanCommune::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($withQuarters && $this->hasTable('abidjan_quarters')) {
            $query->with(['quarters' => fn ($quarters) => $quarters
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('name')]);
        }

        return $query->get()->map(function (AbidjanCommune $commune) use ($withQuarters): array {
            $item = [
                'id' => (int) $commune->id,
                'name' => (string) $commune->name,
            ];

            if ($withQuarters) {
                $item['quarters'] = $commune->relationLoaded('quarters')
                    ? $commune->quarters->map(static fn ($quarter): array => [
                        'id' => (int) $quarter->id,
                        'name' => (string) $quarter->name,
                    ])->values()->all()
                    : [];
            }

            return $item;
        })->values()->all();
    }

    public function quarters(?int $communeId = null): array
    {
        if (! $this->hasTable('abidjan_quarters')) {
            return [];
        }

        return AbidjanQuarter::query()
            ->where('is_active', true)
            ->when($communeId !== null && $communeId > 0, fn (Builder $query) => $query->where('commune_id', $communeId))
            ->orderBy('priority')
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'commune_id', 'name'])
            ->map(static fn (AbidjanQuarter $quarter): array => [
                'id' => (int) $quarter->id,
                'commune_id' => (int) $quarter->commune_id,
                'name' => (string) $quarter->name,
            ])
            ->values()
            ->all();
    }

    public function regions(): array
    {
        return $this->distinctShopLocationValues('region', 'Abidjan');
    }

    public function cities(): array
    {
        return $this->distinctShopLocationValues('city', 'Abidjan');
    }

    public function deliveryTerritory(): array
    {
        return $this->territoryCoverage->activeZonesForClient();
    }

    public function identityCountries(): array
    {
        return $this->compatibilityOptions('identity_countries', 'value');
    }

    public function processingTimeCodes(): array
    {
        return array_values(array_map(
            'strval',
            (array) config('ovanie_reference_data.compatibility.processing_times', [])
        ));
    }

    public function payoutMethods(): array
    {
        return $this->compatibilityOptions('payout_methods', 'value');
    }

    /**
     * Opérateurs présentés dans le checkout, dans l'ordre historique de l'UI.
     * Les quatre opérateurs Mobile Money proviennent du contrat canonique ;
     * "card" est une option de paiement en ligne, pas un opérateur Mobile Money.
     */
    public function checkoutOperators(): array
    {
        $mobileMoney = $this->reference('mobile_money_operators');
        $extraLabels = (array) config('ovanie_reference_data.compatibility.extra_labels', []);
        $order = (array) config('ovanie_reference_data.compatibility.checkout_operator_order', []);
        $result = [];

        foreach ($order as $code) {
            $code = (string) $code;
            $label = $mobileMoney[$code] ?? $extraLabels[$code] ?? null;
            if ($label === null) {
                continue;
            }
            $result[] = ['code' => $code, 'label' => (string) $label];
        }

        return $result;
    }

    /**
     * Métadonnées publiques de l'API commune de référentiels.
     *
     * Étape 4 : l'API est exposée en lecture seule. La compatibilité APK
     * (versions minimales/maximales, blocage d'une ancienne application, etc.)
     * reste volontairement hors de cette étape.
     */
    public function apiMeta(): array
    {
        return [
            'service' => 'OVANIE Reference Data',
            'endpoint_version' => (string) config('ovanie_reference_data.api.endpoint_version', 'v1'),
            'schema_version' => $this->contractVersion(),
            'registry_version' => $this->registryVersion(),
            'source' => 'laravel',
            'read_only' => true,
            'compatibility_route' => '/api/' . ltrim((string) config('ovanie_schema_compatibility.route', 'mobile/v1/reference-data/compatibility'), '/'),
            'available_keys' => array_values(array_unique(array_merge(
                array_keys((array) config('ovanie_reference_data.contract_references', [])),
                [
                    'categories',
                    'categories_flat',
                    'subcategories',
                    'shop_categories',
                    'product_categories',
                    'regions',
                    'cities',
                    'communes',
                    'quarters',
                    'delivery_territory',
                    'identity_countries',
                    'processing_times',
                    'payout_methods',
                    'checkout_operators',
                ]
            ))),
        ];
    }

    /**
     * Payload public commun consommable par les applications OVANIE.
     *
     * Toutes les listes contractuelles utilisent la forme stable
     * [{code, label}, ...]. Les référentiels issus de la base conservent leurs
     * identifiants afin que les formulaires puissent enregistrer les vraies
     * clés Laravel plutôt que des slugs codés dans Flutter.
     */
    public function apiPayload(): array
    {
        $data = $this->contractReferences();

        $data['categories'] = $this->productCategories();
        $data['categories_flat'] = $this->categoriesFlat();
        $data['subcategories'] = $this->subcategoriesFlat();
        $data['shop_categories'] = $this->shopCategories();
        $data['product_categories'] = $this->productCategories();
        $data['regions'] = $this->regions();
        $data['cities'] = $this->cities();
        $data['communes'] = $this->communes(true);
        $data['quarters'] = $this->quarters();
        $data['delivery_territory'] = $this->deliveryTerritory();
        $data['identity_countries'] = $this->identityCountries();
        $data['processing_times'] = $this->processingTimeCodes();
        $data['payout_methods'] = $this->payoutMethods();
        $data['checkout_operators'] = $this->checkoutOperators();

        return [
            'ok' => true,
            'meta' => array_merge($this->apiMeta(), [
                'generated_at' => now()->toIso8601String(),
            ]),
            'data' => $data,
        ];
    }

    /**
     * Snapshot interne utilisé par le validateur de l'étape 3 et l'API
     * unifiée exposée à partir de l'étape 4.
     */
    public function staticSnapshot(): array
    {
        return [
            'contract_version' => $this->contractVersion(),
            'registry_version' => $this->registryVersion(),
            'references' => $this->contractReferences(),
            'compatibility' => [
                'identity_countries' => $this->identityCountries(),
                'processing_times' => $this->processingTimeCodes(),
                'payout_methods' => $this->payoutMethods(),
                'checkout_operators' => $this->checkoutOperators(),
            ],
        ];
    }

    private function compatibilityOptions(string $key, string $codeKey = 'code'): array
    {
        $map = (array) config("ovanie_reference_data.compatibility.{$key}", []);
        $result = [];

        foreach ($map as $code => $label) {
            $result[] = [
                $codeKey => (string) $code,
                'label' => (string) $label,
            ];
        }

        return $result;
    }

    private function distinctShopLocationValues(string $column, string $default): array
    {
        if (! $this->hasTable('shops') || ! Schema::hasColumn('shops', $column)) {
            return [$default];
        }

        $values = Shop::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if (! in_array($default, $values, true)) {
            array_unshift($values, $default);
        }

        return array_values(array_unique($values));
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
