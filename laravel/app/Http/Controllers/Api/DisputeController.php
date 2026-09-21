<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Dispute::class);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $query = Dispute::query()->with(['order', 'orderItem', 'shop'])->latest('created_at')->latest('id');

        if (! $request->user()->can('viewAll', Dispute::class)) {
            $userId = (int) $request->user()->id;
            $query->where(function ($ownership) use ($userId) {
                $ownership->where('client_id', $userId)
                    ->orWhere('vendor_id', $userId)
                    ->orWhereHas('shop', fn ($shop) => $shop->where('user_id', $userId));
            });
        }

        $disputes = $query->paginate($perPage);
        $disputes->getCollection()->transform(fn (Dispute $dispute) => $this->visibleData($dispute, $request->user()));

        return response()->json($disputes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Dispute::class);
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $order = Order::with(['items.shop'])->findOrFail($data['order_id']);
        $item = isset($data['order_item_id'])
            ? OrderItem::with('shop')->where('order_id', $order->id)->findOrFail($data['order_item_id'])
            : null;

        abort_unless($this->mayOpenForOrder($request->user(), $order, $item), 403);

        $shop = $item?->shop ?? $order->shop;
        $vendorId = $shop?->user_id ?? $order->vendor_id;
        $dispute = Dispute::create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'client_id' => $order->client_id,
            'vendor_id' => $vendorId,
            'shop_id' => $shop?->id ?? $order->shop_id,
            'order_reference' => $order->order_number,
            'client_name' => $order->customer_name ?: (string) $order->client?->name,
            'reason' => $data['reason'],
            'status' => 'open',
        ]);

        return response()->json($this->visibleData($dispute, $request->user()), 201);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('view', $dispute);

        return response()->json($this->visibleData($dispute->load(['order', 'orderItem', 'shop']), $request->user()));
    }

    public function update(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('update', $dispute);
        $internal = $request->user()->can('manage', $dispute);
        if (! $internal && $request->hasAny(['status', 'response', 'internal_notes', 'escalated'])) {
            abort(403);
        }

        $rules = ['reason' => ['sometimes', 'string', 'max:5000']];
        if ($internal) {
            $rules += [
                'response' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'status' => ['sometimes', Rule::in(['open', 'in_review', 'resolved', 'closed'])],
                'escalated' => ['sometimes', 'boolean'],
            ];
        }

        $data = $request->validate($rules);
        $sensitiveBefore = $dispute->only(['status', 'escalated', 'internal_notes']);
        $dispute->fill($data);

        if ($internal && array_key_exists('response', $data)) {
            $dispute->responded_at = now();
        }
        if ($internal && array_key_exists('escalated', $data)) {
            $dispute->escalated_at = $data['escalated'] ? now() : null;
        }

        $dispute->save();
        $sensitiveAfter = $dispute->only(['status', 'escalated', 'internal_notes']);
        if ($internal && $sensitiveBefore !== $sensitiveAfter) {
            Log::notice('Sensitive dispute state changed', [
                'dispute_id' => $dispute->id,
                'actor_id' => $request->user()->id,
                'changed_fields' => array_keys(array_diff_assoc($sensitiveAfter, $sensitiveBefore)),
            ]);
        }

        return response()->json($this->visibleData($dispute, $request->user()));
    }

    public function destroy(Request $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('delete', $dispute);
        $dispute->delete();
        Log::notice('Dispute archived', ['dispute_id' => $dispute->id, 'actor_id' => $request->user()->id]);

        return response()->json(null, 204);
    }

    private function mayOpenForOrder(User $user, Order $order, ?OrderItem $item): bool
    {
        if ($user->is_admin || $user->hasStaffPermission('disputes.read')) {
            return true;
        }
        if ((int) $order->client_id === (int) $user->id) {
            return true;
        }

        return $item
            ? (int) $item->shop?->user_id === (int) $user->id
            : $order->items->contains(fn (OrderItem $orderItem) => (int) $orderItem->shop?->user_id === (int) $user->id);
    }

    private function visibleData(Dispute $dispute, User $user): array
    {
        $data = $dispute->toArray();
        unset($data['order'], $data['order_item'], $data['shop'], $data['client'], $data['vendor']);
        if (! $user->can('viewInternalNotes', $dispute)) {
            unset($data['internal_notes']);
        }

        return $data;
    }
}
