<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebAuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'role' => $user->role,
            'prenom' => $user->prenom, // ou first_name
            'email' => $user->email,
            'telephone' => $user->telephone,
            // autres infos si besoin
        ]);
    }
}
