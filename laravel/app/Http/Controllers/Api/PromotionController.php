<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    /**
     * Afficher la liste des promotions.
     * Optionnellement, on peut filtrer pour n'afficher que les actives.
     */
    public function index(Request $request)
    {
        if ($request->query('active')) {
            $promotions = Promotion::active()->get();
        } else {
            $promotions = Promotion::all();
        }

        return response()->json($promotions);
    }

    /**
     * Créer une nouvelle promotion.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:promotions,code'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        $promotion = Promotion::create($data);

        return response()->json($promotion, 201);
    }

    /**
     * Afficher une promotion spécifique.
     */
    public function show(Promotion $promotion)
    {
        return response()->json($promotion);
    }

    /**
     * Mettre à jour une promotion.
     */
    public function update(Request $request, Promotion $promotion)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('promotions')->ignore($promotion->id)],
            'type' => ['sometimes', Rule::in(['percent', 'fixed'])],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $promotion->update($data);

        return response()->json($promotion);
    }

    /**
     * Supprimer une promotion.
     */
    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return response()->json(null, 204);
    }
}
