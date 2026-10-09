<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth'); // protège les pages web
    }

    public function index()
    {
        $user = Auth::user();

        // Ancienne page brute : chaque profil est renvoyé vers son vrai espace commandes.
        if ($user?->role === 'client' && Route::has('client.orders')) {
            return redirect()->route('client.orders', array_filter(['q' => request('q')]));
        }
        if ($user?->role === 'vendor' && Route::has('vendor.orders')) {
            return redirect()->route('vendor.orders');
        }

        $query = Order::latest();

        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        }

        if ($user->role === 'vendor') {
            $query->whereHas('items.product.shop', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $orders = $query->paginate(20);

        return view('orders.index', compact('orders'));
    }

    public function markAsDelivered(Order $order)
    {
        $order->status = 'delivered';
        $order->save();

        $message = "Votre commande #{$order->id} a été livrée. Merci pour votre confiance.";

        SendSmsJob::dispatch($order->phone, $message)->onQueue('sms');

        return back();
    }

}
