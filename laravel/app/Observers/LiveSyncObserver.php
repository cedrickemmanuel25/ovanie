<?php

namespace App\Observers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryIncident;
use App\Models\DeliveryTour;
use App\Models\Dispute;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ReturnModel;
use App\Models\Review;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\Wishlist;
use App\Support\LiveSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Throwable;

/**
 * Traduit les écritures Eloquent en sujets LiveSync (voir App\Support\LiveSync).
 */
class LiveSyncObserver
{
    /**
     * Colonnes dont la seule modification ne doit pas rafraîchir les écrans
     * (compteurs de vues, battements GPS…), sinon une simple consultation
     * déclencherait des rechargements en boucle.
     */
    private const NOISE_COLUMNS = [
        'updated_at', 'views', 'views_count', 'view_count', 'clicks', 'clicks_count',
        'impressions', 'impressions_count', 'last_viewed_at', 'last_seen_at',
        'gps_last_seen_at', 'last_activity_at', 'popularity_score', 'search_score',
    ];

    /**
     * Colonnes propres au livreur mises à jour à chaque signal GPS ou
     * connexion : seul un vrai changement (statut, en ligne, dossier) doit
     * rafraîchir la logistique.
     */
    private const DRIVER_NOISE_COLUMNS = [
        'latitude', 'longitude', 'last_seen_at', 'last_login_at',
        'otp_code', 'otp_expires_at', 'otp_used_at', 'otp_last_sent_at',
    ];

    /** @var array<class-string, true> */
    public const MODELS = [
        Cart::class => true,
        CartItem::class => true,
        Favorite::class => true,
        Wishlist::class => true,
        Order::class => true,
        OrderItem::class => true,
        Payment::class => true,
        Shipment::class => true,
        DeliveryAssignment::class => true,
        DeliveryDriver::class => true,
        DeliveryTour::class => true,
        DeliveryIncident::class => true,
        ReturnModel::class => true,
        Dispute::class => true,
        Product::class => true,
        ProductVariant::class => true,
        ProductImage::class => true,
        Review::class => true,
        Shop::class => true,
        DatabaseNotification::class => true,
    ];

    public function created(Model $model): void
    {
        $this->sync($model);
    }

    public function updated(Model $model): void
    {
        $changes = array_diff(array_keys($model->getChanges()), self::NOISE_COLUMNS);
        if ($model instanceof DeliveryDriver) {
            $changes = array_diff($changes, self::DRIVER_NOISE_COLUMNS);
        }
        if ($changes !== []) {
            $this->sync($model);
        }
    }

    public function deleted(Model $model): void
    {
        $this->sync($model);
    }

    public function restored(Model $model): void
    {
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        try {
            $this->dispatch($model);
        } catch (Throwable) {
            // Jamais bloquant pour l'écriture métier.
        }
    }

    private function dispatch(Model $model): void
    {
        match (true) {
            $model instanceof Cart => LiveSync::touchUser((int) $model->user_id, 'cart'),
            $model instanceof CartItem => LiveSync::touchUser(
                (int) Cart::query()->whereKey($model->cart_id)->value('user_id'),
                'cart'
            ),
            $model instanceof Favorite, $model instanceof Wishlist => LiveSync::touchUser((int) $model->user_id, 'favorites'),
            $model instanceof Order => $this->order($model),
            $model instanceof OrderItem,
            $model instanceof Payment,
            $model instanceof Shipment,
            $model instanceof ReturnModel,
            $model instanceof Dispute => $this->orderChild($model),
            $model instanceof DeliveryAssignment => $this->assignment($model),
            // Statut du livreur (en ligne, en livraison, dossier), tournées et
            // incidents : visibles en direct dans l'espace logistique.
            $model instanceof DeliveryDriver => LiveSync::touch('ops', "driver.{$model->getKey()}.missions"),
            $model instanceof DeliveryTour, $model instanceof DeliveryIncident => LiveSync::touch('ops'),
            $model instanceof Product => LiveSync::touchProduct((int) $model->getKey(), (int) $model->shop_id),
            $model instanceof ProductVariant, $model instanceof ProductImage, $model instanceof Review => $this->productChild($model),
            $model instanceof Shop => LiveSync::touch("shop.{$model->getKey()}", 'catalog'),
            $model instanceof DatabaseNotification => $this->notification($model),
            default => null,
        };
    }

    private function order(Order $order): void
    {
        LiveSync::touchUser((int) $order->client_id, 'orders');
        if ($order->wasChanged('client_id') && $order->getOriginal('client_id')) {
            LiveSync::touchUser((int) $order->getOriginal('client_id'), 'orders');
        }
        foreach ($this->orderShopIds((int) $order->getKey(), (int) $order->shop_id) as $shopId) {
            LiveSync::touchShop($shopId);
        }
        LiveSync::touch('ops');
    }

    /**
     * Mission, expédition, paiement, retour… d'une commande : prévient le
     * client, la ou les boutiques concernées et la logistique.
     *
     * La boutique vient de la ligne de commande (order_item) quand elle est
     * connue : une commande peut contenir plusieurs boutiques et
     * orders.shop_id est souvent vide.
     */
    private function orderChild(Model $model): void
    {
        $orderId = (int) ($model->getAttribute('order_id') ?? 0);
        $orderItemId = (int) ($model->getAttribute('order_item_id') ?? 0);
        $clientId = (int) ($model->getAttribute('client_id') ?? 0);
        $shopId = (int) ($model->getAttribute('shop_id') ?? 0);

        if (! $shopId && $orderItemId > 0) {
            $item = OrderItem::query()->whereKey($orderItemId)->first(['id', 'order_id', 'shop_id']);
            $shopId = (int) $item?->shop_id;
            $orderId = $orderId ?: (int) $item?->order_id;
        }

        $order = $orderId > 0 ? Order::query()->whereKey($orderId)->first(['id', 'client_id', 'shop_id']) : null;
        $clientId = $clientId ?: (int) $order?->client_id;

        LiveSync::touchUser($clientId, 'orders');
        $shopIds = $shopId > 0 ? [$shopId] : $this->orderShopIds($orderId, (int) $order?->shop_id);
        foreach ($shopIds as $id) {
            LiveSync::touchShop($id);
        }
        LiveSync::touch('ops');
    }

    /**
     * @return array<int, int>
     */
    private function orderShopIds(int $orderId, int $fallbackShopId = 0): array
    {
        $ids = $orderId > 0
            ? OrderItem::query()->where('order_id', $orderId)->distinct()->pluck('shop_id')->map(fn ($id) => (int) $id)->filter()->all()
            : [];

        if ($fallbackShopId > 0) {
            $ids[] = $fallbackShopId;
        }

        return array_values(array_unique($ids));
    }

    private function assignment(DeliveryAssignment $assignment): void
    {
        LiveSync::touchDriver((int) $assignment->driver_id, 'missions');
        if ($assignment->wasChanged('driver_id') && $assignment->getOriginal('driver_id')) {
            LiveSync::touchDriver((int) $assignment->getOriginal('driver_id'), 'missions');
        }
        $this->orderChild($assignment);
    }

    private function productChild(Model $model): void
    {
        $productId = (int) $model->getAttribute('product_id');
        if ($productId <= 0) {
            return;
        }

        LiveSync::touchProduct($productId, (int) Product::query()->whereKey($productId)->value('shop_id'));
    }

    private function notification(DatabaseNotification $notification): void
    {
        $type = (string) $notification->notifiable_type;
        $id = (int) $notification->notifiable_id;

        if (is_a($type, \App\Models\DeliveryDriver::class, true)) {
            LiveSync::touchDriver($id, 'notifications');

            return;
        }

        if (is_a($type, \App\Models\User::class, true)) {
            LiveSync::touchUser($id, 'notifications');

            return;
        }

        LiveSync::touch('ops');
    }
}
