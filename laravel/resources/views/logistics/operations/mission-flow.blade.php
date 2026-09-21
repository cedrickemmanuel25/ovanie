<div class="ops-table-scroll"><table class="ops-table ops-flow-table"><thead><tr><th>Expédition</th><th>Commande</th><th>Client</th><th>Destination</th><th>Produits</th><th>Poids</th><th>Statut</th>@if($showAssign ?? false)<th>Action</th>@endif</tr></thead><tbody>
@forelse($flowMissions as $row)<tr><td><a class="ops-reference" href="{{ $row['detailsUrl'] }}">{{ $row['reference'] }}</a></td><td>{{ $row['order'] }}</td><td>{{ $row['client'] }}</td><td>{{ $row['destination'] }}</td><td>{{ implode(' + ',$row['products']) }}</td><td>{{ $row['weight'] }} kg</td><td><x-operations.badge :status="$row['status']"/></td>@if($showAssign ?? false)<td><button type="button" class="ops-button ops-button-small" data-open-assignment="{{ $row['id'] }}">Affecter</button></td>@endif</tr>
@empty<tr><td colspan="{{ ($showAssign ?? false) ? 8 : 7 }}" class="ops-empty">Aucune mission</td></tr>
@endforelse
</tbody></table></div>
