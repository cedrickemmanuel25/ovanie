<?php

/*
|--------------------------------------------------------------------------
| OVANIE PRO — formulaires d'inscription professionnels (V5.2 révision 1)
|--------------------------------------------------------------------------
| Source unique pour ACC-03 à ACC-11 : le même schéma sert à afficher le
| formulaire ET à le valider côté serveur (RG-46). Chaque profil possède ses
| propres blocs (RG-37) ; les champs usine ne sont demandés qu'au fabricant
| (RG-40) ; les exigences dépendent du statut entreprise / indépendant (RG-38).
|
| Champ : label, type (text, email, tel, url, number, year, textarea, select,
| radio, checkboxes), required (true | false | 'organization' | 'independent'),
| options, placeholder, help, width ('half' | 'full'), target :
|   - organization : colonne de pro_organizations (identité légale partagée)
|   - profile      : colonne de pro_professional_profiles (responsable)
|   - data         : profile_data (bloc métier propre au profil) — défaut
*/

$yesNo = ['yes' => 'Oui', 'no' => 'Non'];

$legalForms = [
    'sarl' => 'SARL', 'sarlu' => 'SARLU', 'sa' => 'SA', 'sas' => 'SAS', 'sasu' => 'SASU',
    'ei' => 'Entreprise individuelle', 'gie' => 'GIE', 'cooperative' => 'Coopérative', 'autre' => 'Autre',
];

$shippingModes = ['terrestre' => 'Terrestre', 'maritime' => 'Maritime', 'aerien' => 'Aérien'];

// Bloc « Responsable » commun : nom et prénoms viennent du compte (ACC-02), jamais ressaisis.
$responsibleBlock = [
    'title' => 'Responsable',
    'intro' => 'Vos coordonnées professionnelles. Vos nom et prénoms proviennent de votre compte.',
    'fields' => [
        'responsible_position' => ['label' => 'Fonction dans l’entreprise', 'type' => 'text', 'required' => true, 'target' => 'profile', 'placeholder' => 'Ex. Gérant, Directeur commercial', 'width' => 'half'],
        'professional_email' => ['label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
        'professional_whatsapp' => ['label' => 'WhatsApp professionnel', 'type' => 'tel', 'required' => false, 'target' => 'profile', 'width' => 'half'],
    ],
];

// Bloc « Entreprise » : identité légale de l'organisation (stockée une seule fois).
$companyBlock = [
    'title' => 'Entreprise',
    'intro' => 'Identité légale de votre organisation. Elle sera partagée avec vos collaborateurs invités.',
    'entity' => 'organization',
    'fields' => [
        'legal_name' => ['label' => 'Raison sociale', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
        'trade_name' => ['label' => 'Nom commercial', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
        'legal_form' => ['label' => 'Forme juridique', 'type' => 'select', 'required' => true, 'target' => 'organization', 'options' => $legalForms, 'width' => 'half'],
        'founded_year' => ['label' => 'Année de création', 'type' => 'year', 'required' => false, 'target' => 'organization', 'width' => 'half'],
        'registration_number' => ['label' => 'Registre (RCCM ou équivalent)', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
        'tax_number' => ['label' => 'Numéro contribuable', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half', 'help' => 'Selon votre pays d’enregistrement.'],
        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half', 'placeholder' => 'https://'],
        'address' => ['label' => 'Adresse', 'type' => 'textarea', 'required' => true, 'target' => 'organization', 'width' => 'full'],
    ],
];

// Variante « professionnel indépendant » : pas de champs de grande société (ACC-09, RG-38).
$independentBlock = [
    'title' => 'Identité professionnelle',
    'intro' => 'Votre activité indépendante. Le registre n’est demandé que si vous en disposez.',
    'entity' => 'independent',
    'fields' => [
        'trade_name' => ['label' => 'Nom commercial ou nom de l’activité', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
        'independent_status' => ['label' => 'Statut professionnel', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
            'auto_entrepreneur' => 'Entrepreneur individuel / auto-entrepreneur',
            'artisan_immatricule' => 'Artisan immatriculé',
            'freelance' => 'Indépendant sans immatriculation',
            'autre' => 'Autre',
        ]],
        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
        'registration_number' => ['label' => 'Numéro d’immatriculation (si disponible)', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
        'address' => ['label' => 'Adresse', 'type' => 'textarea', 'required' => false, 'target' => 'organization', 'width' => 'full'],
    ],
];

$legalDocuments = [
    'registry' => ['label' => 'Registre de commerce (RCCM ou équivalent)', 'required' => 'organization'],
    'tax_certificate' => ['label' => 'Attestation fiscale / numéro contribuable', 'required' => false],
    'representative_id' => ['label' => 'Pièce d’identité du représentant', 'required' => true],
];

return [

    'entity_types' => [
        'organization' => ['label' => 'Entreprise / organisation', 'description' => 'Société enregistrée, coopérative, GIE…'],
        'independent' => ['label' => 'Professionnel indépendant', 'description' => 'Artisan ou prestataire exerçant en son nom propre.'],
    ],

    'desired_functions' => [
        'buy' => 'Acheter',
        'sell' => 'Vendre / fournir',
        'services' => 'Proposer des services',
        'publish' => 'Publier des offres ou des besoins',
    ],

    'document_rules' => [
        'mimes' => 'pdf,jpg,jpeg,png,webp',
        'media_mimes' => 'pdf,jpg,jpeg,png,webp,mp4,mov',
        'max_kb' => 10240,
        'media_max_kb' => 20480,
    ],

    'profiles' => [

        // ACC-04
        'fabricant_producteur' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'responsible' => $responsibleBlock,

                // L'identité légale est persistée dans pro_organizations. Les champs
                // métier restent dans profile_data via la cible par défaut « data ».
                'company' => array_replace_recursive($companyBlock, ['fields' => [
                    'registration_country' => [
                        'label' => 'Pays d’enregistrement',
                        'type' => 'country',
                        'required' => true,
                        'target' => 'organization',
                        'width' => 'half',
                    ],
                    'founded_year' => [
                        'label' => 'Année de création',
                        'type' => 'year',
                        'required' => true,
                        'target' => 'organization',
                        'width' => 'half',
                    ],
                    'tax_number' => [
                        'label' => 'Numéro contribuable',
                        'type' => 'text',
                        'required' => true,
                        'target' => 'organization',
                        'width' => 'half',
                    ],
                    'sector' => [
                        'label' => 'Secteur d’activité principal',
                        'type' => 'text',
                        'required' => true,
                        'width' => 'half',
                        'placeholder' => 'Ex. matériaux de construction',
                    ],
                    'product_categories' => [
                        'label' => 'Catégories de produits fabriqués',
                        'type' => 'textarea',
                        'required' => true,
                        'width' => 'full',
                    ],
                ]]),

                'factory' => [
                    'title' => 'Usine',
                    'intro' => 'Les capacités restent « déclarées » tant qu’elles ne sont pas vérifiées par un audit.',
                    'fields' => [
                        'factory_name' => ['label' => 'Nom de l’usine', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'factory_country' => ['label' => 'Pays de l’usine', 'type' => 'country', 'required' => true, 'width' => 'half'],
                        'factory_city' => ['label' => 'Ville de l’usine', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'factory_address' => ['label' => 'Adresse complète de l’usine', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'factory_position' => ['label' => 'Position géographique', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => 'Ex. 5.3453, -4.0053'],
                        'factory_founded_year' => ['label' => 'Année de création de l’usine', 'type' => 'year', 'required' => true, 'width' => 'half'],
                        'factory_area' => ['label' => 'Superficie du site (m²)', 'type' => 'number', 'required' => true, 'width' => 'half'],
                        'factory_area_unit' => ['label' => 'Unité de superficie', 'type' => 'select', 'required' => false, 'options' => ['m2' => 'm²', 'ha' => 'ha']],
                        'production_lines' => ['label' => 'Nombre de lignes de production', 'type' => 'number', 'required' => true, 'width' => 'half'],
                        'capacity_monthly' => ['label' => 'Capacité de production mensuelle', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'capacity_monthly_unit' => ['label' => 'Unité (capacité mensuelle)', 'type' => 'select', 'required' => false, 'options' => ['tonnes' => 'Tonnes', 'kg' => 'Kg', 'm3' => 'm³', 'm2' => 'm²', 'unites' => 'Unités', 'sacs' => 'Sacs', 'palettes' => 'Palettes', 'litres' => 'Litres']],
                        'capacity_annual' => ['label' => 'Capacité de production annuelle', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'capacity_annual_unit' => ['label' => 'Unité (capacité annuelle)', 'type' => 'select', 'required' => false, 'options' => ['tonnes' => 'Tonnes', 'kg' => 'Kg', 'm3' => 'm³', 'm2' => 'm²', 'unites' => 'Unités', 'sacs' => 'Sacs', 'palettes' => 'Palettes', 'litres' => 'Litres']],
                        'main_equipment' => ['label' => 'Équipements principaux', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'products_manufactured' => ['label' => 'Produits fabriqués sur ce site', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'markets_served' => ['label' => 'Marchés principalement desservis', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'export_countries' => ['label' => 'Pays exportés', 'type' => 'text', 'required' => false, 'width' => 'half'],
                    ],
                ],

                'commerce' => [
                    'title' => 'Commerce',
                    'fields' => [
                        'moq' => ['label' => 'MOQ habituel / quantité minimale de commande', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'moq_unit' => ['label' => 'Unité du MOQ', 'type' => 'select', 'required' => false, 'options' => ['tonnes' => 'Tonnes', 'kg' => 'Kg', 'm3' => 'm³', 'm2' => 'm²', 'unites' => 'Unités', 'sacs' => 'Sacs', 'palettes' => 'Palettes', 'litres' => 'Litres']],
                        'moq_mode' => ['label' => 'Type de MOQ', 'type' => 'radio', 'required' => false, 'options' => ['fixed' => 'Valeur fixe', 'on_request' => 'Sur demande', 'variable' => 'Variable selon produit']],
                        'samples' => ['label' => 'Échantillons disponibles', 'type' => 'radio', 'required' => true, 'options' => $yesNo, 'width' => 'half'],
                        'production_lead_time' => ['label' => 'Délai moyen de production', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'production_lead_time_unit' => ['label' => 'Unité du délai', 'type' => 'select', 'required' => false, 'options' => ['jours' => 'Jours', 'semaines' => 'Semaines', 'mois' => 'Mois']],
                        'production_lead_time_mode' => ['label' => 'Type de délai', 'type' => 'radio', 'required' => false, 'options' => ['fixed' => 'Valeur fixe', 'on_request' => 'Sur demande', 'variable' => 'Variable selon produit']],
                        'countries_served' => ['label' => 'Pays desservis', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'export_enabled' => ['label' => 'Export', 'type' => 'radio', 'required' => true, 'options' => $yesNo, 'width' => 'half'],
                        // Ces deux champs deviennent obligatoires seulement si Export = Oui.
                        // La dépendance est contrôlée dans ProOnboardingController.
                        'shipping_modes' => ['label' => 'Modes d’expédition disponibles', 'type' => 'checkboxes', 'required' => false, 'options' => $shippingModes, 'width' => 'full'],
                        'incoterms' => ['label' => 'Incoterms utilisés', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],

                'verification' => [
                    'title' => 'Vérification',
                    'fields' => [
                        'representative_id_type' => [
                            'label' => 'Type de pièce du représentant',
                            'type' => 'select',
                            'required' => true,
                            'width' => 'half',
                            'options' => [
                                'cni' => 'Carte nationale d’identité',
                                'passport' => 'Passeport',
                                'driver_license' => 'Permis de conduire',
                            ],
                        ],
                        'representative_id_number' => ['label' => 'Numéro de la pièce du représentant', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        // Une certification est facultative, mais dès qu'elle est renseignée
                        // ses données sont contrôlées comme un ensemble cohérent.
                        'certification_type' => ['label' => 'Type de certification', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'certification_body' => ['label' => 'Organisme de certification', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'certification_number' => ['label' => 'Numéro de certification', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'certification_issue_date' => ['label' => 'Date d’émission de la certification', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'certification_expiry_date' => ['label' => 'Date d’expiration de la certification', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'certifications_json' => ['label' => 'Certifications structurées', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],
            ],

            // IMPORTANT : chaque zone d'upload affichée dans la vue existe dans le schéma.
            // ProFormSchema peut donc la valider et storeDocuments() la persister.
            'documents' => array_replace($legalDocuments, [
                'tax_certificate' => ['label' => 'Document fiscal / Contribuable', 'required' => true],
                'other_legal_documents' => ['label' => 'Autres documents légaux', 'required' => false, 'multiple' => true],
                'certifications' => ['label' => 'Certifications', 'required' => false, 'multiple' => true],
                'factory_photos' => ['label' => 'Photos de l’usine', 'required' => true, 'multiple' => true],
                'factory_videos' => ['label' => 'Vidéos de l’usine', 'required' => false, 'multiple' => true, 'media' => true],
                'technical_documents' => ['label' => 'Documents techniques', 'required' => false, 'multiple' => true],
                // Compatibilité avec d'anciens brouillons du formulaire.
                'factory_media' => ['label' => 'Photos / vidéos de l’usine (ancien format)', 'required' => false, 'multiple' => true, 'media' => true],
            ]),
        ],

        // ACC-05
        'grossiste' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'company' => [
                    'title' => 'Entreprise',
                    'entity' => 'organization',
                    'fields' => [
                        'legal_name' => ['label' => 'Raison sociale', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'trade_name' => ['label' => 'Nom commercial', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays d’enregistrement', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse complète', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'full'],
                        'legal_form' => ['label' => 'Forme juridique', 'type' => 'select', 'required' => true, 'target' => 'organization', 'width' => 'half', 'options' => [
                            'sarl' => 'SARL', 'sarlu' => 'SARLU', 'sa' => 'SA', 'sas' => 'SAS', 'sasu' => 'SASU',
                            'ei' => 'Entreprise individuelle', 'gie' => 'GIE', 'cooperative' => 'Coopérative', 'autre' => 'Autre',
                        ]],
                        'registration_number' => ['label' => 'Registre de commerce (RCCM)', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro contribuable', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'representative_name' => ['label' => 'Nom et prénoms du représentant', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'responsible_position' => ['label' => 'Fonction dans l’entreprise', 'type' => 'select', 'required' => true, 'target' => 'profile', 'width' => 'half', 'options' => [
                            'direction_generale' => 'Direction générale', 'gerant' => 'Gérant / Fondateur',
                            'direction_commerciale' => 'Direction commerciale', 'responsable_achats' => 'Responsable achats / approvisionnement',
                            'responsable_logistique' => 'Responsable logistique', 'autre' => 'Autre',
                        ]],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_whatsapp' => ['label' => 'WhatsApp professionnel', 'type' => 'tel', 'required' => false, 'target' => 'profile', 'width' => 'half'],
                        'professional_email' => ['label' => 'Adresse e-mail professionnelle', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                    ],
                ],
                'activity' => [
                    'title' => 'Activité',
                    'fields' => [
                        'categories_sold' => ['label' => 'Catégories de produits vendues', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'brands' => ['label' => 'Marques distribuées', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'goods_origin' => ['label' => 'Origine principale des marchandises', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'distribution_status' => ['label' => 'Statut commercial', 'type' => 'radio', 'required' => true, 'width' => 'full', 'options' => [
                            'importateur' => 'Importateur', 'distributeur' => 'Distributeur',
                            'importateur_distributeur' => 'Importateur et distributeur', 'grossiste_independant' => 'Grossiste indépendant', 'autre' => 'Autre',
                        ]],
                        'stock_capacity' => ['label' => 'Capacité de stock indicative', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'stock_unit' => ['label' => 'Unité de capacité', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'tonnes' => 'Tonnes', 'm2' => 'm²', 'm3' => 'm³', 'palettes' => 'Palettes', 'sacs' => 'Sacs', 'cartons' => 'Cartons', 'pieces' => 'Pièces', 'lots' => 'Lots',
                        ]],
                        'sale_units' => ['label' => 'Unités de vente', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'tonnes' => 'Tonnes', 'm2' => 'm²', 'm3' => 'm³', 'palettes' => 'Palettes', 'sacs' => 'Sacs', 'cartons' => 'Cartons', 'pieces' => 'Pièces', 'lots' => 'Lots',
                        ]],
                        'moq' => ['label' => 'MOQ habituel', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'moq_unit' => ['label' => 'Unité du MOQ', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'tonnes' => 'Tonnes', 'm2' => 'm²', 'm3' => 'm³', 'palettes' => 'Palettes', 'sacs' => 'Sacs', 'cartons' => 'Cartons', 'pieces' => 'Pièces', 'lots' => 'Lots',
                        ]],
                        'sales_volume' => ['label' => 'Volumes de vente indicatifs', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'sales_volume_unit' => ['label' => 'Unité des volumes', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'tonnes' => 'Tonnes', 'm2' => 'm²', 'm3' => 'm³', 'palettes' => 'Palettes', 'sacs' => 'Sacs', 'cartons' => 'Cartons', 'pieces' => 'Pièces', 'lots' => 'Lots',
                        ]],
                        'volume_periodicity' => ['label' => 'Périodicité des volumes', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => ['mensuel' => 'Mensuel', 'annuel' => 'Annuel']],
                        'zones_served' => ['label' => 'Zones desservies', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'countries_served' => ['label' => 'Pays desservis', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ],
                ],
                'warehouse' => [
                    'title' => 'Entrepôt & expédition',
                    'fields' => [
                        'dispatch_country' => ['label' => 'Pays du lieu principal d’expédition', 'type' => 'country', 'required' => true, 'width' => 'half'],
                        'dispatch_city' => ['label' => 'Ville du lieu principal d’expédition', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'dispatch_address' => ['label' => 'Adresse du lieu principal d’expédition', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'dispatch_position' => ['label' => 'Position géographique', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'has_warehouses' => ['label' => 'Disposez-vous d’un ou plusieurs entrepôts ?', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => ['yes' => 'Oui', 'no' => 'Non']],
                        'warehouses' => ['label' => 'Entrepôts', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'preparation_lead_time' => ['label' => 'Délai moyen de préparation', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'preparation_lead_unit' => ['label' => 'Unité du délai', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => ['heures' => 'heures', 'jours' => 'jours']],
                        'preparation_workdays' => ['label' => 'Jours ouvrés', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'lun' => 'Lun', 'mar' => 'Mar', 'mer' => 'Mer', 'jeu' => 'Jeu', 'ven' => 'Ven', 'sam' => 'Sam', 'dim' => 'Dim',
                        ]],
                        'preparation_conditions' => ['label' => 'Conditions particulières de préparation', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],
                'international' => [
                    'title' => 'Commerce international',
                    'fields' => [
                        'trade_flows' => ['label' => 'Opérations internationales', 'type' => 'radio', 'required' => true, 'width' => 'full', 'options' => [
                            'import' => 'Import', 'export' => 'Export', 'both' => 'Import et export', 'none' => 'Non actuellement',
                        ]],
                        'supply_countries' => ['label' => 'Pays d’approvisionnement', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'destination_countries' => ['label' => 'Pays de destination', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'transport_modes' => ['label' => 'Modes de transport utilisés', 'type' => 'checkboxes', 'required' => false, 'width' => 'full', 'options' => [
                            'terrestre' => 'Terrestre', 'maritime' => 'Maritime', 'aerien' => 'Aérien',
                        ]],
                        'international_notes' => ['label' => 'Informations internationales complémentaires', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],
            ],
            'documents' => [
                'registry' => ['label' => 'Registre de commerce / RCCM ou équivalent', 'required' => true],
                'constitution_document' => ['label' => 'Statuts ou document légal de constitution', 'required' => false],
                'legal_existence' => ['label' => 'Pièce d’existence légale complémentaire', 'required' => false],
                'tax_certificate' => ['label' => 'Numéro contribuable / attestation fiscale', 'required' => true],
                'tax_clearance' => ['label' => 'Attestation de régularité fiscale', 'required' => false],
                'professional_license' => ['label' => 'Licence ou document professionnel pertinent', 'required' => false],
                'product_catalogue' => ['label' => 'Catalogue produits ou liste de références', 'required' => false],
                'supply_proof' => ['label' => 'Facture fournisseur / bon de livraison / preuve d’approvisionnement', 'required' => false],
                'warehouse_photos' => ['label' => 'Photos d’entrepôt ou de stock', 'required' => false, 'multiple' => true],
                'sector_authorization' => ['label' => 'Autorisation sectorielle ou commerciale', 'required' => false],
                'product_approval' => ['label' => 'Agrément spécifique au produit', 'required' => false],
                'certifications' => ['label' => 'Certification qualité / produit / autre certification pertinente', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-06
        'demi_grossiste' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'company' => [
                    'title' => 'Entreprise',
                    'fields' => [
                        'legal_name' => ['label' => 'Raison sociale ou nom commercial', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'legal_form' => ['label' => 'Statut / forme juridique', 'type' => 'select', 'required' => true, 'target' => 'organization', 'options' => $legalForms, 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays d’enregistrement', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'registration_number' => ['label' => 'Registre de commerce (RCCM)', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro fiscal', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse complète', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'full'],
                        'representative_name' => ['label' => 'Représentant de l’entreprise', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'responsible_position' => ['label' => 'Fonction', 'type' => 'text', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_whatsapp' => ['label' => 'WhatsApp professionnel', 'type' => 'tel', 'required' => false, 'target' => 'profile', 'width' => 'half'],
                        'professional_email' => ['label' => 'Adresse e-mail professionnelle', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                    ],
                ],
                'activity' => [
                    'title' => 'Activité en semi-gros',
                    'fields' => [
                        'categories' => ['label' => 'Catégories de produits', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'brands_origins' => ['label' => 'Principales marques', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'origin_country' => ['label' => 'Origine des produits', 'type' => 'country', 'required' => true, 'width' => 'half'],
                        'sale_units' => ['label' => 'Unités de vente', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'minimum_quantity' => ['label' => 'Quantité minimale habituelle', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'minimum_quantity_unit' => ['label' => 'Unité de la quantité minimale', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'stock_capacity' => ['label' => 'Capacité de stock indicative', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'stock_unit' => ['label' => 'Unité de stock', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'supply_frequency' => ['label' => 'Fréquence d’approvisionnement', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire', 'bimensuelle' => 'Deux fois par mois', 'mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'variable' => 'Variable',
                        ]],
                        'quantity_tiers' => ['label' => 'Paliers de quantité', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                    ],
                ],
                'zones' => [
                    'title' => 'Zones',
                    'fields' => [
                        'dispatch_country' => ['label' => 'Pays d’expédition', 'type' => 'country', 'required' => true, 'width' => 'half'],
                        'dispatch_city' => ['label' => 'Ville d’expédition', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'dispatch_address' => ['label' => 'Adresse principale d’expédition', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'dispatch_position' => ['label' => 'Position géographique', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'cities_served' => ['label' => 'Villes desservies', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'regions_served' => ['label' => 'Régions desservies', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'countries_served' => ['label' => 'Pays desservis', 'type' => 'text', 'required' => true, 'width' => 'full'],
                    ],
                ],
                'conditions' => [
                    'title' => 'Conditions commerciales',
                    'fields' => [
                        'availability' => ['label' => 'Disponibilité habituelle des produits', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'stock' => 'En stock généralement', 'commande' => 'Principalement sur commande', 'mixte' => 'Stock et commande', 'variable' => 'Variable selon les produits',
                        ]],
                        'preparation_time' => ['label' => 'Délai moyen de préparation des commandes', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'preparation_unit' => ['label' => 'Unité du délai', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => ['heures' => 'heures', 'jours' => 'jours']],
                        'restock_frequency' => ['label' => 'Fréquence de réapprovisionnement', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire', 'bimensuelle' => 'Deux fois par mois', 'mensuelle' => 'Mensuelle', 'variable' => 'Variable',
                        ]],
                        'volume_negotiation' => ['label' => 'Possibilité de négociation par volume', 'type' => 'radio', 'required' => true, 'options' => $yesNo, 'width' => 'half'],
                        'commercial_comment' => ['label' => 'Commentaire sur vos conditions commerciales', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],
            ],
            'documents' => [
                'registry' => ['label' => 'Registre / document professionnel', 'required' => true],
                'tax_certificate' => ['label' => 'Identification fiscale', 'required' => false],
                'professional_proof' => ['label' => 'Justificatif professionnel', 'required' => true],
                'other_authorizations' => ['label' => 'Autres autorisations pertinentes', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-07
        'revendeur_professionnel' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'company' => [
                    'title' => 'Entreprise',
                    'entity' => 'organization',
                    'fields' => [
                        'legal_name' => ['label' => 'Raison sociale / nom commercial', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays d’enregistrement', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse', 'type' => 'textarea', 'required' => true, 'target' => 'organization', 'width' => 'full'],
                        'registration_number' => ['label' => 'Registre professionnel / RCCM', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro contribuable', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'representative_name' => ['label' => 'Représentant', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'responsible_position' => ['label' => 'Fonction', 'type' => 'text', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_whatsapp' => ['label' => 'WhatsApp professionnel', 'type' => 'tel', 'required' => false, 'target' => 'profile', 'width' => 'half'],
                        'professional_email' => ['label' => 'Adresse e-mail professionnelle', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                    ],
                ],
                'sourcing' => [
                    'title' => 'Approvisionnement',
                    'fields' => [
                        'purchase_categories' => ['label' => 'Catégories achetées', 'type' => 'textarea', 'required' => true, 'width' => 'half'],
                        'resale_categories' => ['label' => 'Catégories revendues', 'type' => 'textarea', 'required' => true, 'width' => 'half'],
                        'sourcing_countries' => ['label' => 'Pays d’approvisionnement recherchés', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'usual_suppliers' => ['label' => 'Fournisseurs habituels', 'type' => 'textarea', 'required' => false, 'width' => 'half'],
                        'usual_brands' => ['label' => 'Marques habituelles', 'type' => 'textarea', 'required' => false, 'width' => 'half'],
                    ],
                ],
                'commercial' => [
                    'title' => 'Activité commerciale',
                    'fields' => [
                        'sales_zones' => ['label' => 'Zones de vente', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'purchase_volumes' => ['label' => 'Volumes d’achat indicatifs', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'purchase_volume_unit' => ['label' => 'Unité', 'type' => 'select', 'required' => false, 'width' => 'half', 'options' => [
                            'unites' => 'Unités', 'pieces' => 'Pièces', 'cartons' => 'Cartons', 'sacs' => 'Sacs', 'packs' => 'Packs', 'palettes' => 'Palettes', 'lots' => 'Lots', 'tonnes' => 'Tonnes', 'm2' => 'm²', 'm3' => 'm³',
                        ]],
                        'purchase_volume_period' => ['label' => 'Période', 'type' => 'select', 'required' => false, 'width' => 'half', 'options' => [
                            'hebdomadaire' => 'Hebdomadaire', 'mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'annuelle' => 'Annuelle',
                        ]],
                        'purchase_frequency' => ['label' => 'Fréquence d’achat', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'quotidienne' => 'Quotidienne', 'hebdomadaire' => 'Hebdomadaire', 'mensuelle' => 'Mensuelle', 'trimestrielle' => 'Trimestrielle', 'ponctuelle' => 'Ponctuelle',
                        ]],
                        'importer' => ['label' => 'Êtes-vous importateur ?', 'type' => 'radio', 'required' => true, 'options' => $yesNo, 'width' => 'half'],
                        'commercial_supply_countries' => ['label' => 'Pays d’approvisionnement actuels ou recherchés', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                    ],
                ],
                'needs' => [
                    'title' => 'Besoins',
                    'fields' => [
                        'needs' => ['label' => 'Comment souhaitez-vous vous approvisionner sur OVANIE PRO ?', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'rfq' => 'Demandes de devis / RFQ',
                            'direct_orders' => 'Commandes directes',
                            'find_manufacturers' => 'Trouver des fabricants',
                            'find_suppliers' => 'Trouver des grossistes / fournisseurs',
                            'compare_offers' => 'Comparer plusieurs propositions',
                            'international_sourcing' => 'Approvisionnement international',
                        ]],
                    ],
                ],
            ],
            'documents' => [
                'registry' => ['label' => 'Registre professionnel / RCCM', 'required' => true],
                'tax_certificate' => ['label' => 'Identification fiscale / numéro contribuable', 'required' => false],
                'professional_proof' => ['label' => 'Justificatif d’activité professionnelle', 'required' => true],
                'commercial_proof' => ['label' => 'Catalogue, carte commerciale ou preuve d’activité de revente', 'required' => false],
                'other_authorizations' => ['label' => 'Autorisation spécifique ou agrément', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-08
        'entreprise_btp' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'responsible' => $responsibleBlock,
                'company' => $companyBlock,
                'btp' => [
                    'title' => 'Activité BTP',
                    'intro' => 'Vos chantiers ne remplacent pas l’adresse légale de l’entreprise.',
                    'fields' => [
                        'specialties' => ['label' => 'Spécialités', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'intervention_zones' => ['label' => 'Zones d’intervention', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'work_types' => ['label' => 'Types de travaux', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'batiment' => 'Bâtiment', 'genie_civil' => 'Génie civil', 'vrd' => 'VRD', 'second_oeuvre' => 'Second œuvre', 'renovation' => 'Rénovation', 'autre' => 'Autre',
                        ]],
                        'project_categories' => ['label' => 'Catégories de projets / chantiers', 'type' => 'text', 'required' => false, 'width' => 'full'],
                    ],
                ],
                'purchasing' => [
                    'title' => 'Achats',
                    'fields' => [
                        'purchase_categories' => ['label' => 'Matériaux et équipements habituellement achetés', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'purchase_scope' => ['label' => 'Achats', 'type' => 'checkboxes', 'required' => false, 'width' => 'half', 'options' => ['local' => 'Locaux', 'international' => 'Internationaux']],
                        'purchase_volume' => ['label' => 'Fréquence ou volumes indicatifs', 'type' => 'text', 'required' => false, 'width' => 'half'],
                    ],
                ],
                'organisation' => [
                    'title' => 'Organisation',
                    'intro' => 'Facultatif : ces personnes pourront ensuite être invitées comme collaborateurs.',
                    'fields' => [
                        'purchasing_manager' => ['label' => 'Responsable achats', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'site_manager' => ['label' => 'Responsable chantier', 'type' => 'text', 'required' => false, 'width' => 'half'],
                    ],
                ],
                'functions' => [
                    'title' => 'Fonctions souhaitées',
                    'fields' => [
                        'btp_functions' => ['label' => 'Fonctions', 'type' => 'checkboxes', 'required' => false, 'width' => 'full', 'options' => [
                            'rfq' => 'Demandes de devis (RFQ)', 'tenders' => 'Appels d’offres', 'orders' => 'Commandes', 'services' => 'Services', 'subcontracting' => 'Sous-traitance',
                        ]],
                    ],
                ],
            ],
            'documents' => $legalDocuments + [
                'certifications' => ['label' => 'Agréments / certifications', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-09
        'entrepreneur_artisan' => [
            'entity_types' => ['independent', 'organization'],
            'blocks' => [
                'identity' => [
                    'title' => 'Identité professionnelle',
                    'fields' => [
                        'trade_name' => ['label' => 'Nom commercial / enseigne', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'independent_status' => ['label' => 'Statut professionnel', 'type' => 'select', 'required' => 'independent', 'width' => 'half', 'options' => [
                            'artisan_independant' => 'Artisan indépendant',
                            'artisan_immatricule' => 'Artisan immatriculé',
                            'entrepreneur_individuel' => 'Entrepreneur individuel',
                            'auto_entrepreneur' => 'Auto-entrepreneur',
                            'freelance' => 'Professionnel indépendant',
                            'autre' => 'Autre',
                        ]],
                        'legal_name' => ['label' => 'Raison sociale', 'type' => 'text', 'required' => 'organization', 'target' => 'organization', 'width' => 'half'],
                        'legal_form' => ['label' => 'Forme juridique', 'type' => 'select', 'required' => 'organization', 'target' => 'organization', 'options' => $legalForms, 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse professionnelle', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'full'],
                        'registration_number' => ['label' => 'Registre / immatriculation', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro contribuable', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_whatsapp' => ['label' => 'WhatsApp', 'type' => 'tel', 'required' => false, 'target' => 'profile', 'width' => 'half'],
                        'professional_email' => ['label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                    ],
                ],
                'trade' => [
                    'title' => 'Métier',
                    'fields' => [
                        'main_trade' => ['label' => 'Métier principal', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'electricien' => 'Électricien', 'plombier' => 'Plombier', 'macon' => 'Maçon', 'carreleur' => 'Carreleur',
                            'peintre' => 'Peintre', 'menuisier' => 'Menuisier', 'soudeur' => 'Soudeur', 'climatisation' => 'Climatisation / froid',
                            'etancheite' => 'Étanchéité', 'charpentier' => 'Charpentier / couvreur', 'autre' => 'Autre métier BTP',
                        ]],
                        'specialties' => ['label' => 'Spécialités', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'installation_batiment' => 'Installation bâtiment', 'tableaux_electriques' => 'Tableaux électriques',
                            'cablage' => 'Câblage et raccordement', 'maintenance' => 'Maintenance', 'eclairage' => 'Éclairage intérieur / extérieur',
                            'industriel' => 'Intervention industrielle', 'mise_aux_normes' => 'Mise aux normes', 'depannage' => 'Dépannage',
                            'solaire' => 'Énergie solaire', 'automatismes' => 'Automatismes', 'autre' => 'Autre spécialité',
                        ]],
                        'experience_level' => ['label' => 'Années d’expérience', 'type' => 'radio', 'required' => true, 'width' => 'full', 'options' => [
                            'moins_2' => 'Moins de 2 ans', '2_5' => '2 à 5 ans', '5_10' => '5 à 10 ans', '10_plus' => '10 ans et plus',
                        ]],
                        'services' => ['label' => 'Services proposés', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'installation' => 'Installation', 'maintenance' => 'Maintenance', 'depannage' => 'Dépannage', 'diagnostic' => 'Diagnostic',
                            'rehabilitation' => 'Réhabilitation', 'sous_traitance' => 'Sous-traitance', 'etude_conseil' => 'Étude & conseil',
                            'fourniture_pose' => 'Fourniture avec pose', 'urgence' => 'Intervention d’urgence', 'autre' => 'Autres',
                        ]],
                    ],
                ],
                'intervention' => [
                    'title' => 'Intervention',
                    'fields' => [
                        'intervention_zones' => ['label' => 'Zones d’intervention', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'availability' => ['label' => 'Disponibilité', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => [
                            'maintenant' => 'Disponible maintenant', 'quelques_jours' => 'Sous quelques jours', 'planification' => 'Sur planification',
                        ]],
                        'team_size' => ['label' => 'Taille d’équipe indicative', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => [
                            'solo' => 'Travail individuel', '2_5' => '2 à 5 personnes', '6_10' => '6 à 10 personnes', '10_plus' => 'Plus de 10 personnes',
                        ]],
                        'intervention_capacity' => ['label' => 'Capacité d’intervention', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => [
                            'petits_travaux' => 'Petits travaux', 'projets_moyens' => 'Projets moyens', 'grands_chantiers' => 'Grands chantiers', 'tous' => 'Tous types de projets',
                        ]],
                        'client_types' => ['label' => 'Types de clients', 'type' => 'checkboxes', 'required' => false, 'width' => 'half', 'options' => [
                            'particuliers' => 'Particuliers', 'entreprises' => 'Entreprises', 'promoteurs' => 'Promoteurs immobiliers',
                            'administrations' => 'Administrations publiques', 'ong' => 'ONG / Projets', 'autres' => 'Autres',
                        ]],
                    ],
                ],
                'references' => [
                    'title' => 'Réalisations',
                    'fields' => [
                        'references_json' => ['label' => 'Réalisations ajoutées', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'references_completed' => ['label' => 'Étape réalisations terminée', 'type' => 'radio', 'required' => true, 'width' => 'full', 'options' => ['1' => 'Terminée']],
                    ],
                ],
                'qualifications' => [
                    'title' => 'Qualifications',
                    'fields' => [
                        'qualifications_note' => ['label' => 'Notes sur les qualifications', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'qualifications_completed' => ['label' => 'Étape qualifications terminée', 'type' => 'radio', 'required' => true, 'width' => 'full', 'options' => ['1' => 'Terminée']],
                    ],
                ],
            ],
            'documents' => [
                'work_photos' => ['label' => 'Photos de réalisations', 'required' => false, 'multiple' => true, 'media' => true],
                'certificates' => ['label' => 'Certificats professionnels', 'required' => false, 'multiple' => true],
                'authorizations' => ['label' => 'Autorisations', 'required' => false, 'multiple' => true],
                'insurance' => ['label' => 'Assurances', 'required' => false, 'multiple' => true],
                'professional_card' => ['label' => 'Carte professionnelle', 'required' => false, 'multiple' => true],
                'approvals' => ['label' => 'Agréments', 'required' => false, 'multiple' => true],
                'representative_id' => ['label' => 'Pièce d’identité', 'required' => true],
                'professional_document' => ['label' => 'Document professionnel', 'required' => true],
                'activity_proof' => ['label' => 'Justificatif d’activité', 'required' => true],
                'authorization_approval' => ['label' => 'Autorisation ou agrément', 'required' => false],
                'liability_insurance' => ['label' => 'Assurance responsabilité civile', 'required' => false],
                'other_documents' => ['label' => 'Autres documents', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-10
        'promoteur_immobilier' => [
            'entity_types' => ['organization'],
            'blocks' => [
                'company' => [
                    'title' => 'Entreprise',
                    'fields' => [
                        'legal_name' => ['label' => 'Raison sociale', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'trade_name' => ['label' => 'Nom commercial', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'legal_form' => ['label' => 'Forme juridique', 'type' => 'select', 'required' => true, 'target' => 'organization', 'options' => $legalForms, 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays d’enregistrement', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'full'],
                        'registration_number' => ['label' => 'Registre / RCCM', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro fiscal / contribuable', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'representative_name' => ['label' => 'Représentant', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'responsible_position' => ['label' => 'Fonction', 'type' => 'text', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_email' => ['label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half', 'placeholder' => 'https://'],
                    ],
                ],
                'activity' => [
                    'title' => 'Activité',
                    'fields' => [
                        'real_estate_activities' => ['label' => 'Activités immobilières', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'promoteur' => 'Promoteur immobilier',
                            'amenageur' => 'Aménageur',
                            'constructeur' => 'Constructeur',
                            'gestionnaire' => 'Gestionnaire immobilier',
                            'autre' => 'Autre activité autorisée',
                        ]],
                        'activity_zones' => ['label' => 'Zones principales d’activité', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'real_estate_segments' => ['label' => 'Segments immobiliers principaux', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'residentiel' => 'Résidentiel',
                            'bureaux' => 'Bureaux',
                            'commerces' => 'Commerces',
                            'programmes_mixtes' => 'Programmes mixtes',
                            'lotissements' => 'Lotissements',
                            'residences_services' => 'Résidences services',
                        ]],
                    ],
                ],
                'projects' => [
                    'title' => 'Projets',
                    'fields' => [
                        'project_zones' => ['label' => 'Zones géographiques des projets', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'program_types' => ['label' => 'Types de programmes', 'type' => 'checkboxes', 'required' => true, 'width' => 'full', 'options' => [
                            'residentiel' => 'Résidentiel',
                            'commercial' => 'Commercial',
                            'industriel' => 'Industriel',
                            'mixte' => 'Mixte',
                            'lotissement' => 'Lotissement',
                            'infrastructure' => 'Infrastructure associée',
                            'autre' => 'Autre',
                        ]],
                        'current_projects' => ['label' => 'Projets ou chantiers actuels', 'type' => 'textarea', 'required' => false, 'width' => 'full', 'help' => 'Ces données restent privées par défaut.'],
                    ],
                ],
                'purchasing' => [
                    'title' => 'Achats',
                    'fields' => [
                        'purchase_categories' => ['label' => 'Catégories régulièrement achetées', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'purchase_volume_band' => ['label' => 'Volumes indicatifs', 'type' => 'radio', 'required' => false, 'width' => 'full', 'options' => [
                            'low' => 'Faibles volumes',
                            'regular' => 'Volumes réguliers',
                            'high' => 'Volumes importants',
                            'project' => 'Achats par projet',
                        ]],
                        'purchase_local_countries' => ['label' => 'Achats locaux', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'purchase_international_countries' => ['label' => 'Achats internationaux', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'rfq_usage' => ['label' => 'Utilisation de RFQ', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => [
                            'regular' => 'Régulièrement',
                            'occasional' => 'Occasionnellement',
                            'rare' => 'Rarement',
                            'never' => 'Jamais',
                        ]],
                        'tender_usage' => ['label' => 'Utilisation d’appels d’offres', 'type' => 'radio', 'required' => true, 'width' => 'half', 'options' => [
                            'frequent' => 'Fréquente',
                            'occasional' => 'Ponctuelle',
                            'project' => 'Selon projet',
                            'unused' => 'Non utilisée',
                        ]],
                        'supplier_types' => ['label' => 'Types de fournisseurs recherchés', 'type' => 'checkboxes', 'required' => false, 'width' => 'full', 'options' => [
                            'manufacturers' => 'Fabricants',
                            'distributors' => 'Distributeurs',
                            'btp_providers' => 'Prestataires BTP',
                            'certified_suppliers' => 'Fournisseurs certifiés',
                            'technical_subcontractors' => 'Sous-traitants techniques',
                        ]],
                    ],
                ],
            ],
            'documents' => [
                'company_documents' => ['label' => 'Documents de l’entreprise', 'required' => true, 'multiple' => true],
                'registry' => ['label' => 'Registre / Immatriculation', 'required' => true],
                'tax_certificate' => ['label' => 'Documents fiscaux', 'required' => true],
                'professional_proofs' => ['label' => 'Justificatifs professionnels', 'required' => true, 'multiple' => true],
                'approvals_certifications' => ['label' => 'Agréments / Certifications', 'required' => false, 'multiple' => true],
            ],
        ],

        // ACC-11
        'prestataire_btp' => [
            'entity_types' => ['organization', 'independent'],
            'blocks' => [
                'contact' => [
                    'title' => 'Coordonnées professionnelles',
                    'fields' => [
                        'professional_email' => ['label' => 'E-mail professionnel', 'type' => 'email', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_phone' => ['label' => 'Téléphone professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                        'professional_whatsapp' => ['label' => 'WhatsApp professionnel', 'type' => 'tel', 'required' => true, 'target' => 'profile', 'width' => 'half'],
                    ],
                ],
                'company' => [
                    'title' => 'Entreprise',
                    'entity' => 'organization',
                    'fields' => [
                        'legal_name' => ['label' => 'Raison sociale', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'trade_name' => ['label' => 'Nom commercial', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'registration_country' => ['label' => 'Pays', 'type' => 'country', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'legal_form' => ['label' => 'Forme juridique', 'type' => 'select', 'required' => true, 'target' => 'organization', 'options' => $legalForms, 'width' => 'half'],
                        'registration_number' => ['label' => 'Registre / RCCM', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'tax_number' => ['label' => 'Numéro d’identifiant fiscal', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'full'],
                        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                    ],
                ],
                'independent' => [
                    'title' => 'Professionnel indépendant',
                    'entity' => 'independent',
                    'fields' => [
                        'trade_name' => ['label' => 'Nom commercial / activité', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'independent_status' => ['label' => 'Statut professionnel', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'artisan' => 'Artisan / professionnel indépendant',
                            'consultant' => 'Consultant indépendant',
                            'freelance' => 'Freelance',
                            'entrepreneur' => 'Entrepreneur individuel',
                            'autre' => 'Autre',
                        ]],
                        'registration_number' => ['label' => 'Registre / immatriculation', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                        'city' => ['label' => 'Ville', 'type' => 'text', 'required' => true, 'target' => 'organization', 'width' => 'half'],
                        'address' => ['label' => 'Adresse professionnelle', 'type' => 'text', 'required' => false, 'target' => 'organization', 'width' => 'full'],
                        'website' => ['label' => 'Site web', 'type' => 'url', 'required' => false, 'target' => 'organization', 'width' => 'half'],
                    ],
                ],
                'services' => [
                    'title' => 'Services',
                    'fields' => [
                        'service_category' => ['label' => 'Catégorie de service', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'installation' => 'Installation', 'maintenance' => 'Maintenance', 'etudes' => 'Études / ingénierie', 'transport' => 'Transport chantier',
                            'location' => 'Location d’équipements', 'sous_traitance' => 'Sous-traitance', 'inspection' => 'Inspection / contrôle',
                            'securite' => 'Sécurité BTP', 'autre' => 'Autre',
                        ]],
                        'service_subcategory' => ['label' => 'Sous-catégorie', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'service_title' => ['label' => 'Titre de la prestation', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'services_description' => ['label' => 'Description de la prestation', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                        'skills' => ['label' => 'Compétences principales', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'specialties' => ['label' => 'Spécialités', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'installation_types' => ['label' => 'Types d’interventions réalisées', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'intervention_levels' => ['label' => 'Niveau / complexité d’intervention', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'standards' => ['label' => 'Normes et standards maîtrisés', 'type' => 'text', 'required' => false, 'width' => 'full'],
                    ],
                ],
                'intervention' => [
                    'title' => 'Intervention',
                    'fields' => [
                        'intervention_zones' => ['label' => 'Zones d’intervention', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'availability' => ['label' => 'Disponibilité', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'team_capacity' => ['label' => 'Capacité de l’équipe', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'team_size' => ['label' => 'Nombre de personnes indicatif', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'indicative_lead_time' => ['label' => 'Délai moyen d’intervention', 'type' => 'text', 'required' => true, 'width' => 'half'],
                        'client_types' => ['label' => 'Types de clients', 'type' => 'text', 'required' => false, 'width' => 'full'],
                    ],
                ],
                'means' => [
                    'title' => 'Moyens',
                    'fields' => [
                        'means_types' => ['label' => 'Types de moyens', 'type' => 'text', 'required' => true, 'width' => 'full'],
                        'equipment_json' => ['label' => 'Liste des équipements et moyens', 'type' => 'textarea', 'required' => true, 'width' => 'full'],
                    ],
                ],
                'references' => [
                    'title' => 'Références',
                    'fields' => [
                        'references_json' => ['label' => 'Réalisations et références', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'references_status' => ['label' => 'Étape Références', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ],
                ],
                'qualifications' => [
                    'title' => 'Qualifications',
                    'fields' => [
                        'qualifications_json' => ['label' => 'Qualifications déclarées', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                        'qualifications_status' => ['label' => 'Étape Qualifications', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ],
                ],
                'pricing' => [
                    'title' => 'Tarification',
                    'fields' => [
                        'pricing_mode' => ['label' => 'Mode de tarification principal', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => [
                            'quote' => 'Sur devis', 'indicative' => 'Tarif indicatif', 'hour' => 'À l’heure', 'day' => 'À la journée',
                            'package' => 'Au forfait', 'unit' => 'À l’unité', 'other' => 'Autre',
                        ]],
                        'indicative_rate' => ['label' => 'Tarif indicatif', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'pricing_currency' => ['label' => 'Devise', 'type' => 'select', 'required' => false, 'width' => 'half', 'options' => ['FCFA' => 'FCFA', 'EUR' => 'EUR', 'USD' => 'USD']],
                        'pricing_unit' => ['label' => 'Unité de facturation', 'type' => 'text', 'required' => false, 'width' => 'half'],
                        'pricing_modes' => ['label' => 'Modes de facturation complémentaires', 'type' => 'text', 'required' => false, 'width' => 'full'],
                        'pricing_notes' => ['label' => 'Conditions ou précisions tarifaires', 'type' => 'textarea', 'required' => false, 'width' => 'full'],
                    ],
                ],
            ],
            'documents' => [
                'work_media' => ['label' => 'Photos / vidéos de réalisations', 'required' => false, 'multiple' => true, 'media' => true],
                'qualification_proofs' => ['label' => 'Certifications, assurances, agréments et qualifications', 'required' => false, 'multiple' => true],
                'professional_documents' => ['label' => 'Documents professionnels', 'required' => true, 'multiple' => true],
                'legal_documents' => ['label' => 'Documents légaux applicables', 'required' => 'organization', 'multiple' => true],
                'insurance_documents' => ['label' => 'Assurances professionnelles', 'required' => false, 'multiple' => true],
                'accreditation_documents' => ['label' => 'Agréments', 'required' => false, 'multiple' => true],
                'certification_documents' => ['label' => 'Certifications', 'required' => false, 'multiple' => true],
                'authorization_documents' => ['label' => 'Autorisations métier', 'required' => false, 'multiple' => true],
            ],
        ],
    ],
];
