<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Contrat unique de création des comptes Client OVANIE.
 *
 * Le formulaire Web historique reste inchangé. L'application Client envoie
 * les mêmes champs : prénom, nom, e-mail, indicatif, téléphone, mot de passe,
 * confirmation et acceptation des conditions.
 */
class PublicAccountService
{
    public const PHONE_COUNTRIES = ['+225', '+221', '+223', '+226', '+233', '+224'];

    public function validateClientRegistration(Request $request): array
    {
        $phoneCountry = $this->resolveCountryCode(
            $request->input('phone_country', '+225'),
            $request->input('phone')
        );
        $phone = $this->normalizePublicPhone($phoneCountry, $request->input('phone'));

        $normalized = [
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone_country' => $phoneCountry,
            'phone' => $phone,
            'password' => (string) $request->input('password'),
            'password_confirmation' => (string) $request->input('password_confirmation'),
            'terms' => $request->input('terms'),
            'device_name' => trim((string) $request->input('device_name')),
        ];

        $request->merge($normalized);

        return Validator::make($normalized, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone_country' => ['required', Rule::in(self::PHONE_COUNTRIES)],
            'phone' => [
                'required',
                'regex:/^\+[1-9][0-9]{7,14}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($phoneCountry): void {
                    $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
                    $countryDigits = ltrim($phoneCountry, '+');
                    $local = str_starts_with($digits, $countryDigits)
                        ? substr($digits, strlen($countryDigits))
                        : $digits;

                    $variants = [$digits];
                    // Compatibilité avec les anciens comptes ivoiriens qui ont
                    // pu être enregistrés sans +225 dans la base.
                    if ($phoneCountry === '+225' && $local !== '') {
                        $variants[] = $local;
                    }
                    $variants = array_values(array_unique(array_filter($variants)));

                    $normalizedSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '')";
                    $placeholders = implode(',', array_fill(0, count($variants), '?'));

                    if ($variants !== [] && User::query()
                        ->whereRaw("{$normalizedSql} IN ({$placeholders})", $variants)
                        ->exists()) {
                        $fail('Ce numéro de téléphone est déjà associé à un compte OVANIE.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ], [
            'first_name.required' => 'Renseignez votre prénom.',
            'last_name.required' => 'Renseignez votre nom.',
            'email.required' => 'Renseignez votre adresse e-mail.',
            'email.email' => 'L’adresse e-mail renseignée est invalide.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte OVANIE.',
            'phone_country.in' => 'Sélectionnez un indicatif proposé par OVANIE.',
            'phone.required' => 'Renseignez votre numéro de téléphone.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
            'password.required' => 'Créez un mot de passe.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'terms.accepted' => 'Vous devez accepter les Conditions d’utilisation et la Politique de confidentialité.',
        ])->validate();
    }

    public function createClient(array $data): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => PortalAuthenticationService::PORTAL_CLIENT,
            'status' => 'active',
            'password' => Hash::make((string) $data['password']),
        ]);
    }

    public function resolveCountryCode(mixed $countryCode, mixed $phone): string
    {
        $raw = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        $internationalDigits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        // Compatibilité avec les anciens clients mobiles qui envoyaient déjà
        // le numéro complet (+225..., +221..., etc.) sans phone_country.
        foreach (self::PHONE_COUNTRIES as $allowedCode) {
            $allowedDigits = ltrim($allowedCode, '+');
            if (str_starts_with($internationalDigits, $allowedDigits)
                && strlen($internationalDigits) > strlen($allowedDigits) + 5) {
                return $allowedCode;
            }
        }

        return $this->normalizeCountryCode($countryCode);
    }

    public function normalizeCountryCode(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
        $code = $digits === '' ? '+225' : '+'.$digits;

        return in_array($code, self::PHONE_COUNTRIES, true) ? $code : '+225';
    }

    public function normalizePublicPhone(string $countryCode, mixed $value): string
    {
        $countryCode = $this->normalizeCountryCode($countryCode);
        $countryDigits = ltrim($countryCode, '+');
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        if (str_starts_with($digits, '00'.$countryDigits)) {
            $digits = substr($digits, 2 + strlen($countryDigits));
        } elseif (str_starts_with($digits, $countryDigits)) {
            $digits = substr($digits, strlen($countryDigits));
        }

        return $digits === '' ? '' : $countryCode.$digits;
    }
}
