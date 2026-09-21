<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CommercialMobileClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();
        $commercialId = (int) $commercial->id;
        $search = trim((string) $request->query('q', ''));
        $status = Str::lower(trim((string) $request->query('status', 'all')));

        $base = User::query()
            ->where('role', 'client')
            ->where('created_by_commercial_id', $commercialId);

        $summary = [
            'all' => (clone $base)->count(),
            'active' => $this->activeClients(clone $base)->count(),
            'prospects' => $this->prospectClients(clone $base)->count(),
            'inactive' => $this->inactiveClients(clone $base)->count(),
        ];

        $query = clone $base;

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($needle) {
                $builder
                    ->where('name', 'like', $needle)
                    ->orWhere('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('phone', 'like', $needle)
                    ->orWhere('city', 'like', $needle)
                    ->orWhere('account_type', 'like', $needle);
            });
        }

        $query = match ($status) {
            'active' => $this->activeClients($query),
            'prospect' => $this->prospectClients($query),
            'inactive' => $this->inactiveClients($query),
            default => $query,
        };

        $clients = $query
            ->with(['addresses' => function ($addressQuery) {
                $addressQuery
                    ->orderByDesc('is_default')
                    ->orderByDesc('id');
            }])
            ->withCount([
                'orders as orders_count' => fn ($orderQuery) => $orderQuery->operational(),
            ])
            ->addSelect([
                'last_order_at' => Order::query()
                    ->select('created_at')
                    ->whereColumn('client_id', 'users.id')
                    ->operational()
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->latest('users.created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'profile' => $this->profile($commercial),
            'unread_notifications' => $commercial->unreadNotifications()->count(),
            'summary' => $summary,
            'clients' => $clients->map(fn (User $client) => $this->serializeClient($client))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $commercial */
        $commercial = $request->user();

        $data = $request->validate([
            'client_type' => ['required', Rule::in(['particulier', 'entreprise'])],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone_country' => ['required', Rule::in(['+225', '+221', '+223', '+226', '+233', '+224'])],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $normalized = $this->normalizePhone(
                        (string) $request->input('phone_country', '+225'),
                        (string) $value,
                    );

                    if (User::query()->where('phone', $normalized)->exists()) {
                        $fail('Ce numéro de téléphone est déjà utilisé.');
                    }
                },
            ],
            'city' => ['required', 'string', 'max:120'],
            'commune_quartier' => ['nullable', 'string', 'max:160'],
            'commercial_notes' => ['nullable', 'string', 'max:2000'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'send_credentials' => ['sometimes', 'boolean'],
        ], [
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ]);

        $fullName = trim(preg_replace('/\s+/', ' ', (string) $data['full_name']) ?? '');
        $parts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = array_shift($parts) ?: $fullName;
        $lastName = trim(implode(' ', $parts));
        $phone = $this->normalizePhone((string) $data['phone_country'], (string) $data['phone']);
        $communeQuartier = trim((string) ($data['commune_quartier'] ?? ''));
        if ($communeQuartier === '') {
            $communeQuartier = trim((string) $data['city']);
        }
        $notes = trim((string) ($data['commercial_notes'] ?? ''));

        /** @var User $client */
        $client = DB::transaction(function () use (
            $commercial,
            $data,
            $fullName,
            $firstName,
            $lastName,
            $phone,
            $communeQuartier,
            $notes,
        ) {
            $client = User::create([
                'created_by_commercial_id' => $commercial->id,
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'name' => $fullName,
                'email' => Str::lower(trim((string) $data['email'])),
                'phone' => $phone,
                'password' => Hash::make((string) $data['password']),
                'role' => 'client',
                'status' => 'active',
                'city' => trim((string) $data['city']),
                'account_type' => (string) $data['client_type'],
            ]);

            $client->addresses()->create([
                'type' => 'other',
                'label' => 'Adresse commerciale',
                'recipient_name' => $fullName,
                'city' => trim((string) $data['city']),
                'commune' => $communeQuartier,
                'quartier' => $communeQuartier,
                'country' => "Côte d'Ivoire",
                'address' => $communeQuartier.', '.trim((string) $data['city']),
                'phone' => $phone,
                'is_default' => true,
            ]);

            if ($notes !== '' && Schema::hasColumn('users', 'commercial_notes')) {
                DB::table('users')
                    ->where('id', $client->id)
                    ->update(['commercial_notes' => $notes]);
            }

            return $client;
        });

        $delivery = ['email' => false, 'sms' => false];
        if ((bool) ($data['send_credentials'] ?? false)) {
            $delivery = $this->sendCredentials(
                $client,
                (string) $data['password'],
                $phone,
            );
        }

        $client->load(['addresses' => fn ($query) => $query->orderByDesc('is_default')->orderByDesc('id')]);
        $client->setAttribute('orders_count', 0);
        $client->setAttribute('last_order_at', null);

        return response()->json([
            'message' => 'Le client a été créé et ajouté à votre portefeuille commercial.',
            'client' => $this->serializeClient($client),
            'credential_delivery' => $delivery,
        ], 201);
    }

    private function activeClients(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('orders', fn ($orderQuery) => $orderQuery->operational());
    }

    private function prospectClients(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereDoesntHave('orders', fn ($orderQuery) => $orderQuery->operational());
    }

    private function inactiveClients(Builder $query): Builder
    {
        return $query->where('status', '!=', 'active');
    }

    private function serializeClient(User $client): array
    {
        $address = $client->addresses->first();
        $ordersCount = (int) ($client->getAttribute('orders_count') ?? 0);
        $status = (string) $client->status !== 'active'
            ? 'inactive'
            : ($ordersCount > 0 ? 'active' : 'prospect');

        $name = trim((string) ($client->name ?: $client->full_name));
        $communeQuartier = trim((string) (
            $address?->quartier
            ?: $address?->commune
            ?: ''
        ));

        return [
            'id' => $client->id,
            'name' => $name !== '' ? $name : 'Client OVANIE',
            'email' => (string) $client->email,
            'phone' => (string) ($client->phone ?? ''),
            'account_type' => (string) ($client->account_type ?: 'particulier'),
            'client_status' => $status,
            'city' => (string) ($client->city ?: $address?->city ?: ''),
            'commune_quartier' => $communeQuartier,
            'orders_count' => $ordersCount,
            'last_order_at' => $client->getAttribute('last_order_at')
                ? (string) $client->getAttribute('last_order_at')
                : null,
        ];
    }

    private function profile(User $user): array
    {
        $avatar = trim((string) ($user->avatar ?? ''));
        $avatarUrl = null;

        if ($avatar !== '') {
            $avatarUrl = Str::startsWith($avatar, ['http://', 'https://'])
                ? $avatar
                : url('/storage/'.ltrim($avatar, '/'));
        }

        $name = $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?: Str::before($name, ' '),
            'name' => $name,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function sendCredentials(User $client, string $plainPassword, string $phone): array
    {
        $result = ['email' => false, 'sms' => false];
        $message = "Bienvenue sur OVANIE. Votre identifiant est {$client->email} et votre mot de passe temporaire est {$plainPassword}. Connectez-vous puis modifiez votre mot de passe.";

        try {
            Mail::raw($message, function ($mail) use ($client) {
                $mail->to($client->email)->subject('Vos accès OVANIE');
            });
            $result['email'] = true;
        } catch (\Throwable $error) {
            Log::warning('Accès client OVANIE non envoyés par e-mail.', [
                'client_id' => $client->id,
                'error' => $error->getMessage(),
            ]);
        }

        try {
            $result['sms'] = SmsService::send($phone, $message) !== false;
        } catch (\Throwable $error) {
            Log::warning('Accès client OVANIE non envoyés par SMS.', [
                'client_id' => $client->id,
                'error' => $error->getMessage(),
            ]);
        }

        return $result;
    }

    private function normalizePhone(string $country, string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $country) ?? '';

        if ($countryDigits !== '' && Str::startsWith($digits, $countryDigits)) {
            return '+'.$digits;
        }

        return '+'.$countryDigits.ltrim($digits, '0');
    }
}
