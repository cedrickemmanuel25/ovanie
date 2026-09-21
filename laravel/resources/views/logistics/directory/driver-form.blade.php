@php
    $hasDriverAvatar = $editing && $driver && ! empty($driver->avatar);
    $driverAvatarUrl = $hasDriverAvatar
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($driver->avatar)
        : '';

    $dialogId = $editing ? 'driver-edit' : 'driver-create';
    $formId = $editing ? 'driver-edit-form' : 'driver-create-form';
    $driverAction = $editing
        ? route('logistics.drivers.update', $driver)
        : route('logistics.drivers.store');

    $selectedZoneIds = collect(old('zone_ids', $driver?->interventionZoneIds() ?? []))
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    if ($editing && empty($selectedZoneIds) && $driver?->zone) {
        $legacyZone = $zones->first(
            fn ($zone) => mb_strtolower(trim($zone->name)) === mb_strtolower(trim($driver->zone))
        );
        if ($legacyZone) {
            $selectedZoneIds = [(string) $legacyZone->id];
        }
    }

    $vehicleOptions = [
        'moto' => 'Moto',
        'tricycle' => 'Tricycle',
        'pickup' => 'Pickup',
        'camion_3t' => 'Camion 3T',
        'camion_10t' => 'Camion 10T',
    ];

    $availabilityDays = collect(old(
        'availability_days',
        data_get($driver?->profile, 'availability_days', [])
    ))->map(fn ($day) => (string) $day)->values()->all();

    $days = [
        'lundi' => 'Lun',
        'mardi' => 'Mar',
        'mercredi' => 'Mer',
        'jeudi' => 'Jeu',
        'vendredi' => 'Ven',
        'samedi' => 'Sam',
        'dimanche' => 'Dim',
    ];

@endphp

<style id="ovanie-driver-modal-v8-style">
#{{ $dialogId }}.driver-v8,
#{{ $dialogId }}.driver-v8 *{box-sizing:border-box}
#{{ $dialogId }}.driver-v8{
    width:min(860px,calc(100vw - 32px));
    max-width:860px;
    max-height:calc(100dvh - 28px);
    padding:0;
    border:0;
    border-radius:18px;
    background:#fff;
    color:#0b1948;
    overflow:hidden;
    box-shadow:0 28px 90px rgba(10,29,61,.28);
}
#{{ $dialogId }}.driver-v8::backdrop{background:rgba(8,27,49,.40);backdrop-filter:blur(1px)}
#{{ $dialogId }} .dv8-form{margin:0;padding:0;background:#fff}
#{{ $dialogId }} .dv8-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:22px 24px 17px;border-bottom:1px solid #e7edf5}
#{{ $dialogId }} .dv8-title{margin:0;font-size:24px;line-height:1.15;font-weight:800;letter-spacing:-.35px;color:#07194b}
#{{ $dialogId }} .dv8-subtitle{margin:5px 0 0;font-size:12.5px;color:#657aa1}
#{{ $dialogId }} .dv8-close{display:grid;place-items:center;width:34px;height:34px;min-width:34px;padding:0;border:0;border-radius:9px;background:transparent;color:#536b94;font-size:24px;line-height:1;cursor:pointer}
#{{ $dialogId }} .dv8-close:hover{background:#f2f6fa;color:#132a5b}

#{{ $dialogId }} .dv8-stepper{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:7px;padding:14px 24px;background:#fbfcfe;border-bottom:1px solid #edf1f6}
#{{ $dialogId }} .dv8-step{display:flex;align-items:center;gap:8px;min-width:0;padding:8px 9px;border:1px solid #e2e9f2;border-radius:10px;background:#fff;color:#5d7398}
#{{ $dialogId }} .dv8-step-number{display:grid;place-items:center;width:25px;height:25px;min-width:25px;border-radius:50%;background:#edf3fa;color:#36557e;font-size:11px;font-weight:800}
#{{ $dialogId }} .dv8-step-copy{min-width:0}
#{{ $dialogId }} .dv8-step-copy strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10.5px;font-weight:800;color:#18315e}
#{{ $dialogId }} .dv8-step-copy small{display:block;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:8.5px;color:#8192aa}
#{{ $dialogId }} .dv8-step.is-active{border-color:#a8dfc6;background:#edf9f3}
#{{ $dialogId }} .dv8-step.is-active .dv8-step-number{background:#009c60;color:#fff}
#{{ $dialogId }} .dv8-step.is-active .dv8-step-copy strong{color:#007a4a}
#{{ $dialogId }} .dv8-step.is-done .dv8-step-number{background:#dff4ea;color:#007b49}

#{{ $dialogId }} .dv8-content{padding:16px 24px 14px;min-height:352px}
#{{ $dialogId }} [data-dv8-panel]{display:block}
#{{ $dialogId }} [data-dv8-panel][hidden]{display:none!important}
#{{ $dialogId }} .dv8-panel{border:1px solid #dbe5f1;border-radius:13px;background:#fff;overflow:hidden}
#{{ $dialogId }} .dv8-panel-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border-bottom:1px solid #e8eef6;background:#fcfdff}
#{{ $dialogId }} .dv8-panel-head h3{margin:0;font-size:15px;font-weight:800;color:#0b194b}
#{{ $dialogId }} .dv8-panel-head p{margin:3px 0 0;font-size:10.5px;color:#7588a5}
#{{ $dialogId }} .dv8-badge{display:inline-flex;align-items:center;min-height:24px;padding:0 9px;border-radius:999px;background:#f0f4f9;color:#6e7f99;font-size:9px;font-weight:800;white-space:nowrap}
#{{ $dialogId }} .dv8-panel-body{padding:16px}

#{{ $dialogId }} .dv8-field{display:block;min-width:0}
#{{ $dialogId }} .dv8-label{display:flex;align-items:center;gap:6px;margin:0 0 6px;font-size:11.5px;font-weight:800;color:#132853}
#{{ $dialogId }} .dv8-label small{font-size:9px;font-weight:600;color:#8292aa}
#{{ $dialogId }} .dv8-required{color:#e22b46}
#{{ $dialogId }} .dv8-input,
#{{ $dialogId }} .dv8-select,
#{{ $dialogId }} .dv8-search{
    width:100%;height:44px;margin:0;padding:0 13px;border:1.5px solid #c9d7e7;border-radius:9px;outline:0;background:#f9fbfe;color:#0b1948;font:500 13px/1.2 Arial,sans-serif;transition:.15s ease;
}
#{{ $dialogId }} .dv8-input:hover,#{{ $dialogId }} .dv8-select:hover,#{{ $dialogId }} .dv8-search:hover{border-color:#a5bad3;background:#fff}
#{{ $dialogId }} .dv8-input:focus,#{{ $dialogId }} .dv8-select:focus,#{{ $dialogId }} .dv8-search:focus{border-color:#087aff;background:#fff;box-shadow:0 0 0 3px rgba(8,122,255,.10)}
#{{ $dialogId }} .dv8-input::placeholder,#{{ $dialogId }} .dv8-search::placeholder{color:#99a7b9;font-weight:400}
#{{ $dialogId }} .dv8-select{appearance:none;padding-right:38px;background-image:linear-gradient(45deg,transparent 50%,#526b91 50%),linear-gradient(135deg,#526b91 50%,transparent 50%);background-position:calc(100% - 18px) 18px,calc(100% - 13px) 18px;background-size:5px 5px,5px 5px;background-repeat:no-repeat}
#{{ $dialogId }} .dv8-help{display:block;margin-top:5px;font-size:9.5px;line-height:1.35;color:#8291a8}
#{{ $dialogId }} .dv8-error{display:block;margin:5px 0 0;color:#d72742;font-size:10px;font-weight:700}
#{{ $dialogId }} .is-invalid{border-color:#e0495f!important;box-shadow:0 0 0 3px rgba(224,73,95,.08)!important}

#{{ $dialogId }} .dv8-personal{display:grid;grid-template-columns:160px minmax(0,1fr);gap:22px;align-items:start}
#{{ $dialogId }} .dv8-photo{position:relative;display:grid;place-items:center;width:100%;height:142px;border:1.5px dashed #aac2df;border-radius:12px;background:#f7faff;overflow:hidden;cursor:pointer}
#{{ $dialogId }} .dv8-photo:hover{border-color:#6f9fd5;background:#f0f6fd}
#{{ $dialogId }} .dv8-photo input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
#{{ $dialogId }} .dv8-photo-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;color:#536f99;text-align:center}
#{{ $dialogId }} .dv8-photo-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:#e6f0fb;font-size:20px}
#{{ $dialogId }} .dv8-photo-placeholder strong{font-size:11px}
#{{ $dialogId }} .dv8-photo-placeholder small{font-size:9px}
#{{ $dialogId }} .dv8-photo-preview{width:100%;height:100%;object-fit:cover}
#{{ $dialogId }} .dv8-photo-preview[hidden],#{{ $dialogId }} .dv8-photo-placeholder[hidden]{display:none!important}
#{{ $dialogId }} .dv8-personal-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 18px;padding-top:2px}

#{{ $dialogId }} .dv8-zone-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;margin-bottom:12px}
#{{ $dialogId }} .dv8-zone-count{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 11px;border-radius:9px;background:#eef4fa;color:#59708f;font-size:10px;font-weight:800;white-space:nowrap}
#{{ $dialogId }} .dv8-zone-count.has-selection{background:#e8f8f0;color:#007b4a}
#{{ $dialogId }} .dv8-zone-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
#{{ $dialogId }} .dv8-zone-card{position:relative;display:flex;align-items:center;gap:9px;min-height:46px;padding:8px 10px;border:1px solid #dce6f1;border-radius:9px;background:#fff;cursor:pointer;transition:.14s ease}
#{{ $dialogId }} .dv8-zone-card:hover{border-color:#a8bfd7;background:#f9fbfe}
#{{ $dialogId }} .dv8-zone-card.is-selected{border-color:#88d4b1;background:#eefaf4;box-shadow:0 0 0 1px rgba(0,156,96,.04)}
#{{ $dialogId }} .dv8-zone-card input{position:absolute;opacity:0;pointer-events:none}
#{{ $dialogId }} .dv8-zone-box{display:grid;place-items:center;width:19px;height:19px;min-width:19px;border:1.5px solid #b8c8db;border-radius:5px;background:#fff;color:transparent;font-size:12px;font-weight:900}
#{{ $dialogId }} .dv8-zone-card.is-selected .dv8-zone-box{border-color:#009c60;background:#009c60;color:#fff}
#{{ $dialogId }} .dv8-zone-name{min-width:0;font-size:10.5px;font-weight:800;color:#142953;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#{{ $dialogId }} .dv8-zone-card[hidden]{display:none!important}

#{{ $dialogId }} .dv8-two-cols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
#{{ $dialogId }} .dv8-vehicle-info{display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:11px 12px;border-radius:9px;background:#f4f8fc;color:#607593;font-size:10.5px;line-height:1.45}
#{{ $dialogId }} .dv8-vehicle-info b{color:#17315f}

#{{ $dialogId }} .dv8-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:7px;margin-top:4px}
#{{ $dialogId }} .dv8-day{position:relative;display:grid;place-items:center;height:46px;border:1px solid #dbe5f0;border-radius:9px;background:#fff;color:#536a8c;font-size:10.5px;font-weight:800;cursor:pointer;transition:.14s ease}
#{{ $dialogId }} .dv8-day input{position:absolute;opacity:0;pointer-events:none}
#{{ $dialogId }} .dv8-day.is-selected{border-color:#84d2ad;background:#eaf9f2;color:#007b49;box-shadow:inset 0 0 0 1px rgba(0,156,96,.05)}
#{{ $dialogId }} .dv8-availability-note{margin-top:12px;padding:10px 12px;border-left:3px solid #2d7ff9;border-radius:7px;background:#f2f7ff;color:#5d7190;font-size:10.5px;line-height:1.45}

#{{ $dialogId }} .dv8-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
#{{ $dialogId }} .dv8-summary-card{padding:13px 14px;border:1px solid #dfe7f1;border-radius:10px;background:#fbfcfe}
#{{ $dialogId }} .dv8-summary-card h4{margin:0 0 9px;font-size:11px;font-weight:800;color:#19325f}
#{{ $dialogId }} .dv8-summary-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:4px 0;font-size:10.5px;color:#71829b}
#{{ $dialogId }} .dv8-summary-row strong{max-width:65%;text-align:right;color:#122752;font-weight:800;word-break:break-word}
#{{ $dialogId }} .dv8-summary-card.wide{grid-column:1/-1}
#{{ $dialogId }} .dv8-summary-zones{display:flex;flex-wrap:wrap;gap:6px}
#{{ $dialogId }} .dv8-summary-chip{display:inline-flex;align-items:center;min-height:25px;padding:0 9px;border-radius:999px;background:#eaf8f1;color:#007a49;font-size:9.5px;font-weight:800}

#{{ $dialogId }} .dv8-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 24px 16px;border-top:1px solid #e7edf5;background:#fff}
#{{ $dialogId }} .dv8-footer-right{display:flex;align-items:center;gap:9px;margin-left:auto}
#{{ $dialogId }} .dv8-btn{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:0 16px;border:1px solid #ccd9e9;border-radius:8px;background:#fff;color:#112752;font-size:11px;font-weight:800;cursor:pointer}
#{{ $dialogId }} .dv8-btn:hover{background:#f7f9fc}
#{{ $dialogId }} .dv8-btn-primary{min-width:112px;border-color:#009c60;background:#009c60;color:#fff;box-shadow:0 3px 10px rgba(0,156,96,.16)}
#{{ $dialogId }} .dv8-btn-primary:hover{background:#008b56}
#{{ $dialogId }} .dv8-btn[hidden]{display:none!important}

@media(max-width:760px){
    #{{ $dialogId }}.driver-v8{width:calc(100vw - 16px);max-height:calc(100dvh - 16px);border-radius:12px}
    #{{ $dialogId }} .dv8-header{padding:16px 16px 12px}
    #{{ $dialogId }} .dv8-stepper{padding:10px 12px;gap:5px}
    #{{ $dialogId }} .dv8-step{justify-content:center;padding:7px 4px}
    #{{ $dialogId }} .dv8-step-copy{display:none}
    #{{ $dialogId }} .dv8-content{padding:12px;min-height:390px}
    #{{ $dialogId }} .dv8-personal{grid-template-columns:130px minmax(0,1fr);gap:14px}
    #{{ $dialogId }} .dv8-personal-fields,#{{ $dialogId }} .dv8-two-cols{grid-template-columns:1fr}
    #{{ $dialogId }} .dv8-zone-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    #{{ $dialogId }} .dv8-days{grid-template-columns:repeat(4,minmax(0,1fr))}
    #{{ $dialogId }} .dv8-summary{grid-template-columns:1fr}
    #{{ $dialogId }} .dv8-summary-card.wide{grid-column:auto}
    #{{ $dialogId }} .dv8-footer{padding:11px 12px 13px}
}
</style>

<dialog class="driver-v8" id="{{ $dialogId }}" data-driver-v8-dialog>
    <form id="{{ $formId }}" class="dv8-form" method="post" action="{{ $driverAction }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($editing)
            @method('PATCH')
        @endif

        <header class="dv8-header">
            <div>
                <h2 class="dv8-title">{{ $editing ? 'Modifier le livreur' : 'Inviter un livreur partenaire' }}</h2>
                <p class="dv8-subtitle">
                    {{ $editing
                        ? 'Mettez à jour les informations opérationnelles du livreur.'
                        : 'OVANIE ne recrute pas ses livreurs : renseignez simplement son identité et son numéro. Il complètera lui-même son dossier (véhicule, zones, pièces justificatives) depuis l’application mobile OVANIE Livreur.' }}
                </p>
            </div>
            <button class="dv8-close" type="button" data-directory-close aria-label="Fermer">×</button>
        </header>

        @unless ($editing)
            <div class="dv8-content">
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Identité du livreur</h3>
                            <p>Il recevra un accès sur l’app mobile OVANIE Livreur avec ce numéro.</p>
                        </div>
                    </div>
                    <div class="dv8-panel-body">
                        <div class="dv8-two-cols">
                            <label class="dv8-field">
                                <span class="dv8-label">Nom <b class="dv8-required">*</b></span>
                                <input class="dv8-input" name="last_name" required maxlength="120" autocomplete="family-name" value="{{ old('last_name') }}" placeholder="Ex. Koné">
                                @error('last_name')<span class="dv8-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="dv8-field">
                                <span class="dv8-label">Prénom <b class="dv8-required">*</b></span>
                                <input class="dv8-input" name="first_name" required maxlength="120" autocomplete="given-name" value="{{ old('first_name') }}" placeholder="Ex. Adama">
                                @error('first_name')<span class="dv8-error">{{ $message }}</span>@enderror
                            </label>
                        </div>
                        <label class="dv8-field" style="margin-top:18px">
                            <span class="dv8-label">Numéro de téléphone <b class="dv8-required">*</b></span>
                            <input class="dv8-input" name="phone" required maxlength="30" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" placeholder="Ex. 07 00 00 00 00">
                            @error('phone')<span class="dv8-error">{{ $message }}</span>@enderror
                        </label>
                        <div class="dv8-vehicle-info" style="margin-top:16px">
                            <span>ℹ️</span>
                            <span>Une fois invité, le livreur ouvre l’application <b>OVANIE Livreur</b> avec ce numéro pour renseigner son véhicule, ses zones et ses justificatifs. Son dossier apparaîtra ensuite dans « Dossiers à vérifier » pour validation par la Logistique.</span>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="dv8-footer">
                <button type="button" class="dv8-btn" data-directory-close>Annuler</button>
                <div class="dv8-footer-right">
                    <button type="submit" class="dv8-btn dv8-btn-primary">Inviter le livreur</button>
                </div>
            </footer>
        @endunless

        @if ($editing)
        <nav class="dv8-stepper" aria-label="Étapes du formulaire">
            @foreach ([
                ['Informations', 'Identité'],
                ['Zones', 'Communes'],
                ['Véhicule', 'Moyen'],
                ['Disponibilité', 'Planning'],
                ['Confirmation', 'Résumé'],
            ] as [$title, $subtitle])
                <div class="dv8-step" data-dv8-step-indicator>
                    <span class="dv8-step-number">{{ $loop->iteration }}</span>
                    <span class="dv8-step-copy"><strong>{{ $title }}</strong><small>{{ $subtitle }}</small></span>
                </div>
            @endforeach
        </nav>

        <div class="dv8-content">
            <section data-dv8-panel>
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Informations personnelles</h3>
                            <p>Identité et contact principal du livreur.</p>
                        </div>
                        <span class="dv8-badge">Photo facultative</span>
                    </div>
                    <div class="dv8-panel-body">
                        <div class="dv8-personal">
                            <div>
                                <span class="dv8-label">Photo de profil <small>Facultative</small></span>
                                <label class="dv8-photo">
                                    <img class="dv8-photo-preview" data-dv8-photo-preview src="{{ $driverAvatarUrl }}" alt="Aperçu de la photo" @if (! $hasDriverAvatar) hidden @endif>
                                    <span class="dv8-photo-placeholder" data-dv8-photo-placeholder @if ($hasDriverAvatar) hidden @endif>
                                        <span class="dv8-photo-icon">👤</span>
                                        <strong>Ajouter une photo</strong>
                                        <small>JPG ou PNG · max 2 Mo</small>
                                    </span>
                                    <input type="file" name="avatar" accept="image/jpeg,image/png,.jpg,.jpeg,.png" data-dv8-photo-input>
                                </label>
                                <small class="dv8-help" data-dv8-photo-name>{{ $hasDriverAvatar ? 'Photo actuelle enregistrée' : 'Aucune photo obligatoire.' }}</small>
                            </div>

                            <div class="dv8-personal-fields">
                                <label class="dv8-field">
                                    <span class="dv8-label">Nom complet <b class="dv8-required">*</b></span>
                                    <input class="dv8-input" name="name" required maxlength="190" autocomplete="name" value="{{ old('name', $driver?->name) }}" placeholder="Ex. Koné Adama">
                                </label>
                                <label class="dv8-field">
                                    <span class="dv8-label">Numéro de téléphone <b class="dv8-required">*</b></span>
                                    <input class="dv8-input" name="phone" required maxlength="30" inputmode="tel" autocomplete="tel" value="{{ old('phone', $driver?->phone) }}" placeholder="Ex. 07 00 00 00 00">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section data-dv8-panel hidden>
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Zones d’intervention</h3>
                            <p>Sélectionnez toutes les communes dans lesquelles ce livreur peut recevoir des missions.</p>
                        </div>
                        <span class="dv8-badge" data-dv8-zone-count>0 commune</span>
                    </div>
                    <div class="dv8-panel-body">
                        <div class="dv8-zone-toolbar">
                            <input class="dv8-search" type="search" placeholder="Rechercher une commune…" data-dv8-zone-search>
                            <button class="dv8-btn" type="button" data-dv8-zone-clear>Effacer</button>
                        </div>
                        <div class="dv8-zone-grid" data-dv8-zone-grid>
                            @foreach ($zones as $zone)
                                <label class="dv8-zone-card" data-dv8-zone-card data-zone-name="{{ mb_strtolower($zone->name) }}">
                                    <input type="checkbox" name="zone_ids[]" value="{{ $zone->id }}" @checked(in_array((string) $zone->id, $selectedZoneIds, true))>
                                    <span class="dv8-zone-box">✓</span>
                                    <span class="dv8-zone-name">{{ $zone->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="dv8-error" data-dv8-zone-error hidden>Sélectionnez au moins une commune pour continuer.</p>
                    </div>
                </div>
            </section>

            <section data-dv8-panel hidden>
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Véhicule du livreur</h3>
                            <p>Choisissez le type de véhicule autorisé dans OVANIE Logistics.</p>
                        </div>
                    </div>
                    <div class="dv8-panel-body">
                        <div class="dv8-two-cols">
                            <label class="dv8-field">
                                <span class="dv8-label">Véhicule <b class="dv8-required">*</b></span>
                                <select class="dv8-select" name="vehicle" required>
                                    <option value="">Sélectionner un véhicule</option>
                                    @foreach ($vehicleOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('vehicle', $driver?->vehicle) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="dv8-field">
                                <span class="dv8-label">Immatriculation <b class="dv8-required">*</b></span>
                                <input class="dv8-input" name="plate" required maxlength="40" value="{{ old('plate', data_get($driver?->profile, 'plate', '')) }}" placeholder="Ex. AB-1234-CI">
                            </label>
                        </div>
                        <div class="dv8-vehicle-info">
                            <span>ℹ️</span>
                            <span><b>Véhicules OVANIE :</b> Moto, Tricycle, Pickup, Camion 3T et Camion 10T. Le type choisi sert à l’affectation des missions selon le poids et le volume à transporter.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section data-dv8-panel hidden>
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Disponibilité</h3>
                            <p>Sélectionnez les jours habituels de travail du livreur.</p>
                        </div>
                    </div>
                    <div class="dv8-panel-body">
                        <div>
                            <span class="dv8-label">Jours de disponibilité <b class="dv8-required">*</b></span>
                            <div class="dv8-days" data-dv8-days>
                                @foreach ($days as $value => $label)
                                    <label class="dv8-day" data-dv8-day>
                                        <input type="checkbox" name="availability_days[]" value="{{ $value }}" @checked(in_array($value, $availabilityDays, true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="dv8-error" data-dv8-days-error hidden>Sélectionnez au moins un jour de disponibilité.</p>
                        </div>

                        <div class="dv8-availability-note">
                            Ces jours représentent la disponibilité habituelle du livreur. Son statut opérationnel peut ensuite évoluer selon les missions affectées.
                        </div>
                    </div>
                </div>
            </section>

            <section data-dv8-panel hidden>
                <div class="dv8-panel">
                    <div class="dv8-panel-head">
                        <div>
                            <h3>Confirmation</h3>
                            <p>Vérifiez les informations avant l’enregistrement.</p>
                        </div>
                        <span class="dv8-badge">Résumé</span>
                    </div>
                    <div class="dv8-panel-body">
                        <div class="dv8-summary">
                            <article class="dv8-summary-card">
                                <h4>Informations personnelles</h4>
                                <div class="dv8-summary-row"><span>Nom complet</span><strong data-summary-name>—</strong></div>
                                <div class="dv8-summary-row"><span>Téléphone</span><strong data-summary-phone>—</strong></div>
                                <div class="dv8-summary-row"><span>Photo</span><strong data-summary-photo>Facultative</strong></div>
                            </article>
                            <article class="dv8-summary-card">
                                <h4>Véhicule</h4>
                                <div class="dv8-summary-row"><span>Type</span><strong data-summary-vehicle>—</strong></div>
                                <div class="dv8-summary-row"><span>Immatriculation</span><strong data-summary-plate>—</strong></div>
                            </article>
                            <article class="dv8-summary-card">
                                <h4>Disponibilité</h4>
                                <div class="dv8-summary-row"><span>Jours de travail</span><strong data-summary-days>—</strong></div>
                            </article>
                            <article class="dv8-summary-card">
                                <h4>Couverture</h4>
                                <div class="dv8-summary-row"><span>Communes</span><strong data-summary-zone-count>0</strong></div>
                            </article>
                            <article class="dv8-summary-card wide">
                                <h4>Communes d’intervention</h4>
                                <div class="dv8-summary-zones" data-summary-zones></div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <footer class="dv8-footer">
            <button type="button" class="dv8-btn" data-directory-close>Annuler</button>
            <div class="dv8-footer-right">
                <button type="button" class="dv8-btn" data-dv8-prev hidden>Précédent</button>
                <button type="button" class="dv8-btn dv8-btn-primary" data-dv8-next>Suivant →</button>
            </div>
        </footer>
        @endif
    </form>
</dialog>

@if ($editing)
<script>
(function () {
    'use strict';

    const dialog = document.getElementById(@json($dialogId));
    const form = document.getElementById(@json($formId));
    if (!dialog || !form || dialog.dataset.dv8Ready === '1') return;
    dialog.dataset.dv8Ready = '1';

    const panels = Array.from(dialog.querySelectorAll('[data-dv8-panel]'));
    const indicators = Array.from(dialog.querySelectorAll('[data-dv8-step-indicator]'));
    const prevButton = dialog.querySelector('[data-dv8-prev]');
    const nextButton = dialog.querySelector('[data-dv8-next]');
    const zoneCards = Array.from(dialog.querySelectorAll('[data-dv8-zone-card]'));
    const dayCards = Array.from(dialog.querySelectorAll('[data-dv8-day]'));
    const zoneSearch = dialog.querySelector('[data-dv8-zone-search]');
    const zoneCount = dialog.querySelector('[data-dv8-zone-count]');
    const zoneError = dialog.querySelector('[data-dv8-zone-error]');
    const daysError = dialog.querySelector('[data-dv8-days-error]');
    let current = 0;

    const vehicleLabels = @json($vehicleOptions);
    const dayLabels = @json($days);

    function clearFieldErrors(panel) {
        panel.querySelectorAll('.dv8-error[data-generated-error]').forEach(node => node.remove());
        panel.querySelectorAll('.is-invalid').forEach(node => node.classList.remove('is-invalid'));
    }

    function fieldError(field, message) {
        field.classList.add('is-invalid');
        const note = document.createElement('span');
        note.className = 'dv8-error';
        note.dataset.generatedError = '1';
        note.textContent = message;
        field.insertAdjacentElement('afterend', note);
    }

    function selectedZones() {
        return zoneCards.filter(card => card.querySelector('input').checked);
    }

    function selectedDays() {
        return dayCards.filter(card => card.querySelector('input').checked);
    }

    function updateZones() {
        const selected = selectedZones();
        zoneCards.forEach(card => card.classList.toggle('is-selected', card.querySelector('input').checked));
        if (zoneCount) {
            zoneCount.textContent = selected.length + ' commune' + (selected.length > 1 ? 's' : '');
            zoneCount.classList.toggle('has-selection', selected.length > 0);
        }
        if (zoneError && selected.length) zoneError.hidden = true;
    }

    function updateDays() {
        const selected = selectedDays();
        dayCards.forEach(card => card.classList.toggle('is-selected', card.querySelector('input').checked));
        if (daysError && selected.length) daysError.hidden = true;
    }

    function validateStep(index) {
        const panel = panels[index];
        if (!panel) return false;
        clearFieldErrors(panel);

        if (index === 0) {
            const name = form.elements['name'];
            const phone = form.elements['phone'];
            let valid = true;
            if (!name.value.trim()) { fieldError(name, 'Renseignez le nom complet du livreur.'); valid = false; }
            if (!phone.value.trim()) { fieldError(phone, 'Renseignez le numéro de téléphone.'); valid = false; }
            if (!valid) panel.querySelector('.is-invalid')?.focus();
            return valid;
        }

        if (index === 1) {
            if (!selectedZones().length) {
                if (zoneError) zoneError.hidden = false;
                return false;
            }
            return true;
        }

        if (index === 2) {
            const vehicle = form.elements['vehicle'];
            const plate = form.elements['plate'];
            let valid = true;
            if (!vehicle.value) { fieldError(vehicle, 'Sélectionnez le véhicule du livreur.'); valid = false; }
            if (!plate.value.trim()) { fieldError(plate, 'Renseignez l’immatriculation du véhicule.'); valid = false; }
            if (!valid) panel.querySelector('.is-invalid')?.focus();
            return valid;
        }

        if (index === 3) {
            if (!selectedDays().length) {
                if (daysError) daysError.hidden = false;
                return false;
            }
            return true;
        }

        return true;
    }

    function refreshSummary() {
        const name = form.elements['name']?.value.trim() || '—';
        const phone = form.elements['phone']?.value.trim() || '—';
        const vehicle = form.elements['vehicle']?.value || '';
        const plate = form.elements['plate']?.value.trim() || '—';
        const zones = selectedZones().map(card => card.querySelector('.dv8-zone-name')?.textContent.trim()).filter(Boolean);
        const days = selectedDays().map(card => dayLabels[card.querySelector('input').value] || card.querySelector('input').value);
        const photoInput = dialog.querySelector('[data-dv8-photo-input]');
        const photo = photoInput?.files?.length ? 'Photo sélectionnée' : (@json($hasDriverAvatar) ? 'Photo enregistrée' : 'Aucune photo');

        dialog.querySelector('[data-summary-name]').textContent = name;
        dialog.querySelector('[data-summary-phone]').textContent = phone;
        dialog.querySelector('[data-summary-photo]').textContent = photo;
        dialog.querySelector('[data-summary-vehicle]').textContent = vehicleLabels[vehicle] || '—';
        dialog.querySelector('[data-summary-plate]').textContent = plate;
        dialog.querySelector('[data-summary-days]').textContent = days.length ? days.join(', ') : '—';
        dialog.querySelector('[data-summary-zone-count]').textContent = zones.length.toString();

        const zoneBox = dialog.querySelector('[data-summary-zones]');
        zoneBox.replaceChildren();
        zones.forEach(zone => {
            const chip = document.createElement('span');
            chip.className = 'dv8-summary-chip';
            chip.textContent = zone;
            zoneBox.appendChild(chip);
        });
        if (!zones.length) {
            const empty = document.createElement('span');
            empty.className = 'dv8-help';
            empty.textContent = 'Aucune commune sélectionnée.';
            zoneBox.appendChild(empty);
        }
    }

    function showStep(index) {
        current = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach((panel, i) => panel.hidden = i !== current);
        indicators.forEach((indicator, i) => {
            indicator.classList.toggle('is-active', i === current);
            indicator.classList.toggle('is-done', i < current);
        });
        prevButton.hidden = current === 0;
        nextButton.textContent = current === panels.length - 1
            ? (@json($editing ? 'Enregistrer les modifications' : 'Enregistrer le livreur'))
            : 'Suivant →';
        if (current === panels.length - 1) refreshSummary();
    }

    nextButton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (current < panels.length - 1) {
            if (!validateStep(current)) return;
            showStep(current + 1);
            return;
        }
        if (!validateStep(current)) return;
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });

    prevButton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        showStep(current - 1);
    });

    zoneCards.forEach(card => {
        card.querySelector('input').addEventListener('change', updateZones);
    });

    dayCards.forEach(card => {
        card.querySelector('input').addEventListener('change', updateDays);
    });

    zoneSearch?.addEventListener('input', function () {
        const term = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        zoneCards.forEach(card => {
            const name = (card.dataset.zoneName || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            card.hidden = !!term && !name.includes(term);
        });
    });

    dialog.querySelector('[data-dv8-zone-clear]')?.addEventListener('click', function () {
        zoneCards.forEach(card => card.querySelector('input').checked = false);
        updateZones();
    });

    const photoInput = dialog.querySelector('[data-dv8-photo-input]');
    photoInput?.addEventListener('change', function () {
        const preview = dialog.querySelector('[data-dv8-photo-preview]');
        const placeholder = dialog.querySelector('[data-dv8-photo-placeholder]');
        const label = dialog.querySelector('[data-dv8-photo-name]');
        const file = this.files?.[0];
        if (!file) return;
        if (!file.type.startsWith('image/') || file.size > 2 * 1024 * 1024) {
            this.value = '';
            if (label) label.textContent = file.size > 2 * 1024 * 1024 ? 'Photo trop volumineuse : 2 Mo maximum.' : 'Le fichier choisi doit être une image.';
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            preview.src = reader.result;
            preview.hidden = false;
            placeholder.hidden = true;
            if (label) label.textContent = file.name;
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            nextButton.click();
        }
    });

    updateZones();
    updateDays();
    showStep(0);
})();
</script>
@endif
