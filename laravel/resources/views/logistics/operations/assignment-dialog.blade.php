<dialog class="ops-assignment-dialog" id="ops-assignment-dialog" tabindex="-1" aria-labelledby="ops-assignment-title">
<form method="post" id="ops-assignment-form">@csrf
    <header class="ops-assignment-heading">
        <div>
            <h2 id="ops-assignment-title">Nouvelle affectation</h2>
            <p data-assignment-dialog-subtitle>Sélectionnez une mission réelle, puis choisissez un livreur disponible et compatible.</p>
        </div>
        <span class="ops-quick-assignment"><x-operations.icon name="bolt"/>Affectation rapide</span>
        <button type="button" class="ops-icon-button" data-close-assignment aria-label="Fermer l’affectation"><x-operations.icon name="close"/></button>
    </header>

    <div class="ops-assignment-columns">
        <section class="ops-assignment-mission">
            <h3><x-operations.icon name="box"/>Mission sélectionnée</h3>

            <div class="ops-assignment-mission-picker" data-assignment-mission-picker hidden>
                <div class="ops-assignment-picker-intro">
                    <strong>Choisir la mission à affecter</strong>
                    <p>Aucune commande n’est sélectionnée automatiquement. Choisissez une mission réellement prête à être affectée.</p>
                </div>
                <div class="ops-assignment-mission-list" data-assignment-mission-list></div>
            </div>

            <div data-assignment-mission-details>
                <div class="ops-assignment-reference">
                    <span class="ops-square-icon tone-orange"><x-operations.icon name="box"/></span>
                    <div><strong data-assignment="reference">—</strong><p>Commande <span data-assignment="order">—</span></p></div>
                    <x-operations.badge status="unassigned"/>
                </div>
                <dl class="ops-mission-facts">
                    @foreach([['pin','Destination','destination'],['building','Client','client'],['box','Poids','weightLabel'],['truck','Véhicule requis','vehicle']] as [$icon,$label,$key])
                    <div><dt><x-operations.icon :name="$icon"/>{{ $label }}</dt><dd data-assignment="{{ $key }}">—</dd></div>
                    @endforeach
                </dl>
                <div class="ops-assignment-products"><x-operations.icon name="box"/><div><p>Produits (<span data-assignment="referencesCount">0</span> références)</p><ul data-assignment-products><li>—</li></ul></div></div>
                <div class="ops-assignment-pickup"><x-operations.icon name="warehouse"/><div><p>Lieu de collecte</p><strong><span data-assignment="pickup">—</span> — <span data-assignment="pickupZone">—</span></strong></div></div>
                <div class="ops-assignment-journey"><span class="ops-journey-icon"><x-operations.icon name="warehouse"/></span><div><strong>Collecte</strong><p data-assignment="pickup">—</p><p data-assignment="pickupZone">—</p></div><i></i><span class="ops-journey-icon"><x-operations.icon name="pin"/></span><div><strong>Livraison</strong><p data-assignment="destination">—</p></div></div>
            </div>
        </section>

        <section class="ops-assignment-controls">
            <h3><x-operations.icon name="user"/>Affectation</h3>
            <fieldset class="ops-assignment-control-body" data-assignment-controls-body disabled>
                <input type="hidden" name="driver_id" id="ops-assignment-driver" value="">

                <div class="ops-driver-candidate-heading">
                    <div><strong>Livreurs disponibles</strong><small data-driver-picker-caption>Choisissez d’abord une mission.</small></div>
                    <span class="ops-driver-candidate-count" data-driver-candidate-count>0</span>
                </div>
                <div class="ops-driver-candidate-list" data-assignment-driver-list>
                    <p class="ops-assignment-empty">Aucune mission sélectionnée.</p>
                </div>

                <div class="ops-assigned-driver" data-assignment-selected-driver hidden>
                    <div class="ops-assigned-driver-top"><span class="ops-driver-initials" data-driver="initials"></span><div><strong data-driver="name">—</strong><div class="ops-driver-tags"><span class="ops-badge is-green" data-driver-availability>—</span><span class="ops-badge is-green" data-driver-online>—</span></div></div><div class="ops-driver-score"><strong><x-operations.icon name="star"/><span data-driver="rating">—</span></strong><small data-driver-reviews></small></div></div>
                    <div class="ops-driver-facts"><p><x-operations.icon name="pin"/>Zone : <span data-driver="zone">—</span></p><p><x-operations.icon name="moto"/>Véhicule : <span data-driver="vehicle">—</span></p><p><x-operations.icon name="clock"/><span data-driver="recommendation">—</span></p></div>
                    <div class="ops-driver-compatibility"><span class="ops-badge is-green" data-assignment-compatibility><x-operations.icon name="check-circle"/><span>Véhicule à confirmer</span></span><span class="ops-badge is-green" data-assignment-capacity><x-operations.icon name="check-circle"/><span>Charge à confirmer</span></span></div>
                </div>

                <label for="ops-pickup-time">Heure de collecte <em>*</em></label><div class="ops-field-icon ops-datetime ops-date-styled"><x-operations.icon name="calendar"/><input id="ops-pickup-time" name="pickup_scheduled_at" type="datetime-local" required value="{{ $pickupScheduledAt ?? now()->addMinutes(10)->format('Y-m-d\TH:i') }}"><span data-date-display></span></div>
                <div class="ops-assignment-etas"><div><small>ETA collecte</small><strong><x-operations.icon name="clock"/><span data-driver="pickupEtaLabel">—</span></strong></div><div><small>ETA client</small><strong><x-operations.icon name="clock"/><span data-driver="clientEtaLabel">—</span></strong></div><div><small>Heure estimée de livraison</small><label class="ops-delivery-time"><x-operations.icon name="clock"/><input type="datetime-local" aria-label="Heure estimée de livraison" name="estimated_delivery_at" required value="{{ $deliveryScheduledAt ?? now()->addMinutes(41)->format('Y-m-d\TH:i') }}"><span data-delivery-display></span></label></div></div>
                <label class="ops-notify"><input type="hidden" name="notify_driver" value="0"><input type="checkbox" class="ops-switch" name="notify_driver" value="1" checked><span>Notifier le livreur<small>Le livreur recevra une notification avec les détails de la mission.</small></span></label>
                <label class="ops-note-label" for="ops-assignment-note"><x-operations.icon name="list"/>Note opérationnelle <small>(optionnelle)</small></label><textarea name="note" id="ops-assignment-note" maxlength="500" placeholder="Ajouter une note opérationnelle..."></textarea><div class="ops-note-count"><span data-note-count>0</span>/500</div>
                <input type="hidden" name="vehicle_plate" data-assignment-plate>
            </fieldset>
        </section>
    </div>

    <footer class="ops-assignment-footer"><button type="button" class="ops-button" data-close-assignment>Annuler</button><button type="button" class="ops-button" data-save-assignment disabled>Enregistrer brouillon</button><a class="ops-button" href="#" data-advanced-assignment hidden><x-operations.icon name="settings"/>Planification avancée</a><button type="submit" class="ops-button ops-button-primary" disabled>Valider l’affectation</button></footer>
</form></dialog>
