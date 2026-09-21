@extends('layouts.client')
@section('title', 'Mes moyens de paiement')
@section('content')
<section class="cs-page-head"><div><h1>Mes moyens de paiement</h1><p>{{ $methods->count() }} méthodes de paiement préférées</p></div><button class="cs-btn" data-modal-open="paymentModal">Ajouter</button></section>
<div class="cs-two-col">
    <div>
        <section class="cs-card"><div class="cs-section-head"><h2>Mobile Money</h2><button class="cs-link-button" data-modal-open="paymentModal">+ Ajouter</button></div>
            @forelse($methods as $method)
            <article class="cs-payment-method"><span class="operator {{ $method->operator }}">{{ strtoupper($method->operator) }}</span><div><strong>{{ ucfirst($method->operator) }}</strong><p>{{ preg_replace('/(\\d{2})\\d+(\\d{2})/', '$1 XX XX $2', $method->phone) }}</p><small>Dernière utilisation : {{ $method->last_used_at?->format('d/m/Y') ?: 'Jamais utilisée' }}</small></div>@if($method->is_default)<span class="cs-badge success">Par défaut</span>@endif<form method="POST" action="{{ route('client.payments.default',$method) }}">@csrf<button class="cs-btn small ghost" @disabled($method->is_default)>Par défaut</button></form><form method="POST" action="{{ route('client.payments.destroy',$method) }}" onsubmit="return confirm('Supprimer ce moyen de paiement ?')">@csrf @method('DELETE')<button class="cs-btn small danger">Supprimer</button></form></article>
            @empty<div class="cs-empty"><h3>Aucun compte Mobile Money</h3><p>Ajoutez Orange Money, MTN, Wave ou Moov pour accélérer vos prochains achats.</p></div>@endforelse
        </section>
        <section class="cs-card"><h2>Cartes bancaires</h2><div class="cs-empty"><h3>Aucune carte enregistrée</h3><p>Vos données de carte sont chiffrées et sécurisées — conformité PCI-DSS.</p><button class="cs-btn outline">Ajouter une carte</button></div></section>
    </div>
    <aside>
        <section class="cs-card"><div class="cs-section-head"><h2>Historique des paiements</h2><a href="#">Voir tout →</a></div>@forelse($payments as $payment)<div class="cs-payment-row"><span class="operator {{ $payment->operator ?: $payment->method }}">{{ strtoupper(substr($payment->operator ?: $payment->method,0,3)) }}</span><div><strong>{{ $payment->status === 'success' || $payment->status === 'paid' ? 'Paiement réussi' : ucfirst($payment->status) }}</strong><p>#{{ $payment->order?->order_number }}</p></div><b>{{ number_format($payment->amount,0,',',' ') }} FCFA</b></div>@empty<div class="cs-empty"><p>Aucun paiement enregistré.</p></div>@endforelse</section>
        <section class="cs-card cs-points"><h2>Solde & Points</h2><strong>{{ number_format($points,0,',',' ') }} points fidélité</strong><p>{{ number_format($points*10,0,',',' ') }} FCFA de réduction disponible.</p><div class="cs-progress"><span style="width:{{ min(100,$points % 1000 / 10) }}%"></span></div><button class="cs-btn full">Utiliser mes points au prochain achat</button></section>
    </aside>
</div>
<div class="cs-modal" id="paymentModal" aria-hidden="true"><div class="cs-modal-panel small"><button class="cs-modal-close" data-modal-close>×</button><h2>Ajouter un compte Mobile Money</h2><form method="POST" action="{{ route('client.payments.store') }}">@csrf<div class="cs-form-grid one"><label>Opérateur<select name="operator" required><option value="orange">Orange Money</option><option value="mtn">MTN MoMo</option><option value="wave">Wave</option><option value="moov">Moov Money</option></select></label><label>Nom du titulaire<input name="account_name" required></label><label>Numéro<input name="phone" required placeholder="+225 07 XX XX XX XX"></label><label class="cs-check"><input type="checkbox" name="is_default" value="1"> Définir par défaut</label></div><div class="cs-modal-actions"><button class="cs-btn">Enregistrer</button></div></form></div></div>
@endsection
