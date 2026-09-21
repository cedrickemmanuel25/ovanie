<?php

return [
    // Aucun secret n'est embarqué dans le code. En production, renseigner soit
    // un chemin vers un JSON de compte de service, soit le JSON lui-même.
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    'timeout' => (int) env('FIREBASE_PUSH_TIMEOUT', 15),
];
