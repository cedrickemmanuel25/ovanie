<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DriverAccessController extends Controller
{
    public function index()
    {
        return redirect()->route('logistics.drivers');
    }

    public function update(Request $request, DeliveryDriver $driver)
    {
        $pinIsRequired = ! $driver->hasPortalAccess();

        $validated = $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('delivery_drivers', 'email')->ignore($driver->id),
            ],
            'pin' => [
                Rule::requiredIf($pinIsRequired),
                'nullable',
                'digits:6',
                'confirmed',
            ],
        ], [
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre livreur.',
            'pin.required' => 'Saisissez un code d’accès à 6 chiffres.',
            'pin.digits' => 'Le code d’accès doit contenir exactement 6 chiffres.',
            'pin.confirmed' => 'La confirmation du code d’accès ne correspond pas.',
        ]);

        $updates = [
            'email' => $validated['email'] ?: null,
            'is_active' => true,
            'must_change_password' => false,
            'phone_verified_at' => $driver->phone_verified_at ?: now(),
        ];

        if (! empty($validated['pin'])) {
            $updates['password'] = Hash::make($validated['pin']);
        }

        $driver->forceFill($updates)->save();

        $message = $pinIsRequired
            ? "Accès livreur activé pour {$driver->name}."
            : "Accès livreur mis à jour pour {$driver->name}.";

        return back()->with('success', $message . ' Il peut se connecter avec son numéro de téléphone sur l’espace livreur.');
    }
}
