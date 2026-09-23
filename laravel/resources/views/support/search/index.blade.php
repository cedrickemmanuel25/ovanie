@extends('layouts.staff')
@section('title', 'Recherche OVANIE | Support')
@section('content')
<section class="workspace-banner">
    <div>
        <span class="eyebrow"><i data-lucide="search"></i> Recherche transversale</span>
        <h1>Recherche OVANIE</h1>
        <p>Retrouvez depuis un seul écran un client, vendeur, commande, paiement, livraison, livreur partenaire, mission ou dossier Support. Les données restent dans leurs modules d’origine.</p>
    </div>
</section>

<form class="quick-search" method="GET" action="{{ route('support.search') }}">
    <i data-lucide="search"></i>
    <input autofocus type="search" name="q" value="{{ $q }}" placeholder="Nom, téléphone, e-mail, commande, paiement, suivi, mission, boutique…">
    <button class="btn btn-primary" type="submit">Rechercher</button>
</form>

@if($q !== '' && mb_strlen($q) < 2)<div class="notice warning"><i data-lucide="info"></i><div>Saisissez au moins 2 caractères.</div></div>@endif

@if(mb_strlen($q) >= 2)
<section class="card">
    <div class="card-head"><div><h2>Résultats pour « {{ $q }} »</h2><p>{{ $results->count() }} élément(s) trouvé(s) dans les données OVANIE.</p></div></div>
    <div class="support-result-list">
        @forelse($results as $result)
            <article class="support-result-item">
                <div class="support-result-icon"><i data-lucide="{{ $result['icon'] }}"></i></div>
                <div class="support-result-main">
                    <div class="support-result-meta"><span>{{ $result['type'] }}</span><span class="status {{ $result['status'] }}">{{ str_replace('_',' ', $result['status']) }}</span></div>
                    <strong>{{ $result['title'] }}</strong>
                    <small>{{ $result['subtitle'] ?: 'Aucun détail supplémentaire' }}</small>
                </div>
                <div class="support-result-actions">
                    @if(!empty($result['url']))<a class="btn" href="{{ $result['url'] }}">Consulter</a>@endif
                    @if(!empty($result['ticket_query']))<a class="btn btn-primary" href="{{ route('support.tickets.create', $result['ticket_query']) }}">Créer un dossier</a>@endif
                </div>
            </article>
        @empty
            <div class="empty"><i data-lucide="search-x"></i><div>Aucun résultat.</div><span class="record-sub">Vérifiez la référence, le numéro de téléphone ou le nom saisi.</span></div>
        @endforelse
    </div>
</section>
@endif
@endsection
