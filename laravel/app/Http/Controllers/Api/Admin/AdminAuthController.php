<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternalLoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminAuthController extends Controller
{
    /**
     * Portail unique du personnel interne OVANIE.
     */
    public function showLoginForm()
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            return redirect()->route($this->dashboardRoute($user));
        }

        return view('admin.auth.adminlogin');
    }

    /**
     * L'enregistrement admin est conservé pour l'initialisation de la plateforme.
     * Les comptes Logistique, Support et Commercial se créent depuis /admin/staff.
     */
    public function showRegisterForm()
    {
        return view('admin.auth.adminregister');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'status' => 'active',
            'is_admin' => 1,
        ]);

        event(new Registered($user));

        return redirect()->route('admin.adminlogin')
            ->with('success', 'Compte administrateur créé. Vous pouvez maintenant vous connecter.');
    }

    /**
     * Authentifie Administration, Logistique, Support et Commercial sur le guard admin.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim((string) $credentials['email']));
        $user = User::query()
            ->with('staffProfile')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $allowedRoles = config('staff.internal_roles', ['admin', 'logistique', 'support', 'commercial']);
        $isInternal = $user
            && in_array((string) $user->role, $allowedRoles, true);

        if (! $user || ! $isInternal || ! Hash::check($credentials['password'], $user->password)) {
            $this->recordLogin($request, InternalLoginLog::EVENT_LOGIN_FAILED, $user, $email, [
                'reason' => ! $isInternal ? 'not_internal' : 'invalid_credentials',
            ]);

            return back()->withErrors([
                'email' => 'Identifiants invalides pour le personnel interne OVANIE.',
            ])->withInput($request->except('password'));
        }

        if ((string) $user->status !== 'active') {
            $this->recordLogin($request, InternalLoginLog::EVENT_LOGIN_FAILED, $user, $email, [
                'reason' => 'account_'.$user->status,
            ]);

            return back()->withErrors([
                'email' => $user->status === 'suspended'
                    ? 'Ce compte professionnel est suspendu. Contactez un administrateur OVANIE.'
                    : 'Ce compte professionnel est inactif. Contactez un administrateur OVANIE.',
            ])->withInput($request->except('password'));
        }

        if ($user->staffProfile && ! $user->staffProfile->is_active) {
            $this->recordLogin($request, InternalLoginLog::EVENT_LOGIN_FAILED, $user, $email, [
                'reason' => 'profile_disabled',
            ]);

            return back()->withErrors([
                'email' => 'L’accès professionnel de ce compte est désactivé.',
            ])->withInput($request->except('password'));
        }

        Auth::guard('admin')->login($user, $request->boolean('remember'));
        Auth::shouldUse('admin');
        $request->session()->regenerate();

        if ($user->staffProfile) {
            $user->staffProfile->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $this->recordLogin($request, InternalLoginLog::EVENT_LOGIN_SUCCESS, $user, $email);

        return redirect()->intended(route($this->dashboardRoute($user)));
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            $this->recordLogin($request, InternalLoginLog::EVENT_LOGOUT, $user, $user->email);
        }

        Auth::guard('admin')->logout();

        // Ne pas invalider toute la session : un compte Client/Vendeur utilisant
        // le guard web peut rester connecté dans le même navigateur.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.adminlogin')
            ->with('success', 'Vous êtes déconnecté de l’espace interne OVANIE.');
    }

    private function dashboardRoute(User $user): string
    {
        if ((bool) $user->is_admin || $user->role === 'admin') {
            return 'admin.dashboard';
        }

        return match ($user->role) {
            'logistique' => 'logistics.dashboard',
            'support' => 'support.dashboard',
            'commercial' => 'commercial.dashboard',
            default => 'admin.adminlogin',
        };
    }

    private function recordLogin(
        Request $request,
        string $event,
        ?User $user,
        ?string $attemptedEmail,
        array $metadata = []
    ): void {
        if (! class_exists(InternalLoginLog::class)) {
            return;
        }

        try {
            InternalLoginLog::create([
                'user_id' => $user?->id,
                'event' => $event,
                'attempted_email' => $attemptedEmail,
                'role' => $user?->role,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable) {
            // La connexion ne doit pas être bloquée si la migration des journaux
            // n'a pas encore été exécutée sur une installation existante.
        }
    }
}
