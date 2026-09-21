<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddCspHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        $csp = implode('; ', [
            "default-src 'self' https://{$host}",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://api.mapbox.com",
            "img-src 'self' data: blob: https://{$host} https://ngrok.com https://*.ngrok-free.dev https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://api.mapbox.com https://*.mapbox.com",
            "connect-src 'self' https://{$host} https://*.ngrok-free.dev https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://api.mapbox.com https://*.mapbox.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://api.mapbox.com",
            "worker-src 'self' blob:",
            "frame-ancestors 'none'",
        ]) . ';';

        $response = $next($request);

        return $response->header('Content-Security-Policy', $csp);
    }
}
