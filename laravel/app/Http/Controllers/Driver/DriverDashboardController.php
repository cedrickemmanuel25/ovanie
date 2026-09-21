<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverMissionService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class DriverDashboardController extends Controller
{
    public function index(DriverMissionService $missions)
    {
        $driver = Auth::guard('driver')->user();
        $all = $missions->listForDriver($driver);

        return view('driver.dashboard', [
            'driver' => $driver,
            'missions' => $all->take(5),
            'stats' => [
                'active' => $all->whereIn('status', ['planned', 'assigned', 'accepted', 'collecting', 'picked_up', 'in_transit', 'arrived'])->count(),
                'today' => $all->filter(fn (array $mission) => $mission['estimated_delivery_at']?->isToday())->count(),
                'delivered' => $all->where('status', 'delivered')->count(),
                'notifications' => $driver->unreadNotifications()->count(),
            ],
            'notifications' => $driver->unreadNotifications()->latest()->take(8)->get(),
        ]);
    }

    public function readNotification(Request $request, string $notification)
    {
        $driver = Auth::guard('driver')->user();
        $record = $driver->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : back();
    }
}
