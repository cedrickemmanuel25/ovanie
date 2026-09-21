<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

/**
 * Centralise l'exclusion des jeux de démonstration historiques de l'espace
 * OVANIE Logistics. Les données restent en base pour ne pas casser un poste
 * local, mais elles ne participent plus aux écrans opérationnels, statistiques
 * ni cartes.
 */
class LogisticsOperationalDataScope
{
    /** @var array<string,bool> */
    private static array $tableExists = [];

    /** @var array<string,bool> */
    private static array $columnExists = [];

    private static function hasTable(string $table): bool
    {
        return self::$tableExists[$table] ??= Schema::hasTable($table);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return self::$columnExists[$key] ??= Schema::hasColumn($table, $column);
    }

    public static function orders(Builder $query): Builder
    {
        if (self::hasColumn('orders', 'order_number')) {
            $query->where(function (Builder $q) {
                $q->whereNull('order_number')
                    ->orWhere(function (Builder $number) {
                        $number->where('order_number', 'not like', 'ORD-DEMO-%')
                            ->where('order_number', 'not like', 'ORD-EXTRA0%')
                            ->where('order_number', 'not like', 'CMD-DEMO-%');
                    });
            });
        }

        $query->whereDoesntHave('client', function (Builder $client) {
            $client->where(function (Builder $email) {
                $email->where('email', 'like', '%@demo.ovanie.%')
                    ->orWhere('email', 'like', '%@demo.ovanie.invalid');
            });
        });

        // Les seeders de démonstration créent également des boutiques et produits
        // reconnaissables. On les exclut sans toucher aux vraies commandes.
        $query->whereDoesntHave('items.product.shop', function (Builder $shop) {
            $shop->where(function (Builder $demo) {
                $demo->where('slug', 'like', 'demo-%')
                    ->orWhere('identity_number', 'like', 'CI-DEMO-%')
                    ->orWhere('identity_number', 'like', 'DEMO-%');
            })->orWhereHas('user', function (Builder $user) {
                $user->where('email', 'like', '%demo%');
            });
        });

        return $query;
    }

    public static function drivers(Builder $query): Builder
    {
        if (self::hasColumn('delivery_drivers', 'email')) {
            $query->where(function (Builder $q) {
                $q->whereNull('email')
                    ->orWhere(function (Builder $email) {
                        $email->where('email', 'not like', '%@demo.ovanie.%')
                            ->where('email', 'not like', '%@demo.ovanie.invalid')
                            ->where('email', 'not like', '%@livreur.ovanie.com');
                    });
            });
        }

        return $query;
    }

    /**
     * Exclut uniquement les véhicules créés par l’ancien seeder de démonstration
     * Flotte. Le couple code + immatriculation est volontairement utilisé afin de
     * ne pas masquer un vrai véhicule qui réutiliserait un code FLT historique.
     */
    public static function fleetVehicles(Builder $query): Builder
    {
        if (! self::hasTable('logistics_fleet_vehicles')) {
            return $query;
        }

        if (! self::hasColumn('logistics_fleet_vehicles', 'code')
            || ! self::hasColumn('logistics_fleet_vehicles', 'registration')) {
            return $query;
        }

        $legacyDemoVehicles = [
            ['FLT-001', '3301-AB01'], ['FLT-002', '5567-GJ01'], ['FLT-003', 'TR-2456'],
            ['FLT-004', 'TR-3366'], ['FLT-005', 'PK-7789'], ['FLT-006', 'PK-4412'],
            ['FLT-007', 'ABJ-1234-AB'], ['FLT-008', 'CM-8877'], ['FLT-009', 'CT-9001'],
            ['FLT-010', 'CT-9015'], ['FLT-011', '3302-AB01'], ['FLT-012', '3303-AB01'],
            ['FLT-013', 'TR-3305'], ['FLT-014', 'PK-5501'], ['FLT-015', 'CM-7701'],
            ['FLT-016', 'CT-7705'], ['FLT-017', '3304-AB01'], ['FLT-018', '3305-AB01'],
        ];

        foreach ($legacyDemoVehicles as [$code, $registration]) {
            $query->where(function (Builder $vehicle) use ($code, $registration) {
                $vehicle->where('code', '!=', $code)
                    ->orWhere('registration', '!=', $registration);
            });
        }

        return $query;
    }

    /**
     * Le scope peut être appliqué soit à un Builder classique, soit directement
     * à une relation Eloquent (HasMany/HasOne...) pendant un eager loading.
     * Laravel transmet une instance de Relation aux callbacks de load()/with(),
     * tandis que whereHas()/les requêtes directes transmettent un Builder.
     *
     * @template TQuery of Builder|Relation
     * @param TQuery $query
     * @return TQuery
     */
    public static function assignments(Builder|Relation $query): Builder|Relation
    {
        if (self::hasColumn('delivery_assignments', 'mission_number')) {
            $query->where(function (Builder $q) {
                $q->whereNull('mission_number')
                    ->orWhere(function (Builder $mission) {
                        $mission->where('mission_number', 'not like', 'SHP-DEMO-%')
                            ->where('mission_number', 'not like', 'GPS-DEMO-%');
                    });
            });
        }

        $query->whereHas('driver', fn (Builder $driver) => self::drivers($driver));
        $query->whereHas('order', fn (Builder $order) => self::orders($order));

        return $query;
    }

    public static function shipments(Builder $query): Builder
    {
        if (self::hasColumn('shipments', 'tracking_number')) {
            $query->where(function (Builder $q) {
                $q->whereNull('tracking_number')
                    ->orWhere('tracking_number', 'not like', 'SHP-DEMO-%');
            });
        }

        if (self::hasColumn('shipments', 'routing_provider')) {
            $query->where(function (Builder $q) {
                $q->whereNull('routing_provider')->orWhere('routing_provider', '!=', 'demo');
            });
        }

        $query->whereHas('order', fn (Builder $order) => self::orders($order));

        return $query;
    }

    public static function shops(Builder $query): Builder
    {
        $query->where(function (Builder $shop) {
            $shop->whereNull('slug')->orWhere('slug', 'not like', 'demo-%');
        });

        if (self::hasColumn('shops', 'identity_number')) {
            $query->where(function (Builder $shop) {
                $shop->whereNull('identity_number')
                    ->orWhere(function (Builder $identity) {
                        $identity->where('identity_number', 'not like', 'CI-DEMO-%')
                            ->where('identity_number', 'not like', 'DEMO-%');
                    });
            });
        }

        $query->whereDoesntHave('user', fn (Builder $user) => $user->where('email', 'like', '%demo%'));

        return $query;
    }
}
