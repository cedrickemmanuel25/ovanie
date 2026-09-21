<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Submission;

class PublicSubmissionController extends Controller
{
    /**
     * Stocker une soumission depuis le formulaire public
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // Enregistrer en base
        Submission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre suggestion !'
        ]);
    }
}