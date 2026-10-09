<?php

namespace App\Http\Controllers\Auth;

use App\Support\ProForms\ProFormSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** ACC-18 Mon compte, ACC-19 évolution du profil et des rôles. */
class ProAccountController extends ProAccessController
{
    public function show(Request $request, ProFormSchema $schema): View|RedirectResponse
    {
        $user = $this->proUser();
        if ($redirect = $this->onboardingRedirect($user)) {
            return $redirect;
        }
        $profile = $user->professionalProfile()->with('organization.documents')->first();
        $organization = $profile?->organization;

        $missing = [];
        if ($profile && $profile->entity_type && $schema->has($profile->profile_key)) {
            $values = array_merge((array) $profile->profile_data, $profile->only([
                'responsible_position', 'professional_email', 'professional_phone', 'professional_whatsapp',
            ]), $organization ? $organization->only($organization->getFillable()) : []);
            $provided = $organization ? $organization->documents->where('status', '!=', 'replaced')->pluck('document_type')->unique()->all() : [];
            $missing = $schema->missing($profile->profile_key, $profile->entity_type, $values, $provided);
        }

        [$phoneCode, $phoneNumber] = $this->splitPhone($user->phone);

        return view('pro.auth.account', [
            'user' => $user,
            'profile' => $profile,
            'profileLabel' => $profile ? $schema->label($profile->profile_key) : null,
            'missing' => $missing,
            'memberships' => $user->companyMemberships()->with('organization')->whereNotNull('pro_organization_id')->get(),
            'activeRoles' => $user->roles()->pluck('role')->all(),
            'roles' => $this->businessRoles(),
            'permissions' => $this->permissions(),
            'countries' => $this->countries(),
            'languages' => $this->languages(),
            'phoneCodes' => $this->phoneCodes(),
            'phoneCode' => $phoneCode,
            'phoneNumber' => $phoneNumber,
            'session' => [
                'ip' => $request->ip(),
                'agent' => $this->describeAgent((string) $request->userAgent()),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->proUser();
        $this->mergePhone($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validateWithBag('identity', [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'residence_country' => ['required', Rule::in($this->countries())],
            'preferred_language' => ['required', Rule::in(array_keys($this->languages()))],
            'phone_country' => $this->phoneCodeRule(),
            'phone' => ['required', 'regex:/^\+[0-9]{8,15}$/', Rule::unique('pro_users', 'phone')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('pro_users', 'email')->ignore($user->id)],
        ], [
            'phone.unique' => 'Ce numéro est déjà utilisé par un autre compte.',
            'email.unique' => 'Cette adresse est déjà utilisée par un autre compte.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
        ], ['last_name' => 'nom', 'first_name' => 'prénoms', 'residence_country' => 'pays de résidence', 'preferred_language' => 'langue', 'phone' => 'téléphone', 'email' => 'e-mail']);

        unset($data['phone_country']);
        $user->fill($data + ['name' => trim($data['first_name'] . ' ' . $data['last_name']), 'locale' => $data['preferred_language']]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('success', 'Vos informations personnelles sont enregistrées.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->proUser();
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:pro'],
            'password' => ['required', 'confirmed', 'different:current_password', $this->passwordRule()],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.different' => 'Choisissez un mot de passe différent de l’actuel.',
        ], ['current_password' => 'mot de passe actuel', 'password' => 'nouveau mot de passe']);

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        // Les autres sessions ouvertes sont fermées : leur empreinte de mot de passe ne correspond plus.
        $request->session()->put('pro_password_hash_' . $user->id, $user->fresh()->getAuthPassword());

        return back()->with('success', 'Mot de passe modifié. Vos autres sessions ont été déconnectées.');
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $user = $this->proUser();
        $request->validateWithBag('sessions', [
            'session_password' => ['required', 'current_password:pro'],
        ], ['session_password.current_password' => 'Mot de passe incorrect.'], ['session_password' => 'mot de passe']);

        $user->forceFill([
            'password' => Hash::make($request->input('session_password')),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->put('pro_password_hash_' . $user->id, $user->fresh()->getAuthPassword());

        return back()->with('success', 'Toutes vos autres sessions ont été déconnectées.');
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };
        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'appareil inconnu',
        };

        return "$browser sur $system";
    }
}
