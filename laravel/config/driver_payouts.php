<?php

return [
    /*
     | Mode de versement des gains livreurs, juste après chaque livraison :
     | - paydunya   : virement Mobile Money réel (PAYDUNYA_MODE=live obligatoire) ;
     | - simulation : aucun argent ne part, le versement est marqué effectué (tests) ;
     | - manual     : la Logistique verse elle-même puis marque le versement.
     */
    'execution_mode' => strtolower((string) env(
        'DRIVER_PAYOUT_EXECUTION_MODE',
        strtolower((string) env('PAYDUNYA_MODE', 'test')) === 'live' ? 'paydunya' : 'simulation'
    )),

    // Au-delà de ce montant d'espèces dues à OVANIE, le livreur ne reçoit plus de mission.
    'cash_limit' => (float) env('DRIVER_CASH_LIMIT', 50000),
];
