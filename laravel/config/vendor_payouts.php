<?php

return [
    // Modes :
    // - simulation : aucun argent réel ;
    // - manual     : un administrateur confirme un transfert réalisé hors OVANIE ;
    // - paydunya   : OVANIE déclenche automatiquement l'API de déboursement PayDunya.
    // Par sécurité, la production reste MANUELLE tant que l'option n'est pas changée explicitement.
    'execution_mode' => env(
        'VENDOR_PAYOUT_EXECUTION_MODE',
        strtolower((string) env('PAYDUNYA_MODE', 'test')) === 'live' ? 'manual' : 'simulation'
    ),
    'require_reference_in_live' => (bool) env('VENDOR_PAYOUT_REQUIRE_REFERENCE_IN_LIVE', true),
    'auto_batch_limit' => (int) env('VENDOR_PAYOUT_AUTO_BATCH_LIMIT', 25),

    // Paiement rapide : 72 h après éligibilité du reversement.
    'post_delivery_delay_hours' => (int) env('VENDOR_PAYOUT_POST_DELIVERY_HOURS', 72),

    // Les 90 premiers jours du paiement rapide sont sans frais de traitement.
    'post_delivery_free_days' => (int) env('VENDOR_PAYOUT_FREE_DAYS', 90),
    'post_delivery_processing_fee_rate' => (float) env('VENDOR_PAYOUT_PROCESSING_FEE_RATE', 0.05),

    // Paiement hebdomadaire : vendredi à 17 h, fuseau de l'application.
    'weekly_iso_day' => (int) env('VENDOR_PAYOUT_WEEKLY_ISO_DAY', 5),
    'weekly_hour' => (int) env('VENDOR_PAYOUT_WEEKLY_HOUR', 17),
    'weekly_minute' => (int) env('VENDOR_PAYOUT_WEEKLY_MINUTE', 0),
];
