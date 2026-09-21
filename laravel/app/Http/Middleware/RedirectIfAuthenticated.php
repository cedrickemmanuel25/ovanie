<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            $user = Auth::guard($guard)->user();

            if ((string) ($user->status ?? '') !== 'active') {
                Auth::guard($guard)->logout();
                continue;
            }

            if ((bool) ($user->is_admin ?? false) || ($user->role ?? '') === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            if (($user->role ?? '') === 'logistique') {
                return redirect()->route('logistics.dashboard');
            }

            if (($user->role ?? '') === 'support') {
                return redirect()->route('support.dashboard');
            }

            if (($user->role ?? '') === 'commercial') {
                return redirect()->route('commercial.dashboard');
            }

            if (($user->role ?? '') === 'vendor') {
                return redirect()->route('vendor.dashboard');
            }

            return redirect()->route('home');
        }

        return $next($request);
    }
}
