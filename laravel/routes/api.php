<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\Commercial\CommercialMobileAuthController;
use App\Http\Controllers\Api\Driver\DriverAuthController;
use App\Http\Controllers\Api\Driver\DriverDashboardController;
use App\Http\Controllers\Api\Driver\DriverMissionController;
use App\Http\Controllers\Api\Driver\DriverOnboardingController;
use App\Http\Controllers\Api\Driver\DriverPresenceController;
use App\Http\Controllers\Api\Driver\DriverPushDeviceController;
use App\Http\Controllers\Api\Commercial\CommercialMobileDashboardController;
use App\Http\Controllers\Api\Commercial\CommercialMobileClientController;
use App\Http\Controllers\Api\Commercial\CommercialMobileShopController;
use App\Http\Controllers\Api\Commercial\CommercialMobileProductController;
use App\Http\Controllers\Api\Commercial\CommercialMobileMenuController;
use App\Http\Controllers\Api\Commercial\CommercialMobileProspectingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\Api\AppelOffreController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WaveController;

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\ActivitySectorController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminCommissionController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CartController as ApiCartController;
use App\Http\Controllers\Api\ClientFavoriteController;
use App\Http\Controllers\Api\ClientWishlistController;
use App\Http\Controllers\Api\DevisController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\LogisticsQuoteController;
use App\Http\Controllers\Api\MobileOrderController;
use App\Http\Controllers\Api\MobileClientOrderActionController;
use App\Http\Controllers\Api\MobileReturnController;
use App\Http\Controllers\Api\MobileClientAccountController;
use App\Http\Controllers\Api\MobileReviewController;
use App\Http\Controllers\Api\MobileRecentlyViewedController;
use App\Http\Controllers\Api\MobilePushDeviceController;
use App\Http\Controllers\Api\MobileCheckoutController;
use App\Http\Controllers\Api\DeliveryTerritoryController;
use App\Http\Controllers\Api\MobileMarketplaceController;
use App\Http\Controllers\Api\MobileReferenceDataController;
use App\Http\Controllers\Api\Auth\MobileAuthController;
use App\Http\Controllers\Api\Vendor\VendorMobileController;
use App\Http\Controllers\Api\NegotiationController as ApiNegotiationController;
use App\Http\Controllers\NegotiationController as GuidedNegotiationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\PaymentProofController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReviewController as ApiReviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\DriverLocationController as ApiDriverLocationController;
use App\Http\Controllers\Api\ShipmentTrackingController;

use App\Http\Controllers\Admin\UserApiController;
use App\Http\Controllers\Api\Staff\SupportTicketApiController;
use App\Http\Controllers\Api\Staff\CommercialLeadApiController;
use App\Http\Controllers\Api\Support\PublicSupportChatController;
use App\Http\Controllers\Api\Support\UnifiedMobileSupportController;
use App\Http\Controllers\Api\Commercial\CommercialSupportTransferController;
use App\Http\Controllers\Webhooks\SupportTelephonyWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\Webhooks\TwilioSupportVoiceController;

/*
|--------------------------------------------------------------------------
| API OVANIE V2
|--------------------------------------------------------------------------
| Règle :
| - Public = lecture seule + estimateurs non sensibles.
| - Privé = auth:sanctum.
| - Admin = auth:sanctum + isAdmin.
| - Webhook = public mais throttlé.
*/

/*
|--------------------------------------------------------------------------
| PUBLIC API — lecture seule
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:120,1')->group(function () {
    // Produits publics
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/json', [ProductController::class, 'json']);
    Route::get('/products/top-sellers', [ProductController::class, 'topSellers']);
    Route::get('/products/recommendations', [ProductController::class, 'recommendations']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/catalog', [ProductController::class, 'catalog']);
    Route::post('/products/{product}/view', [ProductController::class, 'incrementView'])->middleware('throttle:60,1');

    // Catégories et recherche. Les boutiques restent internes à OVANIE.
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/marketplace/home', [MobileMarketplaceController::class, 'home']);
    Route::get('/marketplace/categories', [MobileMarketplaceController::class, 'categories']);
    Route::get('/marketplace/filters', [MobileMarketplaceController::class, 'filters']);

    // Devis / appels publics

    // Catalogue business public
    Route::get('/business/json', [BusinessController::class, 'json'])
        ->middleware('throttle:60,1')
        ->name('business.json');

    // Activités & secteurs publics
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::get('/activities/{id}', [ActivityController::class, 'show']);
    Route::get('/activity-sectors', [ActivitySectorController::class, 'index'])->name('activity-sectors.index');
    Route::get('/activity-sectors/{activity_sector}', [ActivitySectorController::class, 'show'])->name('activity-sectors.show');
    Route::get('/secteurs', [ActivitySectorController::class, 'index']);
    Route::get('/secteurs/{slug}/activites', [ActivitySectorController::class, 'activites']);

    // Services / promotions / images produits publics
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::get('/product-images', [ProductImageController::class, 'index']);
    Route::get('/product-images/{id}', [ProductImageController::class, 'show']);
    Route::get('/reviews', [ApiReviewController::class, 'index']);
    Route::get('/reviews/{review}', [ApiReviewController::class, 'show']);

    // Calculateur public : estimation seulement
    Route::post('/calculator/estimate', [CalculatorController::class, 'estimate'])->middleware('throttle:30,1');
});

// Webhook public : ne pas protéger avec Sanctum, sinon les opérateurs ne pourront pas appeler l'URL.
Route::post('/wave/webhook', [WaveController::class, 'webhook'])
    ->middleware('throttle:30,1')
    ->name('wave.webhook');

Route::prefix('support/chat')->middleware(['auth:sanctum', 'throttle:30,1'])->name('api.support.chat.')->group(function () {
    Route::post('/start', [PublicSupportChatController::class, 'start'])->name('start');
    // Doit être déclarée avant /{token} : sinon "latest" serait capturé comme un token.
    Route::get('/latest', [PublicSupportChatController::class, 'latest'])->name('latest');
    Route::get('/{token}', [PublicSupportChatController::class, 'show'])->name('show');
    Route::post('/{token}/messages', [PublicSupportChatController::class, 'message'])->name('message');
    Route::post('/{token}/callback', [PublicSupportChatController::class, 'callback'])->name('callback');
});


Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:120,1')
    ->name('webhooks.whatsapp.verify');

Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:240,1')
    ->name('webhooks.whatsapp.receive');

Route::post('/webhooks/support/telephony', SupportTelephonyWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.support.telephony');

Route::prefix('webhooks/support/twilio')->middleware('throttle:240,1')->name('webhooks.support.twilio.')->group(function () {
    Route::post('/voice', [TwilioSupportVoiceController::class, 'voice'])->name('voice');
    Route::post('/gather', [TwilioSupportVoiceController::class, 'gather'])->name('gather');
    Route::post('/status', [TwilioSupportVoiceController::class, 'status'])->name('status');
});


/*
|--------------------------------------------------------------------------
| MOBILE REFERENCE DATA API — source commune Web ↔ Mobile
|--------------------------------------------------------------------------
| Lecture seule et publique : ces référentiels sont nécessaires avant même
| l'authentification (ouverture boutique, inscription, formulaires, checkout).
| Aucune donnée utilisateur ni donnée sensible n'est exposée ici.
*/
Route::prefix('mobile/v1/reference-data')
    ->middleware('throttle:120,1')
    ->name('api.mobile.reference-data.')
    ->group(function () {
        Route::get('/', [MobileReferenceDataController::class, 'index'])->name('index');
        Route::get('/meta', [MobileReferenceDataController::class, 'meta'])->name('meta');
        Route::get('/compatibility', [MobileReferenceDataController::class, 'compatibility'])->name('compatibility');
    });


/*
|--------------------------------------------------------------------------
| MOBILE AUTH API — OVANIE V49
|--------------------------------------------------------------------------
| API tokenisée Sanctum pour l'application mobile OVANIE.
| Les routes /auth/login et /auth/register sont conservées comme alias
| temporaires pour les APK plus anciens déjà installés sur un téléphone.
*/
Route::prefix('mobile/v1/auth')->group(function () {
    Route::get('/status', [MobileAuthController::class, 'status'])
        ->middleware('throttle:60,1');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [MobileAuthController::class, 'login']);
        Route::post('/register', [MobileAuthController::class, 'register']);
    });

    Route::post('/forgot-password', [MobileAuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,10');
    Route::post('/reset-password', [MobileAuthController::class, 'resetPassword'])
        ->middleware('throttle:8,10');

    Route::middleware(['auth:sanctum', 'throttle:60,1,mobile-auth-session:'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
    });
});

// Compatibilité avec les anciens APK qui appellent /api/auth/* : le même
// contrôleur et les mêmes règles sont appliqués, sans second système d'auth.
Route::prefix('auth')->group(function () {
    Route::get('/status', [MobileAuthController::class, 'status'])
        ->middleware('throttle:60,1');
    Route::post('/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/register', [MobileAuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password', [MobileAuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,10');
    Route::post('/reset-password', [MobileAuthController::class, 'resetPassword'])
        ->middleware('throttle:8,10');

    Route::middleware(['auth:sanctum', 'throttle:60,1,mobile-auth-session:'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
    });
});


/*
|--------------------------------------------------------------------------
| MOBILE VENDEUR API — application OVANIE Vendeur séparée
|--------------------------------------------------------------------------
| Source unique : les mêmes modèles, services et règles que le Web OVANIE.
| Les routes open-shop et open-shop-v2 sont des alias vers la même logique
| afin de rester compatibles avec les builds déjà installés.
*/
Route::prefix('mobile/v1/vendor')->middleware('throttle:120,1')->group(function () {
    Route::get('/status', function () {
        return response()->json([
            'ok' => true,
            'service' => 'OVANIE Vendeur Mobile',
            'version' => 'v1',
        ]);
    });

    // Référentiels utiles au formulaire d'ouverture de boutique.
    Route::get('/meta', [VendorMobileController::class, 'meta']);
    Route::get('/meta/quarters', [VendorMobileController::class, 'quarters']);
    Route::get('/meta/landmarks', [VendorMobileController::class, 'landmarks']);

    // Nouveau vendeur : compte vendeur + boutique en une seule opération.
    Route::post('/open-shop', [VendorMobileController::class, 'openShop'])
        ->middleware('throttle:8,1')
        ->name('api.mobile.vendor.open-shop');

    // Endpoint utilisé par la version mobile actuelle.
    Route::post('/open-shop-v2', [VendorMobileController::class, 'openShop'])
        ->middleware('throttle:8,1')
        ->name('api.mobile.vendor.open-shop-v2');
});

Route::prefix('mobile/v1/vendor')
    ->middleware(['auth:sanctum', 'throttle:240,1'])
    ->group(function () {
        Route::get('/context', [VendorMobileController::class, 'context']);

        // Compatibilité : compte déjà connecté mais sans boutique.
        Route::post('/shop/onboard', [VendorMobileController::class, 'onboardShop'])
            ->middleware('throttle:12,1');

        Route::get('/dashboard', [VendorMobileController::class, 'dashboard']);
        Route::get('/menu', [VendorMobileController::class, 'menuOverview']);
        Route::get('/statistics', [VendorMobileController::class, 'statistics']);
        Route::get('/reviews', [VendorMobileController::class, 'reviews']);
        Route::get('/profile', [VendorMobileController::class, 'personalProfile']);
        Route::get('/returns/{caseId}', [VendorMobileController::class, 'returnDetail']);
        Route::get('/disputes/{caseId}', [VendorMobileController::class, 'disputeDetail']);
        Route::get('/notifications/{notification}', [VendorMobileController::class, 'notificationDetail']);
        Route::post('/reviews/{review}/reply', [VendorMobileController::class, 'replyReview']);
        Route::patch('/profile', [VendorMobileController::class, 'updatePersonalProfile']);
        Route::post('/shop/presentation', [VendorMobileController::class, 'savePresentation']);
        Route::post('/shop/preparation-settings', [VendorMobileController::class, 'savePreparationSettings']);

        Route::get('/shop', [VendorMobileController::class, 'shop']);
        Route::match(['put', 'patch', 'post'], '/shop', [VendorMobileController::class, 'updateShop']);
        Route::get('/shop/delivery', [VendorMobileController::class, 'deliverySettings']);
        Route::post('/shop/logistics-settings', [VendorMobileController::class, 'saveLogisticsSettings']);
        Route::post('/shop/logistics-location', [VendorMobileController::class, 'resolveLogisticsLocation'])->middleware('throttle:30,1');
        Route::post('/shop/delivery', [VendorMobileController::class, 'updateDeliverySettings']);
        Route::post('/shop/delivery/zones', [VendorMobileController::class, 'storeDeliveryZone']);
        Route::match(['put', 'patch'], '/shop/delivery/zones/{zone}', [VendorMobileController::class, 'updateDeliveryZone']);
        Route::delete('/shop/delivery/zones/{zone}', [VendorMobileController::class, 'destroyDeliveryZone']);

        Route::get('/products', [VendorMobileController::class, 'products']);
        Route::post('/products', [VendorMobileController::class, 'storeProduct'])->middleware('throttle:30,1');
        // Product::getRouteKeyName() retourne "slug" pour les routes Web.
        // L'application vendeur envoie volontairement l'ID numérique du produit :
        // on force donc le binding sur la colonne id uniquement pour l'API mobile vendeur.
        Route::get('/products/{product:id}', [VendorMobileController::class, 'product']);
        Route::match(['put', 'patch', 'post'], '/products/{product:id}', [VendorMobileController::class, 'updateProduct']);
        Route::post('/products/{product:id}/toggle', [VendorMobileController::class, 'toggleProduct']);
        Route::delete('/products/{product:id}', [VendorMobileController::class, 'archiveProduct']);
        Route::post('/products/{product:id}/restore', [VendorMobileController::class, 'restoreProduct']);
        Route::post('/products/{product:id}/images/reorder', [VendorMobileController::class, 'reorderProductImages']);
        Route::get('/boost/packages', [VendorMobileController::class, 'boostPackages']);
        Route::post('/products/{product:id}/boost/pay', [VendorMobileController::class, 'payBoost'])->middleware('throttle:20,1');

        Route::get('/orders', [VendorMobileController::class, 'orders']);
        Route::get('/orders/{order}', [VendorMobileController::class, 'order']);
        Route::post('/orders/{order}/preparation', [VendorMobileController::class, 'updateOrderPreparation']);
        Route::post('/orders/{order}/ship', [VendorMobileController::class, 'shipOrder']);
        Route::post('/orders/{order}/delivery-status', [VendorMobileController::class, 'updateDeliveryStatus']);
        Route::post('/orders/{order}/verify-otp', [VendorMobileController::class, 'verifyDeliveryOtp'])->middleware('throttle:10,1');
        Route::post('/orders/{order}/confirm-payment', [VendorMobileController::class, 'reportCodPayment']);

        Route::get('/finance', [VendorMobileController::class, 'finance']);
        Route::get('/payouts', [VendorMobileController::class, 'payouts']);
        Route::get('/payouts/{payout}', [VendorMobileController::class, 'payout']);
        Route::post('/payouts/{payout}/follow-up', [VendorMobileController::class, 'requestPayoutFollowUp']);
        Route::get('/payouts/{payout}/receipt', [VendorMobileController::class, 'payoutReceipt']);

        Route::get('/returns', [VendorMobileController::class, 'returns']);
        Route::post('/returns/{return}/accept', [VendorMobileController::class, 'acceptReturn']);
        Route::post('/returns/{return}/reject', [VendorMobileController::class, 'rejectReturn']);
        Route::post('/returns/{return}/refund', [VendorMobileController::class, 'refundReturn']);

        Route::get('/disputes', [VendorMobileController::class, 'disputes']);
        Route::post('/disputes/{dispute}/respond', [VendorMobileController::class, 'respondDispute']);
        Route::post('/disputes/{dispute}/escalate', [VendorMobileController::class, 'escalateDispute']);

        Route::get('/support', [UnifiedMobileSupportController::class, 'index'])->defaults('support_requester_type', 'vendor')->defaults('support_source_app', 'vendor_mobile');
        Route::post('/support/tickets', [UnifiedMobileSupportController::class, 'store'])->defaults('support_requester_type', 'vendor')->defaults('support_source_app', 'vendor_mobile')->middleware('throttle:10,1');
        Route::get('/support/tickets/{ticket}', [UnifiedMobileSupportController::class, 'show'])->defaults('support_requester_type', 'vendor')->defaults('support_source_app', 'vendor_mobile');
        Route::post('/support/tickets/{ticket}/messages', [UnifiedMobileSupportController::class, 'reply'])->defaults('support_requester_type', 'vendor')->defaults('support_source_app', 'vendor_mobile')->middleware('throttle:20,1');
        Route::get('/support/tickets/{ticket}/messages/{message}/attachments/{index}', [UnifiedMobileSupportController::class, 'download'])->defaults('support_requester_type', 'vendor')->defaults('support_source_app', 'vendor_mobile')->whereNumber('index');

        Route::get('/notifications', [VendorMobileController::class, 'notifications']);
        Route::post('/notifications/read-all', [VendorMobileController::class, 'readAllNotifications']);
        Route::post('/notifications/{notification}/read', [VendorMobileController::class, 'readNotification']);
    });

/*
|--------------------------------------------------------------------------
| PRIVATE API — utilisateur connecté
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:240,1,private-api:'])->group(function () {
    // Auth utilisateur
    Route::get('/auth/me', function (Request $request) {
        return response()->json(['user' => $request->user()]);
    });

    // Panier
    Route::get('/cart', [ApiCartController::class, 'index']);
    Route::post('/cart', [ApiCartController::class, 'store']);
    Route::put('/cart/{cartItem}', [ApiCartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [ApiCartController::class, 'destroy']);
    Route::delete('/cart', [ApiCartController::class, 'clear']);
    // Ajout au panier au prix négocié via NegotiationController::offers/store
    // ci-dessous. Même contrôleur que le Web (App\Http\Controllers\ProductController).
    Route::post('/cart/add-negotiated', [ProductController::class, 'addNegotiatedToCart'])
        ->name('api.cart.addNegotiated');

    // Favoris — même table favorites que l’espace client Web
    Route::get('/favorites', [ClientFavoriteController::class, 'index']);
    Route::put('/favorites/{product:id}', [ClientFavoriteController::class, 'store']);
    Route::delete('/favorites/{product:id}', [ClientFavoriteController::class, 'destroy']);
    Route::delete('/favorites', [ClientFavoriteController::class, 'clear']);

    // Listes d’envies — données persistées dans Laravel et partagées avec le Web.
    Route::get('/wishlists', [ClientWishlistController::class, 'index']);
    Route::post('/wishlists', [ClientWishlistController::class, 'store']);
    Route::patch('/wishlists/{wishlist}', [ClientWishlistController::class, 'update']);
    Route::delete('/wishlists/{wishlist}', [ClientWishlistController::class, 'destroy']);
    Route::put('/wishlists/{wishlist}/products/{product:id}', [ClientWishlistController::class, 'addProduct']);
    Route::delete('/wishlists/{wishlist}/products/{product:id}', [ClientWishlistController::class, 'removeProduct']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1,web-orders:');
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    // Source de vérité des zones/communes utilisées par les checkouts Web + mobile.
    Route::get('/mobile/delivery-territory', [DeliveryTerritoryController::class, 'index']);
    // Prévisualisation checkout mobile : calcule les frais de livraison avant création de la commande
    Route::post('/mobile/checkout/preview', [MobileCheckoutController::class, 'preview'])->middleware('throttle:90,1,mobile-checkout-preview:');

    Route::get('/mobile/orders', [MobileOrderController::class, 'index']);
    Route::post('/mobile/orders', [MobileOrderController::class, 'store'])->middleware('throttle:12,1,mobile-orders:');
    Route::get('/mobile/orders/{order}', [MobileOrderController::class, 'show']);
    Route::get('/mobile/orders/{order}/invoice', [MobileClientOrderActionController::class, 'invoice']);
    Route::post('/mobile/orders/{order}/reception', [MobileClientOrderActionController::class, 'confirmReception'])
        ->middleware('throttle:12,1,mobile-reception:');
    Route::post('/mobile/orders/{order}/cancel', [MobileClientOrderActionController::class, 'cancel'])
        ->middleware('throttle:8,1,mobile-order-cancel:');
    Route::post('/mobile/orders/{order}/reorder', [MobileClientOrderActionController::class, 'reorder'])
        ->middleware('throttle:12,1,mobile-order-reorder:');

    // Retours / réclamations : mêmes tables et mêmes règles que l'espace client Web.
    Route::get('/mobile/returns', [MobileReturnController::class, 'index']);
    Route::post('/mobile/returns', [MobileReturnController::class, 'store'])->middleware('throttle:12,1,mobile-returns:');
    Route::get('/mobile/returns/{returnRequest}', [MobileReturnController::class, 'show'])->whereNumber('returnRequest');


    // Espace client mobile — une seule source Laravel partagée avec le Web.
    Route::get('/mobile/client/capabilities', [MobileClientAccountController::class, 'capabilities']);
    Route::get('/mobile/client/profile', [MobileClientAccountController::class, 'profile']);
    Route::patch('/mobile/client/profile', [MobileClientAccountController::class, 'updateProfile']);
    Route::put('/mobile/client/password', [MobileClientAccountController::class, 'changePassword'])->middleware('throttle:8,1,mobile-password:');
    Route::get('/mobile/client/sessions', [MobileClientAccountController::class, 'sessions']);
    Route::delete('/mobile/client/sessions/{session}', [MobileClientAccountController::class, 'destroySession']);
    Route::get('/mobile/client/account-deletion', [MobileClientAccountController::class, 'accountDeletionStatus']);
    Route::post('/mobile/client/account-deletion/code', [MobileClientAccountController::class, 'requestAccountDeletionCode'])
        ->middleware('throttle:3,10,mobile-account-deletion-code:');
    Route::delete('/mobile/client/account', [MobileClientAccountController::class, 'deleteAccount'])
        ->middleware('throttle:5,10,mobile-account-delete:');

    Route::get('/mobile/client/payment-methods', [MobileClientAccountController::class, 'paymentMethods']);
    Route::post('/mobile/client/payment-methods', [MobileClientAccountController::class, 'storePaymentMethod']);
    Route::post('/mobile/client/payment-methods/{method}/default', [MobileClientAccountController::class, 'defaultPaymentMethod']);
    Route::delete('/mobile/client/payment-methods/{method}', [MobileClientAccountController::class, 'destroyPaymentMethod']);

    Route::get('/mobile/client/notifications', [MobileClientAccountController::class, 'notifications']);
    Route::post('/mobile/client/notifications/read-all', [MobileClientAccountController::class, 'readAllNotifications']);
    Route::post('/mobile/client/notifications/{notification}/read', [MobileClientAccountController::class, 'readNotification']);
    Route::get('/mobile/client/notification-preferences', [MobileClientAccountController::class, 'notificationPreferences']);
    Route::put('/mobile/client/notification-preferences', [MobileClientAccountController::class, 'updateNotificationPreferences']);
    Route::get('/mobile/client/push', [MobilePushDeviceController::class, 'status']);
    Route::post('/mobile/client/push/devices', [MobilePushDeviceController::class, 'store'])
        ->middleware('throttle:15,1,mobile-push-register:');
    Route::delete('/mobile/client/push/devices', [MobilePushDeviceController::class, 'destroy'])
        ->middleware('throttle:15,1,mobile-push-unregister:');

    Route::get('/mobile/client/loyalty', [MobileClientAccountController::class, 'loyalty']);
    Route::get('/mobile/client/legal', [MobileClientAccountController::class, 'legal']);

    Route::get('/mobile/client/reviews', [MobileReviewController::class, 'index']);
    Route::post('/mobile/client/reviews/product', [MobileReviewController::class, 'storeProduct']);
    Route::post('/mobile/client/reviews/delivery', [MobileReviewController::class, 'storeDelivery']);

    Route::get('/mobile/client/recently-viewed', [MobileRecentlyViewedController::class, 'index']);
    Route::post('/mobile/client/recently-viewed/{product}', [MobileRecentlyViewedController::class, 'store']);
    Route::delete('/mobile/client/recently-viewed', [MobileRecentlyViewedController::class, 'destroy']);

    Route::get('/mobile/client/support', [UnifiedMobileSupportController::class, 'index'])->defaults('support_requester_type', 'client')->defaults('support_source_app', 'client_mobile');
    Route::post('/mobile/client/support/tickets', [UnifiedMobileSupportController::class, 'store'])->defaults('support_requester_type', 'client')->defaults('support_source_app', 'client_mobile')->middleware('throttle:10,1,mobile-support-ticket:');
    Route::get('/mobile/client/support/tickets/{ticket}', [UnifiedMobileSupportController::class, 'show'])->defaults('support_requester_type', 'client')->defaults('support_source_app', 'client_mobile');
    Route::post('/mobile/client/support/tickets/{ticket}/messages', [UnifiedMobileSupportController::class, 'reply'])->defaults('support_requester_type', 'client')->defaults('support_source_app', 'client_mobile')->middleware('throttle:20,1,mobile-support-reply:');
    Route::get('/mobile/client/support/tickets/{ticket}/messages/{message}/attachments/{index}', [UnifiedMobileSupportController::class, 'download'])->defaults('support_requester_type', 'client')->defaults('support_source_app', 'client_mobile')
        ->whereNumber('index')
        ->name('mobile.client.support.attachments.download');

    // Paiements
    Route::get('/payments', [ApiPaymentController::class, 'index']);
    Route::post('/payments', [ApiPaymentController::class, 'store'])->middleware('throttle:20,1,payments:');
    Route::get('/payments/{payment}', [ApiPaymentController::class, 'show']);

    // Preuves de paiement
    Route::get('payment-proofs/{paymentProof}/file', [PaymentProofController::class, 'download'])
        ->name('api.payment-proofs.download');
    Route::apiResource('payment-proofs', PaymentProofController::class);

    // Livraison / logistique
    Route::post('/logistics/quotes', [LogisticsQuoteController::class, 'store'])->middleware('throttle:60,1,logistics-quotes:');
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::post('/driver/location/update', [ApiDriverLocationController::class, 'update']);
    Route::get('/shipments/{shipment}/tracking', [ShipmentTrackingController::class, 'shipment']);
    Route::get('/orders/{order}/tracking', [ShipmentTrackingController::class, 'order']);

    // Devis privés
    Route::get('/devis', [DevisController::class, 'index'])->name('api.devis.index');
    Route::post('/devis', [DevisController::class, 'store'])->name('api.devis.store');
    Route::get('/devis/{devis}', [DevisController::class, 'show'])->name('api.devis.show');
    Route::get('/devis/{devis}/attachment', [DevisController::class, 'downloadAttachment'])
        ->name('api.devis.attachment');
    Route::put('/devis/{devis}', [DevisController::class, 'update'])->name('api.devis.update');
    Route::delete('/devis/{devis}', [DevisController::class, 'destroy'])->name('api.devis.destroy');

    // Appels d'offre privés
    Route::get('/appels', [AppelOffreController::class, 'index'])->name('api.appels.index');
    Route::post('/appels', [AppelOffreController::class, 'store'])->name('api.appels.store');
    Route::get('/appels/{appelOffre}', [AppelOffreController::class, 'show'])->name('api.appels.show');
    Route::get('/appels/{appelOffre}/attachment', [AppelOffreController::class, 'downloadAttachment'])
        ->name('api.appels.attachment');
    Route::put('/appels/{appelOffre}', [AppelOffreController::class, 'update'])->name('api.appels.update');
    Route::patch('/appels/{appelOffre}', [AppelOffreController::class, 'update']);
    Route::delete('/appels/{appelOffre}', [AppelOffreController::class, 'destroy'])->name('api.appels.destroy');

    // Négociations
    Route::get('/negotiations', [ApiNegotiationController::class, 'index']);
    Route::post('/negotiations', [ApiNegotiationController::class, 'store']);
    Route::get('/negotiations/{negotiation}', [ApiNegotiationController::class, 'show']);
    Route::put('/negotiations/{negotiation}', [ApiNegotiationController::class, 'update']);
    Route::delete('/negotiations/{negotiation}', [ApiNegotiationController::class, 'destroy']);
    Route::post('/products/{product}/negotiate', [ApiNegotiationController::class, 'storeFromProduct'])->name('products.negotiate');

    // Négociation guidée en 3 offres réelles définies par le vendeur
    // (price_p1/p2/p3), identique au flux Web ("Négocier" sur la fiche
    // produit). Distincte du CRUD ci-dessus, qui crée une négociation
    // "pending" en attente de contre-offre manuelle du vendeur.
    Route::get('/products/{product}/negotiation-offers', [GuidedNegotiationController::class, 'offers'])
        ->name('api.products.negotiationOffers');
    Route::post('/products/{product}/negotiation-offers/accept', [GuidedNegotiationController::class, 'store'])
        ->name('api.products.negotiationOffers.accept');

    // Avis
    Route::post('/reviews', [ApiReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ApiReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ApiReviewController::class, 'destroy']);

    // Demandes business utilisateur
    Route::get('/business/my', [BusinessController::class, 'myRequests']);
    Route::post('/business', [BusinessController::class, 'store']);

    // Adresses utilisateur — même table que l’espace client Web
    Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault']);
    Route::apiResource('addresses', AddressController::class);

    // Litiges
    Route::apiResource('disputes', DisputeController::class);

    // Produits vendeur connecté
    Route::get('/products/my', [ProductController::class, 'myProducts']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Activités protégées
    Route::post('/activities', [ActivityController::class, 'store'])->middleware('isAdmin');
    Route::put('/activities/{id}', [ActivityController::class, 'update'])->middleware('isAdmin');
    Route::patch('/activities/{id}', [ActivityController::class, 'update'])->middleware('isAdmin');
    Route::delete('/activities/{id}', [ActivityController::class, 'destroy'])->middleware('isAdmin');

    // Images produits protégées
    Route::post('/product-images', [ProductImageController::class, 'store']);
    Route::put('/product-images/{id}', [ProductImageController::class, 'update']);
    Route::patch('/product-images/{id}', [ProductImageController::class, 'update']);
    Route::delete('/product-images/{id}', [ProductImageController::class, 'destroy']);

    Route::middleware('staff:support')->prefix('staff/support')->name('api.staff.support.')->group(function () {
        Route::get('/tickets', [SupportTicketApiController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [SupportTicketApiController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [SupportTicketApiController::class, 'show'])->name('tickets.show');
        Route::put('/tickets/{ticket}', [SupportTicketApiController::class, 'update'])->name('tickets.update');
        Route::post('/tickets/{ticket}/messages', [SupportTicketApiController::class, 'reply'])->name('tickets.reply');
    });

    Route::middleware('staff:commercial')->prefix('staff/commercial')->name('api.staff.commercial.')->group(function () {
        Route::get('/leads', [CommercialLeadApiController::class, 'index'])->name('leads.index');
        Route::post('/leads', [CommercialLeadApiController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [CommercialLeadApiController::class, 'show'])->name('leads.show');
        Route::put('/leads/{lead}', [CommercialLeadApiController::class, 'update'])->name('leads.update');
        Route::post('/leads/{lead}/activities', [CommercialLeadApiController::class, 'activity'])->name('leads.activity');
    });
});


/*
|--------------------------------------------------------------------------
| MOBILE COMMERCIAL — authentification interne + tableau de bord
|--------------------------------------------------------------------------
|
| Application séparée dédiée aux commerciaux terrain. Les comptes restent
| ceux de la table users et Laravel reste l'autorité des règles métier.
|
*/
/*
|--------------------------------------------------------------------------
| API MOBILE LIVREUR — application Flutter séparée "OVANIE Livreur"
|--------------------------------------------------------------------------
| OVANIE ne recrute pas ses propres livreurs : ce sont des livreurs
| partenaires invités par la Logistique (voir LogisticsDirectoryController).
| Le contrat détaillé de chaque endpoint est documenté en tête de
| App\Http\Controllers\Api\Driver\DriverAuthController et
| App\Http\Controllers\Api\Driver\DriverOnboardingController.
*/
Route::prefix('driver')->name('api.driver.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/check-phone', [DriverAuthController::class, 'checkPhone'])
            ->middleware('throttle:20,1')
            ->name('check-phone');
        Route::post('/login', [DriverAuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('login');
        Route::post('/login/verify', [DriverAuthController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('login.verify');
        Route::post('/login/resend', [DriverAuthController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('login.resend');
    });

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::post('/auth/logout', [DriverAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [DriverOnboardingController::class, 'me'])->name('me');
        Route::get('/dashboard', DriverDashboardController::class)->name('dashboard');
        Route::get('/map-config', [DriverMissionController::class, 'mapConfig'])->name('map-config');
        Route::get('/missions', [DriverMissionController::class, 'index'])->name('missions.index');
        Route::get('/missions/{missionNumber}', [DriverMissionController::class, 'show'])->name('missions.show');
        Route::post('/missions/{missionNumber}/accept', [DriverMissionController::class, 'accept'])->name('missions.accept');
        Route::post('/missions/{missionNumber}/reject', [DriverMissionController::class, 'reject'])->name('missions.reject');
        Route::post('/missions/{missionNumber}/start', [DriverMissionController::class, 'start'])->name('missions.start');
        Route::post('/missions/{missionNumber}/pickups/{stopId}/complete', [DriverMissionController::class, 'completePickup'])->name('missions.pickups.complete');
        Route::post('/missions/{missionNumber}/stage', [DriverMissionController::class, 'stage'])->name('missions.stage');
        Route::post('/missions/{missionNumber}/location', [DriverMissionController::class, 'location'])
            ->middleware('throttle:120,1')
            ->name('missions.location');
        Route::post('/missions/{missionNumber}/gps-unavailable', [DriverMissionController::class, 'gpsUnavailable'])->name('missions.gps-unavailable');
        Route::post('/missions/{missionNumber}/incident', [DriverMissionController::class, 'incident'])->name('missions.incident');
        Route::post('/missions/{missionNumber}/verify-otp', [DriverMissionController::class, 'verifyOtp'])->name('missions.verify-otp');
        Route::post('/presence', [DriverPresenceController::class, 'heartbeat'])
            ->middleware('throttle:120,1')
            ->name('presence');
        Route::post('/presence/offline', [DriverPresenceController::class, 'offline'])
            ->name('presence.offline');
        Route::post('/availability', [DriverPresenceController::class, 'availability'])
            ->name('availability');
        Route::post('/onboarding/submit', [DriverOnboardingController::class, 'submit'])
            ->middleware('throttle:10,1')
            ->name('onboarding.submit');
        Route::get('/territory/communes', [DriverOnboardingController::class, 'communes'])->name('territory.communes');
        Route::get('/push', [DriverPushDeviceController::class, 'status'])->name('push.status');
        Route::post('/push/devices', [DriverPushDeviceController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('push.devices.store');
        Route::delete('/push/devices', [DriverPushDeviceController::class, 'destroy'])
            ->middleware('throttle:15,1')
            ->name('push.devices.destroy');

        Route::get('/support', [UnifiedMobileSupportController::class, 'index'])->defaults('support_requester_type', 'driver')->defaults('support_source_app', 'driver_mobile');
        Route::post('/support/tickets', [UnifiedMobileSupportController::class, 'store'])->defaults('support_requester_type', 'driver')->defaults('support_source_app', 'driver_mobile')->middleware('throttle:10,1');
        Route::get('/support/tickets/{ticket}', [UnifiedMobileSupportController::class, 'show'])->defaults('support_requester_type', 'driver')->defaults('support_source_app', 'driver_mobile');
        Route::post('/support/tickets/{ticket}/messages', [UnifiedMobileSupportController::class, 'reply'])->defaults('support_requester_type', 'driver')->defaults('support_source_app', 'driver_mobile')->middleware('throttle:20,1');
        Route::get('/support/tickets/{ticket}/messages/{message}/attachments/{index}', [UnifiedMobileSupportController::class, 'download'])->defaults('support_requester_type', 'driver')->defaults('support_source_app', 'driver_mobile')->whereNumber('index');
    });
});

Route::prefix('mobile/v1/commercial')->name('api.mobile.commercial.')->group(function () {
    Route::get('/auth/status', [CommercialMobileAuthController::class, 'status'])->name('auth.status');
    Route::post('/auth/login', [CommercialMobileAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'staff:commercial', 'throttle:120,1'])->group(function () {
        Route::get('/auth/me', [CommercialMobileAuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [CommercialMobileAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/dashboard', CommercialMobileDashboardController::class)->name('dashboard');
        Route::get('/clients', [CommercialMobileClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [CommercialMobileClientController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('clients.store');
        Route::get('/shops/meta', [CommercialMobileShopController::class, 'meta'])->name('shops.meta');
        Route::get('/geo/reverse', [CommercialMobileShopController::class, 'reverseGeocode'])
            ->middleware('throttle:30,1')
            ->name('geo.reverse');
        Route::get('/shops', [CommercialMobileShopController::class, 'index'])->name('shops.index');
        Route::post('/shops', [CommercialMobileShopController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('shops.store');
        Route::get('/shops/{shop}', [CommercialMobileShopController::class, 'show'])
            ->whereNumber('shop')
            ->name('shops.show');

        Route::get('/prospecting/overview', [CommercialMobileProspectingController::class, 'overview'])->name('prospecting.overview');
        Route::get('/prospecting/mission', [CommercialMobileProspectingController::class, 'mission'])->name('prospecting.mission');
        Route::post('/prospecting/missions/{mission}/quarters/{missionQuarter}/start', [CommercialMobileProspectingController::class, 'startQuarter'])->name('prospecting.quarters.start');
        Route::post('/prospecting/missions/{mission}/quarters/{missionQuarter}/finish', [CommercialMobileProspectingController::class, 'finishQuarter'])->name('prospecting.quarters.finish');
        Route::get('/prospecting/prospects', [CommercialMobileProspectingController::class, 'prospects'])->name('prospecting.prospects');
        Route::post('/prospecting/prospects', [CommercialMobileProspectingController::class, 'storeProspect'])->name('prospecting.prospects.store');
        Route::post('/prospecting/prospects/{prospect}/visits', [CommercialMobileProspectingController::class, 'visit'])->name('prospecting.prospects.visit');

        Route::get('/products/shops', [CommercialMobileProductController::class, 'shopsIndex'])->name('products.shops');
        Route::get('/products/meta', [CommercialMobileProductController::class, 'meta'])->name('products.meta');
        Route::get('/products/sessions', [CommercialMobileProductController::class, 'sessions'])->name('products.sessions');
        Route::post('/products/sessions', [CommercialMobileProductController::class, 'createSession'])->name('products.sessions.store');
        Route::get('/products/sessions/{id}', [CommercialMobileProductController::class, 'session'])->whereNumber('id');
        Route::post('/products/sessions/{id}/finish', [CommercialMobileProductController::class, 'finish'])->whereNumber('id');
        Route::post('/products/capture', [CommercialMobileProductController::class, 'capture'])->middleware('throttle:60,1');
        Route::get('/products/incomplete', [CommercialMobileProductController::class, 'incomplete']);
        Route::get('/products/{product:id}/edit', [CommercialMobileProductController::class, 'edit'])->whereNumber('product');
        Route::post('/products/{product:id}/steps/{step}', [CommercialMobileProductController::class, 'saveStep'])->whereNumber('product')->whereNumber('step');
        Route::post('/products/{product:id}/media', [CommercialMobileProductController::class, 'media'])->whereNumber('product');
        Route::post('/products/{product:id}/publish', [CommercialMobileProductController::class, 'publish'])->whereNumber('product');

        Route::get('/support', [UnifiedMobileSupportController::class, 'index'])->defaults('support_requester_type', 'commercial')->defaults('support_source_app', 'commercial_mobile');
        Route::post('/support/tickets', [UnifiedMobileSupportController::class, 'store'])->defaults('support_requester_type', 'commercial')->defaults('support_source_app', 'commercial_mobile')->middleware('throttle:10,1');
        Route::get('/support/tickets/{ticket}', [UnifiedMobileSupportController::class, 'show'])->defaults('support_requester_type', 'commercial')->defaults('support_source_app', 'commercial_mobile');
        Route::post('/support/tickets/{ticket}/messages', [UnifiedMobileSupportController::class, 'reply'])->defaults('support_requester_type', 'commercial')->defaults('support_source_app', 'commercial_mobile')->middleware('throttle:20,1');
        Route::get('/support/tickets/{ticket}/messages/{message}/attachments/{index}', [UnifiedMobileSupportController::class, 'download'])->defaults('support_requester_type', 'commercial')->defaults('support_source_app', 'commercial_mobile')->whereNumber('index');

        Route::get('/support/transfers', [CommercialSupportTransferController::class, 'index']);
        Route::get('/support/transfers/{handoff}', [CommercialSupportTransferController::class, 'show']);
        Route::post('/support/transfers/{handoff}/claim', [CommercialSupportTransferController::class, 'claim']);
        Route::post('/support/transfers/{handoff}/messages', [CommercialSupportTransferController::class, 'message']);
        Route::post('/support/transfers/{handoff}/resolve', [CommercialSupportTransferController::class, 'resolve']);

        // Menu OVANIE Commercial
        Route::get('/menu/overview', [CommercialMobileMenuController::class, 'overview'])->name('menu.overview');
        Route::get('/menu/profile', [CommercialMobileMenuController::class, 'profile'])->name('menu.profile');
        Route::get('/menu/notifications', [CommercialMobileMenuController::class, 'notifications'])->name('menu.notifications');
        Route::post('/menu/notifications/read-all', [CommercialMobileMenuController::class, 'markAllNotificationsRead'])->name('menu.notifications.read-all');
        Route::post('/menu/notifications/{notification}/read', [CommercialMobileMenuController::class, 'markNotificationRead'])->name('menu.notifications.read');
        Route::get('/menu/preferences', [CommercialMobileMenuController::class, 'preferences'])->name('menu.preferences');
        Route::post('/menu/preferences', [CommercialMobileMenuController::class, 'savePreferences'])->name('menu.preferences.save');
        Route::get('/menu/visits', [CommercialMobileMenuController::class, 'visits'])->name('menu.visits');
        Route::get('/menu/drafts', [CommercialMobileMenuController::class, 'drafts'])->name('menu.drafts');
        Route::get('/menu/sync', [CommercialMobileMenuController::class, 'syncStatus'])->name('menu.sync');
        Route::post('/menu/sync', [CommercialMobileMenuController::class, 'synchronize'])->name('menu.sync.run');
    });
});

/*
|--------------------------------------------------------------------------
| ADMIN API — admin uniquement
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'isAdmin', 'throttle:60,1'])->prefix('admin')->group(function () {
    // Utilisateurs
    Route::get('/users', [UserApiController::class, 'index']);
    Route::post('/users', [UserApiController::class, 'store']);
    Route::put('/users/{user}', [UserApiController::class, 'update']);
    Route::delete('/users/{user}', [UserApiController::class, 'destroy']);

    // Catégories admin : CRUD protégé
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Promotions admin : CRUD protégé
    Route::post('/promotions', [PromotionController::class, 'store']);
    Route::put('/promotions/{promotion}', [PromotionController::class, 'update']);
    Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy']);

    // Commissions admin
    Route::get('/commissions', [AdminCommissionController::class, 'index']);
    Route::post('/commissions/{id}/pay', [AdminCommissionController::class, 'pay'])->middleware('throttle:30,1');

    // Activity sectors admin CRUD
    Route::post('/activity-sectors', [ActivitySectorController::class, 'store']);
    Route::put('/activity-sectors/{activity_sector}', [ActivitySectorController::class, 'update']);
    Route::patch('/activity-sectors/{activity_sector}', [ActivitySectorController::class, 'update']);
    Route::delete('/activity-sectors/{activity_sector}', [ActivitySectorController::class, 'destroy']);
});
