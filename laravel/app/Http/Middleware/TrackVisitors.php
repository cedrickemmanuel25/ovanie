<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackVisitors
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        // Ajouter le visiteur dans le cache pour 5 minutes
        Cache::put('visitor_online_' . $ip, true, now()->addMinutes(5));

        // Compter le nombre de visiteurs en ligne
        $onlineVisitors = collect(Cache::getRedis()->keys('visitor_online_*'))->count();
        Cache::put('online_visitors', $onlineVisitors, now()->addMinutes(5));

        return $next($request);
    }
}
