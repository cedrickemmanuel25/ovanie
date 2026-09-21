<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CheckoutPaymentOptionsService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CheckoutPaymentOptionsServiceTest extends TestCase
{
    public function test_legacy_direct_payment_flag_does_not_disable_paydunya(): void
    {
        config()->set('paydunya.enabled', true);

        $shop = new Shop(['direct_payment' => true]);
        $product = new Product();
        $product->setRelation('shop', $shop);
        $item = new CartItem();
        $item->setRelation('product', $product);
        $cart = new Cart();
        $cart->setRelation('items', new Collection([$item]));

        $options = app(CheckoutPaymentOptionsService::class)->forCart($cart, 10_000);
        $online = collect($options['methods'])->firstWhere('code', 'paydunya');

        $this->assertFalse($options['cash_on_delivery_required']);
        $this->assertTrue($online['enabled']);
        $this->assertNull($online['reason']);
    }

    public function test_paydunya_is_disabled_below_its_minimum_but_cash_on_delivery_remains_available(): void
    {
        config()->set('paydunya.enabled', true);

        $options = app(CheckoutPaymentOptionsService::class)->forCart(new Cart(), 1);
        $online = collect($options['methods'])->firstWhere('code', 'paydunya');
        $cash = collect($options['methods'])->firstWhere('code', 'cash_on_delivery');

        $this->assertSame(200, $options['online_min_xof']);
        $this->assertFalse($online['enabled']);
        $this->assertNull($online['reason']);
        $this->assertTrue($cash['enabled']);
    }
}
