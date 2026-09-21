<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilise les sessions Laravel pendant les tests locaux depuis un téléphone.
 *
 * Cette protection s'active uniquement pour localhost, une adresse IP privée
 * ou un Quick Tunnel *.trycloudflare.com. Elle n'altère donc pas la
 * configuration des domaines OVANIE de production.
 *
 * Elle s'exécute AVANT StartSession afin d'éviter les 419 provoqués par une
 * ancienne configuration mise en cache (SESSION_DOMAIN=127.0.0.1, cookie de
 * production, etc.).
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
        $isHttpsTunnel = str_ends_with($host, '.trycloudflare.com')
            && (str_contains($forwardedProto, 'https') || $request->isSecure());

        config([
            'session.driver' => 'file',
            'session.files' => $sessionDirectory,
            // Host-only cookie : ne jamais l'attacher à 127.0.0.1 ou ovanie.com
            // lorsque la requête arrive depuis trycloudflare.com.
            'session.domain' => null,
            'session.path' => '/',
            'session.cookie' => 'ovanie-v60-local-session',
            'session.http_only' => true,
            'session.same_site' => 'lax',
            // HTTPS sur le tunnel ; HTTP autorisé uniquement pour le LAN local.
            'session.secure' => $isHttpsTunnel,
        ]);

        return $next($request);
    }

    private function isLocalTestHost(string $host): bool
    {
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
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
