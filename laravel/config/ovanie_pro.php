<?php

return [
    'professional_profiles' => [
        'fabricant_producteur' => [
            'label' => 'Fabricant / Producteur',
            'short_label' => 'Fabricant',
            'description' => 'Usine, capacités, produits, ventes, export et RFQ.',
            'form_intro' => 'Présentez votre activité industrielle et les produits que vous fabriquez.',
            'business_label' => 'Raison sociale du fabricant',
            'activity_placeholder' => 'Ex. fabrication de ciment, menuiserie aluminium…',
            'icon' => 'factory',
            'benefits' => ['Usine et capacité de production', 'Catalogue fabricant', 'RFQ et marchés export'],
        ],
        'grossiste' => [
            'label' => 'Grossiste',
            'short_label' => 'Grossiste',
            'description' => 'Catalogue gros, lots, MOQ et export.',
            'form_intro' => 'Indiquez les familles de produits que vous commercialisez en gros et vos conditions minimales.',
            'business_label' => 'Raison sociale / nom commercial',
            'activity_placeholder' => 'Ex. distribution de matériaux de construction…',
            'icon' => 'boxes',
            'benefits' => ['Catalogue de gros', 'MOQ et lots', 'RFQ et export'],
        ],
        'demi_grossiste' => [
            'label' => 'Demi-grossiste',
            'short_label' => 'Demi-grossiste',
            'description' => 'Semi-gros et paliers de quantité.',
            'form_intro' => 'Décrivez vos produits de semi-gros et les paliers de quantité proposés.',
            'business_label' => 'Raison sociale / nom commercial',
            'activity_placeholder' => 'Ex. semi-gros quincaillerie, plomberie…',
            'icon' => 'store',
            'benefits' => ['Catalogue semi-gros', 'Paliers de quantité', 'Conditions commerciales'],
        ],
        'revendeur_professionnel' => [
            'label' => 'Revendeur professionnel',
            'short_label' => 'Revendeur',
            'description' => 'Achats B2B, RFQ et commandes.',
            'form_intro' => 'Précisez vos familles d’achats et les besoins professionnels que vous souhaitez sourcer.',
            'business_label' => 'Raison sociale / nom commercial',
            'activity_placeholder' => 'Ex. négoce, distribution, magasin BTP…',
            'icon' => 'tag',
            'benefits' => ['Achats B2B', 'Demandes de devis / RFQ', 'Suivi fournisseurs et commandes'],
        ],
        'entreprise_btp' => [
            'label' => 'Entreprise BTP',
            'short_label' => 'Entreprise BTP',
            'description' => 'Achats, consultations, appels d’offres et services.',
            'form_intro' => 'Présentez votre spécialité BTP et les types de chantiers ou projets que vous réalisez.',
            'business_label' => 'Raison sociale de l’entreprise',
            'activity_placeholder' => 'Ex. bâtiment, génie civil, VRD, second œuvre…',
            'icon' => 'building',
            'benefits' => ['Achats chantier', 'Consultations et appels d’offres', 'Suivi des commandes PRO'],
        ],
        'entrepreneur_artisan' => [
            'label' => 'Entrepreneur / Artisan professionnel',
            'short_label' => 'Artisan / Entrepreneur',
            'description' => 'Services, achats et sous-traitance.',
            'form_intro' => 'Indiquez votre métier, les services proposés et vos zones d’intervention.',
            'business_label' => 'Nom commercial / nom de l’activité',
            'activity_placeholder' => 'Ex. électricien, plombier, carreleur, maçon…',
            'icon' => 'hardhat',
            'benefits' => ['Services et interventions', 'Achats professionnels', 'Sous-traitance'],
        ],
        'promoteur_immobilier' => [
            'label' => 'Promoteur / Immobilier',
            'short_label' => 'Promoteur',
            'description' => 'Achats, marchés et RFQ.',
            'form_intro' => 'Présentez vos projets immobiliers et les zones dans lesquelles vous développez vos opérations.',
            'business_label' => 'Raison sociale du promoteur',
            'activity_placeholder' => 'Ex. promotion résidentielle, lotissement, immobilier tertiaire…',
            'icon' => 'home',
            'benefits' => ['Sourcing fournisseurs', 'Consultations / RFQ', 'Achats de projets'],
        ],
        'prestataire_btp' => [
            'label' => 'Prestataire BTP',
            'short_label' => 'Prestataire',
            'description' => 'Études, maintenance, installation, transport ou location.',
            'form_intro' => 'Précisez vos services, vos spécialités et vos zones d’intervention.',
            'business_label' => 'Raison sociale / nom du prestataire',
            'activity_placeholder' => 'Ex. maintenance, bureau d’études, transport chantier…',
            'icon' => 'wrench',
            'benefits' => ['Services BTP', 'Zones d’intervention', 'Devis et missions'],
        ],
    ],

    'business_roles' => [
        'buyer_pro' => [
            'label' => 'Acheteur PRO',
            'description' => 'Achats B2B, RFQ, appels d’offres, commandes et paiements.',
            'space' => 'ACHETEUR PRO',
            'requires_pro' => true,
        ],
        'seller_supplier' => [
            'label' => 'Vendeur / Fournisseur',
            'description' => 'Boutique, catalogue, prix, stock, RFQ reçues, commandes et ventes.',
            'space' => 'VENDEUR / FOURNISSEUR',
            'requires_pro' => true,
        ],
        'advertiser' => [
            'label' => 'Annonceur',
            'description' => 'Publier des offres simples selon les droits du compte.',
            'space' => 'ANNONCEUR',
            'requires_pro' => false,
        ],
        'requester' => [
            'label' => 'Demandeur',
            'description' => 'Publier des besoins simples et demandes professionnelles.',
            'space' => 'DEMANDEUR',
            'requires_pro' => false,
        ],
    ],

    // Code du drapeau (public/ovanie-pro/flags/xx.svg) de chaque pays proposé.
    'flags' => [
        'Côte d’Ivoire' => 'ci', 'Sénégal' => 'sn', 'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Ghana' => 'gh', 'Guinée' => 'gn',
        'Togo' => 'tg', 'Bénin' => 'bj', 'Niger' => 'ne', 'Cameroun' => 'cm', 'Nigeria' => 'ng', 'Maroc' => 'ma',
        'France' => 'fr', 'Chine' => 'cn', 'Turquie' => 'tr', 'Émirats arabes unis' => 'ae',
    ],

    // Listes proposées dans le formulaire « Informations métier ».
    'business_options' => [
        'positions' => ['Direction générale', 'Gérant', 'Direction commerciale', 'Responsable des achats', 'Responsable de production', 'Responsable administratif et financier', 'Autre'],
        'sectors' => ['Matériaux de construction', 'Ciment et liants', 'Béton et préfabriqués', 'Acier et métallurgie', 'Bois et menuiserie', 'Peintures et revêtements', 'Électricité et plomberie', 'Carrières et granulats', 'Équipements et machines', 'Autre'],
        'product_categories' => ['Ciment', 'Briques et parpaings', 'Tuyaux et canalisations', 'Béton prêt à l’emploi', 'Éléments préfabriqués', 'Granulats', 'Fer à béton', 'Tôles et toitures', 'Carreaux', 'Peintures', 'Menuiseries', 'Câbles électriques'],
        'equipment' => ['Concasseur à mâchoires', 'Mélangeur de béton', 'Presse hydraulique', 'Silo de stockage', 'Centrale à béton', 'Four rotatif', 'Broyeur', 'Convoyeur', 'Chariot élévateur', 'Pont roulant'],
        'incoterms' => ['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'],
        'certification_types' => ['ISO 9001', 'ISO 14001', 'ISO 45001', 'Norme nationale (CODINORM…)', 'Marquage CE', 'Autre'],
    ],

    // Écran « Parlez-nous de votre activité » : phrase de présentation et informations demandées ensuite.
    'profile_presentation' => [
        'fabricant_producteur' => [
            'pitch' => 'Vous fabriquez ou produisez directement des matériaux, équipements ou produits.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['chart', 'Détails de votre production', 'Produits fabriqués, capacités, certifications'],
                ['map-pin', 'Localisation et sites de production', 'Adresse, zones de couverture'],
                ['award', 'Documents de vérification', 'Pièces légales, attestations, photos'],
                ['truck', 'Logistique et approvisionnement', 'Modes de livraison, délais, zones desservies'],
            ],
        ],
        'grossiste' => [
            'pitch' => 'Vous distribuez des produits en gros, par lots ou volumes importants.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['boxes', 'Catalogue et distribution', 'Catégories vendues, marques, unités de vente'],
                ['store', 'Entrepôts et expédition', 'Lieux de stockage, délais de préparation'],
                ['award', 'Documents de vérification', 'Pièces légales, agréments'],
                ['globe', 'Commerce international', 'Import, export, pays desservis'],
            ],
        ],
        'demi_grossiste' => [
            'pitch' => 'Vous commercialisez en semi-gros avec plusieurs paliers de quantité.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['boxes', 'Activité semi-gros', 'Catégories, unités de vente, paliers de quantité'],
                ['map-pin', 'Zones desservies', 'Régions, origine d’expédition'],
                ['handshake', 'Conditions commerciales', 'Délais, disponibilité, négociation'],
                ['award', 'Documents de vérification', 'Pièces légales, pièce d’identité'],
            ],
        ],
        'revendeur_professionnel' => [
            'pitch' => 'Vous achetez auprès de fournisseurs pour revendre à vos clients.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['cart', 'Approvisionnement', 'Catégories achetées et revendues, fournisseurs'],
                ['chart', 'Activité commerciale', 'Zones de vente, volumes, fréquence d’achat'],
                ['file-search', 'Besoins', 'RFQ, commandes directes, recherche de fournisseurs'],
                ['award', 'Documents de vérification', 'Pièces légales, pièce d’identité'],
            ],
        ],
        'entreprise_btp' => [
            'pitch' => 'Vous réalisez des travaux, gérez des chantiers et effectuez des achats professionnels.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['hardhat', 'Activité BTP', 'Spécialités, types de travaux, zones d’intervention'],
                ['cart', 'Achats', 'Matériaux et équipements habituellement achetés'],
                ['users', 'Organisation', 'Responsable achats, responsable chantier'],
                ['award', 'Documents de vérification', 'Pièces légales, agréments, certifications'],
            ],
        ],
        'entrepreneur_artisan' => [
            'pitch' => 'Vous exercez un métier ou proposez directement des prestations BTP.',
            'asks' => [
                ['user', 'Identité professionnelle', 'Statut, nom de l’activité, ville'],
                ['hardhat', 'Métier et services', 'Métier principal, spécialités, services proposés'],
                ['map-pin', 'Zones d’intervention', 'Disponibilité, capacité d’intervention'],
                ['file', 'Réalisations', 'Références, photos de chantiers'],
                ['shield', 'Qualifications', 'Certificats, assurances, cartes professionnelles'],
                ['key', 'Documents de vérification', 'Pièce d’identité, immatriculation si disponible'],
            ],
        ],
        'promoteur_immobilier' => [
            'pitch' => 'Vous développez, construisez, aménagez ou gérez des programmes immobiliers.',
            'asks' => [
                ['building', 'Informations sur votre entreprise', 'Raison sociale, forme juridique, activité principale'],
                ['id-card', 'Informations légales', 'RCCM, N° contribuable, documents officiels'],
                ['home', 'Activité immobilière', 'Types de programmes, zones de projets'],
                ['cart', 'Achats', 'Catégories achetées, volumes, RFQ et appels d’offres'],
                ['chart', 'Projets en cours', 'Chantiers et programmes actuels'],
                ['award', 'Documents de vérification', 'Pièces légales, agréments'],
            ],
        ],
        'prestataire_btp' => [
            'pitch' => 'Vous fournissez des services spécialisés aux acteurs du bâtiment et des travaux publics.',
            'asks' => [
                ['building', 'Informations sur votre structure', 'Raison sociale ou nom de l’activité'],
                ['wrench', 'Services proposés', 'Catégorie, description, compétences'],
                ['map-pin', 'Intervention et moyens', 'Zones, délais, équipements, capacité d’équipe'],
                ['tag', 'Tarification', 'Tarif indicatif ou sur devis'],
                ['file', 'Références', 'Réalisations, clients de référence'],
                ['award', 'Documents de vérification', 'Pièces légales, certifications, assurances'],
            ],
        ],
    ],

    // Compte simple : publication d'une offre ou d'un besoin.
    'publication' => [
        'natures' => [
            'offer' => [
                'product' => ['Produit / matériau', 'boxes'], 'lot' => ['Lot de produits', 'boxes'], 'equipment' => ['Équipement / machine', 'wrench'],
                'stock' => ['Stock disponible', 'store'], 'service' => ['Service / prestation', 'hardhat'], 'other' => ['Autre offre', 'plus'],
            ],
            'need' => [
                'product' => ['Produit / matériau', 'boxes'], 'lot' => ['Lot de produits', 'boxes'], 'equipment' => ['Équipement / machine', 'wrench'],
                'rental' => ['Location', 'key'], 'service' => ['Service / prestation', 'hardhat'], 'other' => ['Autre besoin', 'plus'],
            ],
        ],
        'units' => [
            'unite' => 'Unité', 'sac_50' => 'Sac (50 kg)', 'sac_25' => 'Sac (25 kg)', 'tonne' => 'Tonne', 'kg' => 'Kg', 'm2' => 'm²', 'm3' => 'm³',
            'metre' => 'Mètre', 'barre' => 'Barre', 'bloc' => 'Bloc', 'palette' => 'Palette', 'carton' => 'Carton', 'lot' => 'Lot',
            'litre' => 'Litre', 'rouleau' => 'Rouleau', 'jour' => 'Jour', 'heure' => 'Heure', 'forfait' => 'Forfait',
        ],
        'currencies' => ['XOF' => 'XOF', 'EUR' => 'EUR', 'USD' => 'USD'],
        'availability' => ['immediate' => 'Immédiate', 'few_days' => 'Sous quelques jours', 'on_order' => 'Sur commande'],
        'validity' => [7 => '7 jours', 15 => '15 jours', 30 => '30 jours'],
        'deadlines' => ['asap' => 'Dès que possible', '7' => 'Sous 7 jours', '15' => 'Sous 15 jours', '30' => 'Sous 30 jours', 'date' => 'Date précise'],
        'max_photos' => 6,
        'max_documents' => 4,
    ],

    // Ce que chaque permission permet, affiché sur l'invitation collaborateur (ACC-14).
    'internal_permission_descriptions' => [
        'administrator' => 'Vous pourrez gérer l’entreprise, ses collaborateurs, ses documents et l’ensemble de ses espaces.',
        'direction' => 'Vous pourrez consulter l’activité, valider les décisions importantes et suivre les performances.',
        'purchasing' => 'Vous pourrez rechercher des fournisseurs, envoyer des RFQ et suivre les commandes d’achat.',
        'commercial' => 'Vous pourrez gérer le catalogue, répondre aux RFQ et suivre les ventes de l’entreprise.',
        'logistics' => 'Vous pourrez suivre les expéditions, les livraisons et les réceptions de marchandises.',
        'accounting' => 'Vous pourrez consulter les factures, les paiements et les relevés de l’entreprise.',
        'site_manager' => 'Vous pourrez gérer les chantiers, suivre les équipes et consulter les projets de l’entreprise.',
        'read_only' => 'Vous pourrez consulter les informations de l’entreprise sans pouvoir les modifier.',
    ],

    // Présentation des fonctions sur l'écran « Fonctions » (étape 4) : tags, photo et espace activé.
    'business_role_presentation' => [
        'buyer_pro' => [
            'icon' => 'cart', 'image' => 'ovanie-pro/access/role-buyer.jpg', 'tags' => ['Produits', 'Fournisseurs'],
            'text' => 'Rechercher des produits et fournisseurs, acheter, envoyer des RFQ, gérer les appels d’offres, commandes et paiements.',
            'space' => 'Accès au catalogue, fournisseurs, appels d’offres et commandes.',
        ],
        'seller_supplier' => [
            'icon' => 'store', 'image' => 'ovanie-pro/access/role-seller.jpg', 'tags' => ['Produits', 'Services', 'Appels d’offres'],
            'text' => 'Créer votre présence commerciale, publier vos produits, recevoir des RFQ, propositions et commandes.',
            'space' => 'Gestion de votre vitrine, produits, RFQ et propositions.',
        ],
        'advertiser' => [
            'icon' => 'megaphone', 'image' => 'ovanie-pro/access/role-advertiser.jpg', 'tags' => ['Offres', 'Visibilité'],
            'text' => 'Publier des offres simples selon les fonctionnalités autorisées.',
            'space' => 'Publication d’offres et suivi de leur visibilité.',
        ],
        'requester' => [
            'icon' => 'file-search', 'image' => 'ovanie-pro/access/role-requester.jpg', 'tags' => ['Besoins', 'Devis', 'Mise en relation'],
            'text' => 'Publier un besoin professionnel et recevoir des réponses autorisées.',
            'space' => 'Publication de besoins et réception de réponses qualifiées.',
        ],
    ],

    // Chiffres du panneau de connexion : à tenir à jour avec la réalité de la plateforme.
    'access_stats' => [
        ['icon' => 'users', 'value' => '+ 5 000', 'label' => 'Professionnels actifs'],
        ['icon' => 'building', 'value' => '+ 2 000', 'label' => 'Entreprises référencées'],
        ['icon' => 'map-pin', 'value' => '8', 'label' => 'Pays d’Afrique de l’Ouest'],
    ],

    'internal_permissions' => [
        'administrator' => 'Administrateur',
        'direction' => 'Direction',
        'purchasing' => 'Achats',
        'commercial' => 'Commercial',
        'logistics' => 'Logistique',
        'accounting' => 'Comptabilité',
        'site_manager' => 'Responsable chantier',
        'read_only' => 'Lecture seule',
    ],

    'languages' => [
        'fr' => 'Français',
        'en' => 'English',
    ],

    'countries' => [
        'Côte d’Ivoire', 'Sénégal', 'Mali', 'Burkina Faso', 'Ghana', 'Guinée', 'Togo', 'Bénin', 'Niger',
        'Cameroun', 'Nigeria', 'Maroc', 'France', 'Chine', 'Turquie', 'Émirats arabes unis', 'Autre',
    ],

    // Indicatifs proposés sur les formulaires de compte (ACC-02, ACC-13, ACC-18).
    'phone_codes' => [
        '+225' => 'CI +225', '+221' => 'SN +221', '+223' => 'ML +223', '+226' => 'BF +226',
        '+233' => 'GH +233', '+224' => 'GN +224', '+228' => 'TG +228', '+229' => 'BJ +229',
        '+227' => 'NE +227', '+237' => 'CM +237', '+234' => 'NG +234', '+212' => 'MA +212',
        '+33' => 'FR +33', '+86' => 'CN +86', '+90' => 'TR +90', '+971' => 'AE +971',
    ],

    // Durées d'expiration proposées pour une invitation collaborateur (ACC-13).
    'invitation_expiry_days' => [3 => '3 jours', 7 => '7 jours', 14 => '14 jours', 30 => '30 jours'],

];
