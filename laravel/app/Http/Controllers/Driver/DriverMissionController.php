<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverMissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverMissionController extends Controller
{
    public function index(Request $request, DriverMissionService $service)
    {
        $driver = Auth::guard('driver')->user();
        $missions = $service->listForDriver($driver);
        $status = $request->string('status')->toString();

        if ($status !== '') {
            $missions = $missions->where('status', $status)->values();
        }

        return view('driver.missions.index', compact('driver', 'missions', 'status'));
    }

    public function show(string $missionNumber, DriverMissionService $service)
    {
        $driver = Auth::guard('driver')->user();
        $mission = $service->detail($driver, $missionNumber);

        return view('driver.missions.show', compact('driver', 'mission'));
    }

    public function accept(string $missionNumber, DriverMissionService $service)
    {
        $service->accept(Auth::guard('driver')->user(), $missionNumber);
        return back()->with('success', 'Mission acceptée.');
    }

    public function reject(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $service->reject(Auth::guard('driver')->user(), $missionNumber, $validated['reason'] ?? null);
        return redirect()->route('driver.missions.index')->with('success', 'Mission refusée et renvoyée au centre logistique.');
    }

    public function start(string $missionNumber, DriverMissionService $service)
    {
        $service->start(Auth::guard('driver')->user(), $missionNumber);
        return back()->with('success', 'Mission démarrée. Suivez les étapes de collecte.');
    }

    public function stage(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate(['stage' => ['required', 'in:collecting,loaded,in_transit,arrived']]);
        $service->updateStage(Auth::guard('driver')->user(), $missionNumber, $validated['stage']);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Étape mise à jour.'])
            : back()->with('success', 'Étape mise à jour.');
    }

    public function location(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $result = $service->recordLocation(Auth::guard('driver')->user(), $missionNumber, $validated);
        $location = $result['location'];

        return response()->json([
            'success' => true,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'location' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => is_numeric($location->accuracy) ? (float) $location->accuracy : null,
            ],
            'route' => $result['route_plan'] ?? null,
        ]);
    }

    public function gpsUnavailable(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate([
            'reason' => ['required', 'in:permission_denied,no_signal,device_issue,battery_saving,other'],
            'manual_eta_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        $service->gpsUnavailable(
            Auth::guard('driver')->user(),
            $missionNumber,
            $validated['reason'],
            filled($validated['manual_eta_at'] ?? null) ? Carbon::parse($validated['manual_eta_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Mode sans GPS activé. Continuez à mettre à jour les étapes manuellement.',
        ]);
    }

    public function incident(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate([
            'incident_type' => ['required', 'in:traffic_jam,client_absent,address_issue,vehicle_breakdown,accident,product_damaged,access_impossible,other'],
            'description' => ['nullable', 'string', 'max:1500'],
        ]);

        $service->reportIncident(
            Auth::guard('driver')->user(),
            $missionNumber,
            $validated['incident_type'],
            $validated['description'] ?? null,
        );

        return back()->with('success', 'Incident transmis au centre logistique OVANIE.');
    }

    public function verifyOtp(Request $request, string $missionNumber, DriverMissionService $service)
    {
        $validated = $request->validate(['delivery_otp_code' => ['required', 'digits:6']]);
        $service->verifyOtp(Auth::guard('driver')->user(), $missionNumber, $validated['delivery_otp_code']);

        return response()->json(['success' => true, 'message' => 'Livraison confirmée.']);
    }
}
