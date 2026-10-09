@extends('admin.layouts.app')

@section('title', 'Configuration opérationnelle boutique | Administration OVANIE')
@section('page-title', 'Configuration opérationnelle')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_shop_operational_configuration.css') }}">
@endpush

@section('content')
@php
    $currentLogistics = old('logistics_type', in_array($shop->logistics_type, ['ovanie', 'seller'], true) ? $shop->logistics_type : 'ovanie');
    $profile = $shop->sellerDeliveryProfile;
    $operatorLabels = [
        'orange' => 'Orange Money',
        'mtn' => 'MTN MoMo',
        'wave' => 'Wave',
        'moov' => 'Moov Money',
    ];
@endphp

<div class="ops-config-page" data-ops-config>
    <header class="ops-config-header">
        <div>
            <a href="{{ route('admin.shops.show', $shop) }}" class="ops-back-link">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h11"/></svg>
                Retour au dossier boutique
            </a>
            <span class="ops-eyebrow">Régularisation administrateur</span>
            <h2>Configurer la livraison et le reversement</h2>
            <p>Vous pouvez compléter ces informations pour le vendeur. Les données techniques de géolocalisation restent invisibles et sont utilisées uniquement en arrière-plan par OVANIE Logistics.</p>
        </div>
        <aside class="ops-shop-card">
            <span class="ops-shop-avatar">{{ strtoupper(substr($shop->name ?: 'B', 0, 1)) }}</span>
            <div>
                <small>Boutique concernée</small>
                <strong>{{ $shop->name ?: 'Boutique sans nom' }}</strong>
                <span>{{ $shop->user?->name ?: 'Responsable non renseigné' }}</span>
            </div>
        </aside>
    </header>

    @if($errors->any())
        <div class="ops-alert ops-alert-danger">
            <strong>La configuration n’a pas été enregistrée.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.shops.operational-configuration.update', $shop) }}" class="ops-form">
        @csrf
        @method('PUT')

        <section class="ops-section">
            <div class="ops-section-title">
                <span class="ops-step">1</span>
                <div>
                    <h3>Mode de livraison</h3>
                    <p>Choisissez qui prend réellement en charge les commandes de cette boutique.</p>
                </div>
            </div>

            <div class="ops-choice-grid">
                <label class="ops-choice-card" data-logistics-choice="ovanie">
                    <input type="radio" name="logistics_type" value="ovanie" @checked($currentLogistics === 'ovanie')>
                    <span class="ops-choice-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h12v10H3z"/><path d="M15 10h4l2 3v3h-6z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                    </span>
                    <span class="ops-choice-content">
                        <strong>OVANIE Logistics</strong>
                        <small>Le vendeur ne dispose pas de sa propre organisation de livraison. OVANIE organise la collecte et la livraison.</small>
                        <em>Recommandé si le vendeur n’a pas de livreurs.</em>
                    </span>
                    <span class="ops-choice-check">✓</span>
                </label>

                <label class="ops-choice-card" data-logistics-choice="seller">
                    <input type="radio" name="logistics_type" value="seller" @checked($currentLogistics === 'seller')>
                    <span class="ops-choice-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                    </span>
                    <span class="ops-choice-content">
                        <strong>Logistique du vendeur</strong>
                        <small>Le vendeur possède ses propres livreurs ou une organisation capable d’assurer ses livraisons.</small>
                        <em>Zones, tarifs et délais obligatoires.</em>
                    </span>
                    <span class="ops-choice-check">✓</span>
                </label>
            </div>

            <div class="ops-dynamic-panel" data-panel="ovanie">
                <div class="ops-info-banner">
                    <span class="ops-info-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2"/></svg>
                    </span>
                    <div>
                        <strong>Aucun GPS administrateur et aucune carte à manipuler</strong>
                        <p>Copiez la position depuis Google Maps. OVANIE extrait ou calcule automatiquement les coordonnées techniques en arrière-plan pour la logistique et l’application livreur.</p>
                    </div>
                </div>

                <div class="ops-grid ops-grid-two">
                    <div class="ops-field ops-field-wide">
                        <label for="google_maps_reference">Position Google Maps <span>*</span></label>
                        <input id="google_maps_reference" name="google_maps_reference" type="text"
                               value="{{ old('google_maps_reference', $shop->admin_google_maps_reference) }}"
                               placeholder="Ex. 5.348950, -4.003420 ou lien Google Maps complet">
                        <small>Les latitude/longitude enregistrées par OVANIE ne sont pas affichées dans l’espace admin.</small>
                        @error('google_maps_reference')<span class="ops-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ops-field ops-field-wide">
                        <label for="address">Adresse / point de retrait <span>*</span></label>
                        <input id="address" name="address" type="text" value="{{ old('address', $shop->address) }}" placeholder="Ex. Rue des Jardins, près de la station...">
                        @error('address')<span class="ops-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ops-field">
                        <label for="commune">Commune <span>*</span></label>
                        <input id="commune" name="commune" type="text" value="{{ old('commune', $shop->commune) }}" placeholder="Ex. Cocody">
                        @error('commune')<span class="ops-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ops-field">
                        <label for="district">Quartier <span>*</span></label>
                        <input id="district" name="district" type="text" value="{{ old('district', $shop->district) }}" placeholder="Ex. Riviera 3">
                        @error('district')<span class="ops-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="ops-field ops-field-wide">
                        <label for="landmark">Point de repère</label>
                        <input id="landmark" name="landmark" type="text" value="{{ old('landmark', $shop->landmark) }}" placeholder="Ex. Après le carrefour, bâtiment bleu">
                    </div>
                </div>
            </div>

            <div class="ops-dynamic-panel" data-panel="seller">
                <div class="ops-info-banner is-neutral">
                    <span class="ops-info-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8"/></svg>
                    </span>
                    <div>
                        <strong>La livraison sera assurée par le vendeur</strong>
                        <p>Renseignez au minimum sa capacité et une zone tarifée afin que le checkout puisse calculer correctement la livraison.</p>
                    </div>
                </div>

                <div class="ops-grid ops-grid-two">
                    <div class="ops-field">
                        <label for="seller_default_delay">Délai habituel <span>*</span></label>
                        <input id="seller_default_delay" name="seller_default_delay" type="text" value="{{ old('seller_default_delay', $profile?->default_delay) }}" placeholder="Ex. 24 à 48 h">
                    </div>
                    <div class="ops-field">
                        <label for="seller_zone_commune">Zone principale desservie <span>*</span></label>
                        <input id="seller_zone_commune" name="seller_zone_commune" type="text" value="{{ old('seller_zone_commune', $sellerZone?->commune) }}" placeholder="Ex. Cocody">
                    </div>
                    <div class="ops-field">
                        <label for="seller_max_weight_kg">Poids maximum par livraison (kg) <span>*</span></label>
                        <input id="seller_max_weight_kg" name="seller_max_weight_kg" type="number" step="0.01" min="0.01" value="{{ old('seller_max_weight_kg', $profile?->max_weight_kg) }}">
                    </div>
                    <div class="ops-field">
                        <label for="seller_max_volume_m3">Volume maximum (m³) <span>*</span></label>
                        <input id="seller_max_volume_m3" name="seller_max_volume_m3" type="number" step="0.001" min="0.001" value="{{ old('seller_max_volume_m3', $profile?->max_volume_m3) }}">
                    </div>
                    <div class="ops-field">
                        <label for="seller_zone_price">Tarif de livraison de la zone (FCFA) <span>*</span></label>
                        <input id="seller_zone_price" name="seller_zone_price" type="number" step="1" min="0" value="{{ old('seller_zone_price', $sellerZone?->delivery_price) }}">
                    </div>
                    <div class="ops-field">
                        <label for="seller_zone_delay">Délai de la zone <span>*</span></label>
                        <input id="seller_zone_delay" name="seller_zone_delay" type="text" value="{{ old('seller_zone_delay', $sellerZone?->estimated_delay) }}" placeholder="Ex. Livraison sous 24 h">
                    </div>
                    <div class="ops-field ops-field-wide">
                        <label for="seller_conditions">Conditions de livraison <span>*</span></label>
                        <textarea id="seller_conditions" name="seller_conditions" rows="4" placeholder="Conditions, restrictions, horaires, modalités de remise...">{{ old('seller_conditions', $profile?->conditions) }}</textarea>
                    </div>
                </div>
            </div>
        </section>

        <section class="ops-section">
            <div class="ops-section-title">
                <span class="ops-step">2</span>
                <div>
                    <h3>Reversement du vendeur</h3>
                    <p>Configurez quand et sur quel compte OVANIE reverse les ventes éligibles.</p>
                </div>
            </div>

            <div class="ops-payout-choice">
                <label class="ops-mini-choice">
                    <input type="radio" name="payment_mode" value="{{ \App\Models\Shop::PAYMENT_POST_DELIVERY }}" @checked(old('payment_mode', $shop->payment_mode ?: \App\Models\Shop::PAYMENT_POST_DELIVERY) === \App\Models\Shop::PAYMENT_POST_DELIVERY)>
                    <span><strong>Après chaque livraison</strong><small>Reversement éligible sous 72 h après confirmation de la livraison.</small></span>
                </label>
                <label class="ops-mini-choice">
                    <input type="radio" name="payment_mode" value="{{ \App\Models\Shop::PAYMENT_WEEKLY }}" @checked(old('payment_mode', $shop->payment_mode) === \App\Models\Shop::PAYMENT_WEEKLY)>
                    <span><strong>Hebdomadaire</strong><small>Les ventes éligibles sont regroupées dans un reversement périodique.</small></span>
                </label>
            </div>

            <div class="ops-grid ops-grid-two ops-payout-grid">
                <div class="ops-field">
                    <label for="mm_operator">Compte de réception <span>*</span></label>
                    <select id="mm_operator" name="mm_operator" required>
                        <option value="">Choisir l’opérateur</option>
                        @foreach($operatorLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('mm_operator', $shop->mm_operator) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-field">
                    <label for="mm_number">Numéro Mobile Money <span>*</span></label>
                    <input id="mm_number" name="mm_number" type="text" value="{{ old('mm_number', $shop->mm_number) }}" placeholder="Ex. 07 00 00 00 00" required>
                </div>
                <div class="ops-field ops-field-wide">
                    <label for="mm_holder">Nom du titulaire <span>*</span></label>
                    <input id="mm_holder" name="mm_holder" type="text" value="{{ old('mm_holder', $shop->mm_holder) }}" placeholder="Nom complet du titulaire du compte" required>
                </div>
            </div>
        </section>

        <section class="ops-confirmation-card">
            <label>
                <input type="checkbox" name="admin_confirmation" value="1" @checked(old('admin_confirmation'))>
                <span>
                    <strong>Je confirme avoir vérifié ces informations avec le vendeur.</strong>
                    <small>Cette action configure la boutique au nom du vendeur et est enregistrée comme intervention administrative.</small>
                </span>
            </label>
        </section>

        <footer class="ops-actions">
            <a href="{{ route('admin.shops.show', $shop) }}" class="ops-button ops-button-secondary">Annuler</a>
            <button type="submit" class="ops-button ops-button-primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                Enregistrer et régulariser la boutique
            </button>
        </footer>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('admin/js/admin_shop_operational_configuration.js') }}" defer></script>
@endpush
