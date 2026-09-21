<?php

use App\Http\Controllers\Driver\DriverAccessController;
use App\Http\Controllers\Driver\DriverAuthController;
use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Driver\DriverMissionController;
use App\Http\Controllers\Driver\DriverProfileController;
use App\Http\Controllers\SellerDriverTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mission mobile du chauffeur d'un vendeur
|--------------------------------------------------------------------------
|
| L'identifiant public et le jeton aléatoire sont tous les deux obligatoires.
| Ces routes ne créent jamais de données de démonstration : elles écrivent les
| positions et les étapes réelles dans les tables de suivi vendeur.
|
*/
Route::prefix('mission-vendeur/{publicId}/{token}')
    ->where([
        'publicId' => '[A-Za-z0-9\-]{20,80}',
        'token' => '[A-Za-z0-9]{40,160}',
    ])
    ->middleware('throttle:120,1')
    ->name('seller-driver.')
    ->group(function () {
        Route::get('/', [SellerDriverTrackingController::class, 'show'])->name('mission');
        Route::post('/accepter', [SellerDriverTrackingController::class, 'accept'])->name('accept');
        Route::post('/demarrer', [SellerDriverTrackingController::class, 'start'])->name('start');
        Route::post('/position', [SellerDriverTrackingController::class, 'location'])->name('location');
        Route::post('/gps-indisponible', [SellerDriverTrackingController::class, 'gpsUnavailable'])->name('gps-unavailable');
        Route::post('/statut', [SellerDriverTrackingController::class, 'manualStatus'])->name('status');
        Route::post('/incident', [SellerDriverTrackingController::class, 'incident'])->name('incident');
        Route::post('/confirmer-otp', [SellerDriverTrackingController::class, 'verifyOtp'])->name('verify-otp');
    });

/*
|--------------------------------------------------------------------------
| Portail des livreurs OVANIE Logistics
|--------------------------------------------------------------------------
*/
Route::prefix('espace-livreur')->name('driver.')->group(function () {
    Route::middleware('guest:driver')->group(function () {
        Route::get('/connexion', [DriverAuthController::class, 'showLogin'])->name('login');
        Route::post('/connexion', [DriverAuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.submit');
    });

    Route::middleware('auth:driver')->group(function () {
        Route::get('/', [DriverDashboardController::class, 'index'])->name('dashboard');
        Route::post('/deconnexion', [DriverAuthController::class, 'logout'])->name('logout');
        Route::post('/notifications/{notification}/lire', [DriverDashboardController::class, 'readNotification'])
            ->name('notifications.read');

        Route::get('/missions', [DriverMissionController::class, 'index'])->name('missions.index');
        Route::get('/missions/{missionNumber}', [DriverMissionController::class, 'show'])->name('missions.show');
        Route::post('/missions/{missionNumber}/accepter', [DriverMissionController::class, 'accept'])->name('missions.accept');
        Route::post('/missions/{missionNumber}/refuser', [DriverMissionController::class, 'reject'])->name('missions.reject');
        Route::post('/missions/{missionNumber}/demarrer', [DriverMissionController::class, 'start'])->name('missions.start');
        Route::post('/missions/{missionNumber}/etape', [DriverMissionController::class, 'stage'])->name('missions.stage');
        Route::post('/missions/{missionNumber}/position', [DriverMissionController::class, 'location'])
            ->middleware('throttle:240,1')
            ->name('missions.location');
        Route::post('/missions/{missionNumber}/gps-indisponible', [DriverMissionController::class, 'gpsUnavailable'])
            ->name('missions.gps-unavailable');
        Route::post('/missions/{missionNumber}/incident', [DriverMissionController::class, 'incident'])->name('missions.incident');
        Route::post('/missions/{missionNumber}/confirmer-otp', [DriverMissionController::class, 'verifyOtp'])
            ->name('missions.verify-otp');

        Route::get('/profil', [DriverProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil', [DriverProfileController::class, 'update'])->name('profile.update');
    });
});

/* Gestion des codes d'accès des livreurs depuis l'espace logistique. */
Route::middleware(['auth:admin', 'internal', 'staff:logistique'])
    ->prefix('logistique')
    ->name('logistics.')
    ->group(function () {
        Route::get('/livreurs/acces', [DriverAccessController::class, 'index'])->name('drivers.access.index');
        Route::put('/livreurs/{driver}/acces', [DriverAccessController::class, 'update'])->name('drivers.access.update');
    });
