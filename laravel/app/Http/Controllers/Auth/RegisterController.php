<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Affiche le formulaire d'inscription.
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Traite l'inscription.
     */
    public function register(Request $request)
    {
        // ✅ Validation
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'      => ['required', 'string', 'max:20'],
            'password'   => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        // ✅ Création utilisateur
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],

            // Breeze / Auth utilisent "name"
            'name'       => $validated['first_name'] . ' ' . $validated['last_name'],

            'email'      => $validated['email'],
            'phone'      => $validated['phone'],
            'password'   => Hash::make($validated['password']),
            'role'       => 'client',
            'status'     => 'active',
        ]);

        // Email verification / events
       // event(new Registered($user));

        // Auto-login
        Auth::login($user);

        return redirect()->route('home')
            ->with('success', 'Compte créé avec succès. Bienvenue !');
    }
}
