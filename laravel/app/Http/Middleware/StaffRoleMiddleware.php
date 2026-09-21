<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StaffRoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Les routes web internes utilisent le guard admin. Les API Sanctum
        // continuent d'utiliser l'utilisateur résolu par la requête.
        $user = Auth::guard('admin')->user() ?: $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Accès réservé au personnel OVANIE autorisé.');
        }

        if ((string) $user->status !== 'active' || ($user->staffProfile && ! $user->staffProfile->is_active)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Votre accès professionnel OVANIE est désactivé.'], 403);
            }

            Auth::guard('admin')->logout();
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('admin.adminlogin')->withErrors([
                'email' => 'Votre accès professionnel OVANIE est désactivé.',
            ]);
        }

        $requiredPermission = $this->requiredPermission((string) $request->route()?->getName());
        if ($requiredPermission && ! $user->hasStaffPermission($requiredPermission)) {
            abort(403, 'Votre compte ne possède pas la permission nécessaire pour cette action.');
        }

        if ($user->staffProfile) {
            $user->staffProfile->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function requiredPermission(string $routeName): ?string
    {
        foreach (config('staff.route_permissions', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $permission;
            }
        }

        return null;
    }
}
