@extends('admin.layouts.app')

@section('title', 'Formulaire de réception')

@section('content')
    <main class="admin-main" style="padding:24px;">
        <div style="max-width:1300px; margin:0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1 style="margin:0 0 8px; font-size:28px; font-weight:800; color:#111827;">
                        Formulaire de réception
                    </h1>
                    <p style="margin:0; color:#6b7280;">
                        Consultation du formulaire rempli par le client
                    </p>
                </div>

                <a href="{{ route('admin.orders.reception.pdf', $order->id) }}" target="_blank" style="
                  background: linear-gradient(135deg, #111111, #2b2b2b);
                  color: #ffffff;
                  padding: 11px 18px;
                  border-radius: 12px;
                  text-decoration: none;
                  font-weight: 800;
                  box-shadow: 0 10px 20px rgba(17,17,17,.14);
                  display: inline-flex;
                  align-items: center;
                  gap: 8px;
               ">
                    📄 Télécharger PDF
                </a>
            </div>

            <div
                style="background:#fff; border-radius:22px; padding:24px; box-shadow:0 10px 30px rgba(15,23,42,.06); margin-bottom:20px;">
                <div style="display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:16px;">
                    <div><strong>Commande :</strong><br>#{{ $order->order_number }}</div>
                    <div><strong>Client :</strong><br>{{ $form->client_name }}</div>
                    <div><strong>Téléphone :</strong><br>{{ $form->client_phone ?: '—' }}</div>
                    <div><strong>Code client :</strong><br>{{ $form->client_code ?: '—' }}</div>
                    <div><strong>Lieu réception :</strong><br>{{ $form->reception_place ?: '—' }}</div>
                    <div><strong>Livreur :</strong><br>{{ $form->courier_name ?: '—' }}</div>
                    <div><strong>Statut :</strong><br>{{ $form->status === 'validated' ? 'Validé' : 'Brouillon' }}</div>
                    <div><strong>Ville :</strong><br>{{ $form->validated_city ?: '—' }}</div>
                    <div><strong>Date validation :</strong><br>{{ $form->validated_date?->format('d/m/Y') ?: '—' }}</div>
                    <div><strong>Date/heure validation :</strong><br>{{ $form->validated_at?->format('d/m/Y H:i') ?: '—' }}
                    </div>
                </div>
            </div>

            <div
                style="background:#fff; border-radius:22px; padding:24px; box-shadow:0 10px 30px rgba(15,23,42,.06); overflow:auto;">
                <table style="width:100%; border-collapse:collapse; min-width:1100px;">
                    <thead>
                        <tr style="background:#111827; color:#fff;">
                            <th style="padding:14px; text-align:left;">Désignation</th>
                            <th style="padding:14px; text-align:left;">Qté</th>
                            <th style="padding:14px; text-align:left;">Prix unitaire</th>
                            <th style="padding:14px; text-align:left;">Montant</th>
                             <th style="padding:14px; text-align:left;">Gain vendeur</th>
                            <th style="padding:14px; text-align:left;">Commission OVANIE</th>
                            <th style="padding:14px; text-align:left;">Boutique</th>
                            <th style="padding:14px; text-align:left; color:#ff6b6b;">Livreur</th>
                            <th style="padding:14px; text-align:left; color:#ff6b6b;">N° pièce</th>
                            <th style="padding:14px; text-align:left; color:#ff6b6b;">Recto</th>
                            <th style="padding:14px; text-align:left; color:#ff6b6b;">Verso</th>
                            <th style="padding:14px; text-align:left;">Paiement</th>
                            <th style="padding:14px; text-align:left;">Réception</th>
                           
                            <th style="padding:14px; text-align:left;">Date</th>
                            <th style="padding:14px; text-align:left;">Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($form->items as $line)
                            <tr style="{{ (($line->payment_status ?? 'pending') === 'paid' || $line->received) ? 'background:#ecfdf3;' : '' }}">
                                <td style="padding:14px; border-bottom:1px solid #084888;">{{ $line->product_name }}</td>
                                <td style="padding:14px; border-bottom:1px solid #0f6ecd;">{{ $line->quantity }}</td>
                                <td style="padding:14px; border-bottom:1px solid #0d67c1;">
                                    {{ number_format($line->unit_price, 0, ',', ' ') }} FCFA
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #077cf2;">
                                    {{ number_format($line->amount, 0, ',', ' ') }} FCFA
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #097ff5;">
                                    {{ number_format($line->vendor_gain, 0, ',', ' ') }} FCFA
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #037af2;">
                                    {{ number_format($line->site_commission, 0, ',', ' ') }} FCFA
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #0582fe;">{{ $line->shop_name ?: '—' }}</td>
                                <td style="padding:14px; border-bottom:1px solid #057ef8;">{{ $line->courier_name ?: '—' }}</td>
                                <td style="padding:14px; border-bottom:1px solid #0381fe;">{{ $line->courier_id_number ?: '—' }}
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #027ffc;">
                                    @if($line->courier_id_front_path)
                                        <a href="{{ route('admin.private-documents.reception', [$line, 'front']) }}" target="_blank">Voir
                                            recto</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #047df6;">
                                    @if($line->courier_id_back_path)
                                        <a href="{{ route('admin.private-documents.reception', [$line, 'back']) }}" target="_blank">Voir
                                            verso</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #057cf3;">
                                    @if(($line->payment_status ?? 'pending') === 'paid')
                                        <span style="display:inline-flex; padding:7px 12px; border-radius:999px; background:#dcfce7; color:#166534; font-weight:800;">Payé</span>
                                        <div style="font-size:11px; color:#166534; margin-top:4px;">
                                            {{ $line->paid_at ? $line->paid_at->format('d/m/Y H:i') : '' }}
                                        </div>
                                    @else
                                        <span style="display:inline-flex; padding:7px 12px; border-radius:999px; background:#f3f4f6; color:#374151; font-weight:800;">Non payé</span>
                                    @endif
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #057cf3;">
                                    @if($line->received)
                                        <span
                                            style="display:inline-flex; padding:7px 12px; border-radius:999px; background:#dcfce7; color:#166534; font-weight:800;">Reçu</span>
                                    @else
                                        <span
                                            style="display:inline-flex; padding:7px 12px; border-radius:999px; background:#f3f4f6; color:#374151; font-weight:800;">En
                                            attente</span>
                                    @endif
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #0682fe;">
                                    {{ $line->received_date?->format('d/m/Y') ?: '—' }}
                                </td>
                                <td style="padding:14px; border-bottom:1px solid #047df7;">{{ $line->received_time ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
