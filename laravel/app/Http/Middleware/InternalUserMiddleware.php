<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InternalUserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $user = $guard->user();

        $isInternal = $user
            && in_array((string) $user->role, config('staff.internal_roles', []), true);

        $profileEnabled = ! $user?->staffProfile || (bool) $user->staffProfile->is_active;
        $accountEnabled = $user && (string) $user->status === 'active';

        if (! $isInternal || ! $profileEnabled || ! $accountEnabled) {
            if ($guard->check()) {
                $guard->logout();
            }

            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->regenerateToken();
            }

            $message = ! $accountEnabled || ! $profileEnabled
                ? 'Votre compte professionnel est inactif ou suspendu. Contactez un administrateur OVANIE.'
                : 'Utilisez un compte du personnel interne OVANIE.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('admin.adminlogin')->withErrors(['email' => $message]);
        }

        Auth::shouldUse('admin');

        return $next($request);
    }
}
