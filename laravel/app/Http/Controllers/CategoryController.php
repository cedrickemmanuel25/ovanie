<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\MarketplacePerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()
            ->with(['children' => fn ($children) => $children->active()->ordered()])
            ->ordered();

        if ($request->boolean('active')) {
            $query->active();
        }

        if ($request->boolean('root')) {
            $query->roots();
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->integer('parent_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1200'],
            'icon' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['actif', 'inactif'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category = Category::create($data + [
            'level' => empty($data['parent_id']) ? 1 : 2,
            'is_active' => $data['status'] === 'actif',
        ]);

        $this->flushCaches();

        return response()->json($category->load('parent'), 201);
    }

    public function update(Request $request, int $id)
    {
        $category = Category::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1200'],
            'icon' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['actif', 'inactif'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category->update($data + [
            'level' => empty($data['parent_id']) ? 1 : 2,
            'is_active' => $data['status'] === 'actif',
        ]);

        $this->flushCaches();

        return response()->json($category->fresh('parent'));
    }

    public function destroy(int $id)
    {
        $category = Category::query()->findOrFail($id);

        if ($category->products()->exists() || $category->children()->exists() || $category->masterProducts()->exists()) {
            return response()->json([
                'message' => 'Cette catégorie est utilisée. Rendez-la inactive ou déplacez ses éléments avant de la supprimer.',
            ], 422);
        }

        $category->delete();
        $this->flushCaches();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    private function flushCaches(): void
    {
        app(MarketplacePerformanceService::class)->flushMarketplaceCache();
        Cache::forget('homepage.public.' . config('homepage.cache.version', 'v1'));
    }
}
