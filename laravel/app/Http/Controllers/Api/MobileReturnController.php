<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientReturnResource;
use App\Models\Order;
use App\Models\ReturnModel;
use App\Models\User;
use App\Services\OrderWorkflowService;
use App\Services\ReturnRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileReturnController extends Controller
{
    public function index(Request $request, ReturnRefundService $returnRefunds)
    {
        $clientId = (int) $request->user()->id;

        // Même périmètre et mêmes règles d'éligibilité que l'espace client Web.
        $orders = Order::query()
            ->operational()
            ->where('client_id', $clientId)
            ->with(['items.product', 'client'])
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->latest()
            ->get();

        $eligibleOrders = $orders->map(function (Order $order) use ($returnRefunds) {
            $items = $order->items->map(function ($item) use ($order, $returnRefunds) {
                $claimQuantity = $returnRefunds->availableQuantity($item, 'claim');
                $physicalQuantity = $returnRefunds->availableQuantity($item, 'return');
                $isDelivered = $item->isVendorDelivered();
                $withinWindow = $isDelivered && $returnRefunds->isWithinReturnWindow($item, $order);
                $deadline = $isDelivered ? $returnRefunds->returnDeadline($item, $order) : null;

                return [
                    'id' => (int) $item->id,
                    'product' => [
                        'id' => $item->product_id ? (int) $item->product_id : null,
                        'name' => $item->product?->name ?: 'Produit OVANIE',
                        'main_image_url' => $item->product?->main_image_url,
                    ],
                    'unit_price' => $item->price !== null ? (float) $item->price : null,
                    'ordered_quantity' => (int) $item->quantity,
                    'available_return_quantity' => $physicalQuantity,
                    'available_claim_quantity' => $claimQuantity,
                    'can_claim' => $claimQuantity > 0,
                    'can_return' => $physicalQuantity > 0 && $withinWindow,
                    'can_refund' => $physicalQuantity > 0 && $withinWindow,
                    'return_deadline' => $deadline?->toDateString(),
                    'delivered' => $isDelivered,
                ];
            })->filter(fn (array $item) => $item['can_claim'] || $item['can_return'] || $item['can_refund'])
                ->values();

            if ($items->isEmpty()) {
                return null;
            }

            return [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'created_at' => $order->created_at?->toIso8601String(),
                'delivery_address' => (string) ($order->delivery_address ?: $order->address ?: ''),
                'delivery_city' => (string) ($order->delivery_city ?: ''),
                'delivery_commune' => (string) ($order->delivery_commune ?: ''),
                'delivery_quartier' => (string) ($order->delivery_quartier ?: ''),
                'recipient_name' => (string) ($order->delivery_recipient_name ?: $order->customer_name ?: $order->client?->name ?: ''),
                'recipient_phone' => (string) ($order->delivery_recipient_phone ?: $order->phone ?: $order->client?->phone ?: ''),
                'recipient_email' => (string) ($order->client?->email ?: ''),
                'items' => $items->all(),
            ];
        })->filter()->values();

        $returns = ReturnModel::with(['order.client', 'orderItem.product'])
            ->where('client_id', $clientId)
            ->latest()
            ->get();

        return response()->json([
            'requests' => ClientReturnResource::collection($returns)->resolve($request),
            'eligible_orders' => $eligibleOrders->all(),
        ]);
    }

    public function show(Request $request, int $returnRequest)
    {
        $return = ReturnModel::with(['order.client', 'orderItem.product'])
            ->where('client_id', $request->user()->id)
            ->findOrFail($returnRequest);

        return new ClientReturnResource($return);
    }

    public function store(Request $request, ReturnRefundService $returnRefunds)
    {
        $data = $request->validate([
            'existing_return_id' => ['nullable', 'integer'],
            'order_id' => ['required', 'integer'],
            'order_item_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'detailed_description' => ['nullable', 'string', 'max:1500'],
            'discovery_date' => ['nullable', 'date'],
            'storage_location' => ['nullable', 'string', 'max:500'],
            'pickup_address' => ['nullable', 'string', 'max:700'],
            'pickup_contact_name' => ['nullable', 'string', 'max:160'],
            'pickup_contact_phone' => ['nullable', 'string', 'max:80'],
            'pickup_contact_email' => ['nullable', 'email', 'max:190'],
            'pickup_date' => ['nullable', 'date'],
            'pickup_time_slot' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1'],
            'return_type' => ['required', Rule::in(['return', 'claim', 'refund'])],
            'photo_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'videos' => ['nullable', 'array', 'max:1'],
            'videos.*' => ['file', 'mimes:mp4,mov,m4v,avi', 'max:15360'],
        ]);

        $photoPaths = [];
        if ($request->hasFile('photo_proof')) {
            $photoPaths[] = $request->file('photo_proof')->store('private-documents/return-proof', 'local');
        }
        foreach ($request->file('photos', []) as $photo) {
            if (count($photoPaths) >= 5) {
                break;
            }
            $photoPaths[] = $photo->store('private-documents/return-proof', 'local');
        }
        $photoPaths = array_values(array_unique(array_filter($photoPaths)));

        $videoPaths = [];
        foreach ($request->file('videos', []) as $video) {
            if (count($videoPaths) >= 1) {
                break;
            }
            $videoPaths[] = $video->store('private-documents/return-proof-video', 'local');
        }
        $videoPaths = array_values(array_unique(array_filter($videoPaths)));

        $preparationMeta = array_filter([
            'detailed_description' => $data['detailed_description'] ?? null,
            'discovery_date' => $data['discovery_date'] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'pickup_address' => $data['pickup_address'] ?? null,
            'pickup_contact_name' => $data['pickup_contact_name'] ?? null,
            'pickup_contact_phone' => $data['pickup_contact_phone'] ?? null,
            'pickup_contact_email' => $data['pickup_contact_email'] ?? null,
            'pickup_date' => $data['pickup_date'] ?? null,
            'pickup_time_slot' => $data['pickup_time_slot'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        // Un retour déjà accepté peut être préparé depuis l'application sans
        // créer un second dossier. Le même endpoint conserve la compatibilité
        // avec les anciennes versions mobiles.
        if (! empty($data['existing_return_id'])) {
            $return = ReturnModel::query()
                ->with(['order.client', 'orderItem.product'])
                ->where('client_id', $request->user()->id)
                ->findOrFail((int) $data['existing_return_id']);

            if ((int) $return->order_id !== (int) $data['order_id'] || (int) $return->order_item_id !== (int) $data['order_item_id']) {
                throw ValidationException::withMessages([
                    'existing_return_id' => 'Le dossier sélectionné ne correspond pas au produit concerné.',
                ]);
            }

            $meta = is_array($return->meta) ? $return->meta : [];
            $existingPhotos = array_values(array_filter((array) data_get($meta, 'photo_proofs', [])));
            $existingVideos = array_values(array_filter((array) data_get($meta, 'video_proofs', [])));
            if ($return->photo_proof && ! in_array($return->photo_proof, $existingPhotos, true)) {
                array_unshift($existingPhotos, $return->photo_proof);
            }

            $meta = array_merge($meta, $preparationMeta);
            $meta['photo_proofs'] = array_values(array_unique(array_merge($existingPhotos, $photoPaths)));
            $meta['video_proofs'] = array_values(array_unique(array_merge($existingVideos, $videoPaths)));

            $return->forceFill([
                'reason' => $data['reason'],
                'quantity' => $data['quantity'],
                'meta' => $meta,
                'logistics_status' => $return->status === ReturnModel::STATUS_ACCEPTED
                    ? ReturnModel::LOGISTICS_PICKUP_PLANNED
                    : $return->logistics_status,
            ])->save();

            return response()->json([
                'message' => 'La préparation de votre retour a été enregistrée.',
                'data' => (new ClientReturnResource($return->fresh(['order.client', 'orderItem.product'])))->resolve($request),
            ]);
        }

        $order = Order::query()
            ->operational()
            ->where('client_id', $request->user()->id)
            ->with(['items.product.shop.user', 'client'])
            ->whereIn('status', ['shipped', 'delivered', 'completed'])
            ->findOrFail((int) $data['order_id']);

        $item = $order->items->firstWhere('id', (int) $data['order_item_id']);
        if (! $item) {
            throw ValidationException::withMessages([
                'order_item_id' => 'Le produit sélectionné n’appartient pas à cette commande.',
            ]);
        }

        $isReturnOrRefund = in_array($data['return_type'], ['return', 'refund'], true);
        if ($isReturnOrRefund && ! $item->isVendorDelivered()) {
            throw ValidationException::withMessages([
                'order_item_id' => 'Un retour ou remboursement ne peut être demandé qu’après la livraison du produit.',
            ]);
        }

        if ($isReturnOrRefund && ! $returnRefunds->isWithinReturnWindow($item, $order)) {
            $deadline = $returnRefunds->returnDeadline($item, $order);
            $message = $deadline
                ? 'Le délai de retour de cet article a expiré le ' . $deadline->format('d/m/Y') . '. Vous pouvez ouvrir une réclamation auprès du support OVANIE.'
                : 'La date de livraison réelle de cet article n’est pas disponible. Ouvrez une réclamation afin qu’OVANIE vérifie le dossier.';

            throw ValidationException::withMessages(['order_item_id' => $message]);
        }

        $availableQuantity = $returnRefunds->availableQuantity($item, $data['return_type']);
        if ($availableQuantity <= 0 || (int) $data['quantity'] > $availableQuantity) {
            throw ValidationException::withMessages([
                'quantity' => $availableQuantity <= 0
                    ? 'Toute la quantité disponible pour cet article est déjà concernée par une demande antérieure ou en cours.'
                    : "La quantité maximale encore disponible pour une demande est {$availableQuantity}.",
            ]);
        }

        $return = DB::transaction(function () use ($request, $data, $order, $item, $photoPaths, $videoPaths, $preparationMeta, $returnRefunds) {
            $lockedUser = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ((bool) $lockedUser->deletion_in_progress || $lockedUser->status === 'suspended') {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Votre compte ne peut plus créer de nouvelle demande.',
                ]);
            }

            $lockedItem = $order->items()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $availableQuantity = $returnRefunds->availableQuantity($lockedItem, $data['return_type']);
            if ($availableQuantity <= 0 || (int) $data['quantity'] > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "La quantité maximale encore disponible pour une demande est {$availableQuantity}.",
                ]);
            }

            $meta = $preparationMeta;
            if ($photoPaths) {
                $meta['photo_proofs'] = $photoPaths;
            }
            if ($videoPaths) {
                $meta['video_proofs'] = $videoPaths;
            }

            $return = ReturnModel::create([
                'order_id' => $order->id,
                'order_item_id' => $lockedItem->id,
                'client_id' => $request->user()->id,
                'vendor_id' => $item->product?->shop?->user_id,
                'shop_id' => $lockedItem->shop_id ?: $item->product?->shop_id,
                'product_id' => $lockedItem->product_id,
                'quantity' => $data['quantity'],
                'order_reference' => $order->order_number,
                'product_name' => $item->product?->name ?: 'Produit commande',
                'reason' => $data['reason'],
                'request_date' => now()->toDateString(),
                'status' => ReturnModel::STATUS_PENDING,
                'return_type' => $data['return_type'],
                'logistics_status' => $returnRefunds->initialLogisticsStatus(
                    $data['return_type'],
                    $lockedItem->delivery_provider
                ),
                'photo_proof' => $photoPaths[0] ?? null,
                'meta' => $meta ?: null,
            ]);

            $lockedItem->forceFill([
                'return_status' => $data['return_type'] === 'claim' ? 'claim_pending' : ReturnModel::STATUS_PENDING,
                'payout_status' => 'blocked',
            ])->save();

            app(OrderWorkflowService::class)->recordHistory(
                $order,
                $lockedItem,
                'return',
                null,
                ReturnModel::STATUS_PENDING,
                [
                    'actor_type' => 'client',
                    'user_id' => $request->user()->id,
                    'label' => match ($data['return_type']) {
                        'claim' => 'Réclamation enregistrée',
                        'refund' => 'Demande de remboursement enregistrée',
                        default => 'Demande de retour enregistrée',
                    },
                    'message' => $data['reason'],
                    'metadata' => ['return_id' => $return->id, 'quantity' => $data['quantity']],
                ]
            );

            return $return;
        }, 3);

        app(OrderWorkflowService::class)->notify(
            $item->product?->shop?->user,
            'Retour / réclamation client',
            'Une demande concerne la commande ' . $order->order_number,
            [
                'category' => 'returns',
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'url' => route('vendor.returns.index'),
            ]
        );

        return response()->json([
            'message' => 'Votre demande a été envoyée avec succès.',
            'data' => (new ClientReturnResource($return->load(['order.client', 'orderItem.product'])))->resolve($request),
        ], 201);
    }
}
