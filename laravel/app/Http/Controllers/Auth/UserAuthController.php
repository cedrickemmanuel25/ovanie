<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PortalAuthenticationService;
use App\Services\Auth\PublicAccountService;
use App\Services\GuestCartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAuthController extends Controller
{
    /**
     * Endpoint historique de création Client.
     * Il utilise le même contrat que registerWeb() et l'application mobile.
     */
    public function register(Request $request, PublicAccountService $accounts)
    {
        $data = $accounts->validateClientRegistration($request);
        $user = $accounts->createClient($data);

        Auth::guard('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
            app(GuestCartService::class)->mergeIntoUserCart($request, $user);
        }

        return response()->json([
            'message' => 'Inscription réussie.',
            'redirect' => route('client.dashboard'),
            'user' => $user,
        ], 201);
    }

    /**
     * Connexion historique conservée pour compatibilité.
     */
    public function login(Request $request, PortalAuthenticationService $portalAuth)
    {
        $identifier = trim((string) $request->input('identifier', $request->input('email', '')));
        $request->merge(['identifier' => $identifier]);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Renseignez votre e-mail ou votre numéro de téléphone.',
        ]);

        $user = $portalAuth->findUserByIdentifier($data['identifier']);

        if (! $user || ! $portalAuth->validateCredentials($user, $data['password'])) {
            return back()
                ->withErrors(['identifier' => 'Identifiants incorrects. Vérifiez votre e-mail/téléphone et votre mot de passe.'])
                ->withInput($request->except('password'));
        }

        if ($error = $portalAuth->accessError($user, null)) {
            return back()
                ->withErrors(['identifier' => $error])
                ->withInput($request->except('password'));
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        app(GuestCartService::class)->mergeIntoUserCart($request, $user);

        return redirect()->intended(route($this->dashboardRoute($user), absolute: false));
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Connexion Web Client/Vendeur.
     *
     * Les deux portails partagent le même formulaire et le même moteur ; la
     * route serveur fixe le portail afin d'empêcher le croisement des rôles.
     */
    public function loginWeb(Request $request, PortalAuthenticationService $portalAuth)
    {
        // Le formulaire Web historique conserve volontairement le champ
        // `email`, même s’il accepte aussi un numéro de téléphone.
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Renseignez votre email ou votre numéro de téléphone.',
        ]);

        $identifier = trim((string) $data['email']);
        $user = $portalAuth->findUserByIdentifier($identifier);

        if (! $user || ! $portalAuth->validateCredentials($user, $data['password'])) {
            return back()
                ->withErrors(['email' => 'Identifiants invalides.'])
                ->withInput($request->except('password'));
        }

        // Le Web conserve son fonctionnement historique : un seul formulaire
        // permet la connexion Client ou Vendeur, puis redirige selon le rôle.
        if ($error = $portalAuth->accessError($user, null)) {
            return back()
                ->withErrors(['email' => $error])
                ->withInput($request->except('password'));
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        app(GuestCartService::class)->mergeIntoUserCart($request, $user);

        return redirect()->intended(route($this->dashboardRoute($user), absolute: false));
    }

    private function dashboardRoute(User $user): string
    {
        return $user->role === PortalAuthenticationService::PORTAL_VENDOR
            ? 'vendor.dashboard'
            : 'client.dashboard';
    }

    public function showRegistrationChoice()
    {
        return view('auth.register-choice');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /**
     * Inscription Web Client : même données/règles que l'application Client.
     */
    public function registerWeb(Request $request, PublicAccountService $accounts)
    {
        $data = $accounts->validateClientRegistration($request);
        $user = $accounts->createClient($data);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        app(GuestCartService::class)->mergeIntoUserCart($request, $user);

        return redirect()->intended(route('client.dashboard', absolute: false));
    }
}
