<?php

namespace App\Http\Middleware;

use App\Support\AccountSpace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'espace client est réservé aux comptes clients : un vendeur ou un membre de l'équipe
 * qui y arrive (lien « Mon tableau de bord », ancien favori) est renvoyé vers son propre espace.
 */
class EnsureClientSpace
{
    public function handle(Request $request, Closure $next): Response
    {
        $space = AccountSpace::current();

        if ($space && $space['key'] !== 'client' && $request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->to($space['url']);
        }

        return $next($request);
    }
}
