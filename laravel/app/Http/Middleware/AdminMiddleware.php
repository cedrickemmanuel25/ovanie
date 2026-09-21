<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Utilise explicitement le guard 'admin'
        if (!Auth::guard('admin')->check() || !Auth::guard('admin')->user()->is_admin) {
            // Redirige vers la page de login admin si non connecté
            return redirect()->route('admin.adminlogin')
                ->with('error', 'Vous devez être connecté en tant qu’administrateur.');
        }

        return $next($request);
    }
}
