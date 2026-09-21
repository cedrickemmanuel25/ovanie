<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register (via AJAX / API)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName'  => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'     => ['required', 'string', 'max:20'],
            'password'  => ['required', 'confirmed', Password::min(6)],
        ]);

        // Normalize email
        $email = strtolower($validated['email']);

        $user = User::create([
            'first_name' => $validated['firstName'],
            'last_name'  => $validated['lastName'],
            'email'      => $email,
            'phone'      => $validated['phone'],
            'password'   => Hash::make($validated['password']),
            'role'       => 'client',
            'status'     => 'active',
        ]);

        // Fire Registered event (sends verification mail if configured)
        event(new Registered($user));

        // Log user in (session)
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user' => $user,
        ], 201);
    }

    /**
     * Login (via AJAX / API) — accepte email ou phone as identifier
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        $identifier = trim(strtolower($request->input('identifier')));
        $password = $request->input('password');

        // Detect if identifier looks like a phone (+225...)
        $isPhone = preg_match('/^\+?225\d{8,10}$/', preg_replace('/\s+/', '', $identifier));

        $credentials = $isPhone
            ? ['phone' => $identifier, 'password' => $password]
            : ['email' => $identifier, 'password' => $password];

        // attempt authentication (web guard)
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // increment rate limiter here if tu veux (Laravel's LoginRequest le fait normalement)
            return response()->json([
                'message' => 'Identifiants invalides.'
            ], 401);
        }

        // regenerate session to avoid fixation
        $request->session()->regenerate();

        $user = Auth::user();

        return response()->json([
            'message' => 'Connecté avec succès.',
            'user' => $user,
        ], 200);
    }

    /**
     * Return current authenticated user (session)
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(null, 204);
        }

        return response()->json(['user' => $user], 200);
    }

    /**
     * Logout (session)
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté.'], 200);
    }
}
