<div class="ops-table-scroll"><table class="ops-table ops-products-table"><thead><tr><th>Produit</th><th>Quantité</th><th>Poids unitaire</th><th>Poids total</th></tr></thead><tbody>
@foreach($missionGroup['items'] as $line)
@php($unitWeight=$line->product?->weight_kg ?? $line->product?->weight)
<tr><td>{{ $line->product?->name ?: 'Produit' }}</td><td>{{ $line->quantity }}</td><td>{{ $unitWeight !== null ? $unitWeight.' kg' : 'Non renseigné' }}</td><td>{{ $line->logistics_weight_kg ? $line->logistics_weight_kg.' kg' : ($unitWeight !== null ? $unitWeight*$line->quantity.' kg' : 'Non renseigné') }}</td></tr>
@endforeach
</tbody><tfoot><tr><td>Total</td><td>{{ $missionGroup['items']->sum('quantity') }} unités</td><td>Poids total</td><td><strong>{{ $m['weight'] }} kg</strong></td></tr></tfoot></table></div>
