<div class="pricing-modal pricing-editor" id="vehicle-rate-modal" hidden role="dialog" aria-modal="true" aria-labelledby="vehicle-rate-modal-title" @if($errors->any()) data-pricing-auto-open @endif>
<form method="POST" action="{{ route('logistics.ovanie-pricing.vehicle-tariffs.store') }}" class="pricing-modal-dialog">
@csrf
<header class="pricing-modal-header"><div class="pricing-modal-title"><h2 id="vehicle-rate-modal-title" data-editor-title>Configurer un véhicule</h2><p>Livraison = base + distance × prix/km + poids × prix/kg + volume × prix/m³ + frais éventuels.</p></div><button type="button" class="pricing-modal-close" data-pricing-close aria-label="Fermer">×</button></header>
<div class="pricing-modal-body">
@if($errors->any())<div class="pricing-form-alert" role="alert">{{ $errors->first() }}</div>@endif
<section class="pricing-modal-section"><h3>Véhicule et statut</h3><div class="pricing-form-grid">
<div class="pricing-form-field"><label for="vehicle-rate-modal-vehicle">Type de véhicule *</label><select id="vehicle-rate-modal-vehicle" name="vehicle_code" class="pricing-form-control" required data-vehicle-select><option value="">Sélectionner un véhicule</option>@foreach($vehicles as $code=>$vehicle)<option value="{{ $code }}" @selected(old('vehicle_code')===$code)>{{ $vehicle['label'] }}</option>@endforeach</select></div>
<div class="pricing-form-field"><label for="vehicle-rate-modal-status">Statut *</label><select id="vehicle-rate-modal-status" name="is_active" class="pricing-form-control" required><option value="1" @selected(old('is_active','1')=='1')>Actif</option><option value="0" @selected(old('is_active','1')=='0')>Inactif</option></select></div>
<div class="pricing-form-field full"><label for="rate-label">Libellé affiché</label><input id="rate-label" name="vehicle_label" class="pricing-form-control" maxlength="80" value="{{ old('vehicle_label') }}" placeholder="Par défaut : nom du véhicule"></div>
</div></section>
<section class="pricing-modal-section"><h3>Tarifs (FCFA)</h3><p>Mettez 0 pour une composante non facturée. Aucun minimum ni plafond n’est appliqué.</p><div class="pricing-form-grid">
<div class="pricing-form-field"><label for="vehicle-rate-modal-base">Tarif de base *</label><input id="vehicle-rate-modal-base" name="base_fee" type="number" min="0" step="1" class="pricing-form-control" required value="{{ old('base_fee') }}" placeholder="ex. 500"></div>
<div class="pricing-form-field"><label for="vehicle-rate-modal-km">Prix par km *</label><input id="vehicle-rate-modal-km" name="price_per_km" type="number" min="0" step="0.01" class="pricing-form-control" required value="{{ old('price_per_km') }}" placeholder="ex. 150"></div>
<div class="pricing-form-field"><label for="vehicle-rate-modal-kg">Prix par kg *</label><input id="vehicle-rate-modal-kg" name="price_per_kg" type="number" min="0" step="0.01" class="pricing-form-control" required value="{{ old('price_per_kg') }}" placeholder="ex. 20"></div>
<div class="pricing-form-field"><label for="vehicle-rate-modal-m3">Prix par m³ *</label><input id="vehicle-rate-modal-m3" name="price_per_m3" type="number" min="0" step="0.01" class="pricing-form-control" required value="{{ old('price_per_m3') }}" placeholder="ex. 1000"></div>
</div></section>
<section class="pricing-modal-section"><h3>Limites de capacité</h3><p>Servent au choix automatique du véhicule selon le poids et le volume.</p><div class="pricing-form-grid">
<div class="pricing-form-field"><label for="vehicle-rate-modal-weight">Charge maximale (kg)</label><input id="vehicle-rate-modal-weight" name="max_weight_kg" type="number" min="0.01" step="0.01" class="pricing-form-control" value="{{ old('max_weight_kg') }}"></div>
<div class="pricing-form-field"><label for="vehicle-rate-modal-volume">Volume maximal (m³)</label><input id="vehicle-rate-modal-volume" name="max_volume_m3" type="number" min="0.0001" step="0.0001" class="pricing-form-control" value="{{ old('max_volume_m3') }}"></div>
</div></section>
<div class="pricing-form-field"><label for="vehicle-rate-modal-description">Note interne (facultative)</label><textarea id="vehicle-rate-modal-description" class="pricing-form-control" name="description" maxlength="500">{{ old('description') }}</textarea></div>
</div>
<footer class="pricing-modal-footer"><button type="button" class="pricing-btn" data-pricing-close>Annuler</button><button type="submit" class="pricing-btn pricing-btn-green">Enregistrer les tarifs</button></footer>
</form></div>
