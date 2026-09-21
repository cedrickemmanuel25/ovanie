<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorValidationController extends Controller
{
    public function __construct()
    {
        // Sécurisation : admin uniquement
        $this->middleware(['auth', 'isAdmin']);
    }

    /**
     * Liste des boutiques en attente de validation
     */
    public function index()
    {
        $vendors = Vendor::where('status', 'pending')->get();

        return view('admin.vendor_validation.index', compact('vendors'));
    }

    /**
     * Approuver une boutique
     */
    public function approve(Vendor $vendor)
    {
        $vendor->update([
            'status' => 'approved'
        ]);

        return redirect()
            ->back()
            ->with('success', 'Boutique validée avec succès.');
    }

    /**
     * Refuser une boutique
     */
    public function reject(Vendor $vendor)
    {
        $vendor->update([
            'status' => 'rejected'
        ]);

        return redirect()
            ->back()
            ->with('success', 'Boutique refusée.');
    }
}
