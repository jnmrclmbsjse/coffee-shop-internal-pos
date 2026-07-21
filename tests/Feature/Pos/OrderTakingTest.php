<?php

namespace Tests\Feature\Pos;

use App\Enums\OrderStatus;
use App\Livewire\Pos\OrderTaking;
use App\Models\BusinessDay;
use App\Models\ProductSize;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderTakingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array{0: BusinessDay, 1: ProductSize}
     */
    protected function scenario(float $price = 100): array
    {
        return [
            BusinessDay::factory()->create(),
            ProductSize::factory()->create(['price' => $price]),
        ];
    }

    public function test_adding_an_item_creates_a_parked_order_and_recomputes_totals(): void
    {
        [$day, $size] = $this->scenario(100);

        Livewire::test(OrderTaking::class)
            ->call('addItem', $size->id)
            ->assertHasNoErrors();

        $order = SalesOrder::first();
        $this->assertSame(OrderStatus::Parked, $order->status);
        $this->assertSame(1, $order->order_number);
        $this->assertSame($day->id, $order->business_day_id);
        $this->assertSame('100.00', $order->total);
    }

    public function test_repeat_taps_of_the_same_size_merge_into_one_line(): void
    {
        [, $size] = $this->scenario(100);

        Livewire::test(OrderTaking::class)
            ->call('addItem', $size->id)
            ->call('addItem', $size->id)
            ->call('addItem', $size->id);

        $order = SalesOrder::first();
        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertSame('300.00', $order->total);
    }

    public function test_order_number_increments_per_day(): void
    {
        [$day, $size] = $this->scenario();
        SalesOrder::factory()->for($day, 'businessDay')->create(['order_number' => 5]);

        Livewire::test(OrderTaking::class)->call('addItem', $size->id);

        $this->assertSame(6, SalesOrder::where('order_number', '!=', 5)->first()->order_number);
    }

    public function test_per_line_senior_discount_applies_20_percent(): void
    {
        [, $size] = $this->scenario(100);

        $component = Livewire::test(OrderTaking::class)->call('addItem', $size->id);
        $item = SalesOrder::first()->items->first();

        $component->call('setDiscount', $item->id, 'senior');

        $item->refresh();
        $this->assertSame('20.00', $item->discount_amount);
        $this->assertSame('80.00', $item->line_total);
    }

    public function test_decrement_removes_the_line_at_zero(): void
    {
        [, $size] = $this->scenario();

        $component = Livewire::test(OrderTaking::class)->call('addItem', $size->id);
        $item = SalesOrder::first()->items->first();

        $component->call('decrementQty', $item->id);

        $this->assertSame(0, SalesOrderItem::count());
    }

    public function test_completing_sets_status_payment_and_timestamp(): void
    {
        [, $size] = $this->scenario();

        Livewire::test(OrderTaking::class)
            ->call('addItem', $size->id)
            ->call('completeOrder', 'cash')
            ->assertHasNoErrors();

        $order = SalesOrder::first();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->payment_method);
        $this->assertNotNull($order->completed_at);
    }

    public function test_completing_without_items_is_rejected(): void
    {
        $this->scenario();

        Livewire::test(OrderTaking::class)
            ->call('completeOrder', 'cash')
            ->assertHasErrors('order');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_parking_an_empty_order_discards_it(): void
    {
        [, $size] = $this->scenario();

        $component = Livewire::test(OrderTaking::class)->call('addItem', $size->id);
        $item = SalesOrder::first()->items->first();

        $component->call('removeItem', $item->id)->call('park');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_parked_order_can_be_resumed(): void
    {
        [$day] = $this->scenario();
        $parked = SalesOrder::factory()->for($day, 'businessDay')->create(['order_number' => 9]);
        SalesOrderItem::factory()->for($parked, 'order')->create();

        Livewire::test(OrderTaking::class)
            ->call('resumeOrder', $parked->id)
            ->assertSet('orderId', $parked->id);
    }

    public function test_voiding_marks_the_order_and_writes_an_audit_row(): void
    {
        [, $size] = $this->scenario();

        $component = Livewire::test(OrderTaking::class)->call('addItem', $size->id);
        $order = SalesOrder::first();

        $component->set('voidReason', 'wrong order')->call('voidOrder')->assertHasNoErrors();

        $this->assertSame(OrderStatus::Void, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->voided_at);
        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'sales_order',
            'entity_id' => $order->id,
            'action' => 'void',
        ]);
    }

    public function test_voiding_requires_a_reason(): void
    {
        [, $size] = $this->scenario();

        Livewire::test(OrderTaking::class)
            ->call('addItem', $size->id)
            ->set('voidReason', '')
            ->call('voidOrder')
            ->assertHasErrors('voidReason');

        $this->assertSame(OrderStatus::Parked, SalesOrder::first()->status);
    }

    public function test_guests_are_redirected_from_the_route(): void
    {
        auth()->logout();

        $this->get(route('pos.order'))->assertRedirect();
    }
}
