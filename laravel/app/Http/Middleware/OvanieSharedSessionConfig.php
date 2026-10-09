<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nom historique conservé pour ne pas casser le bootstrap existant.
 *
 * Le comportement n'est plus un partage de session : le sous-domaine
 * OVANIE Pro reçoit un cookie de session propre et host-only.
 */
class OvanieSharedSessionConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(trim((string) $request->getHost()));
        $proDomain = strtolower(trim((string) config('ovanie.pro_domain', 'pro.ovanie.com')));

        if ($host === $proDomain) {
            $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
            $secure = $request->isSecure() || str_contains($forwardedProto, 'https');

            config([
                'session.domain' => null,
                'session.cookie' => config('ovanie.pro_session_cookie', 'ovanie_pro_session'),
                'session.path' => '/',
                'session.http_only' => true,
                'session.same_site' => 'lax',
                'session.secure' => $secure,
            ]);
        }

        return $next($request);
    }
}
