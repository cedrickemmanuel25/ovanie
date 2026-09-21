<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use App\Models\OrderReceptionFormItem;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    public function test_it_has_a_reception_item_relationship(): void
    {
        $orderItem = new OrderItem;
        $relation = $orderItem->receptionItem();

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertInstanceOf(OrderReceptionFormItem::class, $relation->getRelated());
        $this->assertSame('order_reception_form_items.order_item_id', $relation->getQualifiedForeignKeyName());
        $this->assertSame('id', $relation->getLocalKeyName());
    }
}
