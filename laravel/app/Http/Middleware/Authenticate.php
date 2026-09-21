<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Important : permet à $request->user() et auth()->user()
                // d'utiliser le guard qui a réellement authentifié la route.
                Auth::shouldUse($guard ?: config('auth.defaults.guard'));

                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        if ($request->is('espace-livreur') || $request->is('espace-livreur/*') || in_array('driver', $guards, true)) {
            return redirect()->route('driver.login');
        }

        $isInternalPath = $request->is('administration')
            || $request->is('administration/*')
            || $request->is('admin')
            || $request->is('admin/*')
            || $request->is('support')
            || $request->is('support/*')
            || $request->is('commercial')
            || $request->is('commercial/*')
            || $request->is('logistique')
            || $request->is('logistique/*')
            || in_array('admin', $guards, true);

        if ($isInternalPath) {
            return redirect()->route('admin.adminlogin');
        }

        return redirect()->route('login');
    }
}
