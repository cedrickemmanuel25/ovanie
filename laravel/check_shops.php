<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shops = DB::table('shops')->get(['id','name','status','is_active','logistics_status','address','commune','district','latitude','longitude','logistics_type']);
echo "=== SHOPS STATUS ===\n";
foreach ($shops as $s) {
    $issues = [];
    if ($s->status !== 'approved') $issues[] = 'status='.$s->status;
    if (!$s->is_active) $issues[] = 'is_active=0';
    if ($s->logistics_status !== 'ready') $issues[] = 'logistics_status='.$s->logistics_status;
    if (!$s->address) $issues[] = 'no_address';
    if (!$s->district) $issues[] = 'no_district';
    if (!$s->latitude) $issues[] = 'no_lat';
    echo 'Shop#'.$s->id.' ['.$s->name.']: ' . (empty($issues) ? 'OK' : implode(', ', $issues)) . "\n";
}

echo "\n=== USERS ===\n";
$users = DB::table('users')->get(['id','name','email','role','created_at']);
foreach ($users as $u) {
    echo '#'.$u->id.' ['.$u->role.'] '.$u->name.' - '.$u->email."\n";
}

echo "\n=== PRODUCTS visible count ===\n";
$count = DB::table('products')
    ->join('shops', 'products.shop_id', '=', 'shops.id')
    ->where('shops.status', 'approved')
    ->where('shops.is_active', 1)
    ->where('shops.logistics_status', 'ready')
    ->whereNotNull('shops.address')
    ->whereNotNull('shops.district')
    ->whereNotNull('shops.latitude')
    ->count();
echo "Visible products: $count\n";
