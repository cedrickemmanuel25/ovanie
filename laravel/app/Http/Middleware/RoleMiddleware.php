<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $isInternalRoute = $request->is('administration')
            || $request->is('administration/*')
            || $request->is('admin')
            || $request->is('admin/*')
            || $request->is('support')
            || $request->is('support/*')
            || $request->is('commercial')
            || $request->is('commercial/*')
            || $request->is('logistique')
            || $request->is('logistique/*');

        $guardName = $isInternalRoute ? 'admin' : config('auth.defaults.guard', 'web');
        $user = Auth::guard($guardName)->user() ?: $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Accès interdit'], 403);
            }

            if ($isInternalRoute) {
                return redirect()->route('admin.adminlogin')->withErrors([
                    'email' => 'Accès interdit pour ce compte professionnel.',
                ]);
            }

            abort(403, 'Accès interdit');
        }

        return $next($request);
    }
}
