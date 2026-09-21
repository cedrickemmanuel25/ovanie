@extends('layouts.logistics')
@section('title','Fiche partenaire')
@section('crumb','Partenaires > Fiche partenaire')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-partners.css') }}?v={{ @filemtime(public_path('css/logistics-partners.css')) ?: '1' }}">
@endpush

@section('content')
@php
    $profile=$row['profile'];
    $contractAvailable=(bool)($profile?->contract_path);
    $canAssign=$row['is_active'] && ($profile?->can_receive_missions ?? true);
@endphp
<main class="partner-page partner-detail-page">
    <header class="partner-titlebar">
        <div>
            <a class="partner-back-link" href="{{ route('logistics.partners') }}"><x-partners.icon name="back"/> Retour aux partenaires</a>
            <h1>Fiche partenaire</h1>
            <p>Véhicules, couverture, missions et performance du partenaire.</p>
        </div>
        <div class="partner-title-actions">
            @if($carrier->phone)<a class="partner-btn partner-btn-light" href="tel:{{ preg_replace('/\s+/','',$carrier->phone) }}"><x-partners.icon name="phone"/> Contacter</a>@endif
            <button class="partner-btn partner-btn-primary" type="button" data-partner-open="mission-assign" @disabled(!$canAssign)><x-partners.icon name="route"/> Attribuer des missions</button>
        </div>
    </header>

    @if($errors->any())
        <div class="partner-alert partner-alert-danger"><strong>Action non enregistrée.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="partner-detail-kpis">
        <article class="partner-card partner-identity-kpi">
            <span class="partner-logo-large">
                @if($row['logo_url'])<img src="{{ $row['logo_url'] }}" alt="">@else<span>{{ mb_strtoupper(mb_substr($carrier->name,0,2)) }}</span>@endif
            </span>
            <div><span>Partenaire</span><strong>{{ $carrier->name }}</strong><small>{{ $row['code'] }}</small></div>
        </article>
        <article class="partner-kpi"><span class="partner-kpi-icon blue"><x-partners.icon name="{{ $row['type']==='specialized'?'hardhat':'briefcase' }}"/></span><div><span>Type</span><strong class="detail-value">{{ $row['type_label'] }}</strong><small>transporteur externe</small></div></article>
        <article class="partner-kpi"><span class="partner-kpi-icon green"><x-partners.icon name="map"/></span><div><span>Couverture</span><strong>{{ $row['coverage']->count() }}</strong><small>zone(s) configurée(s)</small></div></article>
        <article class="partner-kpi"><span class="partner-kpi-icon orange"><x-partners.icon name="truck"/></span><div><span>Véhicules actifs</span><strong>{{ $row['vehicle_count'] }}</strong><small>dans la flotte partenaire</small></div></article>
        <article class="partner-kpi"><span class="partner-kpi-icon purple"><x-partners.icon name="activity"/></span><div><span>SLA 30 jours</span><strong>{{ $row['sla_percent']===null?'—':number_format($row['sla_percent'],1,',',' ').'%' }}</strong><small>calculé sur les livraisons datées</small></div></article>
        <article class="partner-kpi"><span class="partner-kpi-icon {{ $row['is_active']?'green':'red' }}"><x-partners.icon name="{{ $row['is_active']?'check':'ban' }}"/></span><div><span>Statut</span><strong class="detail-value">{{ $row['status_label'] }}</strong><small>{{ $row['active_missions'] }} mission(s) en cours</small></div></article>
    </section>

    <section class="partner-detail-layout">
        <div class="partner-detail-main">
            <article class="partner-card">
                <header class="partner-card-head"><div><h2><x-partners.icon name="briefcase"/> Informations du partenaire</h2></div></header>
                <div class="partner-info-grid">
                    <dl>
                        <div><dt>Nom</dt><dd>{{ $carrier->name }}</dd></div>
                        <div><dt>Contact principal</dt><dd>{{ $profile?->contact_name ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Téléphone</dt><dd>{{ $carrier->phone ?: 'Non renseigné' }}</dd></div>
                        <div><dt>E-mail</dt><dd>{{ $profile?->email ?: 'Non renseigné' }}</dd></div>
                        <div><dt>Type</dt><dd>{{ $row['type_label'] }}</dd></div>
                    </dl>
                    <dl>
                        <div><dt>Adresse</dt><dd>{{ $profile?->address ?: $carrier->city ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Zone principale</dt><dd>{{ $profile?->main_zone ?: $carrier->city ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Référence contrat</dt><dd>{{ $profile?->contract_reference ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Date d’intégration</dt><dd>{{ $profile?->integrated_at?->format('d/m/Y') ?: 'Non renseignée' }}</dd></div>
                        <div><dt>Objectif SLA</dt><dd>{{ ($profile && $profile->sla_percent>0)?number_format($profile->sla_percent,1,',',' ').'%':'Non renseigné' }}</dd></div>
                    </dl>
                </div>
                @if($profile?->description || $profile?->observations)
                    <div class="partner-info-notes">
                        @if($profile?->description)<p><strong>Description :</strong> {{ $profile->description }}</p>@endif
                        @if($profile?->observations)<p><strong>Observation :</strong> {{ $profile->observations }}</p>@endif
                    </div>
                @endif
            </article>

            <article class="partner-card partner-table-card">
                <header class="partner-card-head"><div><h2><x-partners.icon name="truck"/> Véhicules du partenaire</h2><p>{{ $carrier->vehicles->where('is_active',true)->count() }} véhicule(s) actif(s)</p></div><button type="button" class="partner-btn partner-btn-light" data-partner-open="partner-vehicle"><x-partners.icon name="plus"/> Ajouter un véhicule</button></header>
                <div class="partner-table-wrap">
                    <table class="partner-table compact">
                        <thead><tr><th>Type</th><th>Immatriculation</th><th>Capacité</th><th>Volume</th><th>Statut</th></tr></thead>
                        <tbody>
                            @forelse($carrier->vehicles->sortByDesc('is_active') as $vehicle)
                                <tr>
                                    <td><span class="partner-vehicle"><x-partners.icon name="truck"/>{{ $vehicle->type ?: 'Non renseigné' }}</span></td>
                                    <td>{{ $vehicle->plate_number ?: '—' }}</td>
                                    <td>{{ $vehicle->capacity_ton>0 ? number_format($vehicle->capacity_ton*1000,0,',',' ').' kg' : '—' }}</td>
                                    <td>{{ $vehicle->volume_m3>0 ? number_format($vehicle->volume_m3,2,',',' ').' m³' : '—' }}</td>
                                    <td><span class="partner-status {{ $vehicle->is_active?'active':'inactive' }}"><i></i>{{ $vehicle->is_active?'Actif':'Inactif' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="partner-empty"><x-partners.icon name="truck"/><strong>Aucun véhicule enregistré</strong><span>Aucun véhicule n’est actuellement enregistré pour ce partenaire.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="partner-card partner-table-card">
                <header class="partner-card-head"><div><h2><x-partners.icon name="route"/> Historique des missions</h2><p>{{ $row['mission_count'] }} mission(s) réelle(s) affectée(s) au partenaire</p></div></header>
                <div class="partner-table-wrap">
                    <table class="partner-table compact">
                        <thead><tr><th>Mission</th><th>Destination</th><th>Véhicule</th><th>Livreur</th><th>Date</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                            @forelse($missions as $mission)
                                <tr>
                                    <td><strong>{{ $mission['reference'] }}</strong></td>
                                    <td>{{ $mission['destination'] }}</td>
                                    <td>{{ $mission['vehicle'] }}</td>
                                    <td>{{ $mission['driver'] }}</td>
                                    <td>{{ $mission['date']?->format('d/m/Y H:i') ?: '—' }}</td>
                                    <td><span class="partner-result {{ $mission['status_key'] }}">{{ $mission['status'] }}</span></td>
                                    <td>@if($mission['order_item_id'])<a class="partner-icon-btn" title="Ouvrir le suivi" href="{{ route('logistics.tracking.mission',$mission['order_item_id']) }}"><x-partners.icon name="eye"/></a>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="partner-empty"><x-partners.icon name="route"/><strong>Aucune mission enregistrée</strong><span>Les futures expéditions attribuées à ce transporteur apparaîtront ici.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </div>

        <aside class="partner-side">
            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="activity"/> Performance</h3></header>
                <div class="partner-metrics">
                    <div><x-partners.icon name="activity"/><strong>{{ $row['sla_percent']===null?'—':number_format($row['sla_percent'],1,',',' ').'%' }}</strong><span>SLA 30 jours</span></div>
                    <div><x-partners.icon name="check"/><strong>{{ $row['successful_deliveries'] }}</strong><span>Livraisons réussies</span></div>
                    <div><x-partners.icon name="clock"/><strong>{{ $row['delays_count'] }}</strong><span>Retards 30 jours</span></div>
                    <div><x-partners.icon name="route"/><strong>{{ $row['active_missions'] }}</strong><span>Missions en cours</span></div>
                </div>
            </article>

            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="map"/> Couverture</h3></header>
                <div class="partner-zone-list">
                    @forelse($row['coverage'] as $zone)<span><x-partners.icon name="pin"/>{{ $zone }}</span>@empty<p class="partner-empty-small">Aucune couverture configurée.</p>@endforelse
                </div>
            </article>

            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="truck"/> Répartition de la flotte</h3></header>
                <div class="partner-bars">
                    @php($fleetMax=max(1,(int)$fleet->max()))
                    @forelse($fleet as $type=>$count)<div><span>{{ $type }}</span><i><b style="width:{{ round(($count/$fleetMax)*100) }}%"></b></i><strong>{{ $count }}</strong></div>@empty<p class="partner-empty-small">Aucun véhicule actif.</p>@endforelse
                </div>
            </article>

            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="settings"/> Actions</h3></header>
                <div class="partner-quick-actions">
                    @if($contractAvailable)<a href="{{ route('logistics.partners.contract',$carrier->code ?: $carrier->id) }}"><x-partners.icon name="file"/> Télécharger le contrat</a>@else<span class="disabled"><x-partners.icon name="file"/> Contrat non disponible</span>@endif
                    <button type="button" data-partner-open="partner-note"><x-partners.icon name="note"/> Ajouter une note</button>
                    <form method="POST" action="{{ route('logistics.partners.status',$carrier->code ?: $carrier->id) }}">@csrf<button class="{{ $row['is_active']?'danger':'success' }}" type="submit"><x-partners.icon name="{{ $row['is_active']?'ban':'check' }}"/>{{ $row['is_active']?'Suspendre':'Réactiver' }} le partenaire</button></form>
                </div>
            </article>

            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="note"/> Notes internes</h3></header>
                <div class="partner-notes">
                    @forelse($profile?->internal_notes ?? [] as $note)
                        <div><span>{{ mb_strtoupper(mb_substr($note['author']??'OV',0,2)) }}</span><p><strong>{{ $note['author']??'Équipe logistique' }}</strong><small>{{ $note['date']??'' }}</small>{{ $note['text']??'' }}</p></div>
                    @empty<p class="partner-empty-small">Aucune note interne.</p>@endforelse
                </div>
            </article>
        </aside>
    </section>
</main>


<div class="partner-modal" id="partner-vehicle" hidden>
    <form class="partner-modal-dialog partner-modal-small" method="POST" action="{{ route('logistics.partners.vehicles.store',$carrier->code ?: $carrier->id) }}">
        @csrf
        <header class="partner-modal-head"><div><h2>Ajouter un véhicule</h2><p>{{ $carrier->name }}</p></div><button type="button" data-partner-close><x-partners.icon name="x"/></button></header>
        <div class="partner-modal-body">
            <section class="partner-form-section" style="grid-column:1/-1">
                <h3><x-partners.icon name="truck"/> Informations du véhicule</h3>
                <div class="partner-form-grid two">
                    <label>Type <sup>*</sup><select name="type" required><option value="">Sélectionner</option>@foreach(['Moto','Tricycle','Pickup','Camion 3T','Camion 10T'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
                    <label>Immatriculation<input name="plate_number" placeholder="Ex. CI 01 AB 1234"></label>
                    <label>Capacité (kg) <sup>*</sup><input type="number" name="capacity_kg" min="1" step="1" required></label>
                    <label>Volume (m³)<input type="number" name="volume_m3" min="0" step="0.01"></label>
                    <div class="full partner-check-list"><label><input type="checkbox" name="is_active" value="1" checked> Véhicule actif</label></div>
                </div>
            </section>
        </div>
        <footer class="partner-modal-foot"><button class="partner-btn partner-btn-light" type="button" data-partner-close>Annuler</button><button class="partner-btn partner-btn-primary" type="submit"><x-partners.icon name="check"/> Enregistrer le véhicule</button></footer>
    </form>
</div>

<div class="partner-modal" id="partner-note" hidden>
    <form class="partner-modal-dialog partner-modal-small" method="POST" action="{{ route('logistics.partners.notes',$carrier->code ?: $carrier->id) }}">
        @csrf
        <header class="partner-modal-head"><div><h2>Ajouter une note interne</h2><p>{{ $carrier->name }}</p></div><button type="button" data-partner-close><x-partners.icon name="x"/></button></header>
        <div class="partner-modal-body"><label class="partner-note-field">Note<textarea name="note" required maxlength="1000" placeholder="Information utile à l’équipe logistique..."></textarea></label></div>
        <footer class="partner-modal-foot"><button class="partner-btn partner-btn-light" type="button" data-partner-close>Annuler</button><button class="partner-btn partner-btn-primary" type="submit"><x-partners.icon name="check"/> Enregistrer</button></footer>
    </form>
</div>

<div class="partner-modal" id="mission-assign" hidden>
    <form class="partner-modal-dialog partner-assign-dialog" method="POST" action="{{ route('logistics.partners.missions.assign',$carrier->code ?: $carrier->id) }}" data-partner-assignment>
        @csrf
        <header class="partner-modal-head"><div><h2>Attribuer des missions</h2><p>Seules les expéditions non attribuées et compatibles avec la couverture peuvent être sélectionnées.</p></div><button type="button" data-partner-close><x-partners.icon name="x"/></button></header>
        <div class="partner-assignment-summary">
            <span class="partner-logo-large">@if($row['logo_url'])<img src="{{ $row['logo_url'] }}" alt="">@else<span>{{ mb_strtoupper(mb_substr($carrier->name,0,2)) }}</span>@endif</span>
            <div><small>Partenaire</small><strong>{{ $carrier->name }}</strong></div>
            <div><small>Couverture</small><strong>{{ $row['coverage']->count() }} zone(s)</strong></div>
            <div><small>Véhicules actifs</small><strong>{{ $row['vehicle_count'] }}</strong></div>
            <div><small>SLA 30 jours</small><strong>{{ $row['sla_percent']===null?'—':number_format($row['sla_percent'],1,',',' ').'%' }}</strong></div>
        </div>
        <div class="partner-assign-body">
            <section class="partner-card partner-table-card">
                <header class="partner-card-head"><div><h2>Missions disponibles</h2><p>{{ $availableMissions->count() }} expédition(s) à affecter</p></div></header>
                <div class="partner-table-wrap partner-assign-table-wrap">
                    <table class="partner-table compact">
                        <thead><tr><th></th><th>Mission</th><th>Destination</th><th>Commune</th><th>Poids</th><th>Véhicule requis</th><th>ETA</th><th>Priorité</th><th>Couverture</th></tr></thead>
                        <tbody>
                            @forelse($availableMissions as $mission)
                                <tr class="{{ $mission['compatible']?'':'incompatible' }}">
                                    <td><input type="checkbox" name="missions[]" value="{{ $mission['id'] }}" @disabled(!$mission['compatible']) data-mission-check data-weight="{{ $mission['weight_kg'] }}" data-vehicle="{{ $mission['vehicle'] }}" data-reference="{{ $mission['reference'] }}" data-destination="{{ $mission['destination'] }}"></td>
                                    <td><strong>{{ $mission['reference'] }}</strong></td>
                                    <td>{{ $mission['destination'] }}</td>
                                    <td>{{ $mission['commune'] }}</td>
                                    <td>{{ number_format($mission['weight_kg'],1,',',' ') }} kg</td>
                                    <td>{{ $mission['vehicle'] }}</td>
                                    <td>{{ $mission['eta']?->format('d/m/Y H:i') ?: 'Non définie' }}</td>
                                    <td><span class="partner-chip {{ $mission['priority']==='urgent'?'red':($mission['priority']==='high'?'orange':'blue') }}">{{ $mission['priority']==='urgent'?'Urgente':($mission['priority']==='high'?'Élevée':'Normale') }}</span></td>
                                    <td><span class="partner-chip {{ $mission['compatible']?'green':'red' }}">{{ $mission['compatible']?'Compatible':'Hors couverture' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="partner-empty"><x-partners.icon name="route"/><strong>Aucune mission disponible</strong><span>Il n’existe actuellement aucune expédition prête à être attribuée.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="partner-card partner-assignment-panel">
                <h3><x-partners.icon name="activity"/> Résumé de l’attribution</h3>
                <dl><div><dt>Missions sélectionnées</dt><dd data-selected-count>0</dd></div><div><dt>Charge totale</dt><dd data-selected-weight>0 kg</dd></div></dl>
                <h4>Répartition par véhicule</h4>
                <div class="partner-selected-vehicles" data-selected-vehicles><span>Aucune mission sélectionnée.</span></div>
                <h4>Missions sélectionnées</h4>
                <div class="partner-selected-list" data-selected-list><span>Aucune mission sélectionnée.</span></div>
            </aside>
        </div>
        <footer class="partner-modal-foot"><button class="partner-btn partner-btn-light" type="button" data-partner-close>Annuler</button><button class="partner-btn partner-btn-primary" type="submit" disabled data-assign-submit><x-partners.icon name="route"/> Attribuer les missions</button></footer>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-partners.js') }}?v={{ @filemtime(public_path('js/logistics-partners.js')) ?: '1' }}"></script>
@endpush
