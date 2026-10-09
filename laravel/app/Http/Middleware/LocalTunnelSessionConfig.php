<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilise les sessions Laravel pendant les tests locaux, LAN et tunnels.
 *
 * Ce middleware s'exécute AVANT StartSession. Il neutralise notamment les
 * cookies Secure/domain hérités de la production qui provoqueraient un 419
 * sur http://ovanie.test:8000 ou http://pro.ovanie.test:8000.
 */
class LocalTunnelSessionConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(trim((string) $request->getHost()));

        if (! $this->isLocalTestHost($host)) {
            return $next($request);
        }

        $sessionDirectory = storage_path('framework/sessions');
        if (! is_dir($sessionDirectory)) {
            @mkdir($sessionDirectory, 0775, true);
        }

        $viewsDirectory = storage_path('framework/views');
        if (! is_dir($viewsDirectory)) {
            @mkdir($viewsDirectory, 0775, true);
        }

        $cacheDirectory = storage_path('framework/cache/data');
        if (! is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0775, true);
        }

        $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        $secure = $request->isSecure() || str_contains($forwardedProto, 'https');

        $proDomain = strtolower(trim((string) config('ovanie.pro_domain', 'pro.ovanie.test')));
        $isProHost = $host === $proDomain;

        config([
            'session.driver' => 'file',
            'session.files' => $sessionDirectory,
            'session.domain' => null,
            'session.path' => '/',
            // OVANIE classique et OVANIE Pro gardent des sessions distinctes
            // même pendant les tests locaux.
            'session.cookie' => $isProHost
                ? config('ovanie.pro_session_cookie', 'ovanie_pro_local_session')
                : 'ovanie_local_session',
            'session.http_only' => true,
            'session.same_site' => 'lax',
            // HTTP local => false. HTTPS/tunnel HTTPS => true.
            'session.secure' => $secure,
        ]);

        return $next($request);
    }

    private function isLocalTestHost(string $host): bool
    {
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        // Domaines de développement utilisés par OVANIE.
        if (str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (str_ends_with($host, '.trycloudflare.com')) {
            return true;
        }

        // RFC1918 IPv4 : 10/8, 172.16/12, 192.168/16.
        if (preg_match('/^10\./', $host) === 1 || preg_match('/^192\.168\./', $host) === 1) {
            return true;
        }

        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host) === 1) {
            return true;
        }

        return false;
    }
}
