<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Formulaire de réception - OVANIE</title>
    <style>
        @page {
            margin: 28px 26px 34px 26px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1b1b1b;
            line-height: 1.45;
        }

        .page {
            border: 1px solid #d8c08a;
            padding: 18px 18px 22px 18px;
        }

        .top-bar {
            height: 8px;
            background: #b48a2c;
            margin: -18px -18px 18px -18px;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #111111;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #111111;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #7a6a4a;
        }

        .doc-title-box {
            text-align: right;
        }

        .doc-title {
            font-size: 18px;
            font-weight: 800;
            color: #111111;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .doc-ref {
            font-size: 11px;
            color: #6b7280;
        }

        .notice {
            background: linear-gradient(180deg, #fff1f1, #ffe5e5);
            border: 2px solid #ff0000;
            padding: 12px 14px;
            font-size: 11.5px;
            color: #b80000;
            font-weight: 700;
            margin-bottom: 16px;
            border-radius: 6px;

            /* effet visuel fort */
            box-shadow: 0 0 0 2px rgba(255, 0, 0, 0.08),
                0 6px 14px rgba(255, 0, 0, 0.15);

            position: relative;
        }

        .notice::before {
            content: "⚠️ NOTICE IMPORTANTE";
            display: block;
            font-size: 10px;
            font-weight: 900;
            color: #ff0000;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #111111;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #d9d9d9;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .info-table td {
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .info-label {
            display: block;
            font-size: 10px;
            color: #8a8f98;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 11px;
            font-weight: 700;
            color: #111111;
            word-wrap: break-word;
        }

        .status-box {
            margin-bottom: 18px;
            padding: 10px 12px;
            border: 1px solid #d9d9d9;
            background: #fafafa;
        }

        .status-label {
            font-weight: 800;
            color: #111111;
        }

        .status-validated {
            color: #1f7a1f;
            font-weight: 800;
        }

        .status-draft {
            color: #b45309;
            font-weight: 800;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .items-table th {
            background: #111111;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 10px 8px;
            border: 1px solid #111111;
            text-align: left;
        }

        .items-table td {
            border: 1px solid #dcdfe4;
            padding: 9px 8px;
            font-size: 10.5px;
            vertical-align: middle;
        }

        .items-table tr.received-row td {
            background: #eefaf0;
        }

        .items-table tr:nth-child(even):not(.received-row) td {
            background: #fcfcfc;
        }

        .amount {
            font-weight: 800;
            color: #111111;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: 800;
            border-radius: 10px;
            text-transform: uppercase;
        }

        .badge-ok {
            background: #dcf3df;
            color: #166534;
            border: 1px solid #9fd6a8;
        }

        .badge-paid {
            background: #dcf3df;
            color: #166534;
            border: 1px solid #9fd6a8;
        }

        .badge-no {
            background: #f1f3f5;
            color: #4b5563;
            border: 1px solid #d7dce2;
        }

        .footer {
            margin-top: 26px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }

        .signature-table td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 0 10px 0 0;
        }

        .signature-box {
            border-top: 1px solid #111111;
            padding-top: 8px;
            margin-top: 44px;
            font-size: 11px;
            font-weight: 700;
            color: #111111;
        }

        .gold-line {
            height: 2px;
            background: #b48a2c;
            margin-top: 18px;
            margin-bottom: 10px;
        }

        .footer-note {
            text-align: center;
            font-size: 10px;
            color: #7a7a7a;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="top-bar"></div>

        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 58%;">
                        <div class="brand-title">OVANIE</div>
                        <div class="brand-subtitle">
                            Marketplace BTP &amp; Énergie — Formulaire officiel de réception
                        </div>
                    </td>
                    <td class="doc-title-box" style="width: 42%;">
                        <div class="doc-title">Formulaire de réception</div>
                        <div class="doc-ref">
                            Commande #{{ $order->order_number }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="notice">
            <strong style="color:#ff0000;">ATTENTION :</strong>
            sans confirmation de paiement ou de livraison, les paiements et réclamations liés à cette commande
            peuvent ne pas être pris en charge. Ce document atteste l’état de réception des produits
            déclarés par le client.
        </div>

        <div class="section-title">Informations générales</div>

        <table class="info-table">
            <tr>
                <td>
                    <span class="info-label">Client</span>
                    <span class="info-value">{{ $form->client_name ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Téléphone</span>
                    <span class="info-value">{{ $form->client_phone ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Code client</span>
                    <span class="info-value">{{ $form->client_code ?: '—' }}</span>
                </td>

            </tr>
            <tr>

                <td>
                    <span class="info-label">Lieu de réception</span>
                    <span class="info-value">{{ $form->reception_place ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Nom du livreur</span>
                    <span class="info-value">{{ $form->courier_name ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Ville de validation</span>
                    <span class="info-value">{{ $form->validated_city ?: '—' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="info-label">Statut</span>
                    <span class="info-value">
                        @if($form->status === 'validated')
                            <span class="status-validated">Validé</span>
                        @else
                            <span class="status-draft">Brouillon</span>
                        @endif
                    </span>
                </td>
                <td>
                    <span class="info-label">Date de validation</span>
                    <span class="info-value">{{ $form->validated_date?->format('d/m/Y') ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Date / heure validation</span>
                    <span class="info-value">{{ $form->validated_at?->format('d/m/Y H:i') ?: '—' }}</span>
                </td>
                <td>
                    <span class="info-label">Commande</span>
                    <span class="info-value">#{{ $order->order_number }}</span>
                </td>
            </tr>
        </table>

        <div class="status-box">
            <span class="status-label">État du formulaire :</span>
            @if($form->status === 'validated')
                <span class="status-validated">formulaire validé par le client</span>
            @else
                <span class="status-draft">formulaire enregistré en brouillon</span>
            @endif
        </div>

        <div class="section-title">Détail des produits déclarés</div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Désignation</th>
                    <th style="width: 6%;">Qté</th>
                    <th style="width: 11%;">Prix unitaire</th>
                    <th style="width: 11%;">Montant</th>
                    <th style="width: 10%;">Gain vendeur</th>
                    <th style="width: 10%;">Commission</th>
                    <th style="width: 12%;">Boutique</th>
                    <th style="width: 12%; color:#ff4d4f;">Livreur</th>
                    <th style="width: 12%; color:#ff4d4f;">N° pièce</th>
                    <th style="width: 7%;">Paiement</th>
                    <th style="width: 6%;">Date</th>
                    <th style="width: 6%;">Heure</th>
                </tr>
            </thead>
            <tbody>
                @foreach($form->items as $line)
                    <tr class="{{ (($line->payment_status ?? 'pending') === 'paid' || $line->received) ? 'received-row' : '' }}">
                        <td>{{ $line->product_name }}</td>
                        <td>{{ $line->quantity }}</td>
                        <td>{{ number_format($line->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="amount">{{ number_format($line->amount, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $line->vendor_gain ?: '—' }}</td>
                        <td>{{ $line->site_commission ?: '—' }}</td>
                        <td>{{ $line->shop_name ?: '—' }}</td>
                        <td>{{ $line->courier_name ?: '—' }}</td>
                        <td>{{ $line->courier_id_number ?: '—' }}</td>
                        <td>
                            @if(($line->payment_status ?? 'pending') === 'paid')
                                <span class="badge badge-paid">Payé</span>
                            @else
                                <span class="badge badge-no">Non payé</span>
                            @endif
                        </td>
                        <td>{{ $line->received_date?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $line->received_time ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:10px; font-size:10px; color:#6b7280;">
            Les pièces d’identité recto/verso des livreurs sont archivées électroniquement dans le dossier de réception
            associé à cette commande. Les lignes marquées Payé sont considérées réglées par PayDunya.
        </div>
        <div class="footer">
            <table class="signature-table">
                <tr>
                    <td>
                        <div>
                            Fait à : <strong>{{ $form->validated_city ?: '..........................' }}</strong><br>
                            Le :
                            <strong>{{ $form->validated_date?->format('d/m/Y') ?: '..........................' }}</strong>
                        </div>

                        <div class="signature-box">
                            Signature / confirmation client
                        </div>
                    </td>
                    <td>
                        <div style="text-align:right;">
                            Référence interne : <strong>FR-{{ $order->order_number }}</strong>
                        </div>

                        <div class="signature-box" style="text-align:right;">
                            Cachet / validation administrative
                        </div>
                    </td>
                </tr>
            </table>

            <div class="gold-line"></div>

            <div class="footer-note">
                Document généré depuis l’administration OVANIE — usage interne et archivage
            </div>
        </div>
    </div>
</body>

</html>