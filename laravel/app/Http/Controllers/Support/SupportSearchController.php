<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorPayout;
use Illuminate\Http\Request;

class SupportSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $results = collect();

        if (mb_strlen($q) >= 2) {
            $like = "%{$q}%";

            User::query()
                ->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->limit(8)->get()->each(fn ($u) => $results->push([
                    'type' => 'Compte', 'icon' => 'user-round', 'title' => $u->name ?: $u->email,
                    'subtitle' => collect([$u->role, $u->email, $u->phone])->filter()->implode(' · '),
                    'status' => $u->status ?? 'actif',
                    'ticket_query' => ['requester_user_id' => $u->id],
                ]));

            Shop::query()->with('user')
                ->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('business_email', 'like', $like)->orWhere('whatsapp', 'like', $like))
                ->limit(8)->get()->each(fn ($shop) => $results->push([
                    'type' => 'Boutique', 'icon' => 'store', 'title' => $shop->name,
                    'subtitle' => collect([$shop->user?->name, $shop->city, $shop->commune])->filter()->implode(' · '),
                    'status' => $shop->status,
                    'ticket_query' => ['shop_id' => $shop->id, 'requester_user_id' => $shop->user_id],
                ]));

            Order::query()->operational()->with('client')
                ->where(fn ($x) => $x->where('order_number', 'like', $like)->orWhere('invoice_number', 'like', $like))
                ->limit(8)->get()->each(fn ($order) => $results->push([
                    'type' => 'Commande', 'icon' => 'package-check', 'title' => $order->order_number,
                    'subtitle' => collect([$order->client?->name, number_format((float) $order->total_amount, 0, ',', ' ').' FCFA'])->filter()->implode(' · '),
                    'status' => $order->status,
                    'ticket_query' => ['order_id' => $order->id, 'requester_user_id' => $order->client_id],
                ]));

            Payment::query()->with(['user', 'order'])
                ->where(fn ($x) => $x->where('reference', 'like', $like)->orWhere('transaction_id', 'like', $like))
                ->limit(8)->get()->each(fn ($payment) => $results->push([
                    'type' => 'Paiement', 'icon' => 'credit-card', 'title' => $payment->reference ?: 'Paiement #'.$payment->id,
                    'subtitle' => collect([$payment->order?->order_number, $payment->user?->name, number_format((float) $payment->amount, 0, ',', ' ').' FCFA'])->filter()->implode(' · '),
                    'status' => $payment->status,
                    'ticket_query' => ['payment_id' => $payment->id, 'order_id' => $payment->order_id, 'requester_user_id' => $payment->user_id],
                ]));

            Shipment::query()->with(['order.client', 'shop'])
                ->where(fn ($x) => $x->where('tracking_number', 'like', $like)->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)))
                ->limit(8)->get()->each(fn ($shipment) => $results->push([
                    'type' => 'Livraison', 'icon' => 'truck', 'title' => $shipment->tracking_number ?: 'Livraison #'.$shipment->id,
                    'subtitle' => collect([$shipment->order?->order_number, $shipment->shop?->name])->filter()->implode(' · '),
                    'status' => $shipment->status,
                    'ticket_query' => ['shipment_id' => $shipment->id, 'order_id' => $shipment->order_id, 'shop_id' => $shipment->shop_id, 'requester_user_id' => $shipment->order?->client_id],
                ]));

            DeliveryDriver::query()
                ->where(fn ($x) => $x->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(8)->get()->each(fn ($driver) => $results->push([
                    'type' => 'Livreur partenaire', 'icon' => 'bike', 'title' => $driver->name,
                    'subtitle' => collect([$driver->phone, $driver->vehicle, $driver->zone])->filter()->implode(' · '),
                    'status' => $driver->onboarding_status,
                    'ticket_query' => ['requester_type' => 'driver', 'delivery_driver_id' => $driver->id, 'context_type' => 'driver', 'context_id' => $driver->id],
                ]));

            DeliveryAssignment::query()->with('driver')
                ->where('mission_number', 'like', $like)
                ->limit(8)->get()->each(fn ($mission) => $results->push([
                    'type' => 'Mission livraison', 'icon' => 'route', 'title' => $mission->resolved_mission_number,
                    'subtitle' => collect([$mission->driver?->name, 'Commande #'.$mission->order_id])->filter()->implode(' · '),
                    'status' => $mission->status,
                    'ticket_query' => ['requester_type' => 'driver', 'delivery_driver_id' => $mission->driver_id, 'context_type' => 'delivery_assignment', 'context_id' => $mission->id],
                ]));

            VendorPayout::query()->with(['vendor', 'shop', 'order'])
                ->where(fn ($x) => $x->where('payout_reference', 'like', $like)->orWhere('batch_reference', 'like', $like))
                ->limit(8)->get()->each(fn ($payout) => $results->push([
                    'type' => 'Reversement vendeur', 'icon' => 'wallet-cards', 'title' => $payout->payout_reference ?: 'Reversement #'.$payout->id,
                    'subtitle' => collect([$payout->shop?->name, $payout->order?->order_number, number_format((float) $payout->payout_amount, 0, ',', ' ').' FCFA'])->filter()->implode(' · '),
                    'status' => $payout->status,
                    'ticket_query' => ['requester_type' => 'vendor', 'shop_id' => $payout->shop_id, 'requester_user_id' => $payout->vendor_id, 'order_id' => $payout->order_id, 'context_type' => 'vendor_payout', 'context_id' => $payout->id],
                ]));

            SupportTicket::query()
                ->where(fn ($x) => $x->where('reference', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('requester_name', 'like', $like)->orWhere('requester_phone', 'like', $like))
                ->limit(10)->get()->each(fn ($ticket) => $results->push([
                    'type' => 'Dossier Support', 'icon' => 'life-buoy', 'title' => $ticket->reference.' · '.$ticket->subject,
                    'subtitle' => collect([$ticket->requester_name, $ticket->requester_phone])->filter()->implode(' · '),
                    'status' => $ticket->status,
                    'url' => route('support.tickets.show', $ticket),
                    'ticket_query' => [],
                ]));
        }

        return view('support.search.index', compact('q', 'results'));
    }
}
