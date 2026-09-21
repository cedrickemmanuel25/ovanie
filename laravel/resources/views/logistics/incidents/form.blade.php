@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/logistics-incidents.css') }}?v={{ filemtime(public_path('css/logistics-incidents.css')) }}">
    @endpush
    @push('scripts')
        <script defer src="{{ asset('js/logistics-incidents.js') }}?v={{ filemtime(public_path('js/logistics-incidents.js')) }}"></script>
    @endpush
@endonce

@php
    $fixedItem = isset($item) && $item;
    $choices = $fixedItem ? collect([$item]) : ($incidentItems ?? collect());
    $selectedItemId = (string) old('order_item_id', $fixedItem ? $item->id : '');
    $selectedSignalSource = (string) old('signal_source', '');

    $initialChoice = $fixedItem
        ? $item
        : $choices->first(fn ($choice) => (string) $choice->id === $selectedItemId);

    $initialAssignment = $initialChoice?->latestDeliveryAssignment;
    $initialDriver = $initialAssignment?->driver;
    $initialLocation = $initialAssignment?->latestLocation;
    $initialOrder = $initialChoice?->order;
    $initialShop = $initialChoice?->product?->shop;
    $initialLat = $initialLocation?->latitude ?? $initialDriver?->latitude ?? $initialChoice?->driver_latitude;
    $initialLng = $initialLocation?->longitude ?? $initialDriver?->longitude ?? $initialChoice?->driver_longitude;
    $initialGpsAt = $initialLocation?->recorded_at ?? $initialDriver?->last_seen_at ?? $initialChoice?->driver_location_updated_at;

    $initialDestination = collect([
        $initialOrder?->delivery_address,
        $initialOrder?->delivery_quartier,
        $initialOrder?->delivery_commune,
        $initialOrder?->delivery_city,
    ])->filter()->unique()->implode(' · ');

    $initialPickup = collect([
        $initialShop?->display_name ?: $initialShop?->name,
        $initialAssignment?->pickup_address ?: $initialShop?->address,
        $initialShop?->commune,
    ])->filter()->unique()->implode(' · ');

    $initialClientName = $initialOrder?->delivery_recipient_name
        ?: $initialOrder?->customer_name
        ?: $initialOrder?->client?->name;
    $initialClientPhone = $initialOrder?->delivery_recipient_phone
        ?: $initialOrder?->phone
        ?: $initialOrder?->client?->phone;

    $initialMissionRef = $initialAssignment?->mission_number
        ?: $initialChoice?->shipment?->tracking_number
        ?: ($initialChoice ? 'OVL-' . str_pad((string) $initialChoice->id, 6, '0', STR_PAD_LEFT) : null);

    $initialMapPoints = ($initialLat !== null && $initialLng !== null)
        ? [[
            'lat' => (float) $initialLat,
            'lng' => (float) $initialLng,
            'label' => $initialDriver?->name ?: 'Dernière position connue',
            'color' => '#087aff',
        ]]
        : [];

    $incidentTypeGroups = [
        'Client et destination' => [
            'client_absent', 'client_introuvable', 'adresse_introuvable', 'adresse_incorrecte', 'litige_client', 'refus_reception', 'otp_impossible',
        ],
        'Collecte et marchandise' => [
            'vendeur_pas_pret', 'point_vente_pas_pret', 'produit_endommage', 'produit_incomplet', 'quantite_incorrecte', 'probleme_chargement', 'colis_perdu',
        ],
        'Route, véhicule et sécurité' => [
            'acces_chantier_difficile', 'panne_vehicule', 'accident', 'blocage_route', 'probleme_securite', 'probleme_carburant', 'retard_important', 'retard_livraison',
        ],
        'Fin de livraison' => [
            'livraison_reportee', 'livraison_echouee', 'retour_point_vente',
        ],
        'Autre' => [
            'autre_incident',
        ],
    ];

    $sourceDescriptions = [
        'driver_phone' => 'Le livreur a appelé ou écrit au centre logistique.',
        'client_support' => 'Le client a contacté l’assistance ou le service client.',
        'shop_contact' => 'La boutique / le point de collecte a signalé le problème.',
        'operations_control' => 'Un opérateur logistique a détecté une anomalie pendant le suivi.',
    ];


    $paymentLabels = [
        'paid' => 'Payé',
        'pending' => 'En attente',
        'authorized' => 'Autorisé',
        'partially_paid' => 'Partiellement payé',
        'partial' => 'Partiellement payé',
        'commission_paid' => 'Payé',
        'verified' => 'Vérifié',
        'escrow_held' => 'Paiement sécurisé',
        'failed' => 'Échec du paiement',
        'cancelled' => 'Annulé',
        'canceled' => 'Annulé',
        'refunded' => 'Remboursé',
        'cash_on_delivery' => 'Paiement à la livraison',
        'cod' => 'Paiement à la livraison',
    ];
    $initialPaymentStatus = (string) ($initialOrder?->payment_status ?: '');
    $initialPaymentLabel = $paymentLabels[$initialPaymentStatus] ?? ($initialPaymentStatus !== '' ? ucfirst(str_replace('_', ' ', $initialPaymentStatus)) : 'Non renseigné');
    $operatorName = request()->user('admin')?->name ?: request()->user()?->name ?: 'Responsable logistique connecté';
    $impactOptions = $impactLevels ?? [
        'none' => 'Aucun impact confirmé',
        'delay' => 'Retard probable',
        'blocked' => 'Livraison interrompue',
        'rescheduled' => 'Livraison à reprogrammer',
    ];
@endphp

<dialog class="directory-modal incident-modal" id="incident-create">
    <header class="incident-modal-header">
        <div>
            <div class="incident-title-row">
                <h2>Nouveau dossier incident</h2>
                <x-operations.tag>Signalement reçu</x-operations.tag>
            </div>
            <p>Le centre logistique reçoit, qualifie et traite un signalement. Il n’est pas présenté comme témoin de l’incident sur le terrain.</p>
        </div>
        <button type="button" data-directory-close aria-label="Fermer">×</button>
    </header>

    @if($choices->isNotEmpty())
        <form
            method="post"
            action="{{ $fixedItem ? route('logistics.incidents.store', $item) : '#' }}"
            class="directory-form incident-form"
            enctype="multipart/form-data"
            data-restore-draft="incident-draft"
            data-operator-name="{{ $operatorName }}"
            data-initial-driver="{{ $initialDriver?->name }}"
            data-initial-driver-phone="{{ $initialDriver?->phone }}"
            data-initial-client="{{ $initialClientName }}"
            data-initial-client-phone="{{ $initialClientPhone }}"
            data-initial-shop="{{ $initialShop?->display_name ?: $initialShop?->name }}"
            data-initial-shop-phone="{{ $initialShop?->whatsapp }}"
            @unless($fixedItem) data-incident-dynamic-form @endunless
        >
            @csrf

            <div class="incident-automation-note">
                <x-operations.icon name="bolt"/>
                <div>
                    <strong>Les incidents terrain remontent automatiquement lorsqu’ils sont signalés depuis une application.</strong>
                    <span>L’application du livreur OVANIE et la supervision terrain créent directement le dossier incident. Ce formulaire sert seulement à consigner un problème reçu par appel, WhatsApp, assistance client, boutique ou constaté dans le centre de suivi.</span>
                </div>
            </div>

            <div class="incident-mission-step">
                <x-operations.panel title="1. Mission concernée" icon="box">
                    @if($fixedItem)
                        <input type="hidden" name="order_item_id" value="{{ $item->id }}">
                        <div class="incident-mission-card is-selected">
                            <div>
                                <strong>{{ $initialMissionRef }}</strong>
                                <span>{{ $initialOrder?->order_number ?: 'Commande #' . $initialChoice?->order_id }}</span>
                            </div>
                            <x-operations.tag tone="blue">{{ $initialChoice?->delivery_status_label ?: 'Statut inconnu' }}</x-operations.tag>
                        </div>
                    @else
                        <label class="incident-mission-select">
                            Livraison en cours <b class="required">*</b>
                            <select required name="order_item_id" data-incident-mission>
                                <option value="" @selected($selectedItemId === '') disabled>Choisir une livraison OVANIE en cours…</option>
                                @foreach($choices as $choice)
                                    @php
                                        $assignment = $choice->latestDeliveryAssignment;
                                        $driver = $assignment?->driver;
                                        $latestLocation = $assignment?->latestLocation;
                                        $order = $choice->order;
                                        $shop = $choice->product?->shop;
                                        $missionRef = $assignment?->mission_number
                                            ?: $choice->shipment?->tracking_number
                                            ?: 'OVL-' . str_pad((string) $choice->id, 6, '0', STR_PAD_LEFT);
                                        $destination = collect([$order?->delivery_address, $order?->delivery_quartier, $order?->delivery_commune, $order?->delivery_city])->filter()->unique()->implode(' · ');
                                        $pickup = collect([$shop?->display_name ?: $shop?->name, $assignment?->pickup_address ?: $shop?->address, $shop?->commune])->filter()->unique()->implode(' · ');
                                        $clientName = $order?->delivery_recipient_name ?: $order?->customer_name ?: $order?->client?->name;
                                        $clientPhone = $order?->delivery_recipient_phone ?: $order?->phone ?: $order?->client?->phone;
                                        $resource = ($driver?->name ?: 'Non affecté') . ' — ' . ($driver?->vehicle ?: 'Véhicule non renseigné');
                                        $lat = $latestLocation?->latitude ?? $driver?->latitude ?? $choice->driver_latitude;
                                        $lng = $latestLocation?->longitude ?? $driver?->longitude ?? $choice->driver_longitude;
                                        $gpsAt = $latestLocation?->recorded_at ?? $driver?->last_seen_at ?? $choice->driver_location_updated_at;
                                        $driverStatus = $driver ? ($driver->is_online ? 'En ligne' : 'Hors ligne') : 'Non affecté';
                                        $paymentStatus = (string) ($order?->payment_status ?: '');
                                        $paymentLabel = $paymentLabels[$paymentStatus] ?? ($paymentStatus !== '' ? ucfirst(str_replace('_', ' ', $paymentStatus)) : 'Non renseigné');
                                    @endphp
                                    <option
                                        value="{{ $choice->id }}"
                                        @selected($selectedItemId === (string) $choice->id)
                                        data-action="{{ route('logistics.incidents.store', $choice) }}"
                                        data-mission="{{ $missionRef }}"
                                        data-order="{{ $order?->order_number ?: 'Commande #' . $choice->order_id }}"
                                        data-resource="{{ $resource }}"
                                        data-driver="{{ $driver?->name }}"
                                        data-driver-phone="{{ $driver?->phone }}"
                                        data-driver-status="{{ $driverStatus }}"
                                        data-client="{{ $clientName ?: 'Non renseigné' }}"
                                        data-client-phone="{{ $clientPhone ?: 'Non renseigné' }}"
                                        data-shop="{{ $shop?->display_name ?: $shop?->name ?: 'Non renseignée' }}"
                                        data-shop-phone="{{ $shop?->whatsapp ?: 'Non renseigné' }}"
                                        data-pickup="{{ $pickup ?: 'Point de collecte non renseigné' }}"
                                        data-destination="{{ $destination ?: ($assignment?->delivery_address ?: 'Destination non renseignée') }}"
                                        data-status="{{ $choice->delivery_status_label }}"
                                        data-payment="{{ $paymentLabel }}"
                                        data-lat="{{ $lat }}"
                                        data-lng="{{ $lng }}"
                                        data-gps-at="{{ $gpsAt?->format('d/m/Y H:i:s') }}"
                                        data-has-driver="{{ $driver ? '1' : '0' }}"
                                        data-has-client="{{ $clientName ? '1' : '0' }}"
                                        data-has-shop="{{ $shop ? '1' : '0' }}"
                                    >
                                        {{ $missionRef }} — {{ $order?->order_number ?: 'Commande #' . $choice->order_id }} — {{ $order?->delivery_commune ?: 'Destination à confirmer' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <div class="incident-live-grid incident-live-grid-mission">
                        <div class="incident-live-card">
                            <span>Mission / commande</span>
                            <strong data-incident-mission-ref>{{ $initialMissionRef ?: 'Sélectionnez une mission' }}</strong>
                            <small data-incident-order>{{ $initialOrder?->order_number ?: '—' }}</small>
                        </div>
                        <div class="incident-live-card">
                            <span>État actuel</span>
                            <strong data-incident-status>{{ $initialChoice?->delivery_status_label ?: '—' }}</strong>
                            <small>Paiement : <b data-incident-payment>{{ $initialPaymentLabel }}</b></small>
                        </div>
                        <div class="incident-live-card">
                            <span>Livreur / véhicule</span>
                            <strong data-incident-resource>{{ $initialDriver?->name ?: 'Non affecté' }}{{ $initialDriver?->vehicle ? ' — '.$initialDriver->vehicle : '' }}</strong>
                            <small><span data-incident-driver-status>{{ $initialDriver ? ($initialDriver->is_online ? 'En ligne' : 'Hors ligne') : '—' }}</span> · <span data-incident-driver-phone>{{ $initialDriver?->phone ?: 'Téléphone non renseigné' }}</span></small>
                        </div>
                        <div class="incident-live-card">
                            <span>Client</span>
                            <strong data-incident-client>{{ $initialClientName ?: 'Non renseigné' }}</strong>
                            <small data-incident-client-phone>{{ $initialClientPhone ?: 'Téléphone non renseigné' }}</small>
                        </div>
                        <div class="incident-live-card incident-live-wide-half">
                            <span>Collecte boutique</span>
                            <strong data-incident-shop>{{ $initialShop?->display_name ?: $initialShop?->name ?: 'Non renseignée' }}</strong>
                            <small data-incident-pickup>{{ $initialPickup ?: 'Point de collecte non renseigné' }}</small>
                        </div>
                        <div class="incident-live-card incident-live-wide-half">
                            <span>Destination client</span>
                            <strong data-incident-destination>{{ $initialOrder?->delivery_commune ?: 'Non renseignée' }}</strong>
                            <small data-incident-destination-full>{{ $initialDestination ?: 'Adresse non renseignée' }}</small>
                        </div>
                    </div>
                </x-operations.panel>
            </div>

            <div class="incident-form-layout">
                <div class="incident-form-column">
                    <div class="incident-source-step {{ $initialChoice ? '' : 'is-locked' }}" data-incident-source-step>
                        <x-operations.panel title="2. Origine du signalement" icon="help">
                            <div class="incident-source-locked" data-incident-source-locked>
                                <strong>Choisissez d’abord la mission concernée.</strong>
                                <span>La source n’est jamais déduite de la commande : elle correspond au canal qui a réellement transmis l’information au centre logistique.</span>
                            </div>
                            <div class="incident-source-content" data-incident-source-content>
                                <p class="incident-help-text"><strong>Comment ce champ fonctionne :</strong> après avoir choisi la mission, sélectionnez uniquement le canal qui a réellement transmis le problème au centre logistique. Ce choix n’est jamais déduit de la commande. Lorsqu’un incident arrive automatiquement depuis l’application du livreur, la supervision vendeur ou une alerte système, son origine est enregistrée automatiquement et ce formulaire n’est pas utilisé.</p>
                                <div class="incident-source-grid">
                                    @foreach($signalSources as $value => $label)
                                        <label class="incident-source-card" data-signal-source-option="{{ $value }}">
                                            <input
                                                type="radio"
                                                name="signal_source"
                                                value="{{ $value }}"
                                                @checked($selectedSignalSource === $value)
                                                @disabled(!$initialChoice || ($value === 'driver_phone' && !$initialDriver) || ($value === 'client_support' && !$initialClientName) || ($value === 'shop_contact' && !$initialShop))
                                                required
                                            >
                                            <span class="incident-source-dot"></span>
                                            <span>
                                                <strong>{{ $label }}</strong>
                                                <small>{{ $sourceDescriptions[$value] ?? '' }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="incident-source-preview" data-incident-source-preview>
                                    <span>Signalant identifié</span>
                                    <strong data-incident-source-actor>Choisissez l’origine du signalement</strong>
                                    <small data-incident-source-contact>Les coordonnées proviennent de la mission sélectionnée.</small>
                                </div>

                                <div class="incident-reporter-grid">
                                    <label>
                                        Référence du signalement <span class="optional">(optionnel)</span>
                                        <input name="reporter_reference" value="{{ old('reporter_reference') }}" maxlength="120" placeholder="Ex. appel 07:42, ticket SUP-0081…">
                                    </label>
                                    <label>
                                        Note sur la source <span class="optional">(optionnel)</span>
                                        <input name="reporter_note" value="{{ old('reporter_note') }}" maxlength="500" placeholder="Ex. le livreur confirme une panne">
                                    </label>
                                </div>
                            </div>
                        </x-operations.panel>
                    </div>

                    <x-operations.panel title="3. Informations sur l’incident" icon="alert">
                        <label>Type d’incident <b class="required">*</b></label>
                        <details class="incident-type-picker" data-incident-type-picker>
                            <summary>
                                <span data-incident-type-label>{{ old('incident_type') && isset($types[old('incident_type')]) ? $types[old('incident_type')] : 'Choisir un type d’incident' }}</span>
                                <span aria-hidden="true">⌄</span>
                            </summary>
                            <div class="incident-type-menu">
                                @foreach($incidentTypeGroups as $groupLabel => $groupValues)
                                    <section>
                                        <strong>{{ $groupLabel }}</strong>
                                        <div>
                                            @foreach($groupValues as $value)
                                                @continue(!isset($types[$value]))
                                                <label>
                                                    <input type="radio" name="incident_type" value="{{ $value }}" data-label="{{ $types[$value] }}" @checked(old('incident_type') === $value) required>
                                                    <span>{{ $types[$value] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </details>

                        <div class="incident-field-title">Gravité <b class="required">*</b></div>
                        <div class="directory-radio-row incident-radio-row">
                            @foreach(['low'=>'Basse','medium'=>'Moyenne','high'=>'Élevée','critical'=>'Critique'] as $value => $label)
                                <label>
                                    <input type="radio" name="severity" value="{{ $value }}" @checked(old('severity') === $value) required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div class="incident-field-title">Impact sur la livraison <b class="required">*</b></div>
                        <p class="incident-help-text">Cette qualification détermine si la mission continue et si le client doit être informé. Le système propose automatiquement un impact selon le type d’incident, mais l’opérateur peut le corriger.</p>
                        <div class="incident-impact-grid" data-incident-impact-grid>
                            @foreach($impactOptions as $value => $label)
                                <label class="incident-impact-card">
                                    <input type="radio" name="impact_level" value="{{ $value }}" @checked(old('impact_level') === $value) required>
                                    <span>
                                        <strong>{{ $label }}</strong>
                                        <small>
                                            @if($value === 'none') La livraison peut continuer sans conséquence confirmée.
                                            @elseif($value === 'delay') Le client est informé d’un retard possible, la mission reste active.
                                            @elseif($value === 'blocked') Le client est informé et la livraison est mise en interruption opérationnelle.
                                            @else Le client est informé qu’une nouvelle planification est nécessaire.
                                            @endif
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="incident-client-notice" data-incident-client-notice>
                            <x-operations.icon name="help"/>
                            <span><strong>Notification client :</strong> elle sera envoyée automatiquement lorsqu’un impact retard, interruption ou reprogrammation est confirmé. Le message reste opérationnel et n’expose pas inutilement les détails internes du véhicule ou du livreur.</span>
                        </div>

                        <div class="incident-field-title">Responsabilité présumée <b class="required">*</b></div>
                        <p class="incident-help-text">Première qualification uniquement. Elle peut être corrigée après vérification.</p>
                        <div class="directory-radio-row incident-radio-row">
                            @foreach([
                                'driver'=>'Livreur',
                                'vehicle'=>'Véhicule',
                                'client'=>'Client',
                                'point_vente'=>'Boutique',
                                'transport'=>'Trafic / externe',
                                'ovanie'=>'OVANIE Logistics',
                                'unknown'=>'À déterminer',
                            ] as $value => $label)
                                <label>
                                    <input type="radio" name="responsibility" value="{{ $value }}" @checked(old('responsibility', 'unknown') === $value) required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <label class="incident-description-field">
                            Faits signalés <b class="required">*</b>
                            <textarea name="description" maxlength="2000" required rows="4" placeholder="Décrivez uniquement les faits rapportés : lieu, problème, conséquence sur la mission et action déjà tentée.">{{ old('description') }}</textarea>
                        </label>

                        <label>
                            Photo / preuve <span class="optional">(si disponible)</span>
                            <span class="directory-upload incident-upload">
                                <x-operations.icon name="download"/>
                                Ajouter une photo, capture ou document
                                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <small>JPG, PNG, WEBP ou PDF · 10 Mo maximum</small>
                            </span>
                        </label>
                    </x-operations.panel>
                </div>

                <div class="incident-form-column">
                    <x-operations.panel title="Dernière position connue" icon="pin">
                        <p class="incident-help-text">Dernière position GPS enregistrée pour le livreur affecté. La position de l’ordinateur du responsable logistique n’est jamais utilisée comme position de l’incident.</p>
                        <input type="hidden" name="latitude" value="{{ $initialLat }}" data-incident-latitude>
                        <input type="hidden" name="longitude" value="{{ $initialLng }}" data-incident-longitude>
                        <div class="incident-gps-layout">
                            <div class="incident-gps-meta">
                                <div><span>Latitude</span><strong data-incident-lat-label>{{ $initialLat !== null ? number_format((float)$initialLat, 6, '.', '') : 'Non disponible' }}</strong></div>
                                <div><span>Longitude</span><strong data-incident-lng-label>{{ $initialLng !== null ? number_format((float)$initialLng, 6, '.', '') : 'Non disponible' }}</strong></div>
                                <div class="incident-gps-wide"><span>Dernière mise à jour GPS</span><strong data-incident-gps-at>{{ $initialGpsAt?->format('d/m/Y H:i:s') ?: 'Aucune position reçue' }}</strong></div>
                            </div>
                            <div class="directory-map small incident-map" data-directory-map="{{ json_encode($initialMapPoints) }}" data-incident-map></div>
                        </div>
                    </x-operations.panel>

                    <x-operations.panel title="4. Action opérationnelle" icon="calendar">
                        <div class="incident-treatment-grid">
                            <label>
                                Nouvelle date / heure estimée <span class="optional">(si report)</span>
                                <input type="datetime-local" name="rescheduled_at" value="{{ old('rescheduled_at') }}">
                            </label>
                            <label>
                                Prochaine action
                                <input name="next_action" maxlength="255" value="{{ old('next_action') }}" placeholder="Ex. contacter le client, envoyer un véhicule…">
                            </label>
                        </div>
                        <label class="directory-check incident-notify-row">
                            <input class="directory-switch" type="checkbox" name="notify_teams" value="1" @checked(old('notify_teams'))>
                            Notifier également les équipes internes concernées
                        </label>
                    </x-operations.panel>

                    <div class="incident-intake-explainer">
                        <strong>Comment le dossier arrive au centre logistique ?</strong>
                        <ul>
                            <li><b>Application du livreur / chauffeur :</b> le signalement crée directement le dossier incident avec son origine.</li>
                            <li><b>Appel, WhatsApp, assistance client ou boutique :</b> l’opérateur sélectionne la mission puis indique qui a transmis l’information.</li>
                            <li><b>Contrôle du centre logistique :</b> l’opérateur ouvre un dossier lorsqu’une anomalie visible dans le suivi doit être traitée comme incident.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <footer class="incident-modal-footer">
                <span class="incident-footer-note">Mission, commande, client, boutique, livreur, véhicule et GPS affichés ici proviennent des données réelles OVANIE.</span>
                <div>
                    <button type="button" class="ops-button" data-directory-close>Annuler</button>
                    <button type="button" class="ops-button" data-draft="incident-draft">Enregistrer brouillon</button>
                    <button class="ops-button ops-button-primary">Enregistrer le signalement</button>
                </div>
            </footer>
        </form>
    @else
        <div class="directory-empty incident-no-mission">
            <strong>Aucune mission OVANIE active disponible.</strong>
            <span>Un dossier incident manuel doit être rattaché à une livraison OVANIE réellement en cours : livreur affecté, collecte effectuée, en transit ou en retard.</span>
            <a class="ops-button" href="{{ route('logistics.shipments') }}">Voir les expéditions</a>
        </div>
    @endif
</dialog>
