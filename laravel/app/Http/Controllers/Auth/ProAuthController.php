<?php

namespace App\Http\Controllers\Auth;

use App\Models\ProUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** ACC-01 choix du parcours, ACC-02 compte commun, ACC-15 connexion. */
class ProAuthController extends ProAccessController
{
    public function choice(): View|RedirectResponse
    {
        if ($this->proUser()) {
            return redirect()->route('pro.account');
        }

        return view('pro.auth.choice');
    }

    public function showLogin(): View|RedirectResponse
    {
        if ($this->proUser()) {
            return redirect()->route('pro.account');
        }

        return view('pro.auth.login');
    }

    /** Connexion par e-mail ou téléphone, sans ambiguïté entre deux pays. */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['identifier' => 'e-mail ou téléphone', 'password' => 'mot de passe']);

        $user = $this->findProUserByIdentifier((string) $data['identifier']);

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            return back()
                ->withErrors(['identifier' => 'E-mail, téléphone ou mot de passe incorrect.'])
                ->withInput($request->only('identifier', 'remember'));
        }

        if ($user->status !== 'active') {
            return back()
                ->withErrors(['identifier' => 'Ce compte est désactivé. Contactez le support OVANIE PRO.'])
                ->withInput($request->only('identifier'));
        }

        Auth::guard('pro')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('pro_password_hash_' . $user->id, $user->getAuthPassword());

        return redirect()->intended(route('pro.account'));
    }

    public function showRegister(Request $request, string $accountType): View|RedirectResponse
    {
        if ($user = $this->proUser()) {
            return $accountType === 'pro'
                ? redirect()->route('pro.register.qualification', array_filter(['profile' => $request->query('profile')]))
                : redirect()->route('pro.account');
        }

        if ($accountType === 'simple') {
            $purpose = $request->query('purpose');

            return redirect()->route('pro.publish.start', array_filter(['type' => ['advertiser' => 'offre', 'requester' => 'besoin'][$purpose] ?? null]));
        }

        $profile = (string) $request->query('profile', '');

        return view('pro.auth.register', [
            'accountType' => $accountType,
            'purpose' => in_array($request->query('purpose'), ['advertiser', 'requester'], true) ? $request->query('purpose') : null,
            'profile' => config("ovanie_pro.professional_profiles.$profile") ? $profile : null,
            'languages' => $this->languages(),
            'countries' => $this->countries(),
            'phoneCodes' => $this->phoneCodes(),
        ]);
    }

    public function register(Request $request, string $accountType): RedirectResponse
    {
        $this->mergePhone($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'residence_country' => ['required', Rule::in($this->countries())],
            'phone_country' => $this->phoneCodeRule(),
            'phone' => ['required', 'string', 'regex:/^\+[0-9]{8,15}$/', Rule::unique('pro_users', 'phone')],
            'email' => ['required', 'email', 'max:255', Rule::unique('pro_users', 'email')],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'preferred_language' => ['required', Rule::in(array_keys($this->languages()))],
            'purpose' => [$accountType === 'simple' ? 'required' : 'nullable', Rule::in(['advertiser', 'requester'])],
            'profile' => ['nullable', Rule::in(array_keys((array) config('ovanie_pro.professional_profiles', [])))],
            'terms' => ['accepted'],
        ], [
            'phone.unique' => 'Ce numéro est déjà utilisé par un compte OVANIE PRO.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée. Connectez-vous ou réinitialisez votre mot de passe.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation.',
            'purpose.required' => 'Indiquez ce que vous souhaitez publier.',
        ], [
            'last_name' => 'nom', 'first_name' => 'prénoms', 'residence_country' => 'pays de résidence',
            'phone' => 'téléphone', 'email' => 'e-mail', 'password' => 'mot de passe', 'preferred_language' => 'langue',
        ]);

        $user = DB::transaction(function () use ($data, $accountType) {
            $user = ProUser::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'residence_country' => $data['residence_country'],
                'preferred_language' => $data['preferred_language'],
                'locale' => $data['preferred_language'],
                'password' => $data['password'],
                'account_type' => $accountType,
                'onboarding_path' => $accountType === 'pro' ? 'professional' : 'simple',
                'status' => 'active',
                'professional_status' => 'not_started',
                'currency' => 'XOF',
            ]);

            if ($accountType === 'simple') {
                $user->roles()->create(['role' => $data['purpose']]);
            }

            return $user;
        });

        Auth::guard('pro')->login($user);
        $request->session()->regenerate();
        $request->session()->put('pro_password_hash_' . $user->id, $user->getAuthPassword());

        if ($accountType === 'pro') {
            return redirect()->route('pro.register.qualification', array_filter(['profile' => $data['profile'] ?? null]))
                ->with('success', 'Compte créé. Qualifiez maintenant votre activité professionnelle.');
        }

        return redirect()->route('pro.account')->with('success', 'Votre compte OVANIE PRO est créé.');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Seul un chemin d'invitation interne peut être conservé comme destination
        // après changement de compte. Aucun redirect externe n'est accepté.
        $returnTo = (string) $request->input('return_to', '');
        $safeReturnTo = preg_match('~^/compte/invitation/[A-Za-z0-9]+$~', $returnTo) ? $returnTo : null;

        Auth::guard('pro')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($safeReturnTo) {
            $request->session()->put('url.intended', url($safeReturnTo));
        }

        return redirect()->route('pro.login')->with('status', 'Vous êtes déconnecté. Votre progression est conservée.');
    }
}
