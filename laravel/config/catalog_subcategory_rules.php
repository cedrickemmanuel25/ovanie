<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reclassement des anciens produits OVANIE
    |--------------------------------------------------------------------------
    | Les clés sont les slugs des sous-catégories. Les expressions servent
    | uniquement à proposer/reclasser les produits encore rattachés à une
    | catégorie principale. Une proposition ambiguë n'est jamais appliquée.
    */
    'minimum_score' => 60,
    'minimum_margin' => 20,

    'keywords' => [
        // Matériaux gros œuvre
        'ciment' => ['ciment', 'liant hydraulique', 'mortier ciment'],
        'fer-a-beton' => ['fer a beton', 'acier beton', 'barre acier', 'rond beton', 'rond a beton'],
        'gravier' => ['gravier', 'grave', 'concasse', 'granulat'],
        'sable' => ['sable'],
        'briques' => ['brique', 'briques'],
        'blocs-beton' => ['bloc beton', 'parpaing', 'parpaings'],
        'agglos' => ['agglo', 'agglos', 'agglomere'],
        'hourdis' => ['hourdis', 'entrevous'],
        'treillis-soudes' => ['treillis soude', 'treillis soudes'],
        'chaux' => ['chaux'],
        'beton-pret-a-lemploi' => ['beton pret', 'beton pret a emploi', 'ready mix'],
        'etancheite-gros-oeuvre' => ['etancheite', 'membrane bitumineuse', 'bitume', 'waterproof'],

        // Matériaux écologiques
        'briques-ecologiques' => ['brique ecologique', 'briques ecologiques'],
        'blocs-de-terre-comprimee' => ['bloc terre comprimee', 'btc', 'terre comprimee'],
        'peintures-ecologiques' => ['peinture ecologique', 'peinture naturelle'],
        'enduits-naturels' => ['enduit naturel', 'enduits naturels', 'enduit terre', 'enduit chaux'],
        'isolants-ecologiques' => ['isolant ecologique', 'isolation ecologique', 'laine bois', 'fibre bois', 'ouate cellulose'],
        'bois-traites-ecologiques' => ['bois traite ecologique', 'bois ecologique'],
        'revetements-recycles' => ['revetement recycle', 'revetements recycles'],
        'materiaux-recycles' => ['materiau recycle', 'materiaux recycles'],
        'solutions-de-construction-durable' => ['construction durable', 'solution durable'],
        'produits-basse-consommation' => ['basse consommation', 'economique energie'],

        // Outillage & équipement
        'outillage-a-main' => ['marteau', 'tournevis', 'cle plate', 'cle a molette', 'pince', 'burin', 'truelle', 'scie a main', 'lime', 'ciseau'],
        'outillage-electroportatif' => ['visseuse', 'meuleuse', 'ponceuse', 'perforateur', 'marteau piqueur', 'outil electroportatif'],
        'echelles-escabeaux' => ['echelle', 'escabeau'],
        'equipements-de-chantier' => ['echafaudage', 'betonniere', 'brouette', 'equipement chantier'],
        'equipements-de-protection-epi' => ['epi', 'casque chantier', 'gilet securite', 'chaussure securite', 'gant protection', 'lunette protection', 'harnais securite'],
        'machines-de-chantier' => ['compacteur', 'plaque vibrante', 'mini pelle', 'pelleteuse', 'chargeuse', 'bulldozer', 'machine chantier'],
        'mesure-tracage' => ['metre ruban', 'telemetre', 'niveau laser', 'niveau a bulle', 'laser', 'equerre', 'cordeau traceur'],
        'coupe-percage' => ['perceuse', 'foret', 'meche', 'disque diamant', 'disque coupe', 'scie circulaire', 'scie sauteuse', 'carotteuse', 'coupe carreau', 'coupeuse'],
        'soudure' => ['poste a souder', 'soudure', 'soudeuse', 'electrode soudure', 'masque soudure'],
        'nettoyage-chantier' => ['nettoyeur haute pression', 'aspirateur chantier', 'balayeuse', 'nettoyage chantier'],
        'levage-manutention' => ['palan', 'treuil', 'cric', 'transpalette', 'diable', 'chariot manutention', 'leve charge', 'levage', 'manutention'],
        'quincaillerie' => ['vis', 'clou', 'cheville', 'ecrou', 'boulon', 'charniere', 'serrure', 'quincaillerie'],

        // Matériaux de finition
        'carrelage' => ['carrelage', 'carreau', 'gres cerame'],
        'faience' => ['faience'],
        'peinture' => ['peinture', 'laque', 'sous couche'],
        'enduits-platre' => ['enduit', 'platre', 'placo', 'plaque platre'],
        'revetements-muraux' => ['revetement mural', 'papier peint', 'lambris mural'],
        'revetements-de-sol' => ['revetement sol', 'parquet', 'sol pvc', 'vinyle', 'moquette'],
        'faux-plafonds' => ['faux plafond', 'dalle plafond', 'plafond suspendu'],
        'portes-interieures' => ['porte interieure', 'porte interieur'],
        'fenetres' => ['fenetre', 'fenetres'],
        'sanitaires-de-finition' => ['lavabo', 'vasque', 'baignoire', 'douche', 'sanitaire'],
        'robinetterie' => ['robinet', 'mitigeur', 'melangeur', 'robinetterie'],
        'decoration-interieure' => ['decoration', 'moulure', 'corniche decorative'],

        // Énergie solaire
        'panneaux-solaires' => ['panneau solaire', 'module solaire', 'panneau photovoltaique'],
        'batteries-solaires' => ['batterie solaire', 'batterie lithium', 'batterie gel'],
        'onduleurs-solaires' => ['onduleur solaire', 'inverter solaire'],
        'regulateurs' => ['regulateur solaire', 'controleur charge', 'mppt', 'pwm'],
        'kits-solaires' => ['kit solaire'],
        'lampadaires-solaires' => ['lampadaire solaire'],
        'projecteurs-solaires' => ['projecteur solaire'],
        'pompes-solaires' => ['pompe solaire', 'pompage solaire'],
        'accessoires-de-fixation-solaire' => ['fixation solaire', 'rail panneau solaire', 'support panneau solaire'],
        'cables-solaires' => ['cable solaire', 'cable photovoltaique'],
        'coffrets-de-protection-solaire' => ['coffret solaire', 'protection solaire', 'coffret dc'],

        // Électricité & plomberie
        'cables-fils-electriques' => ['cable electrique', 'fil electrique', 'cable cuivre'],
        'disjoncteurs' => ['disjoncteur'],
        'tableaux-electriques' => ['tableau electrique', 'coffret electrique'],
        'prises-interrupteurs' => ['prise electrique', 'interrupteur'],
        'luminaires' => ['luminaire', 'ampoule', 'spot led', 'reglette led'],
        'gaines-conduits' => ['gaine electrique', 'conduit electrique', 'tube iro'],
        'protection-electrique' => ['parafoudre', 'differentiel', 'protection electrique'],
        'accessoires-electriques' => ['domino electrique', 'borne electrique', 'accessoire electrique'],
        'tuyaux' => ['tuyau', 'tube pvc', 'tube pehd', 'tube ppr'],
        'raccords' => ['raccord', 'coude pvc', 'te pvc', 'manchon'],
        'vannes' => ['vanne'],
        'robinets' => ['robinet'],
        'eviers-lavabos' => ['evier', 'lavabo'],
        'wc-sanitaires' => ['wc', 'toilette', 'cuvette wc'],
        'pompes-a-eau' => ['pompe a eau', 'surpresseur'],
        'reservoirs' => ['reservoir eau', 'cuve eau', 'citerne eau'],
        'accessoires-plomberie' => ['accessoire plomberie', 'siphon', 'flexible plomberie'],

        // Reconditionnés
        'groupes-electrogenes-reconditionnes' => ['groupe electrogene', 'generateur'],
        'outillage-reconditionne' => ['perceuse', 'meuleuse', 'visseuse', 'perforateur', 'outillage'],
        'materiel-electrique-reconditionne' => ['materiel electrique', 'onduleur', 'tableau electrique'],
        'equipements-solaires-reconditionnes' => ['panneau solaire', 'batterie solaire', 'equipement solaire'],
        'pompes-reconditionnees' => ['pompe'],
        'machines-de-chantier-reconditionnees' => ['machine chantier', 'betonniere', 'compacteur', 'mini pelle'],
        'accessoires-reconditionnes' => ['accessoire'],
    ],
];
