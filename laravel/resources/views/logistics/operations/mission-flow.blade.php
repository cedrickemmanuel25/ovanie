<div class="ops-table-scroll">
<table class="ops-table">
<thead><tr><th>Mission</th><th>Commande</th><th>Client</th><th>Phase</th><th>Préparation</th><th>Livreur partenaire</th><th>Véhicule</th></tr></thead>
<tbody>
@forelse($flowMissions as $row)
<tr>
    <td><a class="ops-reference" href="{{ $row['detailsUrl'] }}">{{ $row['reference'] }}</a></td>
    <td>{{ $row['order'] }}</td>
    <td>{{ $row['client'] }}</td>
    <td><x-operations.badge :status="$row['operationalStatus']" :label="$row['operationalLabel']"/>@if($row['hasOpenIncident'])<small style="display:block;margin-top:4px"><a href="{{ $row['incidentUrl'] }}" class="text-red">{{ $row['automaticDelay'] ? 'Retard détecté' : 'Incident ouvert' }} ↗</a></small>@endif</td>
    <td><strong>{{ $row['preparationPercent'] }} %</strong><small style="display:block">{{ $row['readyStops'] }} / {{ $row['totalStops'] }} point(s) prêt(s)</small></td>
    <td><strong>{{ $row['driver'] }}</strong>@if($row['reserved'])<small style="display:block">Mission réservée</small>@elseif($row['operationalStatus']==='waiting_acceptance')<small style="display:block">{{ $row['offeredDriverCount'] }} livreur(s) invité(s)</small>@endif</td>
    <td>{{ $row['vehicle'] }}<small style="display:block">{{ $row['weight'] }} kg</small></td>
</tr>
@empty
<tr><td colspan="7" class="ops-empty">Aucune mission dans cette phase.</td></tr>
@endforelse
</tbody>
</table>
</div>
