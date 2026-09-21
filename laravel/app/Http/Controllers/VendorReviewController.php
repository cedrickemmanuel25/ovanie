<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor']);
    }

    public function index(Request $request)
    {
        $shop = Auth::user()?->shop;
        abort_unless($shop, 403, 'Boutique introuvable.');

        $rating = $request->query('rating', 'all');
        $onlyUnanswered = $request->boolean('unanswered');

        $reviews = Review::whereHas('product', fn ($query) => $query->where('shop_id', $shop->id))
            ->with(['user:id,name', 'product:id,name,slug'])
            ->latest()
            ->get();

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $reviews->where('rating', $i)->count();
        }

        $summary = [
            'count' => $reviews->count(),
            'average' => $reviews->isNotEmpty() ? round((float) $reviews->avg('rating'), 1) : null,
            'distribution' => $distribution,
            'replied' => $reviews->filter(fn (Review $review) => filled($review->vendor_reply))->count(),
            'this_month' => $reviews->filter(fn (Review $review) => $review->created_at?->isCurrentMonth())->count(),
        ];

        $filtered = $reviews
            ->when($rating !== 'all', fn ($items) => $items->where('rating', (int) $rating))
            ->when($onlyUnanswered, fn ($items) => $items->filter(fn (Review $review) => blank($review->vendor_reply)))
            ->values();

        return view('vendor.reviews', compact('filtered', 'summary', 'rating', 'onlyUnanswered'));
    }

    public function reply(Request $request, Review $review)
    {
        $shop = Auth::user()?->shop;
        abort_unless($shop, 403, 'Boutique introuvable.');
        abort_unless((int) $review->product?->shop_id === (int) $shop->id, 404);

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->vendor_reply = $validated['reply'];
        $review->vendor_replied_at = now();
        $review->save();

        return back()->with('success', 'Votre réponse a été publiée.');
    }
}
