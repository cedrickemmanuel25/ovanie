<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserApiController extends Controller
{
    public function index()
    {
        return response()->json(User::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,client,vendor,logistique',
            'status' => 'required|in:active,suspended',
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'status' => $validated['status'],
            'password' => isset($validated['password']) ? Hash::make($validated['password']) : Hash::make('password123'),
        ]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,client,vendor,logistique',
            'status' => 'required|in:active,suspended',
        ]);

        $user->update($validated);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function destroy(User $user, AccountDeletionService $deletionService)
    {
        if ($user->role === 'client') {
            $deletionService->assertCanSelfDelete($user);
        }

        $deletionService->anonymize($user);

        return response()->json([
            'success' => true,
            'message' => 'Le compte a été anonymisé et suspendu. Les historiques commerciaux et financiers sont conservés.',
        ]);
    }
}
