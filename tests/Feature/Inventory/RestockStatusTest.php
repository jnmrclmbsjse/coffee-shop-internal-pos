<?php

namespace Tests\Feature\Inventory;

use App\Enums\CountPhase;
use App\Livewire\Inventory\RestockStatus;
use App\Models\BusinessDay;
use App\Models\ParLevel;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RestockStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_surfaces_computed_status_ordered_by_urgency(): void
    {
        $day = BusinessDay::factory()->create();
        $count = StockCount::factory()->for($day, 'businessDay')->opening()->create();

        $enough = StockItem::factory()->create(['name' => 'Plenty']);
        $urgent = StockItem::factory()->create(['name' => 'Almost Out']);
        ParLevel::factory()->create(['stock_item_id' => $enough->id]);
        ParLevel::factory()->create(['stock_item_id' => $urgent->id]);

        StockCountLine::factory()->for($count, 'stockCount')->create([
            'stock_item_id' => $enough->id, 'counted_qty' => 100,
        ]);
        StockCountLine::factory()->for($count, 'stockCount')->create([
            'stock_item_id' => $urgent->id, 'counted_qty' => 5,
        ]);

        Livewire::test(RestockStatus::class)
            ->assertSeeInOrder(['Almost Out', 'Plenty'])   // urgent before enough
            ->assertSee('Urgent')
            ->assertSee('Enough');
    }

    public function test_it_prefers_the_closing_sheet_once_present(): void
    {
        $day = BusinessDay::factory()->create();
        StockCount::factory()->for($day, 'businessDay')->opening()->create();
        StockCount::factory()->for($day, 'businessDay')->closing()->create();

        Livewire::test(RestockStatus::class)
            ->assertSet('phase', CountPhase::Closing);
    }

    public function test_empty_state_without_a_count(): void
    {
        BusinessDay::factory()->create();

        Livewire::test(RestockStatus::class)
            ->assertSee('No count has been submitted');
    }
}
