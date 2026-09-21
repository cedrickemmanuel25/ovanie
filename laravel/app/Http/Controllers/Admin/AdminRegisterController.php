<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminRegisterController extends Controller
{
    // Affiche le formulaire
    public function showRegisterForm()
    {
        abort_unless(config('security.admin_registration_enabled'), 404);

        if (!session('admin_register_gate')) {
            return redirect()->route('admin.adminlogin')
                ->with('register_gate_required', true);
        }

        return view('admin.auth.adminregister');
    }

    public function registerGate(Request $request)
    {
        abort_unless(config('security.admin_registration_enabled'), 404);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $expectedPassword = (string) config('security.admin_registration_password', '');

        if ($expectedPassword === '' || ! hash_equals($expectedPassword, (string) $request->password)) {
            return response()->json([
                'message' => 'Mot de passe incorrect'
            ], 403);
        }

        session(['admin_register_gate' => true]);

        return response()->json([
            'success' => true
        ]);
    }
    // Traite l'inscription
    public function register(Request $request)
    {
        abort_unless(config('security.admin_registration_enabled'), 404);

        if (!session('admin_register_gate')) {
            return response()->json([
                'message' => 'Accès inscription admin non autorisé.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Séparer first_name et last_name
        $nameParts = explode(' ', $request->name);
        $first_name = $nameParts[0];
        $last_name = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

        $user = User::create([
            'first_name' => $first_name,
            'last_name' => $last_name,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'is_admin' => 1, // 🔥 IMPORTANT
            'status' => 'active',
            'phone' => null,
            'google_id' => null,
            'facebook_id' => null,
            'email_verification_token' => null,
        ]);

        return response()->json([
            'message' => 'Administrateur créé avec succès',
            'user' => $user
        ]);
    }
}
