<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Vérifie le mot de passe admin (AJAX)
     */
    public function checkPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        if ($request->password === env('ADMIN_PASSWORD')) {

            // 🔐 On garde l'accès
            session(['admin_gate' => true]);

            return response()->json([
                'success' => true
            ]);
        }

        return response()->json([
            'success' => false
        ]);
    }
}
