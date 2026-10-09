<?php

return [
    /*
    | Synchronisation web ↔ mobile (GET /live-sync) : intervalle conseillé
    | aux clients entre deux vérifications, en millisecondes.
    */
    'live_sync_poll_ms' => (int) env('OVANIE_LIVE_SYNC_POLL_MS', 8000),

    /*
    |--------------------------------------------------------------------------
    | Domaines OVANIE
    |--------------------------------------------------------------------------
    |
    | OVANIE et OVANIE Pro utilisent le même code Laravel mais sont deux
    | plateformes fonctionnelles distinctes. Leur authentification et leurs
    | utilisateurs ne sont pas partagés.
    |
    */
    'main_domain' => env('OVANIE_MAIN_DOMAIN', 'ovanie.com'),
    'pro_domain' => env('OVANIE_PRO_DOMAIN', 'pro.ovanie.com'),
    'pro_url' => env('OVANIE_PRO_URL', 'https://pro.ovanie.com'),

    /*
    |--------------------------------------------------------------------------
    | Session OVANIE Pro isolée
    |--------------------------------------------------------------------------
    |
    | Même si le domaine principal possède un cookie valable sur .ovanie.com,
    | OVANIE Pro utilise un nom de cookie différent. Une session OVANIE ne peut
    | donc jamais authentifier automatiquement un compte OVANIE Pro.
    |
    */
    'pro_session_cookie' => env('OVANIE_PRO_SESSION_COOKIE', 'ovanie_pro_session'),
];
