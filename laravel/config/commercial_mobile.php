<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Objectifs terrain affichés sur l'accueil de l'application Commercial
    |--------------------------------------------------------------------------
    |
    | Laisser vide dans .env pour ne pas inventer d'objectif. L'application
    | affichera "Non configuré". Une visite est comptée à partir des activités
    | commerciales de type "meeting" enregistrées par le commercial.
    |
    */
    'daily_visit_target' => env('COMMERCIAL_DAILY_VISIT_TARGET'),
    'monthly_visit_target' => env('COMMERCIAL_MONTHLY_VISIT_TARGET'),
];
