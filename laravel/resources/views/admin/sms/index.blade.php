@extends('admin.layouts.app')

@section('title', 'Historique des SMS | Administration OVANIE')
@section('page-title', 'Historique des SMS')

@section('content')
<main class="sms-admin-page">
    <section class="sms-hero">
        <div>
            <p class="sms-eyebrow">COMMUNICATIONS SORTANTES</p>
            <h1>Historique des SMS</h1>
            <p>Consultez uniquement les messages réellement enregistrés par le fournisseur SMS configuré dans OVANIE.</p>
        </div>

        @if(request()->hasAny(['q', 'status', 'date_from', 'date_to', 'sort']))
            <a href="{{ route('admin.sms.index') }}" class="sms-button sms-button--secondary">Réinitialiser</a>
        @endif
    </section>

    <section class="sms-stats-grid">
        <article class="sms-stat"><span class="sms-stat__icon sms-stat__icon--blue">S</span><div><small>Total enregistré</small><strong>{{ number_format($stats['total'], 0, ',', ' ') }}</strong><span>Tous les journaux SMS</span></div></article>
        <article class="sms-stat"><span class="sms-stat__icon sms-stat__icon--green">✓</span><div><small>Envoyés</small><strong>{{ number_format($stats['sent'], 0, ',', ' ') }}</strong><span>Envoi confirmé</span></div></article>
        <article class="sms-stat"><span class="sms-stat__icon sms-stat__icon--orange">…</span><div><small>En attente</small><strong>{{ number_format($stats['pending'], 0, ',', ' ') }}</strong><span>Traitement non terminé</span></div></article>
        <article class="sms-stat"><span class="sms-stat__icon sms-stat__icon--red">!</span><div><small>Échecs</small><strong>{{ number_format($stats['failed'], 0, ',', ' ') }}</strong><span>Envois non confirmés</span></div></article>
    </section>

    <section class="sms-filter-card">
        <div class="sms-section-heading">
            <div><p class="sms-eyebrow">RECHERCHE ET FILTRES</p><h2>Retrouver un message</h2></div>
            <span>{{ $smsLogs->total() }} résultat(s)</span>
        </div>

        <form method="GET" action="{{ route('admin.sms.index') }}" class="sms-filter-form">
            <div class="sms-field sms-field--wide">
                <label for="sms_q">Numéro, message ou réponse technique</label>
                <input id="sms_q" type="search" name="q" value="{{ request('q') }}" placeholder="Ex. 0700000000, commande confirmée...">
            </div>
            <div class="sms-field">
                <label for="sms_status">Statut</label>
                <select id="sms_status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="sent" @selected(request('status') === 'sent')>Envoyé</option>
                    <option value="pending" @selected(request('status') === 'pending')>En attente</option>
                    <option value="failed" @selected(request('status') === 'failed')>Échec</option>
                </select>
            </div>
            <div class="sms-field"><label for="sms_from">Du</label><input id="sms_from" type="date" name="date_from" value="{{ request('date_from') }}"></div>
            <div class="sms-field"><label for="sms_to">Au</label><input id="sms_to" type="date" name="date_to" value="{{ request('date_to') }}"></div>
            <div class="sms-field">
                <label for="sms_sort">Classement</label>
                <select id="sms_sort" name="sort">
                    <option value="recent" @selected(request('sort', 'recent') === 'recent')>Plus récents</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                </select>
            </div>
            <button type="submit" class="sms-button sms-button--primary">Rechercher</button>
        </form>
    </section>

    <section class="sms-list-card">
        <div class="sms-list-heading">
            <div><p class="sms-eyebrow">JOURNAL D’ENVOI</p><h2>Messages enregistrés</h2></div>
            <span>{{ $smsLogs->firstItem() ?? 0 }}–{{ $smsLogs->lastItem() ?? 0 }} sur {{ $smsLogs->total() }}</span>
        </div>

        <div class="sms-list">
            @forelse($smsLogs as $sms)
                @php
                    $status = $sms->status ?: 'pending';
                    $label = match($status) { 'sent' => 'Envoyé', 'failed' => 'Échec', default => 'En attente' };
                    $class = match($status) { 'sent' => 'sms-status--success', 'failed' => 'sms-status--danger', default => 'sms-status--warning' };
                @endphp
                <article class="sms-item">
                    <div class="sms-item__top">
                        <div class="sms-phone">
                            <span class="sms-phone__icon">✉</span>
                            <div><small>DESTINATAIRE</small><strong>{{ $sms->phone ?: 'Numéro non renseigné' }}</strong></div>
                        </div>
                        <span class="sms-status {{ $class }}">{{ $label }}</span>
                    </div>
                    <div class="sms-message">{{ $sms->message ?: 'Message vide' }}</div>
                    <div class="sms-item__bottom">
                        <span>Journal #{{ $sms->id }}</span>
                        <time datetime="{{ optional($sms->created_at)->toIso8601String() }}">{{ optional($sms->created_at)->format('d/m/Y à H:i') ?? 'Date indisponible' }}</time>
                    </div>
                    @if($status === 'failed' && !empty($sms->response))
                        <details class="sms-error-detail"><summary>Voir le motif technique</summary><p>{{ $sms->response }}</p></details>
                    @endif
                </article>
            @empty
                <div class="sms-empty"><span>✉</span><h3>Aucun SMS enregistré</h3><p>La page reste vide tant qu’aucun envoi réel n’a été journalisé.</p></div>
            @endforelse
        </div>

        @if($smsLogs->hasPages())
            <div class="sms-pagination">{{ $smsLogs->links() }}</div>
        @endif
    </section>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/admin_sms.css') }}">
@endpush
