<?php

namespace App\Services\SupportAi;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportIdentityResolver
{
    /** @var list<string>|null */
    private static ?array $availablePhoneColumns = null;

    public function __construct(private readonly SupportPhoneNormalizer $phones) {}

    /**
     * @return array{user:?User,method:string,confidence:float}
     */
    public function resolve(?User $authenticatedUser, ?string $email, ?string $phone): array
    {
        if ($authenticatedUser) {
            return ['user' => $authenticatedUser, 'method' => 'authenticated', 'confidence' => 100.0];
        }

        $normalizedEmail = Str::lower(trim((string) $email));
        if ($normalizedEmail !== '') {
            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->limit(2)
                ->get();

            if ($users->count() === 1) {
                return ['user' => $users->first(), 'method' => 'email', 'confidence' => 98.0];
            }
        }

        $columns = $this->phoneColumns();
        $variants = $this->phones->variants($phone);

        if ($columns !== [] && $variants !== []) {
            $users = User::query()
                ->where(function (Builder $query) use ($columns, $variants): void {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'whereIn' : 'orWhereIn';
                        $query->{$method}($column, $variants);
                    }
                })
                ->limit(2)
                ->get();

            if ($users->count() === 1) {
                return ['user' => $users->first(), 'method' => 'phone', 'confidence' => 96.0];
            }

            $canonical = $this->phones->canonical($phone);
            $digits = $canonical ? preg_replace('/\D+/', '', $canonical) : null;
            $tail = $digits ? substr($digits, -8) : null;

            if ($tail) {
                $candidates = User::query()
                    ->where(function (Builder $query) use ($columns, $tail): void {
                        foreach ($columns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $query->{$method}($column, 'like', "%{$tail}");
                        }
                    })
                    ->limit(10)
                    ->get()
                    ->filter(function (User $candidate) use ($columns, $phone): bool {
                        foreach ($columns as $column) {
                            if ($this->phones->same($candidate->getAttribute($column), $phone)) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->values();

                if ($candidates->count() === 1) {
                    return ['user' => $candidates->first(), 'method' => 'phone_normalized', 'confidence' => 94.0];
                }
            }
        }

        return ['user' => null, 'method' => 'unmatched', 'confidence' => 0.0];
    }

    public function applyToConversation(
        SupportConversation $conversation,
        ?User $authenticatedUser = null,
        ?string $email = null,
        ?string $phone = null,
    ): SupportConversation {
        $match = $this->resolve(
            $authenticatedUser,
            $email ?: $conversation->requester_email,
            $phone ?: $conversation->requester_phone,
        );

        $metadata = (array) ($conversation->metadata ?? []);
        $whatsappProfileName = trim((string) ($conversation->requester_name ?? ''));
        if ($whatsappProfileName !== '') {
            data_set($metadata, 'identity.whatsapp_profile_name', $whatsappProfileName);
        }

        if (! $match['user']) {
            $conversation->forceFill([
                'metadata' => $metadata,
                'requester_user_id' => null,
                'requester_email' => filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? Str::lower(trim((string) $email))
                    : $conversation->requester_email,
                'requester_phone' => trim((string) $phone) !== ''
                    ? trim((string) $phone)
                    : $conversation->requester_phone,
                'requester_match_method' => 'unmatched',
                'requester_matched_at' => null,
            ])->save();

            return $conversation->fresh();
        }

        $user = $match['user'];
        $accountName = trim((string) ($user->name ?: $user->full_name));
        $stableName = $accountName !== ''
            ? $accountName
            : (trim((string) $conversation->requester_name) ?: null);

        data_set($metadata, 'identity.stable_display_name', $stableName);
        data_set($metadata, 'identity.source', 'ovanie_account');
        data_set($metadata, 'identity.user_id', (int) $user->id);

        $conversation->forceFill([
            'requester_user_id' => $user->id,
            // IMPORTANT : une fois le compte identifié, le nom OVANIE devient la source unique.
            // Le nom de profil WhatsApp reste seulement dans metadata.identity.whatsapp_profile_name.
            'requester_name' => $stableName,
            'requester_email' => $user->email ?: $conversation->requester_email,
            'requester_phone' => $conversation->requester_phone ?: $user->phone,
            'requester_match_method' => $match['method'],
            'requester_matched_at' => now(),
            'metadata' => $metadata,
        ])->save();

        return $conversation->fresh('requester');
    }

    /** @return list<string> */
    private function phoneColumns(): array
    {
        if (self::$availablePhoneColumns !== null) {
            return self::$availablePhoneColumns;
        }

        if (! Schema::hasTable('users')) {
            return self::$availablePhoneColumns = [];
        }

        return self::$availablePhoneColumns = collect(['phone', 'secondary_phone', 'whatsapp_phone'])
            ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
            ->values()
            ->all();
    }
}
