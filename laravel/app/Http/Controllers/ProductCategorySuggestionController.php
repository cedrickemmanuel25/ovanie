<?php

namespace App\Http\Controllers;

use App\Services\AiProductCategorySuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint partagé vendeur/commercial : suggère une catégorie/sous-catégorie
 * à partir du nom saisi, pour que le vendeur ou le commercial n'ait plus
 * besoin de la sélectionner à la main. Toujours modifiable côté formulaire ;
 * répond {"suggestion": null} si l'IA est désactivée, indisponible, ou
 * n'est pas assez confiante pour proposer une catégorie.
 */
class ProductCategorySuggestionController extends Controller
{
    public function __invoke(Request $request, AiProductCategorySuggester $suggester): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'suggestion' => $suggester->suggest($data['name']),
        ]);
    }
}
