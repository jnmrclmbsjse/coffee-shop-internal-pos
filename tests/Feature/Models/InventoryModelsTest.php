<?php

namespace Tests\Feature\Models;

use App\Enums\CountPhase;
use App\Enums\DayType;
use App\Enums\ParStatus;
use App\Enums\StockLevel;
use App\Models\BusinessDay;
use App\Models\ParLevel;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_factories_persist_with_uuid_keys(): void
    {
        $day = BusinessDay::factory()->create();
        $count = StockCount::factory()->for($day, 'businessDay')->create();
        $line = StockCountLine::factory()->for($count, 'stockCount')->create();
        $movement = StockMovement::factory()->for($day, 'businessDay')->create();

        $this->assertIsString($day->getKey());
        $this->assertTrue(BusinessDay::whereKey($day->getKey())->exists());
        $this->assertTrue(StockCountLine::whereKey($line->getKey())->exists());
        $this->assertTrue(StockMovement::whereKey($movement->getKey())->exists());
    }

    public function test_relationships_resolve_across_a_count(): void
    {
        $day = BusinessDay::factory()->create();
        $count = StockCount::factory()->for($day, 'businessDay')->create();
        $line = StockCountLine::factory()->for($count, 'stockCount')->create();

        $this->assertTrue($count->businessDay->is($day));
        $this->assertTrue($day->stockCounts->contains($count));
        $this->assertTrue($count->lines->contains($line));
        $this->assertTrue($line->stockItem->exists);
    }

    public function test_trigger_fills_status_for_quantity_items(): void
    {
        // par_qty 40, low 20, urgent 10 (ParLevelFactory defaults), normal day.
        [$item, $count] = $this->quantityScenario();

        $this->assertSame(ParStatus::Enough, $this->countedQty($item, $count, 50));   // >= par
        $this->assertSame(ParStatus::BelowPar, $this->countedQty($item, $count, 30));  // < par, > low
        $this->assertSame(ParStatus::Low, $this->countedQty($item, $count, 15));       // <= low
        $this->assertSame(ParStatus::Urgent, $this->countedQty($item, $count, 5));     // <= urgent
    }

    public function test_trigger_fills_status_for_level_items(): void
    {
        // par half, low quarter, urgent low (ParLevelFactory::level defaults), normal day.
        $item = StockItem::factory()->level()->create();
        ParLevel::factory()->level()->create(['stock_item_id' => $item->id, 'day_type' => DayType::Normal]);
        $count = StockCount::factory()->create();

        $this->assertSame(ParStatus::Enough, $this->countedLevel($item, $count, StockLevel::Full));
        $this->assertSame(ParStatus::BelowPar, $this->countedLevel($item, $count, StockLevel::Third));
        $this->assertSame(ParStatus::Low, $this->countedLevel($item, $count, StockLevel::Quarter));
        $this->assertSame(ParStatus::Urgent, $this->countedLevel($item, $count, StockLevel::Low));
    }

    public function test_trigger_rejects_the_wrong_count_field_per_method(): void
    {
        $quantityItem = StockItem::factory()->create();
        $levelItem = StockItem::factory()->level()->create();
        $count = StockCount::factory()->create();

        // quantity item counted by level -> rejected
        $this->expectException(QueryException::class);
        StockCountLine::factory()->for($count, 'stockCount')->create([
            'stock_item_id' => $quantityItem->id,
            'counted_qty' => null,
            'counted_level' => StockLevel::Full,
        ]);
    }

    public function test_trigger_rejects_a_missing_level_for_a_level_item(): void
    {
        $levelItem = StockItem::factory()->level()->create();
        $count = StockCount::factory()->create();

        $this->expectException(QueryException::class);
        StockCountLine::factory()->for($count, 'stockCount')->create([
            'stock_item_id' => $levelItem->id,
            'counted_qty' => 10,
            'counted_level' => null,
        ]);
    }

    public function test_a_business_day_allows_only_one_count_per_phase(): void
    {
        $day = BusinessDay::factory()->create();
        StockCount::factory()->for($day, 'businessDay')->opening()->create();

        $this->expectException(QueryException::class);
        StockCount::factory()->for($day, 'businessDay')->opening()->create();
    }

    public function test_computed_columns_are_not_mass_assignable(): void
    {
        $line = new StockCountLine;

        $this->assertFalse($line->isFillable('computed_status'));
        $this->assertFalse($line->isFillable('expected_qty'));
        $this->assertFalse($line->isFillable('variance'));
    }

    public function test_inventory_status_view_surfaces_the_computed_status(): void
    {
        [$item, $count] = $this->quantityScenario();
        $line = $this->line($item, $count, ['counted_qty' => 15]);

        $status = DB::table('v_inventory_status')
            ->where('stock_item_id', $item->id)
            ->where('phase', CountPhase::Opening->value)
            ->value('computed_status');

        $this->assertSame(ParStatus::Low->value, $status);
        $this->assertSame(ParStatus::Low, $line->fresh()->computed_status);
    }

    /**
     * A quantity item with default par (40/20/10, normal) and a fresh opening
     * count on a normal day.
     *
     * @return array{0: StockItem, 1: StockCount}
     */
    private function quantityScenario(): array
    {
        $item = StockItem::factory()->create();
        ParLevel::factory()->create(['stock_item_id' => $item->id, 'day_type' => DayType::Normal]);
        $count = StockCount::factory()->create();

        return [$item, $count];
    }

    private function countedQty(StockItem $item, StockCount $count, float $qty): ParStatus
    {
        return $this->line($item, $count, ['counted_qty' => $qty])->fresh()->computed_status;
    }

    private function countedLevel(StockItem $item, StockCount $count, StockLevel $level): ParStatus
    {
        return $this->line($item, $count, ['counted_qty' => null, 'counted_level' => $level])
            ->fresh()->computed_status;
    }

    /**
     * Upsert one line for the item on the count (unique on stock_count_id + item),
     * so repeated calls in a scenario re-count the same item.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function line(StockItem $item, StockCount $count, array $attrs): StockCountLine
    {
        return StockCountLine::updateOrCreate(
            ['stock_count_id' => $count->id, 'stock_item_id' => $item->id],
            $attrs,
        );
    }
}
