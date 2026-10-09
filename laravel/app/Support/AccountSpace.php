<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Espace de travail du compte connecté, quel que soit son type : équipe interne, livreur, vendeur ou client.
 * Sert aux liens « Mon tableau de bord » des pages publiques et à écarter les non-clients de l'espace client.
 */
class AccountSpace
{
    /** @return array{key:string,label:string,url:string,name:string}|null */
    public static function current(): ?array
    {
        if ($staff = Auth::guard('admin')->user()) {
            $role = (string) ($staff->role ?? '');
            [$key, $label, $route] = match (true) {
                (bool) ($staff->is_admin ?? false) || $role === 'admin' => ['admin', 'Administration', 'admin.dashboard'],
                in_array($role, ['logistique', 'logistics'], true) => ['logistique', 'Espace logistique', 'logistics.dashboard'],
                $role === 'support' => ['support', 'Espace support', 'support.dashboard'],
                $role === 'commercial' => ['commercial', 'Espace commercial', 'commercial.dashboard'],
                default => ['admin', 'Administration', 'admin.dashboard'],
            };

            return self::space($key, $label, $route, $staff);
        }

        if ($driver = Auth::guard('driver')->user()) {
            return self::space('livreur', 'Espace livreur', 'driver.dashboard', $driver);
        }

        if ($user = Auth::guard('web')->user()) {
            $role = (string) ($user->role ?? '');
            // Un compte interne connecté par le formulaire public garde son propre espace.
            if ((bool) ($user->is_admin ?? false) || $role === 'admin') {
                return self::space('admin', 'Administration', 'admin.dashboard', $user);
            }
            if (in_array($role, ['logistique', 'logistics'], true)) {
                return self::space('logistique', 'Espace logistique', 'logistics.dashboard', $user);
            }
            if ($role === 'support') {
                return self::space('support', 'Espace support', 'support.dashboard', $user);
            }
            if ($role === 'commercial') {
                return self::space('commercial', 'Espace commercial', 'commercial.dashboard', $user);
            }
            if ($role === 'vendor') {
                return self::space('vendeur', 'Espace vendeur', 'vendor.dashboard', $user);
            }

            return self::space('client', 'Mon compte OVANIE', 'client.dashboard', $user);
        }

        return null;
    }

    public static function isClient(): bool
    {
        return (self::current()['key'] ?? null) === 'client';
    }

    private static function space(string $key, string $label, string $route, object $account): array
    {
        $name = trim((string) ($account->first_name ?? '')) ?: trim((string) ($account->name ?? ''));

        return [
            'key' => $key,
            'label' => $label,
            'url' => Route::has($route) ? route($route) : url('/'),
            'name' => $name !== '' ? $name : $label,
        ];
    }
}
