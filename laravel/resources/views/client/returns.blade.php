@extends('layouts.client')
@section('title', 'Retours et réclamations')
@section('content')
<section class="cs-page-head">
    <div>
        <h1>Retours & réclamations</h1>
        <p>Signalez un problème sur un article précis de l’une de vos commandes.</p>
    </div>
    <a class="cs-btn outline" href="{{ route('client.orders') }}">Voir mes commandes</a>
</section>

<div class="cs-two-col">
    <div>
        <section class="cs-card">
            <div class="cs-section-head">
                <h2>Nouvelle demande</h2>
                <span class="cs-badge info">{{ $orders->count() }} commande(s) disponible(s)</span>
            </div>

            @if($orders->isNotEmpty())
                <form method="POST" action="{{ route('client.returns.store') }}" enctype="multipart/form-data" class="cs-form-grid one" data-return-form>
                    @csrf

                    <label>Commande
                        <select name="order_id" required data-order-select>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>
                                    #{{ $order->order_number }} — {{ number_format($order->total_amount, 0, ',', ' ') }} FCFA
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>Article concerné
                        <select name="order_item_id" required data-item-select>
                            @foreach($orders as $order)
                                @foreach($order->items as $item)
                                    @php $option = $returnOptions[$item->id] ?? []; @endphp
                                    <option
                                        value="{{ $item->id }}"
                                        data-order-id="{{ $order->id }}"
                                        data-max-quantity="{{ $option['available_quantity'] ?? 0 }}"
                                        data-max-claim-quantity="{{ $option['available_claim_quantity'] ?? $option['available_quantity'] ?? 0 }}"
                                        data-can-return="{{ !empty($option['can_return']) ? '1' : '0' }}"
                                        data-can-refund="{{ !empty($option['can_refund']) ? '1' : '0' }}"
                                        data-can-claim="{{ !empty($option['can_claim']) ? '1' : '0' }}"
                                        data-return-deadline="{{ $option['return_deadline'] ?? '' }}"
                                        @selected(old('order_item_id') == $item->id)
                                    >
                                        {{ $item->product?->name ?: 'Produit' }} — Retour : {{ $option['available_quantity'] ?? 0 }} · Réclamation : {{ $option['available_claim_quantity'] ?? 0 }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </label>

                    <label>Type de demande
                        <select name="return_type" required data-return-type-select>
                            <option value="return" @selected(old('return_type') === 'return')>Retour produit</option>
                            <option value="refund" @selected(old('return_type') === 'refund')>Demande de remboursement</option>
                            <option value="claim" @selected(old('return_type') === 'claim')>Réclamation / problème de livraison</option>
                        </select>
                        <small data-eligibility-note></small>
                    </label>

                    <label>Quantité concernée
                        <input type="number" name="quantity" min="1" value="{{ old('quantity', 1) }}" required data-quantity-input>
                    </label>

                    <label>Motif détaillé
                        <textarea name="reason" required minlength="10" maxlength="1500" placeholder="Décrivez précisément le problème rencontré, l’état de l’article et la solution souhaitée.">{{ old('reason') }}</textarea>
                    </label>

                    <label>Photo justificative <small>(facultatif, JPG/PNG/WEBP, 4 Mo max.)</small>
                        <input type="file" name="photo_proof" accept="image/jpeg,image/png,image/webp">
                    </label>

                    <div class="cs-actions">
                        <button class="cs-btn" type="submit">Envoyer la demande</button>
                        <a class="cs-btn ghost" href="{{ route('contact.index') }}">Contacter le support</a>
                    </div>
                </form>
            @else
                <div class="cs-empty">
                    <h3>Aucune demande disponible</h3>
                    <p>Les articles apparaissent ici lorsqu’une réclamation ou un retour peut encore être ouvert.</p>
                    <a class="cs-btn" href="{{ route('catalog.index') }}">Continuer mes achats</a>
                </div>
            @endif
        </section>
    </div>

    <aside>
        <section class="cs-card">
            <h2>Suivi de mes demandes</h2>
            @forelse($returns as $return)
                @php
                    $decision = (array) data_get($return->meta, 'vendor_decision', []);
                    $published = !empty($decision['published_at']);
                    $decisionType = (string) ($decision['type'] ?? '');
                    $decisionProofs = (array) ($decision['proofs'] ?? []);
                    $deliveryDetails = (array) ($decision['delivery_details'] ?? []);
                    $refundDetails = (array) ($decision['refund_details'] ?? []);
                    $decisionLabel = $decision['client_label'] ?? match($decisionType) {
                        'reject' => 'Rejeté',
                        'refund' => 'Remboursé',
                        'accept' => 'Retour confirmé',
                        default => null,
                    };
                    $clientStatus = (!$published && $decisionType !== '') ? 'validation_logistique' : $return->status;
                    $clientStatusLabel = [
                        'validation_logistique' => 'En cours de validation',
                        'pending' => 'En attente',
                        'accepted' => 'Acceptée',
                        'rejected' => 'Refusée',
                        'refunded' => 'Remboursée',
                        'resolved' => 'Résolue',
                        'closed' => 'Clôturée',
                    ][$clientStatus] ?? 'En traitement';
                    $clientStatusTone = [
                        'validation_logistique' => 'warning',
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'refunded' => 'success',
                        'resolved' => 'success',
                        'closed' => 'success',
                    ][$clientStatus] ?? 'neutral';
                    $refundMethodLabel = match((string)($refundDetails['method'] ?? '')) {
                        'mobile_money' => 'Mobile Money',
                        'bank_transfer' => 'Virement bancaire',
                        'cash' => 'Espèces',
                        'paydunya_manual' => 'PayDunya',
                        default => (string)($refundDetails['method'] ?? ''),
                    };
                @endphp
                <article class="cs-support-row" style="align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <strong>#{{ $return->order?->order_number ?? $return->order_reference }}</strong>
                        <p>{{ $return->orderItem?->product?->name ?? $return->product_name }} · Qté {{ $return->quantity ?: 1 }}</p>
                        <small>{{ $return->request_date?->format('d/m/Y') ?? $return->created_at?->format('d/m/Y') }}</small>
                    </div>
                    <span class="cs-badge {{ $clientStatusTone }}">{{ $clientStatusLabel }}</span>

                    @if($published)
                        <div style="flex:0 0 100%;margin-top:14px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc">
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                                <strong>{{ $decisionLabel }}</strong>
                                <small>Confirmé par OVANIE Logistics le {{ \Carbon\Carbon::parse($decision['published_at'])->format('d/m/Y à H:i') }}</small>
                            </div>
                            <p style="margin:8px 0 0">{{ $decision['client_message'] ?? $decision['response'] ?? $return->vendor_response }}</p>

                            @if($decisionType === 'reject')
                                <div class="cs-note-list" style="margin-top:10px">
                                    <p><strong>Motif du rejet :</strong> {{ $decision['response'] ?? $return->vendor_response ?? 'Motif communiqué par le vendeur.' }}</p>
                                    @if(count($decisionProofs))
                                        <p><strong>Preuves du rejet :</strong></p>
                                        @foreach($decisionProofs as $proofIndex => $proof)
                                            @php($proofName=is_array($proof)?($proof['name']??'Preuve '.($proofIndex+1)):'Preuve '.($proofIndex+1))
                                            <p><a class="cs-btn ghost" href="{{ route('client.private-documents.return-decision', [$return, $proofIndex]) }}" target="_blank" rel="noopener">Voir {{ $proofName }}</a></p>
                                        @endforeach
                                    @endif
                                </div>
                            @elseif($decisionType === 'accept')
                                <div class="cs-note-list" style="margin-top:10px">
                                    <p><strong>Adresse de collecte :</strong> {{ $deliveryDetails['address'] ?? 'À confirmer' }}</p>
                                    <p><strong>Date :</strong> {{ !empty($deliveryDetails['date']) ? \Carbon\Carbon::parse($deliveryDetails['date'])->format('d/m/Y') : 'À planifier' }} · <strong>Créneau :</strong> {{ $deliveryDetails['slot'] ?? 'À planifier' }}</p>
                                    <p><strong>Livreur :</strong> {{ $deliveryDetails['driver_name'] ?? 'Non affecté' }} @if(!empty($deliveryDetails['driver_phone']))· {{ $deliveryDetails['driver_phone'] }}@endif</p>
                                    @if(!empty($deliveryDetails['vehicle']))<p><strong>Véhicule :</strong> {{ $deliveryDetails['vehicle'] }}</p>@endif
                                    @if(!empty($deliveryDetails['destination']))<p><strong>Destination retour :</strong> {{ $deliveryDetails['destination'] }}</p>@endif
                                </div>
                            @elseif($decisionType === 'refund')
                                <div class="cs-note-list" style="margin-top:10px">
                                    <p><strong>Montant :</strong> {{ number_format((float)($refundDetails['amount'] ?? $return->refund_amount ?? 0), 0, ',', ' ') }} FCFA</p>
                                    <p><strong>État :</strong> {{ $return->status === 'refunded' ? 'Remboursement effectué' : 'Remboursement en traitement' }}</p>
                                    @if($refundMethodLabel !== '')<p><strong>Mode :</strong> {{ $refundMethodLabel }}</p>@endif
                                    @if(!empty($refundDetails['reference']))<p><strong>Référence :</strong> {{ $refundDetails['reference'] }}</p>@endif
                                </div>
                            @endif
                        </div>
                    @elseif(!empty($decisionType))
                        <div style="flex:0 0 100%;margin-top:10px"><small>La décision du vendeur est en cours de validation par OVANIE Logistics.</small></div>
                    @endif
                </article>
            @empty
                <div class="cs-empty"><p>Aucune demande envoyée pour le moment.</p></div>
            @endforelse
        </section>

        <section class="cs-card">
            <h2>Bon à savoir</h2>
            <div class="cs-note-list">
                <p>Conservez l’article et son emballage jusqu’à la fin du traitement.</p>
                <p>Les retours et demandes de remboursement sont ouverts pendant {{ config('client_space.return_window_days', 7) }} jours après la livraison réelle de l’article.</p>
                <p>Une réclamation de livraison reste distincte du processus de retour.</p>
                <p>OVANIE peut demander des informations complémentaires avant validation.</p>
            </div>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-return-form]');
    if (!form) return;

    const orderSelect = form.querySelector('[data-order-select]');
    const itemSelect = form.querySelector('[data-item-select]');
    const typeSelect = form.querySelector('[data-return-type-select]');
    const quantityInput = form.querySelector('[data-quantity-input]');
    const eligibilityNote = form.querySelector('[data-eligibility-note]');
    const allItemOptions = Array.from(itemSelect.options);

    function selectedItemOption() {
        return itemSelect.selectedOptions[0] || null;
    }

    function syncItems() {
        const orderId = String(orderSelect.value);
        let firstVisible = null;

        allItemOptions.forEach(option => {
            const visible = option.dataset.orderId === orderId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && !firstVisible) firstVisible = option;
        });

        if (!selectedItemOption() || selectedItemOption().disabled) {
            itemSelect.value = firstVisible ? firstVisible.value : '';
        }

        syncEligibility();
    }

    function syncEligibility() {
        const option = selectedItemOption();
        if (!option) return;

        const allowed = {
            return: option.dataset.canReturn === '1',
            refund: option.dataset.canRefund === '1',
            claim: option.dataset.canClaim === '1',
        };

        Array.from(typeSelect.options).forEach(typeOption => {
            typeOption.disabled = !allowed[typeOption.value];
        });

        const selectedType = typeSelect.selectedOptions[0];
        if (!selectedType || selectedType.disabled) {
            const firstAllowed = Array.from(typeSelect.options).find(typeOption => !typeOption.disabled);
            typeSelect.value = firstAllowed ? firstAllowed.value : '';
        }

        const selectedRequestType = typeSelect.value || 'return';
        const rawMax = selectedRequestType === 'claim'
            ? option.dataset.maxClaimQuantity
            : option.dataset.maxQuantity;
        const max = Math.max(1, Number(rawMax || 1));
        quantityInput.max = String(max);
        if (Number(quantityInput.value || 1) > max) quantityInput.value = String(max);

        if (allowed.return || allowed.refund) {
            eligibilityNote.textContent = option.dataset.returnDeadline
                ? `Retour ou remboursement possible jusqu’au ${option.dataset.returnDeadline}.`
                : 'Cet article est éligible au retour.';
        } else if (allowed.claim) {
            eligibilityNote.textContent = 'Le délai de retour est terminé ou la livraison n’est pas encore finalisée. Une réclamation reste disponible.';
        } else {
            eligibilityNote.textContent = 'Aucune nouvelle demande n’est disponible pour cet article.';
        }
    }

    orderSelect.addEventListener('change', syncItems);
    itemSelect.addEventListener('change', syncEligibility);
    typeSelect.addEventListener('change', syncEligibility);
    syncItems();
});
</script>
@endpush
