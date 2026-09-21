@extends('layouts.logistics-operations')
@section('page-class','ops-page-supervision')
@section('title', 'Planification avancée')
@php
    $missionGroup=$group;
    $m=\App\ViewModels\LogisticsOperationsData::mission($group);
    $driverRows=$drivers->map(fn($d)=>\App\ViewModels\LogisticsOperationsData::driver($d))->all();
@endphp
@section('content')
<x-operations.page-header title="Planification avancée" subtitle="Comparer les livreurs, vérifier les ETA, la carte et les contraintes avant l’affectation" current="Planification avancée"><a class="ops-button" href="{{ $m['detailsUrl'] }}"><x-operations.icon name="back"/>Retour à la mission</a><a class="ops-button" href="{{ route('logistics.shipments') }}"><x-operations.icon name="close"/>Annuler</a><button class="ops-button ops-button-primary" type="submit" form="ops-full-assignment"><x-operations.icon name="check-circle"/>Valider la planification</button></x-operations.page-header>
<div class="ops-kpis ops-assignment-kpis">
@foreach([['box','Mission',$m['reference'],'blue'],['box','Commande',$m['order'],'orange'],['warning','Statut','À affecter','orange'],['user','Client',$m['client'],'purple'],['pin','Destination',$m['destination'],'red'],['weight','Poids / Volume',$m['weight'].' kg','purple'],['moto','Véhicule requis',$m['vehicle'],'green']] as [$icon,$label,$value,$tone])<x-operations.kpi :icon="$icon" :label="$label" :value="$value" :tone="$tone"/>
@endforeach
</div>
<form id="ops-full-assignment" method="post" action="{{ $m['assignUrl'] }}">
@csrf
<div class="ops-assign-layout"><div class="ops-stack"><p class="ops-footnote">Mode avancé : utilisez cet écran pour comparer plusieurs livreurs et contrôler précisément la mission. Pour une affectation standard, utilisez le modal « Nouvelle affectation » depuis la liste des expéditions.</p>
<x-operations.panel title="Comparaison des livreurs" icon="user"><div class="ops-filters ops-driver-filters"><label class="ops-search"><x-operations.icon name="search"/><input placeholder="Rechercher un livreur" aria-label="Rechercher un livreur" data-driver-search></label><select data-driver-availability aria-label="Disponibilité"><option value="">Disponibilité</option><option value="available">Disponible</option><option value="busy">Occupé</option></select><select data-driver-compatible aria-label="Véhicule compatible"><option value="">Véhicule compatible</option><option value="yes">Compatible</option></select><select data-driver-zone aria-label="Zone"><option value="">Zone</option>
@foreach(collect($driverRows)->pluck('zone')->unique() as $zone)<option>{{ $zone }}</option>
@endforeach
</select></div>
<div class="ops-driver-options">
@forelse($driverRows as $d)<label class="ops-driver-option" data-driver-option="{{ $d['id'] }}"><input type="radio" name="driver_id" value="{{ $d['id'] }}" required @checked(old('driver_id',$bestDriverId)==$d['id']) @disabled($d['compatible']===false || !$d['available'])><span class="ops-driver-initials">{{ $d['initials'] }}</span><div><strong>{{ $d['name'] }}</strong><small class="text-green">{{ $d['online'] ? '● En ligne' : 'Hors ligne' }}</small><small><x-operations.icon name="pin"/>{{ $d['zone'] }}</small></div><div><span><x-operations.icon name="moto"/>{{ $d['vehicle'] }}</span><small class="{{ $d['compatible'] ? 'text-green' : 'text-orange' }}">{{ $d['compatible'] ? '✓ Véhicule compatible' : 'Véhicule incompatible' }}</small><span class="ops-rating">★ {{ $d['rating'] }}</span></div><div><span class="ops-badge {{ $d['available'] ? 'is-green' : 'is-red' }}">{{ $d['available'] ? 'Disponible' : 'Occupé' }}</span></div><div class="ops-driver-eta"><small>ETA collecte</small><strong>{{ $d['pickupEta'] !== null ? $d['pickupEta'].' min' : 'À confirmer' }}</strong></div><div class="ops-driver-eta"><small>ETA client</small><strong>{{ $d['clientEta'] !== null ? $d['clientEta'].' min' : 'À confirmer' }}</strong></div>
@if($bestDriverId==$d['id'])<span class="ops-best-choice">Meilleur choix</span>
@endif
</label>
@empty<p class="ops-empty">Aucun livreur actif ne dispose actuellement du véhicule requis {{ $m['vehicle'] }}.</p>
@endforelse
<p class="ops-empty" data-no-drivers hidden>Aucun livreur ne correspond aux filtres.</p></div><nav class="ops-list-pagination" aria-label="Pagination des livreurs"><span data-driver-page-count></span><div><button type="button" class="ops-button ops-button-small" data-driver-page-previous>Précédent</button><button type="button" class="ops-button ops-button-small" data-driver-page-next>Suivant <x-operations.icon name="next"/></button></div></nav></x-operations.panel>
<x-operations.panel title="Résumé de la mission" icon="list">
@include('logistics.operations.mission-summary')<h3 class="ops-subheading"><x-operations.icon name="box"/>Produits à transporter</h3>
@include('logistics.operations.products')</x-operations.panel>
</div><aside class="ops-stack"><x-operations.panel title="Planification & affectation" icon="user"><dl class="ops-facts"><dt>Livreur sélectionné</dt><dd data-selected-driver="name">Choisir un livreur</dd><dt>Véhicule attribué</dt><dd data-selected-driver="vehicle">—</dd><dt><label for="full-plate">Immatriculation</label></dt><dd><input id="full-plate" name="vehicle_plate" value="{{ old('vehicle_plate') }}" maxlength="40" required></dd><dt>Disponibilité</dt><dd data-selected-driver="availability">—</dd><dt>Compatibilité charge</dt><dd data-selected-driver="compatibility">—</dd><dt><label for="full-pickup">Heure de collecte</label></dt><dd><input id="full-pickup" type="datetime-local" name="pickup_scheduled_at" value="{{ old('pickup_scheduled_at',$pickupScheduledAt) }}" min="{{ $minimumPickupValue }}" required></dd><dt>ETA collecte</dt><dd data-selected-driver="pickup">—</dd><dt>ETA livraison client</dt><dd data-selected-driver="client">—</dd><dt><label for="full-delivery">Heure estimée de livraison</label></dt><dd><input id="full-delivery" name="estimated_delivery_at" type="datetime-local" value="{{ old('estimated_delivery_at',$deliveryScheduledAt) }}" required></dd><dt>Notification livreur</dt><dd><input type="hidden" name="notify_driver" value="0"><input class="ops-switch" aria-label="Notifier le livreur" type="checkbox" name="notify_driver" value="1" checked></dd></dl><button class="ops-button ops-button-primary ops-wide" type="submit"><x-operations.icon name="check-circle"/>Confirmer la planification</button></x-operations.panel>
<x-operations.panel title="Contrôles opérationnels" icon="list"><dl class="ops-facts"><dt>Paiement</dt><dd>{{ $order?->payment_status==='paid' ? 'Payé' : 'À confirmer' }}</dd><dt>Véhicule requis</dt><dd>{{ $m['vehicle'] }}</dd><dt>Charge totale</dt><dd>{{ $m['weight'] }} kg</dd><dt>Point de collecte prêt</dt><dd><span class="ops-badge is-orange">{{ $m['status']==='ready_for_pickup' ? 'Prêt' : 'À confirmer' }}</span></dd></dl></x-operations.panel>
<x-operations.panel title="Prévision chronologique" icon="clock">
    <dl class="ops-facts">
        <dt>Mission créée</dt><dd>{{ $rep->created_at?->format('H:i') ?: '—' }}</dd>
        <dt>Dernière position GPS</dt><dd data-preview-gps>À confirmer</dd>
        <dt>Trajet livreur → collecte</dt><dd><span data-selected-driver="pickup">—</span> <small data-preview-pickup-distance></small></dd>
        <dt>Collecte prévue</dt><dd data-preview-pickup>{{ $bestDriverId ? \Carbon\Carbon::parse($pickupScheduledAt)->format('H:i') : 'À confirmer' }}</dd>
        <dt>Trajet collecte → client</dt><dd><span data-selected-driver="client">—</span> <small data-preview-client-distance></small></dd>
        <dt>Livraison prévue</dt><dd data-preview-delivery>{{ $bestDriverId && $clientLegEtaMinutes !== null ? \Carbon\Carbon::parse($deliveryScheduledAt)->format('H:i') : 'À confirmer' }}</dd>
    </dl>
    <p class="ops-footnote">Prévisions calculées à partir de la dernière position GPS enregistrée du livreur, du point de collecte de la boutique et de l’adresse de livraison client. Si une donnée GPS ou routière manque, aucune heure n’est inventée.</p>
</x-operations.panel></aside></div></form>
@endsection
@push('scripts')<script type="application/json" id="ops-full-driver-data">{!! json_encode($driverRows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endpush
