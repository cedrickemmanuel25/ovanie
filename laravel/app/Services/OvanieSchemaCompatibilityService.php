<?php

namespace App\Services;

use InvalidArgumentException;

class OvanieSchemaCompatibilityService
{
    public function currentSchemaVersion(): string
    {
        return (string) config('ovanie_contract.definition_version', 'unknown');
    }

    public function compatibilityVersion(): string
    {
        return (string) config('ovanie_schema_compatibility.compatibility_version', 'unknown');
    }

    public function apps(): array
    {
        return (array) config('ovanie_schema_compatibility.apps', []);
    }

    /**
     * Vérifie si une application donnée peut interpréter le schéma Laravel
     * courant. Le schéma annoncé par l'APK est également retourné pour rendre
     * le diagnostic explicite.
     */
    public function evaluate(string $app, string $clientSchemaVersion): array
    {
        $app = trim(strtolower($app));
        $clientSchemaVersion = trim($clientSchemaVersion);
        $definition = $this->apps()[$app] ?? null;

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Application OVANIE inconnue : {$app}");
        }

        $serverSchema = $this->currentSchemaVersion();
        $min = (string) ($definition['supported_schema_min'] ?? $serverSchema);
        $max = (string) ($definition['supported_schema_max'] ?? $serverSchema);

        $clientDeclaresSupportedSchema = $clientSchemaVersion !== ''
            && version_compare($clientSchemaVersion, $min, '>=')
            && version_compare($clientSchemaVersion, $max, '<=');

        $serverWithinAppRange = version_compare($serverSchema, $min, '>=')
            && version_compare($serverSchema, $max, '<=');

        $sameContractGeneration = $clientSchemaVersion !== ''
            && version_compare($clientSchemaVersion, $serverSchema, '==');

        $compatible = $clientDeclaresSupportedSchema
            && $serverWithinAppRange
            && $sameContractGeneration;

        $reason = 'compatible';
        if ($clientSchemaVersion === '') {
            $reason = 'missing_client_schema';
        } elseif (! $clientDeclaresSupportedSchema) {
            $reason = 'client_schema_outside_supported_range';
        } elseif (! $serverWithinAppRange) {
            $reason = 'server_schema_outside_app_range';
        } elseif (! $sameContractGeneration) {
            $reason = 'schema_version_mismatch';
        }

        return [
            'compatible' => $compatible,
            'status' => $compatible ? 'compatible' : 'incompatible',
            'reason' => $reason,
            'app' => $app,
            'app_label' => (string) ($definition['label'] ?? $app),
            'client_schema_version' => $clientSchemaVersion,
            'server_schema_version' => $serverSchema,
            'supported_schema_min' => $min,
            'supported_schema_max' => $max,
            'compatibility_version' => $this->compatibilityVersion(),
        ];
    }

    public function publicMeta(): array
    {
        return [
            'compatibility_version' => $this->compatibilityVersion(),
            'schema_version' => $this->currentSchemaVersion(),
            'route' => '/api/' . ltrim((string) config('ovanie_schema_compatibility.route'), '/'),
            'apps' => collect($this->apps())->map(static fn (array $definition, string $code): array => [
                'code' => $code,
                'label' => (string) ($definition['label'] ?? $code),
                'supported_schema_min' => (string) ($definition['supported_schema_min'] ?? ''),
                'supported_schema_max' => (string) ($definition['supported_schema_max'] ?? ''),
            ])->values()->all(),
        ];
    }
}
