<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth::guard('admin')->check()) {
            return redirect()->route('admin.adminlogin'); // Redirection vers login admin
        }

        return $next($request);
    }
}
