<?php

return [
    // 1 point gagné pour chaque tranche de 1 000 FCFA de produits payés et reçus.
    'earn_every_xof' => (int) env('LOYALTY_EARN_EVERY_XOF', 1000),

    // Valeur de réduction d'un point utilisé au checkout.
    'point_value_xof' => (int) env('LOYALTY_POINT_VALUE_XOF', 10),

    // Plafond de réduction fidélité calculé sur le sous-total des produits (hors livraison).
    'max_redemption_percent' => (int) env('LOYALTY_MAX_REDEMPTION_PERCENT', 20),
];
