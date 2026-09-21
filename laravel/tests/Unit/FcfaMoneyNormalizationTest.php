<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class FcfaMoneyNormalizationTest extends TestCase
{
    public function test_product_fcfa_prices_are_normalized_to_whole_francs(): void
    {
        $product = new Product();
        $product->price = 1499.99;
        $product->promo_price = '1200,49';
        $product->price_p1 = 1100.51;

        $this->assertSame(1500, $product->getAttributes()['price']);
        $this->assertSame(1200, $product->getAttributes()['promo_price']);
        $this->assertSame(1101, $product->getAttributes()['price_p1']);
    }

    public function test_null_optional_product_prices_remain_null(): void
    {
        $product = new Product();
        $product->promo_price = null;
        $product->price_p1 = '';

        $this->assertNull($product->getAttributes()['promo_price']);
        $this->assertNull($product->getAttributes()['price_p1']);
    }
}
