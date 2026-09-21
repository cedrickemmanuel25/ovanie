<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Ajoute des headers HTTP de sécurité sur les réponses web.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', config('security.headers.x_frame_options', 'SAMEORIGIN'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('Permissions-Policy', config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=(self)'));

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=' . config('security.headers.hsts_max_age', 31536000) . '; includeSubDomains'
            );
        }

        if (config('security.headers.csp_enabled', false)) {
            $response->headers->set('Content-Security-Policy', config('security.headers.content_security_policy'));
        }

        return $response;
    }
}
