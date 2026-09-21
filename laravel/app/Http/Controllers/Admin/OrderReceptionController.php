<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReceptionForm;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderReceptionController extends Controller
{
    public function show($orderId)
    {
        $order = Order::with(['client'])
            ->findOrFail($orderId);

        $form = OrderReceptionForm::with('items')
            ->where('order_id', $order->id)
            ->firstOrFail();

        return view('admin.orders.reception-show', compact('order', 'form'));
    }


   

    public function downloadPdf($orderId)
    {
        $order = \App\Models\Order::with(['client'])->findOrFail($orderId);

        $form = \App\Models\OrderReceptionForm::with('items')
            ->where('order_id', $order->id)
            ->firstOrFail();

        $pdf = Pdf::loadView('admin.orders.reception-pdf', compact('order', 'form'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('OVANIE-formulaire-reception-' . $order->order_number . '.pdf');
    }

}
