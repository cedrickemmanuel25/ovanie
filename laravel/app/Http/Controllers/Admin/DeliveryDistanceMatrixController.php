<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDistanceMatrix;
use Illuminate\Http\Request;

class DeliveryDistanceMatrixController extends Controller
{
    public function index()
    {
        return view('admin.logistics.distance-matrix.index', [
            'distances' => DeliveryDistanceMatrix::query()
                ->orderBy('origin_commune')
                ->orderBy('destination_commune')
                ->paginate(40),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DeliveryDistanceMatrix::updateOrCreate([
            'origin_commune' => $data['origin_commune'],
            'destination_commune' => $data['destination_commune'],
        ], $data);

        $reverse = array_merge($data, [
            'origin_commune' => $data['destination_commune'],
            'destination_commune' => $data['origin_commune'],
        ]);

        DeliveryDistanceMatrix::updateOrCreate([
            'origin_commune' => $reverse['origin_commune'],
            'destination_commune' => $reverse['destination_commune'],
        ], $reverse);

        return back()->with('success', 'Distance enregistree.');
    }

    public function update(Request $request, DeliveryDistanceMatrix $distance)
    {
        $distance->update($this->validated($request));

        return back()->with('success', 'Distance mise a jour.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'origin_commune' => ['required', 'string', 'max:120'],
            'destination_commune' => ['required', 'string', 'max:120'],
            'distance_km' => ['required', 'numeric', 'min:0.01'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
