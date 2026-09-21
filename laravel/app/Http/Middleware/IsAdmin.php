<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $adminGuardUser = Auth::guard('admin')->user();
        $sanctumUser = $request->user();

        $isAdmin = ($adminGuardUser && $adminGuardUser->role === 'admin')
            || ($sanctumUser && $sanctumUser->role === 'admin');

        if (! $isAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Accès administrateur requis',
                ], 403);
            }

            return redirect()->route('admin.adminlogin');
        }

        return $next($request);
    }
}
