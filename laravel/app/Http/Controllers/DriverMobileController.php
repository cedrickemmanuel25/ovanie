<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\OrderItem;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverMobileController extends Controller
{
    /**
     * Page principale livreur — accès via /livreur/{token}
     * Le token est le numéro de téléphone encodé en base64 (simple, sans auth complexe)
     */
    public function show(string $token)
    {
        $phone = base64_decode($token, true);

        if (! $phone) {
            abort(404);
        }

        $driver = DeliveryDriver::where('phone', $phone)
            ->where('is_active', true)
            ->firstOrFail();

        // Missions actives du livreur (pas encore livrées)
        $assignments = DeliveryAssignment::with([
            'orderItem.order.client',
            'orderItem.product.shop',
        ])
            ->where('driver_id', $driver->id)
            ->whereNotIn('status', ['delivered', 'delivery_failed', 'cancelled'])
            ->latest()
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        return view('logistics.driver-mobile', [
            'driver'      => $driver,
            'token'       => $token,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Mise à jour du statut depuis le téléphone du livreur
     * POST /livreur/{token}/statut/{assignment}
     */
    public function updateStatus(Request $request, string $token, DeliveryAssignment $assignment)
    {
        $phone = base64_decode($token, true);

        $driver = DeliveryDriver::where('phone', $phone)
            ->where('is_active', true)
            ->firstOrFail();

        // Sécurité : seul le livreur assigné peut modifier
        abort_if($assignment->driver_id !== $driver->id, 403);

        $action = $request->input('action');

        $transitions = [
            'start_pickup'   => ['status' => 'en_route_pickup',  'item_status' => 'picked_up',   'label' => 'En route vers le magasin'],
            'confirm_pickup' => ['status' => 'picked_up',        'item_status' => 'in_transit',  'label' => 'Produits récupérés'],
            'at_client'      => ['status' => 'at_client',        'item_status' => 'in_transit',  'label' => 'Chez le client'],
            'delivered'      => ['status' => 'delivered',        'item_status' => 'delivered',   'label' => 'Livraison confirmée'],
        ];

        abort_if(! isset($transitions[$action]), 422, 'Action invalide.');

        $transition = $transitions[$action];

        DB::transaction(function () use ($assignment, $transition, $driver) {
            // Mettre à jour l'assignment
            $assignment->update(['status' => $transition['status']]);

            // Mettre à jour le statut de livraison sur l'order_item
            $item = $assignment->orderItem;
            if ($item) {
                try {
                    app(OrderWorkflowService::class)->setDeliveryStatus(
                        $item,
                        $transition['item_status'],
                        null,
                        'logistics',
                        'Mise à jour livreur : ' . $transition['label']
                    );
                } catch (\Throwable) {
                    // Fallback direct si le workflow n'accepte pas la transition
                    $item->forceFill(['delivery_status' => $transition['item_status']])->save();
                }
            }

            // Mettre à jour le statut du livreur lui-même
            if ($transition['status'] === 'delivered') {
                $driver->update(['status' => 'Disponible']);
                $assignment->update(['delivered_at' => now()]);
            } elseif ($transition['status'] === 'en_route_pickup') {
                $driver->update(['status' => 'En livraison']);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'status' => $transition['status']]);
        }

        return redirect("/livreur/{$token}")->with('success', $transition['label'] . ' ✓');
    }

    /**
     * Partage de position GPS du livreur (appelé en arrière-plan depuis JS)
     */
    public function updateLocation(Request $request, string $token, DeliveryAssignment $assignment)
    {
        $phone = base64_decode($token, true);
        $driver = DeliveryDriver::where('phone', $phone)->where('is_active', true)->firstOrFail();
        abort_if($assignment->driver_id !== $driver->id, 403);

        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $driver->update([
            'latitude'     => $data['latitude'],
            'longitude'    => $data['longitude'],
            'last_seen_at' => now(),
            'is_online'    => true,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function mapAssignment(DeliveryAssignment $a): array
    {
        $item  = $a->orderItem;
        $order = $item?->order;
        $shop  = $item?->product?->shop;

        $statusLabels = [
            'assigned'        => ['label' => 'Assigné',               'step' => 0, 'color' => '#6366f1'],
            'en_route_pickup' => ['label' => 'En route vers magasin', 'step' => 1, 'color' => '#f59e0b'],
            'picked_up'       => ['label' => 'Produits récupérés',    'step' => 2, 'color' => '#3b82f6'],
            'at_client'       => ['label' => 'Chez le client',        'step' => 3, 'color' => '#8b5cf6'],
            'delivered'       => ['label' => 'Livré',                 'step' => 4, 'color' => '#16a34a'],
        ];

        $statusInfo = $statusLabels[$a->status] ?? ['label' => $a->status, 'step' => 0, 'color' => '#64748b'];

        return [
            'id'             => $a->id,
            'status'         => $a->status,
            'status_label'   => $statusInfo['label'],
            'status_step'    => $statusInfo['step'],
            'status_color'   => $statusInfo['color'],
            'pickup_address' => $a->pickup_address ?: ($shop?->address ?: 'Adresse non définie'),
            'shop_name'      => $shop?->name ?: 'Boutique',
            'shop_phone'     => $shop?->phone ?: null,
            'delivery_address' => $a->delivery_address ?: ($order ? ($order->delivery_address ?: ($order->delivery_commune ?: 'Adresse client')) : 'Adresse non définie'),
            'client_name'    => $order?->client?->name ?: ($order?->client?->first_name . ' ' . $order?->client?->last_name) ?: 'Client',
            'client_phone'   => $order?->delivery_phone ?: $order?->client?->phone ?: null,
            'order_ref'      => $order?->order_number ?: ('#' . $a->order_id),
            'scheduled_at'   => $a->pickup_scheduled_at?->format('d/m/Y H:i') ?: null,
            'next_action'    => $this->nextAction($a->status),
        ];
    }

    private function nextAction(?string $status): ?array
    {
        return match ($status) {
            'assigned'        => ['action' => 'start_pickup',   'label' => 'Je pars au magasin',         'icon' => '🏍️', 'color' => '#f59e0b'],
            'en_route_pickup' => ['action' => 'confirm_pickup', 'label' => "J'ai récupéré les produits", 'icon' => '📦', 'color' => '#3b82f6'],
            'picked_up'       => ['action' => 'at_client',      'label' => 'Je suis chez le client',     'icon' => '📍', 'color' => '#8b5cf6'],
            'at_client'       => ['action' => 'delivered',      'label' => 'Livraison confirmée',        'icon' => '✅', 'color' => '#16a34a'],
            default           => null,
        };
    }
}
