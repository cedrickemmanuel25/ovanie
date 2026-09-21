<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Commission marketplace
    |--------------------------------------------------------------------------
    |
    | Taux ajouté au prix vendeur pour produire le prix public OVANIE.
    | 0.05 = 5 %. Tous les calculs catalogue/panier/checkout utilisent ce taux.
    |
    */
    'default_commission_rate' => (float) env('MARKETPLACE_DEFAULT_COMMISSION_RATE', 0.05),
];
