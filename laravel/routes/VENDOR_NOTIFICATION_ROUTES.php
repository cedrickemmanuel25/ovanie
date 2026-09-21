<?php
/*
|--------------------------------------------------------------------------
| À AJOUTER DANS routes/web.php
|--------------------------------------------------------------------------
*/

/*
| 1. Ajouter avec les imports :
*/
use App\Http\Controllers\VendorNotificationController;

/*
| 2. Ajouter DANS le groupe :
|
| Route::middleware([VendorMiddleware::class, HasShopMiddleware::class])
|     ->prefix('vendeur')
|     ->name('vendor.')
|     ->group(function () {
*/

Route::post(
    '/notifications/read-all',
    [VendorNotificationController::class, 'readAll']
)->name('notifications.read-all');

Route::post(
    '/notifications/{notification}/read',
    [VendorNotificationController::class, 'read']
)->name('notifications.read');
