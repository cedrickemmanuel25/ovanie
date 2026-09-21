<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Empêche la mise en cache des pages HTML de l'espace logistique : plusieurs
 * navigateurs affichaient des versions différentes de la même page après des
 * mises à jour de l'interface (un a gardé une ancienne version en cache).
 */
class NoHtmlCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
