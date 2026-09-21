@extends('admin.layouts.app')

@section('title', 'Validation de la boutique | Administration OVANIE')
@section('page-title', 'Validation de la boutique')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_shops.css') }}">
@endpush

@section('content')
@php
    $status = $shop->status ?: 'pending';
    $statusLabels = [
        'pending' => 'En attente',
        'approved' => 'Approuvée',
        'rejected' => 'Rejetée',
    ];
    $sellerTypeLabels = [
        'particulier' => 'Particulier',
        'entreprise' => 'Entreprise',
        'artisan' => 'Artisan',
        'grossiste' => 'Grossiste / distributeur',
    ];
    $legalFormLabels = [
        'sarl' => 'SARL',
        'sarlu' => 'SARLU',
        'sa' => 'SA',
        'sas' => 'SAS',
        'ei' => 'Entreprise individuelle',
        'cooperative' => 'Coopérative',
        'autre' => 'Autre',
    ];
    $identityTypeLabels = [
        'cni' => 'Carte nationale d’identité',
        'passport' => 'Passeport',
        'permis' => 'Permis de conduire',
        'resident' => 'Carte de résident',
    ];
    $identityUploadLabels = [
        'pdf' => 'Document PDF',
        'scan' => 'Photographies recto et verso',
    ];
    $kycLabels = [
        'pending' => 'À contrôler',
        'verified' => 'Vérifié',
        'rejected' => 'Rejeté',
    ];
    $logisticsLabels = [
        'ready' => 'Prête',
        'incomplete' => 'Incomplète',
        'suspended' => 'Suspendue',
    ];
    $geoSourceLabels = [
        'browser_gps' => 'GPS du téléphone',
        'map' => 'Position placée sur la carte',
        'manual' => 'Saisie manuelle',
    ];
    $operatorLabels = [
        'orange' => 'Orange Money',
        'mtn' => 'MTN MoMo',
        'wave' => 'Wave',
        'moov' => 'Moov Money',
    ];
    $documentDefinitions = [
        'identity-pdf' => ['label' => 'Pièce d’identité au format PDF', 'available' => (bool) $shop->identity_file, 'icon' => 'file'],
        'identity-front' => ['label' => 'Recto de la pièce d’identité', 'available' => (bool) $shop->identity_file_front, 'icon' => 'image'],
        'identity-back' => ['label' => 'Verso de la pièce d’identité', 'available' => (bool) $shop->identity_file_back, 'icon' => 'image'],
        'selfie' => ['label' => 'Photo du responsable', 'available' => (bool) $shop->selfie, 'icon' => 'user'],
        'rccm' => ['label' => 'Document RCCM', 'available' => (bool) $shop->rccm_file, 'icon' => 'file'],
        'tax' => ['label' => 'Document fiscal', 'available' => (bool) $shop->tax_file, 'icon' => 'file'],
    ];
    $documentCount = collect($documentDefinitions)->where('available', true)->count();
    $hasGps = $shop->latitude !== null && $shop->longitude !== null;
    $kycStatus = $shop->kyc_status ?: 'pending';
    $logisticsStatus = $shop->logistics_status ?: 'incomplete';
    $canPublish = $shop->canPublishProducts();
    $validationChecks = [
        ['label' => 'Informations du responsable', 'valid' => filled($shop->user?->name) && filled($shop->user?->email)],
        ['label' => 'Informations de la boutique', 'valid' => filled($shop->name) && filled($shop->main_category)],
        ['label' => 'Documents d’identité', 'valid' => $documentCount > 0],
        ['label' => 'Position du point de collecte', 'valid' => $shop->usesSellerLogistics() || $hasGps],
        ['label' => 'Configuration logistique', 'valid' => $logisticsStatus === 'ready'],
        ['label' => 'Autorisation de publication', 'valid' => $canPublish],
    ];
@endphp

<div class="shop-validation-page">
    <header class="shop-validation-heading">
        <div>
            <span class="shops-kicker">Contrôle vendeur</span>
            <h2>{{ $shop->name ?: 'Boutique sans nom' }}</h2>
            <p>Examinez le dossier vendeur, les documents, la localisation et la préparation logistique avant de prendre une décision.</p>
        </div>
        <div class="shop-heading-actions">
            <a class="shops-secondary-button" href="{{ route('admin.shops.index') }}">
                <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></svg>
                Retour aux boutiques
            </a>
            <a class="shops-primary-button" href="{{ route('admin.shops.downloadDossier', $shop) }}">
                <svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                Télécharger le dossier
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="shops-alert shops-alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shops-alert shops-alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="shops-alert shops-alert-danger">
            <strong>Le formulaire contient une erreur.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <section class="shop-summary-card">
        <div class="shop-summary-main">
            <span class="shop-summary-avatar">{{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}</span>
            <div>
                <div class="shop-summary-title">
                    <h3>{{ $shop->name ?: 'Boutique sans nom' }}</h3>
                    <span class="shops-status is-{{ $status }}">{{ $statusLabels[$status] ?? 'En attente' }}</span>
                    <span class="mini-status {{ $shop->is_active ? 'is-success' : 'is-warning' }}">
                        {{ $shop->is_active ? 'Vente activée' : 'Vente désactivée' }}
                    </span>
                </div>
                <p>{{ $shop->user?->name ?: 'Responsable non renseigné' }} · {{ $sellerTypeLabels[$shop->seller_type] ?? 'Profil vendeur non renseigné' }}</p>
                <div class="shop-summary-contact">
                    <span>{{ $shop->user?->email ?: 'Adresse e-mail non renseignée' }}</span>
                    <span>{{ $shop->user?->phone ?: 'Téléphone non renseigné' }}</span>
                    <span>{{ collect([$shop->commune, $shop->district, $shop->city])->filter()->join(' · ') ?: 'Localisation non renseignée' }}</span>
                </div>
            </div>
        </div>
        <div class="shop-summary-reference">
            <small>Identifiant du dossier</small>
            <strong>#{{ $shop->id }}</strong>
            <span>Créé le {{ optional($shop->created_at)->format('d/m/Y à H:i') }}</span>
        </div>
    </section>

    <section class="shop-validation-stat-grid">
        <div class="shop-validation-stat">
            <span class="shops-stat-icon is-blue"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span>
            <div><small>Documents disponibles</small><strong>{{ $documentCount }} / {{ count($documentDefinitions) }}</strong><em>Dossier KYC sécurisé</em></div>
        </div>
        <div class="shop-validation-stat">
            <span class="shops-stat-icon {{ $hasGps ? 'is-green' : 'is-red' }}"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg></span>
            <div><small>Position de collecte</small><strong>{{ $hasGps ? 'Enregistrée' : 'Manquante' }}</strong><em>{{ $hasGps ? $shop->geo_status_label : 'Latitude et longitude absentes' }}</em></div>
        </div>
        <div class="shop-validation-stat">
            <span class="shops-stat-icon {{ $logisticsStatus === 'ready' ? 'is-green' : 'is-orange' }}"><svg viewBox="0 0 24 24"><path d="M3 6h13v10H3z"/><path d="M16 10h3l2 3v3h-5z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
            <div><small>Préparation logistique</small><strong>{{ $logisticsLabels[$logisticsStatus] ?? 'Incomplète' }}</strong><em>{{ $shop->logistics_mode_label }}</em></div>
        </div>
        <div class="shop-validation-stat">
            <span class="shops-stat-icon is-purple"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M8 8h8v8H8z"/></svg></span>
            <div><small>Activité de la boutique</small><strong>{{ number_format($shop->products_count ?? 0, 0, ',', ' ') }} produit(s)</strong><em>{{ number_format($shop->orders_count ?? 0, 0, ',', ' ') }} commande(s)</em></div>
        </div>
    </section>

    <div class="shop-validation-layout">
        <main class="shop-validation-main">
            <section class="shop-section-card decision-card">
                <header class="decision-header">
                    <span class="decision-header-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/></svg>
                    </span>
                    <div class="decision-header-copy">
                        <span class="decision-kicker">Contrôle administratif</span>
                        <h3>Décision sur le dossier vendeur</h3>
                        <p>Choisissez une action après vérification de l’identité, de la position de collecte et de la préparation logistique.</p>
                    </div>
                    <div class="decision-current-status">
                        <small>Statut actuel</small>
                        <span class="shops-status is-{{ $status }}">{{ $statusLabels[$status] ?? 'En attente' }}</span>
                    </div>
                </header>

                @if($shop->rejection_reason)
                    <div class="rejection-reason">
                        <strong>Dernier motif communiqué</strong>
                        <p>{{ $shop->rejection_reason }}</p>
                    </div>
                @endif

                <div class="decision-guidance">
                    <span aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                    </span>
                    <p>La validation commerciale active le compte vendeur. La publication des produits reste conditionnée par les contrôles logistiques.</p>
                </div>

                <div class="decision-option-grid">
                    <form action="{{ route('admin.shops.updateStatus', $shop) }}" method="POST" class="decision-option decision-option-approve" onsubmit="return confirm('Approuver et activer cette boutique ?')">
                        @csrf
                        <input type="hidden" name="status" value="approved">
                        <div class="decision-option-top">
                            <span class="decision-option-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                            </span>
                            <span class="decision-option-label">Validation</span>
                        </div>
                        <div class="decision-option-copy">
                            <h4>Approuver la boutique</h4>
                            <p>Active le compte vendeur et confirme la conformité administrative du dossier.</p>
                        </div>
                        <button type="submit" class="decision-button decision-button-approve">Approuver et activer</button>
                    </form>

                    <form action="{{ route('admin.shops.updateStatus', $shop) }}" method="POST" class="decision-option decision-option-pending" onsubmit="return confirm('Remettre cette boutique en attente ?')">
                        @csrf
                        <input type="hidden" name="status" value="pending">
                        <div class="decision-option-top">
                            <span class="decision-option-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                            </span>
                            <span class="decision-option-label">Complément requis</span>
                        </div>
                        <div class="decision-option-copy">
                            <h4>Demander des corrections</h4>
                            <p>Conserve le dossier et permet au vendeur de compléter les éléments manquants.</p>
                        </div>
                        <button type="submit" class="decision-button decision-button-pending">Remettre en attente</button>
                    </form>

                    <form action="{{ route('admin.shops.updateStatus', $shop) }}" method="POST" class="decision-option decision-option-reject" onsubmit="return confirm('Rejeter cette boutique et transmettre ce motif ?')">
                        @csrf
                        <input type="hidden" name="status" value="rejected">
                        <div class="decision-reject-intro">
                            <div class="decision-option-top">
                                <span class="decision-option-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="m8 8 8 8M16 8l-8 8"/><circle cx="12" cy="12" r="9"/></svg>
                                </span>
                                <span class="decision-option-label">Refus motivé</span>
                            </div>
                            <div class="decision-option-copy">
                                <h4>Rejeter le dossier</h4>
                                <p>Indiquez précisément au vendeur la correction attendue avant une nouvelle soumission.</p>
                            </div>
                        </div>
                        <div class="decision-reject-field">
                            <label for="rejection_reason">Motif du rejet <span>*</span></label>
                            <textarea id="rejection_reason" name="rejection_reason" maxlength="2000" required placeholder="Exemple : la pièce d’identité est illisible et la position du point de collecte doit être confirmée.">{{ old('rejection_reason', $status === 'rejected' ? $shop->rejection_reason : '') }}</textarea>
                            <small>Ce message sera communiqué au vendeur.</small>
                        </div>
                        <button type="submit" class="decision-button decision-button-reject">Rejeter et notifier</button>
                    </form>
                </div>
            </section>

            <section class="shop-section-card">
                <div class="shop-section-heading">
                    <span class="section-icon"><svg viewBox="0 0 24 24"><path d="M6 2h9l3 3v17H6z"/><path d="M14 2v5h5"/><path d="M9 12h6M9 16h6"/></svg></span>
                    <div>
                        <h3>Documents du dossier vendeur</h3>
                        <p>Les fichiers sont protégés et accessibles uniquement depuis les routes administratives sécurisées.</p>
                    </div>
                </div>
                <div class="document-grid">
                    @foreach($documentDefinitions as $key => $document)
                        <article class="document-card {{ $document['available'] ? 'is-available' : 'is-missing' }}">
                            <span class="document-icon">
                                @if($document['icon'] === 'user')
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 4-7 8-7s7 2 8 7"/></svg>
                                @elseif($document['icon'] === 'image')
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-5-5L5 20"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24"><path d="M6 2h9l3 3v17H6z"/><path d="M14 2v5h5"/></svg>
                                @endif
                            </span>
                            <div>
                                <strong>{{ $document['label'] }}</strong>
                                <span class="mini-status {{ $document['available'] ? 'is-success' : 'is-warning' }}">
                                    {{ $document['available'] ? 'Disponible' : 'Non fourni' }}
                                </span>
                            </div>
                            @if($document['available'])
                                <div class="document-actions">
                                    <a target="_blank" rel="noopener" href="{{ route('admin.shops.documents.show', [$shop, $key]) }}">Consulter</a>
                                    <a href="{{ route('admin.shops.documents.download', [$shop, $key]) }}">Télécharger</a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="shop-section-card">
                <div class="shop-section-heading">
                    <span class="section-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 4-7 8-7s7 2 8 7"/></svg></span>
                    <div><h3>Responsable et entreprise</h3><p>Identité du titulaire du compte et informations légales déclarées.</p></div>
                </div>
                <div class="details-grid">
                    <article class="details-card">
                        <h4>Responsable de la boutique</h4>
                        <dl>
                            <div><dt>Nom complet</dt><dd>{{ $shop->user?->name ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Adresse e-mail</dt><dd>{{ $shop->user?->email ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Téléphone</dt><dd>{{ $shop->user?->phone ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Profil vendeur</dt><dd>{{ $sellerTypeLabels[$shop->seller_type] ?? 'Non renseigné' }}</dd></div>
                            <div><dt>Paiement direct</dt><dd>{{ $shop->direct_payment ? 'Activé' : 'Désactivé' }}</dd></div>
                        </dl>
                    </article>
                    <article class="details-card">
                        <h4>Informations de l’entreprise</h4>
                        <dl>
                            <div><dt>Raison sociale</dt><dd>{{ $shop->company_name ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Forme juridique</dt><dd>{{ $legalFormLabels[$shop->legal_form] ?? ($shop->legal_form ?: 'Non renseignée') }}</dd></div>
                            <div><dt>Numéro RCCM</dt><dd>{{ $shop->rccm ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Numéro contribuable</dt><dd>{{ $shop->taxpayer_number ?: 'Non renseigné' }}</dd></div>
                        </dl>
                    </article>
                </div>
            </section>

            <section class="shop-section-card">
                <div class="shop-section-heading">
                    <span class="section-icon"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg></span>
                    <div><h3>Boutique, localisation et logistique</h3><p>Informations utilisées pour la collecte, le calcul des distances et la publication des produits.</p></div>
                </div>
                <div class="details-grid">
                    <article class="details-card">
                        <h4>Informations de la boutique</h4>
                        <dl>
                            <div><dt>Nom</dt><dd>{{ $shop->name ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Catégorie principale</dt><dd>{{ $shop->main_category ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Description</dt><dd>{{ $shop->description ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Zone commerciale</dt><dd>{{ $shop->delivery_zone ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Délai de traitement</dt><dd>{{ $shop->processing_time ?: 'Non renseigné' }}</dd></div>
                        </dl>
                    </article>
                    <article class="details-card">
                        <h4>Adresse du point de collecte</h4>
                        <dl>
                            <div><dt>Région</dt><dd>{{ $shop->region ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Ville</dt><dd>{{ $shop->city ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Commune</dt><dd>{{ $shop->commune ?: 'Non renseignée' }}</dd></div>
                            <div><dt>Quartier</dt><dd>{{ $shop->district ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Point de repère</dt><dd>{{ $shop->landmark ?: 'Facultatif, non renseigné' }}</dd></div>
                            <div><dt>Adresse complète</dt><dd>{{ $shop->address ?: 'Non renseignée' }}</dd></div>
                        </dl>
                    </article>
                    <article class="details-card">
                        <h4>Position GPS</h4>
                        <dl>
                            <div><dt>État</dt><dd>{{ $hasGps ? $shop->geo_status_label : 'Position manquante' }}</dd></div>
                            <div><dt>Latitude</dt><dd>{{ $hasGps ? number_format((float) $shop->latitude, 7, ',', '') : 'Non enregistrée' }}</dd></div>
                            <div><dt>Longitude</dt><dd>{{ $hasGps ? number_format((float) $shop->longitude, 7, ',', '') : 'Non enregistrée' }}</dd></div>
                            <div><dt>Précision</dt><dd>{{ $shop->geo_accuracy !== null ? number_format((float) $shop->geo_accuracy, 0, ',', ' ') . ' m' : 'Non renseignée' }}</dd></div>
                            <div><dt>Source</dt><dd>{{ $geoSourceLabels[$shop->geo_source] ?? ($shop->geo_source ?: 'Non renseignée') }}</dd></div>
                        </dl>
                        @if($hasGps)
                            <a class="map-link" href="https://www.google.com/maps?q={{ $shop->latitude }},{{ $shop->longitude }}" target="_blank" rel="noopener">Ouvrir la position sur la carte</a>
                        @endif
                    </article>
                    <article class="details-card">
                        <h4>Configuration logistique</h4>
                        <dl>
                            <div><dt>Mode</dt><dd>{{ $shop->logistics_mode_label }}</dd></div>
                            <div><dt>État</dt><dd>{{ $logisticsLabels[$logisticsStatus] ?? 'Incomplète' }}</dd></div>
                            <div><dt>Publication des produits</dt><dd>{{ $canPublish ? 'Autorisée' : 'Bloquée' }}</dd></div>
                            <div><dt>Reversement vendeur</dt><dd>{{ $shop->payment_mode_label }}</dd></div>
                        </dl>
                    </article>
                </div>
            </section>

            <section class="shop-section-card">
                <div class="shop-section-heading">
                    <span class="section-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M7 9h10M7 13h6"/></svg></span>
                    <div><h3>Identité et reversement</h3><p>Données déclarées pour la vérification du responsable et le paiement du vendeur.</p></div>
                </div>
                <div class="details-grid">
                    <article class="details-card">
                        <h4>Pièce d’identité</h4>
                        <dl>
                            <div><dt>Pays de délivrance</dt><dd>{{ strtolower($shop->identity_country ?: '') === 'ci' ? 'Côte d’Ivoire' : ($shop->identity_country ? strtoupper($shop->identity_country) : 'Non renseigné') }}</dd></div>
                            <div><dt>Type de pièce</dt><dd>{{ $identityTypeLabels[$shop->identity_type] ?? ($shop->identity_type ?: 'Non renseigné') }}</dd></div>
                            <div><dt>Numéro</dt><dd>{{ $shop->identity_number ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Mode d’envoi</dt><dd>{{ $identityUploadLabels[$shop->identity_upload_mode] ?? ($shop->identity_upload_mode ?: 'Non renseigné') }}</dd></div>
                        </dl>
                    </article>
                    <article class="details-card">
                        <h4>Reversement Mobile Money</h4>
                        <dl>
                            <div><dt>Opérateur</dt><dd>{{ $operatorLabels[$shop->mm_operator] ?? ($shop->mm_operator ?: 'Non renseigné') }}</dd></div>
                            <div><dt>Numéro de réception</dt><dd>{{ $shop->mm_number ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Nom du titulaire</dt><dd>{{ $shop->mm_holder ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Calendrier</dt><dd>{{ $shop->payment_mode_label }}</dd></div>
                            <div><dt>WhatsApp professionnel</dt><dd>{{ $shop->whatsapp ?: 'Non renseigné' }}</dd></div>
                            <div><dt>Adresse e-mail professionnelle</dt><dd>{{ $shop->business_email ?: 'Non renseignée' }}</dd></div>
                        </dl>
                    </article>
                </div>
            </section>
        </main>

        <aside class="shop-validation-sidebar">
            <section class="shop-side-card">
                <h3>Contrôle du dossier</h3>
                <div class="validation-check-list">
                    @foreach($validationChecks as $check)
                        <div class="validation-check {{ $check['valid'] ? 'is-valid' : 'is-warning' }}">
                            <span>{{ $check['valid'] ? '✓' : '!' }}</span>
                            <strong>{{ $check['label'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="shop-side-card">
                <h3>Traçabilité commerciale</h3>
                <dl class="sidebar-details">
                    <div><dt>Créée par</dt><dd>{{ $shop->commercialCreator?->name ?: 'Création directe' }}</dd></div>
                    <div><dt>Commercial responsable</dt><dd>{{ $shop->commercialManager?->name ?: 'Non attribué' }}</dd></div>
                    <div><dt>Dernier contrôle par</dt><dd>{{ $shop->reviewer?->name ?: 'Aucun contrôle enregistré' }}</dd></div>
                    <div><dt>Date d’approbation</dt><dd>{{ $shop->approved_at ? $shop->approved_at->format('d/m/Y à H:i') : 'Non approuvée' }}</dd></div>
                </dl>
            </section>

            <section class="shop-side-card {{ $canPublish ? 'is-ready-card' : 'is-warning-card' }}">
                <h3>{{ $canPublish ? 'Boutique prête' : 'Publication bloquée' }}</h3>
                <p>
                    {{ $canPublish
                        ? 'La boutique satisfait les conditions commerciales et logistiques nécessaires à la publication.'
                        : 'L’approbation du dossier ne suffit pas encore : complétez la localisation ou la logistique avant de publier les produits.' }}
                </p>
                <a href="{{ route('admin.products.index', ['shop_id' => $shop->id]) }}">Voir les produits de la boutique</a>
            </section>
        </aside>
    </div>
</div>
@endsection
