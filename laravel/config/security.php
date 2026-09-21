<?php

return [
    'admin_registration_enabled' => (bool) env('ADMIN_REGISTRATION_ENABLED', false),
    'admin_registration_password' => env('ADMIN_REGISTER_PASSWORD'),
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=(self)'),
        'hsts_max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000),

        /*
         | Active le CSP seulement après test, car un CSP trop strict peut bloquer
         | Tailwind/Vite, images produits, scripts de paiement ou widgets externes.
         */
        'csp_enabled' => env('SECURITY_CSP_ENABLED', false),
        'content_security_policy' => env(
            'SECURITY_CSP',
            "default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; connect-src 'self' https:; frame-src 'self' https:"
        ),
    ],

    'uploads' => [
        'max_image_kb' => env('SECURITY_MAX_IMAGE_KB', 5120),
        'max_pdf_kb' => env('SECURITY_MAX_PDF_KB', 10240),
        'allowed_image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_document_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],
];
