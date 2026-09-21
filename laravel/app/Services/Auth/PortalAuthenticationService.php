<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PortalAuthenticationService
{
    public const PORTAL_CLIENT = 'client';
    public const PORTAL_VENDOR = 'vendor';

    public function normalizePortal(?string $portal): ?string
    {
        $value = Str::lower(trim((string) $portal));

        return in_array($value, [self::PORTAL_CLIENT, self::PORTAL_VENDOR], true)
            ? $value
            : null;
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($identifier)])
                ->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if ($digits === '') {
            return null;
        }

        $variants = collect([$digits])
            ->when(strlen($digits) === 10, fn ($items) => $items->push('225'.$digits))
            ->when(
                str_starts_with($digits, '225') && strlen($digits) > 10,
                fn ($items) => $items->push(substr($digits, 3))
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $normalizedPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '')";
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $hasWhatsappPhone = Schema::hasColumn('users', 'whatsapp_phone');
        $normalizedWhatsappSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp_phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '')";

        return User::query()
            ->where(function ($query) use (
                $identifier,
                $variants,
                $normalizedPhoneSql,
                $normalizedWhatsappSql,
                $placeholders,
                $hasWhatsappPhone
            ) {
                $query->where('phone', $identifier)
                    ->orWhereRaw("{$normalizedPhoneSql} IN ({$placeholders})", $variants);

                if ($hasWhatsappPhone) {
                    $query->orWhere('whatsapp_phone', $identifier)
                        ->orWhereRaw("{$normalizedWhatsappSql} IN ({$placeholders})", $variants);
                }
            })
            ->first();
    }

    public function validateCredentials(User $user, string $password): bool
    {
        $provider = Auth::guard('web')->getProvider();

        if ($provider instanceof EloquentUserProvider) {
            return $provider->validateCredentials($user, ['password' => $password]);
        }

        return password_verify($password, (string) $user->getAuthPassword());
    }

    public function accessError(User $user, ?string $portal): ?string
    {
        if ($this->isInternalUser($user)) {
            return 'Les comptes du personnel OVANIE utilisent leur application ou portail interne.';
        }

        if (Str::lower(trim((string) ($user->status ?? ''))) !== 'active') {
            return 'Ce compte est suspendu ou désactivé. Contactez le support OVANIE.';
        }

        $portal = $this->normalizePortal($portal);
        if ($portal === null) {
            return null;
        }

        $role = Str::lower(trim((string) ($user->role ?? 'client')));

        if ($portal === self::PORTAL_CLIENT && $role !== self::PORTAL_CLIENT) {
            return 'Ce compte est un compte vendeur. Utilisez l’espace vendeur OVANIE.';
        }

        if ($portal === self::PORTAL_VENDOR && $role !== self::PORTAL_VENDOR) {
            return 'Ce compte est un compte client. Utilisez l’espace client OVANIE.';
        }

        return null;
    }

    public function isInternalUser(User $user): bool
    {
        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        return in_array(
            (string) ($user->role ?? ''),
            config('staff.internal_roles', []),
            true
        );
    }
}
