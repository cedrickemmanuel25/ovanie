<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Negotiation;
use App\Models\Product;
use App\Services\PublicProductVisibilityService;
use Illuminate\Http\Request;

class NegotiationController extends Controller
{
    public function index(Request $request)
    {
        $negotiations = Negotiation::query()
            ->with(['product.images'])
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $negotiations->setCollection(
            $negotiations->getCollection()->map(fn (Negotiation $negotiation) => $this->clientPayload($negotiation))
        );

        return response()->json($negotiations);
    }

    public function store(Request $request, PublicProductVisibilityService $visibility)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'proposed_price' => ['required', 'numeric', 'min:1'],
        ]);

        $product = $visibility->query(['images'], true)
            ->whereKey($validated['product_id'])
            ->firstOrFail();

        $negotiation = Negotiation::create([
            'product_id' => $product->id,
            'buyer_id' => $request->user()->id,
            'vendor_id' => $product->shop_id,
            'proposed_price' => $validated['proposed_price'],
            'status' => 'pending',
        ]);

        return response()->json($this->clientPayload($negotiation->load('product.images')), 201);
    }

    public function storeFromProduct(Request $request, Product $product, PublicProductVisibilityService $visibility)
    {
        $request->merge(['product_id' => $product->id]);

        return $this->store($request, $visibility);
    }

    public function show(Request $request, Negotiation $negotiation)
    {
        abort_if((int) $negotiation->buyer_id !== (int) $request->user()->id, 403);

        return response()->json($this->clientPayload($negotiation->load('product.images')));
    }

    public function update(Request $request, Negotiation $negotiation)
    {
        abort_if((int) $negotiation->buyer_id !== (int) $request->user()->id, 403);

        $validated = $request->validate([
            'proposed_price' => ['nullable', 'numeric', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,cancelled'],
        ]);

        $negotiation->update($validated);

        return response()->json($this->clientPayload($negotiation->fresh('product.images')));
    }

    public function destroy(Request $request, Negotiation $negotiation)
    {
        abort_if((int) $negotiation->buyer_id !== (int) $request->user()->id, 403);
        $negotiation->delete();

        return response()->json(['message' => 'Négociation supprimée.']);
    }

    private function clientPayload(Negotiation $negotiation): array
    {
        $product = $negotiation->product;

        return [
            'id' => $negotiation->id,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'main_image_url' => $product->main_image_url,
                'public_price' => (float) ($product->final_price ?? $product->price ?? 0),
            ] : null,
            'proposed_price' => (float) $negotiation->proposed_price,
            'counter_price' => $negotiation->counter_price !== null ? (float) $negotiation->counter_price : null,
            'final_price' => $negotiation->final_price !== null ? (float) $negotiation->final_price : null,
            'status' => $negotiation->status,
            'created_at' => optional($negotiation->created_at)->toIso8601String(),
            'updated_at' => optional($negotiation->updated_at)->toIso8601String(),
        ];
    }
}
