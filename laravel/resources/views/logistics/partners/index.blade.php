@extends('layouts.logistics')
@section('title','Partenaires')
@section('crumb','Partenaires')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/logistics-partners.css') }}?v={{ @filemtime(public_path('css/logistics-partners.css')) ?: '1' }}">
@endpush

@section('content')
<main class="partner-page">
    <header class="partner-titlebar">
        <div>
            <h1>Partenaires</h1>
            <p>Gestion des transporteurs partenaires, de leur couverture et de leurs performances.</p>
        </div>
        <div class="partner-title-actions">
            <a class="partner-btn partner-btn-light" href="{{ route('logistics.partners.export') }}">
                <x-partners.icon name="download"/> Exporter
            </a>
            <button class="partner-btn partner-btn-primary" type="button" data-partner-open="partner-create">
                <x-partners.icon name="plus"/> Ajouter un partenaire
            </button>
        </div>
    </header>

    @if($errors->any())
        <div class="partner-alert partner-alert-danger" data-partner-open-on-error="partner-create">
            <strong>Le partenaire n’a pas été enregistré.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <section class="partner-kpis">
        <article class="partner-kpi">
            <span class="partner-kpi-icon green"><x-partners.icon name="users"/></span>
            <div><span>Partenaires actifs</span><strong>{{ $summary['active'] }}</strong><small>sur {{ $summary['total'] }} partenaire(s)</small></div>
        </article>
        <article class="partner-kpi">
            <span class="partner-kpi-icon blue"><x-partners.icon name="briefcase"/></span>
            <div><span>Partenaires enregistrés</span><strong>{{ $summary['total'] }}</strong><small>transporteurs externes</small></div>
        </article>
        <article class="partner-kpi">
            <span class="partner-kpi-icon purple"><x-partners.icon name="hardhat"/></span>
            <div><span>Spécialisés BTP</span><strong>{{ $summary['specialized'] }}</strong><small>charges et chantiers</small></div>
        </article>
        <article class="partner-kpi">
            <span class="partner-kpi-icon orange"><x-partners.icon name="truck"/></span>
            <div><span>Véhicules actifs</span><strong>{{ number_format($summary['vehicles'],0,',',' ') }}</strong><small>flottes partenaires</small></div>
        </article>
        <article class="partner-kpi">
            <span class="partner-kpi-icon green"><x-partners.icon name="route"/></span>
            <div><span>Missions gérées</span><strong>{{ number_format($summary['missions'],0,',',' ') }}</strong><small>expéditions réellement affectées</small></div>
        </article>
    </section>

    <form class="partner-filters" method="GET" action="{{ route('logistics.partners') }}">
        <label class="partner-search">
            <x-partners.icon name="search"/>
            <input name="q" value="{{ request('q') }}" placeholder="Rechercher un partenaire, un contact, un véhicule...">
        </label>
        <select name="type">
            <option value="all">Type — Tous</option>
            <option value="partner" @selected(request('type')==='partner')>Partenaire</option>
            <option value="specialized" @selected(request('type')==='specialized')>Spécialisé BTP</option>
        </select>
        <select name="coverage">
            <option value="">Couverture — Toutes</option>
            @foreach($coverageOptions as $option)
                <option value="{{ $option }}" @selected(request('coverage')===$option)>{{ $option }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all">Statut — Tous</option>
            <option value="active" @selected(request('status')==='active')>Actif</option>
            <option value="suspended" @selected(request('status')==='suspended')>Suspendu</option>
            <option value="draft" @selected(request('status')==='draft')>Brouillon</option>
        </select>
        <button class="partner-btn partner-btn-light" type="submit">Filtrer</button>
        <a class="partner-reset" href="{{ route('logistics.partners') }}"><x-partners.icon name="refresh"/> Réinitialiser</a>
    </form>

    <section class="partner-layout">
        <article class="partner-card partner-table-card">
            <header class="partner-card-head">
                <div><h2>Liste des partenaires</h2><p>{{ $partners->total() }} résultat(s)</p></div>
            </header>
            <div class="partner-table-wrap">
                <table class="partner-table">
                    <thead>
                        <tr>
                            <th>Partenaire</th><th>Type</th><th>Couverture</th><th>Véhicules</th><th>SLA 30 j</th><th>Missions</th><th>Statut</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($partners as $carrier)
                            @php($row=$rows[$carrier->id])
                            <tr>
                                <td>
                                    <a class="partner-identity" href="{{ route('logistics.partners.show',$carrier->code ?: $carrier->id) }}">
                                        <span class="partner-logo-small">
                                            @if($row['logo_url'])<img src="{{ $row['logo_url'] }}" alt="">@else<span>{{ mb_strtoupper(mb_substr($carrier->name,0,2)) }}</span>@endif
                                        </span>
                                        <span><strong>{{ $carrier->name }}</strong><small>{{ $row['code'] }}</small></span>
                                    </a>
                                </td>
                                <td><span class="partner-type {{ $row['type'] }}"><x-partners.icon name="{{ $row['type']==='specialized'?'hardhat':'briefcase' }}"/>{{ $row['type_label'] }}</span></td>
                                <td><span class="partner-coverage-text">{{ $row['coverage_label'] ?: 'Non configurée' }}</span></td>
                                <td>{{ $row['vehicle_count'] }}</td>
                                <td>
                                    @if($row['sla_percent']===null)<span class="partner-muted">—</span>
                                    @else<span class="partner-chip {{ $row['sla_percent']>=90?'green':($row['sla_percent']>=80?'orange':'red') }}">{{ number_format($row['sla_percent'],1,',',' ') }}%</span>@endif
                                </td>
                                <td>{{ $row['mission_count'] }}</td>
                                <td><span class="partner-status {{ $row['is_active']?'active':'inactive' }}"><i></i>{{ $row['status_label'] }}</span></td>
                                <td>
                                    <div class="partner-row-actions">
                                        <a title="Voir la fiche" href="{{ route('logistics.partners.show',$carrier->code ?: $carrier->id) }}"><x-partners.icon name="eye"/></a>
                                        @if($carrier->phone)<a title="Appeler" href="tel:{{ preg_replace('/\s+/','',$carrier->phone) }}"><x-partners.icon name="phone"/></a>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="partner-empty"><x-partners.icon name="briefcase"/><strong>Aucun partenaire réel enregistré</strong><span>Ajoutez un transporteur pour commencer à gérer ses véhicules et ses missions.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($partners->hasPages())
                <footer class="partner-table-footer">
                    <span>Affichage {{ $partners->firstItem() }}–{{ $partners->lastItem() }} sur {{ $partners->total() }}</span>
                    <div class="partner-pagination">
                        @if($partners->onFirstPage())<span class="disabled">Précédent</span>@else<a href="{{ $partners->previousPageUrl() }}">Précédent</a>@endif
                        <b>{{ $partners->currentPage() }} / {{ $partners->lastPage() }}</b>
                        @if($partners->hasMorePages())<a href="{{ $partners->nextPageUrl() }}">Suivant</a>@else<span class="disabled">Suivant</span>@endif
                    </div>
                </footer>
            @endif
        </article>

        <aside class="partner-side">
            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="truck"/> Répartition de la flotte</h3></header>
                <div class="partner-bars">
                    @php($maxVehicle=max(1,(int)$vehicleDistribution->max()))
                    @forelse($vehicleDistribution->take(6) as $label=>$count)
                        <div><span>{{ $label }}</span><i><b style="width:{{ round(($count/$maxVehicle)*100) }}%"></b></i><strong>{{ $count }}</strong></div>
                    @empty
                        <p class="partner-empty-small">Aucun véhicule partenaire enregistré.</p>
                    @endforelse
                </div>
            </article>

            <article class="partner-card partner-side-card">
                <header><h3><x-partners.icon name="alert"/> Partenaires à surveiller</h3></header>
                <div class="partner-watch-list">
                    @forelse($watchPartners as $watch)
                        <a href="{{ route('logistics.partners.show',$watch['carrier']->code ?: $watch['carrier']->id) }}" class="partner-watch-row">
                            <span class="partner-logo-tiny">{{ mb_strtoupper(mb_substr($watch['carrier']->name,0,2)) }}</span>
                            <span><strong>{{ $watch['carrier']->name }}</strong><small>{{ $watch['delays_count'] }} retard(s) sur 30 jours</small></span>
                            <em>{{ $watch['sla_percent']===null?'SLA —':number_format($watch['sla_percent'],1,',',' ').'%' }}</em>
                        </a>
                    @empty
                        <p class="partner-empty-small">Aucune alerte de performance sur les données disponibles.</p>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
</main>

<div class="partner-modal" id="partner-create" hidden>
    <form class="partner-modal-dialog" method="POST" action="{{ route('logistics.partners.store') }}" enctype="multipart/form-data">
        @csrf
        <header class="partner-modal-head">
            <div><h2>Ajouter un partenaire</h2><p>Enregistrer un transporteur externe dans OVANIE Logistics.</p></div>
            <button type="button" data-partner-close aria-label="Fermer"><x-partners.icon name="x"/></button>
        </header>

        <div class="partner-modal-body">
            <section class="partner-form-section">
                <h3><x-partners.icon name="briefcase"/> Informations du partenaire</h3>
                <div class="partner-form-grid two">
                    <label>Nom du partenaire <sup>*</sup><input name="name" value="{{ old('name') }}" required placeholder="Nom de l’entreprise"></label>
                    <label>Type <sup>*</sup><select name="partner_type" required><option value="partner" @selected(old('partner_type','partner')==='partner')>Partenaire de livraison</option><option value="specialized" @selected(old('partner_type')==='specialized')>Spécialisé BTP / charges lourdes</option></select></label>
                    <label>Contact principal <sup>*</sup><input name="contact_name" value="{{ old('contact_name') }}" required placeholder="Nom et prénom"></label>
                    <label>Téléphone <sup>*</sup><input name="phone" value="{{ old('phone') }}" required placeholder="+225 ..."></label>
                    <label>E-mail <sup>*</sup><input name="email" type="email" value="{{ old('email') }}" required placeholder="contact@entreprise.ci"></label>
                    <label>Adresse <sup>*</sup><input name="address" value="{{ old('address') }}" required placeholder="Adresse du partenaire"></label>
                    <label class="full">Logo
                        <span class="partner-file-input"><x-partners.icon name="upload"/><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"><b data-file-label>Sélectionner un logo</b><small>PNG, JPG, WEBP ou SVG — 2 Mo max.</small></span>
                    </label>
                    <label class="full">Description<textarea name="description" maxlength="1000" placeholder="Activité et capacités du partenaire">{{ old('description') }}</textarea></label>
                </div>
            </section>

            <section class="partner-form-section">
                <h3><x-partners.icon name="map"/> Couverture et capacités</h3>
                <div class="partner-form-grid two">
                    <label>Zone principale <sup>*</sup>
                        <select name="main_zone" required>
                            <option value="">Sélectionner</option>
                            @forelse($territoryZones as $zone)<option value="{{ $zone->name }}" @selected(old('main_zone')===$zone->name)>{{ $zone->name }}</option>@empty<option value="" disabled>Aucune zone de livraison active</option>@endforelse
                        </select>
                    </label>
                    <label>Délai contractuel moyen <sup>*</sup><select name="average_delay_hours" required>@foreach([12,24,48,72,96] as $h)<option value="{{ $h }}" @selected((int)old('average_delay_hours',24)===$h)>{{ $h }}h</option>@endforeach</select></label>
                    <label class="full">Communes couvertes <sup>*</sup>
                        <select name="coverage[]" multiple size="7" required>
                            @foreach($communes as $commune)<option value="{{ $commune->name }}" @selected(in_array($commune->name,(array)old('coverage',[]),true))>{{ $commune->name }}</option>@endforeach
                        </select>
                        <small>Maintenez Ctrl pour sélectionner plusieurs communes.</small>
                    </label>
                    <div class="full">
                        <span class="partner-field-title">Types de véhicules pris en charge</span>
                        <div class="partner-check-grid">
                            @foreach(['Moto','Tricycle','Pickup','Camion 3T','Camion 10T'] as $vehicle)
                                <label><input type="checkbox" name="vehicle_types[]" value="{{ $vehicle }}" @checked(in_array($vehicle,(array)old('vehicle_types',[]),true))>{{ $vehicle }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="partner-form-section">
                <h3><x-partners.icon name="file"/> Contrat et exploitation</h3>
                <div class="partner-form-grid two">
                    <label>Référence contrat<input name="contract_reference" value="{{ old('contract_reference') }}" placeholder="Référence interne"></label>
                    <label>Date d’intégration<input type="date" name="integrated_at" value="{{ old('integrated_at') }}"></label>
                    <label>Objectif SLA (%)<input type="number" min="0" max="100" step="0.1" name="sla_percent" value="{{ old('sla_percent') }}" placeholder="Ex. 95"></label>
                    <label>Niveau de priorité<select name="priority_level"><option value="normal">Normal</option><option value="high" @selected(old('priority_level')==='high')>Élevé</option><option value="critical" @selected(old('priority_level')==='critical')>Critique</option></select></label>
                    <label class="full">Contrat PDF
                        <span class="partner-file-input"><x-partners.icon name="file"/><input type="file" name="contract_file" accept="application/pdf"><b data-file-label>Sélectionner un PDF</b><small>10 Mo max.</small></span>
                    </label>
                    <div class="full partner-check-list">
                        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active','1'))> Partenaire actif</label>
                        <label><input type="checkbox" name="can_receive_missions" value="1" @checked(old('can_receive_missions','1'))> Peut recevoir des missions</label>
                        <label><input type="checkbox" name="auto_assignment_visible" value="1" @checked(old('auto_assignment_visible','1'))> Visible pour l’attribution automatique</label>
                        <label><input type="checkbox" name="supports_special_loads" value="1" @checked(old('supports_special_loads'))> Accepte les charges spéciales</label>
                        <label><input type="checkbox" name="intercommunal_delivery" value="1" @checked(old('intercommunal_delivery'))> Livraison intercommunale</label>
                    </div>
                    <label class="full">Observations internes<textarea name="observations" maxlength="1000">{{ old('observations') }}</textarea></label>
                </div>
            </section>
        </div>

        <footer class="partner-modal-foot">
            <button type="button" class="partner-btn partner-btn-light" data-partner-close>Annuler</button>
            <button class="partner-btn partner-btn-light" type="submit" name="save_mode" value="draft">Enregistrer en brouillon</button>
            <button class="partner-btn partner-btn-primary" type="submit" name="save_mode" value="active"><x-partners.icon name="check"/> Créer le partenaire</button>
        </footer>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/logistics-partners.js') }}?v={{ @filemtime(public_path('js/logistics-partners.js')) ?: '1' }}"></script>
@endpush
