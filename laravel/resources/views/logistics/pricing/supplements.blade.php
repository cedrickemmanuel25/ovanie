@extends('layouts.logistics')
@section('title','Suppléments')
@section('crumb','Tarification > Suppléments')
@push('styles')<link rel="stylesheet" href="{{ asset('css/logistics-pricing.css') }}?v={{ @filemtime(public_path('css/logistics-pricing.css')) ?: '20260914' }}">@endpush

@php
    $supplementConditions = [
        'express' => ['label' => 'Livraison urgente / express', 'description' => 'Ajouté quand la livraison doit aller plus vite que le standard.', 'icon' => 'bolt'],
        'fragile' => ['label' => 'Produit fragile', 'description' => 'Ajouté si le colis doit être manipulé avec précaution.', 'icon' => 'box'],
        'handling' => ['label' => 'Manutention spéciale', 'description' => 'Ajouté lorsqu’une manipulation dédiée est nécessaire.', 'icon' => 'weight'],
        'unloading' => ['label' => 'Déchargement requis', 'description' => 'Ajouté si un déchargement est demandé à l’arrivée.', 'icon' => 'truck'],
        'bulky' => ['label' => 'Colis volumineux', 'description' => 'Ajouté si le colis est encombrant ou très volumineux.', 'icon' => 'warehouse'],
        'traffic' => ['label' => 'Trafic routier', 'description' => 'Ajouté si un retard de circulation doit être pris en compte.', 'icon' => 'route'],
    ];
@endphp

@section('content')
<main class="pricing-page">
    <div class="pricing-titlebar">
        <div>
            <h1>Suppléments</h1>
            <p>Gérez les frais additionnels applicables aux livraisons OVANIE Logistics.</p>
        </div>
        <div class="pricing-title-actions">
            <a class="pricing-btn" href="{{ route('logistics.ovanie-pricing.export',['type'=>'supplements']) }}"><x-operations.icon name="download"/> Exporter</a>
            <button type="button" class="pricing-btn pricing-btn-green" data-pricing-open="supplement-modal"><x-operations.icon name="plus"/> Nouveau supplément</button>
        </div>
    </div>

    <section class="pricing-kpis">
        <article class="pricing-kpi"><span class="pricing-kpi-icon green"><x-operations.icon name="list"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Suppléments actifs</span><div class="pricing-kpi-value-row"><strong>{{ $supplements->where('is_active',true)->count() }}</strong><span class="pricing-chip">{{ $activePercent }}% actifs</span></div><p>Règles enregistrées</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon blue"><x-operations.icon name="bolt"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Suppléments automatiques</span><div class="pricing-kpi-value-row"><strong>{{ $supplements->where('is_active',true)->where('automatic',true)->count() }}</strong></div><p>Conditions actives</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon orange"><x-operations.icon name="target"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Catégories de frais</span><div class="pricing-kpi-value-row"><strong>{{ $categoryCount }}</strong></div><p>Catégories utilisées</p></div></article>
        <article class="pricing-kpi"><span class="pricing-kpi-icon purple"><x-operations.icon name="list"/></span><div class="pricing-kpi-body"><span class="pricing-kpi-label">Pourcentage moyen configuré</span><div class="pricing-kpi-value-row"><strong>{{ $averageSupplementPercent!==null ? '+'.number_format($averageSupplementPercent,1,',',' ').'%' : '—' }}</strong></div><p>Suppléments en pourcentage</p></div></article>
    </section>

    @include('logistics.pricing._module_navigation')

    <section class="pricing-supplements-layout">
        <article class="pricing-card">
            <header class="pricing-card-header">
                <div class="pricing-card-heading">
                    <span class="pricing-section-icon"><x-operations.icon name="list"/></span>
                    <div>
                        <h2>Règles de suppléments ({{ $supplements->count() }})</h2>
                        <p>Seules les règles enregistrées apparaissent ici.</p>
                    </div>
                </div>
            </header>
            <div class="pricing-card-body">
                <div class="pricing-filters">
                    <label class="pricing-search"><x-operations.icon name="search"/><input data-table-search="#supplements-table" placeholder="Rechercher un supplément..."></label>
                    <select class="pricing-select"><option>Toutes les catégories</option>@foreach($supplements->pluck('category')->filter()->unique() as $category)<option>{{ $category }}</option>@endforeach</select>
                    <select class="pricing-select"><option>Tous les statuts</option><option>Actif</option><option>Inactif</option></select>
                </div>
                <div class="pricing-table-wrap">
                    <table class="pricing-table pricing-supplement-table" id="supplements-table">
                        <thead>
                            <tr>
                                <th>Libellé</th>
                                <th>Catégorie</th>
                                <th>Montant / Règle</th>
                                <th>Portée</th>
                                <th>Condition</th>
                                <th>Application</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($supplements as $supplement)
                            <tr>
                                <td>
                                    <div class="supplement-name">
                                        <span class="pricing-section-icon"><x-operations.icon name="box"/></span>
                                        <div>
                                            <strong>{{ $supplement->label }}</strong>
                                            <small>{{ $supplement->description }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $supplement->category }}</td>
                                <td class="amount">{{ $supplement->calculation_type==='percentage' ? '+ '.number_format($supplement->amount,1,',',' ').'%' : '+ '.number_format($supplement->amount,0,',',' ').' FCFA' }}</td>
                                <td>{{ $supplement->scope }}</td>
                                <td>{{ $supplement->condition_label ?: '—' }}</td>
                                <td>{{ $supplement->automatic?'Automatique':'Manuelle' }}</td>
                                <td><span class="pricing-status {{ $supplement->is_active?'':'inactive' }}"><i></i>{{ $supplement->is_active?'Actif':'Inactif' }}</span></td>
                                <td><button type="button" class="pricing-btn pricing-btn-small" data-pricing-open="supplement-modal" data-pricing-values='@json(array_merge($supplement->toArray(), ["supplement_id" => $supplement->id]))'>Modifier</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="pricing-empty">Aucun supplément opérationnel n’est enregistré.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </article>

        <aside class="pricing-right-stack">
            <article class="pricing-card pricing-right-card">
                <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon"><x-operations.icon name="settings"/></span><div><h2>Mode de calcul réel</h2><p>Comportement des suppléments dans le moteur tarifaire.</p></div></div></header>
                <div class="pricing-card-body">
                    <div class="pricing-use-note"><x-operations.icon name="check-circle"/><div><strong>Après le tarif principal</strong><span>Les suppléments s’ajoutent après le tarif principal du véhicule. Ils sont l’unique source des frais exceptionnels.</span></div></div>
                    <div class="pricing-use-note"><x-operations.icon name="bolt"/><div><strong>Automatique selon condition</strong><span>Un supplément automatique est appliqué uniquement si la livraison remplit sa condition.</span></div></div>
                    <div class="pricing-use-note"><x-operations.icon name="target"/><div><strong>Exclusivité respectée</strong><span>Lorsqu’un supplément est déclaré non cumulable, le moteur conserve le supplément exclusif applicable le plus élevé.</span></div></div>
                </div>
            </article>
            <article class="pricing-card pricing-right-card">
                <header class="pricing-card-header"><div class="pricing-card-heading"><span class="pricing-section-icon orange"><x-operations.icon name="list"/></span><div><h2>Conditions prises en charge</h2><p>Critères actuellement évalués par OVANIE Logistics.</p></div></div></header>
                <div class="pricing-card-body">
                    <div class="pricing-surcharge-row"><x-operations.icon name="bolt"/><b>Urgence / express</b><span>Selon niveau</span></div>
                    <div class="pricing-surcharge-row"><x-operations.icon name="box"/><b>Produit fragile</b><span>Oui / Non</span></div>
                    <div class="pricing-surcharge-row"><x-operations.icon name="target"/><b>Manutention / déchargement</b><span>Selon besoin</span></div>
                    <div class="pricing-surcharge-row"><x-operations.icon name="weight"/><b>Poids ou volume important</b><span>Condition volumineux</span></div>
                </div>
            </article>
        </aside>
    </section>
</main>

<div class="pricing-modal" id="supplement-modal" hidden @if($errors->any()) data-pricing-auto-open @endif>
    <form method="POST" action="{{ route('logistics.ovanie-pricing.supplements.store') }}" class="pricing-modal-dialog medium pricing-editor">
        @csrf
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="supplement_id" value="{{ old('supplement_id') }}">
        <input type="hidden" name="scope" value="Par livraison">
        <header class="pricing-modal-header">
            <span class="pricing-modal-icon"><x-operations.icon name="list"/></span>
            <div class="pricing-modal-title">
                <h2 data-editor-title>Nouveau supplément</h2>
                <p>Renseignez uniquement les informations essentielles pour créer un frais additionnel facile à comprendre.</p>
            </div>
            <button type="button" class="pricing-modal-close" data-pricing-close><x-operations.icon name="close"/></button>
        </header>

        <div class="pricing-modal-body">
            @if($errors->any())
                <div class="pricing-form-alert"><x-operations.icon name="warning"/><div><strong>Vérifiez les informations saisies.</strong><span>{{ $errors->first() }}</span></div></div>
            @endif

            <div class="pricing-simple-intro">
                <div class="pricing-use-note compact">
                    <x-operations.icon name="help"/>
                    <div>
                        <strong>Principe simple</strong>
                        <span>Le supplément s’ajoute au prix du trajet uniquement quand la condition choisie est vraie.</span>
                    </div>
                </div>
            </div>

            <section class="pricing-modal-section">
                <h3><x-operations.icon name="settings"/> 1. Informations essentielles</h3>
                <p>Donnez un nom clair au supplément puis indiquez comment il doit être calculé.</p>
                <div class="pricing-form-grid three">
                    <div class="pricing-form-field">
                        <label>Libellé <sup>*</sup></label>
                        <input class="pricing-form-control" name="label" value="{{ old('label') }}" placeholder="Ex. Produit fragile" required>
                    </div>
                    <div class="pricing-form-field">
                        <label>Type de calcul <sup>*</sup></label>
                        <select class="pricing-form-control" name="calculation_type" required>
                            <option value="fixed" @selected(old('calculation_type','fixed')==='fixed')>Forfait (montant fixe)</option>
                            <option value="percentage" @selected(old('calculation_type','fixed')==='percentage')>Pourcentage</option>
                        </select>
                    </div>
                    <div class="pricing-form-field">
                        <label>Montant <sup>*</sup></label>
                        <input class="pricing-form-control" type="number" min="0" step="1" name="amount" value="{{ old('amount') }}" placeholder="Ex. 500" required>
                    </div>
                </div>
            </section>

            <section class="pricing-modal-section">
                <h3><x-operations.icon name="pin"/> 2. Quand faut-il l’appliquer ?</h3>
                <p>Cochez un ou plusieurs cas. Le supplément sera ajouté automatiquement si la livraison correspond.</p>
                <div class="supplement-condition-grid">
                    @foreach($supplementConditions as $key => $condition)
                        <label class="condition-item compact">
                            <input type="checkbox" name="conditions[{{ $key }}]" value="1" @checked(old('conditions.'.$key))>
                            <span class="condition-icon"><x-operations.icon :name="$condition['icon']"/></span>
                            <span>
                                <b>{{ $condition['label'] }}</b>
                                <small>{{ $condition['description'] }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="pricing-modal-section">
                <h3><x-operations.icon name="check-circle"/> 3. Activation</h3>
                <p>Choisissez si le supplément doit être actif tout de suite et s’il peut se cumuler avec d’autres suppléments.</p>
                <div class="pricing-check-list simple-check-list">
                    <label class="pricing-check card-check">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))>
                        <span>
                            <b>Activer ce supplément maintenant</b>
                            <small>Décochez seulement si vous voulez l’enregistrer sans l’utiliser tout de suite.</small>
                        </span>
                    </label>
                    <label class="pricing-check card-check">
                        <input type="checkbox" name="compatible_with_others" value="1" @checked(old('compatible_with_others', '1'))>
                        <span>
                            <b>Compatible avec d’autres suppléments</b>
                            <small>Exemple : fragile + déchargement peuvent s’ajouter ensemble si cette case est cochée.</small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="pricing-modal-section">
                <h3><x-operations.icon name="eye"/> Résumé pratique</h3>
                <div class="supplement-summary-box">
                    <div><small>Le supplément sera appliqué</small><strong>Après le tarif du trajet</strong></div>
                    <div><small>Portée</small><strong>Par livraison</strong></div>
                    <div><small>Exemple</small><strong>Tarif trajet 2 000 FCFA + supplément 500 FCFA = total 2 500 FCFA</strong></div>
                </div>
            </section>
        </div>

        <footer class="pricing-modal-footer">
            <button type="button" class="pricing-btn" data-pricing-close>Annuler</button>
            <button type="submit" class="pricing-btn pricing-btn-green"><x-operations.icon name="plus"/> Enregistrer le supplément</button>
        </footer>
    </form>
</div>
@endsection

@push('scripts')<script defer src="{{ asset('js/logistics-pricing.js') }}?v={{ @filemtime(public_path('js/logistics-pricing.js')) ?: '20260914' }}"></script>@endpush
