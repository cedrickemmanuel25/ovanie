@extends('layouts.staff')

@section('title', 'Import produits CSV | Commercial OVANIE')

@section('content')
<style>
.import-page{max-width:1180px;margin:0 auto;color:#17345f}
.import-heading{margin-bottom:16px}
.import-eyebrow{display:flex;align-items:center;gap:7px;margin-bottom:7px;color:#f97316;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.import-heading h1{margin:0;color:#102f5b;font-size:30px;line-height:1.15;letter-spacing:-.025em}
.import-heading p{max-width:760px;margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.55}
.import-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(290px,.65fr);gap:20px;align-items:start}
.import-card{margin-bottom:18px;padding:22px;background:#fff;border:1px solid #e1e8f0;border-radius:16px;box-shadow:0 12px 34px rgba(15,23,42,.05)}
.import-card h2{margin:0;color:#17345f;font-size:18px}
.import-card>p{margin:6px 0 18px;color:#718096;font-size:12px;line-height:1.5}
.import-side{position:sticky;top:20px}
.process-list{display:grid;gap:12px;margin:0;padding:0;list-style:none}
.process-list li{display:grid;grid-template-columns:38px minmax(0,1fr);gap:11px;align-items:start}
.process-number{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#173e79;color:#fff;font-size:14px;font-weight:900}
.process-list strong{display:block;margin:2px 0 4px;color:#203c64;font-size:13px}
.process-list span{display:block;color:#718096;font-size:11px;line-height:1.5}
.template-button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:47px;margin-top:17px;border:1px solid #f97316;border-radius:10px;background:#fff7ed;color:#d95d0b;font-size:13px;font-weight:900;text-decoration:none}
.import-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.field label{display:block;margin-bottom:7px;color:#294364;font-size:12px;font-weight:850}
.field select{width:100%;height:48px;border:1px solid #d6e0eb;border-radius:10px;padding:0 13px;background:#fff;color:#18345f;font-size:14px;outline:none}
.field select:focus{border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.mode-choice-group{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mode-choice{position:relative;display:flex;align-items:flex-start;gap:10px;min-height:72px;padding:12px;border:1px solid #d8e2ed;border-radius:11px;background:#fff;cursor:pointer;transition:.15s}
.mode-choice:hover{border-color:#f4a261;background:#fffaf5}
.mode-choice input[type=radio]{position:absolute;opacity:0;pointer-events:none;width:1px!important;height:1px!important}
.mode-choice-icon{display:grid;place-items:center;flex:0 0 34px;width:34px;height:34px;border-radius:9px;background:#eff6ff;color:#174985}
.mode-choice strong{display:block;color:#294364;font-size:12px}
.mode-choice span{display:block;margin-top:3px;color:#758399;font-size:10px;line-height:1.35}
.mode-choice:has(input:checked){border:2px solid #f97316;padding:11px;background:#fff7ed}
.mode-choice:has(input:checked) .mode-choice-icon{background:#f97316;color:#fff}
.file-zone{grid-column:1/-1;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:210px;border:1.5px dashed #aebfd2;border-radius:14px;padding:24px;text-align:center;background:linear-gradient(180deg,#fbfdff,#f8fafc);cursor:pointer;transition:.15s}
.file-zone:hover,.file-zone.dragover{border-color:#f97316;background:#fffaf5}
.file-zone input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.file-zone-icon{display:grid;place-items:center;width:58px;height:58px;border-radius:15px;background:#eff6ff;color:#174985;margin-bottom:12px}
.file-zone strong{display:block;color:#17345f;font-size:16px}
.file-zone span{display:block;margin-top:6px;color:#718096;font-size:11px;line-height:1.5}
.file-name{display:none;margin-top:13px;padding:9px 12px;border-radius:9px;background:#ecfdf5;color:#166534;font-size:11px;font-weight:800}
.file-name.visible{display:block}
.import-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:47px;padding:0 17px;border:1px solid #d8e2ed;border-radius:10px;background:#fff;color:#17345f;font-size:13px;font-weight:850;text-decoration:none;cursor:pointer;transition:.15s}
.import-button:hover{border-color:#f4a261;color:#d95d0b}
.import-button.primary{border-color:#f97316;background:#f97316;color:#fff;box-shadow:0 8px 18px rgba(249,115,22,.18)}
.import-button.primary:hover{background:#e9650d;color:#fff}
.submit-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:17px}
.columns-table{width:100%;border-collapse:separate;border-spacing:0;font-size:11px}
.columns-table th,.columns-table td{text-align:left;padding:12px;border-top:1px solid #e8edf3;vertical-align:top}
.columns-table th{background:#f8fafc;color:#294364;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
.columns-table th:first-child{border-top-left-radius:9px}.columns-table th:last-child{border-top-right-radius:9px}
.required-badge,.optional-badge{display:inline-flex;border-radius:999px;padding:4px 7px;font-size:9px;font-weight:900}
.required-badge{background:#fff1f2;color:#be123c}.optional-badge{background:#f1f5f9;color:#64748b}
.code-cell{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#174985;font-weight:750}
.report-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0}
.report-box{border-radius:12px;background:#f8fafc;border:1px solid #e5eaf1;padding:14px}
.report-box strong{display:block;color:#173e79;font-size:24px}
.report-box span{display:block;margin-top:4px;color:#78869a;font-size:10px}
.error-list{max-height:280px;overflow:auto;margin-top:12px;padding:13px;background:#fff7ed;border:1px solid #fed7aa;border-radius:11px}
.error-list p{margin:0 0 7px;color:#9a4310;font-size:10px;line-height:1.45}
.alert-import{margin-bottom:16px;border-radius:12px;padding:13px 15px;font-size:12px;line-height:1.5}
.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}.danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
.info-box{margin-top:18px;padding:13px;border:1px solid #cfe0ff;border-radius:11px;background:#eff6ff;color:#31527e;font-size:11px;line-height:1.5}
@media(max-width:940px){.import-layout{grid-template-columns:1fr}.import-side{position:static;order:-1}}
@media(max-width:700px){.import-heading h1{font-size:25px}.import-heading p{font-size:12px}.import-grid{grid-template-columns:1fr}.file-zone{grid-column:auto;min-height:180px}.mode-choice-group{grid-template-columns:1fr}.report-grid{grid-template-columns:1fr 1fr}.submit-row{flex-direction:column}.submit-row .import-button{width:100%}.import-card{padding:17px 15px}.columns-table{min-width:760px}}
</style>

<div class="import-page">
    <header class="import-heading">
        <div class="import-eyebrow"><i data-lucide="file-spreadsheet"></i> Enregistrement en volume</div>
        <h1>Importer plusieurs produits avec un fichier CSV</h1>
        <p>Ce mode convient à une boutique qui possède déjà une liste de prix et de stock. Les données techniques et logistiques sont reprises automatiquement depuis le catalogue maître OVANIE.</p>
    </header>

    @include('commercial.products.partials.mode-navigation', [
        'activeMode' => 'import',
        'workspaceShopId' => $selectedShop?->id,
        'workspaceShop' => $selectedShop,
    ])

    @if(session('success'))
        <div class="alert-import success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-import danger"><strong>Import impossible :</strong> {{ $errors->first() }}</div>
    @endif

    @if(session('import_report'))
        @php($report = session('import_report'))
        <section class="import-card">
            <h2>Rapport du dernier import</h2>
            <p>Chaque ligne valide a été traitée. Les lignes en erreur sont listées séparément et n’empêchent pas les autres produits d’être enregistrés.</p>
            <div class="report-grid">
                <div class="report-box"><strong>{{ $report['created'] }}</strong><span>Nouveaux produits</span></div>
                <div class="report-box"><strong>{{ $report['updated'] }}</strong><span>Produits mis à jour</span></div>
                <div class="report-box"><strong>{{ $report['published'] }}</strong><span>Produits publiés</span></div>
                <div class="report-box"><strong>{{ $report['drafts'] }}</strong><span>Brouillons ou non publiés</span></div>
            </div>
            @if(!empty($report['errors']))
                <div class="error-list">
                    <strong style="display:block;margin-bottom:8px;color:#9a4310">Lignes non importées</strong>
                    @foreach($report['errors'] as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="import-layout">
        <main>
            <section class="import-card">
                <h2>Importer le fichier</h2>
                <p>Sélectionnez la boutique, choisissez le mode d’enregistrement, puis ajoutez le fichier CSV préparé avec le modèle OVANIE.</p>

                @if($shops->isEmpty())
                    <p>Aucune boutique n’est attribuée à votre compte Commercial.</p>
                @else
                    <form id="importForm" method="POST" action="{{ route('commercial.products.import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="import-grid">
                            <div class="field">
                                <label for="shopId">Boutique concernée *</label>
                                <select id="shopId" name="shop_id" required>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}" @selected((int) old('shop_id', $selectedShop?->id) === $shop->id)>
                                            {{ $shop->name }} — {{ $shop->user?->name ?: $shop->user?->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Mode d’enregistrement *</label>
                                <div class="mode-choice-group">
                                    <label class="mode-choice">
                                        <input type="radio" name="intent" value="publish" @checked(old('intent', 'publish') === 'publish')>
                                        <span class="mode-choice-icon"><i data-lucide="badge-check"></i></span>
                                        <span><strong>Publier</strong><span>Publier uniquement les fiches complètes.</span></span>
                                    </label>
                                    <label class="mode-choice">
                                        <input type="radio" name="intent" value="draft" @checked(old('intent') === 'draft')>
                                        <span class="mode-choice-icon"><i data-lucide="save"></i></span>
                                        <span><strong>Brouillon</strong><span>Tout enregistrer sans publication.</span></span>
                                    </label>
                                </div>
                            </div>

                            <label class="file-zone" for="csvFile" id="fileZone">
                                <input id="csvFile" type="file" name="file" accept=".csv,text/csv,text/plain" required>
                                <span class="file-zone-icon"><i data-lucide="upload-cloud"></i></span>
                                <strong>Choisir le fichier CSV</strong>
                                <span>Cliquez ici ou déposez le fichier dans cette zone.<br>5 Mo maximum · 500 lignes maximum.</span>
                                <span class="file-name" id="fileName"></span>
                            </label>
                        </div>

                        <div class="submit-row">
                            <button class="import-button primary" type="submit"><i data-lucide="upload"></i>Lancer l’import</button>
                            <a class="import-button" href="{{ route('commercial.products.import.template') }}"><i data-lucide="download"></i>Télécharger le modèle CSV</a>
                        </div>
                    </form>
                @endif
            </section>

            <section class="import-card">
                <h2>Colonnes attendues dans le fichier</h2>
                <p>L’identifiant produit doit correspondre au SKU, à la référence OVANIE ou à l’identifiant numérique d’un produit maître.</p>
                <div style="overflow:auto">
                    <table class="columns-table">
                        <thead><tr><th>Colonne</th><th>Statut</th><th>Exemple</th><th>Utilité</th></tr></thead>
                        <tbody>
                            <tr><td class="code-cell">identifiant_produit</td><td><span class="required-badge">Obligatoire</span></td><td>CIM-BELIER-50</td><td>SKU, référence ou ID du produit maître</td></tr>
                            <tr><td class="code-cell">prix_fcfa</td><td><span class="required-badge">Obligatoire</span></td><td>5000</td><td>Prix de vente de la boutique</td></tr>
                            <tr><td class="code-cell">stock</td><td><span class="required-badge">Obligatoire</span></td><td>200</td><td>Quantité disponible</td></tr>
                            <tr><td class="code-cell">quantite_minimale</td><td><span class="optional-badge">Facultatif</span></td><td>1</td><td>Quantité minimale de commande</td></tr>
                            <tr><td class="code-cell">prix_promotionnel</td><td><span class="optional-badge">Facultatif</span></td><td>4750</td><td>Doit être inférieur au prix normal</td></tr>
                            <tr><td class="code-cell">disponibilite</td><td><span class="optional-badge">Facultatif</span></td><td>in_stock</td><td>in_stock, on_order, preorder ou out_of_stock</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside class="import-card import-side">
            <h2>Comment tester ce formulaire ?</h2>
            <p>Suivez ces trois étapes dans l’ordre.</p>
            <ol class="process-list">
                <li><span class="process-number">1</span><span><strong>Télécharger le modèle</strong><span>Ouvrez le CSV avec Excel ou LibreOffice.</span></span></li>
                <li><span class="process-number">2</span><span><strong>Remplir les lignes</strong><span>Utilisez un SKU ou une référence qui existe dans le catalogue maître OVANIE.</span></span></li>
                <li><span class="process-number">3</span><span><strong>Importer et contrôler</strong><span>Vérifiez le rapport : créés, mis à jour, publiés, brouillons et erreurs.</span></span></li>
            </ol>
            <a class="template-button" href="{{ route('commercial.products.import.template') }}"><i data-lucide="download"></i>Télécharger le modèle maintenant</a>
            <div class="info-box"><strong>Important :</strong> l’import ne crée pas une nouvelle fiche technique. Il crée une offre de boutique à partir d’un produit déjà présent dans le catalogue maître.</div>
        </aside>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('importForm');
    const input = document.getElementById('csvFile');
    const fileName = document.getElementById('fileName');
    const zone = document.getElementById('fileZone');

    function showFile(file) {
        if (!file || !fileName) return;
        fileName.textContent = `${file.name} · ${(file.size / 1024).toFixed(1)} Ko`;
        fileName.classList.add('visible');
    }

    input?.addEventListener('change', () => showFile(input.files?.[0]));

    ['dragenter', 'dragover'].forEach(eventName => {
        zone?.addEventListener(eventName, event => {
            event.preventDefault();
            zone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        zone?.addEventListener(eventName, event => {
            event.preventDefault();
            zone.classList.remove('dragover');
        });
    });

    zone?.addEventListener('drop', event => {
        const file = event.dataTransfer?.files?.[0];
        if (!file || !input) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        showFile(file);
    });

    form?.addEventListener('submit', function(event) {
        if (this.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        this.dataset.submitting = '1';
        const button = this.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.innerHTML = 'Import en cours…';
        }
    });
})();
</script>
@endsection
