<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\DeliveryService;
use App\Models\DeliveryServiceRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryServiceController extends Controller
{
    public function index(): View
    {
        $services = DeliveryService::with('carrier', 'rates')
            ->orderBy('provider_type')
            ->orderBy('sort_order')
            ->paginate(20);

        return view('admin.delivery-services.index', compact('services'));
    }

    public function create(): View
    {
        $service = new DeliveryService([
            'provider_type' => DeliveryService::PROVIDER_OVANIE,
            'estimated_hours' => 48,
            'sort_order' => 100,
            'is_active' => true,
        ]);
        $carriers = Carrier::orderBy('name')->get();

        return view('admin.delivery-services.form', compact('service', 'carriers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $service = DeliveryService::create($this->validatedService($request));
        $this->syncRates($service, $request);

        return redirect()->route('admin.delivery-services.index')
            ->with('success', 'Service de livraison créé.');
    }

    public function edit(DeliveryService $deliveryService): View
    {
        $deliveryService->load('rates');
        $service = $deliveryService;
        $carriers = Carrier::orderBy('name')->get();

        return view('admin.delivery-services.form', compact('service', 'carriers'));
    }

    public function update(Request $request, DeliveryService $deliveryService): RedirectResponse
    {
        $deliveryService->update($this->validatedService($request, $deliveryService->id));
        $this->syncRates($deliveryService, $request);

        return redirect()->route('admin.delivery-services.index')
            ->with('success', 'Service de livraison mis à jour.');
    }

    public function destroy(DeliveryService $deliveryService): RedirectResponse
    {
        $deliveryService->update(['is_active' => false]);

        return back()->with('success', 'Service de livraison désactivé.');
    }

    private function validatedService(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:delivery_services,code';
        if ($ignoreId) {
            $unique .= ',' . $ignoreId;
        }

        $data = $request->validate([
            'provider_type' => 'nullable|in:ovanie',
            'carrier_id' => 'nullable|exists:carriers,id',
            'code' => ['required', 'string', 'max:80', $unique],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'required|integer|min:1|max:720',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'max_volume_m3' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['provider_type'] = DeliveryService::PROVIDER_OVANIE;

        return $data;
    }

    private function syncRates(DeliveryService $service, Request $request): void
    {
        foreach ((array) $request->input('rates', []) as $rateData) {
            if (! isset($rateData['base_fee']) || $rateData['base_fee'] === '') {
                continue;
            }

            DeliveryServiceRate::updateOrCreate(
                [
                    'id' => $rateData['id'] ?? null,
                    'delivery_service_id' => $service->id,
                ],
                [
                    'delivery_zone' => $rateData['delivery_zone'] ?? null,
                    'city' => $rateData['city'] ?? null,
                    'commune' => $rateData['commune'] ?? null,
                    'base_fee' => $rateData['base_fee'] ?? 0,
                    'price_per_kg' => $rateData['price_per_kg'] ?? 0,
                    'price_per_m3' => $rateData['price_per_m3'] ?? 0,
                    'fragile_fee' => $rateData['fragile_fee'] ?? 0,
                    'unloading_fee' => $rateData['unloading_fee'] ?? 0,
                    'urgent_fee' => $rateData['urgent_fee'] ?? 0,
                    'min_fee' => $rateData['min_fee'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
