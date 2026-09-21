<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderExperienceReview;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $eligibleItems = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($query) => $query->operational()->where('client_id', $userId))
            ->where(function ($query) {
                $query->where('delivery_status', 'delivered')
                    ->orWhere('vendor_delivery_status', 'delivered')
                    ->orWhere('vendor_status', 'delivered')
                    ->orWhereNotNull('delivery_otp_verified_at');
            })
            ->with(['order:id,client_id,order_number,status,created_at', 'product.category', 'product.images'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->unique('product_id')
            ->values();

        $reviews = Review::query()
            ->where('user_id', $userId)
            ->whereIn('product_id', $eligibleItems->pluck('product_id')->all())
            ->get()
            ->keyBy('product_id');

        $orders = Order::query()
            ->operational()
            ->where('client_id', $userId)
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->with('items')
            ->latest()
            ->limit(40)
            ->get()
            ->filter(fn (Order $order) => $order->items->isNotEmpty() && $order->items->every(fn (OrderItem $item) => $item->isVendorDelivered()))
            ->values();

        $experienceReviews = Schema::hasTable('order_experience_reviews')
            ? OrderExperienceReview::query()
                ->where('user_id', $userId)
                ->whereIn('order_id', $orders->pluck('id')->all())
                ->get()
                ->keyBy('order_id')
            : collect();

        return response()->json([
            'data' => [
                'product_reviews' => $eligibleItems->map(function (OrderItem $item) use ($reviews) {
                    $review = $reviews->get($item->product_id);
                    return [
                        'order_id' => (int) $item->order_id,
                        'order_number' => (string) ($item->order?->order_number ?? ''),
                        'order_item_id' => (int) $item->id,
                        'delivered_at' => ($item->delivery_completed_at ?? $item->vendor_delivered_at ?? $item->delivery_otp_verified_at)?->toIso8601String(),
                        'product' => [
                            'id' => (int) $item->product_id,
                            'name' => (string) ($item->product?->name ?? 'Produit OVANIE'),
                            'slug' => (string) ($item->product?->slug ?? ''),
                            'main_image_url' => (string) ($item->product?->main_image_url ?? ''),
                        ],
                        'review' => $review ? [
                            'id' => (int) $review->id,
                            'rating' => (int) $review->rating,
                            'comment' => (string) ($review->comment ?? ''),
                            'updated_at' => $review->updated_at?->toIso8601String(),
                        ] : null,
                    ];
                })->values(),
                'delivery_reviews' => $orders->map(function (Order $order) use ($experienceReviews) {
                    $review = $experienceReviews->get($order->id);
                    return [
                        'order_id' => (int) $order->id,
                        'order_number' => (string) $order->order_number,
                        'delivered_at' => $order->delivered_at?->toIso8601String(),
                        'review' => $review ? [
                            'id' => (int) $review->id,
                            'rating' => (int) $review->delivery_rating,
                            'comment' => (string) ($review->delivery_comment ?? ''),
                            'updated_at' => $review->updated_at?->toIso8601String(),
                        ] : null,
                    ];
                })->values(),
                'capabilities' => [
                    'product_rating' => true,
                    'delivery_rating' => Schema::hasTable('order_experience_reviews'),
                    'seller_rating' => false,
                ],
            ],
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = OrderItem::query()
            ->with('order')
            ->whereKey($data['order_item_id'])
            ->whereHas('order', fn ($query) => $query->operational()->where('client_id', $request->user()->id))
            ->firstOrFail();

        abort_unless($item->product_id && $item->isVendorDelivered(), 422, 'Ce produit ne peut être évalué qu’après sa livraison.');

        $review = Review::updateOrCreate(
            [
                'product_id' => $item->product_id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => (int) $data['rating'],
                'comment' => isset($data['comment']) ? trim((string) $data['comment']) : null,
            ]
        );

        return response()->json([
            'message' => 'Votre avis produit a été enregistré.',
            'data' => [
                'id' => (int) $review->id,
                'product_id' => (int) $review->product_id,
                'rating' => (int) $review->rating,
                'comment' => (string) ($review->comment ?? ''),
                'updated_at' => $review->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function storeDelivery(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('order_experience_reviews'), 503, 'Évaluation livraison indisponible.');

        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->operational()
            ->with('items')
            ->where('client_id', $request->user()->id)
            ->findOrFail($data['order_id']);

        $allDelivered = $order->items->isNotEmpty()
            && $order->items->every(fn (OrderItem $item) => $item->isVendorDelivered());
        abort_unless($allDelivered, 422, 'L’expérience de livraison peut être évaluée après la livraison réelle de la commande.');

        $review = OrderExperienceReview::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'order_id' => $order->id,
            ],
            [
                'delivery_rating' => (int) $data['rating'],
                'delivery_comment' => isset($data['comment']) ? trim((string) $data['comment']) : null,
            ]
        );

        return response()->json([
            'message' => 'Votre évaluation de la livraison a été enregistrée.',
            'data' => [
                'id' => (int) $review->id,
                'order_id' => (int) $review->order_id,
                'rating' => (int) $review->delivery_rating,
                'comment' => (string) ($review->delivery_comment ?? ''),
                'updated_at' => $review->updated_at?->toIso8601String(),
            ],
        ], 201);
    }
}
