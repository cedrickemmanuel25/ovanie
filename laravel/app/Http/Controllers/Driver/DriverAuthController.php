<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DriverAuthController extends Controller
{
    /**
     * Affiche la connexion du portail livreur.
     *
     * Un livreur déjà connecté est renvoyé vers SON tableau de bord,
     * jamais vers la route générale /dashboard ou /login.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('driver')->check()) {
            return redirect()->route('driver.dashboard');
        }

        return view('driver.auth.login');
    }

    /**
     * Connecte un livreur avec son téléphone et son code personnel à 6 chiffres.
     */
    public function login(Request $request): RedirectResponse
    {
        if (Auth::guard('driver')->check()) {
            return redirect()->route('driver.dashboard');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'digits:6'],
        ], [
            'phone.required' => 'Renseignez votre numéro de téléphone.',
            'pin.required' => 'Renseignez votre code d’accès.',
            'pin.digits' => 'Le code d’accès doit contenir exactement 6 chiffres.',
        ]);

        $digits = preg_replace('/\D+/', '', (string) $validated['phone']);
        $localDigits = str_starts_with($digits, '225') ? substr($digits, 3) : $digits;

        $driver = DeliveryDriver::query()
            ->where('is_active', true)
            ->get()
            ->first(function (DeliveryDriver $candidate) use ($digits, $localDigits): bool {
                $stored = preg_replace('/\D+/', '', (string) $candidate->phone);
                $storedLocal = str_starts_with($stored, '225') ? substr($stored, 3) : $stored;

                return $stored === $digits || $storedLocal === $localDigits;
            });

        if (! $driver || ! filled($driver->password) || ! Hash::check($validated['pin'], $driver->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Numéro de téléphone ou code d’accès incorrect.',
            ]);
        }

        Auth::guard('driver')->login($driver, $request->boolean('remember'));
        $request->session()->regenerate();

        $driver->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
            'is_online' => true,
        ])->save();

        return redirect()->intended(route('driver.dashboard'));
    }

    /**
     * Déconnecte uniquement le guard livreur.
     */
    public function logout(Request $request): RedirectResponse
    {
        $driver = Auth::guard('driver')->user();

        if ($driver) {
            $driver->forceFill([
                'is_online' => false,
                'last_seen_at' => now(),
            ])->save();
        }

        Auth::guard('driver')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('driver.login');
    }
}
