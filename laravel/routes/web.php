<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SensitiveDocumentController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ClientAccountController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\LogisticsTerritoryController;
use App\Http\Controllers\LogisticsOvaniePricingController;
use App\Http\Controllers\LogisticsPartnerController;
use App\Http\Controllers\LogisticsFleetController;
use App\Http\Controllers\LogisticsPilotageController;
use App\Http\Controllers\DeliveryIncidentController;
use App\Http\Controllers\VendorDashboardController;
use App\Http\Controllers\VendorDisputeController;
use App\Http\Controllers\VendorReviewController;
use App\Http\Controllers\VendorOrderController;
use App\Http\Controllers\VendorPaymentController;
use App\Http\Controllers\VendorProductController;
use App\Http\Controllers\VendorDeliverySettingsController;
use App\Http\Controllers\VendorReturnController;
use App\Http\Controllers\VendorShopStatusController;
use App\Http\Controllers\VendorShopProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AppelOffreController;
use App\Http\Controllers\DevisWebController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\HomepageStatusController;
use App\Http\Controllers\SearchSuggestionController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayDunyaReturnController;
use App\Http\Controllers\PayDunyaPayoutCallbackController;
use App\Http\Controllers\GiftCardController;
use App\Http\Controllers\GiftCardPaymentReturnController;
use App\Http\Controllers\ClientGiftCardController;
use App\Http\Controllers\Admin\AdminGiftCardController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\DriverLocationController;
use App\Http\Controllers\LogisticsTrackingController;
use App\Http\Controllers\LogisticsRouteController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\NegotiationController;
use App\Http\Controllers\Api\DevisController;
use App\Http\Controllers\Api\ShipmentTrackingController as ApiShipmentTrackingController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminRegisterController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminClientController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\PublicSubmissionController;
use App\Http\Controllers\Admin\AdminShopController;
use App\Http\Controllers\Admin\AdminShopDocumentController;
use App\Http\Controllers\Admin\AdminVendorPaymentVerificationController;
use App\Http\Controllers\Admin\HomeAdController;
use App\Http\Controllers\Admin\DeliveryServiceController;
use App\Http\Controllers\Admin\DeliveryDistanceMatrixController;
use App\Http\Controllers\Admin\MasterProductController;
use App\Http\Controllers\Admin\CartFulfillmentOptimizationController;
use App\Http\Controllers\Admin\SubmissionsController;
use App\Http\Controllers\Admin\AdminSmsController;
use App\Http\Controllers\Admin\VendorPayoutExportController;
use App\Http\Controllers\Admin\VendorPayoutController;
use App\Http\Controllers\OrderReceptionController as ClientOrderReceptionController;
use App\Http\Controllers\Admin\OrderReceptionController as AdminOrderReceptionController;
use App\Http\Controllers\WaveController;
use App\Http\Controllers\BusinessController as BusinessCatalogController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Support\SupportDashboardController;
use App\Http\Controllers\Support\SupportTicketController;
use App\Http\Controllers\Support\SupportRecordController;
use App\Http\Controllers\Support\SupportAiAgentController;
use App\Http\Controllers\Support\SupportConversationController;
use App\Http\Controllers\Support\SupportCallController;
use App\Http\Controllers\Support\SupportHandoffController;
use App\Http\Controllers\Support\SupportCallbackController;
use App\Http\Controllers\Support\SupportKnowledgeController;
use App\Http\Controllers\Support\SupportAiAuditController;
use App\Http\Controllers\Commercial\CommercialDashboardController;
use App\Http\Controllers\Commercial\CommercialLeadController;
use App\Http\Controllers\Commercial\CommercialRecordController;
use App\Http\Controllers\Commercial\CommercialAccountController;
use App\Http\Controllers\Commercial\CommercialProductController;
use App\Http\Controllers\Commercial\CommercialQuickProductController;
use App\Http\Controllers\Commercial\CommercialShopController;
use App\Http\Controllers\Commercial\CommercialProspectingController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AdminProspectingMissionController;
use App\Http\Controllers\Internal\DepartmentHandoffController;
use App\Http\Middleware\VendorMiddleware;
use App\Http\Middleware\HasShopMiddleware;



/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/
Route::get('/', [ProductController::class, 'home'])->name('home');
Route::view('/comment-acheter', 'public.service-info', ['page' => 'buy'])->name('how-to-buy');
Route::view('/livraison', 'public.service-info', ['page' => 'delivery'])->name('delivery.info');
Route::view('/paiement-securise', 'public.service-info', ['page' => 'payment'])->name('payment.secure');
Route::view('/retours-remboursements', 'public.service-info', ['page' => 'returns'])->name('returns.refunds');

// Cartes cadeaux OVANIE
Route::get('/cartes-cadeaux', [GiftCardController::class, 'index'])->name('gift-cards.index');
Route::get('/cartes-cadeaux/comment-ca-marche', [GiftCardController::class, 'howItWorks'])
    ->name('gift-cards.how-it-works');
Route::get('/cartes-cadeaux/conditions-utilisation', [GiftCardController::class, 'terms'])
    ->name('gift-cards.terms');
Route::get('/cartes-cadeaux/{category}', [GiftCardController::class, 'category'])
    ->where('category', 'bon-achat|carte-cadeau|carte-virtuelle')
    ->name('gift-cards.category');
Route::get('/cartes-cadeaux/fiche/{giftCardProduct}', [GiftCardController::class, 'show'])
    ->name('gift-cards.show');
Route::get('/cartes-cadeaux/paiement/retour', [GiftCardPaymentReturnController::class, 'success'])
    ->middleware('throttle:60,1')
    ->name('gift-cards.payment.return');
Route::get('/cartes-cadeaux/paiement/annule', [GiftCardPaymentReturnController::class, 'cancel'])
    ->middleware('throttle:60,1')
    ->name('gift-cards.payment.cancel');
Route::middleware(['auth', 'throttle:20,1'])->group(function () {
    // Nouveau parcours : Acheter => formulaire de paiement OVANIE directement.
    Route::get('/cartes-cadeaux/{giftCardProduct}/paiement', [GiftCardController::class, 'showPayment'])
        ->name('gift-cards.payment.show');
    Route::post('/cartes-cadeaux/{giftCardProduct}/paiement', [GiftCardController::class, 'startPayment'])
        ->name('gift-cards.payment.start');

    // Compatibilité avec les anciens liens /acheter : aucune fiche intermédiaire.
    Route::get('/cartes-cadeaux/{giftCardProduct}/acheter', [GiftCardController::class, 'createPurchase'])
        ->name('gift-cards.purchase.create');
});

Route::get('/search', [ProductController::class, 'search'])->name('search');
Route::get('/search/suggestions', SearchSuggestionController::class)
    ->middleware('throttle:60,1')
    ->name('search.suggestions');
Route::get('/homepage/status', HomepageStatusController::class)
    ->middleware('throttle:60,1')
    ->name('homepage.status');
Route::get('/categories/{category}', [ProductController::class, 'categoryPage'])
    ->whereIn('category', [
        'materiaux-gros-oeuvre',
        'materiaux-de-finition',
        'outillage-equipement',
        'electricite-plomberie',
        'energie-solaire',
        'materiaux-ecologiques',
        'reconditionnes',
    ])
    ->name('categories.show');
Route::get('/meilleures-ventes', [ProductController::class, 'curatedPage'])
    ->defaults('selection', 'best-sellers')
    ->name('catalog.best-sellers');
Route::get('/nouveautes', [ProductController::class, 'curatedPage'])
    ->defaults('selection', 'new-arrivals')
    ->name('catalog.new-arrivals');
Route::get('/catalog/{category?}', [ProductController::class, 'catalog'])->name('catalog.index');
Route::post('/cart/add-negotiated', [ProductController::class, 'addNegotiatedToCart'])
    ->middleware('auth')
    ->name('cart.addNegotiated');
Route::get('/catalog-business', [BusinessCatalogController::class, 'catalog'])
    ->name('catalog.business');
Route::view('/guide-acheteur', 'public.buyer-guide')->middleware('auth')->name('buyer.guide');
Route::view('/faq', 'public.faq')->name('faq');
Route::view('/centre-aide', 'public.help-pages', ['page' => 'help'])->name('help.center');
Route::view('/suivi-commande', 'public.help-pages', ['page' => 'tracking'])->name('order.tracking.public');
Route::view('/garantie-acheteur', 'public.help-pages', ['page' => 'guarantee'])->name('buyer.guarantee');
Route::view('/vendre-sur-ovanie', 'public.sell-on-ovanie')->name('sell.on.ovanie');
Route::view('/partenaires-fournisseurs', 'public.partners-suppliers')->name('partners.suppliers');
Route::view('/conditions-vendeurs', 'public.institutional-page', ['page' => 'vendor-terms'])->name('vendor.terms');
Route::view('/notre-mission', 'public.institutional-page', ['page' => 'mission'])->name('company.mission');
Route::view('/nos-valeurs', 'public.institutional-page', ['page' => 'values'])->name('company.values');
Route::view('/signaler-un-probleme', 'public.institutional-page', ['page' => 'report'])->name('problem.report');
Route::view('/mentions-legales', 'public.legal-page', ['page' => 'legal-notice'])->name('legal.notice');
Route::view('/qui-sommes-nous', 'public.about')->name('about');
Route::view('/carrieres', 'public.careers')->name('careers');
Route::view('/plan-du-site', 'public.sitemap')->name('sitemap');

Route::view('/politique-de-confidentialite', 'public.legal-page', ['page' => 'privacy'])
    ->name('privacy-policy');

Route::view('/conditions-generales', 'public.legal-page', ['page' => 'terms'])
    ->name('terms');

Route::view('/suppression-des-donnees', 'public.data-deletion')
    ->name('data-deletion');

Route::view('/paiement-a-la-livraison', 'public.payment-on-delivery')
    ->middleware('auth')
    ->name('payment.delivery.info');

// Ancienne URL publique conservée pour compatibilité avec les anciens liens.
Route::redirect('/cgu', '/conditions-generales', 301)
    ->name('cgu');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/boost/product/{product}/click', [ProductController::class, 'boostClick'])
    ->name('boost.product.click');
Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::view('/assistance', 'public.support-chat')->middleware('auth')->name('public.support-chat');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');
Route::post('/newsletter/subscribe', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

Route::get('/appel-offre', [AppelOffreController::class, 'create'])->name('appel.offre');
Route::post('/appel-offre', [AppelOffreController::class, 'store'])->name('appel.offre.store');
Route::get('/appel-offre/{id}', [AppelOffreController::class, 'show'])->name('appel.offre.show');

Route::get('/devis', [DevisWebController::class, 'create'])->name('devis.create');
Route::get('/calculator', [CalculatorController::class, 'index'])->name('calculator.index');
Route::post('/calculator/estimate', [CalculatorController::class, 'estimate'])
    ->middleware('throttle:30,1')
    ->name('calculator.estimate');
Route::post('/calculator/add-to-cart', [CalculatorController::class, 'addToCart'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('calculator.addToCart');

Route::post('/product/{product}/negotiate', [NegotiationController::class, 'store'])
    ->middleware('auth')
    ->name('product.negotiate');

/*
|--------------------------------------------------------------------------
| DIAGNOSTIC LOCAL V60 (jamais disponible sur ovanie.com)
|--------------------------------------------------------------------------
*/
Route::get('/__local/v60', function (\Illuminate\Http\Request $request) {
    $host = strtolower($request->getHost());
    $allowed = $host === 'localhost'
        || $host === '127.0.0.1'
        || str_ends_with($host, '.trycloudflare.com')
        || str_starts_with($host, '192.168.')
        || str_starts_with($host, '10.');

    abort_unless($allowed, 404);

    $request->session()->put('ovanie_v60_probe', now()->toIso8601String());

    return response()->json([
        'ok' => true,
        'version' => 'V60',
        'host' => $host,
        'session_driver' => config('session.driver'),
        'session_cookie' => config('session.cookie'),
        'session_domain' => config('session.domain'),
        'secure_cookie' => config('session.secure'),
        'message' => 'Session locale OVANIE V60 active.',
    ]);
})->name('local.v60.status');

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION WEB (GUEST)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::post('/login', [UserAuthController::class, 'loginWeb'])->middleware('throttle:5,1')->name('login.web');

    Route::get('/register', [UserAuthController::class, 'showRegistrationChoice'])->name('register');
    Route::get('/register/client', [UserAuthController::class, 'showRegisterForm'])->name('register.client');
    Route::post('/register', [UserAuthController::class, 'registerWeb'])->middleware('throttle:3,1')->name('register.post');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});


/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION WEB (UTILISATEURS CONNECTÉS)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/client/documents/retours/{return}', [SensitiveDocumentController::class, 'clientReturn'])
        ->name('client.private-documents.return');
    Route::get('/client/documents/retours/{return}/decision/{document}', [SensitiveDocumentController::class, 'clientReturnDecisionProof'])
        ->whereNumber('document')->name('client.private-documents.return-decision');
    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->regenerate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');

    Route::get('/dashboard', function () {
        $user = Auth::user();
        if ($user) {
            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            if ($user->role === 'vendor' || $user->shop) {
                return redirect()->route('vendor.dashboard');
            }
            if (in_array($user->role, ['logistique', 'logistics'], true)) {
                return redirect()->route('logistics.dashboard');
            }
            if ($user->role === 'support') {
                return redirect()->route('support.dashboard');
            }
            if ($user->role === 'commercial') {
                return redirect()->route('commercial.dashboard');
            }
        }
        return redirect()->route('client.dashboard');
    })->name('dashboard');

    // Routes standard Laravel/Breeze nécessaires aux tests Auth et Profile.
    Route::get('/verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('/password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::get('/profile', function () {
        $user = Auth::user();

        if ($user && ($user->role === 'vendor' || $user->shop)) {
            return redirect()->route('vendor.dashboard');
        }

        if ($user && $user->role === 'logistique') {
            return redirect()->route('logistics.dashboard');
        }

        if ($user && $user->role === 'support') {
            return redirect()->route('support.dashboard');
        }

        if ($user && $user->role === 'commercial') {
            return redirect()->route('commercial.dashboard');
        }

        return app(ProfileController::class)->edit(request());
    })->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:5,10')
        ->name('profile.destroy');
    Route::patch('/user/password', [PasswordController::class, 'update'])->name('user.password.update');



    Route::prefix('driver')->group(function () {
        Route::post('/location', [DriverLocationController::class, 'store'])->name('driver.location.store');
    });
    /*
    |--------------------------------------------------------------------------
    | ESPACE VENDEUR OVANIE
    |--------------------------------------------------------------------------
    | Nouvelle convention production : /vendeur avec noms vendor.*
    | Ancienne convention : /daniel avec noms daniel.* pour compatibilité.
    | L'ouverture de boutique est automatique : pas de blocage shop.approved.
    */

    Route::middleware([VendorMiddleware::class, HasShopMiddleware::class])
        ->prefix('vendeur')
        ->name('vendor.')
        ->group(function () {
            Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');
            Route::view('/vendeur-actes', 'vendor.vendor-actes')->name('vendeur-actes');
            Route::view('/cgu', 'vendor.cgu')->name('cgu');
            Route::get('/shop-status', [VendorShopStatusController::class, 'index'])->name('shop-status');
            Route::get('/shop-profile', [VendorShopProfileController::class, 'show'])->name('shop.profile');
            Route::get('/shop-profile/edit', [VendorShopProfileController::class, 'edit'])->name('shop.edit');
            Route::put('/shop-profile', [VendorShopProfileController::class, 'update'])->name('shop.update');
            Route::get('/payment-method', [VendorShopProfileController::class, 'paymentMethod'])->name('payment-method');
            Route::put('/payment-method', [VendorShopProfileController::class, 'updatePaymentMethod'])->name('payment-method.update');
            Route::get('/shop-documents', [VendorShopProfileController::class, 'documents'])->name('shop.documents');
            Route::post('/shop-documents', [VendorShopProfileController::class, 'updateDocuments'])->name('shop.documents.update');

            Route::get('/products', [VendorProductController::class, 'index'])->name('products');
            Route::get('/products/create', [VendorProductController::class, 'create'])->name('add_product');
            Route::post('/products', [VendorProductController::class, 'store'])->name('products.store');
            Route::get('/products/export', [VendorProductController::class, 'export'])->name('products.export');
            Route::get('/products/{product}/edit', [VendorProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [VendorProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [VendorProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/restore', [VendorProductController::class, 'restore'])->name('products.restore');
            Route::post('/products/{product}/toggle', [VendorProductController::class, 'toggleStatus'])->name('products.toggle');
            Route::post('/products/{product}/boost/pay', [VendorProductController::class, 'payBoost'])->name('products.boost.pay');
            Route::get('/products/{product}/boost/success', [VendorProductController::class, 'boostSuccess'])->name('products.boost.success');
            Route::get('/products/{product}/boost/cancel', [VendorProductController::class, 'boostCancel'])->name('products.boost.cancel');

            Route::get('/livraison', [VendorDeliverySettingsController::class, 'index'])->name('delivery.index');
            Route::get('/livraison/mode', [VendorDeliverySettingsController::class, 'mode'])->name('delivery.mode');
            Route::post('/livraison/mode', [VendorDeliverySettingsController::class, 'saveMode'])->name('delivery.mode.update');
            Route::get('/livraison/localisation', [VendorDeliverySettingsController::class, 'location'])->name('delivery.location');
            Route::post('/livraison/localisation/detecter', [VendorDeliverySettingsController::class, 'resolveLocation'])->middleware('throttle:30,1')->name('delivery.location.resolve');
            Route::get('/livraison/configuration', [VendorDeliverySettingsController::class, 'edit'])->name('delivery.edit');
            Route::post('/livraison/configuration', [VendorDeliverySettingsController::class, 'update'])->name('delivery.update');
            Route::post('/livraison/zones', [VendorDeliverySettingsController::class, 'storeZone'])->name('delivery.zones.store');
            Route::put('/livraison/zones/{zone}', [VendorDeliverySettingsController::class, 'updateZone'])->name('delivery.zones.update');
            Route::delete('/livraison/zones/{zone}', [VendorDeliverySettingsController::class, 'destroyZone'])->name('delivery.zones.destroy');

            Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders');
            Route::get('/orders/json', [VendorOrderController::class, 'ordersJson'])->name('orders.json');
            Route::get('/orders/{order}', [VendorOrderController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{order}/status', [VendorOrderController::class, 'updateStatus'])->name('orders.updateStatus');
            Route::post('/orders/{order}/validate', [VendorOrderController::class, 'updateStatus'])->name('orders.validate');
            Route::post('/orders/{order}/confirm-payment', [VendorOrderController::class, 'confirmPayment'])->name('orders.confirmPayment');
            Route::post('/orders/{order}/ship', [VendorOrderController::class, 'markShipped'])->name('orders.ship');
            Route::post('/orders/{order}/delivery-status', [VendorOrderController::class, 'updateDeliveryStatus'])->name('orders.deliveryStatus');
            Route::post('/orders/{order}/verify-otp', [VendorOrderController::class, 'verifyDeliveryOtp'])->middleware('throttle:10,1')->name('orders.verifyOtp');
            Route::post('/orders/{order}/driver-location', [VendorOrderController::class, 'updateDriverLocation'])->name('orders.driverLocation');

            Route::get('/payments', [VendorPaymentController::class, 'index'])->name('payments');
            Route::get('/payments/{payment}', [VendorPaymentController::class, 'show'])->name('payments.show');
            Route::post('/payments/{payment}/mark-completed', [VendorPaymentController::class, 'markCompleted'])->name('payments.markCompleted');


            Route::get('/payouts', [\App\Http\Controllers\VendorPayoutController::class, 'index'])->name('payouts.index');
            Route::get('/payouts/export', [\App\Http\Controllers\VendorPayoutController::class, 'exportCsv'])->name('payouts.export');
            Route::get('/payouts/{payout}', [\App\Http\Controllers\VendorPayoutController::class, 'show'])->name('payouts.show');
            Route::get('/payouts/{payout}/receipt', [SensitiveDocumentController::class, 'vendorPayout'])->name('private-documents.payout');
            Route::post('/payouts/{payout}/follow-up', [\App\Http\Controllers\VendorPayoutController::class, 'requestFollowUp'])->name('payouts.followUp');

            Route::get('/returns', [VendorReturnController::class, 'index'])->name('returns.index');
            Route::get('/returns/{return}/proof/{document?}', [SensitiveDocumentController::class, 'vendorReturn'])
                ->whereNumber('document')->name('private-documents.return');
            Route::post('/returns/{id}/accept', [VendorReturnController::class, 'accept'])->name('returns.accept');
            Route::post('/returns/{id}/reject', [VendorReturnController::class, 'reject'])->name('returns.reject');
            Route::post('/returns/{id}/refund', [VendorReturnController::class, 'refund'])->name('returns.refund');

            Route::get('/disputes', [VendorDisputeController::class, 'index'])->name('disputes.index');
            Route::post('/disputes/{id}/respond', [VendorDisputeController::class, 'respond'])->name('disputes.respond');
            Route::post('/disputes/{id}/escalate', [VendorDisputeController::class, 'escalate'])->name('disputes.escalate');

            Route::get('/reviews', [VendorReviewController::class, 'index'])->name('reviews.index');
            Route::post('/reviews/{review}/reply', [VendorReviewController::class, 'reply'])->name('reviews.reply');

        });

    // Ancien espace /daniel : conservé seulement en redirection pour éviter les doublons.
    // L'espace vendeur officiel est maintenant /vendeur avec noms de routes vendor.*.
    Route::prefix('daniel')->name('daniel.')->group(function () {
        Route::get('/dashboard', fn () => redirect()->route('vendor.dashboard'))->name('dashboard');
        Route::get('/vendeur-actes', fn () => redirect()->route('vendor.vendeur-actes'))->name('vendeur-actes');
        Route::get('/cgu', fn () => redirect()->route('vendor.cgu'))->name('cgu');
        Route::get('/shop-status', fn () => redirect()->route('vendor.shop-status'))->name('shop-status');
        Route::get('/shop-profile', fn () => redirect()->route('vendor.shop.profile'))->name('shop.profile');
        Route::get('/shop-profile/edit', fn () => redirect()->route('vendor.shop.edit'))->name('shop.edit');
        Route::get('/payment-method', fn () => redirect()->route('vendor.payment-method'))->name('payment-method');
        Route::get('/shop-documents', fn () => redirect()->route('vendor.shop.documents'))->name('shop.documents');
        Route::get('/products', fn () => redirect()->route('vendor.products'))->name('products');
        Route::get('/products/create', fn () => redirect()->route('vendor.add_product'))->name('add_product');
        Route::get('/orders', fn () => redirect()->route('vendor.orders'))->name('orders');
        Route::get('/payments', fn () => redirect()->route('vendor.payments'))->name('payments');
        Route::get('/payouts', fn () => redirect()->route('vendor.payouts.index'))->name('payouts.index');
        Route::get('/returns', fn () => redirect()->route('vendor.returns.index'))->name('returns.index');
        Route::get('/disputes', fn () => redirect()->route('vendor.disputes.index'))->name('disputes.index');
        Route::get('/reviews', fn () => redirect()->route('vendor.reviews.index'))->name('reviews.index');
        Route::get('/orders/{order}', fn ($order) => redirect()->route('vendor.orders.show', $order))->name('orders.show');
        Route::get('/products/{product}/edit', fn ($product) => redirect()->route('vendor.products.edit', $product))->name('products.edit');
    });


    Route::get('/demande-devis', function () { return view('devis'); })->name('devis.form');
    Route::post('/demande-devis', [DevisController::class, 'store'])->name('devis.store');

    Route::get('/open-shop/success', [ShopController::class, 'success'])->name('shop.open.success');
    Route::get('/shops/create', [ShopController::class, 'create'])->name('shops.create');
    Route::post('/shops', [ShopController::class, 'store'])->name('shops.store');
    Route::get('/shops/{shop}/edit', [ShopController::class, 'edit'])->name('shops.edit');
    Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
});

/*
|--------------------------------------------------------------------------
| ESPACE SUPPORT OVANIE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'internal', 'staff:support'])->prefix('support')->name('support.')->group(function () {
    Route::get('/', SupportDashboardController::class)->name('dashboard');
    Route::get('/tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [SupportTicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/messages', [SupportTicketController::class, 'reply'])->name('tickets.reply');
    Route::get('/clients', [SupportRecordController::class, 'clients'])->name('clients.index');
    Route::get('/vendeurs', [SupportRecordController::class, 'vendors'])->name('vendors.index');
    Route::get('/commandes', [SupportRecordController::class, 'orders'])->name('orders.index');
    Route::get('/paiements', [SupportRecordController::class, 'payments'])->name('payments.index');
    Route::get('/livraisons', [SupportRecordController::class, 'deliveries'])->name('deliveries.index');
    Route::get('/incidents', [SupportRecordController::class, 'incidents'])->name('incidents.index');
    Route::get('/retours', [SupportRecordController::class, 'returns'])->name('returns.index');
    Route::get('/litiges', [SupportRecordController::class, 'disputes'])->name('disputes.index');
    Route::get('/messages-entrants', [SupportRecordController::class, 'messages'])->name('messages.index');

    Route::get('/centre-ia/agents', [SupportAiAgentController::class, 'index'])->name('ai-agents.index');
    Route::put('/centre-ia/agents/{agent}', [SupportAiAgentController::class, 'update'])->name('ai-agents.update');

    Route::get('/conversations', [SupportConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [SupportConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [SupportConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/reponse-humaine', [SupportConversationController::class, 'reply'])->name('conversations.reply');
    Route::post('/conversations/{conversation}/reponse-ia', [SupportConversationController::class, 'aiReply'])->name('conversations.ai-reply');
    Route::post('/conversations/{conversation}/prendre-en-charge', [SupportConversationController::class, 'takeover'])->name('conversations.takeover');
    Route::post('/conversations/{conversation}/transferer', [SupportConversationController::class, 'handoff'])->name('conversations.handoff');
    Route::post('/conversations/{conversation}/creer-ticket', [SupportConversationController::class, 'createTicket'])->name('conversations.create-ticket');
    Route::post('/conversations/{conversation}/resoudre', [SupportConversationController::class, 'resolve'])->name('conversations.resolve');

    Route::get('/appels', [SupportCallController::class, 'index'])->name('calls.index');
    Route::post('/appels/sortant', [SupportCallController::class, 'outbound'])->name('calls.outbound');
    Route::get('/appels/{call}', [SupportCallController::class, 'show'])->name('calls.show');
    Route::post('/appels/{call}/rappel', [SupportCallController::class, 'callback'])->name('calls.callback');
    Route::post('/appels/{call}/transferer', [SupportCallController::class, 'transfer'])->name('calls.transfer');
    Route::post('/rappels', [SupportCallController::class, 'storeCallback'])->name('callbacks.store');

    Route::get('/transferts-humains', [SupportHandoffController::class, 'index'])->name('handoffs.index');
    Route::post('/transferts-humains/{handoff}/accepter', [SupportHandoffController::class, 'accept'])->name('handoffs.accept');
    Route::post('/transferts-humains/{handoff}/assigner', [SupportHandoffController::class, 'assign'])->name('handoffs.assign');
    Route::post('/transferts-humains/{handoff}/resoudre', [SupportHandoffController::class, 'resolve'])->name('handoffs.resolve');

    Route::get('/demandes-rappel', [SupportCallbackController::class, 'index'])->name('callbacks.index');
    Route::put('/demandes-rappel/{callback}', [SupportCallbackController::class, 'update'])->name('callbacks.update');

    Route::get('/base-connaissances', [SupportKnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('/base-connaissances/create', [SupportKnowledgeController::class, 'create'])->name('knowledge.create');
    Route::post('/base-connaissances', [SupportKnowledgeController::class, 'store'])->name('knowledge.store');
    Route::get('/base-connaissances/{article}/edit', [SupportKnowledgeController::class, 'edit'])->name('knowledge.edit');
    Route::put('/base-connaissances/{article}', [SupportKnowledgeController::class, 'update'])->name('knowledge.update');
    Route::delete('/base-connaissances/{article}', [SupportKnowledgeController::class, 'destroy'])->name('knowledge.destroy');

    Route::get('/supervision-ia', [SupportAiAuditController::class, 'index'])->name('ai-audit.index');
});

/*
|--------------------------------------------------------------------------
| ESPACE COMMERCIAL OVANIE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'internal', 'staff:commercial'])->prefix('commercial')->name('commercial.')->group(function () {
    Route::get('/', CommercialDashboardController::class)->name('dashboard');
    Route::get('/opportunites', [CommercialLeadController::class, 'index'])->name('leads.index');
    Route::get('/opportunites/create', [CommercialLeadController::class, 'create'])->name('leads.create');
    Route::post('/opportunites', [CommercialLeadController::class, 'store'])->name('leads.store');
    Route::get('/opportunites/{lead}', [CommercialLeadController::class, 'show'])->name('leads.show');
    Route::put('/opportunites/{lead}', [CommercialLeadController::class, 'update'])->name('leads.update');
    Route::post('/opportunites/{lead}/activites', [CommercialLeadController::class, 'activity'])->name('leads.activity');
    Route::get('/transferts-support', [DepartmentHandoffController::class, 'commercial'])->name('handoffs.index');
    Route::post('/transferts-support/{handoff}/prendre', [DepartmentHandoffController::class, 'claim'])->name('handoffs.claim');
    Route::post('/transferts-support/{handoff}/resoudre', [DepartmentHandoffController::class, 'resolve'])->name('handoffs.resolve');
    Route::get('/clients', [CommercialRecordController::class, 'clients'])->name('clients.index');
    Route::get('/clients/create', [CommercialAccountController::class, 'createClient'])->name('clients.create');
    Route::post('/clients', [CommercialAccountController::class, 'storeClient'])->name('clients.store');

    Route::get('/prospection', [CommercialProspectingController::class, 'areas'])->name('prospecting.areas');
    Route::post('/prospection/missions/{mission}/quartiers/{missionQuarter}/commencer', [CommercialProspectingController::class, 'startQuarter'])->name('prospecting.quarters.start');
    Route::post('/prospection/missions/{mission}/quartiers/{missionQuarter}/terminer', [CommercialProspectingController::class, 'finishQuarter'])->name('prospecting.quarters.finish');
    Route::get('/prospection/vendeurs', [CommercialProspectingController::class, 'prospects'])->name('prospecting.prospects.index');
    Route::get('/prospection/vendeurs/create', [CommercialProspectingController::class, 'create'])->name('prospecting.prospects.create');
    Route::post('/prospection/vendeurs', [CommercialProspectingController::class, 'store'])->name('prospecting.prospects.store');
    Route::get('/prospection/vendeurs/{prospect}', [CommercialProspectingController::class, 'show'])->name('prospecting.prospects.show');
    Route::put('/prospection/vendeurs/{prospect}', [CommercialProspectingController::class, 'update'])->name('prospecting.prospects.update');
    Route::post('/prospection/vendeurs/{prospect}/visites', [CommercialProspectingController::class, 'visit'])->name('prospecting.prospects.visit');

    Route::get('/vendeurs', [CommercialRecordController::class, 'vendors'])->name('vendors.index');
    Route::get('/vendeurs/create', [CommercialAccountController::class, 'createVendor'])->name('vendors.create');
    Route::post('/vendeurs', [CommercialAccountController::class, 'storeVendor'])->name('vendors.store');

    // Compatibilité avec les anciens liens déjà présents dans le projet.
    Route::get('/comptes/create', [CommercialAccountController::class, 'create'])->name('accounts.create');
    Route::post('/comptes', [CommercialAccountController::class, 'store'])->name('accounts.store');
    Route::get('/vendeurs/{shop}/position', [CommercialShopController::class, 'editLocation'])->name('vendors.location.edit');
    Route::put('/vendeurs/{shop}/position', [CommercialShopController::class, 'updateLocation'])->name('vendors.location.update');
    Route::get('/produits', [CommercialRecordController::class, 'products'])->name('products.index');

    Route::get('/produits/ajout-rapide', [CommercialQuickProductController::class, 'create'])->name('products.quick.create');
    Route::get('/produits/ajout-rapide/recherche', [CommercialQuickProductController::class, 'search'])->name('products.quick.search');
    Route::post('/produits/ajout-rapide', [CommercialQuickProductController::class, 'store'])->name('products.quick.store');
    Route::post('/produits/ajout-rapide/produit-absent', [CommercialQuickProductController::class, 'storeUnknown'])->name('products.quick.unknown.store');

    Route::get('/produits/import', [CommercialQuickProductController::class, 'importForm'])->name('products.import.form');
    Route::get('/produits/import/modele', [CommercialQuickProductController::class, 'downloadTemplate'])->name('products.import.template');
    Route::post('/produits/import', [CommercialQuickProductController::class, 'import'])->name('products.import.store');

    Route::get('/produits/create', [CommercialProductController::class, 'create'])->name('products.create');
    Route::post('/produits', [CommercialProductController::class, 'store'])->name('products.store');
    Route::get('/produits/{product}/edit', [CommercialProductController::class, 'edit'])->name('products.edit');
    Route::match(['put', 'patch'], '/produits/{product}', [CommercialProductController::class, 'update'])->name('products.update');
    Route::get('/commandes', [CommercialRecordController::class, 'orders'])->name('orders.index');
    Route::get('/demandes-business', [CommercialRecordController::class, 'businessRequests'])->name('business.index');
    Route::get('/devis-appels-offres', [CommercialRecordController::class, 'quotes'])->name('quotes.index');
});

Route::get('/open-shop', [ShopController::class, 'openShop'])->name('open-shop');
Route::post('/open-shop/submit', [ShopController::class, 'store'])->name('shop.open.submit');
Route::post('/open-shop', [ShopController::class, 'store'])->name('shop.open.submit.legacy');

Route::prefix('open-shop/geo')
    ->middleware('throttle:60,1')
    ->name('open-shop.geo.')
    ->group(function () {
        Route::get('/communes', [GeoController::class, 'abidjanCommunes'])->name('communes');
        Route::get('/quarters', [GeoController::class, 'abidjanQuarters'])->name('quarters');
        Route::get('/landmarks', [GeoController::class, 'abidjanLandmarks'])->name('landmarks');
        Route::get('/reverse', [GeoController::class, 'reverseShopLocation'])->name('reverse');
        Route::post('/resolve', [GeoController::class, 'resolveShopAddress'])->name('resolve');
    });

Route::post('/paydunya/webhook', [\App\Http\Controllers\PaymentController::class, 'paydunyaWebhook'])
    ->middleware('throttle:30,1')
    ->name('paydunya.webhook');

// Callback serveur-à-serveur des déboursements vendeurs PayDunya.
// Le hash SHA-512 de la MasterKey est vérifié dans le contrôleur/service.
Route::post('/paydunya/payout/callback', PayDunyaPayoutCallbackController::class)
    ->middleware('throttle:60,1,paydunya-payout-callback:')
    ->name('paydunya.payout.callback');

// V75 : retour prestataire PUBLIC. Le token PayDunya est vérifié côté serveur ;
// aucune session web n'est requise, ce qui permet aussi le retour depuis l'APK.
Route::get('/paydunya/return', [PayDunyaReturnController::class, 'success'])
    ->middleware('throttle:60,1,paydunya-return:')
    ->name('paydunya.return');
Route::get('/paydunya/cancel', [PayDunyaReturnController::class, 'cancel'])
    ->middleware('throttle:60,1,paydunya-cancel:')
    ->name('paydunya.cancel');

// V77 : retour spécifique aux paiements ouverts depuis l'application Android.
// Cette route reste publique comme le retour Web, mais la page finale propose
// un deep-link OVANIE qui rouvre directement l'APK installé.
Route::get('/paydunya/mobile/return', [PayDunyaReturnController::class, 'successMobile'])
    ->middleware('throttle:60,1,paydunya-mobile-return:')
    ->name('paydunya.mobile.return');
Route::get('/paydunya/mobile/cancel', [PayDunyaReturnController::class, 'cancelMobile'])
    ->middleware('throttle:60,1,paydunya-mobile-cancel:')
    ->name('paydunya.mobile.cancel');

Route::get('/my-shop', function () {
    $shop = \App\Models\Shop::where('user_id', auth()->id())->first();
    if (!$shop) { return response()->json(null, 404); }
    return response()->json($shop);
})->middleware('auth');


Route::prefix('cart')->name('cart.')->middleware('auth')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add/{product}', [CartController::class, 'add'])->name('add');
    Route::post('/update/{product}', [CartController::class, 'updateQuantity'])->name('update');

    Route::post('/remove/{product}', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
    Route::get('/cart-data', [CartController::class, 'apiCart'])->name('apiCart');
});

Route::get('/cart/count', function () {
    if (!auth()->check()) {
        return response()->json(['count' => 0]);
    }

    $cart = auth()->user()->cart;
    $count = $cart ? $cart->items()->sum('quantity') : 0;

    return response()->json(['count' => $count]);
})->middleware('auth')->name('cart.count');

/*
|--------------------------------------------------------------------------
| CHECKOUT
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/selection', [CheckoutController::class, 'selection'])->name('checkout.selection');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/paiement/{order}', [CheckoutController::class, 'showOnlinePayment'])->name('checkout.payment.show');
    Route::post('/checkout/paiement/{order}', [CheckoutController::class, 'startOnlinePayment'])->name('checkout.payment.start');
    Route::post('/checkout/paiement/{order}/abandonner', [CheckoutController::class, 'abandonOnlinePayment'])
        ->name('checkout.payment.abandon');
    Route::post('/checkout/paiement/{order}/simuler', [PaymentController::class, 'simulatePaydunyaSuccess'])
        ->name('checkout.payment.simulate');
    Route::get('/receipt/{id}', [CheckoutController::class, 'showReceipt'])->name('receipt.show');
    Route::get('/receipt/{id}/pdf', [CheckoutController::class, 'downloadReceipt'])->name('receipt.pdf');
    Route::post('/receipt/{order}/pay', [CheckoutController::class, 'payItem'])->name('receipt.payItem');
});
Route::post('/checkout/delivery-fee-preview', [CheckoutController::class, 'deliveryFeePreview'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('checkout.deliveryFeePreview');
Route::get('/paydunya/order/{order}/success', [CheckoutController::class, 'paydunyaSuccess'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('paydunya.order.success');

Route::get('/paydunya/order/{order}/cancel', [CheckoutController::class, 'paydunyaCancel'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('paydunya.order.cancel');
Route::get('/order/success/{order}', [CheckoutController::class, 'success'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('order.success');

Route::get('/mes-commandes', [OrderController::class, 'index'])
    ->middleware('auth')
    ->name('orders.index');

/*
|--------------------------------------------------------------------------
| SOCIAL LOGIN
|--------------------------------------------------------------------------
*/
Route::get('/login/google', [SocialController::class, 'redirectToGoogle'])->middleware('throttle:10,1')->name('login.google');
Route::get('/login/google/callback', [SocialController::class, 'handleGoogleCallback'])->middleware('throttle:10,1');
Route::get('/login/facebook', [SocialController::class, 'redirectToFacebook'])->middleware('throttle:10,1')->name('login.facebook');
Route::get('/login/facebook/callback', [SocialController::class, 'handleFacebookCallback'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| ROUTES JSON POUR JS
|--------------------------------------------------------------------------
*/
Route::get('/products/json', [ProductController::class, 'json']);

/*
|--------------------------------------------------------------------------
| CLIENT
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientAccountController::class, 'dashboard'])->name('dashboard');
    Route::view('/cgu', 'client.cgu')->name('cgu');

    Route::get('/favorites', [ClientAccountController::class, 'favorites'])->name('favorites');
    Route::match(['post', 'delete'], '/favorites/{product}', [ClientAccountController::class, 'toggleFavorite'])->name('favorites.toggle');

    Route::get('/orders', [ClientAccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [ClientAccountController::class, 'orderShow'])->name('orders.show');
    Route::get('/orders/{order}/tracking', [ClientAccountController::class, 'orderTracking'])->name('orders.tracking');
    Route::get('/orders/{order}/tracking-data', [ApiShipmentTrackingController::class, 'order'])->name('orders.tracking.data');
    Route::post('/orders/{order}/reorder', [ClientAccountController::class, 'reorder'])->name('orders.reorder');

    Route::get('/retours-reclamations', [ClientAccountController::class, 'returns'])->name('returns');
    Route::post('/retours-reclamations', [ClientAccountController::class, 'storeReturn'])->name('returns.store');
    Route::get('/bons-cadeaux', [ClientGiftCardController::class, 'index'])->name('vouchers');
    Route::get('/bons-cadeaux/{giftCard}', [ClientGiftCardController::class, 'show'])->name('gift-cards.show');
    Route::get('/bons-cadeaux/{giftCard}/recharger', [ClientGiftCardController::class, 'rechargeForm'])->name('gift-cards.recharge.form');
    Route::post('/bons-cadeaux/{giftCard}/recharger', [ClientGiftCardController::class, 'recharge'])->name('gift-cards.recharge');

    Route::get('/orders/{id}/resume', [ClientDashboardController::class, 'resumeOrder'])->name('orders.resume');
    Route::patch('/orders/{id}/cancel', [ClientDashboardController::class, 'cancelOrder'])->name('orders.cancel');
    Route::delete('/orders/{id}', [ClientDashboardController::class, 'deleteOrder'])->name('orders.delete');

    Route::get('/addresses', [ClientAccountController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [ClientAccountController::class, 'storeAddress'])->name('addresses.store');
    Route::patch('/addresses/{address}', [ClientAccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('/addresses/{address}', [ClientAccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::post('/addresses/{address}/default', [ClientAccountController::class, 'defaultAddress'])->name('addresses.default');

    Route::get('/payments', [ClientAccountController::class, 'payments'])->name('payments');
    Route::post('/payments', [ClientAccountController::class, 'storePaymentMethod'])->name('payments.store');
    Route::delete('/payments/{method}', [ClientAccountController::class, 'destroyPaymentMethod'])->name('payments.destroy');
    Route::post('/payments/{method}/default', [ClientAccountController::class, 'defaultPaymentMethod'])->name('payments.default');

    Route::get('/notifications', [ClientAccountController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [ClientAccountController::class, 'readAllNotifications'])->name('notifications.readAll');
    Route::post('/notifications/preferences', [ClientAccountController::class, 'updateNotificationPreferences'])->name('notifications.preferences');
    Route::post('/notifications/{notification}/read', [ClientAccountController::class, 'readNotification'])->name('notifications.read');

    Route::get('/settings', [ClientAccountController::class, 'settings'])->name('settings');
    Route::patch('/settings', [ClientAccountController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/deletion-code', [ClientAccountController::class, 'requestAccountDeletionCode'])
        ->middleware('throttle:3,10')
        ->name('settings.deletion-code');
    Route::delete('/settings', [ClientAccountController::class, 'deleteAccount'])
        ->middleware('throttle:5,10')
        ->name('settings.delete');

    Route::get('/orders/{order}/reception-form', [ClientOrderReceptionController::class, 'form'])
        ->name('orders.reception.form');

    Route::post('/orders/{order}/reception-form', [ClientOrderReceptionController::class, 'save'])
        ->name('orders.reception.save');

    Route::post(
        '/orders/{order}/reception/pay-line/{line}',
        [ClientOrderReceptionController::class, 'payLine']
    )
        ->name('orders.reception.payLine');

    Route::get(
        '/orders/{order}/reception/pay-line/{line}/success/{payment}',
        [ClientOrderReceptionController::class, 'payLineSuccess']
    )
        ->name('orders.reception.payLine.success');

    Route::post(
        '/orders/{order}/reception/pay-order',
        [ClientOrderReceptionController::class, 'payOrder']
    )
        ->name('orders.reception.payOrder');

    Route::get(
        '/orders/{order}/reception/pay-order/success/{payment}',
        [ClientOrderReceptionController::class, 'payOrderSuccess']
    )
        ->name('orders.reception.payOrder.success');
});

// L'ancien portail /livreur/{token} utilisait un téléphone encodé en base64.
// Il est volontairement retiré : toutes les missions passent désormais par
// l'espace authentifié /espace-livreur et les routes de routes/driver.php.

// Ancienne URL conservée uniquement comme redirection : il n'existe plus
// de formulaire ni de session Logistique indépendante.
Route::get('/logistique/login', fn () => redirect()->route('admin.adminlogin'))
    ->name('logistics.legacy-login');

Route::middleware(['auth:admin', 'internal', 'staff:logistique', \App\Http\Middleware\NoHtmlCache::class])->prefix('logistique')->name('logistics.')->group(function () {
    Route::get('/documents/retours/{return}', [SensitiveDocumentController::class, 'logisticsReturn'])->name('private-documents.return');
    Route::get('/documents/retours/{return}/decision/{document}', [SensitiveDocumentController::class, 'logisticsReturnDecisionProof'])->whereNumber('document')->name('private-documents.return-decision');
    Route::get('/documents/incidents/{incident}', [SensitiveDocumentController::class, 'logisticsIncident'])->name('private-documents.incident');
    Route::get('/documents/preuves/{proof}', [SensitiveDocumentController::class, 'logisticsProof'])->name('private-documents.proof');
    Route::get('/documents/order-items/{item}/{type}', [SensitiveDocumentController::class, 'adminOrderItem'])->name('private-documents.order-item');
    Route::get('/', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/live', [LogisticsController::class, 'dashboardLive'])->name('dashboard.live');
    Route::get('/expeditions', [LogisticsController::class, 'shipments'])->name('shipments');
    Route::get('/expeditions/export', [LogisticsController::class, 'exportShipments'])->name('shipments.export');
    Route::get('/affectations', [LogisticsController::class, 'assignments'])->name('assignments.index');
    Route::get('/expeditions/{item}/affectation-donnees', [LogisticsController::class, 'assignmentData'])->name('shipments.assignment-data');
    Route::get('/expeditions/{item}', [LogisticsController::class, 'shipmentDetails'])->name('shipments.details');
    Route::get('/expeditions/{item}/affecter', [LogisticsController::class, 'assignPage'])->name('shipments.assign-page');
    Route::patch('/expeditions/{item}/statut', [LogisticsController::class, 'updateShipmentStatus'])->name('shipments.status');
    Route::post('/expeditions/{item}/assigner', [LogisticsController::class, 'assignShipment'])->name('shipments.assign');
    Route::get('/incidents', [DeliveryIncidentController::class, 'index'])->name('incidents.index');
    Route::get('/expeditions/{item}/incidents/create', [DeliveryIncidentController::class, 'create'])->name('incidents.create');
    Route::post('/expeditions/{item}/incidents', [DeliveryIncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incidents/{incident}', [DeliveryIncidentController::class, 'show'])->name('incidents.show');
    Route::patch('/incidents/{incident}', [DeliveryIncidentController::class, 'update'])->name('incidents.update');
    Route::get('/transferts-support', [DepartmentHandoffController::class, 'logistics'])->name('handoffs.index');
    Route::get('/transferts-support/{handoff}', [DepartmentHandoffController::class, 'showLogistics'])->name('handoffs.show');
    Route::post('/transferts-support/{handoff}/prendre', [DepartmentHandoffController::class, 'claim'])->name('handoffs.claim');
    Route::patch('/transferts-support/{handoff}/statut', [DepartmentHandoffController::class, 'updateStatus'])->name('handoffs.status');
    Route::post('/transferts-support/{handoff}/messages', [DepartmentHandoffController::class, 'message'])->name('handoffs.messages');
    Route::get('/transferts-support/{handoff}/pieces-jointes/{message}/{index}', [DepartmentHandoffController::class, 'attachment'])->whereNumber('message')->whereNumber('index')->name('handoffs.attachments.download');
    Route::post('/transferts-support/{handoff}/resoudre', [DepartmentHandoffController::class, 'resolve'])->name('handoffs.resolve');
    Route::get('/suivi-livraisons', [LogisticsController::class, 'tracking'])->name('tracking');
    Route::get('/suivi-mission/{item?}', [LogisticsController::class, 'trackingMission'])->name('tracking.mission');
    // Liste des livreurs suivis (menu "Suivi des livreurs") : doit être déclarée
    // avant la route à paramètre {driver?} ci-dessous pour ne pas être capturée
    // par elle, et existait déjà côté vue/contrôleur sans jamais être routée.
    Route::get('/suivi-livreurs', [LogisticsController::class, 'trackingDrivers'])->name('tracking.drivers');
    Route::get('/suivi-livreur/{driver?}', [LogisticsController::class, 'trackingDriver'])->name('tracking.driver');
    Route::get('/api/suivi-livraisons', [LogisticsTrackingController::class, 'suiviLivraisons'])->name('tracking.api');
    Route::get('/tracking-data', [LogisticsTrackingController::class, 'data'])->name('tracking.data');
    Route::post('/routes/optimize', [LogisticsRouteController::class, 'optimize'])->name('routes.optimize');
    Route::post('/expeditions-geo/{shipment}/route', [LogisticsRouteController::class, 'calculateShipment'])->name('shipments.route');
    Route::get('/livraisons-en-cours', [LogisticsController::class, 'activeDeliveries'])->name('active-deliveries');
    Route::get('/livreurs', [\App\Http\Controllers\LogisticsDirectoryController::class, 'drivers'])->name('drivers');
    Route::get('/livreurs/fiche/{driver?}', [\App\Http\Controllers\LogisticsDirectoryController::class, 'driver'])->name('drivers.show');
    Route::get('/livreurs/{driver}/documents/{document}', [\App\Http\Controllers\LogisticsDirectoryController::class, 'document'])->name('drivers.document');
    Route::patch('/livreurs/{driver}', [\App\Http\Controllers\LogisticsDirectoryController::class, 'updateDriver'])->name('drivers.update');
    Route::post('/livreurs/{driver}/access', [\App\Http\Controllers\LogisticsDirectoryController::class, 'access'])->name('drivers.access');
    Route::delete('/livreurs/{driver}', [\App\Http\Controllers\LogisticsDirectoryController::class, 'destroyDriver'])->name('drivers.destroy');
    Route::get('/livreurs/{driver}/dossier', [\App\Http\Controllers\LogisticsDirectoryController::class, 'driverReview'])->name('drivers.review');
    Route::post('/livreurs/{driver}/dossier/valider', [\App\Http\Controllers\LogisticsDirectoryController::class, 'approveDriver'])->name('drivers.review.approve');
    Route::post('/livreurs/{driver}/dossier/refuser', [\App\Http\Controllers\LogisticsDirectoryController::class, 'rejectDriver'])->name('drivers.review.reject');
    Route::post('/livreurs/{driver}/dossier/corriger', [\App\Http\Controllers\LogisticsDirectoryController::class, 'requestCorrection'])->name('drivers.review.request-correction');
    Route::post('/retours/{return}/collecte', [\App\Http\Controllers\LogisticsDirectoryController::class, 'planReturn'])->name('returns.collect');
    Route::post('/retours/{return}/notes', [\App\Http\Controllers\LogisticsDirectoryController::class, 'noteReturn'])->name('returns.notes');
    Route::post('/livreurs', [\App\Http\Controllers\LogisticsDirectoryController::class, 'storeDriver'])->name('drivers.store');
    Route::get('/driver/tracking/{shipment}', [LogisticsController::class, 'driverTrackingPage'])->name('driver.tracking');
    Route::get('/retours', [\App\Http\Controllers\LogisticsDirectoryController::class, 'returns'])->name('returns');
    Route::get('/retours/{return}', [LogisticsController::class, 'returnDetails'])->name('returns.details');
    Route::patch('/retours/{return}/approuver', [LogisticsController::class, 'approveReturn'])->name('returns.approve');
    Route::patch('/retours/{return}/rejeter', [LogisticsController::class, 'rejectReturn'])->name('returns.reject');
    Route::patch('/retours/{return}/transmettre-decision', [LogisticsController::class, 'publishReturnDecision'])->name('returns.publish-decision');
    Route::get('/zones', [LogisticsTerritoryController::class, 'index'])->name('zones');
    Route::get('/zones/export', [LogisticsTerritoryController::class, 'export'])->name('zones.export');
    Route::post('/zones', [LogisticsTerritoryController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}', [LogisticsTerritoryController::class, 'show'])->name('zones.show');
    Route::patch('/zones/{zone}', [LogisticsTerritoryController::class, 'update'])->name('zones.update');
    Route::patch('/zones/{zone}/toggle', [LogisticsTerritoryController::class, 'toggle'])->name('zones.toggle');
    Route::delete('/zones/{zone}', [LogisticsTerritoryController::class, 'destroy'])->name('zones.destroy');
    Route::get('/tarification', [LogisticsOvaniePricingController::class, 'index'])->name('ovanie-pricing.index');
    Route::get('/tarification/vehicules', [LogisticsOvaniePricingController::class, 'vehicles'])->name('ovanie-pricing.vehicles');
    Route::get('/tarification/communes', [LogisticsOvaniePricingController::class, 'communes'])->name('ovanie-pricing.communes');
    Route::get('/tarification/communes/configurer', [LogisticsOvaniePricingController::class, 'configureCommunes'])->name('ovanie-pricing.communes.configure');
    Route::get('/tarification/supplements', [LogisticsOvaniePricingController::class, 'supplements'])->name('ovanie-pricing.supplements');
    Route::get('/tarification/simulateur', [LogisticsOvaniePricingController::class, 'simulator'])->name('ovanie-pricing.simulator');
    Route::get('/tarification/export', [LogisticsOvaniePricingController::class, 'export'])->name('ovanie-pricing.export');
    Route::post('/tarification/grilles', [LogisticsOvaniePricingController::class, 'storeGrid'])->name('ovanie-pricing.grids.store');
    Route::post('/tarification/tarifs-vehicules', [LogisticsOvaniePricingController::class, 'storeVehicleTariff'])->name('ovanie-pricing.vehicle-tariffs.store');
    Route::post('/tarification/matrices', [LogisticsOvaniePricingController::class, 'storeMatrix'])->name('ovanie-pricing.matrices.store');
    Route::post('/tarification/matrices/lot', [LogisticsOvaniePricingController::class, 'storeMatrixBulk'])->name('ovanie-pricing.matrices.bulk');
    Route::post('/tarification/supplements', [LogisticsOvaniePricingController::class, 'storeSupplement'])->name('ovanie-pricing.supplements.store');
    Route::post('/tarification/calculer', [LogisticsOvaniePricingController::class, 'calculate'])->name('ovanie-pricing.calculate');
    Route::get('/tarification-ovanie', fn () => redirect()->route('logistics.ovanie-pricing.index'))->name('ovanie-pricing.legacy');
    Route::get('/tournees', [\App\Http\Controllers\LogisticsTourController::class, 'index'])->name('tours.index');
    Route::get('/tournees/creer', [\App\Http\Controllers\LogisticsTourController::class, 'create'])->name('tours.create');
    Route::post('/tournees/previsualiser', [\App\Http\Controllers\LogisticsTourController::class, 'preview'])->name('tours.preview');
    Route::post('/tournees', [\App\Http\Controllers\LogisticsTourController::class, 'store'])->name('tours.store');
    Route::get('/tournees/{tour}', [\App\Http\Controllers\LogisticsTourController::class, 'show'])->name('tours.show');
    Route::post('/tournees/{tour}/demarrer', [\App\Http\Controllers\LogisticsTourController::class, 'start'])->name('tours.start');
    Route::post('/tournees/{tour}/reoptimiser', [\App\Http\Controllers\LogisticsTourController::class, 'reoptimize'])->name('tours.reoptimize');
    Route::post('/tournees/{tour}/ordre', [\App\Http\Controllers\LogisticsTourController::class, 'reorder'])->name('tours.reorder');
    Route::get('/flotte', [LogisticsFleetController::class, 'index'])->name('fleet');
    Route::get('/flotte/ajouter', [LogisticsFleetController::class, 'create'])->name('fleet.create');
    Route::post('/flotte', [LogisticsFleetController::class, 'store'])->name('fleet.store');
    Route::get('/flotte/{vehicle}', [LogisticsFleetController::class, 'show'])->name('fleet.show');

    Route::get('/partenaires', [LogisticsPartnerController::class, 'index'])->name('partners');
    Route::get('/partenaires/export', [LogisticsPartnerController::class, 'export'])->name('partners.export');
    Route::post('/partenaires', [LogisticsPartnerController::class, 'store'])->name('partners.store');
    Route::get('/partenaires/{partner}', [LogisticsPartnerController::class, 'show'])->name('partners.show');
    Route::post('/partenaires/{partner}/missions', [LogisticsPartnerController::class, 'assignMissions'])->name('partners.missions.assign');
    Route::post('/partenaires/{partner}/vehicules', [LogisticsPartnerController::class, 'storeVehicle'])->name('partners.vehicles.store');
    Route::post('/partenaires/{partner}/statut', [LogisticsPartnerController::class, 'toggleStatus'])->name('partners.status');
    Route::post('/partenaires/{partner}/notes', [LogisticsPartnerController::class, 'addNote'])->name('partners.notes');
    Route::get('/partenaires/{partner}/contrat', [LogisticsPartnerController::class, 'downloadContract'])->name('partners.contract');

    Route::get('/pilotage', [LogisticsPilotageController::class, 'reports'])->name('control');
    Route::get('/pilotage/rapports-performances', [LogisticsPilotageController::class, 'reports'])->name('control.reports');
    Route::get('/pilotage/notifications', [LogisticsPilotageController::class, 'notifications'])->name('control.notifications');
    Route::post('/pilotage/notifications/tout-lire', [LogisticsPilotageController::class, 'markAllRead'])->name('control.notifications.read-all');
    Route::get('/pilotage/parametres-logistiques', [LogisticsPilotageController::class, 'settings'])->name('control.settings');
    Route::post('/pilotage/parametres-logistiques', [LogisticsPilotageController::class, 'saveSettings'])->name('control.settings.save');
});

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION DU PERSONNEL INTERNE
|--------------------------------------------------------------------------
| Administration, Logistique, Support et Commercial utilisent le même guard
| et la même page de connexion. Le rôle détermine la redirection finale.
*/
Route::prefix('administration')->name('admin.')->group(function () {
    // Le GET reste accessible sans middleware guest:admin : le contrôleur regarde
    // uniquement le guard admin et redirige un membre déjà connecté vers SON espace.
    // Un compte public connecté peut donc toujours voir le portail interne sans être
    // mélangé à sa session client/vendeur.
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('adminlogin');

    Route::middleware('guest:admin')->group(function () {
        Route::post('login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('adminlogin.submit');
    });

    Route::middleware(['auth:admin', 'internal'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});

/* Compatibilité des anciens favoris : aucun formulaire n'est servi ici. */
Route::get('/admin/login', fn () => redirect()->route('admin.adminlogin'))
    ->name('admin.legacy-login');

/*
|--------------------------------------------------------------------------
| ESPACE ADMINISTRATION
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        // Inscription admin d'initialisation, protégée par son code spécial existant.
        Route::get('register', [AdminRegisterController::class, 'showRegisterForm'])->name('adminregister');
        Route::post('register', [AdminRegisterController::class, 'register'])->name('adminregister.submit');
        Route::post('register/gate', [AdminRegisterController::class, 'registerGate'])->name('adminregister.gate');
    });

    Route::middleware(['auth:admin', 'internal', 'isAdmin'])->group(function () {
        Route::get('documents/order-items/{item}/{type}', [SensitiveDocumentController::class, 'adminOrderItem'])->name('private-documents.order-item');
        Route::get('documents/reception/{item}/{side}', [SensitiveDocumentController::class, 'adminReception'])->name('private-documents.reception');
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/data', [AdminDashboardController::class, 'dashboardData'])->name('dashboard.data');

        // Cartes cadeaux / cartes rechargeables
        Route::get('gift-cards', [AdminGiftCardController::class, 'index'])->name('gift-cards.index');
        Route::patch('gift-cards/products/{giftCardProduct}', [AdminGiftCardController::class, 'updateProduct'])->name('gift-cards.products.update');
        Route::post('gift-cards/{giftCard}/block', [AdminGiftCardController::class, 'block'])->name('gift-cards.block');
        Route::post('gift-cards/{giftCard}/unblock', [AdminGiftCardController::class, 'unblock'])->name('gift-cards.unblock');


        Route::get('prospection-vendeurs', [AdminProspectingMissionController::class, 'index'])->name('prospecting-missions.index');
        Route::get('prospection-vendeurs/create', [AdminProspectingMissionController::class, 'create'])->name('prospecting-missions.create');
        Route::post('prospection-vendeurs', [AdminProspectingMissionController::class, 'store'])->name('prospecting-missions.store');
        Route::get('prospection-vendeurs/{mission}', [AdminProspectingMissionController::class, 'show'])->name('prospecting-missions.show');
        Route::put('prospection-vendeurs/{mission}', [AdminProspectingMissionController::class, 'update'])->name('prospecting-missions.update');
        Route::post('prospection-vendeurs/{mission}/cloturer', [AdminProspectingMissionController::class, 'complete'])->name('prospecting-missions.complete');

        Route::get('staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [AdminStaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::get('staff/login-logs', [AdminStaffController::class, 'loginLogs'])->name('staff.login-logs');
        Route::get('staff/{staff}/edit', [AdminStaffController::class, 'edit'])->name('staff.edit');
        Route::put('staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::patch('staff/{staff}/status', [AdminStaffController::class, 'updateStatus'])->name('staff.status');
        Route::patch('staff/{staff}/password', [AdminStaffController::class, 'resetPassword'])->name('staff.password');
        Route::delete('staff/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('products', [AdminProductController::class, 'index'])->name('products.index');

        Route::get('products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::get('products/{product}', [AdminProductController::class, 'show']);
        Route::put('products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/notify-vendor', [AdminProductController::class, 'notifyVendor']);

        Route::get('shops', [AdminShopController::class, 'index'])->name('shops.index');
        Route::get('shops/{shop}', [AdminShopController::class, 'show'])->name('shops.show');
        Route::get('shops/{shop}/download-dossier', [AdminShopController::class, 'downloadDossier'])
            ->name('shops.downloadDossier');
        Route::post('shops/{shop}/status', [AdminShopController::class, 'updateStatus'])->name('shops.updateStatus');
        Route::post('shops/{shop}/commercial-manager', [AdminShopController::class, 'updateCommercialManager'])->name('shops.updateCommercialManager');
        Route::delete('shops/{shop}', [AdminShopController::class, 'destroy'])->name('shops.destroy');

        Route::get('shops/{shop}/documents/{document}', [AdminShopDocumentController::class, 'show'])
            ->whereIn('document', [
                'identity-pdf',
                'identity-front',
                'identity-back',
                'selfie',
                'rccm',
                'tax',
            ])
            ->name('shops.documents.show');

        Route::get('shops/{shop}/documents/{document}/download', [AdminShopDocumentController::class, 'download'])
            ->whereIn('document', [
                'identity-pdf',
                'identity-front',
                'identity-back',
                'selfie',
                'rccm',
                'tax',
            ])
            ->name('shops.documents.download');


        Route::get('payment-verifications', [AdminVendorPaymentVerificationController::class, 'index'])
            ->name('payment-verifications.index');

        Route::post('payment-verifications/{verification}/approve', [AdminVendorPaymentVerificationController::class, 'approve'])
            ->name('payment-verifications.approve');

        Route::post('payment-verifications/{verification}/reject', [AdminVendorPaymentVerificationController::class, 'reject'])
            ->name('payment-verifications.reject');

        Route::get('submissions', [SubmissionsController::class, 'indexAll'])->name('submissions.indexAll');
        Route::get('submissions/{type}/{id}', [SubmissionsController::class, 'show'])->name('submissions.show');
        Route::patch('submissions/{type}/{id}/validate-commission', [SubmissionsController::class, 'validateCommission'])->name('submissions.validateCommission');
        Route::delete('submissions/{type}/{id}', [SubmissionsController::class, 'destroy'])->name('submissions.destroy');

        Route::get('/sms', [AdminSmsController::class, 'index'])->name('sms.index');

        Route::patch('categories/{category}/status', [AdminCategoryController::class, 'updateStatus'])
            ->name('categories.status');
        Route::resource('categories', AdminCategoryController::class)
            ->except(['show']);
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/payment-proof', [AdminOrderController::class, 'paymentProof'])
            ->name('orders.payment-proof');
        Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::post('/orders/{order}/confirm-delivery', [AdminOrderController::class, 'confirmDelivery'])
            ->name('orders.confirmDelivery');
        Route::get('/orders/{order}/reception-form', [AdminOrderReceptionController::class, 'show'])
            ->name('orders.reception.show');

        Route::get('/orders/{order}/reception-pdf', [AdminOrderReceptionController::class, 'downloadPdf'])
            ->name('orders.reception.pdf');


        Route::get('messages', [SubmissionController::class, 'index'])->name('messages.index');
        Route::get('users', fn() => view('admin.users.index'))->name('users.index');

        Route::get('/commissions', [\App\Http\Controllers\Admin\AdminCommissionController::class, 'index'])
            ->name('commissions.index');
        Route::get('/commissions/data', [\App\Http\Controllers\Api\AdminCommissionController::class, 'index'])
            ->name('commissions.data');

        require base_path('routes/admin_promotions.php');

        Route::get('clients', [AdminClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{id}', [AdminClientController::class, 'show'])->name('clients.show');
        Route::get('clients/{id}/edit', [AdminClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{id}', [AdminClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{id}', [AdminClientController::class, 'destroy'])->name('clients.destroy');
        Route::get('/payouts/export', [\App\Http\Controllers\Admin\VendorPayoutExportController::class, 'export'])
            ->name('payouts.export');
        Route::post('/payouts/{payout}/mark-paid', [\App\Http\Controllers\Admin\VendorPayoutController::class, 'markPaid'])
            ->name('payouts.markPaid');
        Route::post('/payouts/{payout}/approve', [\App\Http\Controllers\Admin\VendorPayoutController::class, 'approve'])
            ->name('payouts.approve');
        Route::post('/payouts/{payout}/processing', [\App\Http\Controllers\Admin\VendorPayoutController::class, 'markProcessing'])
            ->name('payouts.processing');
        Route::post('/payouts/{payout}/failed', [\App\Http\Controllers\Admin\VendorPayoutController::class, 'markFailed'])
            ->name('payouts.failed');
        Route::get('/payouts', [VendorPayoutController::class, 'index'])
            ->name('payouts.index');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/update', [SettingsController::class, 'update'])->name('settings.update');

        Route::resource('banners', BannerController::class);
        Route::resource('home-ads', HomeAdController::class)->except(['show']);
        Route::resource('delivery-services', DeliveryServiceController::class)->except(['show']);
        Route::get('logistics/distance-matrix', [DeliveryDistanceMatrixController::class, 'index'])
            ->name('logistics.distance-matrix.index');
        Route::post('logistics/distance-matrix', [DeliveryDistanceMatrixController::class, 'store'])
            ->name('logistics.distance-matrix.store');
        Route::put('logistics/distance-matrix/{distance}', [DeliveryDistanceMatrixController::class, 'update'])
            ->name('logistics.distance-matrix.update');
        Route::resource('master-products', MasterProductController::class)->except(['show', 'destroy']);
        Route::post('master-products/{masterProduct}/attach-products', [MasterProductController::class, 'attachProducts'])
            ->name('master-products.attach-products');
        Route::get('logistics/cart-optimizations', [CartFulfillmentOptimizationController::class, 'index'])
            ->name('logistics.cart-optimizations.index');
        Route::get('logistics/cart-optimizations/{optimization}', [CartFulfillmentOptimizationController::class, 'show'])
            ->name('logistics.cart-optimizations.show');

        Route::post('home-ads/reorder', [HomeAdController::class, 'reorder'])
            ->name('home-ads.reorder');
    });
});

Route::post('/business/pay', [BusinessController::class, 'pay'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('business.pay');

Route::get('/business/pay/success', [BusinessController::class, 'paySuccess'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('business.pay.success');

Route::get('/business/pay/cancel', [BusinessController::class, 'payCancel'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('business.pay.cancel');

Route::post('/banner-click/{id}', [BannerController::class, 'click']);
Route::post('/suggestion', [PublicSubmissionController::class, 'store'])->name('suggestion.store');

Route::prefix('geo')->middleware('throttle:120,1')->group(function () {
    Route::get('/search', [GeoController::class, 'search'])->name('geo.search');
    Route::get('/reverse', [GeoController::class, 'reverse'])->name('geo.reverse');
    Route::get('/abidjan/communes', [GeoController::class, 'abidjanCommunes'])->name('geo.abidjan.communes');
    Route::get('/abidjan/quartiers', [GeoController::class, 'abidjanQuarters'])->name('geo.abidjan.quarters');
    Route::post('/route', [GeoController::class, 'route'])->name('geo.route');
    Route::post('/resolve-shop-address', [GeoController::class, 'resolveShopAddress'])->name('geo.resolve-shop-address');
});

Route::get('/wave/callback', [WaveController::class, 'callback'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('wave.callback');

/*
|--------------------------------------------------------------------------
| TÉLÉCHARGEMENT INSTALLEUR ADMIN OVANIE
|--------------------------------------------------------------------------
*/
Route::get('/telecharger-admin', function () {
    $file = public_path('Installer_Admin_Ovanie.exe');
    if (!file_exists($file)) {
        abort(404, 'Fichier introuvable.');
    }
    return response()->download($file, 'Installer_Admin_Ovanie.exe', [
        'Content-Type' => 'application/octet-stream',
    ]);
})->middleware(['auth:admin', 'throttle:10,1'])->name('download.admin');
