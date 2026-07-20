<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Livewire\Inventory\Movements;
use App\Models\BusinessDay;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_records_a_delivery(): void
    {
        $day = BusinessDay::factory()->create();
        $item = StockItem::factory()->cup()->create();

        Livewire::test(Movements::class)
            ->set('stockItemId', $item->id)
            ->set('type', StockMovementType::Delivery->value)
            ->set('quantity', '50')
            ->set('reason', 'AM delivery')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_movement', [
            'business_day_id' => $day->id,
            'stock_item_id' => $item->id,
            'type' => 'delivery',
            'quantity' => 50,
        ]);
    }

    public function test_quantity_must_be_positive(): void
    {
        BusinessDay::factory()->create();
        $item = StockItem::factory()->create();

        Livewire::test(Movements::class)
            ->set('stockItemId', $item->id)
            ->set('quantity', '0')
            ->call('record')
            ->assertHasErrors('quantity');

        $this->assertDatabaseCount('stock_movement', 0);
    }

    public function test_item_is_required(): void
    {
        BusinessDay::factory()->create();

        Livewire::test(Movements::class)
            ->set('quantity', '10')
            ->call('record')
            ->assertHasErrors('stockItemId');
    }

    public function test_recorded_movements_are_listed(): void
    {
        $day = BusinessDay::factory()->create();
        StockMovement::factory()->for($day, 'businessDay')->wastage()->create([
            'stock_item_id' => StockItem::factory()->create(['name' => 'Spilled Milk']),
            'quantity' => 3,
        ]);

        Livewire::test(Movements::class)
            ->assertSee('Spilled Milk')
            ->assertSee('Wastage');
    }
}
