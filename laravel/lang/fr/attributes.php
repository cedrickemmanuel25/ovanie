<?php

/*
 * Noms français des champs de formulaire, utilisés dans les messages de validation
 * (« Le champ … est obligatoire »). Fusionnés dans validation.php.
 * Un champ absent d'ici apparaît sous son nom technique : ajoutez-le ici.
 */
return [
    // Identité et compte
    'business_name' => 'nom de l’entreprise', 'business_email' => 'e-mail professionnel', 'business_phone' => 'téléphone professionnel',
    'secondary_phone' => 'téléphone secondaire', 'phone_country' => 'indicatif', 'whatsapp_country' => 'indicatif WhatsApp',
    'residence_country' => 'pays de résidence', 'preferred_language' => 'langue préférée', 'display_name' => 'nom affiché',
    'contact_name' => 'nom du contact', 'account_name' => 'nom du compte', 'job_title' => 'fonction', 'employee_code' => 'matricule',
    'session_password' => 'mot de passe', 'deletion_code' => 'code de suppression', 'delete_confirmation' => 'confirmation de suppression',
    'otp_code' => 'code de vérification', 'orange_otp' => 'code Orange Money', 'device_name' => 'nom de l’appareil',

    // Boutique
    'main_category' => 'catégorie principale', 'main_category_id' => 'catégorie principale', 'main_subcategory' => 'sous-catégorie',
    'main_subcategory_id' => 'sous-catégorie', 'subcategory_id' => 'sous-catégorie', 'activity_sector_id' => 'secteur d’activité',
    'identity_country' => 'pays de la pièce', 'identity_file' => 'pièce d’identité', 'identity_file_front' => 'recto de la pièce',
    'identity_file_back' => 'verso de la pièce', 'identity_document' => 'pièce d’identité', 'identity_upload_mode' => 'mode d’envoi de la pièce',
    'rccm_file' => 'registre de commerce', 'tax_file' => 'document fiscal', 'document_file' => 'document', 'document_type' => 'type de document',
    'document_name' => 'nom du document', 'seller_confirmation' => 'confirmation du vendeur', 'seller_type' => 'type de vendeur',
    'payout_method' => 'moyen de reversement', 'payout_holder' => 'titulaire du reversement', 'payout_number' => 'numéro de reversement',
    'online_operator' => 'opérateur de paiement', 'payment_phone' => 'numéro de paiement', 'mobile_number' => 'numéro mobile',
    'geo_accuracy' => 'précision de la position', 'geo_captured_at' => 'date de la position', 'geo_source' => 'source de la position',
    'google_maps_reference' => 'référence Google Maps', 'quarter_id' => 'quartier', 'commune_id' => 'commune', 'commune_ids' => 'communes',

    // Produits
    'sale_type' => 'type de vente', 'product_name' => 'nom du produit', 'product_state' => 'état du produit', 'main_image' => 'image principale',
    'technical_details' => 'caractéristiques techniques', 'technical_sheet' => 'fiche technique', 'product_video' => 'vidéo du produit',
    'product_video_url' => 'lien de la vidéo', 'product_variant_id' => 'modèle', 'product_attributes' => 'caractéristiques',
    'length_cm' => 'longueur', 'width_cm' => 'largeur', 'height_cm' => 'hauteur', 'volume_m3' => 'volume',
    'max_weight_kg' => 'poids maximum', 'max_volume_m3' => 'volume maximum', 'capacity_kg' => 'capacité',
    'units_per_package' => 'unités par conditionnement', 'content_per_unit' => 'contenu par unité', 'content_unit' => 'unité du contenu',
    'coverage_per_unit_m2' => 'surface couverte par unité', 'usage_area' => 'utilisation', 'origin_country' => 'pays d’origine',
    'material_grade' => 'qualité du matériau', 'return_policy' => 'politique de retour', 'supply_delay' => 'délai d’approvisionnement',
    'is_negotiable' => 'prix négociable', 'is_active' => 'statut actif', 'fast_delivery' => 'livraison rapide', 'is_fragile' => 'produit fragile',
    'requires_unloading' => 'déchargement nécessaire', 'unloading_instructions' => 'consignes de déchargement', 'handling_options' => 'options de manutention',
    'price_p1' => 'prix du palier 1', 'price_p2' => 'prix du palier 2', 'price_p3' => 'prix du palier 3', 'negotiated_price' => 'prix négocié',
    'proposed_price' => 'prix proposé', 'flash_start_at' => 'début de la vente flash', 'flash_end' => 'fin de la vente flash',
    'bf_start' => 'début du Black Friday', 'bf_end' => 'fin du Black Friday', 'boost_package' => 'formule de mise en avant',
    'minimum_quantity' => 'quantité minimum', 'lead_time' => 'délai', 'availability_status' => 'disponibilité', 'sort_order' => 'ordre d’affichage',
    'parent_id' => 'catégorie parente', 'category_type' => 'type de catégorie',

    // Livraison
    'delivery_mode' => 'mode de livraison', 'delivery_price' => 'tarif de livraison', 'estimated_delay' => 'délai estimé',
    'default_delay' => 'délai habituel', 'vehicle_code' => 'type de véhicule', 'vehicle_type' => 'type de véhicule', 'vehicle_types' => 'types de véhicule',
    'vehicle_plate' => 'immatriculation', 'plate_number' => 'immatriculation', 'vehicle_label' => 'nom du véhicule', 'vehicle_photo' => 'photo du véhicule',
    'vehicle_color' => 'couleur du véhicule', 'vehicle_year' => 'année du véhicule', 'zone_max_weight_kg' => 'poids maximum de la zone',
    'zone_max_volume_m3' => 'volume maximum de la zone', 'capacity_description' => 'description de la capacité', 'conditions' => 'conditions',
    'pickup_address' => 'adresse de retrait', 'pickup_city' => 'ville de retrait', 'pickup_commune' => 'commune de retrait',
    'pickup_date' => 'date de retrait', 'pickup_time_slot' => 'créneau de retrait', 'pickup_contact_name' => 'contact de retrait',
    'pickup_contact_phone' => 'téléphone du contact de retrait', 'pickup_code' => 'code de retrait', 'pickup_photo' => 'photo du retrait',
    'delivery_photo' => 'photo de livraison', 'delivery_otp_code' => 'code de livraison', 'delivery_provider' => 'transporteur',
    'driver_id' => 'livreur', 'driver_name' => 'nom du livreur', 'driver_phone' => 'téléphone du livreur', 'tracking_number' => 'numéro de suivi',
    'tracking_reference' => 'référence de suivi', 'mission_number' => 'numéro de mission', 'shipment_id' => 'expédition', 'shipment_ids' => 'expéditions',
    'shipment_date' => 'date d’expédition', 'manual_eta_at' => 'heure d’arrivée estimée', 'distance_km' => 'distance', 'incident_type' => 'type d’incident',
    'incident_note' => 'description de l’incident', 'origin_commune' => 'commune de départ', 'destination_commune' => 'commune d’arrivée',
    'origin_commune_id' => 'commune de départ', 'destination_commune_id' => 'commune d’arrivée', 'origin_city' => 'ville de départ',
    'destination_city' => 'ville d’arrivée', 'base_fee' => 'tarif de base', 'price_per_km' => 'tarif par kilomètre', 'price_per_kg' => 'tarif par kilo',
    'price_per_m3' => 'tarif par mètre cube', 'tour_date' => 'date de la tournée', 'departure_time' => 'heure de départ', 'zone_label' => 'nom de la zone',
    'zone_ids' => 'zones', 'partner_type' => 'type de partenaire', 'main_zone' => 'zone principale', 'contract_reference' => 'référence du contrat',
    'contract_file' => 'contrat', 'responsible_name' => 'nom du responsable', 'coverage_start_time' => 'début de couverture',
    'coverage_end_time' => 'fin de couverture', 'operational_note' => 'note opérationnelle', 'registration_document' => 'carte grise',
    'insurance_document' => 'assurance', 'technical_inspection' => 'visite technique', 'mileage_km' => 'kilométrage',
    'delivery_place' => 'lieu de livraison', 'reception_place' => 'lieu de réception', 'validated_city' => 'ville validée',

    // Commandes, paiements, retours
    'order_id' => 'commande', 'order_reference' => 'référence de la commande', 'order_item_id' => 'article', 'item_ids' => 'articles',
    'product_id' => 'produit', 'product_ids' => 'produits', 'shop_id' => 'boutique', 'user_id' => 'utilisateur', 'payment_id' => 'paiement',
    'payment_reference' => 'référence du paiement', 'payment_status' => 'statut du paiement', 'payment_note' => 'note de paiement',
    'client_payment_method_id' => 'moyen de paiement enregistré', 'saved_address_id' => 'adresse enregistrée', 'is_default' => 'par défaut',
    'amount_claimed' => 'montant réclamé', 'refund_reference' => 'référence du remboursement', 'refund_method' => 'mode de remboursement',
    'return_id' => 'retour', 'return_type' => 'type de retour', 'photo_proof' => 'photo justificative', 'detailed_description' => 'description détaillée',
    'discovery_date' => 'date de constat', 'rejection_reason' => 'motif du refus', 'vendor_response' => 'réponse du vendeur',
    'vendor_note' => 'note du vendeur', 'vendor_cancel_reason' => 'motif d’annulation', 'vendor_delivery_note' => 'note de livraison',
    'vendor_rejection_proof' => 'justificatif du refus', 'resolution_note' => 'note de résolution', 'resolution_code' => 'code de résolution',
    'card_brand' => 'type de carte', 'card_last4' => 'derniers chiffres de la carte', 'card_exp_month' => 'mois d’expiration',
    'card_exp_year' => 'année d’expiration', 'recipient_email' => 'e-mail du destinataire', 'personal_message' => 'message personnel',
    'activation_price' => 'prix d’activation', 'initial_balance' => 'solde initial', 'validity_days' => 'durée de validité',
    'validity_months' => 'durée de validité',

    // Assistance, commercial, administration
    'requester_name' => 'nom du demandeur', 'requester_email' => 'e-mail du demandeur', 'requester_phone' => 'téléphone du demandeur',
    'request_type' => 'type de demande', 'request_date' => 'date de la demande', 'preferred_at' => 'date souhaitée',
    'assigned_to' => 'personne assignée', 'admin_note' => 'note de l’administrateur', 'internal_notes' => 'notes internes',
    'next_action' => 'prochaine action', 'next_follow_up_at' => 'date de relance', 'rescheduled_at' => 'nouvelle date',
    'target_department' => 'service concerné', 'priority_level' => 'niveau de priorité', 'client_name' => 'nom du client',
    'client_type' => 'type de client', 'commercial_notes' => 'notes commerciales', 'starts_at' => 'date de début', 'ends_at' => 'date de fin',
    'starts_on' => 'date de début', 'ends_on' => 'date de fin', 'date_from' => 'date de début', 'date_to' => 'date de fin',
    'button_text' => 'texte du bouton', 'button_url' => 'lien du bouton', 'newsletter_email' => 'e-mail',

    // Logistique
    'average_delay_hours' => 'délai moyen', 'calculation_type' => 'type de calcul', 'fleet_vehicle_id' => 'véhicule',
    'mission_items' => 'missions', 'mission_items.*' => 'mission', 'missions' => 'missions', 'missions.*' => 'mission',
    'vendor_delivery_status' => 'statut de livraison', 'registration' => 'immatriculation', 'model' => 'modèle', 'year' => 'année',
    'availability' => 'disponibilité', 'coverage' => 'zones couvertes', 'coverage.*' => 'zone couverte', 'commune_ids.*' => 'commune',
    'stop_order' => 'ordre des arrêts', 'stop_order.*' => 'arrêt', 'slot' => 'créneau', 'destination' => 'destination',
    'sla_percent' => 'taux de respect des délais', 'integrated_at' => 'date d’intégration', 'observations' => 'observations',
    'supplement_id' => 'supplément', 'compatible_with_others' => 'cumul avec d’autres suppléments', 'urgency' => 'urgence',
    'optimization_enabled' => 'optimisation', 'allow_express' => 'livraison express', 'coverage_geojson' => 'tracé de la zone',

    // Administration
    'commercial_id' => 'commercial', 'commercial_ids' => 'commerciaux', 'commercial_ids.*' => 'commercial', 'commission_paid' => 'commission versée',
    'lock_contacts' => 'verrouillage des contacts', 'admin_confirmation' => 'confirmation de l’administrateur', 'site_name' => 'nom du site',
    'contact_email' => 'e-mail de contact', 'contact_phone' => 'téléphone de contact', 'bank_name' => 'nom de la banque',
    'bank_account_name' => 'titulaire du compte bancaire', 'bank_account_number' => 'numéro du compte bancaire', 'estimated_hours' => 'durée estimée',

    'requester_type' => 'type de demandeur', 'source_type' => 'type de source', 'lead_type' => 'type d’opportunité', 'mission_id' => 'mission',
    'escalation_level' => 'niveau d’escalade', 'hours' => 'horaires', 'announcement1' => 'annonce 1', 'announcement2' => 'annonce 2',
    'role' => 'rôle', 'value' => 'valeur', 'link' => 'lien', 'zone' => 'zone', 'position' => 'position',
    'channel' => 'canal', 'priority' => 'priorité', 'body' => 'message', 'severity' => 'gravité', 'to_number' => 'numéro à appeler',
    'needed_by' => 'date souhaitée', 'location_detail' => 'précision du lieu', 'min_order_unit' => 'unité du minimum de commande',
];
