<?php

/*
|--------------------------------------------------------------------------
| IMPORT À AJOUTER EN HAUT DE routes/web.php
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\VendorNotificationController;


/*
|--------------------------------------------------------------------------
| ROUTES À AJOUTER DANS LE GROUPE VENDEUR EXISTANT
|--------------------------------------------------------------------------
|
| Le groupe doit déjà avoir :
|     ->prefix('vendeur')
|     ->name('vendor.')
|
*/

Route::post(
    '/notifications/read-all',
    [VendorNotificationController::class, 'readAll']
)->name('notifications.read-all');

Route::post(
    '/notifications/{notification}/read',
    [VendorNotificationController::class, 'read']
)->name('notifications.read');
