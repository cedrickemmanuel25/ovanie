<?php

return [
    'country_code' => env('GEO_COUNTRY_CODE', 'CI'),
    'geocoding_provider' => env('GEOCODING_PROVIDER', 'mapbox'),

    'mapbox' => [
        'geocoding_token' => env('MAPBOX_GEOCODING_TOKEN', env('MAPBOX_TOKEN')),
        'public_token' => env('MAPBOX_PUBLIC_TOKEN', env('MAPBOX_TOKEN')),
        'language' => env('MAPBOX_LANGUAGE', 'fr'),
        'search_country' => env('MAPBOX_SEARCH_COUNTRY', 'CI'),
    ],

    'nominatim_base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
    'nominatim_user_agent' => env(
        'NOMINATIM_USER_AGENT',
        'OVANIE/1.0 (https://ovanie.com; contact@ovanie.com)'
    ),

    'fallbacks' => [
        'geocoding_provider' => env('GEOCODING_FALLBACK_PROVIDER', 'nominatim'),
    ],
];
