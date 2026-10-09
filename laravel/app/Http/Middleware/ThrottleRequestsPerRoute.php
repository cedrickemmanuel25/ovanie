<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Limitation de débit avec un compteur par route.
 *
 * Par défaut, Laravel identifie un visiteur non connecté par son adresse IP
 * seule : toutes les routes « throttle » partagent alors le même compteur.
 * Sur un téléphone qui fait tourner plusieurs applications OVANIE (même IP),
 * les appels réguliers du catalogue ou de la synchronisation consommaient le
 * quota de la connexion et bloquaient le login (« Too Many Attempts »).
 *
 * Chaque route garde exactement sa limite (ex. 10 connexions/min par IP),
 * mais son compteur ne dépend plus des autres écrans.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $signature = parent::resolveRequestSignature($request);
        $route = $request->route();
        $scope = $route?->getName() ?: $request->method().' '.($route?->uri() ?? $request->path());

        return sha1($signature.'|'.$scope);
    }
}
