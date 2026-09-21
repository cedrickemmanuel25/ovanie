<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverProfileController extends Controller
{
    public function edit()
    {
        $driver = Auth::guard('driver')->user();

        return view('driver.profile', compact('driver'));
    }

    public function update(Request $request)
    {
        /** @var DeliveryDriver $driver */
        $driver = Auth::guard('driver')->user();

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:190', 'unique:delivery_drivers,email,' . $driver->id],
            'availability' => ['required', 'in:Disponible,Indisponible'],
            'current_pin' => ['nullable', 'required_with:new_pin', 'digits:6'],
            'new_pin' => ['nullable', 'digits:6', 'confirmed'],
        ]);

        if (filled($validated['new_pin'] ?? null)) {
            if (! Hash::check((string) $validated['current_pin'], (string) $driver->password)) {
                throw ValidationException::withMessages([
                    'current_pin' => 'Le code actuel est incorrect.',
                ]);
            }

            $driver->password = Hash::make((string) $validated['new_pin']);
        }

        $driver->email = $validated['email'] ?? null;

        // Une mission active reste prioritaire sur l’état de disponibilité déclaré.
        if (! $driver->activeAssignments()->exists()) {
            $driver->status = $validated['availability'];
        }

        $driver->save();

        return back()->with('success', 'Votre profil livreur a été mis à jour.');
    }
}
