@extends('layouts.logistics')
@section('title','Paramètres logistiques')
@section('crumb','Pilotage › Paramètres logistiques')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-management.css') }}?v={{ @filemtime(public_path('css/logistics-management.css')) ?: '1' }}">
@endpush
@section('content')
@php
    $g = $settings;
@endphp
<main class="mg-page settings-page pilotage-v2">
<form method="POST" action="{{ route('logistics.control.settings.save') }}">@csrf
    <header class="mg-titlebar pilotage-titlebar">
        <div><h1>Paramètres logistiques</h1><p>Réglez uniquement les paramètres réellement utilisés par les opérations OVANIE Logistics.</p></div>
        <div class="mg-actions"><button class="mg-btn" type="reset"><x-operations.icon name="refresh"/>Annuler</button><button class="mg-btn mg-btn-primary" type="submit"><x-operations.icon name="save"/>Enregistrer les modifications</button></div>
    </header>

    <nav class="settings-tabs pilotage-settings-tabs">
        <a href="#parametres-generaux" class="active">Informations</a>
        <a href="#delais-alertes">Délais &amp; alertes</a>
        <a href="#gps-geolocalisation">GPS</a>
        <a href="#regles-operationnelles">Règles opérationnelles</a>
    </nav>

    <section class="settings-grid pilotage-settings-grid">
        <div>
            <article class="mg-card settings-card" id="parametres-generaux">
                <header><h3><x-operations.icon name="building"/> Informations OVANIE Logistics</h3><p>Coordonnées enregistrées pour l’espace logistique.</p></header>
                <div class="mg-form-grid two">
                    @foreach(($g['general'] ?? collect()) as $setting)
                        <label>{{ $setting->label }}
                            <input type="{{ $setting->type === 'email' ? 'email' : 'text' }}" name="settings[{{ $setting->setting_key }}]" value="{{ $setting->value }}" @if($setting->setting_key==='platform_name') readonly @endif>
                            @if($setting->description)<small>{{ $setting->description }}</small>@endif
                        </label>
                    @endforeach
                    <div class="pilotage-brand-card">
                        <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="#00a66b" d="M28 3C17 4 4 6 4 16c0 8 8 12 15 8 6-4 8-11 9-21Z"/><path fill="#003b35" d="M22 10c-6 2-10 6-12 11 5-1 11-5 12-11Z"/><path fill="#008955" d="M6 22c6-1 11 0 15 4-8 5-14 2-15-4Z"/></svg>
                        <strong>OVANIE</strong><span>Logistics</span>
                    </div>
                </div>
            </article>

            <article class="mg-card settings-card" id="delais-alertes">
                <header><h3><x-operations.icon name="clock"/> Seuils opérationnels</h3><p>Ces valeurs sont utilisées directement par le moteur de notifications.</p></header>
                <div class="mg-form-grid two">
                    @foreach(($g['delays'] ?? collect()) as $setting)
                        <label>{{ $setting->label }}<span class="unit-input"><input type="number" min="1" step="1" name="settings[{{ $setting->setting_key }}]" value="{{ $setting->value }}"><b>minutes</b></span><small>{{ $setting->description }}</small></label>
                    @endforeach
                </div>
            </article>

            <article class="mg-card settings-card" id="gps-geolocalisation">
                <header><h3><x-operations.icon name="pin"/> Suivi GPS</h3><p>Contrôle du suivi des missions actives et de la perte de signal.</p></header>
                @foreach(($g['gps'] ?? collect()) as $setting)
                    @if($setting->type === 'boolean')
                        <label class="setting-toggle"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><input type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" @checked($setting->value==='1')></label>
                    @else
                        <label class="setting-inline"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><span class="unit-input"><input type="number" min="1" step="1" name="settings[{{ $setting->setting_key }}]" value="{{ $setting->value }}"><b>minutes</b></span></label>
                    @endif
                @endforeach
            </article>
        </div>

        <div>
            <article class="mg-card settings-card">
                <header><h3><x-operations.icon name="bell"/> Génération des notifications</h3><p>Active ou suspend le moteur automatique d’alertes logistiques.</p></header>
                @foreach(($g['operation'] ?? collect()) as $setting)
                    <label class="setting-toggle"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><input type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" @checked($setting->value==='1')></label>
                @endforeach
            </article>

            <article class="mg-card settings-card">
                <header><h3><x-operations.icon name="warning"/> Types d’alertes</h3><p>Chaque option ci-dessous agit directement sur le centre de notifications.</p></header>
                @foreach(($g['alerts'] ?? collect()) as $setting)
                    <label class="setting-toggle"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><input type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" @checked($setting->value==='1')></label>
                @endforeach
            </article>

            <article class="mg-card settings-card escalation-card">
                <header><h3><x-operations.icon name="warning"/> Priorités &amp; escalades</h3><p>Ces règles modifient réellement la priorité des alertes générées.</p></header>
                @foreach(($g['escalation'] ?? collect()) as $i=>$setting)
                    <label class="setting-toggle tone{{ $i }}"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><input type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" @checked($setting->value==='1')></label>
                @endforeach
            </article>
        </div>

        <div id="regles-operationnelles">
            <article class="mg-card settings-card vehicle-rule-card">
                <header><h3><x-operations.icon name="truck"/> Règles véhicules OVANIE</h3><p>Règles métier fixes utilisées pour déterminer le véhicule compatible avec le poids total.</p></header>
                <div class="vehicle-rule-grid">
                    @foreach($vehicleRules as $vehicle)
                        @php
                            $code = (string) ($vehicle['code'] ?? '');
                            $icon = $code === 'moto' ? 'moto' : ($code === 'tricycle' ? 'tour' : 'truck');
                            $maxWeight = $vehicle['max_weight_kg'] ?? null;
                            $capacity = $maxWeight !== null
                                ? '≤ '.number_format((float) $maxWeight, 0, ',', ' ').' kg'
                                : ($code === 'camion_10t' ? '> 3 000 kg' : 'Selon configuration');
                        @endphp
                        <div><span><x-operations.icon :name="$icon"/><b>{{ $vehicle['label'] }}</b></span><strong>{{ $capacity }}</strong></div>
                    @endforeach
                </div>
                <div class="pilotage-system-note"><x-operations.icon name="check-circle"/><span>La compatibilité véhicule est une règle système obligatoire et ne peut pas être désactivée depuis Pilotage.</span></div>
            </article>

            <article class="mg-card settings-card">
                <header><h3><x-operations.icon name="route"/> Création des tournées</h3><p>Le réglage ci-dessous devient la valeur par défaut du formulaire de création d’une tournée.</p></header>
                @foreach(($g['rules'] ?? collect()) as $setting)
                    <label class="setting-toggle"><span><b>{{ $setting->label }}</b><small>{{ $setting->description }}</small></span><input type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" @checked($setting->value==='1')></label>
                @endforeach
            </article>

            <article class="mg-card settings-card">
                <header><h3><x-operations.icon name="check-circle"/> Contrôles obligatoires</h3><p>Ces contrôles sont imposés par le fonctionnement OVANIE et ne sont pas de simples options d’interface.</p></header>
                <div class="pilotage-rule-note"><x-operations.icon name="truck"/><div><strong>Compatibilité du véhicule</strong><span>Une mission ne peut pas être affectée à un véhicule dont la capacité est insuffisante.</span></div></div>
                <div class="pilotage-rule-note"><x-operations.icon name="check-circle"/><div><strong>Traçabilité de livraison</strong><span>Les statuts, affectations et preuves disponibles restent liés aux vraies missions de livraison.</span></div></div>
            </article>
        </div>
    </section>
</form>
</main>
@endsection
@push('scripts')
<script>
(() => {
    const tabs = [...document.querySelectorAll('.pilotage-settings-tabs a')];
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(item => item.classList.remove('active'));
        tab.classList.add('active');
    }));
})();
</script>
@endpush
