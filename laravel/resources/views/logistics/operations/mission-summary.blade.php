@php
    $shop = $missionGroup['shops']->first();
    $points = [['label'=>$m['pickup'],'lat'=>$shop?->latitude,'lng'=>$shop?->longitude,'status'=>'done'],['label'=>$m['destination'],'lat'=>$m['lat'],'lng'=>$m['lng'],'status'=>'in_progress']];
@endphp
<div class="ops-pickup-grid">
    <div class="ops-location-card"><h4><x-operations.icon name="warehouse"/>Point de collecte (Boutique)</h4><strong>{{ $m['pickup'] }}</strong><p><x-operations.icon name="pin"/>{{ $m['pickupZone'] }}</p><p>{{ $shop?->address ?: 'Adresse détaillée non renseignée' }}</p>
@if($shop?->whatsapp)<a href="tel:{{ $shop->whatsapp }}"><x-operations.icon name="phone"/>{{ $shop->whatsapp }}</a>
@endif
</div>
    <x-operations.map :tour="['stops'=>$points,'routeSegments'=>$m['routeSegments']]" :summary="false" mode="pickup"/>
    <div class="ops-location-card"><h4><x-operations.icon name="pin"/>Destination (Client)</h4><strong>{{ $m['client'] }}</strong><p><x-operations.icon name="pin"/>{{ $m['destination'] }}</p><small>{{ $missionGroup['order']?->delivery_address ?: 'Adresse détaillée non renseignée' }}</small></div>
</div>
