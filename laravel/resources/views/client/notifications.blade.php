@extends('layouts.client')
@section('title', 'Notifications')
@section('content')
<section class="cs-page-head">
    <div>
        <h1>Notifications</h1>
        <p>{{ $unreadCount }} notification(s) non lue(s)</p>
    </div>
    <form method="POST" action="{{ route('client.notifications.readAll') }}">
        @csrf
        <button class="cs-btn outline">Tout marquer comme lu</button>
    </form>
</section>

<nav class="cs-tabs">
    @foreach([
        '' => 'Toutes',
        'orders' => 'Commandes',
        'payments' => 'Paiements',
        'delivery' => 'Livraisons',
        'returns' => 'Retours',
        'promotions' => 'Promotions',
        'system' => 'Compte & sécurité',
        'newsletter' => 'Newsletter',
        'cart' => 'Panier',
    ] as $key => $label)
        <a class="{{ request('type', '') === $key ? 'active' : '' }}" href="{{ route('client.notifications', array_filter(['type' => $key])) }}">{{ $label }}</a>
    @endforeach
</nav>

<section class="cs-card">
    @forelse($notifications as $notification)
        @php $data = $notification->data; @endphp
        <form method="POST" action="{{ route('client.notifications.read', $notification->id) }}" class="cs-notification {{ $notification->read_at ? '' : 'unread' }}">
            @csrf
            <button>
                <i>{{ $notification->read_at ? '✓' : '●' }}</i>
                <span>
                    <strong>{{ $data['title'] ?? 'Notification OVANIE' }}</strong>
                    <small>{{ $data['message'] ?? 'Nouvelle information disponible sur votre compte.' }}</small>
                </span>
                <time>{{ $notification->created_at?->diffForHumans() }}</time>
            </button>
        </form>
    @empty
        <div class="cs-empty">
            <h3>Aucune notification</h3>
            <p>Les alertes importantes apparaîtront ici.</p>
        </div>
    @endforelse

    {{ $notifications->links() }}
</section>

<section class="cs-card">
    <h2>Préférences de notification</h2>
    <form method="POST" action="{{ route('client.notifications.preferences') }}">
        @csrf
        @include('client.partials.notification-preferences')
        <button class="cs-btn">Enregistrer</button>
    </form>
</section>
@endsection
