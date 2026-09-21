<?php

return [
    /*
     * La simulation écrit de vraies positions dans la base. Elle doit donc
     * rester désactivée, sauf pendant un test local explicitement demandé.
     */
    'local_simulation' => filter_var(
        env('DELIVERY_LOCAL_SIMULATION', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /* Fréquence du polling des écrans client et logistique. */
    'client_poll_seconds' => max(5, (int) env('DELIVERY_CLIENT_POLL_SECONDS', 12)),
    'logistics_poll_seconds' => max(5, (int) env('DELIVERY_LOGISTICS_POLL_SECONDS', 10)),

    /* Seuils d'état du signal GPS. */
    'gps_active_seconds' => max(30, (int) env('DELIVERY_GPS_ACTIVE_SECONDS', 90)),
    'gps_weak_seconds' => max(60, (int) env('DELIVERY_GPS_WEAK_SECONDS', 180)),
    'gps_lost_seconds' => max(120, (int) env('DELIVERY_GPS_LOST_SECONDS', 300)),

    /* Présence livreur hors mission : l'app envoie un heartbeat GPS périodique. */
    'driver_presence_online_seconds' => max(30, (int) env('DELIVERY_DRIVER_PRESENCE_ONLINE_SECONDS', 120)),
    'driver_presence_heartbeat_seconds' => max(20, (int) env('DELIVERY_DRIVER_PRESENCE_HEARTBEAT_SECONDS', 45)),

    /* Le client ne reçoit jamais une position trop ancienne ou une phase de collecte. */
    'client_gps_max_age_seconds' => max(60, (int) env('DELIVERY_CLIENT_GPS_MAX_AGE_SECONDS', 180)),

    /* Contraintes de caméra : aucune vue pays/continent sur le suivi client. */
    'client_map_min_zoom' => max(9.5, (float) env('DELIVERY_CLIENT_MAP_MIN_ZOOM', 11)),
    'client_map_max_span_km' => max(10, (float) env('DELIVERY_CLIENT_MAP_MAX_SPAN_KM', 35)),

    /* Stabilisation des petites oscillations GPS lorsque le véhicule est immobile. */
    'gps_jitter_radius_cap_m' => max(10, (int) env('DELIVERY_GPS_JITTER_RADIUS_CAP_M', 60)),
    'gps_max_accuracy_m' => max(20, (int) env('DELIVERY_GPS_MAX_ACCURACY_M', 60)),

    /* Navigation temps réel de la mission vendeur. */
    'route_refresh_seconds' => max(8, (int) env('DELIVERY_ROUTE_REFRESH_SECONDS', 12)),
    'route_deviation_threshold_m' => max(20, (int) env('DELIVERY_ROUTE_DEVIATION_THRESHOLD_M', 30)),
    'route_deviation_cooldown_seconds' => max(5, (int) env('DELIVERY_ROUTE_DEVIATION_COOLDOWN_SECONDS', 8)),
    'arrival_radius_m' => max(10, (int) env('DELIVERY_ARRIVAL_RADIUS_M', 20)),
    'arrival_max_accuracy_m' => max(15, (int) env('DELIVERY_ARRIVAL_MAX_ACCURACY_M', 35)),
    'arrival_confirmation_fixes' => max(2, (int) env('DELIVERY_ARRIVAL_CONFIRMATION_FIXES', 2)),
];
