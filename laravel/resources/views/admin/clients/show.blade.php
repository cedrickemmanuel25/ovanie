@extends('admin.layouts.app')

@section('title', 'Fiche client | Administration OVANIE')
@section('page-title', 'Fiche client')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_clients.css') }}">
@endpush

@section('content')
@php
    $displayName = trim($client->full_name) ?: ($client->name ?: 'Client sans nom');
    $initials = collect(preg_split('/\s+/', $displayName))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $statusLabels = ['active' => 'Actif', 'inactive' => 'Inactif', 'blocked' => 'Bloqué', 'suspended' => 'Suspendu'];
    $status = $client->status ?: 'inactive';
    $orderStatusLabels = [
        'pending' => 'En attente', 'confirmed' => 'Confirmée', 'paid' => 'Payée',
        'shipped' => 'Expédiée', 'completed' => 'Terminée', 'cancelled' => 'Annulée',
    ];
    $paymentLabels = [
        'cash_on_delivery' => 'Paiement à la livraison',
        'paydunya' => 'Paiement en ligne',
        'bank_transfer' => 'Virement bancaire',
    ];
@endphp

<div class="ac-page">
    <section class="ac-page-head ac-page-head-actions">
        <div>
            <span class="ac-eyebrow">Compte acheteur #{{ $client->id }}</span>
            <h2>{{ $displayName }}</h2>
            <p>Consultez l’identité, l’activité, les adresses et les commandes du client.</p>
        </div>
        <div class="ac-head-actions">
            <a class="ac-btn ac-btn-secondary" href="{{ route('admin.clients.index') }}">Retour aux clients</a>
            <a class="ac-btn ac-btn-primary" href="{{ route('admin.clients.edit', $client->id) }}">Modifier la fiche</a>
        </div>
    </section>

    @if (session('success'))
        <div class="ac-alert ac-alert-success">{{ session('success') }}</div>
    @endif

    <section class="ac-profile-hero">
        <div class="ac-profile-main">
            <span class="ac-profile-avatar">{{ $initials ?: 'C' }}</span>
            <div>
                <div class="ac-profile-name-line">
                    <h3>{{ $displayName }}</h3>
                    <span class="ac-status ac-status-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
                </div>
                <p>{{ $client->email }}</p>
                <div class="ac-profile-tags">
                    <span>{{ $client->phone ?: 'Téléphone non renseigné' }}</span>
                    <span>{{ $client->city ?: 'Ville non renseignée' }}</span>
                    <span>{{ $client->commercialCreator ? 'Créé par '.$client->commercialCreator->name : 'Création directe' }}</span>
                </div>
            </div>
        </div>
        <div class="ac-profile-date">
            <span>Inscription</span>
            <strong>{{ $client->created_at?->format('d/m/Y') }}</strong>
            <small>{{ $client->created_at?->format('H:i') }}</small>
        </div>
    </section>

    <section class="ac-stats ac-stats-detail">
        <article class="ac-stat-card"><span class="ac-stat-icon ac-stat-blue">C</span><div><span>Commandes</span><strong>{{ number_format($stats['orders'], 0, ',', ' ') }}</strong><small>Commandes réellement validées</small></div></article>
        <article class="ac-stat-card"><span class="ac-stat-icon ac-stat-green">F</span><div><span>Total dépensé</span><strong>{{ number_format($stats['spent'], 0, ',', ' ') }} FCFA</strong><small>Hors commandes annulées</small></div></article>
        <article class="ac-stat-card"><span class="ac-stat-icon ac-stat-orange">A</span><div><span>Adresses</span><strong>{{ number_format($stats['addresses'], 0, ',', ' ') }}</strong><small>Adresses de livraison enregistrées</small></div></article>
        <article class="ac-stat-card"><span class="ac-stat-icon ac-stat-purple">♥</span><div><span>Favoris</span><strong>{{ number_format($stats['favorites'], 0, ',', ' ') }}</strong><small>Produits conservés par le client</small></div></article>
    </section>

    <div class="ac-detail-grid">
        <div class="ac-detail-main">
            <section class="ac-panel">
                <div class="ac-section-head">
                    <div><span class="ac-section-icon">I</span><div><h3>Informations du compte</h3><p>Coordonnées et préférences principales.</p></div></div>
                </div>
                <div class="ac-info-grid">
                    <div><span>Nom complet</span><strong>{{ $displayName }}</strong></div>
                    <div><span>Adresse e-mail</span><strong>{{ $client->email }}</strong></div>
                    <div><span>Téléphone principal</span><strong>{{ $client->phone ?: 'Non renseigné' }}</strong></div>
                    <div><span>Téléphone secondaire</span><strong>{{ $client->secondary_phone ?: 'Non renseigné' }}</strong></div>
                    <div><span>Ville</span><strong>{{ $client->city ?: 'Non renseignée' }}</strong></div>
                    <div><span>Langue</span><strong>{{ $client->locale === 'fr' || ! $client->locale ? 'Français' : strtoupper($client->locale) }}</strong></div>
                </div>
            </section>

            <section class="ac-panel">
                <div class="ac-section-head ac-section-head-between">
                    <div><span class="ac-section-icon">C</span><div><h3>Commandes récentes</h3><p>Les huit dernières commandes opérationnelles.</p></div></div>
                    <a href="{{ route('admin.orders.index', ['search' => $client->email]) }}">Voir toutes les commandes</a>
                </div>

                <div class="ac-order-list">
                    @forelse ($recentOrders as $order)
                        @php $orderStatus = $order->status ?: 'pending'; @endphp
                        <a class="ac-order-row" href="{{ route('admin.orders.show', $order) }}">
                            <div><span class="ac-order-reference">{{ $order->order_number ?: '#'.$order->id }}</span><small>{{ $order->created_at?->format('d/m/Y à H:i') }}</small></div>
                            <div><span>{{ $paymentLabels[$order->payment_method] ?? 'Mode de paiement non renseigné' }}</span><small>{{ (int) ($order->items_count ?? 0) }} article(s)</small></div>
                            <div class="ac-order-amount"><strong>{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</strong><span class="ac-order-status ac-order-status-{{ $orderStatus }}">{{ $orderStatusLabels[$orderStatus] ?? ucfirst($orderStatus) }}</span></div>
                        </a>
                    @empty
                        <div class="ac-empty-inline"><strong>Aucune commande validée</strong><span>Le client n’a pas encore finalisé de commande.</span></div>
                    @endforelse
                </div>
            </section>

            <section class="ac-panel">
                <div class="ac-section-head"><div><span class="ac-section-icon">A</span><div><h3>Adresses de livraison</h3><p>Points de livraison enregistrés dans l’espace client.</p></div></div></div>
                <div class="ac-address-grid">
                    @forelse ($client->addresses as $address)
                        <article class="ac-address-card">
                            <div><strong>{{ $address->label ?: ($address->type === 'work' ? 'Adresse professionnelle' : 'Adresse de livraison') }}</strong>@if($address->is_default)<span>Par défaut</span>@endif</div>
                            <p>{{ collect([$address->address, $address->quartier, $address->commune, $address->city])->filter()->implode(', ') ?: 'Adresse non détaillée' }}</p>
                            <small>{{ $address->recipient_name ?: $displayName }} · {{ $address->phone ?: $client->phone ?: 'Téléphone non renseigné' }}</small>
                        </article>
                    @empty
                        <div class="ac-empty-inline"><strong>Aucune adresse enregistrée</strong><span>Le client renseignera une adresse lors du passage de sa commande.</span></div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="ac-detail-side">
            <section class="ac-panel ac-compact-panel">
                <div class="ac-section-head"><div><span class="ac-section-icon">T</span><div><h3>Traçabilité</h3><p>Origine et suivi du compte.</p></div></div></div>
                <dl class="ac-data-list">
                    <div><dt>Créé par</dt><dd>{{ $client->commercialCreator?->name ?: 'Création directe' }}</dd></div>
                    <div><dt>Contact du créateur</dt><dd>{{ $client->commercialCreator?->email ?: 'Non applicable' }}</dd></div>
                    <div><dt>Dernière commande</dt><dd>{{ $lastOrderAt ? \Carbon\Carbon::parse($lastOrderAt)->format('d/m/Y à H:i') : 'Aucune commande' }}</dd></div>
                    <div><dt>Dernière modification</dt><dd>{{ $client->updated_at?->format('d/m/Y à H:i') }}</dd></div>
                </dl>
            </section>

            <section class="ac-panel ac-compact-panel">
                <div class="ac-section-head"><div><span class="ac-section-icon">P</span><div><h3>Moyens de paiement</h3><p>Données masquées pour la sécurité.</p></div></div></div>
                <div class="ac-payment-list">
                    @forelse ($client->paymentMethods as $method)
                        <div><span>{{ $method->operator_label }}</span><strong>{{ $method->masked_phone ?: 'Numéro masqué' }}</strong>@if($method->is_default)<small>Par défaut</small>@endif</div>
                    @empty
                        <p class="ac-muted">Aucun moyen de paiement enregistré.</p>
                    @endforelse
                </div>
            </section>

            <section class="ac-danger-zone">
                <h3>Gestion du compte</h3>
                <p>L’anonymisation retire les données personnelles et suspend définitivement l’accès au compte.</p>
                <form action="{{ route('admin.clients.destroy', $client->id) }}" method="POST" onsubmit="return confirm('Confirmer l’anonymisation et la suspension de ce compte client ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Anonymiser et suspendre</button>
                </form>
            </section>
        </aside>
    </div>
</div>
@endsection
