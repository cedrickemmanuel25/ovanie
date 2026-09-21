<?php

use App\Http\Controllers\Api\Auth\MobileAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OVANIE MOBILE AUTH V46
|--------------------------------------------------------------------------
| Ce fichier est chargé depuis routes/api.php.
| Comme routes/api.php possède déjà le préfixe global /api de Laravel,
| les URL finales sont :
|
| POST /api/mobile/v1/auth/login
| POST /api/mobile/v1/auth/register
| GET  /api/mobile/v1/auth/me
| POST /api/mobile/v1/auth/logout
*/
Route::prefix('mobile/v1/auth')->group(function () {
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/login', [MobileAuthController::class, 'login']);
        Route::post('/register', [MobileAuthController::class, 'register']);
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
    });
});


/*
|--------------------------------------------------------------------------
| Compatibilité APK OVANIE V45 et antérieurs
|--------------------------------------------------------------------------
| Ces alias permettent à un APK déjà installé qui appelle encore /api/auth/*
| de se reconnecter immédiatement. La V46 Flutter utilise en priorité les
| routes versionnées /api/mobile/v1/auth/*.
*/
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/login', [MobileAuthController::class, 'login']);
        Route::post('/register', [MobileAuthController::class, 'register']);
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
    });
});
