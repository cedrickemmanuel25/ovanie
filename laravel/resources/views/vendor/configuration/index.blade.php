@extends('layouts.vendor')

@section('title', 'Finaliser ma boutique - OVANIE')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/vendor-shop-configuration.css') }}?v={{ @filemtime(public_path('css/vendor-shop-configuration.css')) ?: '1' }}">
@endpush

@section('content')
@php
    $logisticsDone = !$needsLogistics;
    $payoutDone = !$needsPayout;
    // Le mode choisi à l'ouverture de la boutique est repris : on ne repose pas la question,
    // on indique seulement ce qu'il reste à compléter.
    $chosenLogistics = $chosenLogistics ?? ($shop->hasChosenLogisticsMode() ? $shop->logistics_type : null);
    $remaining = (int) $needsLogistics + (int) $needsPayout;
    $logisticsTitle = match ($chosenLogistics) {
        'ovanie' => 'Confirmez le point de retrait de votre boutique',
        'seller' => 'Complétez votre livraison',
        default => 'Comment livrez-vous les commandes de votre boutique ?',
    };
    $logisticsIntro = match ($chosenLogistics) {
        'ovanie' => 'Vous avez déjà choisi OVANIE Logistics. Il reste à enregistrer la position exacte de la boutique pour que les livreurs puissent venir retirer les commandes.',
        'seller' => 'Vous avez déjà choisi votre propre logistique. Il reste à renseigner vos zones desservies, vos tarifs, vos délais et vos capacités.',
        default => 'Choisissez la solution qui correspond réellement à votre organisation.',
    };
@endphp
<div class="vsc-page">
    <div class="vsc-shell">
        <header class="vsc-hero">
            <div class="vsc-hero-copy">
                <span class="vsc-kicker">Configuration de votre boutique</span>
                <h1>{{ $remaining > 1 ? 'Finalisez deux réglages pour vendre' : 'Un dernier réglage pour vendre' }}</h1>
                <p>Votre boutique et vos produits sont conservés. @if($needsLogistics && $payoutDone)Votre reversement est déjà configuré : il reste seulement à terminer la livraison.@elseif($logisticsDone && $needsPayout)Votre livraison est déjà configurée : il reste seulement à terminer le reversement.@else Terminez la livraison puis le reversement pour que vos commandes puissent être traitées.@endif</p>
            </div>
            <div class="vsc-progress" aria-label="Progression de la configuration">
                <div class="vsc-progress-item {{ $logisticsDone ? 'done' : 'active' }}">
                    <span>{{ $logisticsDone ? '✓' : '1' }}</span>
                    <div><strong>Livraison</strong><small>{{ $logisticsDone ? 'Configurée' : 'À compléter' }}</small></div>
                </div>
                <i></i>
                <div class="vsc-progress-item {{ $payoutDone ? 'done' : (!$needsLogistics ? 'active' : '') }}">
                    <span>{{ $payoutDone ? '✓' : '2' }}</span>
                    <div><strong>Reversement</strong><small>{{ $payoutDone ? 'Configuré' : 'À compléter' }}</small></div>
                </div>
            </div>
        </header>

        @if(session('success'))<div class="vsc-alert success">{{ session('success') }}</div>@endif
        @if(session('info'))<div class="vsc-alert info">{{ session('info') }}</div>@endif
        @if(session('warning'))<div class="vsc-alert warning">{{ session('warning') }}</div>@endif
        @if($errors->any())<div class="vsc-alert danger"><strong>Vérifiez les informations :</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if($needsLogistics)
        <section class="vsc-card" id="logisticsStep">
            <div class="vsc-section-head">
                <div class="vsc-section-icon">1</div>
                <div><span class="vsc-overline">Livraison</span><h2>{{ $logisticsTitle }}</h2><p>{{ $logisticsIntro }}</p></div>
            </div>

            <form method="POST" action="{{ route('vendor.configuration.logistics') }}" id="vscLogisticsForm">
                @csrf
                @if($chosenLogistics)
                    {{-- Mode déjà choisi à l'ouverture de la boutique : pas de nouveau choix, seulement ce qu'il reste à compléter. --}}
                    <input type="radio" name="logistics_type" value="{{ $chosenLogistics }}" checked hidden>
                    <p class="vsc-chosen-mode">
                        <strong>{{ $chosenLogistics === 'ovanie' ? 'OVANIE Logistics' : 'Ma propre logistique' }}</strong>
                        <a href="{{ route('vendor.delivery.index') }}">Changer de mode de livraison</a>
                    </p>
                @else
                <div class="vsc-choice-grid">
                    <label class="vsc-choice-card">
                        <input type="radio" name="logistics_type" value="seller" @checked(old('logistics_type', $chosenLogistics)==='seller')>
                        <span class="vsc-choice-mark">✓</span>
                        <span class="vsc-choice-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg></span>
                        <strong>J’ai ma propre logistique</strong>
                        <p>Choisissez cette option si vous avez vos propres livreurs, chauffeurs ou un service capable de livrer vos clients.</p>
                        <ul><li>Vous définissez vos zones desservies</li><li>Vous renseignez vos tarifs et délais</li><li>Vous gardez votre organisation de livraison</li></ul>
                    </label>

                    <label class="vsc-choice-card featured">
                        <input type="radio" name="logistics_type" value="ovanie" @checked(old('logistics_type', $chosenLogistics)==='ovanie')>
                        <span class="vsc-choice-mark">✓</span>
                        <span class="vsc-choice-badge">Recommandé<span class="vsc-choice-badge-detail">&nbsp;si vous n’avez pas de livreur</span></span>
                        <span class="vsc-choice-icon">OV</span>
                        <strong>Utiliser OVANIE Logistics</strong>
                        <p>OVANIE organise le retrait à votre boutique et la livraison au client avec ses partenaires.</p>
                        <ul><li>Aucun tarif de livraison à créer</li><li>Votre point de retrait est enregistré à partir de votre position</li><li>Votre adresse exacte reste privée</li></ul>
                    </label>
                </div>
                @endif

                <div class="vsc-ovanie-panel" id="vscOvaniePanel" @if($chosenLogistics !== 'ovanie') hidden @endif>
                    <div class="vsc-location-status" id="vscLocationStatus">
                        <span class="vsc-location-dot"></span>
                        <div><strong>Localisation automatique de la boutique</strong><small>Votre position permet aux livreurs OVANIE de vous trouver facilement.</small></div>
                        <button type="button" id="vscRetryLocation">Détecter ma position</button>
                    </div>
                    <div class="vsc-fields">
                        <div class="vsc-field full"><label>Adresse / point de retrait</label><input name="address" id="vscAddress" value="{{ old('address', $shop->address) }}" placeholder="L’adresse détectée apparaîtra ici"></div>
                        <div class="vsc-field"><label>Commune</label><input name="commune" id="vscCommune" value="{{ old('commune', $shop->commune) }}" placeholder="Ex. Cocody"></div>
                        <div class="vsc-field"><label>Quartier</label><input name="district" id="vscDistrict" value="{{ old('district', $shop->district) }}" placeholder="Ex. Angré"></div>
                        <div class="vsc-field full"><label>Point de repère</label><input name="landmark" id="vscLandmark" value="{{ old('landmark', $shop->landmark) }}" placeholder="Ex. près de la pharmacie, en face de..."></div>
                    </div>
                    <input type="hidden" name="latitude" id="vscLatitude" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="vscLongitude" value="{{ old('longitude') }}">
                    <input type="hidden" name="geo_accuracy" id="vscAccuracy" value="{{ old('geo_accuracy') }}">
                    <input type="hidden" name="geo_captured_at" id="vscCapturedAt" value="{{ old('geo_captured_at') }}">
                </div>

                <div class="vsc-actions"><button class="vsc-primary" type="submit" id="vscLogisticsSubmit">Continuer</button></div>
            </form>
        </section>
        @endif

        @if(!$needsLogistics && $needsPayout)
        <section class="vsc-card" id="payoutStep">
            <div class="vsc-section-head">
                <div class="vsc-section-icon">2</div>
                <div><span class="vsc-overline">Étape 2</span><h2>Comment souhaitez-vous recevoir vos reversements ?</h2><p>Le calendrier indique quand OVANIE rend vos ventes éligibles au reversement. Le compte de réception doit vous appartenir.</p></div>
            </div>

            <form method="POST" action="{{ route('vendor.configuration.payout') }}">
                @csrf
                <div class="vsc-subtitle">Calendrier de reversement</div>
                <div class="vsc-choice-grid payout">
                    <label class="vsc-choice-card compact">
                        <input type="radio" name="payment_mode" value="post_delivery" @checked(old('payment_mode',$shop->payment_mode)==='post_delivery') required>
                        <span class="vsc-choice-mark">✓</span><span class="vsc-choice-icon">72h</span><strong>Après chaque livraison</strong><p>Chaque vente éligible est préparée pour reversement sous 72 h après confirmation de la livraison.</p>
                    </label>
                    <label class="vsc-choice-card compact">
                        <input type="radio" name="payment_mode" value="weekly" @checked(old('payment_mode',$shop->payment_mode)==='weekly') required>
                        <span class="vsc-choice-mark">✓</span><span class="vsc-choice-icon">7J</span><strong>Reversement hebdomadaire</strong><p>Les ventes éligibles de la période sont regroupées dans un reversement hebdomadaire.</p>
                    </label>
                </div>

                <div class="vsc-payout-box">
                    <div class="vsc-subtitle">Compte Mobile Money de réception</div>
                    <p class="vsc-help">Vos gains vous sont versés par Mobile Money sur ce compte.</p>
                    <div class="vsc-fields">
                        <div class="vsc-field"><label>Opérateur</label><select name="mm_operator" required>@foreach(['orange'=>'Orange Money','mtn'=>'MTN MoMo','wave'=>'Wave','moov'=>'Moov Money'] as $value=>$label)<option value="{{ $value }}" @selected(old('mm_operator',$shop->mm_operator)===$value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="vsc-field"><label>Numéro de réception</label><input name="mm_number" value="{{ old('mm_number',$shop->mm_number) }}" placeholder="01 23 45 67 89" required></div>
                        <div class="vsc-field full"><label>Nom du titulaire</label><input name="mm_holder" value="{{ old('mm_holder',$shop->mm_holder) }}" placeholder="Nom tel qu’enregistré chez l’opérateur" required></div>
                    </div>
                    <label class="vsc-confirm"><input type="checkbox" name="seller_confirmation" value="1" required><span>Je confirme que ce compte de réception m’appartient et que les informations sont correctes.</span></label>
                </div>
                <div class="vsc-actions"><button class="vsc-primary" type="submit">Terminer la configuration</button></div>
            </form>
        </section>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
window.OVANIE_VENDOR_CONFIGURATION = {
    resolveUrl: @json(route('vendor.delivery.location.resolve')),
    csrf: @json(csrf_token())
};
</script>
<script src="{{ asset('js/vendor-shop-configuration.js') }}?v={{ @filemtime(public_path('js/vendor-shop-configuration.js')) ?: '1' }}"></script>
@endpush
