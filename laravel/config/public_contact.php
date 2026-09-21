<?php

$message = (string) env(
    'OVANIE_PUBLIC_WHATSAPP_MESSAGE',
    'Bonjour OVANIE, j’ai besoin d’assistance.'
);

$whatsappNumber = preg_replace(
    '/\D+/',
    '',
    (string) env('OVANIE_PUBLIC_WHATSAPP_NUMBER', '2250161781818')
) ?: '2250161781818';

return [
    /*
    |--------------------------------------------------------------------------
    | Coordonnées publiques officielles OVANIE
    |--------------------------------------------------------------------------
    |
    | Les pages publiques doivent utiliser uniquement ces valeurs afin
    | d'éviter la coexistence de plusieurs numéros/e-mails dans le site.
    |
    */
    'phone_display' => env('OVANIE_PUBLIC_PHONE_DISPLAY', '01 61 78 00 00'),
    'phone_e164' => env('OVANIE_PUBLIC_PHONE_E164', '+2250161780000'),
    'email' => env('OVANIE_PUBLIC_EMAIL', 'contact@ovanie.com'),

    'whatsapp_number' => $whatsappNumber,
    'whatsapp_label' => env('OVANIE_PUBLIC_WHATSAPP_LABEL', 'Discutez avec un conseiller'),
    'whatsapp_message' => $message,
    'whatsapp_url' => 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($message),
];
