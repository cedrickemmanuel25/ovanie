<?php

namespace App\Services\Sms\Templates;

use App\Models\Order;

class SmsTemplate
{
    public static function orderStatus(Order $order): string
    {
        return "IMOo: Commande #{$order->id} est {$order->status}. Merci.";
    }

    public static function paymentConfirmed(Order $order): string
    {
        return "IMOo: Paiement reçu pour la commande #{$order->id}.";
    }

    public static function shipped(Order $order): string
    {
        return "IMOo: Commande #{$order->id} expédiée.";
    }
}
