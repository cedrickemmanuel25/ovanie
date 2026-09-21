<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Accepts both normal web form submissions and JSON/ajax requests.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'      => ['required', 'string', 'regex:/^\+?225\d{8,10}$/'],
            'password'   => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Normalise email
        $validated['email'] = strtolower($validated['email']);

        // Create user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'],
            'password'   => Hash::make($validated['password']),
            'role'       => $request->get('role', 'client'),   // default role = client
            'status'     => $request->get('status', 'active'), // default status = active
        ]);

        // Log the user in
        Auth::login($user);
        $request->session()->regenerate();

        // If request expects JSON (API call from register.js), return JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Compte créé avec succès.',
                'redirect' => route('client.dashboard'),
                'user' => $user,
            ], 201);
        }

        return redirect()->route('client.dashboard');
    }
}
