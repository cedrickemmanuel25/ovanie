<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fenêtre de retour client
    |--------------------------------------------------------------------------
    |
    | Nombre de jours calendaires pendant lesquels un retour ou une demande de
    | remboursement peut être ouverte après la livraison réelle de l'article.
    | Les réclamations de livraison restent traitées séparément.
    |
    */
    'return_window_days' => max(1, (int) env('RETURN_WINDOW_DAYS', 7)),

    /*
    |--------------------------------------------------------------------------
    | Communes considérées comme faisant partie de la zone Abidjan
    |--------------------------------------------------------------------------
    |
    | Cette liste permet d'éviter qu'une adresse « Cocody », « Anyama » ou
    | « Bingerville » soit classée à tort comme une livraison intérieure lorsque
    | le champ ville ne contient pas littéralement le mot « Abidjan ».
    |
    */
    'abidjan_communes' => [
        'Abobo',
        'Adjamé',
        'Anyama',
        'Attécoubé',
        'Bingerville',
        'Cocody',
        'Koumassi',
        'Marcory',
        'Plateau',
        'Port-Bouët',
        'Songon',
        'Treichville',
        'Yopougon',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alias officiels et variantes de saisie des communes
    |--------------------------------------------------------------------------
    |
    | Le GPS, Mapbox et Nominatim n'utilisent pas toujours les mêmes accents,
    | apostrophes ou tirets. Ces alias sont normalisés avant toute recherche de
    | tarif. Le quartier ne constitue jamais une zone obligatoire distincte.
    |
    */
    'abidjan_commune_aliases' => [
        'Abobo' => ['Abobo'],
        'Adjamé' => ['Adjamé', 'Adjame'],
        'Anyama' => ['Anyama'],
        'Attécoubé' => ['Attécoubé', 'Attecoube', 'Attecoubet'],
        'Bingerville' => ['Bingerville'],
        'Cocody' => ['Cocody'],
        'Koumassi' => ['Koumassi'],
        'Marcory' => ['Marcory'],
        'Plateau' => ['Plateau', 'Le Plateau'],
        'Port-Bouët' => ['Port-Bouët', 'Port Bouët', 'Port-Bouet', 'Port Bouet'],
        'Songon' => ['Songon'],
        'Treichville' => ['Treichville'],
        'Yopougon' => ['Yopougon'],
    ],

    // Ce dictionnaire améliore le résultat lorsque le fournisseur GPS retourne
    // seulement un quartier. Il n'est pas utilisé comme liste fermée : tout
    // quartier d'une commune reconnue reste desservi, même absent de cette liste.
    'abidjan_location_communes' => [
        // Cocody
        'riviera' => 'Cocody',
        'riviera palmeraie' => 'Cocody',
        'palmeraie' => 'Cocody',
        'angré' => 'Cocody',
        'angre' => 'Cocody',
        'bonoumin' => 'Cocody',
        'deux plateaux' => 'Cocody',
        '2 plateaux' => 'Cocody',
        'faya' => 'Cocody',
        'akouédo' => 'Cocody',
        'akouedo' => 'Cocody',
        'djorogobité' => 'Cocody',
        'djorogobite' => 'Cocody',
        'anono' => 'Cocody',
        'blockhauss' => 'Cocody',
        "m'pouto" => 'Cocody',
        'cite atci' => 'Cocody',
        'atci' => 'Cocody',

        // Yopougon
        'niangon' => 'Yopougon',
        'siporex' => 'Yopougon',
        'selmer' => 'Yopougon',
        'wassakara' => 'Yopougon',
        'gesco' => 'Yopougon',
        'toits rouges' => 'Yopougon',
        'andokoi' => 'Yopougon',
        'azito' => 'Yopougon',
        'maroc yopougon' => 'Yopougon',

        // Abobo
        'avocatier' => 'Abobo',
        'sagbé' => 'Abobo',
        'sagbe' => 'Abobo',
        "n'dotré" => 'Abobo',
        'n dotre' => 'Abobo',
        'pk 18' => 'Abobo',
        'pk18' => 'Abobo',
        'samaké' => 'Abobo',
        'samake' => 'Abobo',
        'anonkoua kouté' => 'Abobo',

        // Adjamé
        'williamsville' => 'Adjamé',
        '220 logements' => 'Adjamé',
        'bracodi' => 'Adjamé',
        'mirador' => 'Adjamé',

        // Attécoubé
        'locodjro' => 'Attécoubé',
        'abobodoumé' => 'Attécoubé',
        'abobodoume' => 'Attécoubé',
        'mossikro' => 'Attécoubé',

        // Marcory
        'zone 4' => 'Marcory',
        'zone quatre' => 'Marcory',
        'biétry' => 'Marcory',
        'bietry' => 'Marcory',
        'anoumabo' => 'Marcory',
        'hibiscus' => 'Marcory',

        // Koumassi
        'remblais' => 'Koumassi',
        'prodomo' => 'Koumassi',
        'campement koumassi' => 'Koumassi',

        // Port-Bouët
        'vridi' => 'Port-Bouët',
        'gonzagueville' => 'Port-Bouët',
        'adjouffou' => 'Port-Bouët',
        'akwaba' => 'Port-Bouët',
        'derrière wharf' => 'Port-Bouët',
        'derriere wharf' => 'Port-Bouët',

        // Treichville
        'belleville treichville' => 'Treichville',
        'arras' => 'Treichville',
        'biafra' => 'Treichville',

        // Anyama / Songon / Bingerville
        'ebimpé' => 'Anyama',
        'ebimpe' => 'Anyama',
        'songon dagbé' => 'Songon',
        'songon dagbe' => 'Songon',
        'songon kassemblé' => 'Songon',
        'songon kassemble' => 'Songon',
        'akandjé' => 'Bingerville',
        'akandje' => 'Bingerville',
    ],

];
