<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\VendorPaymentVerificationRequest;
use App\Services\LoyaltyService;
use App\Services\ClientDeliveryGroupService;
use App\Services\DeliveryGroupCollectionService;
use App\Services\OrderSettlementService;
use App\Services\OrderWorkflowService;
use App\Services\VendorOrderReleaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminVendorPaymentVerificationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $verifications = VendorPaymentVerificationRequest::with(['order', 'shop', 'vendor', 'requester'])
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('requested_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payment-verifications.index', compact('verifications', 'status'));
    }

    public function approve(
        Request $request,
        VendorPaymentVerificationRequest $verification,
        ClientDeliveryGroupService $deliveryGroups,
        DeliveryGroupCollectionService $groupCollections
    ) {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use (
            $verification,
            $validated,
            $deliveryGroups,
            $groupCollections
        ) {
            $lockedVerification = VendorPaymentVerificationRequest::query()
                ->whereKey($verification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVerification->status !== 'pending') {
                return ['already_processed' => true];
            }

            $order = $lockedVerification->order()->lockForUpdate()->firstOrFail();
            $order->load([
                'items.product',
                'items.shop',
                'items.shipment',
                'deliverySelections',
                'shipments',
                'payments',
            ]);

            $group = $deliveryGroups->findForShop($order, (int) $lockedVerification->shop_id);

            if (! $group) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment' => 'Le groupe de livraison correspondant à cette boutique est introuvable.',
                ]);
            }

            if (! $group['is_delivered']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment' => 'Le paiement ne peut pas être validé avant la livraison des articles concernés.',
                ]);
            }

            $expectedAmount = (float) $group['total'];
            if (abs((float) $lockedVerification->amount_claimed - $expectedAmount) > 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment' => 'Le montant déclaré ne correspond pas au montant attendu de ' . number_format($expectedAmount, 0, ',', ' ') . ' FCFA.',
                ]);
            }

            $lockedVerification->update([
                'status' => 'approved',
                'reviewed_by' => auth('admin')->id(),
                'admin_note' => $validated['admin_note'] ?? null,
                'reviewed_at' => now(),
            ]);

            if (Schema::hasColumn('order_items', 'vendor_payment_status')) {
                $order->items()
                    ->whereIn('id', $group['item_ids']->all())
                    ->update([
                        'vendor_payment_status' => 'verified',
                        'vendor_payment_verified_at' => now(),
                        'vendor_payment_note' => $validated['admin_note']
                            ?? 'Paiement de la livraison vérifié par l’administration OVANIE.',
                    ]);
            }

            $collection = $groupCollections->collect($order, $group['key'], [
                'actor_type' => 'admin',
                'user_id' => auth('admin')->id(),
                'operator' => 'seller_delivery_verified_by_ovanie',
                'verification_request_id' => $lockedVerification->id,
                'shop_id' => (int) $lockedVerification->shop_id,
                'payment_method_declared' => $lockedVerification->payment_method,
                'payment_reference_declared' => $lockedVerification->payment_reference,
            ]);

            app(VendorOrderReleaseService::class)->releaseForShop(
                $order->fresh(),
                (int) $lockedVerification->shop_id
            );

            return [
                'approved' => true,
                'order_id' => $order->id,
                'all_paid' => (bool) ($collection['all_paid'] ?? false),
                'delivery_number' => $group['number'],
                'amount' => $expectedAmount,
            ];
        }, 3);

        if (! empty($result['already_processed'])) {
            return back()->with('warning', 'Cette demande a déjà été traitée.');
        }

        if (! empty($result['order_id'])) {
            $order = \App\Models\Order::with('client')->find($result['order_id']);

            if ($order) {
                app(OrderWorkflowService::class)->notify(
                    $order->client,
                    'Paiement de livraison confirmé',
                    'Le paiement de la livraison ' . ($result['delivery_number'] ?? '') . ' a été vérifié par OVANIE.',
                    [
                        'category' => 'payments',
                        'order_id' => $order->id,
                        'url' => route('client.orders.show', $order),
                    ]
                );
            }
        }

        return back()->with(
            'success',
            'Le paiement de cette livraison a été vérifié et enregistré. Les autres livraisons restent indépendantes.'
        );
    }

    public function reject(Request $request, VendorPaymentVerificationRequest $verification)
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ], [
            'admin_note.required' => 'La raison du rejet est obligatoire.',
        ]);

        $updated = DB::transaction(function () use ($verification, $validated) {
            $lockedVerification = VendorPaymentVerificationRequest::query()
                ->whereKey($verification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVerification->status !== 'pending') {
                return false;
            }

            $lockedVerification->update([
                'status' => 'rejected',
                'reviewed_by' => auth('admin')->id(),
                'admin_note' => $validated['admin_note'],
                'reviewed_at' => now(),
            ]);

            return true;
        }, 3);

        return $updated
            ? back()->with('success', 'Demande de vérification paiement rejetée.')
            : back()->with('warning', 'Cette demande a déjà été traitée.');
    }
}
