<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    /**
     * Affiche le formulaire de connexion (web).
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Traite la connexion depuis le formulaire web.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = auth()->user();

        // 🔐 ADMIN PRIORITAIRE
        if ($user->is_admin) {
            return redirect()->intended(route('admin.dashboard'));
        }

        // vendeur
        if ($user->shop) {
            return redirect()->route('vendor.dashboard');
        }

        // logistique
        if ($user->role === 'logistique') {
            return redirect()->route('logistics.dashboard');
        }

        // client
        return redirect()->route('home');
    }

    /**
     * Connexion via API JSON
     */
    public function loginApi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $identifier = trim($data['identifier']);
        $password = $data['password'];

        $throttleKey = $this->throttleKey($identifier, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez plus tard.',
                'retry_after' => $seconds,
            ], 429);
        }

        // email ou téléphone
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $credentials = [$field => $identifier, 'password' => $password];

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey);

            return response()->json([
                'message' => 'Identifiants invalides.'
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = Auth::user()->makeHidden(['password', 'remember_token']);

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => $user,
        ], 200);
    }

    /**
     * Retourne l'utilisateur connecté (API)
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(null, 204);
        }

        $user = $user->makeHidden(['password', 'remember_token']);

        return response()->json(['user' => $user], 200);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Déconnecté.'], 200);
        }

        return redirect()->route('login');
    }

    /**
     * Génère la clé throttle
     */
    protected function throttleKey(string $identifier, ?string $ip = null): string
    {
        $id = Str::lower($identifier);
        $ipPart = $ip ?? request()->ip() ?? 'unknown';

        return Str::transliterate("login|{$id}|{$ipPart}");
    }

    /**
     * Méthode destroy alternative (si utilisée dans routes)
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
