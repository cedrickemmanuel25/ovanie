@php
    /*
     * Synchronisation web ↔ mobile : déclare les sujets affichés par la page
     * courante. public/js/live-sync.js recharge la page quand l'un d'eux change
     * (voir App\Support\LiveSync). Les pages absentes de cette table gardent
     * seulement la mise à jour du compteur panier.
     */
    $_liveSyncPages = [
        'cart.index' => ['cart'],
        'checkout.*' => ['cart', 'orders'],
        'client.dashboard' => ['orders', 'notifications', 'favorites'],
        'client.orders' => ['orders'],
        'client.orders.show' => ['orders'],
        'client.orders.tracking' => ['orders'],
        'client.payments' => ['orders'],
        'client.returns' => ['orders'],
        'client.favorites' => ['favorites'],
        'client.notifications' => ['notifications'],
        'vendor.dashboard' => ['shop', 'notifications'],
        'vendor.orders' => ['shop'],
        'vendor.orders.show' => ['shop'],
        'vendor.products' => ['shop'],
        'vendor.returns.index' => ['shop'],
        'vendor.reviews.index' => ['shop'],
        'vendor.disputes.index' => ['shop'],
        'vendor.payments' => ['shop'],
        'driver.dashboard' => ['missions', 'notifications'],
        'driver.missions.index' => ['missions'],
        'driver.missions.show' => ['missions'],
        'admin.dashboard' => ['ops'],
        'admin.orders.index' => ['ops'],
        'admin.orders.show' => ['ops'],
        'logistics.dashboard' => ['ops'],
        'logistics.shipments' => ['ops'],
        'logistics.shipments.details' => ['ops'],
        'logistics.assignments.index' => ['ops'],
        'logistics.active-deliveries' => ['ops'],
        'logistics.assignments.*' => ['ops'],
        'logistics.drivers' => ['ops'],
        'logistics.drivers.show' => ['ops'],
        'logistics.drivers.review' => ['ops'],
        'logistics.tours.index' => ['ops'],
        'logistics.tours.show' => ['ops'],
        'logistics.fleet' => ['ops'],
        'logistics.fleet.show' => ['ops'],
        'logistics.incidents.index' => ['ops'],
        'logistics.incidents.show' => ['ops'],
        'logistics.returns' => ['ops'],
        'logistics.returns.details' => ['ops'],
        'support.orders.index' => ['ops'],
        'support.deliveries.index' => ['ops'],
        'support.returns.index' => ['ops'],
        'support.disputes.index' => ['ops'],
    ];
    // Pages de saisie sensibles : jamais de rechargement automatique, seulement le bandeau.
    $_liveSyncNotifyOnly = ['checkout.*'];

    $_liveSyncRoute = request()->route();
    $_liveSyncName = (string) optional($_liveSyncRoute)->getName();
    $_liveSyncTopics = [];
    $_liveSyncMode = 'reload';
    foreach ($_liveSyncPages as $_pattern => $_topics) {
        if ($_liveSyncName !== '' && \Illuminate\Support\Str::is($_pattern, $_liveSyncName)) {
            $_liveSyncTopics = array_merge($_liveSyncTopics, $_topics);
        }
    }
    foreach ($_liveSyncNotifyOnly as $_pattern) {
        if ($_liveSyncName !== '' && \Illuminate\Support\Str::is($_pattern, $_liveSyncName)) {
            $_liveSyncMode = 'notify';
        }
    }

    $_liveSyncWatch = [];
    if ($_liveSyncName === 'product.show') {
        $_liveSyncProduct = optional($_liveSyncRoute)->parameter('product');
        $_liveSyncProductId = $_liveSyncProduct instanceof \Illuminate\Database\Eloquent\Model
            ? (int) $_liveSyncProduct->getKey()
            : 0;
        if ($_liveSyncProductId > 0) {
            $_liveSyncWatch[] = 'product:'.$_liveSyncProductId;
        }
    }
@endphp
<script type="application/json" id="ovanie-live-sync-config">{!! json_encode([
    'endpoint' => route('live-sync', [], false),
    'cartCountEndpoint' => route('cart.count', [], false),
    'topics' => array_values(array_unique($_liveSyncTopics)),
    'watch' => $_liveSyncWatch,
    'mode' => $_liveSyncMode,
    'pollMs' => (int) config('ovanie.live_sync_poll_ms', 8000),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/live-sync.js') }}?v={{ @filemtime(public_path('js/live-sync.js')) ?: '1' }}" defer></script>
<meta name="ovanie-csrf-endpoint" content="{{ route('csrf-token', [], false) }}">
<script src="{{ asset('js/session-keepalive.js') }}?v={{ @filemtime(public_path('js/session-keepalive.js')) ?: '1' }}" defer></script>
