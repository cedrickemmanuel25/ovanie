<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::query()
            ->with('user:id,first_name,last_name,name,avatar')
            ->withExists('verifiedOrders as verified_purchase')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 50));

        return PublicReviewResource::collection($reviews);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::updateOrCreate(
            [
                'product_id' => $validated['product_id'],
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        $review->load('user:id,first_name,last_name,name,avatar')->loadExists('verifiedOrders as verified_purchase');

        return (new PublicReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Review $review)
    {
        $review->load('user:id,first_name,last_name,name,avatar')->loadExists('verifiedOrders as verified_purchase');

        return new PublicReviewResource($review);
    }

    public function update(Request $request, Review $review)
    {
        abort_if((int) $review->user_id !== (int) $request->user()->id, 403);

        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update($validated);

        $review = $review->fresh('user:id,first_name,last_name,name,avatar');
        $review->loadExists('verifiedOrders as verified_purchase');

        return new PublicReviewResource($review);
    }

    public function destroy(Request $request, Review $review)
    {
        abort_if((int) $review->user_id !== (int) $request->user()->id, 403);
        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }
}
