<?php

namespace Tests\Unit;

use App\Http\Controllers\CheckoutController;
use App\Models\Cart;
use App\Models\CartItem;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CheckoutSelectionTest extends TestCase
{
    public function test_checkout_selection_keeps_only_requested_cart_items_in_memory(): void
    {
        $cart = new Cart();
        $cart->setRelation('items', collect([
            $this->cartItem(11),
            $this->cartItem(12),
            $this->cartItem(13),
        ]));

        $method = new ReflectionMethod(CheckoutController::class, 'applyCheckoutSelection');
        $method->setAccessible(true);
        $method->invoke(new CheckoutController(), $cart, [11, 13]);

        $this->assertSame([11, 13], $cart->items->pluck('id')->all());
    }

    private function cartItem(int $id): CartItem
    {
        $item = new CartItem();
        $item->id = $id;

        return $item;
    }
}
