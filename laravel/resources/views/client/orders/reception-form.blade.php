@extends('layouts.client')
@section('title', 'Confirmation de réception')
@section('content')
@php
    $allPaid = (float) ($outstandingAmount ?? 0) <= 0;
    $allReceived = $form->items->every(fn($line) => (bool) $line->received);
@endphp

<div class="cs-breadcrumb">
    <a href="{{ route('client.dashboard') }}">Mon compte</a> ›
    <a href="{{ route('client.orders') }}">Mes commandes</a> ›
    <a href="{{ route('client.orders.show', $order) }}">#{{ $order->order_number }}</a> ›
    Réception
</div>

<section class="cs-page-head">
    <div>
        <h1>Confirmer la réception</h1>
        <p>Vérifiez chaque article réellement reçu avant la validation définitive.</p>
    </div>
    <span class="cs-badge {{ $form->status === 'validated' ? 'success' : 'warning' }}">
        {{ $form->status === 'validated' ? 'Réception validée' : 'En attente de confirmation' }}
    </span>
</section>

@if($form->status === 'validated' && !$allPaid)
    <section class="cs-card">
        <div class="cs-note-list">
            <p><strong>Réception confirmée.</strong> Le paiement reste un processus indépendant : vous pouvez encore régler le solde restant ci-dessous.</p>
        </div>
    </section>
@endif

<section class="cs-card">
    <div class="cs-section-head">
        <div>
            <h2>Commande #{{ $order->order_number }}</h2>
            <p>Client : {{ $client->name }} · Référence : {{ $clientCode }}</p>
        </div>
        @if($form->validated_at)
            <small>Validée le {{ $form->validated_at->format('d/m/Y à H:i') }}</small>
        @endif
    </div>
</section>

<form method="POST" action="{{ route('client.orders.reception.save', $order) }}" data-reception-form>
    @csrf

    <section class="cs-card">
        <h2>Informations de réception</h2>
        <div class="cs-form-grid">
            <label>Lieu de livraison
                <input type="text" name="delivery_place" value="{{ old('delivery_place', $form->delivery_place ?: ($order->delivery_address ?: $order->address)) }}" @disabled($form->status === 'validated')>
            </label>
            <label>Lieu de réception
                <input type="text" name="reception_place" value="{{ old('reception_place', $form->reception_place) }}" placeholder="Ex. chantier, domicile, dépôt" @disabled($form->status === 'validated')>
            </label>
            <label>Ville de validation
                <input type="text" name="validated_city" value="{{ old('validated_city', $form->validated_city ?: $order->delivery_city) }}" @disabled($form->status === 'validated')>
            </label>
        </div>
    </section>

    <section class="cs-card">
        <div class="cs-section-head">
            <div>
                <h2>Articles de la commande</h2>
                <p>Le paiement et la réception sont deux statuts distincts.</p>
            </div>
            @if(!$allPaid)
                <button type="submit" form="pay-all-form" class="cs-btn outline">Payer le solde · {{ number_format($outstandingAmount, 0, ',', ' ') }} FCFA</button>
            @endif
        </div>

        <div class="cs-table-wrap">
            <table class="cs-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Qté</th>
                        <th>Montant</th>
                        <th>Paiement</th>
                        <th>Réception</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($form->items as $line)
                        <tr>
                            <td><strong>{{ $line->product_name }}</strong></td>
                            <td>{{ $line->quantity }}</td>
                            <td>{{ number_format($line->amount, 0, ',', ' ') }} FCFA</td>
                            <td>
                                @if(($line->payment_status ?? 'pending') === 'paid')
                                    <span class="cs-badge success">Payé</span>
                                @else
                                    <span class="cs-badge warning">En attente</span>
                                @endif
                            </td>
                            <td>
                                <label class="cs-check">
                                    <input
                                        type="checkbox"
                                        name="items[{{ $line->id }}][received]"
                                        value="1"
                                        data-received-check
                                        @checked(old('items.'.$line->id.'.received', $line->received))
                                        @disabled($form->status === 'validated')
                                    >
                                    Article reçu
                                </label>
                            </td>
                            <td>
                                <input
                                    type="date"
                                    name="items[{{ $line->id }}][received_date]"
                                    value="{{ old('items.'.$line->id.'.received_date', $line->received_date?->format('Y-m-d')) }}"
                                    data-received-date
                                    @disabled($form->status === 'validated')
                                >
                            </td>
                            <td>
                                <input
                                    type="time"
                                    name="items[{{ $line->id }}][received_time]"
                                    value="{{ old('items.'.$line->id.'.received_time', $line->received_time) }}"
                                    data-received-time
                                    @disabled($form->status === 'validated')
                                >
                            </td>
                            <td>
                                @if(($line->payment_status ?? 'pending') !== 'paid')
                                    <button type="submit" form="pay-line-{{ $line->id }}" class="cs-btn small outline">Payer</button>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($form->status !== 'validated')
            <div class="cs-actions" style="margin-top:16px;">
                <button type="submit" name="action" value="save" class="cs-btn outline">Enregistrer le brouillon</button>
                <button type="submit" name="action" value="validate" class="cs-btn" onclick="return confirm('Confirmez-vous avoir reçu tous les articles cochés ? Cette validation est définitive.')">Confirmer définitivement la réception</button>
            </div>
        @endif
    </section>
</form>

@if(!$allPaid)
    <form id="pay-all-form" method="POST" action="{{ route('client.orders.reception.payOrder', $order) }}">
        @csrf
    </form>

    @foreach($form->items as $line)
        @if(($line->payment_status ?? 'pending') !== 'paid')
            <form id="pay-line-{{ $line->id }}" method="POST" action="{{ route('client.orders.reception.payLine', [$order, $line->id]) }}">
                @csrf
            </form>
        @endif
    @endforeach
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-received-check]').forEach(check => {
        const row = check.closest('tr');
        const dateInput = row?.querySelector('[data-received-date]');
        const timeInput = row?.querySelector('[data-received-time]');

        check.addEventListener('change', () => {
            if (!check.checked) {
                if (dateInput) dateInput.value = '';
                if (timeInput) timeInput.value = '';
                return;
            }

            const now = new Date();
            const pad = value => String(value).padStart(2, '0');
            if (dateInput && !dateInput.value) {
                dateInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            }
            if (timeInput && !timeInput.value) {
                timeInput.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            }
        });
    });
});
</script>
@endpush
