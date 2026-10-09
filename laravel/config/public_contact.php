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

    // Adresse physique officielle.
    'address' => env('OVANIE_PUBLIC_ADDRESS', 'Feh Kessé, Bingerville, Abidjan, Côte d’Ivoire'),
    'address_short' => env('OVANIE_PUBLIC_ADDRESS_SHORT', 'Feh Kessé, Bingerville'),

    // Disponibilité de l'assistance (affichée dans le footer, la page Contact et les bandeaux).
    'hours' => env('OVANIE_PUBLIC_HOURS', 'Assistance 7j/7'),
    'hours_detail' => env('OVANIE_PUBLIC_HOURS_DETAIL', 'Tous les jours, du lundi au dimanche'),

    'whatsapp_number' => $whatsappNumber,
    'whatsapp_label' => env('OVANIE_PUBLIC_WHATSAPP_LABEL', 'Discutez avec un conseiller'),
    'whatsapp_message' => $message,
    'whatsapp_url' => 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($message),
];
