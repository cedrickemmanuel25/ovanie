<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request, SearchService $searchService): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return response()->json(
            $searchService->suggestions(
                (string) ($validated['q'] ?? ''),
                $validated['category'] ?? null,
                (int) ($validated['limit'] ?? 6),
            )
        );
    }
}
