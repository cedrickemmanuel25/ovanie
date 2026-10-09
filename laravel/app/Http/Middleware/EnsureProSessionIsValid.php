<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session OVANIE PRO : un compte désactivé perd immédiatement l'accès, et un changement
 * de mot de passe (ou « déconnecter mes autres sessions ») ferme les autres sessions.
 */
class EnsureProSessionIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('pro');
        $user = $guard->user();

        if ($user) {
            // Empreinte liée au compte : un changement de compte dans la session repart d'une empreinte neuve.
            $key = 'pro_password_hash_' . $user->getAuthIdentifier();
            $stored = $request->session()->get($key);

            if ($user->status !== 'active' || ($stored && ! hash_equals($stored, (string) $user->getAuthPassword()))) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('pro.login')->with('status', $user->status !== 'active'
                    ? 'Ce compte est désactivé. Contactez le support OVANIE Pro.'
                    : 'Votre session a été fermée. Reconnectez-vous.');
            }

            if (! $stored) {
                $request->session()->put($key, $user->getAuthPassword());
            }
        }

        return $next($request);
    }
}
