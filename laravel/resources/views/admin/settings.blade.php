@extends('admin.layouts.app')

@section('title', 'Paramètres du site | Administration OVANIE')
@section('page-title', 'Paramètres du site')

@php
    $settingImageUrl = function ($path, $fallback = 'images/placeholder.png') {
        $path = trim((string) ($path ?: $fallback));

        if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/') || str_starts_with($path, 'images/')) {
            return asset($path);
        }

        return asset('storage/' . $path);
    };

    $settingsIcons = [
        'store' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10 5 4h14l2 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/><path d="M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-4-2-2 2c-3.5-1.4-6.3-4.2-7.7-7.7l2-2Z"/></svg>',
        'pin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4Z"/><path d="M12 9v5M12 17h.01"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>',
        'palette' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a2 2 0 0 1 0-4h2a7 7 0 0 0-2-11Z"/><circle cx="7.5" cy="10" r=".7"/><circle cx="9.5" cy="6.5" r=".7"/><circle cx="14.5" cy="6.5" r=".7"/><circle cx="17" cy="10" r=".7"/></svg>',
        'megaphone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 14-6v14L3 13Z"/><path d="M7 14v5h4l-1-4"/></svg>',
        'bank' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 9 9-5 9 5"/><path d="M5 10v7M9 10v7M15 10v7M19 10v7M3 20h18"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-5-5L5 20"/></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>',
    ];
@endphp

@section('content')
<main class="admin-main settings-premium-page">

    <header class="admin-header settings-hero">
        <div class="settings-hero__content">
            <span class="settings-hero__eyebrow">CENTRE DE CONTRÔLE OVANIE</span>
            <h1>Paramètres du site</h1>
            <p>Gérez l’identité, les annonces, les visuels, les coordonnées et les éléments premium affichés sur OVANIE.</p>
        </div>

        <div class="settings-hero__panel">
            <div class="settings-hero__logo">
                @if(!empty($settings['siteLogo']))
                    <img src="{{ $settingImageUrl($settings['siteLogo'], 'images/logo.png') }}" alt="Logo OVANIE">
                @else
                    <span>OV</span>
                @endif
            </div>
            <div>
                <strong>{{ $settings['siteName'] ?? 'OVANIE Marketplace' }}</strong>
                <small>{{ $settings['contactEmail'] ?? 'contact@ovanie.com' }}</small>
            </div>
        </div>
    </header>

    <section class="settings-kpi-grid">
        <div class="settings-kpi-card">
            <span class="settings-kpi-icon">{!! $settingsIcons['store'] !!}</span>
            <div>
                <strong>Site</strong>
                <small>{{ $settings['siteName'] ?? 'OVANIE' }}</small>
            </div>
        </div>
        <div class="settings-kpi-card">
            <span class="settings-kpi-icon">{!! $settingsIcons['phone'] !!}</span>
            <div>
                <strong>Assistance</strong>
                <small>{{ $settings['phone'] ?? 'Non renseigné' }}</small>
            </div>
        </div>
        <div class="settings-kpi-card">
            <span class="settings-kpi-icon">{!! $settingsIcons['pin'] !!}</span>
            <div>
                <strong>Adresse</strong>
                <small>{{ $settings['address'] ?? 'Côte d’Ivoire' }}</small>
            </div>
        </div>
        <div class="settings-kpi-card {{ ($settings['maintenanceMode'] ?? '0') == '1' ? 'is-warning' : 'is-ok' }}">
            <span class="settings-kpi-icon">{!! ($settings['maintenanceMode'] ?? '0') == '1' ? $settingsIcons['warning'] : $settingsIcons['check'] !!}</span>
            <div>
                <strong>Statut</strong>
                <small>{{ ($settings['maintenanceMode'] ?? '0') == '1' ? 'Maintenance active' : 'Site actif' }}</small>
            </div>
        </div>
    </section>

    <section class="settings-section settings-premium-shell">
        <form id="settingsForm" method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="settings-grid">

                {{-- ================= INFOS GENERALES ================= --}}
                <div class="settings-card settings-card-featured">
                    <div class="settings-card-head">
                        <span class="settings-card-icon">{!! $settingsIcons['settings'] !!}</span>
                        <div>
                            <h2 class="settings-card-title">Informations générales</h2>
                            <p>Nom, contact, horaires et localisation principale.</p>
                        </div>
                    </div>

                    <div class="settings-fields-grid">
                        <div class="form-group">
                            <label for="siteName">Nom du site</label>
                            <input type="text" id="siteName" name="siteName" value="{{ old('siteName', $settings['siteName'] ?? '') }}" required />
                            @error('siteName') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="contactEmail">Email de contact</label>
                            <input type="email" id="contactEmail" name="contactEmail" value="{{ old('contactEmail', $settings['contactEmail'] ?? '') }}" required />
                            @error('contactEmail') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $settings['phone'] ?? '') }}" required />
                            @error('phone') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="hours">Horaires</label>
                            <input type="text" id="hours" name="hours" value="{{ old('hours', $settings['hours'] ?? '') }}" placeholder="Ex : Lundi à Vendredi, 8h à 18h" />
                            @error('hours') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group settings-field-wide">
                            <label for="address">Adresse</label>
                            <textarea id="address" name="address" rows="3" required>{{ old('address', $settings['address'] ?? '') }}</textarea>
                            @error('address') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- ================= IDENTITE VISUELLE ================= --}}
                <div class="settings-card settings-card-identity">
                    <div class="settings-card-head">
                        <span class="settings-card-icon">{!! $settingsIcons['palette'] !!}</span>
                        <div>
                            <h2 class="settings-card-title">Identité visuelle</h2>
                            <p>Logo injecté dans le layout guest, les métas SEO et le header.</p>
                        </div>
                    </div>

                    <div class="settings-logo-row">
                        <div class="logo-preview-box logo-preview-premium">
                            @if(!empty($settings['siteLogo']))
                                <img src="{{ $settingImageUrl($settings['siteLogo'], 'images/logo.png') }}" alt="Logo actuel" class="logo-preview-img">
                            @else
                                <span>OVANIE</span>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="siteLogo">Logo du site</label>
                            <input type="file" id="siteLogo" name="siteLogo" accept="image/*" />
                            <small class="form-help">Formats acceptés : JPG, PNG, WEBP, SVG. Le logo sera stocké dans <code>storage/logos</code>.</small>
                            @error('siteLogo') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-group checkbox-group settings-maintenance">
                        <input type="checkbox" id="maintenanceMode" name="maintenanceMode" value="1" {{ old('maintenanceMode', $settings['maintenanceMode'] ?? '0') == '1' ? 'checked' : '' }} />
                        <label for="maintenanceMode">
                            <strong>Mode maintenance</strong>
                            <span>Activer temporairement la maintenance du site.</span>
                        </label>
                    </div>
                </div>

                {{-- ================= BARRE D'ANNONCE ================= --}}
                <div class="settings-card settings-card-compact">
                    <div class="settings-card-head">
                        <span class="settings-card-icon">{!! $settingsIcons['megaphone'] !!}</span>
                        <div>
                            <h2 class="settings-card-title">Barre d’annonce</h2>
                            <p>Messages défilants du haut du site.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="announcement1">Texte annonce 1</label>
                        <input type="text" id="announcement1" name="announcement1" value="{{ old('announcement1', $settings['announcement1'] ?? '') }}" placeholder="Ex : Votre chantier commence ici" />
                        @error('announcement1') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="announcement2">Texte annonce 2</label>
                        <input type="text" id="announcement2" name="announcement2" value="{{ old('announcement2', $settings['announcement2'] ?? '') }}" placeholder="Ex : Livraison partout en Côte d’Ivoire" />
                        @error('announcement2') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- ================= INFOS BANCAIRES ================= --}}
                <div class="settings-card settings-card-compact">
                    <div class="settings-card-head">
                        <span class="settings-card-icon">{!! $settingsIcons['bank'] !!}</span>
                        <div>
                            <h2 class="settings-card-title">Informations bancaires</h2>
                            <p>Informations utilisées pour les paiements et documents.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bankName">Nom de la banque</label>
                        <input type="text" id="bankName" name="bankName" value="{{ old('bankName', $settings['bankName'] ?? '') }}" required />
                        @error('bankName') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="bankAccountName">Nom du titulaire du compte</label>
                        <input type="text" id="bankAccountName" name="bankAccountName" value="{{ old('bankAccountName', $settings['bankAccountName'] ?? '') }}" required />
                        @error('bankAccountName') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="bankAccountNumber">Numéro de compte</label>
                        <input type="text" id="bankAccountNumber" name="bankAccountNumber" value="{{ old('bankAccountNumber', $settings['bankAccountNumber'] ?? '') }}" required />
                        @error('bankAccountNumber') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- ================= VISUELS / ICONES ================= --}}
                @php
                    $visualFieldsGroups = [
                        'Navigation & header' => [
                            'cartIcon' => ['label' => 'Icône panier', 'fallback' => 'images/panier.png'],
                            'featuredIcon' => ['label' => 'Icône À la une', 'fallback' => 'images/a-la-une.png'],
                            'blackFridayIcon' => ['label' => 'Icône Black Friday', 'fallback' => 'images/flame_transparent.png'],
                            'flashIcon' => ['label' => 'Icône vente flash', 'fallback' => 'images/flash-panier.png'],
                            'classicSaleIcon' => ['label' => 'Icône vente classique', 'fallback' => 'images/panier.png'],
                            'productsIcon' => ['label' => 'Icône Nos Produits', 'fallback' => 'images/nos-produit.png'],
                        ],
                        'Services & confiance' => [
                            'trustedSellerIcon' => ['label' => 'Icône vendeur vérifié', 'fallback' => 'images/svg.png'],
                            'deliveryIcon' => ['label' => 'Icône livraison suivie', 'fallback' => 'images/svg-copie.png'],
                            'paymentIcon' => ['label' => 'Icône paiement sécurisé', 'fallback' => 'images/svg-e.png'],
                            'supportIcon' => ['label' => 'Icône assistance WhatsApp', 'fallback' => 'images/svg-copie-2.png'],
                            'returnIcon' => ['label' => 'Icône retour / réclamation', 'fallback' => 'images/svg-2.png'],
                        ],
                        'Accueil & animations' => [
                            'aiIcon' => ['label' => 'Icône / image IA OVANIE', 'fallback' => 'images/ia.png'],
                            'workerIcon' => ['label' => 'Image ouvrier / assistance', 'fallback' => 'images/ouvrier1.png'],
                            'giftLeftIcon' => ['label' => 'Image cadeau gauche', 'fallback' => 'images/cadeau-a-gauche.png'],
                            'giftRightIcon' => ['label' => 'Image cadeau droite', 'fallback' => 'images/cadeau-a-droit.png'],
                            'businessIcon' => ['label' => 'Icône OVANIE Pro', 'fallback' => 'images/ovanie-business.png'],
                            'placeholderImage' => ['label' => 'Image placeholder produit', 'fallback' => 'images/placeholder.png'],
                        ],
                    ];

                @endphp

                <div class="settings-card settings-card-full settings-visual-master">
                    <div class="settings-card-head settings-card-head-large">
                        <span class="settings-card-icon">{!! $settingsIcons['image'] !!}</span>
                        <div>
                            <h2 class="settings-card-title">Images & icônes injectées dans l’accueil</h2>
                            <p>Chaque élément peut être remplacé depuis cette page puis utilisé automatiquement par le guest et le home.</p>
                        </div>
                    </div>

                    <div class="settings-visual-groups">
                        @foreach($visualFieldsGroups as $groupTitle => $visualFields)
                            <section class="visual-group-card">
                                <div class="visual-group-head">
                                    <h3>{{ $groupTitle }}</h3>
                                    <span>{{ count($visualFields) }} éléments</span>
                                </div>

                                <div class="visual-settings-grid">
                                    @foreach($visualFields as $fieldName => $field)
                                        @php
                                            $currentVisual = old($fieldName, $settings[$fieldName] ?? $field['fallback']);
                                        @endphp

                                        <div class="form-group visual-setting-item">
                                            <div class="visual-preview-frame">
                                                @if(!empty($currentVisual))
                                                    <img src="{{ $settingImageUrl($currentVisual, $field['fallback']) }}" alt="{{ $field['label'] }}" class="icon-preview-img">
                                                @else
                                                    <span class="visual-empty-icon">{!! $settingsIcons['image'] !!}</span>
                                                @endif
                                            </div>

                                            <div class="visual-setting-body">
                                                <label for="{{ $fieldName }}">{{ $field['label'] }}</label>
                                                <input type="file" id="{{ $fieldName }}" name="{{ $fieldName }}" accept="image/*" />
                                                <small class="form-help">Fallback : <code>{{ $field['fallback'] }}</code></small>
                                                @error($fieldName) <div class="field-error">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>

            </div>

            <div class="settings-actions">
                <button type="submit" class="btn-submit">
                    {!! $settingsIcons['save'] !!}
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </section>

    @if(session('success'))
        <div data-flash-success="{{ session('success') }}"></div>
    @endif

    @if($errors->any())
        <div data-flash-error="Une erreur est survenue. Vérifiez les champs du formulaire."></div>
    @endif
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_settings.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('admin/js/settings.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const adsTextarea = document.getElementById('homeTopAdsJson');
        const loadExampleBtn = document.getElementById('loadAdsExampleBtn');
        const clearBtn = document.getElementById('clearAdsJsonBtn');

        const exampleJson = `[
  {
    "type": "image",
    "title": "Bienvenue chez OVANIE",
    "text": "Découvrez nos meilleures offres du moment",
    "media": "/storage/ads/pub1.jpg",
    "link": "https://www.ovanie.com/",
    "duration": 5000,
    "active": true
  },
  {
    "type": "video",
    "title": "Offre spéciale construction",
    "text": "La vidéo s'affiche quelques secondes puis le défilement continue",
    "media": "/storage/ads/pub2.mp4",
    "link": "https://www.ovanie.com/",
    "duration": 8000,
    "active": true
  }
]`;

        if (loadExampleBtn && adsTextarea) {
            loadExampleBtn.addEventListener('click', function () {
                adsTextarea.value = exampleJson;
                adsTextarea.focus();
            });
        }

        if (clearBtn && adsTextarea) {
            clearBtn.addEventListener('click', function () {
                adsTextarea.value = '[]';
                adsTextarea.focus();
            });
        }
    });
</script>
@endpush
