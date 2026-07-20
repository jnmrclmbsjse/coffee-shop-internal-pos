<?php

namespace Tests\Feature\Inventory;

use App\Enums\CountPhase;
use App\Enums\StockLevel;
use App\Livewire\Inventory\CountSheet;
use App\Models\BusinessDay;
use App\Models\ParLevel;
use App\Models\Staff;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class CountSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_opening_sheet_lists_only_critical_items(): void
    {
        BusinessDay::factory()->create();
        $critical = StockItem::factory()->critical()->create(['name' => 'Medium Cup']);
        $ordinary = StockItem::factory()->create(['name' => 'Straws']);

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->assertSee('Medium Cup')
            ->assertDontSee('Straws');
    }

    public function test_closing_sheet_lists_all_active_items(): void
    {
        BusinessDay::factory()->create();
        StockItem::factory()->critical()->create(['name' => 'Medium Cup']);
        StockItem::factory()->create(['name' => 'Straws']);

        Livewire::test(CountSheet::class, ['phase' => 'closing'])
            ->assertSee('Medium Cup')
            ->assertSee('Straws');
    }

    public function test_it_submits_a_quantity_count_and_the_trigger_sets_status(): void
    {
        $day = BusinessDay::factory()->create();
        $staff = Staff::factory()->create();
        $item = StockItem::factory()->critical()->create();
        ParLevel::factory()->create(['stock_item_id' => $item->id]); // par 40 / low 20 / urgent 10

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->set('submittedBy', $staff->id)
            ->set("counts.{$item->id}.qty", '15')
            ->call('submit')
            ->assertHasNoErrors();

        $line = StockCountLine::first();
        $this->assertSame('15.00', $line->counted_qty);
        $this->assertSame('low', $line->computed_status->value);
        $this->assertDatabaseHas('stock_count', [
            'business_day_id' => $day->id,
            'phase' => CountPhase::Opening->value,
            'submitted_by_id' => $staff->id,
        ]);
    }

    public function test_it_submits_a_level_count_for_level_items(): void
    {
        BusinessDay::factory()->create();
        $staff = Staff::factory()->create();
        $item = StockItem::factory()->level()->critical()->create();

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->set('submittedBy', $staff->id)
            ->set("counts.{$item->id}.level", StockLevel::Half->value)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(StockLevel::Half, StockCountLine::first()->counted_level);
        $this->assertNull(StockCountLine::first()->counted_qty);
    }

    public function test_submitted_by_is_required(): void
    {
        BusinessDay::factory()->create();
        $item = StockItem::factory()->critical()->create();

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->set("counts.{$item->id}.qty", '10')
            ->call('submit')
            ->assertHasErrors('submittedBy');

        $this->assertDatabaseCount('stock_count', 0);
    }

    public function test_uncounted_items_block_submission(): void
    {
        BusinessDay::factory()->create();
        $staff = Staff::factory()->create();
        $item = StockItem::factory()->critical()->create();

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->set('submittedBy', $staff->id)
            ->call('submit')
            ->assertHasErrors("counts.{$item->id}.qty");

        $this->assertDatabaseCount('stock_count', 0);
    }

    public function test_an_already_submitted_phase_is_read_only(): void
    {
        $day = BusinessDay::factory()->create();
        StockItem::factory()->critical()->create();
        StockCount::factory()->for($day, 'businessDay')->opening()->create();

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->assertSee('Submitted')
            ->assertDontSee('Submit opening count');
    }

    public function test_it_shows_an_empty_state_without_an_open_day(): void
    {
        StockItem::factory()->critical()->create();

        Livewire::test(CountSheet::class, ['phase' => 'opening'])
            ->assertSee('No business day is open');
    }

    public function test_the_screen_requires_authentication(): void
    {
        Auth::logout();

        $this->get(route('inventory.opening'))->assertRedirect();
    }
}
