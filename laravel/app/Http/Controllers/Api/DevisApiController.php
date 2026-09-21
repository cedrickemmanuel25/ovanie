<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use Illuminate\Http\Request;

class DevisApiController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Devis::query()
                ->where(function ($query) use ($request) {
                    $query->where('email', $request->user()->email ?? null)
                        ->orWhere('telephone', $request->user()->telephone ?? null)
                        ->orWhere('telephone', $request->user()->phone ?? null);
                })
                ->latest()
                ->paginate((int) $request->get('per_page', 20))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'secteur' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'activites' => ['nullable', 'array'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'nom' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'pays' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'projet' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['email'] = $data['email'] ?? $request->user()->email ?? null;
        $data['telephone'] = $data['telephone'] ?? $request->user()->telephone ?? $request->user()->phone ?? null;

        $devis = Devis::create($data);

        return response()->json(['message' => 'Demande de devis envoyée', 'data' => $devis], 201);
    }

    public function show(Request $request, Devis $devis)
    {
        $email = $request->user()->email ?? null;
        $phone = $request->user()->telephone ?? $request->user()->phone ?? null;
        abort_unless($devis->email === $email || $devis->telephone === $phone, 403);

        return response()->json(['data' => $devis]);
    }
}
