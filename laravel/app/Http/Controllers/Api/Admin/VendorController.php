<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// app/Http/Controllers/Api/Admin/VendorController.php

class VendorController extends Controller
{
    // 📥 Liste des boutiques
    public function index()
    {
        return Vendor::orderBy('created_at', 'desc')->get();
    }

    // 🔄 Mise à jour statut
    public function update(Request $request, Vendor $vendor)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $vendor->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Statut mis à jour',
            'vendor'  => $vendor
        ]);
    }
}

