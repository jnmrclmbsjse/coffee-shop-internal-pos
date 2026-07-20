<?php

namespace Tests\Feature\Filament;

use App\Enums\DayType;
use App\Enums\StockLevel;
use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\RelationManagers\ParLevelsRelationManager;
use App\Models\StockItem;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParLevelsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_quantity_par_for_a_quantity_item(): void
    {
        $item = StockItem::factory()->create();

        Livewire::test(ParLevelsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditStockItem::class,
        ])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'day_type' => DayType::Normal->value,
                'par_qty' => 40,
                'low_qty_threshold' => 20,
                'urgent_qty_threshold' => 10,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('par_level', [
            'stock_item_id' => $item->id,
            'day_type' => 'normal',
            'par_qty' => 40,
        ]);
    }

    public function test_it_requires_the_quantity_target_for_a_quantity_item(): void
    {
        $item = StockItem::factory()->create();

        Livewire::test(ParLevelsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditStockItem::class,
        ])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'day_type' => DayType::Normal->value,
                'par_qty' => null,
            ])
            ->assertHasActionErrors(['par_qty' => 'required']);
    }

    public function test_it_requires_the_level_target_for_a_level_item(): void
    {
        $item = StockItem::factory()->level()->create();

        Livewire::test(ParLevelsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditStockItem::class,
        ])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'day_type' => DayType::Normal->value,
                'par_level_value' => null,
            ])
            ->assertHasActionErrors(['par_level_value' => 'required']);
    }

    public function test_it_creates_a_level_par_for_a_level_item(): void
    {
        $item = StockItem::factory()->level()->create();

        Livewire::test(ParLevelsRelationManager::class, [
            'ownerRecord' => $item,
            'pageClass' => EditStockItem::class,
        ])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'day_type' => DayType::Peak->value,
                'par_level_value' => StockLevel::Half->value,
                'low_level_threshold' => StockLevel::Quarter->value,
                'urgent_level_threshold' => StockLevel::Low->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('par_level', [
            'stock_item_id' => $item->id,
            'day_type' => 'peak',
            'par_level_value' => 'half',
        ]);
    }
}
