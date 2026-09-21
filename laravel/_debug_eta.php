<?php
$item = App\Models\OrderItem::find(204);
echo get_class($item) . " vendor_shipment_date=" . $item->vendor_shipment_date . PHP_EOL;
$s = $item->sellerTrackingSession;
if ($s) {
    echo 'session id ' . $s->id . ' manual_eta_at: ' . $s->manual_eta_at . ' estimated_delivery_at: ' . $s->estimated_delivery_at . PHP_EOL;
} else {
    echo 'no seller tracking session' . PHP_EOL;
}
