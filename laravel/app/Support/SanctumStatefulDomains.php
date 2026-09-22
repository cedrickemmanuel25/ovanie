<?php

namespace App\Support;

class SanctumStatefulDomains
{
    /**
     * Fusionne la liste SANCTUM_STATEFUL_DOMAINS de .env avec les hôtes de
     * développement local, toujours inclus.
     *
     * Bug corrigé : un .env local qui ne liste que le(s) domaine(s) réel(s)
     * (ex. copie d'un .env de production) fait échouer
     * EnsureFrontendRequestsAreStateful::fromFrontend() pour toute requête
     * fetch() du navigateur envoyée depuis un `php artisan serve` local vers
     * une route /api/* : aucune session ne charge, donc auth:sanctum répond
     * 401 "Non authentifié" même pour un utilisateur pourtant bien connecté
     * (repéré via l'assistant IA, qui parle uniquement à des routes API).
     * Ajouter systématiquement les hôtes locaux n'a aucun effet en
     * production, jamais accédée via ces noms d'hôte littéraux.
     *
     * @return list<string>
     */
    public static function merge(string $envValue, array $required): array
    {
        $configured = array_filter(array_map('trim', explode(',', $envValue)));

        return array_values(array_unique(array_merge($configured, $required)));
    }
}
