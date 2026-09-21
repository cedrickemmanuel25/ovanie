<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;

class VendorShipmentController extends Controller
{
    /**
     * Formulaire livraison vendeur pour une commande.
     */
    public function create(Order $order)
    {
        $shop = $this->authorizeVendorOrder($order);

        $vendorItems = $order->items()
            ->with('product')
            ->where('shop_id', $shop->id)
            ->get();

        if (View::exists('vendor.shipment')) {
            return view('vendor.shipment', compact('order', 'shop', 'vendorItems'));
        }

        if (View::exists('daniel.shipment')) {
            return view('daniel.shipment', compact('order', 'shop', 'vendorItems'));
        }

        abort(404, 'La vue shipment est introuvable. Créez resources/views/vendor/shipment.blade.php.');
    }

    /**
     * Enregistre les informations de livraison pour les lignes du vendeur connecté.
     */
    public function store(Request $request, Order $order)
    {
        $shop = $this->authorizeVendorOrder($order);

        $validated = $request->validate([
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer', 'exists:order_items,id'],
            'delivery_method' => ['required', Rule::in(['vendeur', 'ovanie', 'partenaire'])],
            'delivery_status' => ['required', Rule::in(['pending', 'scheduled', 'picked_up', 'in_transit', 'delivered', 'problem'])],
            'partner_name' => ['required_if:delivery_method,partenaire', 'nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:40'],
            'vehicle_plate' => ['nullable', 'string', 'max:80'],
            'scheduled_pickup_at' => ['nullable', 'date'],
            'delivery_otp' => ['nullable', 'string', 'max:20'],
            'delivery_note' => ['nullable', 'string', 'max:2000'],
            'loading_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delivery_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'delivery_method.required' => 'Choisissez le mode de livraison.',
            'delivery_method.in' => 'Le mode de livraison est invalide.',
            'delivery_status.required' => 'Choisissez le statut de livraison.',
            'delivery_status.in' => 'Le statut de livraison est invalide.',
            'partner_name.required_if' => 'Le nom du transporteur partenaire est obligatoire.',
            'loading_photo.image' => 'La photo de chargement doit être une image.',
            'delivery_photo.image' => 'La photo de livraison doit être une image.',
        ]);

        $vendorItemsQuery = $order->items()
            ->where('shop_id', $shop->id);

        if (! empty($validated['item_ids'])) {
            $vendorItemsQuery->whereIn('id', $validated['item_ids']);
        }

        $vendorItems = $vendorItemsQuery->get();

        if ($vendorItems->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Aucune ligne de commande valide n’a été sélectionnée pour votre boutique.');
        }

        if ($validated['delivery_status'] === 'delivered') {
            $this->validateDeliveryOtpIfPresent($request, $order);
        }

        $loadingPhoto = $request->hasFile('loading_photo')
            ? $request->file('loading_photo')->store('private-documents/delivery-evidence', 'local')
            : null;

        $deliveryPhoto = $request->hasFile('delivery_photo')
            ? $request->file('delivery_photo')->store('private-documents/delivery-evidence', 'local')
            : null;

        DB::transaction(function () use ($order, $vendorItems, $validated, $loadingPhoto, $deliveryPhoto) {
            foreach ($vendorItems as $item) {
                $payload = $this->buildOrderItemDeliveryPayload($validated, $loadingPhoto, $deliveryPhoto);
                $payload = $this->filterExistingOrderItemColumns($payload);

                if (! empty($payload)) {
                    $item->forceFill($payload)->save();
                }
            }

            $this->recalculateGlobalOrderStatus($order->fresh('items'));
        });

        return redirect()
            ->route('vendor.orders.show', $order)
            ->with('success', 'Informations de livraison enregistrées avec succès.');
    }

    /**
     * Vérifie que la commande contient au moins une ligne de la boutique vendeur.
     */
    private function authorizeVendorOrder(Order $order)
    {
        $user = Auth::user();
        $shop = $user?->shop;

        abort_unless($shop, 403, 'Vous devez avoir une boutique pour gérer cette livraison.');

        $hasVendorItem = $order->items()
            ->where('shop_id', $shop->id)
            ->exists();

        abort_unless($hasVendorItem, 403, 'Vous n’êtes pas autorisé à gérer cette commande.');

        return $shop;
    }

    /**
     * Vérifie l'OTP seulement si la commande possède déjà un champ OTP.
     */
    private function validateDeliveryOtpIfPresent(Request $request, Order $order): void
    {
        $expectedOtp = $order->delivery_otp
            ?? $order->delivery_otp_code
            ?? $order->otp_code
            ?? null;

        if (! $expectedOtp) {
            return;
        }

        if (! $request->filled('delivery_otp')) {
            abort(back()->withInput()->with('error', 'Le code OTP client est obligatoire pour confirmer la livraison.'));
        }

        if ((string) $request->input('delivery_otp') !== (string) $expectedOtp) {
            abort(back()->withInput()->with('error', 'Le code OTP saisi est incorrect.'));
        }
    }

    /**
     * Construit les données de livraison à enregistrer sur order_items.
     */
    private function buildOrderItemDeliveryPayload(array $validated, ?string $loadingPhoto, ?string $deliveryPhoto): array
    {
        $deliveryStatus = $validated['delivery_status'];
        $now = now();

        $payload = [
            'vendor_delivery_method' => $validated['delivery_method'],
            'vendor_delivery_status' => $deliveryStatus,
            'vendor_delivery_partner_name' => $validated['partner_name'] ?? null,
            'vendor_tracking_number' => $validated['tracking_number'] ?? null,
            'vendor_carrier' => $validated['delivery_method'],
            'vendor_driver_name' => $validated['driver_name'] ?? null,
            'vendor_driver_phone' => $validated['driver_phone'] ?? null,
            'vendor_vehicle_plate' => $validated['vehicle_plate'] ?? null,
            'vendor_scheduled_pickup_at' => $validated['scheduled_pickup_at'] ?? null,
            'vendor_delivery_note' => $validated['delivery_note'] ?? null,
            'vendor_status_note' => $validated['delivery_note'] ?? null,
            'vendor_status_updated_at' => $now,
        ];

        if ($loadingPhoto) {
            $payload['vendor_loading_photo'] = $loadingPhoto;
        }

        if ($deliveryPhoto) {
            $payload['vendor_delivery_photo'] = $deliveryPhoto;
        }

        if (in_array($deliveryStatus, ['scheduled', 'picked_up', 'in_transit'], true)) {
            $payload['vendor_status'] = 'shipped';
            $payload['vendor_shipment_date'] = $now;
            $payload['vendor_shipped_at'] = $now;
        }

        if ($deliveryStatus === 'picked_up') {
            $payload['vendor_picked_up_at'] = $now;
        }

        if ($deliveryStatus === 'delivered') {
            $payload['vendor_status'] = 'delivered';
            $payload['vendor_delivered_at'] = $now;
            $payload['vendor_delivery_status'] = 'delivered';
        }

        if ($deliveryStatus === 'problem') {
            $payload['vendor_status'] = 'problem';
        }

        return $payload;
    }

    /**
     * Évite les erreurs si certaines colonnes ne sont pas encore migrées.
     */
    private function filterExistingOrderItemColumns(array $payload): array
    {
        return collect($payload)
            ->filter(function ($value, $column) {
                return Schema::hasColumn('order_items', $column);
            })
            ->all();
    }

    /**
     * Recalcule prudemment le statut global selon l'avancement des lignes.
     */
    private function recalculateGlobalOrderStatus(Order $order): void
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            return;
        }

        $statuses = $items->pluck('vendor_status')->filter()->values();
        $deliveryStatuses = $items->pluck('vendor_delivery_status')->filter()->values();

        $newStatus = null;

        if ($statuses->count() === $items->count() && $statuses->every(fn ($status) => $status === 'delivered')) {
            $newStatus = 'delivered';
        } elseif ($statuses->contains('shipped') || $deliveryStatuses->contains('in_transit') || $deliveryStatuses->contains('picked_up')) {
            $newStatus = 'shipped';
        } elseif ($statuses->contains('preparing') || $statuses->contains('ready') || $statuses->contains('accepted')) {
            $newStatus = 'processing';
        }

        if ($newStatus && Schema::hasColumn('orders', 'status')) {
            $order->forceFill(['status' => $newStatus])->save();
        }
    }
}
