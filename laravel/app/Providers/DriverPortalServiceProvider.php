<?php

namespace App\Providers;

use App\Models\DeliveryAssignment;
use App\Observers\DeliveryAssignmentObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DriverPortalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')
                ->group(base_path('routes/driver.php'));
        }

        DeliveryAssignment::observe(DeliveryAssignmentObserver::class);
    }
}
