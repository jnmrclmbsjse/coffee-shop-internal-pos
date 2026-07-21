<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\BusinessDay;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_factories_persist_with_uuid_keys(): void
    {
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->for($order, 'order')->create();
        $cash = CashMovement::factory()->create();
        $expense = Expense::factory()->create();

        $this->assertIsString($order->getKey());
        $this->assertTrue(SalesOrder::whereKey($order->getKey())->exists());
        $this->assertTrue(SalesOrderItem::whereKey($item->getKey())->exists());
        $this->assertTrue(CashMovement::whereKey($cash->getKey())->exists());
        $this->assertTrue(Expense::whereKey($expense->getKey())->exists());
    }

    public function test_relationships_resolve_across_an_order(): void
    {
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->for($order, 'order')->create();

        $this->assertTrue($item->order->is($order));
        $this->assertTrue($order->items->contains($item));
        $this->assertTrue($order->businessDay->exists);
        $this->assertTrue($item->productSize->exists);
    }

    public function test_line_money_is_generated_from_quantity_and_price(): void
    {
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->for($order, 'order')->create([
            'quantity' => 3,
            'unit_price' => 50,
        ]);

        $item->refresh();

        $this->assertSame('150.00', $item->gross_amount);
        $this->assertSame('0.00', $item->discount_amount);
        $this->assertSame('150.00', $item->line_total);
    }

    public function test_per_line_senior_discount_is_a_generated_20_percent(): void
    {
        // seed.sql reference line: 150 gross -> 30 discount -> 120 line_total.
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->senior()->for($order, 'order')->create([
            'quantity' => 1,
            'unit_price' => 150,
        ]);

        $item->refresh();

        $this->assertSame('150.00', $item->gross_amount);
        $this->assertSame('30.00', $item->discount_amount);
        $this->assertSame('120.00', $item->line_total);
    }

    public function test_order_totals_trigger_recomputes_on_insert_update_delete(): void
    {
        $order = SalesOrder::factory()->create();

        $a = SalesOrderItem::factory()->for($order, 'order')->create(['quantity' => 2, 'unit_price' => 100]);
        $b = SalesOrderItem::factory()->senior()->for($order, 'order')->create(['quantity' => 1, 'unit_price' => 150]);

        // 200 (a) + 150 (b) subtotal; 30 discount on b; 320 total.
        $order->refresh();
        $this->assertSame('350.00', $order->subtotal);
        $this->assertSame('30.00', $order->discount_amount);
        $this->assertSame('320.00', $order->total);

        // Update: bump a to qty 3 (300).
        $a->update(['quantity' => 3]);
        $order->refresh();
        $this->assertSame('450.00', $order->subtotal);
        $this->assertSame('420.00', $order->total);

        // Delete b: back to just a.
        $b->delete();
        $order->refresh();
        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertSame('300.00', $order->total);
    }

    public function test_completed_order_requires_a_payment_method(): void
    {
        $order = SalesOrder::factory()->create();

        $this->expectException(QueryException::class);

        // chk_completed_has_payment: completed with null payment_method is rejected.
        $order->update(['status' => OrderStatus::Completed, 'payment_method' => null]);
    }

    public function test_completed_order_with_payment_is_accepted(): void
    {
        $order = SalesOrder::factory()->completed(PaymentMethod::Online)->create();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(PaymentMethod::Online, $order->fresh()->payment_method);
    }

    public function test_order_number_is_unique_per_business_day(): void
    {
        $day = BusinessDay::factory()->create();
        SalesOrder::factory()->for($day, 'businessDay')->create(['order_number' => 1]);

        $this->expectException(QueryException::class);

        SalesOrder::factory()->for($day, 'businessDay')->create(['order_number' => 1]);
    }
}
