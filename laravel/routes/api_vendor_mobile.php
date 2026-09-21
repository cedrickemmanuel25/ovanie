<?php

use App\Http\Controllers\Api\Vendor\VendorMobileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OVANIE VENDEUR MOBILE API v1
|--------------------------------------------------------------------------
| Application Flutter distincte : com.ovanie.vendor
| Source de données unique : backend Laravel OVANIE / parcours Web.
*/
Route::prefix('mobile/v1/vendor')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('/status', function () {
            return response()->json([
                'ok' => true,
                'service' => 'OVANIE Vendeur Mobile',
                'version' => 'v1',
            ]);
        });

        // Données nécessaires au formulaire public /open-shop.
        Route::get('/meta', [VendorMobileController::class, 'meta']);
        Route::get('/meta/quarters', [VendorMobileController::class, 'quarters']);
        Route::get('/meta/landmarks', [VendorMobileController::class, 'landmarks']);

        // Nouveau vendeur : compte vendeur + boutique dans une seule opération.
        Route::post('/open-shop', [VendorMobileController::class, 'openShop'])
            ->middleware('throttle:8,1');
    });

Route::prefix('mobile/v1/vendor')
    ->middleware(['auth:sanctum', 'throttle:240,1'])
    ->group(function () {
        Route::get('/context', [VendorMobileController::class, 'context']);

        // Compte existant connecté mais sans boutique : même formulaire,
        // sans création d'un second compte.
        Route::post('/shop/onboard', [VendorMobileController::class, 'onboardShop'])
            ->middleware('throttle:12,1');

        Route::middleware('vendor.mobile.shop')->group(function () {
            Route::get('/dashboard', [VendorMobileController::class, 'dashboard']);

            Route::get('/shop', [VendorMobileController::class, 'shop']);
            Route::match(['put', 'patch', 'post'], '/shop', [VendorMobileController::class, 'updateShop']);
            Route::get('/shop/delivery', [VendorMobileController::class, 'deliverySettings']);
            Route::post('/shop/delivery', [VendorMobileController::class, 'updateDeliverySettings']);
            Route::post('/shop/delivery/zones', [VendorMobileController::class, 'storeDeliveryZone']);
            Route::match(['put', 'patch'], '/shop/delivery/zones/{zone}', [VendorMobileController::class, 'updateDeliveryZone']);
            Route::delete('/shop/delivery/zones/{zone}', [VendorMobileController::class, 'destroyDeliveryZone']);

            Route::get('/products', [VendorMobileController::class, 'products']);
            Route::post('/products', [VendorMobileController::class, 'storeProduct'])
                ->middleware('throttle:30,1');
            // Product utilise le slug sur le Web, mais l'application mobile envoie son ID.
            Route::get('/products/{product:id}', [VendorMobileController::class, 'product']);
            Route::match(['put', 'patch', 'post'], '/products/{product:id}', [VendorMobileController::class, 'updateProduct']);
            Route::post('/products/{product:id}/toggle', [VendorMobileController::class, 'toggleProduct']);
            Route::delete('/products/{product:id}', [VendorMobileController::class, 'archiveProduct']);
            Route::post('/products/{product:id}/restore', [VendorMobileController::class, 'restoreProduct']);

            Route::get('/orders', [VendorMobileController::class, 'orders']);
            Route::get('/orders/{order}', [VendorMobileController::class, 'order']);
            Route::post('/orders/{order}/preparation', [VendorMobileController::class, 'updateOrderPreparation']);
            Route::post('/orders/{order}/ship', [VendorMobileController::class, 'shipOrder']);
            Route::post('/orders/{order}/delivery-status', [VendorMobileController::class, 'updateDeliveryStatus']);
            Route::post('/orders/{order}/verify-otp', [VendorMobileController::class, 'verifyDeliveryOtp'])
                ->middleware('throttle:10,1');
            Route::post('/orders/{order}/confirm-payment', [VendorMobileController::class, 'reportCodPayment']);

            Route::get('/finance', [VendorMobileController::class, 'finance']);
            Route::get('/payouts', [VendorMobileController::class, 'payouts']);
            Route::get('/payouts/{payout}', [VendorMobileController::class, 'payout']);
            Route::post('/payouts/{payout}/follow-up', [VendorMobileController::class, 'requestPayoutFollowUp']);
        Route::get('/payouts/{payout}/receipt', [VendorMobileController::class, 'payoutReceipt']);

            Route::get('/returns', [VendorMobileController::class, 'returns']);
            Route::post('/returns/{return}/accept', [VendorMobileController::class, 'acceptReturn']);
            Route::post('/returns/{return}/reject', [VendorMobileController::class, 'rejectReturn']);

            Route::get('/disputes', [VendorMobileController::class, 'disputes']);
            Route::post('/disputes/{dispute}/respond', [VendorMobileController::class, 'respondDispute']);
            Route::post('/disputes/{dispute}/escalate', [VendorMobileController::class, 'escalateDispute']);

            Route::get('/notifications', [VendorMobileController::class, 'notifications']);
            Route::post('/notifications/read-all', [VendorMobileController::class, 'readAllNotifications']);
            Route::post('/notifications/{notification}/read', [VendorMobileController::class, 'readNotification']);
        });
    });
