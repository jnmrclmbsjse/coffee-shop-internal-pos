<?php

namespace Tests\Feature\Filament;

use App\Enums\StockCountMethod;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_stock_item(): void
    {
        $category = StockCategory::factory()->create();

        Livewire::test(CreateStockItem::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Medium Cold Cup',
                'unit' => 'pcs',
                'size' => 'M',
                'count_method' => StockCountMethod::Quantity->value,
                'is_reconciled' => true,
                'is_critical' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stock_item', [
            'name' => 'Medium Cold Cup',
            'is_reconciled' => true,
            'count_method' => 'quantity',
        ]);
    }

    public function test_it_requires_a_category_and_name(): void
    {
        Livewire::test(CreateStockItem::class)
            ->fillForm([
                'category_id' => null,
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'category_id' => 'required',
                'name' => 'required',
            ]);
    }

    public function test_toggling_reconciled_forces_quantity_count_method(): void
    {
        $category = StockCategory::factory()->create();

        // Start on level, then turn on reconciled: the count method is forced to
        // quantity (chk_reconciled_is_quantity) and the item saves cleanly.
        Livewire::test(CreateStockItem::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Small Lid',
                'count_method' => StockCountMethod::Level->value,
            ])
            ->set('data.is_reconciled', true)
            ->assertFormSet(['count_method' => StockCountMethod::Quantity])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockItem::query()->where('name', 'Small Lid')->sole();
        $this->assertTrue($item->is_reconciled);
        $this->assertSame(StockCountMethod::Quantity, $item->count_method);
    }
}
