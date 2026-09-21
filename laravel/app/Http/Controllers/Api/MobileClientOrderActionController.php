<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReceptionForm;
use App\Services\OrderFinancialSummaryService;
use App\Services\ClientOrderActionService;
use App\Services\OrderWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileClientOrderActionController extends Controller
{
    /**
     * Même facture que le Web : même commande, même service financier et même
     * vue Blade pdf.receipt. Il n'existe aucune facture mobile parallèle.
     */
    public function invoice(Request $request, Order $order, OrderFinancialSummaryService $financialService)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $order->load(['items.product', 'items.receptionItem', 'payments']);
        abort_if(blank($order->invoice_number), 404, 'La facture de cette commande n’est pas encore disponible.');

        $financialSummary = $financialService->summarize($order);
        $pdf = Pdf::loadView('pdf.receipt', compact('order', 'financialSummary'));

        return $pdf->download('facture-' . $order->invoice_number . '.pdf');
    }

    /**
     * Confirmation mobile de réception. Le workflow métier est le même que le
     * Web et déclenche donc les mêmes effets sur la réception et le reversement.
     */
    public function confirmReception(Request $request, Order $order)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $client = $request->user();
        $order->load('items.product');

        $updatedOrder = DB::transaction(function () use ($request, $order, $client) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->where('client_id', $client->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedOrder->load('items.product');

            $allDelivered = $lockedOrder->items->isNotEmpty() && $lockedOrder->items->every(
                fn (OrderItem $item) => $item->delivery_status === OrderWorkflowService::DELIVERY_DELIVERED
                    || $item->isVendorDelivered()
            );

            if (! $allDelivered) {
                throw ValidationException::withMessages([
                    'delivery' => 'La réception ne peut être confirmée que lorsque tous les articles sont marqués comme livrés.',
                ]);
            }

            $form = OrderReceptionForm::query()->firstOrCreate(
                ['order_id' => $lockedOrder->id, 'client_id' => $client->id],
                [
                    'client_code' => $client->id . '-' . $lockedOrder->order_number,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone ?? '',
                    'delivery_place' => $lockedOrder->delivery_address ?: $lockedOrder->address,
                    'reception_place' => $lockedOrder->delivery_address ?: $lockedOrder->address,
                    'status' => 'draft',
                ]
            );

            $form = OrderReceptionForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $form->load('items');

            if ($form->status === 'validated') {
                return $lockedOrder->fresh(['items.product', 'payments', 'returns', 'shipments']);
            }

            if ($form->items->isEmpty()) {
                foreach ($lockedOrder->items as $item) {
                    $quantity = max(1, (int) ($item->quantity ?? 1));
                    $unitPrice = (float) ($item->price ?? 0);
                    $amount = (float) ($item->subtotal ?? ($quantity * $unitPrice));

                    $form->items()->create([
                        'order_item_id' => $item->id,
                        'product_name' => $item->product?->name ?? 'Produit',
                        'shop_name' => null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'amount' => $amount,
                        'site_commission' => 0,
                        'vendor_gain' => 0,
                        'received' => true,
                        'received_date' => now()->toDateString(),
                        'received_time' => now()->format('H:i'),
                        'payment_status' => $item->is_paid ? 'paid' : 'pending',
                    ]);
                }
            } else {
                foreach ($form->items as $line) {
                    $line->update([
                        'received' => true,
                        'received_date' => $line->received_date ?: now()->toDateString(),
                        'received_time' => $line->received_time ?: now()->format('H:i'),
                    ]);
                }
            }

            $form->update([
                'delivery_place' => $request->input('delivery_place', $form->delivery_place ?: ($lockedOrder->delivery_address ?: $lockedOrder->address)),
                'reception_place' => $request->input('reception_place', $form->reception_place ?: ($lockedOrder->delivery_address ?: $lockedOrder->address)),
                'validated_city' => $request->input('validated_city', $lockedOrder->delivery_city ?: $lockedOrder->delivery_commune),
                'status' => 'validated',
                'validated_at' => now(),
                'validated_date' => now()->toDateString(),
            ]);

            foreach ($lockedOrder->items as $orderItem) {
                if ($orderItem->reception_status !== 'confirmed') {
                    app(OrderWorkflowService::class)->confirmClientReception(
                        $orderItem,
                        $client,
                        'Réception confirmée par le client depuis l’application OVANIE.'
                    );
                }
            }

            return $lockedOrder->fresh(['items.product', 'payments', 'returns', 'shipments']);
        }, 3);

        return response()->json([
            'message' => 'La réception de votre commande a été confirmée.',
            'data' => (new ClientOrderResource($updatedOrder))->resolve($request),
        ]);
    }
    /** Annulation mobile : même workflow que l'espace client Web. */
    public function cancel(Request $request, Order $order, ClientOrderActionService $actions)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $updated = $actions->cancel($request->user(), $order);

        return response()->json([
            'message' => 'Commande annulée. Le panier canonique OVANIE a été restauré lorsque cela était possible.',
            'data' => (new ClientOrderResource($updated))->resolve($request),
        ]);
    }

    /** Commander à nouveau : réutilise le même panier Laravel que le Web. */
    public function reorder(Request $request, Order $order, ClientOrderActionService $actions)
    {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        $result = $actions->reorder($request->user(), $order);
        $added = (int) ($result['added_lines'] ?? 0);

        return response()->json([
            'message' => $added > 0
                ? 'Les articles encore disponibles ont été ajoutés à votre panier OVANIE.'
                : 'Aucun article de cette commande n’est actuellement disponible.',
            'data' => [
                'added_lines' => $added,
                'skipped_lines' => (int) ($result['skipped_lines'] ?? 0),
            ],
        ], $added > 0 ? 200 : 409);
    }

}
