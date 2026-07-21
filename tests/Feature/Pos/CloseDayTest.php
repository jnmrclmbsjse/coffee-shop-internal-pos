<?php

namespace Tests\Feature\Pos;

use App\Enums\BusinessDayStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Pos\CloseDay;
use App\Models\BusinessDay;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ProductSize;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CloseDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * Mirror the seed day's cash figures: float 2000, cash sales 5000, online 3000,
     * cash_in 100, cash_out 200, expenses 500 => expected_cash 6400 (online excluded).
     */
    protected function seedCashDay(): BusinessDay
    {
        $day = BusinessDay::factory()->create(['cash_float' => 2000]);

        $cash = SalesOrder::factory()->completed(PaymentMethod::Cash)->for($day, 'businessDay')->create(['order_number' => 1]);
        SalesOrderItem::factory()->for($cash, 'order')->create(['quantity' => 100, 'unit_price' => 50]);

        $online = SalesOrder::factory()->completed(PaymentMethod::Online)->for($day, 'businessDay')->create(['order_number' => 2]);
        SalesOrderItem::factory()->for($online, 'order')->create(['quantity' => 20, 'unit_price' => 150]);

        CashMovement::factory()->for($day, 'businessDay')->create(['type' => 'cash_in', 'amount' => 100]);
        CashMovement::factory()->for($day, 'businessDay')->cashOut()->create(['amount' => 200]);
        Expense::factory()->for($day, 'businessDay')->create(['amount' => 500]);

        return $day;
    }

    public function test_cash_summary_excludes_online_sales(): void
    {
        $this->seedCashDay();

        Livewire::test(CloseDay::class)
            ->assertSee('6,400.00')   // expected cash
            ->assertSee('3,000.00');  // online shown, but not in expected
    }

    public function test_closing_snapshots_the_cash_reconciliation_and_marks_closed(): void
    {
        $day = $this->seedCashDay();

        Livewire::test(CloseDay::class)
            ->set('actualCash', '6350')
            ->set('discrepancyReason', 'short on change')
            ->call('close')
            ->assertHasNoErrors();

        $day->refresh();
        $this->assertSame(BusinessDayStatus::Closed, $day->status);
        $this->assertSame('6400.00', $day->expected_cash);
        $this->assertSame('6350.00', $day->actual_cash);
        $this->assertSame('-50.00', $day->cash_discrepancy);
        $this->assertSame('5000.00', $day->cash_sales);
        $this->assertSame('3000.00', $day->online_sales);
    }

    public function test_closing_snapshots_cup_variance_onto_the_closing_sheet(): void
    {
        $day = BusinessDay::factory()->create();
        $cup = StockItem::factory()->cup()->create();
        $size = ProductSize::factory()->create(['cup_stock_item_id' => $cup->id]);

        // opening 100 cups
        $opening = StockCount::factory()->for($day, 'businessDay')->create(['phase' => 'opening']);
        StockCountLine::create(['stock_count_id' => $opening->id, 'stock_item_id' => $cup->id, 'counted_qty' => 100]);

        // a completed sale of 10 -> 10 cups drawn down
        $order = SalesOrder::factory()->completed()->for($day, 'businessDay')->create(['order_number' => 1]);
        SalesOrderItem::factory()->for($order, 'order')->create(['product_size_id' => $size->id, 'quantity' => 10, 'unit_price' => 50]);

        // closing count 85 (expected 90 => variance -5)
        $closing = StockCount::factory()->for($day, 'businessDay')->create(['phase' => 'closing']);
        $line = StockCountLine::create(['stock_count_id' => $closing->id, 'stock_item_id' => $cup->id, 'counted_qty' => 85]);

        Livewire::test(CloseDay::class)
            ->set('actualCash', '0')
            ->call('close')
            ->assertHasNoErrors();

        $line->refresh();
        $this->assertSame('90.00', $line->expected_qty);
        $this->assertSame('-5.00', $line->variance);
    }

    public function test_close_requires_actual_cash(): void
    {
        $this->seedCashDay();

        Livewire::test(CloseDay::class)
            ->set('actualCash', '')
            ->call('close')
            ->assertHasErrors('actualCash');
    }

    public function test_it_warns_when_no_closing_count_exists(): void
    {
        $this->seedCashDay();

        Livewire::test(CloseDay::class)
            ->assertSee('No closing count submitted yet');
    }

    public function test_it_shows_empty_state_without_an_open_day(): void
    {
        Livewire::test(CloseDay::class)->assertSee('No business day is open');
    }

    public function test_guests_are_redirected_from_the_route(): void
    {
        auth()->logout();

        $this->get(route('pos.close'))->assertRedirect();
    }
}
